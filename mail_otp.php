<?php
require __DIR__ . '/vendor/autoload.php';   // Composer autoload
require 'config.php';                       // for log_event, etc.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mfa_code(string $email, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'SENDER_EMAIL';       // YOUR Gmail
        $mail->Password   = 'SENDER_EMAILPASSWORD';     // 16-char app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('RECIPIENT_EMAIL', 'Pharma System');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Pharma System OTP';
        $mail->Body    =
            "Dear user,<br><br>
             Your verification code is: <strong>{$otp}</strong>.<br>
             This code is valid for 5 minutes.<br><br>
             If you did not attempt to login, please ignore this email.";

        $mail->send();
        if (function_exists('log_event')) {
            log_event('info', "OTP email sent to $email");
        }
        return true;

    } catch (Exception $e) {
        if (function_exists('log_event')) {
            log_event('error', "OTP email failed for $email: " . $mail->ErrorInfo);
        }
        return false;
    }
}
