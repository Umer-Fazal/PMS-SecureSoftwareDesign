<?php
// Secure feedback submission by logged-in patient

require 'config.php';   // DB + session (assumed)
require 'csrf.php';     // CSRF helpers

// Ensure the patient is logged in (old flow using patient_email)
if (!isset($_SESSION['patient_email'])) {
    header('Location: login_patient.php');
    exit();
}

$success_message = '';
$error_message   = '';

// Fetch patient by email
$patient_email = $_SESSION['patient_email'];

$pat_stmt = $conn->prepare(
    "SELECT patient_id, patient_name, patient_email 
     FROM Patient 
     WHERE patient_email = ?"
);
$pat_stmt->bind_param("s", $patient_email);
$pat_stmt->execute();
$pat_res   = $pat_stmt->get_result();
$patient   = $pat_res->fetch_assoc();

if (!$patient) {
    // Session email does not match any patient row
    $error_message = "Patient record not found. Please login again.";
} else {
    $patient_id = (int)$patient['patient_id'];
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    try {
        verify_csrf_token();

        $order_id_raw = $_POST['order_id'] ?? '';
        $rating_raw   = $_POST['rating'] ?? '';
        $comments_raw = trim($_POST['comments'] ?? '');

        $order_id = (int)$order_id_raw;
        $rating   = (int)$rating_raw;

        // Basic validation
        if ($order_id <= 0) {
            $error_message = "Please select a valid order.";
        } elseif ($rating < 1 || $rating > 5) {
            $error_message = "Rating must be between 1 and 5.";
        } elseif ($comments_raw === '') {
            $error_message = "Please provide your comments.";
        } else {
            // Check that order actually belongs to this patient
            $ord_stmt = $conn->prepare(
                "SELECT order_id 
                 FROM Orders 
                 WHERE order_id = ? AND patient_id = ?"
            );
            $ord_stmt->bind_param("ii", $order_id, $patient_id);
            $ord_stmt->execute();
            $ord_res = $ord_stmt->get_result();

            if ($ord_res->num_rows === 0) {
                $error_message = "You can only give feedback on your own orders.";
            } else {
                // Optional: prevent duplicate feedback for same order
                $check_fb = $conn->prepare(
                    "SELECT feedback_id 
                     FROM Feedback 
                     WHERE order_id = ? AND patient_id = ?"
                );
                $check_fb->bind_param("ii", $order_id, $patient_id);
                $check_fb->execute();
                $fb_res = $check_fb->get_result();

                if ($fb_res->num_rows > 0) {
                    $error_message = "You have already submitted feedback for this order.";
                } else {
                    // Insert feedback safely
                    $ins_stmt = $conn->prepare(
                        "INSERT INTO Feedback (patient_id, order_id, rating, comments)
                         VALUES (?, ?, ?, ?)"
                    );
                    $ins_stmt->bind_param("iiis", $patient_id, $order_id, $rating, $comments_raw);

                    if ($ins_stmt->execute()) {
                        $success_message = "Feedback submitted successfully.";
                    } else {
                        $error_message = "Error submitting feedback. Please try again later.";
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Security error while submitting feedback. Please reload the page.";
    }
}

// Fetch patient's orders for dropdown
$orders = [];
if (!empty($patient_id)) {
    $orders_stmt = $conn->prepare(
        "SELECT order_id, order_date, order_status 
         FROM Orders 
         WHERE patient_id = ?
         ORDER BY order_date DESC"
    );
    $orders_stmt->bind_param("i", $patient_id);
    $orders_stmt->execute();
    $orders_res = $orders_stmt->get_result();
    while ($row = $orders_res->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    
    <style>
        /* Custom styling */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7fb;
            color: #333;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #005b96;
            color: white;
            padding: 10px 0;
            text-align: center;
        }

        header nav ul {
            list-style-type: none;
            padding: 0;
        }

        header nav ul li {
            display: inline-block;
            margin-right: 15px;
        }

        h1 {
            color: #005b96;
            font-size: 24px;
            text-align: center;
            margin-top: 20px;
        }

        .form-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-container label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }

        .form-container select, .form-container input, .form-container textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-container textarea {
            resize: vertical;
        }

        .form-container button {
            background-color: #005b96;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }

        .form-container button:hover {
            background-color: #004478;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }

        .back-button {
            display: inline-block;
            background-color: #17a2b8;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            text-align: center;
            text-decoration: none;
            margin-top: 20px;
        }

        .back-button:hover {
            background-color: #138496;
        }

        footer {
            text-align: center;
            background-color: #005b96;
            color: white;
            padding: 10px 0;
            margin-top: 20px;
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
    <div class="form-container">
        <h1>Submit Feedback</h1>
        
        <?php if ($success_message !== ''): ?>
            <p class="success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <p class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="submit_feedback.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <label for="order_id">Select Order:</label>
            <select name="order_id" required>
                <option value="">Select your order</option>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $label = "Order #{$order['order_id']} ({$order['order_date']} - {$order['order_status']})";
                        ?>
                        <option value="<?php echo (int)$order['order_id']; ?>">
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">No orders found</option>
                <?php endif; ?>
            </select>

            <label for="rating">Rating (1-5):</label>
            <input type="number" name="rating" min="1" max="5" required>

            <label for="comments">Comments:</label>
            <textarea name="comments" rows="4" required></textarea>

            <button type="submit">Submit Feedback</button>
        </form>

        <!-- Button to go back to the patient dashboard -->
        <a href="patient_dashboard.php" class="back-button">Back to Dashboard</a>
    </div>
</main>

<footer>
    <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
</footer>

</body>
</html>
