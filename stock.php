<?php
// Stock management with prepared statements + CSRF + auto stock_name

require 'config.php';
require 'csrf.php';
include 'navbar.php';

// Require logged-in user + MFA + correct role
if (
    !isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['mfa_ok']) ||
    $_SESSION['mfa_ok'] !== true ||
    !in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: login.php');
    exit();
}


$message = '';

// Handle deletion
if (isset($_GET['delete'], $_GET['stock_id'])) {
    $stock_id = (int)$_GET['stock_id'];

    $stmt = $conn->prepare("DELETE FROM stock WHERE stock_id = ?");
    $stmt->bind_param("i", $stock_id);

    if ($stmt->execute()) {
        $message = "Stock entry deleted.";
        if (function_exists('log_event')) {
            log_event('info', "Stock deleted: ID $stock_id");
        }
    } else {
        $message = "Error deleting stock.";
        if (function_exists('log_event')) {
            log_event('error', "Error deleting stock ID $stock_id: " . $stmt->error);
        }
    }

    header('Location: stock.php');
    exit();
}

// Handle create / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $expiry      = trim($_POST['expiry'] ?? '');
    $quantity    = trim($_POST['quantity'] ?? '');
    $rate        = trim($_POST['rate'] ?? '');
    $medicine_id = (int)($_POST['medicine_id'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $stock_id    = isset($_POST['stock_id']) ? (int)$_POST['stock_id'] : null;

    if ($expiry === '' || $quantity === '' || $rate === '' || !$medicine_id || !$supplier_id) {
        $message = "All fields are required.";
    } else {
        // Get medicine name to auto-generate stock_name
        $stmtMed = $conn->prepare("SELECT medicine_name FROM medicine4 WHERE medicine_id = ?");
        $stmtMed->bind_param("i", $medicine_id);
        $stmtMed->execute();
        $medRes = $stmtMed->get_result();
        $medRow = $medRes->fetch_assoc();
        $stock_name = $medRow ? $medRow['medicine_name'] . ' stock' : 'Stock-' . $medicine_id;

        if ($stock_id) {
            // UPDATE
            $stmt = $conn->prepare(
                "UPDATE stock SET 
                     stock_name  = ?,
                     expiry      = ?,
                     quantity    = ?,
                     rate        = ?,
                     medicine_id = ?,
                     supplier_id = ?
                 WHERE stock_id = ?"
            );
            $stmt->bind_param(
                "ssdiisi",
                $stock_name,
                $expiry,
                $quantity,
                $rate,
                $medicine_id,
                $supplier_id,
                $stock_id
            );

            if ($stmt->execute()) {
                $message = "Stock successfully updated!";
                if (function_exists('log_event')) {
                    log_event('info', "Stock updated: ID $stock_id");
                }
            } else {
                $message = "Error updating stock.";
                if (function_exists('log_event')) {
                    log_event('error', "Error updating stock ID $stock_id: " . $stmt->error);
                }
            }

        } else {
            // INSERT
            $stmt = $conn->prepare(
                "INSERT INTO stock (stock_name, expiry, quantity, rate, medicine_id, supplier_id)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssdiis",
                $stock_name,
                $expiry,
                $quantity,
                $rate,
                $medicine_id,
                $supplier_id
            );

            if ($stmt->execute()) {
                $message = "Stock successfully added!";
                if (function_exists('log_event')) {
                    log_event('info', "Stock added for medicine ID $medicine_id");
                }
            } else {
                $message = "Error adding stock.";
                if (function_exists('log_event')) {
                    log_event('error', "Error adding stock for medicine ID $medicine_id: " . $stmt->error);
                }
            }
        }

        header('Location: stock.php');
        exit();
    }
}

// Fetch stock for editing
$edit_stock = null;
if (isset($_GET['edit'], $_GET['stock_id'])) {
    $stock_id = (int)$_GET['stock_id'];

    $stmt = $conn->prepare("SELECT * FROM stock WHERE stock_id = ?");
    $stmt->bind_param("i", $stock_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_stock = $result->fetch_assoc();
}

// Fetch dropdown data + list
$medicines  = $conn->query("SELECT medicine_id, medicine_name FROM medicine4 ORDER BY medicine_name ASC");
$suppliers  = $conn->query("SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name ASC");
$stock_list = $conn->query("SELECT * FROM stock ORDER BY stock_id DESC");

// helper functions
function getMedicineName($id, $conn) {
    $stmt = $conn->prepare("SELECT medicine_name FROM medicine4 WHERE medicine_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['medicine_name'] ?? '';
}
function getSupplierName($id, $conn) {
    $stmt = $conn->prepare("SELECT supplier_name FROM supplier WHERE supplier_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['supplier_name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Management | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
            color: #333;
        }
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .form-container select,
        .form-container input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-container button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
        }
        .form-container button:hover {
            background-color: #0056b3;
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
        table td a {
            text-decoration: none;
            color: #007bff;
        }
        table td a:hover {
            color: #0056b3;
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
        .msg {
            margin-top: 10px;
            color: #2563eb;
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
        <h2><?php echo $edit_stock ? 'Edit Stock' : 'Create Stock'; ?></h2>
        <h3>Manage Stock Information</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Edit Stock Form -->
    <section class="form-container">
        <form action="stock.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <?php if ($edit_stock): ?>
                <input type="hidden" name="stock_id" value="<?php echo (int)$edit_stock['stock_id']; ?>">
            <?php endif; ?>

            <label for="expiry">Expiry Date</label>
            <input type="date" name="expiry"
                   value="<?php echo $edit_stock ? htmlspecialchars($edit_stock['expiry']) : ''; ?>" required>

            <label for="quantity">Quantity</label>
            <input type="number" name="quantity"
                   value="<?php echo $edit_stock ? htmlspecialchars($edit_stock['quantity']) : ''; ?>" required>

            <label for="rate">Rate</label>
            <input type="number" step="0.01" name="rate"
                   value="<?php echo $edit_stock ? htmlspecialchars($edit_stock['rate']) : ''; ?>" required>

            <label for="medicine_id">Medicine</label>
            <select name="medicine_id" required>
                <?php while ($med = $medicines->fetch_assoc()): ?>
                    <option value="<?php echo (int)$med['medicine_id']; ?>"
                        <?php echo $edit_stock && $edit_stock['medicine_id'] == $med['medicine_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($med['medicine_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="supplier_id">Supplier</label>
            <select name="supplier_id" required>
                <?php while ($sup = $suppliers->fetch_assoc()): ?>
                    <option value="<?php echo (int)$sup['supplier_id']; ?>"
                        <?php echo $edit_stock && $edit_stock['supplier_id'] == $sup['supplier_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sup['supplier_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" name="<?php echo $edit_stock ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_stock ? 'Update Stock' : 'Create Stock'; ?>
            </button>
        </form>
    </section>

    <!-- Stock List -->
    <section>
        <h3>Stock List</h3>
        <table>
            <thead>
            <tr>
                <th>Stock / Batch</th>
                <th>Expiry</th>
                <th>Quantity</th>
                <th>Rate</th>
                <th>Medicine</th>
                <th>Supplier</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $stock_list->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['stock_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['expiry']); ?></td>
                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($row['rate']); ?></td>
                    <td><?php echo htmlspecialchars(getMedicineName($row['medicine_id'], $conn)); ?></td>
                    <td><?php echo htmlspecialchars(getSupplierName($row['supplier_id'], $conn)); ?></td>
                    <td>
                        <a href="stock.php?edit=1&stock_id=<?php echo (int)$row['stock_id']; ?>">Edit</a> |
                        <a href="stock.php?delete=1&stock_id=<?php echo (int)$row['stock_id']; ?>"
                           onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </section>
</main>

<footer class="container">
    <small>&copy; 2024 Pharma Management System</small>
</footer>
</body>
</html>
