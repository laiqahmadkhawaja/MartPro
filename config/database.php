<?php
/**
 * MartPro Database Connection Manager
 * Native PDO with prepared statements, UTF-8, and exception handling
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'martpro');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get the singleton PDO database connection
 * @return PDO
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("MartPro Database Connection Error: " . $e->getMessage());
            if (php_sapi_name() === 'cli' || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. Please verify database service status.'
                ]);
                exit;
            } else {
                die("<h3>Database Connection Error</h3><p>Could not connect to database. Please check configuration or run <a href='install.php'>install.php</a>.</p>");
            }
        }
    }
    return $pdo;
}
