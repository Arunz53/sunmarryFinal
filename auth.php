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

// Ensure OTP columns exist for Super Admin flow
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');
    if (!in_array('otp_code', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN otp_code VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('otp_expires', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN otp_expires DATETIME DEFAULT NULL");
    }
} catch (PDOException $e) {
    // ignore
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

// OTP helpers for Super Admin
function generate_and_send_otp_for_user(int $user_id): bool {
    $pdo = getDB();
    // 6-digit OTP
    $otp = random_int(100000, 999999);
    $hashed = password_hash((string)$otp, PASSWORD_DEFAULT);
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires = ? WHERE id = ?");
        $stmt->execute([$hashed, $expiresAt, $user_id]);
    } catch (PDOException $e) {
        return false;
    }

    // Determine recipient: use user's email when present, otherwise fallback to admin address
    try {
        $emStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $emStmt->execute([$user_id]);
        $userEmail = $emStmt->fetchColumn();
    } catch (Exception $e) {
        $userEmail = null;
    }
    $to = $userEmail ?: 'arunasaithambiofficial@gmail.com';
    $subject = 'Super Admin OTP - Sun Matrimony';
    $message = "Your one-time login code is: $otp\nThis code expires in 5 minutes.";
    $headers = "From: no-reply@localhost" . "\r\n" . "Content-Type: text/plain; charset=UTF-8";

    $sent = false;
    $method = 'none';
    $errorMsg = '';

    // PHPMailer if present
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $method = 'phpmailer';
            // If user configures SMTP, they should define these constants in db.php or a config file
            if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            }
            $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SMTP_USER') ? SMTP_USER : 'no-reply@localhost');
            $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Sun Matrimony';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $sent = (bool)$mail->send();
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() . ' | PHPMailerError: ' . $mail->ErrorInfo;
            $sent = false;
        }
    } else {
        // Fallback to PHP mail()
        $method = 'mail()';
        try {
            $sent = (bool)@mail($to, $subject, $message, $headers);
            if (!$sent) $errorMsg = 'mail() returned false';
        } catch (\Exception $e) {
            $sent = false;
            $errorMsg = $e->getMessage();
        }
    }

    // Log attempts for debugging (creates public_html/logs/otp.log)
    try {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $entry = sprintf("%s | user_id=%d | to=%s | otp=%s | method=%s | sent=%s | err=%s\n",
            (new DateTime())->format('Y-m-d H:i:s'), $user_id, $to, $otp, $method, $sent ? '1' : '0', str_replace("\n", ' ', $errorMsg)
        );
        @file_put_contents($logDir . '/otp.log', $entry, FILE_APPEND | LOCK_EX);
    } catch (\Exception $e) {
        // ignore logging errors
    }

    return $sent;
}

function verify_otp_for_user(int $user_id, string $otp): bool {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("SELECT otp_code, otp_expires FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['otp_code'] || !$row['otp_expires']) return false;
        $expires = new DateTime($row['otp_expires']);
        $now = new DateTime();
        if ($now > $expires) return false;
        if (!password_verify($otp, $row['otp_code'])) return false;

        // Clear OTP fields
        $clear = $pdo->prepare("UPDATE users SET otp_code = NULL, otp_expires = NULL WHERE id = ?");
        $clear->execute([$user_id]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
?>