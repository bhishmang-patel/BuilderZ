<?php
// Load Environment Variables
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Application Configuration
define('APP_NAME', getenv('APP_NAME') ?: 'BuilderZ');
define('APP_VERSION', '1.0.0');
define('BASE_URL', getenv('APP_URL') ?: 'http://localhost:8001/');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('CURRENCY_SYMBOL', '₹');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'builderz_erp');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Session Configuration
session_name('BUILDERZ_v2');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Date & Time
date_default_timezone_set('Asia/Kolkata');
define('DATE_FORMAT', 'd-m-Y');
define('DATETIME_FORMAT', 'd-m-Y h:i A');

// Paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('BACKUP_PATH', ROOT_PATH . 'backups/');

// Financial Year
define('FY_START_MONTH', 4); // April

// Pagination
define('RECORDS_PER_PAGE', 25);

// File Upload Limits
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Error Reporting
if (APP_ENV === 'local' || APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . 'error.log');
}
