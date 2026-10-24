<?php
/**
 * MartPro Stock Adjustment AJAX Handler
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
require_permission('inventory.adjust');
require_csrf_token();

$product_id = (int)($_POST['product_id'] ?? 0);
$type = $_POST['adjustment_type'] ?? 'decrease'; // 'increase' or 'decrease'
$quantity = max(0.0001, (float)($_POST['quantity'] ?? 0));
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$product_id || empty($reason) || $quantity <= 0) {
    json_error('Product, adjustment quantity, and reason are required.');
}

$db = get_db();
$store_id = current_store_id();
$user_id = current_user_id();

$p_stmt = $db->prepare("SELECT name, sku, purchase_cost FROM products WHERE id = :id AND is_active = 1 LIMIT 1");
$p_stmt->execute(['id' => $product_id]);
$product = $p_stmt->fetch();

if (!$product) {
    json_error('Product not found or inactive.');
}

$current_qty = get_available_stock($product_id, $store_id);

if ($type === 'decrease' && $current_qty < $quantity && !get_setting('allow_negative_stock', '0')) {
    json_error("Cannot decrease $quantity units. Current store stock is only $current_qty.");
}

$diff = ($type === 'increase') ? $quantity : -$quantity;
$new_qty = $current_qty + $diff;

$db->beginTransaction();
try {
    $adj_number = next_document_number(PREFIX_ADJUSTMENT);

    $stmt = $db->prepare("INSERT INTO stock_adjustments (adjustment_number, store_id, user_id, reason, notes, created_at)
                          VALUES (:num, :store, :uid, :reason, :notes, NOW())");
    $stmt->execute([
        'num'    => $adj_number,
        'store'  => $store_id,
        'uid'    => $user_id,
        'reason' => $reason,
        'notes'  => $notes
    ]);
    $adj_id = (int)$db->lastInsertId();

    $item_stmt = $db->prepare("INSERT INTO stock_adjustment_items 
        (adjustment_id, product_id, previous_qty, adjusted_qty, quantity_difference, unit_cost)
        VALUES (:aid, :pid, :prev, :adj, :diff, :cost)");
    $item_stmt->execute([
        'aid'  => $adj_id,
        'pid'  => $product_id,
        'prev' => $current_qty,
        'adj'  => $new_qty,
        'diff' => $diff,
        'cost' => $product['purchase_cost']
    ]);

    // Record Stock Movement
    $qin = ($type === 'increase') ? $quantity : 0.0;
    $qout = ($type === 'decrease') ? $quantity : 0.0;
    $mtype = ($type === 'decrease' && stripos($reason, 'damage') !== false) ? 'damage' : 'stock_adjustment';

    record_stock_movement(
        $product_id,
        $store_id,
        $mtype,
        $qin,
        $qout,
        (float)$product['purchase_cost'],
        $adj_number,
        null,
        "Stock Adjustment: $reason"
    );

    audit_log('stock_adjustment', 'inventory', 'stock_adjustment', (string)$adj_id, null, [
        'product'    => $product['name'],
        'diff'       => $diff,
        'reason'     => $reason,
        'adj_number' => $adj_number
    ]);

    $db->commit();
    json_success("Stock adjusted successfully! Adjustment Ref: $adj_number", ['adjustment_number' => $adj_number]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Adjustment failed: ' . $e->getMessage());
}
