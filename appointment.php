<?php
// Secure Appointment management (prepared statements + CSRF)

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


$message = '';
$edit_appointment = null;

// ---------------------- DELETE APPOINTMENT (GET) ----------------------
if (isset($_GET['delete'], $_GET['doctor_id'], $_GET['patient_id'])) {
    $doctor_id  = (int)$_GET['doctor_id'];
    $patient_id = (int)$_GET['patient_id'];

    $stmt = $conn->prepare(
        "DELETE FROM Appointment 
         WHERE doctor_id = ? AND patient_id = ?"
    );
    $stmt->bind_param("ii", $doctor_id, $patient_id);

    if ($stmt->execute()) {
        $message = "Appointment deleted successfully.";
        if (function_exists('log_event')) {
            log_event('info', "Appointment deleted (doctor_id=$doctor_id, patient_id=$patient_id)");
        }
    } else {
        $message = "Error deleting appointment.";
        if (function_exists('log_event')) {
            log_event('error', "Error deleting appointment (doctor_id=$doctor_id, patient_id=$patient_id): " . $stmt->error);
        }
    }

    header('Location: appointment.php');
    exit();
}

// ---------------------- CREATE / UPDATE (POST) ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $doctor_id         = (int)($_POST['doctor_id'] ?? 0);
    $patient_id        = (int)($_POST['patient_id'] ?? 0);
    $appointment_date  = trim($_POST['appointment_date'] ?? '');
    $appointment_time  = trim($_POST['appointment_time'] ?? '');
    $appointment_reason= trim($_POST['appointment_reason'] ?? '');

    if (!$doctor_id || !$patient_id || $appointment_date === '' || $appointment_time === '' || $appointment_reason === '') {
        $message = "All fields are required.";
    } else {
        if (isset($_POST['submit_create'])) {
            // ---------- INSERT ----------
            $stmt = $conn->prepare(
                "INSERT INTO Appointment 
                 (patient_id, doctor_id, appointment_date, appointment_time, appointment_reason)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iisss",
                $patient_id,
                $doctor_id,
                $appointment_date,
                $appointment_time,
                $appointment_reason
            );

            if ($stmt->execute()) {
                $message = "Appointment successfully booked!";
                if (function_exists('log_event')) {
                    log_event('info', "Appointment created (doctor_id=$doctor_id, patient_id=$patient_id)");
                }
            } else {
                $message = "Error booking appointment.";
                if (function_exists('log_event')) {
                    log_event('error', "Error creating appointment (doctor_id=$doctor_id, patient_id=$patient_id): " . $stmt->error);
                }
            }

        } elseif (isset($_POST['submit_update'])) {
            // ---------- UPDATE ----------
            // use original doctor/patient ids to locate row
            $old_doctor_id  = (int)($_POST['old_doctor_id'] ?? 0);
            $old_patient_id = (int)($_POST['old_patient_id'] ?? 0);

            if (!$old_doctor_id || !$old_patient_id) {
                $message = "Invalid appointment selected for update.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE Appointment 
                     SET patient_id = ?, doctor_id = ?, appointment_date = ?, appointment_time = ?, appointment_reason = ?
                     WHERE doctor_id = ? AND patient_id = ?"
                );
                $stmt->bind_param(
                    "iisssii",
                    $patient_id,
                    $doctor_id,
                    $appointment_date,
                    $appointment_time,
                    $appointment_reason,
                    $old_doctor_id,
                    $old_patient_id
                );

                if ($stmt->execute()) {
                    $message = "Appointment successfully updated!";
                    if (function_exists('log_event')) {
                        log_event('info', "Appointment updated (old_d=$old_doctor_id, old_p=$old_patient_id → new_d=$doctor_id, new_p=$patient_id)");
                    }
                } else {
                    $message = "Error updating appointment.";
                    if (function_exists('log_event')) {
                        log_event('error', "Error updating appointment (doctor_id=$old_doctor_id, patient_id=$old_patient_id): " . $stmt->error);
                    }
                }
            }
        }
    }
}

