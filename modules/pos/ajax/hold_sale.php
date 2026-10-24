<?php
/**
 * MartPro POS Hold Sale AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';

require_login();
require_csrf_token();

$cart_data = $_POST['cart_data'] ?? '';
$customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$notes = trim($_POST['notes'] ?? '');
$register_id = (int)($_POST['register_id'] ?? 1);
$store_id = current_store_id();
$cashier_id = current_user_id();

if (empty($cart_data)) {
    json_error('Cart is empty. Nothing to suspend.');
}

$db = get_db();
$hold_ref = 'HOLD-' . date('His') . '-' . substr(uniqid(), -3);

$stmt = $db->prepare("INSERT INTO held_sales (hold_reference, store_id, register_id, cashier_id, customer_id, cart_data, notes, held_at)
                      VALUES (:ref, :store, :reg, :cashier, :cust, :cart, :notes, NOW())");
$stmt->execute([
    'ref'     => $hold_ref,
    'store'   => $store_id,
    'reg'     => $register_id,
    'cashier' => $cashier_id,
    'cust'    => $customer_id,
    'cart'    => $cart_data,
    'notes'   => !empty($notes) ? $notes : 'Held at ' . date('h:i A')
]);

json_success("Transaction held successfully with reference: $hold_ref", ['hold_reference' => $hold_ref]);
