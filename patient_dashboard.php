<?php
require 'config.php'; // includes DB + session + AES helpers

// Ensure the patient is logged in (based on session)
if (!isset($_SESSION['patient_email'])) {
    header('Location: login_patient.php');
    exit();
}

$patient_email = $_SESSION['patient_email'];

// 1) Fetch patient securely using prepared statement
$stmt = $conn->prepare("SELECT * FROM Patient WHERE patient_email = ?");
$stmt->bind_param("s", $patient_email);
$stmt->execute();
$result  = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    // Session email not found in DB → force logout
    if (function_exists('log_event')) {
        log_event('error', "Session email not found in DB: $patient_email");
    }
    header('Location: logout.php');
    exit();
}

// Decrypt sensitive fields
$patient['patient_contact'] = decrypt_field($patient['patient_contact']);
if (isset($patient['patient_address'])) {
    $patient['patient_address'] = decrypt_field($patient['patient_address']);
}

$patient_id = (int)$patient['patient_id'];

// 2) Fetch appointments using prepared statement
$appointments = [];
$stmtApp = $conn->prepare(
    "SELECT appointment_date 
     FROM Appointment 
     WHERE patient_id = ? 
     ORDER BY appointment_date DESC"
);
$stmtApp->bind_param("i", $patient_id);
$stmtApp->execute();
$appResult = $stmtApp->get_result();
while ($row = $appResult->fetch_assoc()) {
    $appointments[] = $row;
}

// 3) Fetch orders using prepared statement
$orders = [];
$stmtOrder = $conn->prepare(
    "SELECT order_date, order_status 
     FROM Orders 
     WHERE patient_id = ? 
     ORDER BY order_date DESC"
);
$stmtOrder->bind_param("i", $patient_id);
$stmtOrder->execute();
$orderResult = $stmtOrder->get_result();
while ($row = $orderResult->fetch_assoc()) {
    $orders[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Pharma System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('treatment_appointment.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Arial', sans-serif;
        }

        nav {
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            background-color: rgba(0, 44, 94, 0.8);
            padding: 10px 0;
        }

        nav ul {
            list-style: none;
            display: flex;
            justify-content: center;
        }

        nav ul li {
            margin: 0 15px;
        }

        nav ul li a {
            color: white;
            font-size: 18px;
            text-decoration: none;
        }

        nav ul li a:hover {
            text-decoration: underline;
        }

        .container {
            margin-top: 100px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
        }

        footer {
            position: fixed;
            bottom: 10px;
            width: 100%;
            text-align: center;
            color: white;
            background: rgba(0, 44, 94, 0.8);
            padding: 10px;
        }

        .section-title {
            color: #003366;
            font-weight: bold;
        }

        .btn-primary {
            background-color: #0044cc;
            border-color: #0044cc;
        }

        .card {
            margin: 10px;
        }

        .card-header {
            background-color: #003366;
            color: white;
        }

        .card-body {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Pharma System</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="book_appointment.php">Book Appointment</a></li>
                <li class="nav-item"><a class="nav-link" href="order_items.php">Order Medicines</a></li>
                <li class="nav-item"><a class="nav-link" href="submit_feedback.php">Submit Feedback</a></li>
                <li class="nav-item"><a class="nav-link" href="contact_us.html">Contact Us</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h2 class="section-title">
            Welcome, <?= htmlspecialchars($patient['patient_name'], ENT_QUOTES, 'UTF-8'); ?>!
        </h2>
        
        <!-- Appointment Section -->
        <div class="card">
            <div class="card-header">
                <h4>Your Appointments</h4>
            </div>
            <div class="card-body">
                <?php if (count($appointments) > 0): ?>
                    <ul>
                        <?php foreach ($appointments as $appointment): ?>
                            <li>
                                Appointment on 
                                <?= htmlspecialchars(date('F j, Y', strtotime($appointment['appointment_date'])), ENT_QUOTES, 'UTF-8'); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>You don't have any appointments yet. Book an appointment now!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="card mt-3">
            <div class="card-header">
                <h4>Your Orders</h4>
            </div>
            <div class="card-body">
                <?php if (count($orders) > 0): ?>
                    <ul>
                        <?php foreach ($orders as $order): ?>
                            <li>
                                Order on 
                                <?= htmlspecialchars(date('F j, Y', strtotime($order['order_date'])), ENT_QUOTES, 'UTF-8'); ?>
                                (Status: <?= htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8'); ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>You haven't placed any orders yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Additional Information Section -->
        <div class="card mt-3">
            <div class="card-header">
                <h4>Account Information</h4>
            </div>
            <div class="card-body">
                <p><strong>Email:</strong> 
                    <?= htmlspecialchars($patient['patient_email'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <p><strong>Phone Number:</strong> 
                    <?= htmlspecialchars($patient['patient_contact'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <p><a href="edit_profile.php" class="btn btn-primary">Edit Profile</a></p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <small>&copy; 2024 Pharma System. All rights reserved.</small>
    </footer>

    <!-- Bootstrap 5 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
