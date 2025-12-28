<?php
// edit_profile.php - Patient can view and update their profile plus password securely

require 'config.php';   // DB + secure session + encrypt/decrypt helpers
require 'csrf.php';     // CSRF helpers

// Ensure the patient is logged in
if (!isset($_SESSION['patient_email'])) {
    header('Location: login_patient.php');
    exit();
}

$current_email   = $_SESSION['patient_email'];
$error_message   = '';
$success_message = '';

// 1) Fetch current patient data using prepared statement
$stmt = $conn->prepare(
    "SELECT patient_id, patient_name, patient_email, patient_address, patient_contact 
     FROM Patient 
     WHERE patient_email = ?
     LIMIT 1"
);
$stmt->bind_param("s", $current_email);
$stmt->execute();
$result  = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    if (function_exists('log_event')) {
        log_event('error', "Patient email in session not found: $current_email");
    }
    header('Location: logout.php');
    exit();
}

// Decrypt sensitive fields for display with fallback
$tmpAddress = decrypt_field($patient['patient_address']);
if ($tmpAddress === false || $tmpAddress === null || $tmpAddress === '') {
    $patient_address = $patient['patient_address'];
} else {
    $patient_address = $tmpAddress;
}

$tmpContact = decrypt_field($patient['patient_contact']);
if ($tmpContact === false || $tmpContact === null || $tmpContact === '') {
    $patient_contact = $patient['patient_contact'];
} else {
    $patient_contact = $tmpContact;
}

