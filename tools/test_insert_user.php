<?php
require_once __DIR__ . '/../db.php';

try {
    $pdo = getDB();

    // Inspect role column
    $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $col = $colStmt->fetch(PDO::FETCH_ASSOC);
    echo "role column info:\n";
    var_export($col);
    echo "\n\n";

    // Try to insert a test user
    $username = 'test_customer_' . time();
    $password = password_hash('Test1234!', PASSWORD_DEFAULT);
    $role = 'customer';

    // Determine DB-accepted role
    $dbRole = $role;
    if ($col && isset($col['Type'])) {
        $type = $col['Type'];
        if (strpos($type, "'customer'") === false && strpos($type, "'support'") !== false) {
            $dbRole = 'support';
        }
    }

    echo "Using dbRole= $dbRole for insert\n";

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, credits, profiles_viewed) VALUES (?, ?, ?, 10, 0)");
    $stmt->execute([$username, $password, $dbRole]);

    echo "Inserted user: $username with role $dbRole\n";

} catch (PDOException $e) {
    echo "PDOException: " . $e->getMessage() . "\n";
}

?>