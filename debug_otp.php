<?php
require_once 'db.php';

// Check what OTP is stored for admin1
$stmt = getDB()->prepare("SELECT id, username, otp_code, otp_expires FROM users WHERE id = 1");
$stmt->execute();
$user = $stmt->fetch();

echo "User ID: " . $user['id'] . "\n";
echo "Username: " . $user['username'] . "\n";
echo "OTP Code in DB: " . ($user['otp_code'] ? '(stored)' : 'NULL or empty') . "\n";
echo "OTP Expires: " . ($user['otp_expires'] ?? 'NULL') . "\n";
echo "\nCurrent Time: " . (new DateTime())->format('Y-m-d H:i:s') . "\n";

if ($user['otp_expires']) {
    $expires = new DateTime($user['otp_expires']);
    $now = new DateTime();
    $diff = $now->diff($expires);
    echo "Time remaining: " . $diff->format('%i minutes %s seconds') . "\n";
    if ($now > $expires) {
        echo "⚠️ OTP IS EXPIRED!\n";
    } else {
        echo "✓ OTP is still valid\n";
    }
}
?>
