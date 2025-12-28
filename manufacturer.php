<?php
// Manufacturer management (prepared statements + CSRF + safe output)

require 'config.php';   // DB + session
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


$message            = '';
$edit_manufacturer  = null;

// -------------------- DELETE MANUFACTURER (GET) --------------------
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    $stmt = $conn->prepare("DELETE FROM manufacturer WHERE manufacturer_id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Manufacturer successfully deleted!";
        if (function_exists('log_event')) {
            log_event('info', "Manufacturer deleted: ID $delete_id");
        }
    } else {
        $message = "Error deleting manufacturer.";
        if (function_exists('log_event')) {
            log_event('error', "Error deleting manufacturer ID $delete_id: " . $stmt->error);
        }
    }

    header('Location: manufacturer.php');
    exit();
}

// -------------------- CREATE / UPDATE (POST) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $manufacturer_id      = isset($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id'] : null;
    $manufacturer_name    = trim($_POST['manufacturer_name'] ?? '');
    $manufacturer_address = trim($_POST['manufacturer_address'] ?? '');
    $manufacturer_phone   = trim($_POST['manufacturer_phone'] ?? '');
    $manufacturer_email   = trim($_POST['manufacturer_email'] ?? '');

    if ($manufacturer_name === '' || $manufacturer_address === '' ||
        $manufacturer_phone === '' || $manufacturer_email === '') {

        $message = "All fields are required.";
    } elseif (!filter_var($manufacturer_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        if (isset($_POST['submit_create']) && !$manufacturer_id) {
            // ---------- INSERT ----------
            $stmt = $conn->prepare(
                "INSERT INTO manufacturer
                 (manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssss",
                $manufacturer_name,
                $manufacturer_address,
                $manufacturer_phone,
                $manufacturer_email
            );

            if ($stmt->execute()) {
                $message = "Manufacturer successfully added!";
                if (function_exists('log_event')) {
                    log_event('info', "Manufacturer added: $manufacturer_name");
                }
            } else {
                $message = "Error adding manufacturer.";
                if (function_exists('log_event')) {
                    log_event('error', "Error adding manufacturer $manufacturer_name: " . $stmt->error);
                }
            }

        } elseif (isset($_POST['submit_update']) && $manufacturer_id) {
            // ---------- UPDATE ----------
            $stmt = $conn->prepare(
                "UPDATE manufacturer
                 SET manufacturer_name = ?, manufacturer_address = ?, manufacturer_phone = ?, manufacturer_email = ?
                 WHERE manufacturer_id = ?"
            );
            $stmt->bind_param(
                "ssssi",
                $manufacturer_name,
                $manufacturer_address,
                $manufacturer_phone,
                $manufacturer_email,
                $manufacturer_id
            );

            if ($stmt->execute()) {
                $message = "Manufacturer record updated successfully!";
                if (function_exists('log_event')) {
                    log_event('info', "Manufacturer updated: ID $manufacturer_id");
                }
            } else {
                $message = "Error updating manufacturer record.";
                if (function_exists('log_event')) {
                    log_event('error', "Error updating manufacturer ID $manufacturer_id: " . $stmt->error);
                }
            }
        }
    }
}

// -------------------- FETCH MANUFACTURER FOR EDIT (GET) --------------------
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];

    $stmt = $conn->prepare("SELECT * FROM manufacturer WHERE manufacturer_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_result      = $stmt->get_result();
    $edit_manufacturer = $edit_result->fetch_assoc();
}

// -------------------- FETCH ALL MANUFACTURERS --------------------
$manufacturers_result = $conn->query(
    "SELECT manufacturer_id, manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email
     FROM manufacturer
     ORDER BY manufacturer_name ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manufacturer Management | Pharma Management System</title>
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
        <h2>Manufacturer Management</h2>
        <h3>Manage Manufacturer Records</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Edit Manufacturer Form -->
    <section class="form-container">
        <h3><?php echo $edit_manufacturer ? 'Edit Manufacturer' : 'Create Manufacturer'; ?></h3>
        <form action="manufacturer.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <input type="hidden" name="manufacturer_id"
                   value="<?php echo $edit_manufacturer ? (int)$edit_manufacturer['manufacturer_id'] : ''; ?>">

            <label for="manufacturer_name">Manufacturer Name</label>
            <input type="text" name="manufacturer_name"
                   value="<?php echo $edit_manufacturer ? htmlspecialchars($edit_manufacturer['manufacturer_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="manufacturer_address">Address</label>
            <input type="text" name="manufacturer_address"
                   value="<?php echo $edit_manufacturer ? htmlspecialchars($edit_manufacturer['manufacturer_address'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="manufacturer_phone">Phone</label>
            <input type="text" name="manufacturer_phone"
                   value="<?php echo $edit_manufacturer ? htmlspecialchars($edit_manufacturer['manufacturer_phone'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="manufacturer_email">Email</label>
            <input type="email" name="manufacturer_email"
                   value="<?php echo $edit_manufacturer ? htmlspecialchars($edit_manufacturer['manufacturer_email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <button type="submit" name="<?php echo $edit_manufacturer ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_manufacturer ? 'Update Manufacturer' : 'Create Manufacturer'; ?>
            </button>
        </form>
    </section>

    <!-- Display all Manufacturers -->
    <section>
        <h3>Existing Manufacturers</h3>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Address</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($manufacturers_result->num_rows === 0): ?>
                <tr>
                    <td colspan="6">No manufacturers found.</td>
                </tr>
            <?php else: ?>
                <?php while ($manufacturer = $manufacturers_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int)$manufacturer['manufacturer_id']; ?></td>
                        <td><?php echo htmlspecialchars($manufacturer['manufacturer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($manufacturer['manufacturer_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($manufacturer['manufacturer_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($manufacturer['manufacturer_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="manufacturer.php?edit_id=<?php echo (int)$manufacturer['manufacturer_id']; ?>">Edit</a> |
                            <a href="manufacturer.php?delete_id=<?php echo (int)$manufacturer['manufacturer_id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this manufacturer?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<footer>
    <small>Pharma Management System | &copy; 2024</small>
</footer>
</body>
</html>
