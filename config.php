<?php
// ======================================
// Database connection (MySQL / XAMPP)
// ======================================
$DB_HOST = 'localhost';
$DB_USER = 'root';       // XAMPP default
$DB_PASS = '';           // XAMPP default
$DB_NAME = 'pharmadb';   // Your DB name

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// ======================================
// Strong password validator (reusable)
// ======================================
if (!function_exists('validate_strong_password')) {
    /**
     * Returns [bool $ok, string $errorMessage]
     */
    function validate_strong_password(string $password): array
    {
        // length
        if (strlen($password) < 10) {
            return [false, "Password must be at least 10 characters long."];
        }

        // uppercase
        if (!preg_match('/[A-Z]/', $password)) {
            return [false, "Password must contain at least one UPPERCASE letter (A-Z)."];
        }

        // lowercase
        if (!preg_match('/[a-z]/', $password)) {
            return [false, "Password must contain at least one lowercase letter (a-z)."];
        }

        // digit
        if (!preg_match('/\d/', $password)) {
            return [false, "Password must contain at least one digit (0-9)."];
        }

        // special char
        if (!preg_match('/[\W_]/', $password)) { // non-word or underscore
            return [false, "Password must contain at least one special character (like !@#\$%^&*)."];
        }

        // no spaces
        if (preg_match('/\s/', $password)) {
            return [false, "Password must not contain spaces."];
        }

        return [true, ""];
    }
}

// ======================================
// Secure session configuration
// ======================================
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
        session_regenerate_id(true);
    }

    // Inactivity timeout: 30 minutes
    $INACTIVITY_LIMIT = 1800;

    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > $INACTIVITY_LIMIT)) {

        session_unset();
        session_destroy();

        session_start();
        $_SESSION['created_at']   = time();
        $_SESSION['session_note'] = 'expired_due_to_inactivity';
    }

    $_SESSION['last_activity'] = time();
}

// ======================================
// AES-256 encryption helpers
// ======================================
if (!defined('AES_KEY')) {
    define('AES_KEY', 'this_is_a_32_byte_demo_key__1234'); // 32 chars
}
if (!defined('AES_IV')) {
    define('AES_IV',  '1234567890123456');                 // 16 chars
}

if (!function_exists('encrypt_field')) {
    function encrypt_field($plaintext) {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }

        return openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            AES_KEY,
            0,
            AES_IV
        );
    }
}

if (!function_exists('decrypt_field')) {
    function decrypt_field($ciphertext) {
        if ($ciphertext === null || $ciphertext === '') {
            return $ciphertext;
        }

        return openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            AES_KEY,
            0,
            AES_IV
        );
    }
}

// ======================================
// Simple file-based logger
// ======================================
if (!function_exists('log_event')) {
    function log_event($level, $message) {
        $level   = strtoupper($level);
        $time    = date('Y-m-d H:i:s');
        $line    = "[$time] [$level] $message\n";
        $logDir  = __DIR__ . '/logs';
        $logFile = $logDir . '/app.log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0700, true);
        }

        error_log($line, 3, $logFile);
    }
}
