<?php
// Secure Employee Login using Users table (admin / staff)

require 'config.php';  // DB + secure session
require 'csrf.php';    // CSRF helpers

$error_message = '';

// If already logged in as admin/staff AND MFA done → go to dashboard
if (
    isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['mfa_ok'])
    && $_SESSION['mfa_ok'] === true
    && in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: employee_dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    verify_csrf_token();

    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        $error_message = "Invalid email address.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Look up user in Users table, but only allow admin/staff roles
        $stmt = $conn->prepare(
            "SELECT user_id, email, password_hash, role 
             FROM Users 
             WHERE email = ? AND role IN ('admin', 'staff')"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            // ==========================
            // MFA STEP for admin / staff
            // ==========================
            $code       = random_int(100000, 999999); // 6-digit OTP
            $expires_at = time() + 300;               // valid 5 minutes

            // Store as "pending login" until OTP verified
            $_SESSION['pending_user_id'] = (int)$user['user_id'];
            $_SESSION['pending_email']   = $user['email'];
            $_SESSION['pending_role']    = $user['role'];
            $_SESSION['mfa_code']        = $code;
            $_SESSION['mfa_expires']     = $expires_at;
            $_SESSION['mfa_attempts']    = 0;
            $_SESSION['mfa_ok']          = false;

            if (function_exists('log_event')) {
                log_event('info', "Employee MFA code for {$user['email']}: $code");
            }

            // In a real system you'd email/SMS $code to the user.
            // For SSD demo we'll show it on mfa_verify.php.
            header('Location: mfa_verify.php');
            exit();

        } else {
            $error_message = "Invalid email or password.";
            if (function_exists('log_event')) {
                log_event('warning', "Failed employee login for email: $email");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Login | Pharma Management System</title>
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

        main .grid {
            max-width: 500px;
            margin: 40px auto 60px;
        }

        section {
            background-color: #ffffff;
            padding: 25px 25px 30px;
            border-radius: 10px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
        }

        hgroup h2 {
            margin-bottom: 5px;
        }

        hgroup h3 {
            margin-top: 0;
            font-size: 0.95rem;
            color: #666;
        }

        form div {
            margin-bottom: 15px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        button[type="submit"]:hover {
            background: linear-gradient(135deg, #0069d9, #004a99);
        }

        .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
        }

        footer.container {
            text-align: center;
            margin-top: 20px;
            padding-bottom: 20px;
            font-size: 0.85rem;
            color: #666;
        }
    </style>
</head>
<body>
    <header>
        <nav class="container-fluid">
            <ul>
                <li><strong>Employee Login</strong></li>
                <li>
                    <a href="homepage.php">Back to Home</a>
                    <a href="login.php">Main System Login</a>
                </li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <div class="grid">
            <section>
                <hgroup>
                    <h2>Login to Your Account</h2>
                    <h3>Employees and admins only</h3>
                </hgroup>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login_employee.php">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div>
                        <label for="email">Work Email</label>
                        <input type="email" id="email" name="email"
                               placeholder="employee@gmail.com" required>
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required minlength="8">
                    </div>
                    <button type="submit">Login</button>
                </form>

               <p style="margin-top: 15px; font-size: 0.9rem;">
                 Don't have an account?
                <a href="login.php?admin_register=1">Ask admin to register you</a>
                </p>

            </section>
        </div>
    </main>

    <footer class="container">
        <small>&copy; 2024 Pharma Management System</small>
    </footer>
</body>
</html>
