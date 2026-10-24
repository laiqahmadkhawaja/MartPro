<?php
/**
 * MartPro Promotions AJAX Handler
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('pos.discount');
require_csrf_token();

$title = trim($_POST['title'] ?? '');
$type = $_POST['type'] ?? 'percentage';
$discount_value = max(0.01, (float)($_POST['discount_value'] ?? 0));
$start_date = $_POST['start_date'] ?? date('Y-m-d');
$end_date = $_POST['end_date'] ?? date('Y-12-31');
$min_order_amount = max(0.0, (float)($_POST['min_order_amount'] ?? 0));

if (empty($title) || $discount_value <= 0) {
    json_error('Promotion title and discount value are required.');
}

$db = get_db();
$stmt = $db->prepare("INSERT INTO promotions (title, type, discount_value, start_date, end_date, min_order_amount, is_active, created_at)
                      VALUES (:title, :type, :val, :sd, :ed, :min, 1, NOW())");
$stmt->execute([
    'title' => $title,
    'type'  => $type,
    'val'   => $discount_value,
    'sd'    => $start_date,
    'ed'    => $end_date,
    'min'   => $min_order_amount
]);

$new_id = (int)$db->lastInsertId();
audit_log('create_promotion', 'pos', 'promotion', (string)$new_id, null, ['title' => $title, 'discount' => $discount_value]);

json_success('Promotion rule created successfully.', ['id' => $new_id]);
