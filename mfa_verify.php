<?php
require 'config.php';
require 'csrf.php';

$error_message = '';

// User must have passed password step first
if (
    !isset($_SESSION['pending_user_id'], $_SESSION['pending_email'],
            $_SESSION['pending_role'], $_SESSION['mfa_code'], $_SESSION['mfa_expires'])
) {
    // No pending MFA → send back to login
    header('Location: login.php');
    exit();
}

// Block if expired
if (time() > $_SESSION['mfa_expires']) {
    $error_message = "Your verification code has expired. Please log in again.";
    // Clear pending MFA
    unset($_SESSION['pending_user_id'], $_SESSION['pending_email'], $_SESSION['pending_role'],
          $_SESSION['mfa_code'], $_SESSION['mfa_expires'], $_SESSION['mfa_attempts'],
          $_SESSION['pending_admin_register'], $_SESSION['admin_register_mode']);
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_mfa']) && empty($error_message)) {
    verify_csrf_token();

    $input_code = trim($_POST['mfa_code'] ?? '');
    $_SESSION['mfa_attempts'] = ($_SESSION['mfa_attempts'] ?? 0) + 1;

    // Too many tries → force re-login
    if ($_SESSION['mfa_attempts'] > 5) {
        $error_message = "Too many invalid attempts. Please log in again.";
        unset($_SESSION['pending_user_id'], $_SESSION['pending_email'], $_SESSION['pending_role'],
              $_SESSION['mfa_code'], $_SESSION['mfa_expires'], $_SESSION['mfa_attempts'],
              $_SESSION['pending_admin_register'], $_SESSION['admin_register_mode']);
    } else {
        if (hash_equals((string)$_SESSION['mfa_code'], $input_code)) {
            // Correct OTP → finalize login
            $user_id = $_SESSION['pending_user_id'];
            $email   = $_SESSION['pending_email'];
            $role    = $_SESSION['pending_role'];

            // yaad rakho: kya yeh login "admin-register" mode se aya tha?
            $adminRegisterFlow = !empty($_SESSION['pending_admin_register']);

            session_regenerate_id(true);

            // Final session vars for "real" logged-in user
            $_SESSION['user_id'] = (int)$user_id;
            $_SESSION['email']   = $email;
            $_SESSION['role']    = $role;
            $_SESSION['mfa_ok']  = true;

            // clean pending flags
            unset($_SESSION['pending_user_id'], $_SESSION['pending_email'], $_SESSION['pending_role'],
                  $_SESSION['mfa_code'], $_SESSION['mfa_expires'], $_SESSION['mfa_attempts'],
                  $_SESSION['pending_admin_register'], $_SESSION['admin_register_mode']);

            if (function_exists('log_event')) {
                log_event('info', "MFA success for $email ($role), adminRegisterFlow=" .
                                   ($adminRegisterFlow ? 'yes' : 'no'));
            }

            // ---------- Redirect logic ----------
            if ($adminRegisterFlow && $role === 'admin') {
                // Yeh woh special case hai: admin naya staff/admin register karega
                header('Location: create_user.php');
            } else {
                // Normal case
                if ($role === 'admin' || $role === 'staff') {
                    header('Location: employee_dashboard.php');
                } elseif ($role === 'patient') {
                    header('Location: patient_dashboard.php');
                } else {
                    header('Location: index.php');
                }
            }
            exit();
        } else {
            $error_message = "Incorrect verification code. Try again.";
            if (function_exists('log_event')) {
                log_event('warning', "MFA failed for {$_SESSION['pending_email']}");
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MFA Verification | Pharma Management System</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
        }
        .form-container {
            max-width: 400px;
            margin: 60px auto;
            padding: 24px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .demo-code {
            margin-top: 8px;
            padding: 8px;
            background: #e9f5ff;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .error-message {
            color: #d93025;
            margin-bottom: 12px;
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
    <section class="form-container">
        <h2>Verify your identity</h2>
        <p>We’ve sent a 6-digit verification code to your registered contact.</p>

        

        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="mfa_verify.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

            <label for="mfa_code">Enter 6-digit code</label>
            <input type="text"
                   id="mfa_code"
                   name="mfa_code"
                   maxlength="6"
                   pattern="\d{6}"
                   placeholder="123456"
                   required>

            <button type="submit" name="verify_mfa">Verify</button>
        </form>
    </section>
</main>

<footer class="container">
    <small>&copy; 2024 Pharma Management System</small>
</footer>
</body>
</html>
