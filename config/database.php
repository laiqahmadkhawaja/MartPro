<?php
/**
 * MartPro Database Connection Manager
 * Native PDO with prepared statements, UTF-8, and exception handling
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
}

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'martpro');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

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
                    'message' => 'Database connection failed: ' . $e->getMessage()
                ]);
                exit;
            } else {
                $err = htmlspecialchars($e->getMessage());
                $host = htmlspecialchars(DB_HOST);
                $port = htmlspecialchars(DB_PORT);
                $dbname = htmlspecialchars(DB_NAME);
                $user = htmlspecialchars(DB_USER);
                die("
                    <div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; max-width: 600px; margin: 60px auto; padding: 25px; border: 1px solid #fecaca; background: #fff5f5; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                        <h3 style='margin-top: 0; color: #991b1b;'><i class='bi bi-database-exclamation'></i> Database Connection Error</h3>
                        <p style='color: #4b5563; font-size: 0.95rem;'>Could not connect to the database with the current configuration:</p>
                        <div style='background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 0.85rem; margin-bottom: 15px; word-break: break-all;'>
                            {$err}
                        </div>
                        <div style='background: #ffffff; border: 1px solid #e5e7eb; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 0.85rem; margin-bottom: 15px;'>
                            <div><strong>Host:</strong> {$host}:{$port}</div>
                            <div><strong>Database:</strong> {$dbname}</div>
                            <div><strong>User:</strong> {$user}</div>
                        </div>
                        <p style='color: #6b7280; font-size: 0.85rem; margin-bottom: 15px;'>
                            Please check credentials in <code>config/database.local.php</code> or run the interactive installer.
                        </p>
                        <a href='install.php' style='display: inline-block; background: #16a34a; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;'>
                            Run install.php &rarr;
                        </a>
                    </div>
                ");
            }
        }
    }
    return $pdo;
}
