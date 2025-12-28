<?php
// Secure medicine ordering and cart handling

require 'config.php';   // DB + session_start
require 'csrf.php';

// Check if the patient is logged in
if (!isset($_SESSION['patient_email'])) {
    header('Location: login_patient.php');
    exit();
}

$patient_email = $_SESSION['patient_email'];

// Fetch patient information safely
$stmt = $conn->prepare(
    "SELECT patient_id, patient_name, patient_email 
     FROM Patient 
     WHERE patient_email = ? 
     LIMIT 1"
);
$stmt->bind_param("s", $patient_email);
$stmt->execute();
$patient_result = $stmt->get_result();
$patient        = $patient_result->fetch_assoc();

if (!$patient) {
    header('Location: login_patient.php');
    exit();
}

$patient_id   = (int)$patient['patient_id'];
$patient_name = $patient['patient_name'];

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$error   = '';

// -------------------- ADD TO CART --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    try {
        verify_csrf_token();

        $medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;
        $quantity    = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

        if ($medicine_id <= 0 || $quantity <= 0) {
            $error = "Invalid medicine or quantity.";
        } else {
            // Optional: check if medicine exists & stock > 0 before adding to cart
            $check_stmt = $conn->prepare(
                "SELECT s.quantity
                 FROM stock s
                 WHERE s.medicine_id = ?"
            );
            $check_stmt->bind_param("i", $medicine_id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            $stock_row = $check_res->fetch_assoc();

            if (!$stock_row || (int)$stock_row['quantity'] <= 0) {
                $error = "Selected medicine is not available in stock.";
            } else {
                if (isset($_SESSION['cart'][$medicine_id])) {
                    $_SESSION['cart'][$medicine_id] += $quantity;
                } else {
                    $_SESSION['cart'][$medicine_id] = $quantity;
                }
                $message = "Item added to cart.";
            }
        }
    } catch (Exception $e) {
        $error = "Could not add item to cart.";
        if (function_exists('log_event')) {
            log_event('error', "Error in add_to_cart: " . $e->getMessage());
        }
    }
}

// -------------------- REMOVE FROM CART --------------------
if (isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$remove_id]);
    $message = "Item removed from cart.";
}

