<?php
/**
 * MartPro Stock Transfer Dispatch AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/numbering.php';
require_once __DIR__ . '/../../../includes/inventory_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('inventory.transfer');
require_csrf_token();

$dest_store_id = (int)($_POST['dest_store_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$quantity = max(0.0001, (float)($_POST['quantity'] ?? 0));
$notes = trim($_POST['notes'] ?? '');

$source_store_id = current_store_id();
$user_id = current_user_id();

if (!$dest_store_id || !$product_id || $quantity <= 0) {
    json_error('Destination store, product, and valid quantity are required.');
}

if ($dest_store_id === $source_store_id) {
    json_error('Source and destination store cannot be the same.');
}

$db = get_db();

$p_stmt = $db->prepare("SELECT name, sku, purchase_cost FROM products WHERE id = :id AND is_active = 1 LIMIT 1");
$p_stmt->execute(['id' => $product_id]);
$product = $p_stmt->fetch();

if (!$product) {
    json_error('Product not found.');
}

// Check source stock
$source_stock = get_available_stock($product_id, $source_store_id);
if ($source_stock < $quantity && !get_setting('allow_negative_stock', '0')) {
    json_error("Insufficient stock at source store. Available: $source_stock, Requested: $quantity");
}

$db->beginTransaction();
try {
    $trf_number = next_document_number(PREFIX_TRANSFER);

    $stmt = $db->prepare("INSERT INTO stock_transfers 
        (transfer_number, source_store_id, dest_store_id, status, dispatched_by, dispatched_at, notes, created_at)
        VALUES (:num, :src, :dest, 'dispatched', :uid, NOW(), :notes, NOW())");
    $stmt->execute([
        'num'   => $trf_number,
        'src'   => $source_store_id,
        'dest'  => $dest_store_id,
        'uid'   => $user_id,
        'notes' => $notes
    ]);
    $trf_id = (int)$db->lastInsertId();

    $item_stmt = $db->prepare("INSERT INTO stock_transfer_items (transfer_id, product_id, quantity_dispatched) VALUES (:tid, :pid, :qty)");
    $item_stmt->execute([
        'tid' => $trf_id,
        'pid' => $product_id,
        'qty' => $quantity
    ]);

    // Deduct stock from Source Store
    record_stock_movement(
        $product_id,
        $source_store_id,
        'transfer_out',
        0.0,
        $quantity,
        (float)$product['purchase_cost'],
        $trf_number,
        null,
        "Dispatched transfer to store ID $dest_store_id"
    );

    audit_log('transfer_dispatch', 'inventory', 'stock_transfer', (string)$trf_id, null, [
        'trf_number' => $trf_number,
        'product'    => $product['name'],
        'qty'        => $quantity,
        'to_store'   => $dest_store_id
    ]);

    $db->commit();
    json_success("Transfer dispatched successfully! Transfer Ref: $trf_number", ['transfer_number' => $trf_number]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Dispatch failed: ' . $e->getMessage());
}
