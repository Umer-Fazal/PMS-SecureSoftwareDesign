<?php
// Secure feedback listing (escaping + prepared statement)

require 'config.php';
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


// Fetch all feedbacks (with patient name + order date)
$stmt = $conn->prepare(
    "SELECT 
         f.feedback_id,
         f.patient_id,
         p.patient_name,
         f.order_id,
         f.rating,
         f.comments,
         o.order_date
     FROM Feedback f
     JOIN Orders  o ON f.order_id   = o.order_id
     JOIN Patient p ON f.patient_id = p.patient_id
     ORDER BY o.order_date DESC, f.feedback_id DESC"
);

$stmt->execute();
$feedback_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-image: url('https://media.istockphoto.com/id/1446086182/photo/doctor-shows-a-rating-five-stars-on-the-dark-background.jpg?s=612x612&w=0&k=20&c=JFJGAgUOCVpjrMjKe80oEPRzJc4uoLtXgX60hnSyxtk=');
            background-size: cover;
            background-position: center;
            font-family: 'Arial', sans-serif;
            color: #fff;
            padding: 50px;
        }
        .container {
            background: rgba(0, 0, 0, 0.7);
            padding: 24px;
            border-radius: 10px;
            margin-top: 40px;
        }
        h2 {
            text-align: center;
            color: #f2f2f2;
        }
        .feedback-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 15px 18px;
            margin: 12px 0;
            color: #111827;
        }
        .feedback-card h4 {
            margin-bottom: 8px;
        }
        .feedback-meta {
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .rating {
            font-weight: bold;
            color: #f59e0b;
        }
        .no-data {
            text-align: center;
            margin-top: 20px;
            color: #e5e7eb;
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
    </style>
</head>
<body>

<header>
    <nav class="container-fluid">
        <ul>
            <li><strong>Feedback Management</strong></li>
        </ul>
    </nav>
</header>

<main class="container">
    <hgroup>
        <h2>Feedback from Patients</h2>
    </hgroup>

    <section>
        <?php if ($feedback_result->num_rows === 0): ?>
            <p class="no-data">No feedback has been submitted yet.</p>
        <?php else: ?>
            <?php while ($fb = $feedback_result->fetch_assoc()): ?>
                <div class="feedback-card">
                    <h4>
                        Feedback #<?php echo (int)$fb['feedback_id']; ?>
                    </h4>

                    <div class="feedback-meta">
                        <span><strong>Patient:</strong>
                            <?php echo htmlspecialchars($fb['patient_name'], ENT_QUOTES, 'UTF-8'); ?>
                            (ID: <?php echo (int)$fb['patient_id']; ?>)
                        </span><br>
                        <span><strong>Order ID:</strong> <?php echo (int)$fb['order_id']; ?></span><br>
                        <span><strong>Order Date:</strong>
                            <?php echo htmlspecialchars($fb['order_date'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <p class="rating">Rating: <?php echo (int)$fb['rating']; ?> / 5</p>

                    <p>
                        <strong>Comments:</strong><br>
                        <?php echo nl2br(htmlspecialchars($fb['comments'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>
</main>

<footer class="container">
    <small>&copy; 2024 Pharma Management System</small>
</footer>

</body>
</html>
