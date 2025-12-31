<?php
// test_mail.php - simple PHPMailer SMTP test (no Composer)
// Place PHPMailer source in: public_html/vendor/phpmailer/phpmailer/src/
// Copy smtp.php.example to smtp.php and set your Gmail App Password before running.

if (!file_exists(__DIR__ . '/smtp.php')) {
    echo "Missing smtp.php config. Copy smtp.php.example to smtp.php and set your App Password.\n";
    exit(1);
}
require_once __DIR__ . '/smtp.php';

// Require PHPMailer classes from the manual download
$autoload = __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
if (!file_exists($autoload)) {
    echo "PHPMailer not found. Download PHPMailer from https://github.com/PHPMailer/PHPMailer and extract 'src' to public_html/vendor/phpmailer/phpmailer/src/\n";
    exit(1);
}
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Basic environment checks
if (!extension_loaded('openssl')) {
    echo "Error: PHP OpenSSL extension is not enabled. Edit php.ini and enable extension=openssl then restart Apache.\n";
    exit(1);
}

// Prepare mail
$to = SMTP_USER; // send to yourself to test
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->SMTPDebug = 2; // 0 = off, 2 = client and server messages
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($to);
    $mail->Subject = 'PHPMailer SMTP test';
    $mail->Body = "This is a test email sent via PHPMailer SMTP (TLS 587).";

    $mail->send();
    echo "SUCCESS: Email sent to {$to}\n";
} catch (Exception $e) {
    echo "ERROR: Message could not be sent. Mailer Error: " . $mail->ErrorInfo . "\n";
}

?>
