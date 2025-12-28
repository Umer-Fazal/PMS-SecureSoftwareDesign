<?php
// Include the database connection and session setup
require 'config.php';
require 'csrf.php';

// If already logged in, go to dashboard
if (isset($_SESSION['patient_email'])) {
    header('Location: patient_dashboard.php');
    exit();
}

$error_message = '';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_patient'])) {
    // Check CSRF token
    verify_csrf_token();

    // Get and validate input
    $patient_email    = filter_var($_POST['patient_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $patient_password = $_POST['patient_password'] ?? '';

    if (!$patient_email) {
        $error_message = "Invalid email format.";
    } elseif (strlen($patient_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Use prepared statement to avoid SQL injection
        $stmt = $conn->prepare(
            "SELECT patient_email, patient_password_hash 
             FROM Patient 
             WHERE patient_email = ?"
        );
        $stmt->bind_param("s", $patient_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();

        if ($patient && password_verify($patient_password, $patient['patient_password_hash'])) {
            // Successful login
            session_regenerate_id(true);
            $_SESSION['patient_email'] = $patient['patient_email'];
           
            // Optional logging if you added log_event in config.php
            if (function_exists('log_event')) {
                log_event('info', "Patient logged in: " . $patient['patient_email']);
            }

            header('Location: patient_dashboard.php');
            exit();
        } else {
            $error_message = "Incorrect email or password.";
            if (function_exists('log_event')) {
                log_event('warning', "Failed patient login for email: $patient_email");
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
    <title>Patient Login | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        /* General Reset */
        body, h1, h2, h3, p, form, input, button {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: #f4f4f9;
            color: #333;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: #343a40;
            color: white;
            padding: 30px 0;
            text-align: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .login-container {
            max-width: 450px;
            margin: 80px auto;
            padding: 40px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            font-size: 2rem;
            text-align: center;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #007bff;
            outline: none;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background-color: #007bff;
            color: white;
            font-size: 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #0056b3;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-size: 1rem;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        footer {
            background-color: #343a40;
            color: white;
            padding: 15px 0;
            text-align: center;
            font-size: 0.9rem;
            margin-top: auto;
        }

        footer small {
            font-size: 0.9rem;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            header {
                font-size: 1.5rem;
            }

            .login-container {
                padding: 30px;
                margin-top: 50px;
            }

            .form-group input {
                font-size: 0.9rem;
                padding: 12px;
            }

            .btn-submit {
                font-size: 1rem;
                padding: 12px;
            }
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
        <section class="login-container">
            <h2>Patient Login</h2>
            <p style="text-align: center;">Please enter your email and password to login.</p>

            <?php 
            if (!empty($error_message)) {
                echo "<p class='error-message'>" . htmlspecialchars($error_message) . "</p>";
            }
            ?>
            
            <form action="login_patient.php" method="POST">
                <!-- CSRF token -->
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

                <div class="form-group">
                    <input type="email" name="patient_email" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <input type="password" name="patient_password" placeholder="Password" required minlength="8">
                </div>
                
                <button type="submit" name="login_patient" class="btn-submit">Login</button>
            </form>

            <div class="register-link">
                <p>New here? <a href="register_patient.php">Register Now</a></p>
            </div>
        </section>
    </main>

    <footer>
        <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
    </footer>

</body>
</html>
