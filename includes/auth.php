<?php
/**
 * MartPro Authentication & Permission Verification System
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/response.php';

function is_logged_in(): bool {
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        return false;
    }
    // Check session expiration
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        logout();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function current_user(): ?array {
    return is_logged_in() ? $_SESSION['user'] : null;
}

function current_user_id(): ?int {
    return is_logged_in() ? (int)$_SESSION['user']['id'] : null;
}

function current_store_id(): int {
    if (!empty($_SESSION['active_store_id'])) {
        return (int)$_SESSION['active_store_id'];
    }
    return !empty($_SESSION['user']['default_store_id']) ? (int)$_SESSION['user']['default_store_id'] : 1;
}

function set_active_store(int $store_id): void {
    $_SESSION['active_store_id'] = $store_id;
}

function has_permission(string $permission_slug): bool {
    if (!is_logged_in()) {
        return false;
    }
    // Super admin has all permissions
    if (($_SESSION['user']['role_slug'] ?? '') === ROLE_SUPER_ADMIN) {
        return true;
    }
    $permissions = $_SESSION['user']['permissions'] ?? [];
    return in_array($permission_slug, $permissions, true);
}

function require_login(): void {
    if (!is_logged_in()) {
        if (is_ajax_request()) {
            json_error('Session expired. Please log in again.', [], 401);
        }
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $relative_uri = $request_uri;
        if (defined('BASE_PATH') && !empty(BASE_PATH) && stripos($relative_uri, BASE_PATH) === 0) {
            $relative_uri = substr($relative_uri, strlen(BASE_PATH));
        }
        $relative_uri = ltrim($relative_uri, '/');

        $target_clean = explode('?', $relative_uri)[0];
        if (empty($target_clean) || $target_clean === 'index.php' || $target_clean === 'login.php' || $target_clean === 'logout.php') {
            header('Location: ' . BASE_URL . '/login.php');
        } else {
            header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($relative_uri));
        }
        exit;
    }
}

function require_permission(string $permission_slug): void {
    require_login();
    if (!has_permission($permission_slug)) {
        if (is_ajax_request()) {
            json_error('Access Denied: You do not have permission (' . $permission_slug . ') for this operation.', [], 403);
        }
        include __DIR__ . '/access_denied.php';
        exit;
    }
}

function login(string $username, string $password): array {
    $db = get_db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Check account lockout
    $stmt = $db->prepare("SELECT u.*, r.name AS role_name, r.slug AS role_slug 
                          FROM users u 
                          JOIN roles r ON u.role_id = r.id 
                          WHERE u.username = :username OR u.email = :email LIMIT 1");
    $stmt->execute(['username' => $username, 'email' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        // Record failed attempt
        $log_stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (:ip, :user, 0)");
        $log_stmt->execute(['ip' => $ip, 'user' => $username]);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your account is deactivated. Contact an administrator.'];
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return ['success' => false, 'message' => 'Account is temporarily locked due to repeated failed logins. Please try again later.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $failed_count = (int)$user['failed_login_count'] + 1;
        $lock_time = null;
        if ($failed_count >= 5) {
            $lock_time = date('Y-m-d H:i:s', time() + 900); // 15 mins lock
        }
        $update_stmt = $db->prepare("UPDATE users SET failed_login_count = :cnt, locked_until = :lock WHERE id = :id");
        $update_stmt->execute(['cnt' => $failed_count, 'lock' => $lock_time, 'id' => $user['id']]);

        $log_stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (:ip, :user, 0)");
        $log_stmt->execute(['ip' => $ip, 'user' => $username]);

        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Login successful - regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Fetch user permissions
    $perm_stmt = $db->prepare("SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = :role_id");
    $perm_stmt->execute(['role_id' => $user['role_id']]);
    $permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

    $_SESSION['user'] = [
        'id'               => (int)$user['id'],
        'username'         => $user['username'],
        'full_name'        => $user['full_name'],
        'email'            => $user['email'],
        'role_id'          => (int)$user['role_id'],
        'role_name'        => $user['role_name'],
        'role_slug'        => $user['role_slug'],
        'default_store_id' => (int)($user['default_store_id'] ?? 1),
        'permissions'      => $permissions
    ];
    $_SESSION['active_store_id'] = (int)($user['default_store_id'] ?? 1);
    $_SESSION['last_activity'] = time();

    // Reset login counters and update last login
    $reset_stmt = $db->prepare("UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id");
    $reset_stmt->execute(['id' => $user['id']]);

    $log_stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (:ip, :user, 1)");
    $log_stmt->execute(['ip' => $ip, 'user' => $username]);

    // Record audit log
    if (function_exists('audit_log')) {
        audit_log('login', 'auth', 'user', (string)$user['id'], null, ['ip' => $ip]);
    }

    return ['success' => true, 'message' => 'Login successful.'];
}

function logout(): void {
    if (isset($_SESSION['user']['id']) && function_exists('audit_log')) {
        audit_log('logout', 'auth', 'user', (string)$_SESSION['user']['id']);
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
