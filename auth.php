<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure required user columns and support_profile_views table exist
try {
    $pdo = getDB();
    $colStmt = $pdo->query("SHOW COLUMNS FROM users");
    $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');

    if (!in_array('role', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('super_admin', 'manager', 'customer') NOT NULL DEFAULT 'customer'");
    }
    if (!in_array('credits', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN credits INT DEFAULT 20");
    }
    if (!in_array('profiles_viewed', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profiles_viewed INT DEFAULT 0");
    }
    if (!in_array('last_login', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
    }
    // Create support_profile_views table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_profile_views (
        user_id INT NOT NULL,
        profile_id INT NOT NULL,
        PRIMARY KEY (user_id, profile_id)
    )");
} catch (PDOException $e) {
    // If we can't modify schema (no privileges or table missing), continue gracefully.
}

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function getUserRole() {
    $r = $_SESSION['role'] ?? null;
    // Normalize old DB value 'support' to app-facing 'customer'
    if ($r === 'support') return 'customer';
    return $r;
}

function checkPermission($required_role) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $role_hierarchy = [
        'super_admin' => 3,
        'manager' => 2,
        'customer' => 1
    ];

    $user_role = getUserRole();
    
    if (!isset($role_hierarchy[$user_role]) || 
        $role_hierarchy[$user_role] < $role_hierarchy[$required_role]) {
        header('Location: access_denied.php');
        exit();
    }
    
    // Do not auto-logout customers here; charging happens on actions (view/print).
    
    return true;
}

function chargeCustomerForProfileAction(int $profile_id): bool {
    // Returns true if action allowed (credits consumed or already consumed for this profile).
    if (getUserRole() !== 'customer') return true;
    $pdo = getDB();
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id || $profile_id <= 0) return true;

    // If already recorded for this profile, do not charge again
    $stmt = $pdo->prepare("SELECT 1 FROM support_profile_views WHERE user_id = ? AND profile_id = ?");
    $stmt->execute([$user_id, $profile_id]);
    if ($stmt->fetch()) return true;

    // Check credits
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $credits = (int)$stmt->fetchColumn();
    if ($credits <= 0) {
        return false; // limit exceeded
    }

    // Charge: insert view and decrement credit in a transaction
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO support_profile_views (user_id, profile_id) VALUES (?, ?)")
            ->execute([$user_id, $profile_id]);
        $pdo->prepare("UPDATE users SET credits = credits - 1 WHERE id = ?")
            ->execute([$user_id]);
        // legacy counter
        if (in_array('profiles_viewed', array_column($pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field'))) {
            $pdo->prepare("UPDATE users SET profiles_viewed = profiles_viewed + 1 WHERE id = ?")->execute([$user_id]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // On failure, allow action to proceed (do not lock user out due to DB error)
        return true;
    }
}

function incrementProfileViews(): bool {
    $profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    return chargeCustomerForProfileAction($profile_id);
}
?>