// ---------------------- FETCH APPOINTMENT FOR EDIT --------------------
if (isset($_GET['edit'], $_GET['doctor_id'], $_GET['patient_id'])) {
    $doctor_id  = (int)$_GET['doctor_id'];
    $patient_id = (int)$_GET['patient_id'];

    $stmt = $conn->prepare(
        "SELECT * FROM Appointment 
         WHERE doctor_id = ? AND patient_id = ?"
    );
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    $result           = $stmt->get_result();
    $edit_appointment = $result->fetch_assoc();
}

// ---------------------- FETCH DROPDOWNS & LIST ------------------------

// Doctors dropdown
$doctors = $conn->query("SELECT doctor_id, doctor_name FROM Doctor ORDER BY doctor_name ASC");

// Patients dropdown
$patients = $conn->query("SELECT patient_id, patient_name FROM Patient ORDER BY patient_name ASC");

// All appointments list
$stmt = $conn->prepare(
    "SELECT a.*, p.patient_name, d.doctor_name
     FROM Appointment a
     JOIN Patient p ON a.patient_id = p.patient_id
     JOIN Doctor d ON a.doctor_id = d.doctor_id
     ORDER BY a.appointment_date, a.appointment_time"
);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Management | Pharma Management System</title>
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
        <h2>Appointment Management</h2>
        <h3>Manage Patient Appointments</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Edit Appointment Form -->
    <section class="form-container">
        <h3><?php echo $edit_appointment ? 'Edit Appointment' : 'Create Appointment'; ?></h3>
        <form action="appointment.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <?php if ($edit_appointment): ?>
                <!-- original key values for WHERE clause -->
                <input type="hidden" name="old_doctor_id"
                       value="<?php echo (int)$edit_appointment['doctor_id']; ?>">
                <input type="hidden" name="old_patient_id"
                       value="<?php echo (int)$edit_appointment['patient_id']; ?>">
            <?php endif; ?>

            <label for="doctor_id">Doctor</label>
            <select name="doctor_id" required>
                <option value="">Select Doctor</option>
                <?php while ($doc = $doctors->fetch_assoc()): ?>
                    <option value="<?php echo (int)$doc['doctor_id']; ?>"
                        <?php echo $edit_appointment && $edit_appointment['doctor_id'] == $doc['doctor_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($doc['doctor_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="patient_id">Patient</label>
            <select name="patient_id" required>
                <option value="">Select Patient</option>
                <?php while ($pat = $patients->fetch_assoc()): ?>
                    <option value="<?php echo (int)$pat['patient_id']; ?>"
                        <?php echo $edit_appointment && $edit_appointment['patient_id'] == $pat['patient_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pat['patient_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="appointment_date">Date</label>
            <input type="date" name="appointment_date"
                   value="<?php echo $edit_appointment ? htmlspecialchars($edit_appointment['appointment_date']) : ''; ?>"
                   required>

            <label for="appointment_time">Time</label>
            <input type="time" name="appointment_time"
                   value="<?php echo $edit_appointment ? htmlspecialchars($edit_appointment['appointment_time']) : ''; ?>"
                   required>

            <label for="appointment_reason">Reason</label>
            <textarea name="appointment_reason" required><?php
                echo $edit_appointment ? htmlspecialchars($edit_appointment['appointment_reason']) : '';
            ?></textarea>

            <button type="submit" name="<?php echo $edit_appointment ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_appointment ? 'Update Appointment' : 'Create Appointment'; ?>
            </button>
        </form>
    </section>

    <!-- All Appointments -->
    <section>
        <h3>All Appointments</h3>
        <table>
            <thead>
            <tr>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Time</th>
                <th>Reason</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $appointments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['doctor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['appointment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['appointment_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['appointment_reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a href="appointment.php?edit=1&doctor_id=<?php echo (int)$row['doctor_id']; ?>&patient_id=<?php echo (int)$row['patient_id']; ?>">Edit</a> |
                        <a href="appointment.php?delete=1&doctor_id=<?php echo (int)$row['doctor_id']; ?>&patient_id=<?php echo (int)$row['patient_id']; ?>"
                           onclick="return confirm('Are you sure you want to delete this appointment?');">
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
    <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
</footer>
</body>
</html>
