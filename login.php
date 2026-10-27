<?php
define('MARTPRO_INIT', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to index
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error_message = '';
$raw_redirect = $_GET['redirect'] ?? '';

// Sanitize redirect target:
// 1. Prevent open redirects (e.g. //evil.com or http://evil.com)
if (preg_match('#^(https?:)?//#i', $raw_redirect)) {
    $raw_redirect = '';
}

// 2. Strip any leading BASE_PATH if present (e.g. /martpro/ or martpro/) to avoid duplicate prefixes
if (defined('BASE_PATH') && !empty(BASE_PATH)) {
    $clean_base = trim(BASE_PATH, '/');
    while (preg_match('#^' . preg_quote($clean_base, '#') . '(/|$)#i', ltrim($raw_redirect, '/'))) {
        $raw_redirect = preg_replace('#^' . preg_quote($clean_base, '#') . '(/|$)#i', '', ltrim($raw_redirect, '/'));
    }
}

$clean_redirect = ltrim($raw_redirect, '/');
$target_file = explode('?', $clean_redirect)[0];
if (empty($target_file) || $target_file === 'index.php' || $target_file === 'login.php' || $target_file === 'logout.php') {
    $redirect_url = BASE_URL . '/index.php';
    $form_redirect_param = '';
} else {
    $redirect_url = BASE_URL . '/' . $clean_redirect;
    $form_redirect_param = '?redirect=' . urlencode($clean_redirect);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_message = 'Please provide both username and password.';
    } else {
        $result = login($username, $password);
        if ($result['success']) {
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - MartPro Enterprise POS</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <style>
        body {
            background: linear-gradient(135deg, #0F172A 0%, #162338 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .demo-chip {
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }
        .demo-chip:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="login-card p-4 p-sm-5">
    <!-- Brand Logo -->
    <div class="text-center mb-4">
        <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="MartPro Logo" height="52" class="mb-2">
        <p class="text-muted small mb-0">Sign in to your retail operations terminal</p>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error_message) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= $form_redirect_param ?>">
        <?= csrf_token_field() ?>

        <div class="mb-3">
            <label class="form-label fw-semibold text-navy small">Username or Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                <input type="text" name="username" id="username" class="form-control border-start-0" placeholder="admin / manager / cashier" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold text-navy small">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                <input type="password" name="password" id="password" class="form-control border-start-0" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fs-6 shadow-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to MartPro
        </button>
    </form>

    <!-- Quick Demo Logins -->
    <div class="mt-4 pt-3 border-top">
        <p class="text-muted small text-center mb-2 fw-semibold">Quick Demo Credentials (Click to fill):</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <span class="badge bg-light text-dark border p-2 demo-chip" onclick="fillCreds('admin', 'admin123')">
                <i class="bi bi-shield-check text-success me-1"></i> Admin (admin / admin123)
            </span>
            <span class="badge bg-light text-dark border p-2 demo-chip" onclick="fillCreds('manager', 'admin123')">
                <i class="bi bi-person-badge text-primary me-1"></i> Manager (manager)
            </span>
            <span class="badge bg-light text-dark border p-2 demo-chip" onclick="fillCreds('cashier', 'admin123')">
                <i class="bi bi-upc text-warning me-1"></i> Cashier (cashier)
            </span>
        </div>
    </div>
</div>

<script>
function fillCreds(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
}
</script>
</body>
</html>
