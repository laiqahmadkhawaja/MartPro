<?php
/**
 * MartPro Enterprise System Installer & Diagnostic Tool
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/config/app.php';

$step = $_GET['step'] ?? 'check';
$errors = [];
$success_msg = '';

// Check PHP extensions & storage permissions
$reqs = [
    'PHP Version >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO Extension'      => extension_loaded('pdo'),
    'PDO MySQL Driver'   => extension_loaded('pdo_mysql'),
    'OpenSSL Extension'  => extension_loaded('openssl'),
    'JSON Extension'     => extension_loaded('json'),
    'Storage Writable'   => is_writable(__DIR__ . '/storage')
];

$all_passed = !in_array(false, $reqs, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = trim($_POST['db_port'] ?? '3306');
    $db_name = trim($_POST['db_name'] ?? 'martpro');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = trim($_POST['db_pass'] ?? '');

    try {
        $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");

        // Run schema
        $schema_sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $pdo->exec($schema_sql);

        // Run seed
        if (!empty($_POST['seed_demo_data'])) {
            $seed_sql = file_get_contents(__DIR__ . '/database/seed.sql');
            $pdo->exec($seed_sql);
        }

        $success_msg = "Database tables and demonstration data created successfully!";
        $step = 'complete';
    } catch (Exception $e) {
        $errors[] = "Installation failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MartPro Installation & Setup</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { background: #0F172A; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .install-box { max-width: 650px; width: 100%; background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
    </style>
</head>
<body>
<div class="install-box">
    <div class="text-center mb-4">
        <img src="assets/images/logo.svg" alt="MartPro Logo" height="48" class="mb-2">
        <h4 class="fw-bold text-navy">System Installer & Health Check</h4>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?>
                <div><i class="bi bi-x-circle-fill me-1"></i> <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 'complete'): ?>
        <div class="alert alert-success text-center py-4">
            <i class="bi bi-check-circle-fill text-success fs-1 mb-2 d-block"></i>
            <h5 class="fw-bold">MartPro Installed Successfully!</h5>
            <p class="text-muted mb-3"><?= htmlspecialchars($success_msg) ?></p>
            <a href="login.php" class="btn btn-primary px-4 py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Go to Sign In Screen
            </a>
        </div>
    <?php else: ?>
        <h6 class="fw-bold mb-3 text-secondary text-uppercase small">1. Server Environment Check</h6>
        <div class="list-group mb-4">
            <?php foreach ($reqs as $name => $ok): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <span class="small fw-semibold"><?= htmlspecialchars($name) ?></span>
                    <?php if ($ok): ?>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-lg me-1"></i> Pass</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-lg me-1"></i> Fail</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h6 class="fw-bold mb-3 text-secondary text-uppercase small">2. Database Configuration</h6>
        <form method="POST">
            <div class="row g-2 mb-3">
                <div class="col-8">
                    <label class="form-label small fw-semibold">Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="127.0.0.1" required>
                </div>
                <div class="col-4">
                    <label class="form-label small fw-semibold">Port</label>
                    <input type="text" name="db_port" class="form-control" value="3306" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Database Name</label>
                <input type="text" name="db_name" class="form-control" value="martpro" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Database User</label>
                    <input type="text" name="db_user" class="form-control" value="root" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="Blank if none">
                </div>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="seed_demo_data" id="seed_demo" value="1" checked>
                <label class="form-check-label small" for="seed_demo">
                    Seed demonstration products, stores, stock, categories, and test accounts
                </label>
            </div>

            <button type="submit" name="run_install" class="btn btn-primary w-100 py-2 fw-semibold" <?= !$all_passed ? 'disabled' : '' ?>>
                <i class="bi bi-database-check me-1"></i> Install / Reset MartPro Database
            </button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
