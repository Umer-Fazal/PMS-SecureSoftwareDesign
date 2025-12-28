<?php
// register_patient.php
require 'config.php';
require 'csrf.php';

$error_message   = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_patient'])) {
    verify_csrf_token();

    $patient_name    = trim($_POST['patient_name'] ?? '');
    $patient_gender  = trim($_POST['patient_gender'] ?? '');
    $patient_dob     = $_POST['patient_dob'] ?? '';
    $patient_email   = filter_var($_POST['patient_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $patient_address = trim($_POST['patient_address'] ?? '');
    $patient_contact = trim($_POST['patient_contact'] ?? '');
    $patient_password         = $_POST['patient_password'] ?? '';
    $patient_password_confirm = $_POST['patient_password_confirm'] ?? '';

    // Basic validation
    if (!$patient_email) {
        $error_message = "Invalid email address.";
    } elseif ($patient_name === '' || $patient_gender === '' || $patient_dob === '' ||
              $patient_address === '' || $patient_contact === '') {
        $error_message = "All fields are required.";
    } elseif ($patient_password !== $patient_password_confirm) {
        $error_message = "Passwords do not match.";
    } else {
        // Strong password policy
        list($ok, $policyMsg) = validate_strong_password($patient_password);
        if (!$ok) {
            $error_message = $policyMsg;
        }
    }

    if ($error_message === '') {
        // Hash password
        $password_hash = password_hash($patient_password, PASSWORD_DEFAULT);

        // Encrypt address and contact
        $enc_address = encrypt_field($patient_address);
        $enc_contact = encrypt_field($patient_contact);

        try {
            $conn->begin_transaction();

            // 1) Check if email already exists in Users
            $checkStmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
            $checkStmt->bind_param("s", $patient_email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $conn->rollback();
                $error_message = "This email is already registered.";
            } else {
                // 2) Insert into Users table as role 'patient'
                $userRole = 'patient';
                $stmtUser = $conn->prepare(
                    "INSERT INTO Users (email, password_hash, role) VALUES (?, ?, ?)"
                );
                $stmtUser->bind_param("sss", $patient_email, $password_hash, $userRole);
                $stmtUser->execute();
                $user_id = $stmtUser->insert_id;

                // 3) Insert into Patient table
                $stmtPatient = $conn->prepare(
                    "INSERT INTO Patient 
                        (patient_name, patient_gender, patient_DOB, patient_email, patient_address, patient_contact, patient_password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmtPatient->bind_param(
                    "sssssss",
                    $patient_name,
                    $patient_gender,
                    $patient_dob,
                    $patient_email,
                    $enc_address,
                    $enc_contact,
                    $password_hash
                );
                $stmtPatient->execute();

                $conn->commit();

                $success_message = "Registration successful. You can now log in.";
                if (function_exists('log_event')) {
                    log_event('info', "New patient registered: $patient_email (user_id: $user_id)");
                }

                header('Location: login.php');
                exit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error registering patient. Please try again.";
            if (function_exists('log_event')) {
                log_event('error', "Failed patient registration for $patient_email: " . $e->getMessage());
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
    <title>Patient Registration | Pharma Management System</title>
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
            max-width: 500px;
            margin: 30px auto 50px;
            padding: 25px 25px 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
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
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .form-container button:hover {
            background-color: #0056b3;
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
    </style>
</head>
<body>
<header>
    <nav class="container-fluid">
        <ul>
            <li><strong>Pharma Management System</strong></li>
            <li>
                <a href="login.php">Login</a>
            </li>
        </ul>
    </nav>
</header>

<main class="container">
    <section class="form-container">
        <h2>Patient Registration</h2>
        <p class="subtitle">Fill in the details to create your patient account.</p>

        <?php if (!empty($error_message)): ?>
            <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="message success"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <form action="register_patient.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

            <label for="patient_name">Full Name</label>
            <input type="text" id="patient_name" name="patient_name" placeholder="Full Name" required>

            <label for="patient_gender">Gender</label>
            <select id="patient_gender" name="patient_gender" required>
                <option value="">Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>

            <label for="patient_dob">Date of Birth</label>
            <input type="date" id="patient_dob" name="patient_dob" required>

            <label for="patient_email">Email</label>
            <input type="email" id="patient_email" name="patient_email" placeholder="you@example.com" required>

            <label for="patient_address">Address</label>
            <input type="text" id="patient_address" name="patient_address" placeholder="Address" required>

            <label for="patient_contact">Contact Number</label>
            <input type="text" id="patient_contact" name="patient_contact" placeholder="03xx-xxxxxxx" required>

            <label for="patient_password">Password (strong - 10+ chars, upper, lower, digit, symbol)</label>
            <input type="password" id="patient_password" name="patient_password" required minlength="10">

            <label for="patient_password_confirm">Confirm Password</label>
            <input type="password" id="patient_password_confirm" name="patient_password_confirm" required minlength="10">

            <button type="submit" name="register_patient">Register</button>
        </form>
    </section>
</main>
</body>
</html>
