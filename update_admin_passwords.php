<?php
// One-off script to update seeded admin passwords to secure password_hash values.
// Run from the project root: php update_admin_passwords.php

require_once 'db.php';

$admins = [
    ['admin1', 'Admin123!'],
    ['admin2', 'Manager123!'],
    ['admin3', 'Support123!']
];

try {
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");

    foreach ($admins as $a) {
        [$username, $plain] = $a;
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $update->execute([$hash, $username]);
        echo "Updated password for: $username\n";
    }

    echo "Done.\n";
} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}
