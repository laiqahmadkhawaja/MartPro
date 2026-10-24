<?php
/**
 * MartPro User Management AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('users.manage');
require_csrf_token();

$id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 4);
$store_id = (int)($_POST['default_store_id'] ?? 1);
$is_active = !empty($_POST['is_active']) ? 1 : 0;

if (empty($username) || empty($full_name) || empty($email)) {
    json_error('Username, Full Name, and Email are required.');
}

if (!$id && empty($password)) {
    json_error('Password is required for new user creation.');
}

$db = get_db();

// Uniqueness check
if ($id) {
    $check = $db->prepare("SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id LIMIT 1");
    $check->execute(['u' => $username, 'e' => $email, 'id' => $id]);
} else {
    $check = $db->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
    $check->execute(['u' => $username, 'e' => $email]);
}

if ($check->fetch()) {
    json_error('Username or email is already taken by another account.');
}

if ($id) {
    $sql = "UPDATE users SET username = :u, full_name = :fn, email = :e, role_id = :role, default_store_id = :store, is_active = :act";
    $params = [
        'u'     => $username,
        'fn'    => $full_name,
        'e'     => $email,
        'role'  => $role_id,
        'store' => $store_id,
        'act'   => $is_active,
        'id'    => $id
    ];
    if (!empty($password)) {
        $sql .= ", password_hash = :pwd";
        $params['pwd'] = password_hash($password, PASSWORD_BCRYPT);
    }
    $sql .= " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    audit_log('update_user', 'users', 'user', (string)$id, null, ['username' => $username]);
    json_success('User updated successfully.');
} else {
    $stmt = $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role_id, default_store_id, is_active, created_at)
                          VALUES (:u, :fn, :e, :pwd, :role, :store, :act, NOW())");
    $stmt->execute([
        'u'     => $username,
        'fn'    => $full_name,
        'e'     => $email,
        'pwd'   => password_hash($password, PASSWORD_BCRYPT),
        'role'  => $role_id,
        'store' => $store_id,
        'act'   => 1
    ]);
    $new_id = (int)$db->lastInsertId();

    // Assign store access
    $sa = $db->prepare("INSERT IGNORE INTO user_store_access (user_id, store_id) VALUES (:uid, :sid)");
    $sa->execute(['uid' => $new_id, 'sid' => $store_id]);

    audit_log('create_user', 'users', 'user', (string)$new_id, null, ['username' => $username]);
    json_success('New user account created successfully.', ['id' => $new_id]);
}
