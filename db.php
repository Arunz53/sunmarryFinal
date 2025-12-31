<?php
// db.php - local XAMPP / MariaDB settings (for development)








define('DB_HOST', 'localhost');
define('DB_NAME', 'u478906159_marriage');   // local DB name
define('DB_USER', 'root');       // XAMPP default
define('DB_PASS', '');  
// SMTP settings: prefer environment variables for sensitive values.
// For Gmail use an App Password (recommended) and set it in an env var named SMTP_PASS
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
if (!defined('SMTP_USER')) define('SMTP_USER', getenv('SMTP_USER') ?: 'arunasaithambiofficial@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', getenv('SMTP_PASS') ?: ''); // SET your SMTP password/app-password via environment, not in repo
if (!defined('SMTP_PORT')) define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');




function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // Friendly debug while developing; remove or log in production
            die("Connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

$pdo = getDB();

// Start session if not already started and not running in CLI; avoid headers-sent warnings
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>
