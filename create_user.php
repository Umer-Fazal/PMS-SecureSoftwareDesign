<?php
// create_user.php
// Admin-only page to create Users (admin / staff / patient)

require 'config.php';
require 'csrf.php';

$error_message   = '';
$success_message = '';

// Only admin can access this page
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Remember we want to come back here after login
    $_SESSION['post_login_redirect'] = 'create_user.php';
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    verify_csrf_token();

    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $valid_roles = ['admin', 'staff', 'patient'];

    // Validation
    if (!$email) {
        $error_message = "Invalid email address.";
    } elseif (!in_array($role, $valid_roles, true)) {
        $error_message = "Invalid role selected.";
    } elseif ($password !== $confirm) {
        $error_message = "Passwords do not match.";
    } else {
        // Strong password policy from config.php
        list($ok, $policyMsg) = validate_strong_password($password);
        if (!$ok) {
            $error_message = $policyMsg;
        }
    }

    if ($error_message === '') {
        try {
            // Optional: check if email already exists
            $check = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;

            if ($exists) {
                $error_message = "This email is already registered.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "INSERT INTO Users (email, password_hash, role) VALUES (?, ?, ?)"
                );
                $stmt->bind_param("sss", $email, $password_hash, $role);
                $stmt->execute();

                $success_message = "User account created successfully.";
                if (function_exists('log_event')) {
                    log_event('info', "New user created: $email with role $role");
                }
            }
        } catch (Exception $e) {
            $error_message = "Error creating user. Please try again.";
            if (function_exists('log_event')) {
                log_event('error', "Failed creating user $email: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create User | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background-color: #f3f5fb;
            color: #111827;
        }

        header {
            background-color: #0b1f4b;
            color: #ffffff;
            padding: 14px 0;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
        }

        header nav ul {
            list-style: none;
            margin: 0;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header nav ul li {
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        header nav ul li a {
            color: #e5e7eb;
            text-decoration: none;
            margin-left: 16px;
            font-size: 0.9rem;
        }

        header nav ul li a:hover {
            text-decoration: underline;
        }

        main {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .form-card {
            width: 100%;
            max-width: 520px;
            background-color: #ffffff;
            border-radius: 14px;
            padding: 26px 26px 30px;
            box-shadow:
                0 18px 45px rgba(15, 23, 42, 0.18),
                0 2px 6px rgba(15, 23, 42, 0.08);
        }

        .form-card h1 {
            margin: 0 0 6px;
            font-size: 1.6rem;
        }

        .form-card p.subtitle {
            margin: 0 0 18px;
            font-size: 0.95rem;
            color: #6b7280;
        }

        label {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 4px;
            display: block;
            color: #374151;
        }

        input, select {
            width: 100%;
            padding: 10px 11px;
            margin: 5px 0 14px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.55);
        }

        button[type="submit"] {
            width: 100%;
            padding: 11px;
            border-radius: 999px;
            border: none;
            font-size: 0.98rem;
            font-weight: 600;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.35);
            margin-top: 4px;
        }

        button[type="submit"]:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
        }

        .message {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .message.error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .message.success {
            background-color: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .success-actions {
            margin-top: 10px;
            text-align: right;
        }

        .success-actions a {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 999px;
            background-color: #0f766e;
            color: #ecfeff;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .success-actions a:hover {
            background-color: #115e59;
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
                <a href="homepage.php">Homepage</a>
                <a href="logout.php">Logout</a>
            </li>
        </ul>
    </nav>
</header>

<main>
    <section class="form-card">
        <h1>Create User</h1>
        <p class="subtitle">Create a new admin, staff, or patient login account.</p>

        <?php if (!empty($error_message)): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="success-actions">
                <a href="homepage.php">Go to Homepage</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_user.php" autocomplete="off">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <label for="email">User Email</label>
            <input type="email" id="email" name="email"
                   placeholder="user@example.com" required>

            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="">Select role</option>
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="patient">Patient</option>
            </select>

            <label for="password">Password (strong - 10+ chars, upper, lower, digit, symbol)</label>
            <input type="password" id="password" name="password"
                   required minlength="10">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required minlength="10">

            <button type="submit" name="create_user">Create User</button>
        </form>
    </section>
</main>

</body>
</html>
