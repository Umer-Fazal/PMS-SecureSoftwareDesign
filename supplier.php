<?php
require 'config.php';   // DB + session
require 'csrf.php';     // CSRF helpers
include 'navbar.php';

// Optional: only allow admin/staff to access this page
// Require logged-in user + MFA + correct role
if (
    !isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['mfa_ok']) ||
    $_SESSION['mfa_ok'] !== true ||
    !in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: login.php');
    exit();
}


$message      = '';
$message_type = '';

// ---------- HANDLE CREATE / UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();

        $supplier_name    = trim($_POST['supplier_name'] ?? '');
        $supplier_address = trim($_POST['supplier_address'] ?? '');
        $supplier_phone   = trim($_POST['supplier_phone'] ?? '');
        $supplier_email   = trim($_POST['supplier_email'] ?? '');
        $supplier_status  = trim($_POST['supplier_status'] ?? '');
        $supplier_id_raw  = $_POST['supplier_id'] ?? '';

        // Basic validation
        if ($supplier_name === '' || $supplier_address === '' || $supplier_phone === '' || $supplier_email === '') {
            $message      = 'All fields are required.';
            $message_type = 'error';
        } elseif (!filter_var($supplier_email, FILTER_VALIDATE_EMAIL)) {
            $message      = 'Invalid email format.';
            $message_type = 'error';
        } elseif (!in_array($supplier_status, ['Active', 'Inactive'], true)) {
            $message      = 'Invalid supplier status.';
            $message_type = 'error';
        } else {
            // CREATE
            if (isset($_POST['submit_create'])) {
                $stmt = $conn->prepare(
                    "INSERT INTO supplier 
                     (supplier_name, supplier_address, supplier_phone, supplier_email, supplier_status)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "sssss",
                    $supplier_name,
                    $supplier_address,
                    $supplier_phone,
                    $supplier_email,
                    $supplier_status
                );

                if ($stmt->execute()) {
                    $message      = 'Supplier successfully added!';
                    $message_type = 'success';
                } else {
                    $message      = 'Error adding supplier. Please try again.';
                    $message_type = 'error';
                }
            }

            // UPDATE
            if (isset($_POST['submit_update'])) {
                $supplier_id = (int)$supplier_id_raw;

                if ($supplier_id <= 0) {
                    $message      = 'Invalid supplier ID.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE supplier
                         SET supplier_name = ?, 
                             supplier_address = ?, 
                             supplier_phone = ?, 
                             supplier_email = ?, 
                             supplier_status = ?
                         WHERE supplier_id = ?"
                    );
                    $stmt->bind_param(
                        "sssssi",
                        $supplier_name,
                        $supplier_address,
                        $supplier_phone,
                        $supplier_email,
                        $supplier_status,
                        $supplier_id
                    );

                    if ($stmt->execute()) {
                        $message      = 'Supplier successfully updated!';
                        $message_type = 'success';
                    } else {
                        $message      = 'Error updating supplier. Please try again.';
                        $message_type = 'error';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $message      = 'Security error. Please reload the page and try again.';
        $message_type = 'error';
    }
}

// ---------- HANDLE DELETE (simple, ID sanitized) ----------
if (isset($_GET['delete'], $_GET['supplier_id'])) {
    $supplier_id = (int)$_GET['supplier_id'];

    if ($supplier_id > 0) {
        $stmt = $conn->prepare("DELETE FROM supplier WHERE supplier_id = ?");
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
    }

    header('Location: supplier.php');
    exit();
}

// ---------- FETCH SUPPLIER FOR EDIT (if any) ----------
$edit_supplier = null;
if (isset($_GET['edit'], $_GET['supplier_id'])) {
    $edit_id = (int)$_GET['supplier_id'];
    if ($edit_id > 0) {
        $stmt = $conn->prepare(
            "SELECT supplier_id, supplier_name, supplier_address, supplier_phone, supplier_email, supplier_status
             FROM supplier
             WHERE supplier_id = ?"
        );
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_supplier = $res->fetch_assoc();
    }
}

// ---------- FETCH ALL SUPPLIERS FOR TABLE ----------
$list_stmt = $conn->prepare(
    "SELECT supplier_id, supplier_name, supplier_address, supplier_phone, supplier_email, supplier_status
     FROM supplier
     ORDER BY supplier_name ASC"
);
$list_stmt->execute();
$supplier_result = $list_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier Management | Pharma Management System</title>
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
        .form-container input,
        .form-container textarea {
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

        .msg-success {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }

        .msg-error {
            color: #721c24;
            background: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
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
            <h2>Supplier Management</h2>
            <h3>Manage Supplier Information</h3>
        </hgroup>

        <!-- Display success/error message -->
        <?php if ($message !== ''): ?>
            <p class="<?php echo $message_type === 'success' ? 'msg-success' : 'msg-error'; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <!-- Create / Edit Supplier Form -->
        <section class="form-container">
            <h3><?php echo $edit_supplier ? 'Edit Supplier' : 'Create Supplier'; ?></h3>
            <form action="supplier.php" method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <?php if ($edit_supplier): ?>
                    <input type="hidden" name="supplier_id"
                           value="<?php echo (int)$edit_supplier['supplier_id']; ?>">
                <?php endif; ?>

                <label for="supplier_name">Supplier Name</label>
                <input type="text" name="supplier_name"
                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['supplier_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                       required>

                <label for="supplier_address">Supplier Address</label>
                <input type="text" name="supplier_address"
                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['supplier_address'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                       required>

                <label for="supplier_phone">Supplier Phone</label>
                <input type="text" name="supplier_phone"
                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['supplier_phone'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                       required>

                <label for="supplier_email">Supplier Email</label>
                <input type="email" name="supplier_email"
                       value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['supplier_email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                       required>

                <label for="supplier_status">Supplier Status</label>
                <select name="supplier_status" required>
                    <option value="Active"
                        <?php echo ($edit_supplier && $edit_supplier['supplier_status'] === 'Active') ? 'selected' : ''; ?>>
                        Active
                    </option>
                    <option value="Inactive"
                        <?php echo ($edit_supplier && $edit_supplier['supplier_status'] === 'Inactive') ? 'selected' : ''; ?>>
                        Inactive
                    </option>
                </select>

                <button type="submit" name="<?php echo $edit_supplier ? 'submit_update' : 'submit_create'; ?>">
                    <?php echo $edit_supplier ? 'Update Supplier' : 'Create Supplier'; ?>
                </button>
            </form>
        </section>

        <!-- Display All Suppliers -->
        <section>
            <h3>All Suppliers</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($supplier = $supplier_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supplier['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="supplier.php?edit=1&supplier_id=<?php echo (int)$supplier['supplier_id']; ?>">Edit</a> | 
                                <a href="supplier.php?delete=1&supplier_id=<?php echo (int)$supplier['supplier_id']; ?>"
                                   onclick="return confirm('Are you sure you want to delete this supplier?');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>

    <footer>
        <small>&copy; 2024 Pharma Management System</small>
    </footer>
</body>
</html>
