<?php
/**
 * MartPro POS List Held Sales
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$store_id = current_store_id();
$db = get_db();

$stmt = $db->prepare("SELECT hs.id, hs.hold_reference, hs.notes, hs.held_at, hs.cart_data, hs.customer_id, u.full_name AS cashier_name, c.name AS customer_name
                      FROM held_sales hs
                      JOIN users u ON hs.cashier_id = u.id
                      LEFT JOIN customers c ON hs.customer_id = c.id
                      WHERE hs.store_id = :store
                      ORDER BY hs.id DESC");
$stmt->execute(['store' => $store_id]);
$held = $stmt->fetchAll();

foreach ($held as &$item) {
    $cart = json_decode($item['cart_data'], true);
    $item['item_count'] = is_array($cart) ? count($cart) : 0;
    $item['formatted_time'] = format_datetime($item['held_at']);
}

json_success('Held sales retrieved', $held);
