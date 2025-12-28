<?php
// Doctor management with prepared statements + CSRF

require 'config.php';    // DB + session
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


$message        = '';
$edit_doctor    = null;

// -------------------- HANDLE DELETE (GET) --------------------
if (isset($_GET['delete'], $_GET['doctor_id'])) {
    $doctor_id = (int)$_GET['doctor_id'];

    $stmt = $conn->prepare("DELETE FROM Doctor WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctor_id);

    if ($stmt->execute()) {
        $message = "Doctor successfully deleted.";
        if (function_exists('log_event')) {
            log_event('info', "Doctor deleted: ID $doctor_id");
        }
    } else {
        $message = "Error deleting doctor.";
        if (function_exists('log_event')) {
            log_event('error', "Error deleting doctor ID $doctor_id: " . $stmt->error);
        }
    }

    header('Location: doctor.php');
    exit();
}

// -------------------- HANDLE CREATE / UPDATE (POST) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $doctor_name    = trim($_POST['doctor_name'] ?? '');
    $doctor_address = trim($_POST['doctor_address'] ?? '');
    $doctor_phone   = trim($_POST['doctor_phone'] ?? '');
    $doctor_email   = trim($_POST['doctor_email'] ?? '');
    $doctor_dob     = trim($_POST['doctor_dob'] ?? '');
    $doctor_doj     = trim($_POST['doctor_doj'] ?? '');
    $doctor_id      = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;

    if ($doctor_name === '' || $doctor_address === '' || $doctor_phone === '' ||
        $doctor_email === '' || $doctor_dob === '' || $doctor_doj === '') {
        $message = "All fields are required.";
    } elseif (!filter_var($doctor_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        if (isset($_POST['submit_create'])) {
            // ---------- INSERT ----------
            $stmt = $conn->prepare(
                "INSERT INTO Doctor
                 (doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssss",
                $doctor_name,
                $doctor_address,
                $doctor_phone,
                $doctor_email,
                $doctor_dob,
                $doctor_doj
            );

            if ($stmt->execute()) {
                $message = "Doctor successfully added!";
                if (function_exists('log_event')) {
                    log_event('info', "Doctor added: $doctor_name");
                }
            } else {
                $message = "Error adding doctor.";
                if (function_exists('log_event')) {
                    log_event('error', "Error adding doctor $doctor_name: " . $stmt->error);
                }
            }

        } elseif (isset($_POST['submit_update']) && $doctor_id) {
            // ---------- UPDATE ----------
            $stmt = $conn->prepare(
                "UPDATE Doctor
                 SET doctor_name = ?, doctor_address = ?, doctor_phone = ?, doctor_email = ?,
                     doctor_DOB  = ?, doctor_DOJ = ?
                 WHERE doctor_id = ?"
            );
            $stmt->bind_param(
                "ssssssi",
                $doctor_name,
                $doctor_address,
                $doctor_phone,
                $doctor_email,
                $doctor_dob,
                $doctor_doj,
                $doctor_id
            );

            if ($stmt->execute()) {
                $message = "Doctor successfully updated!";
                if (function_exists('log_event')) {
                    log_event('info', "Doctor updated: ID $doctor_id");
                }
            } else {
                $message = "Error updating doctor.";
                if (function_exists('log_event')) {
                    log_event('error', "Error updating doctor ID $doctor_id: " . $stmt->error);
                }
            }
        }
    }
}

// -------------------- FETCH DOCTOR FOR EDIT --------------------
if (isset($_GET['edit'], $_GET['doctor_id'])) {
    $doctor_id = (int)$_GET['doctor_id'];

    $stmt = $conn->prepare("SELECT * FROM Doctor WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result      = $stmt->get_result();
    $edit_doctor = $result->fetch_assoc();
}

// -------------------- FETCH ALL DOCTORS FOR LIST --------------------
$doctor_result = $conn->query("SELECT * FROM Doctor ORDER BY doctor_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doctor Management | Pharma Management System</title>
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
        <h2>Doctor Management</h2>
        <h3>Manage Doctor Records</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Update Doctor Form -->
    <section class="form-container">
        <h3><?php echo $edit_doctor ? 'Update Doctor' : 'Create Doctor'; ?></h3>
        <form action="doctor.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <input type="hidden" name="doctor_id"
                   value="<?php echo $edit_doctor ? (int)$edit_doctor['doctor_id'] : ''; ?>">

            <label for="doctor_name">Doctor Name</label>
            <input type="text" name="doctor_name"
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['doctor_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="doctor_address">Doctor Address</label>
            <input type="text" name="doctor_address"
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['doctor_address'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="doctor_phone">Doctor Phone</label>
            <input type="text" name="doctor_phone"
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['doctor_phone'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="doctor_email">Doctor Email</label>
            <input type="email" name="doctor_email"
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['doctor_email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="doctor_dob">Date of Birth</label>
            <input type="date" name="doctor_dob"
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['doctor_DOB'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="doctor_doj">Date of Joining</label>
            <input type="date" name="doctor_doj"
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['doctor_DOJ'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <button type="submit" name="<?php echo $edit_doctor ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_doctor ? 'Update Doctor' : 'Create Doctor'; ?>
            </button>
        </form>
    </section>

    <!-- Display All Doctors -->
    <section>
        <h3>All Doctors</h3>
        <table>
            <thead>
            <tr>
                <th>Doctor Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Date of Birth</th>
                <th>Date of Joining</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($doctor = $doctor_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doctor['doctor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($doctor['doctor_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($doctor['doctor_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($doctor['doctor_DOB'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($doctor['doctor_DOJ'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a href="doctor.php?edit=1&doctor_id=<?php echo (int)$doctor['doctor_id']; ?>">Edit</a> |
                        <a href="doctor.php?delete=1&doctor_id=<?php echo (int)$doctor['doctor_id']; ?>"
                           onclick="return confirm('Are you sure you want to delete this doctor?');">
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
    <small>Pharma Management System | &copy; 2024</small>
</footer>
</body>
</html>
