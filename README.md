# Pharma Management System – Secure Software Design

A web-based Pharma Management System developed using PHP & MySQL, focused on secure software design principles.
The system manages medicines, manufacturers, orders, appointments, and users with built-in security mechanisms such as authentication, MFA, CSRF protection, and logging.

This project was developed as an academic final-year project, emphasizing security, access control, and safe web application practices.

---

## Project Objectives

- Digitize pharmacy operations (medicines, orders, manufacturers)
- Implement secure authentication and authorization
- Demonstrate secure software design concepts
- Prevent common web vulnerabilities (CSRF, unauthorized access)
- Maintain audit logs for system activity

---

## Key Features

### User Management
- Admin, Employee, Doctor, Patient roles
- Secure login & logout
- Role-based dashboards
- Profile management

### Pharmacy Management
- Medicine inventory management
- Manufacturer management
- Orders & order confirmation
- Appointment booking system

### Security Features
- Multi-Factor Authentication (OTP via Email)
- CSRF Protection
- Secure password handling
- Session management
- Activity logging (logs/app.log)
- Input validation & access control

---

## Technology Stack

- Frontend: HTML, CSS
- Backend: PHP
- Database: MySQL
- Security: CSRF Tokens, MFA (OTP), Sessions
- Server: Apache (XAMPP)
- Dependency Manager: Composer

---

## Project Structure

```
final-project/
├── index.php
├── config.php
├── auth.php
├── csrf.php
├── login.php
├── logout.php
├── mfa_verify.php
├── mail_otp.php
├── medicine.php
├── manufacturer.php
├── orders.php
├── appointment.php
├── employee_dashboard.php
├── doctor.php
├── edit_profile.php
├── logs/
│   └── app.log
├── composer.json
└──composer.lock

```

---

## How to Run the Project

1. Install XAMPP and start Apache & MySQL
2. Extract project folder into:
   C:\xampp\htdocs\
3. Create a MySQL database named:
   pharmadb
4. Configure database credentials in config.php
5. Run the following command inside project folder:
   composer install
6. Open browser and visit:
   http://localhost/final-project/

---

## Security Design Highlights

- CSRF Protection to prevent forged requests
- OTP-based Multi-Factor Authentication
- Session validation and role-based access control
- Activity logging for auditing

---

## Author

Umer Fazal  
PMS-Secure Software Design

---

## License

Educational use only.
