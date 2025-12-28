<?php
// Secure system login using Users table

require 'config.php';  // DB + session
require 'csrf.php';    // CSRF helpers
require 'mail_otp.php';
$error_message = '';

// ===== 1) Check: kya hum "admin register staff" mode me hain? =====
if (isset($_GET['admin_register']) && $_GET['admin_register'] === '1') {
    // mark in session so hum MFA tak ye info le ja saken
    $_SESSION['admin_register_mode'] = true;
}

// ===== 2) Already logged in user ko redirect karo =====
if (isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['mfa_ok'])
    && $_SESSION['mfa_ok'] === true) {

    $role = $_SESSION['role'];

    // Agar pehle se logged-in admin ne admin_register mode on kiya hua hai
    if (!empty($_SESSION['admin_register_mode']) && $role === 'admin') {
        // ek hi registration flow ke liye kaafi
        $_SESSION['admin_register_mode'] = false;
        header('Location: create_user.php');
        exit();
    }

    if ($role === 'admin' || $role === 'staff') {
        header('Location: employee_dashboard.php');
        exit();
    } elseif ($role === 'patient') {
        header('Location: patient_dashboard.php');
        exit();
    }
}

// ===== 3) Handle login form submit =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
    // Verify CSRF token
    verify_csrf_token();

    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        $error_message = "Invalid email address.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Lookup user in Users table
        $stmt = $conn->prepare(
            "SELECT user_id, email, password_hash, role 
             FROM Users 
             WHERE email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {

            // ---------- Patient: no MFA here (ya tum chaho to laga sakte ho) ----------
            if ($user['role'] === 'patient') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['mfa_ok']  = true;

                if (function_exists('log_event')) {
                    log_event('info', "Patient logged in: {$user['email']}");
                }

                header('Location: patient_dashboard.php');
                exit();
            }

            // ---------- Admin / Staff: MFA required ----------
$code       = random_int(100000, 999999);   // 6-digit OTP
$expires_at = time() + 300;                 // 5 minutes

// Store pending login info
$_SESSION['pending_user_id'] = (int)$user['user_id'];
$_SESSION['pending_email']   = $user['email'];
$_SESSION['pending_role']    = $user['role'];
$_SESSION['mfa_code']        = $code;
$_SESSION['mfa_expires']     = $expires_at;
$_SESSION['mfa_attempts']    = 0;
$_SESSION['mfa_ok']          = false;

// Kya yeh login "admin-register" ke liye tha?
$_SESSION['pending_admin_register'] =
    (!empty($_SESSION['admin_register_mode']) && $user['role'] === 'admin');

if (function_exists('log_event')) {
    log_event('info', "MFA code generated for {$user['email']} (admin_register=" .
                       (!empty($_SESSION['pending_admin_register']) ? 'yes' : 'no') . ")");
}

// ===== Send OTP email here =====
if (!send_mfa_code($user['email'], (string)$code)) {
    // Email send fail → clean pending + show error
    unset(
        $_SESSION['pending_user_id'],
        $_SESSION['pending_email'],
        $_SESSION['pending_role'],
        $_SESSION['mfa_code'],
        $_SESSION['mfa_expires'],
        $_SESSION['mfa_attempts'],
        $_SESSION['pending_admin_register']
    );

    $error_message = "Could not send verification code. Please try again later.";
} else {
    // Email sent successfully → go to MFA page
    header('Location: mfa_verify.php');
    exit();
}


        } else {
            $error_message = "Incorrect email or password.";
            if (function_exists('log_event')) {
                log_event('warning', "Failed login attempt for email: $email");
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
    <title>System Login | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        /* Layout */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f3f5fb;
            color: #1f2933;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            padding: 16px 0;
            background: #0b1f4b;
            color: #ffffff;
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

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
            background-color: #ffffff;
            border-radius: 14px;
            padding: 28px 28px 32px;
            box-shadow:
                0 18px 45px rgba(15, 23, 42, 0.18),
                0 2px 6px rgba(15, 23, 42, 0.08);
        }

        hgroup h2 {
            margin: 0 0 4px;
            font-size: 1.7rem;
            font-weight: 700;
            color: #111827;
        }

        hgroup p {
            margin: 0 0 20px;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .form-container input {
            width: 100%;
            padding: 12px 12px;
            margin: 8px 0 14px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background-color: #0f172a;
            color: #f9fafb;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .form-container input::placeholder {
            color: #9ca3af;
        }

        .form-container input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.7);
            background-color: #020617;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border: none;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.4);
            transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
        }

        .form-container button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.45);
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
        }

        .form-container button:active {
            transform: translateY(0);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .error-message {
            color: #b91c1c;
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 0.9rem;
            margin: 0 0 10px;
        }

        footer {
            text-align: center;
            padding: 12px 8px 18px;
            font-size: 0.85rem;
            color: #6b7280;
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

    <main>
        <section class="form-container">
            <hgroup>
                <h2>System Login</h2>
                <p>Please enter your email and password to login</p>
            </hgroup>
            
            <form action="login.php" method="POST">
                <!-- CSRF token -->
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <?php 
                if (isset($error_message) && $error_message !== '') {
                    echo "<div class='error-message'>" . htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') . "</div>";
                }
                ?>

                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password (min 8 characters)" required minlength="8">
                <button type="submit" name="login_user">Login</button>
            </form>
        </section>
    </main>

    <footer>
        <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
    </footer>
</body>
</html>
