<?php
/**
 * MartPro POS Recall Held Sale
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';

require_login();
require_csrf_token();

$held_id = (int)($_POST['held_id'] ?? 0);
$db = get_db();

$stmt = $db->prepare("SELECT * FROM held_sales WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $held_id]);
$held = $stmt->fetch();

if (!$held) {
    json_error('Held transaction not found or already recalled.');
}

// Delete held sale record
$del = $db->prepare("DELETE FROM held_sales WHERE id = :id");
$del->execute(['id' => $held_id]);

json_success('Transaction recalled successfully.', [
    'customer_id' => $held['customer_id'],
    'cart'        => json_decode($held['cart_data'], true),
    'notes'       => $held['notes']
]);