// 2) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // Profile fields
    $new_name    = trim($_POST['patient_name'] ?? '');
    $new_email   = filter_var($_POST['patient_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $new_contact = trim($_POST['patient_contact'] ?? '');
    $new_address = trim($_POST['patient_address'] ?? '');

    // Password fields (optional)
    $current_password      = $_POST['current_password'] ?? '';
    $new_password          = $_POST['new_password'] ?? '';
    $confirm_new_password  = $_POST['confirm_new_password'] ?? '';

    $change_password = ($current_password !== '' || $new_password !== '' || $confirm_new_password !== '');

    // Basic validation for profile
    if ($new_name === '' || $new_contact === '' || $new_address === '' || !$new_email) {
        $error_message = "All fields are required and email must be valid.";
    }

    // If user wants to change password, validate password fields
    if (!$error_message && $change_password) {
        if ($current_password === '' || $new_password === '' || $confirm_new_password === '') {
            $error_message = "To change your password, fill in all three password fields.";
        } else {
            // Strong password policy
            list($ok, $policyMsg) = validate_strong_password($new_password);
            if (!$ok) {
                $error_message = $policyMsg;
            } elseif ($new_password !== $confirm_new_password) {
                $error_message = "New password and confirm password do not match.";
            } elseif ($new_password === $current_password) {
                $error_message = "New password must be different from current password.";
            }
        }
    }

    if (!$error_message) {
        $conn->begin_transaction();

        try {
            // 2a) Check for email duplication (other patients)
            $check = $conn->prepare(
                "SELECT patient_id 
                 FROM Patient 
                 WHERE patient_email = ? AND patient_id <> ?"
            );
            $check->bind_param("si", $new_email, $patient['patient_id']);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;

            if ($exists) {
                throw new Exception("This email is already in use by another patient.");
            }

            // 2b) If changing password, verify current and update hashes
            if ($change_password) {
                $pwdStmt = $conn->prepare(
                    "SELECT password_hash 
                     FROM Users 
                     WHERE email = ? AND role = 'patient'
                     LIMIT 1"
                );
                $pwdStmt->bind_param("s", $current_email);
                $pwdStmt->execute();
                $pwdResult = $pwdStmt->get_result();
                $userRow   = $pwdResult->fetch_assoc();

                if (!$userRow || !password_verify($current_password, $userRow['password_hash'])) {
                    throw new Exception("Current password is incorrect.");
                }

                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update Users table
                $updUser = $conn->prepare(
                    "UPDATE Users 
                     SET password_hash = ?, email = ?
                     WHERE email = ? AND role = 'patient'"
                );
                $updUser->bind_param("sss", $new_password_hash, $new_email, $current_email);
                $updUser->execute();

                // Update Patient table password_hash
                $updPatientPwd = $conn->prepare(
                    "UPDATE Patient
                     SET patient_password_hash = ?
                     WHERE patient_id = ?"
                );
                $updPatientPwd->bind_param("si", $new_password_hash, $patient['patient_id']);
                $updPatientPwd->execute();
            } else {
                // Only email change
                if ($new_email !== $current_email) {
                    $updUserEmail = $conn->prepare(
                        "UPDATE Users 
                         SET email = ?
                         WHERE email = ? AND role = 'patient'"
                    );
                    $updUserEmail->bind_param("ss", $new_email, $current_email);
                    $updUserEmail->execute();
                }
            }

            // Encrypt contact and address
            $enc_contact = encrypt_field($new_contact);
            $enc_address = encrypt_field($new_address);

            // Update patient profile
            $update = $conn->prepare(
                "UPDATE Patient
                 SET patient_name = ?, 
                     patient_email = ?, 
                     patient_contact = ?, 
                     patient_address = ?
                 WHERE patient_id = ?"
            );
            $update->bind_param(
                "ssssi",
                $new_name,
                $new_email,
                $enc_contact,
                $enc_address,
                $patient['patient_id']
            );
            $update->execute();

            $conn->commit();

            $patient['patient_name']  = $new_name;
            $patient['patient_email'] = $new_email;
            $patient_contact          = $new_contact;
            $patient_address          = $new_address;

            $_SESSION['patient_email'] = $new_email;

            $success_message = $change_password
                ? "Profile and password updated successfully."
                : "Profile updated successfully.";

            if (function_exists('log_event')) {
                log_event(
                    'info',
                    "Patient profile updated: $new_email (ID {$patient['patient_id']}), password changed: " . ($change_password ? 'yes' : 'no')
                );
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();

            if (function_exists('log_event')) {
                log_event('error', "Failed to update profile/password for patient ID {$patient['patient_id']}: " . $e->getMessage());
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
    <title>Edit Profile | Pharma System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f5fb;
            font-family: 'Arial', sans-serif;
        }

        .profile-container {
            max-width: 650px;
            margin: 50px auto;
            padding: 28px 26px 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
        }

        .profile-container h2 {
            margin-bottom: 6px;
        }

        .profile-container p.sub {
            margin-top: 0;
            margin-bottom: 18px;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .form-section-title {
            margin-top: 20px;
            margin-bottom: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
        }

        .form-text-muted {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .btn-primary {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }

        .btn-secondary {
            margin-top: 10px;
        }

        .alert {
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <h2>Edit Your Profile</h2>
        <p class="sub">Update your contact details and optionally change your password.</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="edit_profile.php" autocomplete="off">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-section-title">Personal Information</div>

            <div class="form-group">
                <label for="patient_name">Full Name</label>
                <input
                    type="text"
                    class="form-control"
                    id="patient_name"
                    name="patient_name"
                    value="<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="patient_email">Email Address</label>
                <input
                    type="email"
                    class="form-control"
                    id="patient_email"
                    name="patient_email"
                    value="<?php echo htmlspecialchars($patient['patient_email'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="patient_contact">Phone Number</label>
                <input
                    type="text"
                    class="form-control"
                    id="patient_contact"
                    name="patient_contact"
                    value="<?php echo htmlspecialchars($patient_contact, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="patient_address">Address</label>
                <textarea
                    class="form-control"
                    id="patient_address"
                    name="patient_address"
                    rows="3"
                    required
                ><?php echo htmlspecialchars($patient_address, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="form-section-title">
                Change Password <span class="form-text-muted">(optional)</span>
            </div>
            <p class="form-text-muted">
                Leave these fields blank if you do not want to change your password.  
                New password must be strong - at least 10 characters and include uppercase, lowercase, digit, and a special character.
            </p>

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="current_password"
                    name="current_password"
                    placeholder="Enter current password"
                >
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="new_password"
                    name="new_password"
                    placeholder="Strong password"
                >
            </div>

            <div class="form-group">
                <label for="confirm_new_password">Confirm New Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="confirm_new_password"
                    name="confirm_new_password"
                    placeholder="Re-type new password"
                >
            </div>

            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
        
        <a href="patient_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
