<?php
// Prescription management (admin/staff) with prepared statements + CSRF

session_start();
require 'config.php';
require 'csrf.php';
include 'navbar.php';

$message      = '';
$error        = '';
$edit_pres    = null;

// -------- ROLE CHECK: only admin/staff can access --------
// Require logged-in user + MFA + correct role
if (
    !isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['mfa_ok']) ||
    $_SESSION['mfa_ok'] !== true ||
    !in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: login.php');
    exit();
}


// -------- DELETE PRESCRIPTION (GET) --------
if (isset($_GET['delete'], $_GET['medicine_id'], $_GET['patient_id'], $_GET['doctor_id'])) {
    $medicine_id = (int)$_GET['medicine_id'];
    $patient_id  = (int)$_GET['patient_id'];
    $doctor_id   = (int)$_GET['doctor_id'];

    if ($medicine_id > 0 && $patient_id > 0 && $doctor_id > 0) {
        $stmt = $conn->prepare(
            "DELETE FROM Prescription2 
             WHERE medicine_id = ? AND patient_id = ? AND doctor_id = ?"
        );
        $stmt->bind_param("iii", $medicine_id, $patient_id, $doctor_id);

        if ($stmt->execute()) {
            $message = "Prescription successfully deleted!";
        } else {
            $error = "Error deleting prescription.";
        }
    } else {
        $error = "Invalid prescription identifiers.";
    }
}

