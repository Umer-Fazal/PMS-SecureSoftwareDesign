<?php
require 'config.php';
require 'csrf.php';

// Only admin can access this page
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Remember that after login we want to come back here
    $_SESSION['post_login_redirect'] = 'create_user.php'; 
    // If your file name is create_user.php then use that string instead
    header('Location: login.php');
    exit();
}


$error_message   = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_employee'])) {
    verify_csrf_token();

    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Allow only staff or admin as employee roles
    $allowed_roles = ['staff', 'admin'];

    if (!$email) {
        $error_message = "Invalid email address.";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error_message = "Invalid role selected.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm) {
        $error_message = "Passwords do not match.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO Users (email, password_hash, role) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $email, $password_hash, $role);

        if ($stmt->execute()) {
            $success_message = "Employee account created successfully.";
            if (function_exists('log_event')) {
                log_event('info', "New employee user created: $email with role $role");
            }
        } else {
            $error_message = "Error creating employee. This email may already exist.";
            if (function_exists('log_event')) {
                log_event('error', "Failed creating employee $email: " . $stmt->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Employee | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
            color: #333;
            margin: 0;
        }

        header {
            background-color: #343a40;
            color: white;
            padding: 15px 0;
        }

        header nav ul {
            list-style: none;
            margin: 0;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header nav ul li {
            font-weight: bold;
        }

        header nav ul li a {
            color: #ffffff;
            text-decoration: none;
            margin-left: 15px;
            font-weight: normal;
            font-size: 0.95rem;
        }

        header nav ul li a:hover {
            text-decoration: underline;
        }

        .form-container {
            max-width: 480px;
            margin: 40px auto 60px;
            padding: 25px 25px 30px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
        }

        .form-container h2 {
            margin-bottom: 5px;
            text-align: center;
        }

        .form-container p.subtitle {
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.95rem;
            color: #666;
        }

        .form-container label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }

        .form-container input,
        .form-container select {
            width: 100%;
            padding: 11px;
            margin: 6px 0 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95rem;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .form-container button:hover {
            background: linear-gradient(135deg, #0069d9, #004a99);
        }

        .message {
            text-align: center;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .message.error {
            color: #dc3545;
        }

        .message.success {
            color: #28a745;
        }

        .small-info {
            margin-top: 10px;
            font-size: 0.85rem;
            color: #777;
            text-align: center;
        }

        footer {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: center;
            margin-top: 30px;
        }

        footer small {
            font-size: 0.85rem;
        }

        @media (max-width: 600px) {
            .form-container {
                margin: 20px 15px 40px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<header>
    <nav class="container-fluid">
        <ul>
            <li><strong>Pharma Management System</strong></li>
            <li>
                <a href="employee_dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </li>
        </ul>
    </nav>
</header>

<main class="container">
    <section class="form-container">
        <h2>Register Employee</h2>
        <p class="subtitle">Create a new admin or staff account for the system.</p>

        <?php if (!empty($error_message)): ?>
            <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="message success"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <form method="POST" action="employee_register.php">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

            <label for="email">Employee Email</label>
            <input type="email" id="email" name="email" placeholder="employee@email.com" required>

            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="">Select role</option>
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
            </select>

            <label for="password">Password (min 8 characters)</label>
            <input type="password" id="password" name="password" required minlength="8">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

            <button type="submit" name="register_employee">Create Employee Account</button>
        </form>

        <p class="small-info">
            Only administrators can create new employee accounts.  
            Make sure to share credentials securely with the new user.
        </p>
    </section>
</main>

<footer>
    <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
</footer>

</body>
</html>
