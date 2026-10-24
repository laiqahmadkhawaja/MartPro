<?php
/**
 * MartPro Product Status Toggle
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('products.manage');
require_csrf_token();

$id = (int)($_POST['product_id'] ?? 0);
$db = get_db();

$stmt = $db->prepare("SELECT is_active, name FROM products WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$prod = $stmt->fetch();

if (!$prod) {
    json_error('Product not found.');
}

$new_status = $prod['is_active'] ? 0 : 1;
$upd = $db->prepare("UPDATE products SET is_active = :status WHERE id = :id");
$upd->execute(['status' => $new_status, 'id' => $id]);

audit_log('toggle_status', 'products', 'product', (string)$id, ['is_active' => $prod['is_active']], ['is_active' => $new_status]);

json_success('Product status updated.', ['is_active' => $new_status]);
