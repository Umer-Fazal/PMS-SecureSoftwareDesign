<?php
// Secure patient appointment booking

require 'config.php';   // DB + session_start (already inside)
require 'csrf.php';

// Check if the patient is logged in
if (!isset($_SESSION['patient_email'])) {
    header('Location: login_patient.php');
    exit();
}

$success_message = '';
$error_message   = '';

// Fetch current patient info using prepared statement
$patient_email = $_SESSION['patient_email'];

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
    // Safety: if somehow session email not found anymore
    $error_message = "Patient record not found. Please log in again.";
    header('Location: login_patient.php');
    exit();
}

$patient_id = (int)$patient['patient_id'];

// Fetch available doctors
$doctors_result = $conn->query(
    "SELECT doctor_id, doctor_name 
     FROM Doctor 
     ORDER BY doctor_name ASC"
);

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();

        $appointment_date   = trim($_POST['appointment_date'] ?? '');
        $appointment_time   = trim($_POST['appointment_time'] ?? '');
        $appointment_reason = trim($_POST['appointment_reason'] ?? '');
        $doctor_id          = (int)($_POST['doctor_id'] ?? 0);

        if (!$doctor_id || $appointment_date === '' || $appointment_time === '' || $appointment_reason === '') {
            $error_message = "All fields are required.";
        } else {
            // Check if an appointment with same doctor, date, time already exists for this patient
            $check_stmt = $conn->prepare(
                "SELECT 1 
                 FROM Appointment 
                 WHERE doctor_id = ? 
                   AND patient_id = ? 
                   AND appointment_date = ? 
                   AND appointment_time = ?
                 LIMIT 1"
            );
            $check_stmt->bind_param(
                "iiss",
                $doctor_id,
                $patient_id,
                $appointment_date,
                $appointment_time
            );
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();

            if ($check_res->num_rows > 0) {
                $error_message = "You already have an appointment at this time.";
            } else {
                // Insert the appointment
                $ins_stmt = $conn->prepare(
                    "INSERT INTO Appointment
                     (patient_id, doctor_id, appointment_date, appointment_time, appointment_reason)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $ins_stmt->bind_param(
                    "iisss",
                    $patient_id,
                    $doctor_id,
                    $appointment_date,
                    $appointment_time,
                    $appointment_reason
                );

                if ($ins_stmt->execute()) {
                    $success_message = "Appointment successfully booked on "
                        . htmlspecialchars($appointment_date, ENT_QUOTES, 'UTF-8')
                        . " at "
                        . htmlspecialchars($appointment_time, ENT_QUOTES, 'UTF-8')
                        . ".";
                    if (function_exists('log_event')) {
                        log_event('info', "Patient $patient_id booked appointment with doctor $doctor_id on $appointment_date $appointment_time");
                    }
                } else {
                    $error_message = "Error booking appointment. Please try again.";
                    if (function_exists('log_event')) {
                        log_event('error', "Error inserting appointment for patient $patient_id: " . $ins_stmt->error);
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Unexpected error. Please try again.";
        if (function_exists('log_event')) {
            log_event('error', "Exception in book_appointment: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .container {
            margin-top: 50px;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #0044cc;
            border-color: #0044cc;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .alert {
            margin-top: 20px;
        }
        .form-control {
            margin-bottom: 15px;
        }
        footer {
            margin-top: 30px;
            text-align: center;
            padding: 10px;
            background-color: #0044cc;
            color: white;
        }
        .success {
            color: #16a34a;
        }
        .error {
            color: #dc2626;
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
    <h1>Book an Appointment</h1>

    <?php if ($success_message !== ''): ?>
        <p class="success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($error_message !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form action="book_appointment.php" method="POST">
        <!-- CSRF token -->
        <input type="hidden" name="csrf_token"
               value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
            <label for="doctor_id">Select Doctor:</label>
            <select name="doctor_id" id="doctor_id" required>
                <option value="">-- Select a Doctor --</option>
                <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                    <option value="<?php echo (int)$doctor['doctor_id']; ?>">
                        <?php echo htmlspecialchars($doctor['doctor_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="appointment_date">Appointment Date:</label>
            <input type="date" name="appointment_date" required>
        </div>

        <div class="form-group">
            <label for="appointment_time">Appointment Time:</label>
            <input type="time" name="appointment_time" required>
        </div>

        <div class="form-group">
            <label for="appointment_reason">Reason for Appointment:</label>
            <textarea name="appointment_reason" rows="4" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Book Appointment</button>
    </form>

    <a href="patient_dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</main>

<footer>
    <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
</footer>

</body>
</html>
