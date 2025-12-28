<?php
// Secure order confirmation page

require 'config.php'; // DB + session (no extra session_start here)

// Check if the patient is logged in
if (!isset($_SESSION['patient_email'])) {
    header('Location: login_patient.php');
    exit();
}

$patient_email = $_SESSION['patient_email'];

// Fetch patient info using prepared statement
$stmt = $conn->prepare(
    "SELECT patient_id, patient_name 
     FROM Patient 
     WHERE patient_email = ? 
     LIMIT 1"
);
$stmt->bind_param("s", $patient_email);
$stmt->execute();
$result  = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    // Safety fallback: if session email doesn't match any patient
    header('Location: login_patient.php');
    exit();
}

$patient_id   = (int)$patient['patient_id'];
$patient_name = $patient['patient_name'];

// Check if order message exists
if (isset($_SESSION['order_message'])) {
    $order_message = $_SESSION['order_message'];
    // Clear after reading (one-time flash)
    unset($_SESSION['order_message']);
} else {
    // No confirmation message → go back to order page
    header("Location: order_items.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
            color: #333;
        }
        .page-container {
            margin-top: 50px;
        }
        .card {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .card h2 {
            margin-bottom: 4px;
        }
        .card h3 {
            margin-top: 0;
            font-weight: normal;
            color: #6b7280;
        }
        .highlight {
            margin: 16px 0;
            padding: 12px 14px;
            border-radius: 6px;
            background-color: #ecfdf5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            font-size: 0.95rem;
        }
        .meta {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 10px;
        }
        nav ul:first-child li strong {
            font-size: 1.05rem;
        }
    </style>
</head>
<body>
<nav class="container-fluid">
    <ul>
        <li><strong>Pharma Management System</strong></li>
    </ul>
    <ul>
        <li><a href="patient_dashboard.php">Back to Dashboard</a></li>
    </ul>
</nav>

<main class="container page-container">
    <div class="card">
        <hgroup>
            <h2>Order Confirmation</h2>
            <h3>Thank you, <?php echo htmlspecialchars($patient_name, ENT_QUOTES, 'UTF-8'); ?>!</h3>
        </hgroup>

        <p class="meta">
            Your order is linked with patient ID:
            <strong>#<?php echo $patient_id; ?></strong>
        </p>

        <div class="highlight">
            <?php echo nl2br(htmlspecialchars($order_message, ENT_QUOTES, 'UTF-8')); ?>
        </div>

        <p>If you have any further questions, feel free to contact our support team.</p>

        <a href="patient_dashboard.php" class="button">Go to Dashboard</a>
    </div>
</main>

<footer class="container">
    <small>© 2024 Pharma Management System</small>
</footer>
</body>
</html>
