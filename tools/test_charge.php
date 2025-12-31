<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

// Create or find a test user
$pdo = getDB();
$username = 'cli_test_customer';
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) {
    $pwd = password_hash('Test1234!', PASSWORD_DEFAULT);
    // try to insert with dbRole mapping
    // detect role column type
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    $dbRole = 'customer';
    if (strpos($col['Type'], "'customer'") === false && strpos($col['Type'], "'support'") !== false) {
        $dbRole = 'support';
    }
    $pdo->prepare("INSERT INTO users (username, password, role, credits, profiles_viewed) VALUES (?, ?, ?, 20, 0)")
        ->execute([$username, $pwd, $dbRole]);
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    echo "Inserted test user id={$user['id']} role={$user['role']}\n";
}

// Simulate session
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = $user['id'];
// Normalize role for session
$_SESSION['role'] = ($user['role'] === 'support') ? 'customer' : $user['role'];

// Choose profile id 1 (ensure exists or create)
$profile_id = 1;
$profile = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
$profile->execute([$profile_id]);
if (!$profile->fetch()) {
    // create a minimal profile to test
    $pdo->prepare("INSERT INTO profiles (name, age, gender, district, city, caste, marriage_type) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute(['TestProfile', 30, 'Male', 'Chennai', 'Chennai', 'Test', 'First']);
    $profile_id = $pdo->lastInsertId();
    echo "Created profile id=$profile_id\n";
}

// Show credits before
$creditsBefore = (int)$pdo->prepare("SELECT credits FROM users WHERE id = ?")->execute([$user['id']]) ? (int)$pdo->prepare("SELECT credits FROM users WHERE id = ?")->fetchColumn() : null;
// The above is messy; do properly
$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$creditsBefore = (int)$stmt->fetchColumn();

echo "Credits before: $creditsBefore\n";

// Set GET id and call incrementProfileViews
$_GET['id'] = $profile_id;
$result = incrementProfileViews();

$stmt->execute([$user['id']]);
$creditsAfter = (int)$stmt->fetchColumn();

var_export([ 'increment_result' => $result, 'credits_before' => $creditsBefore, 'credits_after' => $creditsAfter ]);

?>