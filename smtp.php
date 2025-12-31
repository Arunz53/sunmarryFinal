<?php
// smtp.php - Gmail SMTP configuration
// This file should be loaded BEFORE db.php to ensure correct SMTP credentials
// DO NOT commit smtp.php containing real credentials to version control.

// Only define if not already defined
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_USER')) define('SMTP_USER', 'hifivewebdesign@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'ugindxmvqkwiwvpj'); // <-- App Password for hifivewebdesign@gmail.com
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');

if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'hifivewebdesign@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Sunmarry OTP');

?>

