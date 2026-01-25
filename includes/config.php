<?php
// Strict error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Timezone
date_default_timezone_set('Asia/Karachi');

// Application Configuration
define('APP_NAME', 'University Bus Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]");
define('BASE_PATH', dirname(__DIR__));

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_LIFE', 1800); // 30 minutes

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);

// Database Configuration
define('DB_HOST', 'premium281.web-hosting.com');
define('DB_NAME', 'prosdfwo_bus-8-pak-austria-v1');
define('DB_USER', 'prosdfwo_bus8-pak-austria-v1');
define('DB_PASS', 'Bus8PakAustriaV1');
define('DB_CHARSET', 'utf8mb4');


// QR Code Configuration
define('QR_CODE_SIZE', 300);
define('QR_CODE_MARGIN', 4);

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@prosensia.pk');
define('SMTP_PASS', 'AppPasswordHere');
define('FROM_EMAIL', 'noreply@prosensia.pk');
define('FROM_NAME', 'University Bus System');

// Honey Pot Configuration
$honey_pot_fields = [
    'website',
    'homepage',
    'email_confirmation',
    'phone_confirmation'
];

// Rate Limiting Configuration
$rate_limits = [
    'login' => ['attempts' => 5, 'time' => 900],
    'register' => ['attempts' => 3, 'time' => 3600],
    'password_reset' => ['attempts' => 3, 'time' => 1800]
];

// Load environment-specific config if exists
if (file_exists(__DIR__ . '/config.local.php')) {
    include __DIR__ . '/config.local.php';
}
?>