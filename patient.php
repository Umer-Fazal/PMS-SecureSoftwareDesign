<?php
// Patient management (admin/staff) with prepared statements + CSRF

require 'config.php';   // DB + session + encrypt_field/decrypt_field
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

$message      = '';
$error        = '';
$edit_patient = null;

// helper: try to decrypt, if it fails, return original (for old rows/plaintext)
function decrypt_or_plain($value) {
    $plain = decrypt_field($value);
    if ($plain === false || $plain === null || $plain === '') {
        return $value;
    }
    return $plain;
}

// -------------------- OPTIONAL: ROLE CHECK --------------------
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    header('Location: login.php');
    exit();
}

// -------------------- DELETE PATIENT (GET) --------------------
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    if ($delete_id > 0) {
        try {
            // Start transaction
            $conn->begin_transaction();

            // 1 - delete related bills for this patient
            $billStmt = $conn->prepare(
                "DELETE FROM Bill WHERE patient_id = ?"
            );
            $billStmt->bind_param("i", $delete_id);
            $billStmt->execute();

            // if you also have feedback, orders etc tied to patient_id,
            // you can delete them here in the same way.

            // 2 - now delete patient row
            $stmt = $conn->prepare(
                "DELETE FROM Patient WHERE patient_id = ?"
            );
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();

            // Commit all deletions
            $conn->commit();

            $message = "Patient and related records deleted.";
            if (function_exists('log_event')) {
                log_event('info', "Patient deleted: ID $delete_id by user_id " . ($_SESSION['user_id'] ?? 'unknown'));
            }

        } catch (mysqli_sql_exception $e) {
            // Rollback if anything failed
            $conn->rollback();
            $error = "Cannot delete this patient because there are related records (bills or orders).";

            if (function_exists('log_event')) {
                log_event('error', "Delete patient failed ID $delete_id: " . $e->getMessage());
            }
        }
    } else {
        $error = "Invalid patient ID.";
    }
}