// -------- CREATE / UPDATE (POST) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();

        $medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;
        $patient_id  = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $doctor_id   = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
        $potency     = trim($_POST['potency'] ?? '');
        $when_to_take = trim($_POST['when_to_take'] ?? '');
        $amount      = trim($_POST['amount'] ?? '');
        $special_instruction = trim($_POST['special_instruction'] ?? '');
        $duration    = trim($_POST['duration'] ?? '');

        if ($medicine_id <= 0 || $patient_id <= 0 || $doctor_id <= 0) {
            $error = "Please select valid medicine, patient and doctor.";
        } elseif ($potency === '' || $when_to_take === '' || $amount === '' || $special_instruction === '' || $duration === '') {
            $error = "All fields are required.";
        } elseif (!ctype_digit($amount)) {
            $error = "Amount must be a valid number.";
        } else {
            if (isset($_POST['submit_create'])) {
                // INSERT or UPDATE (ON DUPLICATE KEY)
                $stmt = $conn->prepare(
                    "INSERT INTO Prescription2
                        (medicine_id, potency, patient_id, doctor_id, when_to_take, amount, special_instruction, duration)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        potency = VALUES(potency),
                        when_to_take = VALUES(when_to_take),
                        amount = VALUES(amount),
                        special_instruction = VALUES(special_instruction),
                        duration = VALUES(duration)"
                );
                $stmt->bind_param(
                    "isiissss",
                    $medicine_id,
                    $potency,
                    $patient_id,
                    $doctor_id,
                    $when_to_take,
                    $amount,
                    $special_instruction,
                    $duration
                );

                if ($stmt->execute()) {
                    $message = "Prescription successfully added or updated!";
                } else {
                    $error = "Error saving prescription.";
                }

            } elseif (isset($_POST['submit_update'])) {
                // STRICT UPDATE by composite PK
                $stmt = $conn->prepare(
                    "UPDATE Prescription2 
                     SET potency = ?, when_to_take = ?, amount = ?, special_instruction = ?, duration = ?
                     WHERE medicine_id = ? AND patient_id = ? AND doctor_id = ?"
                );
                $stmt->bind_param(
                    "sssssiii",
                    $potency,
                    $when_to_take,
                    $amount,
                    $special_instruction,
                    $duration,
                    $medicine_id,
                    $patient_id,
                    $doctor_id
                );

                if ($stmt->execute()) {
                    $message = "Prescription successfully updated!";
                } else {
                    $error = "Error updating prescription.";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error processing prescription request.";
    }
}

// -------- FETCH ONE PRESCRIPTION FOR EDIT (GET) --------
if (isset($_GET['edit'], $_GET['medicine_id'], $_GET['patient_id'], $_GET['doctor_id'])) {
    $m_id = (int)$_GET['medicine_id'];
    $p_id = (int)$_GET['patient_id'];
    $d_id = (int)$_GET['doctor_id'];

    if ($m_id > 0 && $p_id > 0 && $d_id > 0) {
        $stmt = $conn->prepare(
            "SELECT * FROM Prescription2 
             WHERE medicine_id = ? AND patient_id = ? AND doctor_id = ?"
        );
        $stmt->bind_param("iii", $m_id, $p_id, $d_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_pres = $res->fetch_assoc();
    }
}

// -------- FETCH DROPDOWNS (DOCTORS, PATIENTS, MEDICINES) --------
$doctors      = [];
$patients     = [];
$medicines    = [];

$doc_stmt = $conn->prepare("SELECT doctor_id, doctor_name FROM Doctor ORDER BY doctor_name ASC");
$doc_stmt->execute();
$doc_res = $doc_stmt->get_result();
while ($row = $doc_res->fetch_assoc()) {
    $doctors[] = $row;
}

$pat_stmt = $conn->prepare("SELECT patient_id, patient_name FROM Patient ORDER BY patient_name ASC");
$pat_stmt->execute();
$pat_res = $pat_stmt->get_result();
while ($row = $pat_res->fetch_assoc()) {
    $patients[] = $row;
}

$med_stmt = $conn->prepare("SELECT medicine_id, Medicine_name FROM medicine4 ORDER BY Medicine_name ASC");
$med_stmt->execute();
$med_res = $med_stmt->get_result();
while ($row = $med_res->fetch_assoc()) {
    $medicines[] = $row;
}

// -------- FETCH ALL PRESCRIPTIONS FOR TABLE (join names) --------
$list_stmt = $conn->prepare(
    "SELECT 
         pr.medicine_id,
         pr.patient_id,
         pr.doctor_id,
         pr.potency,
         pr.when_to_take,
         pr.amount,
         pr.special_instruction,
         pr.duration,
         m.Medicine_name,
         p.patient_name,
         d.doctor_name
     FROM Prescription2 pr
     JOIN medicine4 m ON pr.medicine_id = m.medicine_id
     JOIN Patient p   ON pr.patient_id  = p.patient_id
     JOIN Doctor d    ON pr.doctor_id   = d.doctor_id
     ORDER BY p.patient_name, d.doctor_name, m.Medicine_name"
);
$list_stmt->execute();
$prescription_list = $list_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prescription Management | Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
            color: #333;
        }

        .form-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .form-container select,
        .form-container input,
        .form-container textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
        }

        .form-container button:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #007bff;
            color: white;
        }

        table td a {
            text-decoration: none;
            color: #007bff;
        }

        table td a:hover {
            color: #0056b3;
        }

        .msg {
            margin-top: 10px;
            color: #16a34a;
        }

        .err {
            margin-top: 10px;
            color: #dc2626;
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
            <li><strong>Pharma Management System</strong></li>
        </ul>
    </nav>
</header>

<main class="container">
    <hgroup>
        <h2>Prescription Management</h2>
        <h3>Manage Prescription Information</h3>
    </hgroup>

    <?php if ($message !== ''): ?>
        <p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- Create / Edit Prescription Form -->
    <section class="form-container">
        <h3><?php echo $edit_pres ? 'Edit Prescription' : 'Create Prescription'; ?></h3>
        <form action="prescription.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <?php if ($edit_pres): ?>
                <input type="hidden" name="medicine_id" value="<?php echo (int)$edit_pres['medicine_id']; ?>">
                <input type="hidden" name="patient_id" value="<?php echo (int)$edit_pres['patient_id']; ?>">
                <input type="hidden" name="doctor_id"  value="<?php echo (int)$edit_pres['doctor_id']; ?>">
            <?php endif; ?>

            <label for="medicine_id">Medicine</label>
            <select name="medicine_id" <?php echo $edit_pres ? 'disabled' : 'required'; ?>>
                <option value="">Select Medicine</option>
                <?php foreach ($medicines as $m): ?>
                    <?php
                    $selected = $edit_pres && $edit_pres['medicine_id'] == $m['medicine_id'] ? 'selected' : '';
                    ?>
                    <option value="<?php echo (int)$m['medicine_id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($m['Medicine_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="potency">Potency</label>
            <input type="text" name="potency"
                   value="<?php echo $edit_pres ? htmlspecialchars($edit_pres['potency'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="patient_id">Patient</label>
            <select name="patient_id" <?php echo $edit_pres ? 'disabled' : 'required'; ?>>
                <option value="">Select Patient</option>
                <?php foreach ($patients as $p): ?>
                    <?php
                    $selected = $edit_pres && $edit_pres['patient_id'] == $p['patient_id'] ? 'selected' : '';
                    ?>
                    <option value="<?php echo (int)$p['patient_id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($p['patient_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="doctor_id">Doctor</label>
            <select name="doctor_id" <?php echo $edit_pres ? 'disabled' : 'required'; ?>>
                <option value="">Select Doctor</option>
                <?php foreach ($doctors as $d): ?>
                    <?php
                    $selected = $edit_pres && $edit_pres['doctor_id'] == $d['doctor_id'] ? 'selected' : '';
                    ?>
                    <option value="<?php echo (int)$d['doctor_id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($d['doctor_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="when_to_take">When to Take</label>
            <input type="text" name="when_to_take"
                   value="<?php echo $edit_pres ? htmlspecialchars($edit_pres['when_to_take'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="amount">Amount</label>
            <input type="number" name="amount"
                   value="<?php echo $edit_pres ? htmlspecialchars($edit_pres['amount'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="special_instruction">Special Instruction</label>
            <input type="text" name="special_instruction"
                   value="<?php echo $edit_pres ? htmlspecialchars($edit_pres['special_instruction'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <label for="duration">Duration</label>
            <input type="text" name="duration"
                   value="<?php echo $edit_pres ? htmlspecialchars($edit_pres['duration'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                   required>

            <button type="submit" name="<?php echo $edit_pres ? 'submit_update' : 'submit_create'; ?>">
                <?php echo $edit_pres ? 'Update Prescription' : 'Create Prescription'; ?>
            </button>
        </form>
    </section>

    <!-- Prescription Table -->
    <section>
        <h3>Prescription List</h3>
        <table>
            <thead>
            <tr>
                <th>Medicine</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Potency</th>
                <th>When to Take</th>
                <th>Amount</th>
                <th>Special Instruction</th>
                <th>Duration</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($prescription_list->num_rows === 0): ?>
                <tr>
                    <td colspan="9">No prescriptions found.</td>
                </tr>
            <?php else: ?>
                <?php while ($row = $prescription_list->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['Medicine_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['doctor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['potency'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['when_to_take'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['amount'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['special_instruction'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['duration'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="prescription.php?edit=true&medicine_id=<?php echo (int)$row['medicine_id']; ?>&patient_id=<?php echo (int)$row['patient_id']; ?>&doctor_id=<?php echo (int)$row['doctor_id']; ?>">Edit</a> |
                            <a href="prescription.php?delete=true&medicine_id=<?php echo (int)$row['medicine_id']; ?>&patient_id=<?php echo (int)$row['patient_id']; ?>&doctor_id=<?php echo (int)$row['doctor_id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this prescription?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<footer>
    <small>&copy; 2024 Pharma Management System</small>
</footer>
</body>
</html>
