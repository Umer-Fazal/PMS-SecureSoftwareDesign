<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pharma Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        /* Custom styling */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            color: #333;
        }

        header {
            background-color: #343a40;
            color: #fff;
            padding: 20px;
            text-align: center;
        }

        header nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        header nav ul li {
            display: inline-block;
            margin-right: 15px;
        }

        hgroup h2 {
            font-size: 2rem;
            color: #333;
        }

        hgroup h3 {
            font-size: 1.2rem;
            color: #666;
        }

        main {
            margin-top: 40px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .grid div {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .button {
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-align: center;
            text-decoration: none;
            font-size: 1.1rem;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #0056b3;
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
        <div class="grid">
            <section>
                <hgroup>
                    <h2>Welcome to the Pharma Management System</h2>
                    <h3>Choose your role to proceed</h3>
                </hgroup>

                <div class="grid">
                    <!-- Employee Login -->
                    <div>
                        <a href="login_employee.php" role="button" class="button">Login as Employee</a>
                    </div>
                    <!-- Patient Registration -->
                    <div>
                        <a href="login_patient.php" role="button" class="button">Login as Patient</a>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="container">
        <small>&copy; 2024 Pharma Management System. All rights reserved.</small>
    </footer>
</body>
</html>
