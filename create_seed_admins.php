<?php
// Idempotent script to ensure seeded admin users exist with secure password hashes.
// Run: php create_seed_admins.php
require_once 'db.php';

$admins = [
    ['admin1', 'super_admin', 'Admin123!'],
    ['admin2', 'manager', 'Manager123!'],
    ['admin3', 'customer', 'Support123!']
];

try {
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $insert = $pdo->prepare("INSERT INTO users (username, role, password, credits, profiles_viewed, last_login) VALUES (?, ?, ?, ?, ?, ?)");
    $update = $pdo->prepare("UPDATE users SET role = ?, password = ? WHERE username = ?");

    foreach ($admins as $a) {
        [$username, $role, $plain] = $a;
        $check->execute([$username]);
        $row = $check->fetch();
        $hash = password_hash($plain, PASSWORD_DEFAULT);

        if ($row) {
            // User exists: ensure role and password are up-to-date
            $update->execute([$role, $hash, $username]);
            echo "Updated existing user: $username (role set to $role)\n";
        } else {
            // Insert new user
            // Set default credits=10, profiles_viewed=0, last_login=null
            $insert->execute([$username, $role, $hash, 10, 0, null]);
            echo "Inserted user: $username (role $role)\n";
        }
    }

    echo "Done.\n";
} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}
