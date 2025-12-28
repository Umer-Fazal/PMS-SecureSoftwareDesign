<?php
// Orders management (admin/staff) with prepared statements + CSRF

require 'config.php';   // DB + session
require 'csrf.php';
include 'navbar.php';

$message = '';
$error   = '';

// -------------------- OPTIONAL: ROLE CHECK --------------------
// Only allow admin / staff to view this page
// Require logged-in user + MFA + correct role
if (
    !isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['mfa_ok']) ||
    $_SESSION['mfa_ok'] !== true ||
    !in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: login.php');
    exit();
}


// -------------------- HANDLE ORDER STATUS UPDATE --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        verify_csrf_token();

        $order_id     = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $order_status = $_POST['order_status'] ?? '';

        $allowed_statuses = ['Pending', 'Delivered'];

        if ($order_id <= 0) {
            $error = "Invalid order.";
        } elseif (!in_array($order_status, $allowed_statuses, true)) {
            $error = "Invalid status value.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE Orders 
                 SET order_status = ? 
                 WHERE order_id = ?"
            );
            $stmt->bind_param("si", $order_status, $order_id);

            if ($stmt->execute()) {
                $message = "Order status successfully updated!";
                if (function_exists('log_event')) {
                    log_event('info', "Order #$order_id status changed to $order_status by user_id " . ($_SESSION['user_id'] ?? 'unknown'));
                }
            } else {
                $error = "Error updating order status.";
                if (function_exists('log_event')) {
                    log_event('error', "Failed to update order #$order_id: " . $stmt->error);
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error updating order.";
        if (function_exists('log_event')) {
            log_event('error', "Exception updating order: " . $e->getMessage());
        }
    }
}

// -------------------- FETCH ALL ORDERS --------------------
// You can join Patient to show patient_name as well
$order_stmt = $conn->prepare(
    "SELECT 
         o.order_id,
         o.patient_id,
         o.order_date,
         o.order_status,
         p.patient_name
     FROM Orders o
     LEFT JOIN Patient p ON o.patient_id = p.patient_id
     ORDER BY o.order_date DESC, o.order_id DESC"
);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
            color: #333;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #007bff;
            color: white;
        }

        table td form {
            display: inline;
        }

        table td select {
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        table td button {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            background-color: #007bff;
            color: #fff;
            cursor: pointer;
            font-size: 0.9rem;
        }

        table td button:hover {
            background-color: #0056b3;
        }

        .msg {
            margin-top: 10px;
            color: #16a34a;
        }

        .err {
            margin-top: 10px;
            color: #dc2626;
        }

        footer {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: center;
            margin-top: 40px;
        }

        footer small {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<header>
    <nav class="container-fluid">
        <ul>
            <li><strong>Pharma Management System</strong></li>
        </ul>
    </nav>
</header>

<main class="container">
    <hgroup>
        <h2>Orders</h2>
        <h3>Manage All Orders</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <section>
        <h3>All Orders</h3>
        <table>
            <thead>
            <tr>
                <th>Order ID</th>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>Order Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($order_result->num_rows === 0): ?>
                <tr>
                    <td colspan="6">No orders found.</td>
                </tr>
            <?php else: ?>
                <?php while ($order = $order_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int)$order['order_id']; ?></td>
                        <td><?php echo (int)$order['patient_id']; ?></td>
                        <td>
                            <?php
                            echo $order['patient_name']
                                ? htmlspecialchars($order['patient_name'], ENT_QUOTES, 'UTF-8')
                                : '<em>Unknown</em>';
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($order['order_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($order['order_status'] === 'Pending'): ?>
                                <form action="orders.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                           value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="order_id"
                                           value="<?php echo (int)$order['order_id']; ?>">

                                    <select name="order_status" required>
                                        <option value="Pending"
                                            <?php echo ($order['order_status'] === 'Pending') ? 'selected' : ''; ?>>
                                            Pending
                                        </option>
                                        <option value="Delivered"
                                            <?php echo ($order['order_status'] === 'Delivered') ? 'selected' : ''; ?>>
                                            Delivered
                                        </option>
                                    </select>

                                    <button type="submit" name="update_status">Update Status</button>
                                </form>
                            <?php else: ?>
                                <span>Already Delivered</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<footer class="container">
    <small>&copy; 2024 Pharma Management System</small>
</footer>
</body>
</html>
