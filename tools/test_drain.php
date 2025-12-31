<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
$pdo = getDB();

// use existing cli_test_customer
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute(['cli_test_customer']);
$user = $stmt->fetch();
if (!$user) { echo "No test user\n"; exit(); }

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = ($user['role'] === 'support') ? 'customer' : $user['role'];

// Drain credits by creating new profiles and charging
for ($i=0; $i<25; $i++) {
    // create a small profile
    $pdo->prepare("INSERT INTO profiles (name, age, gender, district, city, caste, marriage_type) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute(["DrainProfile_$i", 30+$i%5, 'Male', 'Chennai', 'Chennai', 'Test', 'First']);
    $pid = $pdo->lastInsertId();
    $_GET['id'] = $pid;
    $allowed = incrementProfileViews();
    $stmt2 = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt2->execute([$user['id']]);
    $credits = (int)$stmt2->fetchColumn();
    echo "Action $i -> allowed=".($allowed?1:0)." credits=$credits\n";
    if (!$allowed) break;
}

?>