<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome - Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        /* Custom Styling */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7fb;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #005b96;
            color: white;
            padding: 10px 0;
            text-align: center;
        }

        header h1 {
            margin: 0;
            font-size: 36px;
        }

        nav {
            text-align: center;
            margin: 20px 0;
        }

        nav a {
            margin: 0 15px;
            text-decoration: none;
            color: #005b96;
            font-weight: bold;
            font-size: 18px;
        }

        nav a:hover {
            color: #007acc;
        }

        .main-container {
            max-width: 1100px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .card h3 {
            margin-bottom: 15px;
            font-size: 20px;
        }

        .card p {
            font-size: 16px;
            color: #555;
        }

        .card a {
            display: inline-block;
            margin-top: 10px;
            background-color: #005b96;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        .card a:hover {
            background-color: #007acc;
        }

        footer {
            background-color: #005b96;
            color: white;
            padding: 15px 0;
            text-align: center;
        }

        footer small {
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            header h1 {
                font-size: 28px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            nav {
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <h1>Pharma Management System</h1>
    </header>

    <!-- Navigation Bar -->
    <nav>
        <a href="homepage.php">Home</a>
        <a href="login_patient.php">Patient Login</a>
        <a href="login_employee.php">Employee Login</a>
        <a href="contact_us.html">Contact Us</a>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <h2>Welcome to the Pharma Management System</h2>
        <p>Your one-stop solution for managing pharmacy services. Below are the available resources:</p>

        <!-- Grid Layout for Resources -->
        <div class="grid">
            <!-- Card 1: Patient Dashboard -->
            <div class="card">
                <h3>Patient Dashboard</h3>
                <p>Manage your prescriptions, orders, and appointments.</p>
                <a href="patient_dashboard.php">Go to Dashboard</a>
            </div>

            <!-- Card 2: Employee Dashboard -->
            <div class="card">
                <h3>Employee Dashboard</h3>
                <p>Manage orders, inventory, and prescriptions for the pharmacy.</p>
                <a href="employee_dashboard.php">Go to Dashboard</a>
            </div>

            <!-- Card 3: Feedback -->
            <div class="card">
                <h3>Submit Feedback</h3>
                <p>Provide feedback on the services you received.</p>
                <a href="submit_feedback.php">Submit Feedback</a>
            </div>

            <!-- Card 4: Appointments -->
            <div class="card">
                <h3>Book an Appointment</h3>
                <p>Schedule an appointment with a doctor or pharmacy.</p>
                <a href="book_appointment.php">Book Appointment</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
    </footer>

</body>
</html>
