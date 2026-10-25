<?php
/**
 * MartPro Enterprise Retail POS & Store Management
 * Application Core Configuration
 */

if (!defined('MARTPRO_INIT')) {
    define('MARTPRO_INIT', true);
}

// Session security settings before session starts
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Application Information
define('APP_NAME', 'MartPro');
define('APP_TAGLINE', 'Smart Store. Better Business.');
define('APP_VERSION', '1.0.0 Enterprise');

// Timezone
date_default_timezone_set('UTC');

// Base Paths and URLs
define('APP_ROOT', dirname(__DIR__));
$is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
// Clean base URL path
$base_path = rtrim(str_replace('\\', '/', $script_dir), '/');
if (strpos($base_path, '/modules') !== false) {
    $base_path = substr($base_path, 0, strpos($base_path, '/modules'));
} elseif (strpos($base_path, '/api') !== false) {
    $base_path = substr($base_path, 0, strpos($base_path, '/api'));
} elseif (strpos($base_path, '/print') !== false) {
    $base_path = substr($base_path, 0, strpos($base_path, '/print'));
}
define('BASE_URL', $protocol . $host . (empty($base_path) || $base_path === '/' ? '' : $base_path));

// Environment Mode: 'production' or 'development'
define('APP_ENV', 'development');

if (APP_ENV === 'development') {
    ini_set('display_errors', 0); // Keep display off to prevent corrupting AJAX JSON responses
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Session Lifetime (seconds) - Default 8 hours
define('SESSION_LIFETIME', 28800);
