<?php
require_once 'db.php';
$stmt = getDB()->prepare('SELECT id, username, role, email FROM users WHERE role = ?');
$stmt->execute(['super_admin']);
$users = $stmt->fetchAll();
foreach ($users as $user) {
    echo 'ID: ' . $user['id'] . ' | Username: ' . $user['username'] . ' | Role: ' . $user['role'] . ' | Email: ' . ($user['email'] ?? 'N/A') . PHP_EOL;
}
?>
