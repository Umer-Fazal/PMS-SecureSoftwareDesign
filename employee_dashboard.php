<?php
// Include DB + session
require 'config.php';

// Restrict access to logged-in admin/staff
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    // Not allowed
    http_response_code(403);
    echo "Access denied.";
    exit();
}
$displayName = 'User';
if (!empty($_SESSION['email'])) {
    // Take part before @
    $localPart = strtok($_SESSION['email'], '@');   // "staff1"

    // Replace dots/underscores with space, e.g. "john.doe" -> "john doe"
    $localPart = str_replace(['.', '_'], ' ', $localPart);

    // Capitalize each word: "staff1" -> "Staff1", "john doe" -> "John Doe"
    $displayName = ucwords(strtolower($localPart));
}
// Optional: get email for greeting
$user_email = $_SESSION['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharma System - Employee Dashboard</title>
    <!-- Link to Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Additional Styles -->
    <style>
        body {
            background: url('treatment appointment.jpg') no-repeat center center fixed;
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
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Pharma System</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="doctor.php">Doctors</a></li>
                <li class="nav-item"><a class="nav-link" href="patient.php">Patients</a></li>
                <li class="nav-item"><a class="nav-link" href="appointment.php">Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="manufacturer.php">Manufacturer</a></li>
                <li class="nav-item"><a class="nav-link" href="supplier.php">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link" href="medicine.php">Medicines</a></li>
                <li class="nav-item"><a class="nav-link" href="prescription.php">Prescription</a></li>
                <li class="nav-item"><a class="nav-link" href="stock.php">Stock</a></li>
                <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="feedback.php">Feedback</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h2 class="section-title"> Welcome to the Pharma System Admin!</h2>
        <p>This system helps you manage doctors, patients, appointments, medicines, suppliers, and feedback for a pharmaceutical company.</p>
        <p>Use the navigation bar to explore different sections of the system.</p>
    </div>

    <!-- Footer -->
    <footer>
        <small>&copy; 2024 Pharma System. All rights reserved.</small>
    </footer>

    <!-- Bootstrap 5 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