// -------------------- PROCEED TO PAYMENT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_payment'])) {
    try {
        verify_csrf_token();

        if (empty($_SESSION['cart'])) {
            $error = "Your cart is empty.";
        } else {
            $payment_method = $_POST['payment_method'] ?? 'Cash on Delivery';
            $order_date     = date('Y-m-d');
            $order_status   = 'Pending';

            $cart_items   = [];
            $total_amount = 0.0;

            // 1. Validate stock and compute total based on DB prices
            foreach ($_SESSION['cart'] as $medicine_id => $quantity) {
                $medicine_id = (int)$medicine_id;
                $quantity    = (int)$quantity;

                if ($medicine_id <= 0 || $quantity <= 0) {
                    continue;
                }

                $details_stmt = $conn->prepare(
                    "SELECT s.quantity AS stock_qty, s.rate, m.medicine_name
                     FROM stock s
                     JOIN medicine4 m ON s.medicine_id = m.medicine_id
                     WHERE s.medicine_id = ?
                     LIMIT 1"
                );
                $details_stmt->bind_param("i", $medicine_id);
                $details_stmt->execute();
                $details_res = $details_stmt->get_result();
                $row         = $details_res->fetch_assoc();

                if (!$row) {
                    $error = "One of the selected medicines no longer exists.";
                    break;
                }

                $stock_qty = (int)$row['stock_qty'];
                $rate      = (float)$row['rate'];

                if ($stock_qty < $quantity) {
                    $error = "Not enough stock for medicine: " . $row['medicine_name'];
                    break;
                }

                $line_total   = $rate * $quantity;
                $total_amount += $line_total;

                $cart_items[] = [
                    'medicine_id'   => $medicine_id,
                    'quantity'      => $quantity,
                    'rate'          => $rate,
                    'medicine_name' => $row['medicine_name']
                ];
            }

            if ($error === '') {
                // 2. Begin transaction
                $conn->begin_transaction();

                // 3. Insert into Orders
                $order_stmt = $conn->prepare(
                    "INSERT INTO Orders (patient_id, order_date, order_status)
                     VALUES (?, ?, ?)"
                );
                $order_stmt->bind_param("iss", $patient_id, $order_date, $order_status);

                if (!$order_stmt->execute()) {
                    throw new Exception("Error inserting order: " . $order_stmt->error);
                }

                $order_id = $conn->insert_id;

                // 4. Insert OrderItems + update stock
                $item_stmt = $conn->prepare(
                    "INSERT INTO OrderItems (order_id, medicine_id, Quantity)
                     VALUES (?, ?, ?)"
                );
                $stock_stmt = $conn->prepare(
                    "UPDATE stock
                     SET quantity = quantity - ?
                     WHERE medicine_id = ? AND quantity >= ?"
                );

                foreach ($cart_items as $item) {
                    $mid = $item['medicine_id'];
                    $qty = $item['quantity'];

                    // Insert order item
                    $item_stmt->bind_param("iii", $order_id, $mid, $qty);
                    if (!$item_stmt->execute()) {
                        throw new Exception("Error inserting order item: " . $item_stmt->error);
                    }

                    // Update stock
                    $stock_stmt->bind_param("iii", $qty, $mid, $qty);
                    $stock_stmt->execute();
                    if ($stock_stmt->affected_rows === 0) {
                        throw new Exception("Insufficient stock during update for medicine ID $mid.");
                    }
                }

                // 5. Insert Bill
                $bill_stmt = $conn->prepare(
                    "INSERT INTO Bill (order_id, patient_id, BILL_Date, TotalAmount, PaymentMethod)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $bill_stmt->bind_param("iidss", $order_id, $patient_id, $order_date, $total_amount, $payment_method);

                if (!$bill_stmt->execute()) {
                    throw new Exception("Error inserting bill: " . $bill_stmt->error);
                }

                // 6. Commit transaction
                $conn->commit();

                // Clear cart
                unset($_SESSION['cart']);

                // Set confirmation message and redirect
                $_SESSION['order_message'] = "Your order #$order_id is placed successfully and will be delivered in 60 minutes.";
                header("Location: order_confirmation.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "There was an error processing your order. Please try again.";
        if (function_exists('log_event')) {
            log_event('error', "Order processing failed: " . $e->getMessage());
        }
    }
}

// -------------------- FETCH AVAILABLE MEDICINES (STOCK > 0) --------------------
$medicines_result = $conn->query(
    "SELECT m.medicine_id, m.medicine_name, s.quantity, s.rate
     FROM stock s
     JOIN medicine4 m ON s.medicine_id = m.medicine_id
     WHERE s.quantity > 0
     ORDER BY m.medicine_name ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Items | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
            color: #333;
        }
        table {
            width: 100%;
            margin-top: 10px;
        }
        table th, table td {
            padding: 8px;
        }
        .msg {
            margin-top: 10px;
            color: #16a34a;
        }
        .err {
            margin-top: 10px;
            color: #dc2626;
        }
    </style>
</head>
<body>
<nav class="container-fluid">
    <ul>
        <li><strong>Pharma Management System</strong></li>
    </ul>
    <ul>
        <li><a href="patient_dashboard.php">Back to Dashboard</a></li>
    </ul>
</nav>

<main class="container">
    <div class="grid">
        <section>
            <hgroup>
                <h2>Order Medicine</h2>
                <h3>Available Medicines</h3>
            </hgroup>

            <?php if ($message !== ''): ?>
                <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php
            if ($medicines_result && $medicines_result->num_rows > 0):
            ?>
                <table>
                    <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($medicine = $medicines_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($medicine['medicine_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($medicine['rate'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int)$medicine['quantity']; ?></td>
                            <td>
                                <form action="order_items.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                           value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="medicine_id"
                                           value="<?php echo (int)$medicine['medicine_id']; ?>">
                                    <input type="number" name="quantity"
                                           min="1"
                                           max="<?php echo (int)$medicine['quantity']; ?>"
                                           value="1" required>
                                    <button type="submit" name="add_to_cart" class="button">Add to Cart</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No medicines available in stock.</p>
            <?php endif; ?>
        </section>
    </div>

    <section>
        <hgroup>
            <h3>Your Cart</h3>
        </hgroup>

        <?php
        if (!empty($_SESSION['cart'])) {
            $display_total = 0;
            echo "<table>";
            echo "<thead><tr><th>Medicine Name</th><th>Quantity</th><th>Price</th><th>Total</th><th>Action</th></tr></thead><tbody>";

            foreach ($_SESSION['cart'] as $medicine_id => $quantity) {
                $medicine_id = (int)$medicine_id;
                $quantity    = (int)$quantity;

                $details_stmt = $conn->prepare(
                    "SELECT m.medicine_name, s.rate
                     FROM stock s
                     JOIN medicine4 m ON s.medicine_id = m.medicine_id
                     WHERE s.medicine_id = ?
                     LIMIT 1"
                );
                $details_stmt->bind_param("i", $medicine_id);
                $details_stmt->execute();
                $details_res = $details_stmt->get_result();
                $details     = $details_res->fetch_assoc();

                if (!$details) {
                    continue;
                }

                $medicine_name = $details['medicine_name'];
                $rate          = (float)$details['rate'];
                $line_total    = $rate * $quantity;
                $display_total += $line_total;

                echo "<tr>";
                echo "<td>" . htmlspecialchars($medicine_name, ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . $quantity . "</td>";
                echo "<td>" . $rate . "</td>";
                echo "<td>" . $line_total . "</td>";
                echo "<td><a href='order_items.php?remove=" . $medicine_id . "' class='button' style='background-color:red;'>Remove</a></td>";
                echo "</tr>";
            }

            echo "</tbody></table>";
            echo "<p><strong>Total Amount: " . $display_total . "</strong></p>";
            ?>

            <form action="order_items.php" method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <select name="payment_method">
                    <option value="Cash on Delivery" selected>Cash on Delivery</option>
                    <!-- Add more payment options if needed -->
                </select>
                <button type="submit" name="proceed_payment" class="button">Proceed to Payment</button>
            </form>

            <?php
        } else {
            echo "<p>Your cart is empty. Please add items to the cart first.</p>";
        }
        ?>
    </section>
</main>

<footer class="container">
    <small>© 2024 Pharma Management System</small>
</footer>
</body>
</html>