// -------------------- CREATE / UPDATE PATIENT (POST) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();

        $patient_id      = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $patient_name    = trim($_POST['patient_name'] ?? '');
        $patient_gender  = trim($_POST['patient_gender'] ?? '');
        $patient_dob     = trim($_POST['patient_dob'] ?? '');
        $patient_email   = trim($_POST['patient_email'] ?? '');
        $patient_address = trim($_POST['patient_address'] ?? '');
        $patient_contact = trim($_POST['patient_contact'] ?? '');

        if ($patient_name === '' || $patient_gender === '' || $patient_dob === '' ||
            $patient_email === '' || $patient_address === '' || $patient_contact === '') {

            $error = "All fields are required.";
        } elseif (!filter_var($patient_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {

            // encrypt sensitive fields before saving
            $enc_address = encrypt_field($patient_address);
            $enc_contact = encrypt_field($patient_contact);

            if (isset($_POST['submit_create']) && $patient_id === 0) {
                // ---------- INSERT ----------
                $stmt = $conn->prepare(
                    "INSERT INTO Patient
                     (patient_name, patient_gender, patient_DOB, patient_email, patient_address, patient_contact)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssssss",
                    $patient_name,
                    $patient_gender,
                    $patient_dob,
                    $patient_email,
                    $enc_address,
                    $enc_contact
                );

                if ($stmt->execute()) {
                    $message = "Patient successfully added!";
                    if (function_exists('log_event')) {
                        log_event('info', "Patient created: $patient_email by user_id " . ($_SESSION['user_id'] ?? 'unknown'));
                    }
                } else {
                    $error = "Error adding patient.";
                    if (function_exists('log_event')) {
                        log_event('error', "Error adding patient $patient_email: " . $stmt->error);
                    }
                }

            } elseif (isset($_POST['submit_update']) && $patient_id > 0) {
                // ---------- UPDATE ----------
                $stmt = $conn->prepare(
                    "UPDATE Patient
                     SET patient_name = ?, patient_gender = ?, patient_DOB = ?,
                         patient_email = ?, patient_address = ?, patient_contact = ?
                     WHERE patient_id = ?"
                );
                $stmt->bind_param(
                    "ssssssi",
                    $patient_name,
                    $patient_gender,
                    $patient_dob,
                    $patient_email,
                    $enc_address,
                    $enc_contact,
                    $patient_id
                );

                if ($stmt->execute()) {
                    $message = "Patient record updated successfully!";
                    if (function_exists('log_event')) {
                        log_event('info', "Patient updated: ID $patient_id by user_id " . ($_SESSION['user_id'] ?? 'unknown'));
                    }
                } else {
                    $error = "Error updating patient record.";
                    if (function_exists('log_event')) {
                        log_event('error', "Error updating patient ID $patient_id: " . $stmt->error);
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error saving patient record.";
        if (function_exists('log_event')) {
            log_event('error', "Exception in patient create/update: " . $e->getMessage());
        }
    }
}

// -------------------- FETCH PATIENT FOR EDIT (GET) --------------------
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];

    if ($edit_id > 0) {
        $stmt = $conn->prepare(
            "SELECT patient_id, patient_name, patient_gender, patient_DOB,
                    patient_email, patient_address, patient_contact
             FROM Patient
             WHERE patient_id = ?"
        );
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $edit_res     = $stmt->get_result();
        $edit_patient = $edit_res->fetch_assoc();

        if ($edit_patient) {
            // decrypt for form display
            $edit_patient['patient_address'] = decrypt_or_plain($edit_patient['patient_address']);
            $edit_patient['patient_contact'] = decrypt_or_plain($edit_patient['patient_contact']);
        }
    }
}

// -------------------- FETCH ALL PATIENTS --------------------
$patients_stmt = $conn->prepare(
    "SELECT patient_id, patient_name, patient_gender, patient_DOB,
            patient_email, patient_address, patient_contact
     FROM Patient
     ORDER BY patient_name ASC"
);
$patients_stmt->execute();
$patients_result = $patients_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Management | Pharma Management System</title>
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
        .msg { margin-top: 10px; color: #16a34a; }
        .err { margin-top: 10px; color: #dc2626; }
        footer {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: center;
            margin-top: 40px;
        }
        footer small { font-size: 0.9rem; }
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
        <h2>Patient Management</h2>
        <h3>Manage Patient Records</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Edit Patient Form -->
    <section class="form-container">
        <h3><?php echo $edit_patient ? 'Edit Patient' : 'Create Patient'; ?></h3>
        <form action="patient.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <input type="hidden" name="patient_id"
                   value="<?php echo $edit_patient ? (int)$edit_patient['patient_id'] : 0; ?>">

            <label for="patient_name">Patient Name</label>
            <input type="text" name="patient_name"
                   value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['patient_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="patient_gender">Gender</label>
            <input type="text" name="patient_gender"
                   value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['patient_gender'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="patient_dob">Date of Birth</label>
            <input type="date" name="patient_dob"
                   value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['patient_DOB'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="patient_email">Email</label>
            <input type="email" name="patient_email"
                   value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['patient_email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="patient_address">Address</label>
            <input type="text" name="patient_address"
                   value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['patient_address'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="patient_contact">Contact Number</label>
            <input type="text" name="patient_contact"
                   value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['patient_contact'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <button type="submit" name="<?php echo $edit_patient ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_patient ? 'Update Patient' : 'Create Patient'; ?>
            </button>
        </form>
    </section>

    <!-- Display all Patients -->
    <section>
        <h3>Existing Patients</h3>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Gender</th>
                <th>DOB</th>
                <th>Email</th>
                <th>Address</th>
                <th>Contact</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($patients_result->num_rows === 0): ?>
                <tr>
                    <td colspan="8">No patients found.</td>
                </tr>
            <?php else: ?>
                <?php while ($patient_row = $patients_result->fetch_assoc()): ?>
                    <?php
                    $show_address = decrypt_or_plain($patient_row['patient_address']);
                    $show_contact = decrypt_or_plain($patient_row['patient_contact']);
                    ?>
                    <tr>
                        <td><?php echo (int)$patient_row['patient_id']; ?></td>
                        <td><?php echo htmlspecialchars($patient_row['patient_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($patient_row['patient_gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($patient_row['patient_DOB'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($patient_row['patient_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($show_address, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($show_contact, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="patient.php?edit_id=<?php echo (int)$patient_row['patient_id']; ?>">Edit</a> |
                            <a href="patient.php?delete_id=<?php echo (int)$patient_row['patient_id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this patient?');">
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
