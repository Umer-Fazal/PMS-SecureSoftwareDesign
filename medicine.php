<?php
// Medicine management with prepared statements + CSRF

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


// Message holder
$message = '';

// Handle medicine deletion (GET)
if (isset($_GET['delete'], $_GET['medicine_id'])) {
    $medicine_id = (int)$_GET['medicine_id'];

    $stmt = $conn->prepare("DELETE FROM medicine4 WHERE medicine_id = ?");
    $stmt->bind_param("i", $medicine_id);

    if ($stmt->execute()) {
        $message = "Medicine successfully deleted.";
        if (function_exists('log_event')) {
            log_event('info', "Medicine deleted: ID $medicine_id");
        }
    } else {
        $message = "Error deleting medicine.";
        if (function_exists('log_event')) {
            log_event('error', "Error deleting medicine ID $medicine_id: " . $stmt->error);
        }
    }

    header('Location: medicine.php');
    exit();
}

// Handle create / update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $medicine_name   = trim($_POST['medicine_name'] ?? '');
    $medicine_price  = trim($_POST['medicine_price'] ?? '');
    $medicine_mfg    = trim($_POST['medicine_mfg'] ?? '');
    $medicine_expiry = trim($_POST['medicine_expiry'] ?? '');
    $potency         = trim($_POST['potency'] ?? '');
    $manufacturer_id = trim($_POST['manufacturer_id'] ?? '');
    $is_expired      = trim($_POST['is_expired'] ?? 'N');

    if ($medicine_name === '' || $medicine_price === '' || $medicine_mfg === '' || $medicine_expiry === '' || $manufacturer_id === '') {
        $message = "All required fields must be filled.";
    } else {
        if (isset($_POST['submit_create'])) {
            // INSERT
            $stmt = $conn->prepare(
                "INSERT INTO medicine4 
                 (medicine_name, medicine_price, medicine_MFG, medicine_Expiry, potency, manufacturer_id, is_expired)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            // bind all as strings to keep it simple and safe
            $stmt->bind_param(
                "sssssis",
                $medicine_name,
                $medicine_price,
                $medicine_mfg,
                $medicine_expiry,
                $potency,
                $manufacturer_id,
                $is_expired
            );

            if ($stmt->execute()) {
                $message = "Medicine successfully added!";
                if (function_exists('log_event')) {
                    log_event('info', "Medicine added: $medicine_name");
                }
            } else {
                $message = "Error adding medicine.";
                if (function_exists('log_event')) {
                    log_event('error', "Error adding medicine $medicine_name: " . $stmt->error);
                }
            }

        } elseif (isset($_POST['submit_update']) && isset($_POST['medicine_id'])) {
            // UPDATE
            $medicine_id = (int)$_POST['medicine_id'];

            $stmt = $conn->prepare(
                "UPDATE medicine4 SET
                     medicine_name   = ?,
                     medicine_price  = ?,
                     medicine_MFG    = ?,
                     medicine_Expiry = ?,
                     potency         = ?,
                     manufacturer_id = ?,
                     is_expired      = ?
                 WHERE medicine_id = ?"
            );
            $stmt->bind_param(
                "sssssisI",
                $medicine_name,
                $medicine_price,
                $medicine_mfg,
                $medicine_expiry,
                $potency,
                $manufacturer_id,
                $is_expired,
                $medicine_id
            );
        }
    }
}

// Fetch a specific medicine for editing (if edit button clicked)
$edit_medicine = null;
if (isset($_GET['edit'], $_GET['medicine_id'])) {
    $medicine_id = (int)$_GET['medicine_id'];

    $stmt = $conn->prepare("SELECT * FROM medicine4 WHERE medicine_id = ?");
    $stmt->bind_param("i", $medicine_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_medicine = $result->fetch_assoc();
}

// Fetch all medicines for listing
$medicine_result = $conn->query("SELECT * FROM medicine4 ORDER BY medicine_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medicine Management | Pharma Management System</title>
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
        <h2>Medicine Management</h2>
        <h3>Manage Medicine Information</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Edit Medicine Form -->
    <section class="form-container">
        <h3><?php echo $edit_medicine ? 'Edit Medicine' : 'Create Medicine'; ?></h3>
        <form action="medicine.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <?php if ($edit_medicine): ?>
                <input type="hidden" name="medicine_id"
                       value="<?php echo (int)$edit_medicine['medicine_id']; ?>">
            <?php endif; ?>

            <label for="medicine_name">Medicine Name</label>
            <input type="text" name="medicine_name"
                   value="<?php echo $edit_medicine ? htmlspecialchars($edit_medicine['medicine_name']) : ''; ?>"
                   required>

            <label for="medicine_price">Price</label>
            <input type="number" step="0.01" name="medicine_price"
                   value="<?php echo $edit_medicine ? htmlspecialchars($edit_medicine['medicine_price']) : ''; ?>"
                   required>

            <label for="medicine_mfg">Manufacture Date</label>
            <input type="date" name="medicine_mfg"
                   value="<?php echo $edit_medicine ? htmlspecialchars($edit_medicine['medicine_MFG']) : ''; ?>"
                   required>

            <label for="medicine_expiry">Expiry Date</label>
            <input type="date" name="medicine_expiry"
                   value="<?php echo $edit_medicine ? htmlspecialchars($edit_medicine['medicine_Expiry']) : ''; ?>"
                   required>

            <label for="potency">Potency</label>
            <input type="text" name="potency"
                   value="<?php echo $edit_medicine ? htmlspecialchars($edit_medicine['potency']) : ''; ?>">

            <label for="manufacturer_id">Manufacturer ID</label>
            <input type="number" name="manufacturer_id"
                   value="<?php echo $edit_medicine ? htmlspecialchars($edit_medicine['manufacturer_id']) : ''; ?>"
                   required>

            <label for="is_expired">Is Expired</label>
            <select name="is_expired">
                <option value="N" <?php echo $edit_medicine && $edit_medicine['is_expired'] === 'N' ? 'selected' : ''; ?>>No</option>
                <option value="Y" <?php echo $edit_medicine && $edit_medicine['is_expired'] === 'Y' ? 'selected' : ''; ?>>Yes</option>
            </select>

            <button type="submit" name="<?php echo $edit_medicine ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_medicine ? 'Update Medicine' : 'Create Medicine'; ?>
            </button>
        </form>
    </section>

    <!-- List of Medicines -->
    <section>
        <table>
            <thead>
            <tr>
                <th>Medicine ID</th>
                <th>Name</th>
                <th>Price</th>
                <th>MFG Date</th>
                <th>Expiry Date</th>
                <th>Potency</th>
                <th>Manufacturer ID</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($medicine = $medicine_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int)$medicine['medicine_id']; ?></td>
                    <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                    <td><?php echo htmlspecialchars($medicine['medicine_price']); ?></td>
                    <td><?php echo htmlspecialchars($medicine['medicine_MFG']); ?></td>
                    <td><?php echo htmlspecialchars($medicine['medicine_Expiry']); ?></td>
                    <td><?php echo htmlspecialchars($medicine['potency']); ?></td>
                    <td><?php echo htmlspecialchars($medicine['manufacturer_id']); ?></td>
                    <td><?php echo $medicine['is_expired'] === 'Y' ? 'Expired' : 'Active'; ?></td>
                    <td>
                        <a href="medicine.php?edit=1&medicine_id=<?php echo (int)$medicine['medicine_id']; ?>">Edit</a> |
                        <a href="medicine.php?delete=1&medicine_id=<?php echo (int)$medicine['medicine_id']; ?>"
                           onclick="return confirm('Are you sure?')">Delete</a>
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
