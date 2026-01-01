<?php
// Simple testing endpoint to trigger OTP send for a user id
// Usage: test_send_otp.php?id=123
require_once 'db.php';
require_once 'auth.php';

if (!isset($_GET['id'])) {
    echo "Missing ?id= parameter\n";
    exit;
}
$id = (int)$_GET['id'];
$result = generate_and_send_otp_for_user($id);
$log = '';
$logFile = __DIR__ . '/logs/otp.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log = implode("\n", array_slice($lines, -10));
}

echo "send_result=" . ($result ? '1' : '0') . "\n";
if ($log !== '') {
    echo "--- last log lines ---\n" . $log . "\n";
}
?>