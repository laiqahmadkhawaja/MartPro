<?php
/**
 * MartPro Purchase Order AJAX Handler
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/numbering.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('purchasing.manage');
require_csrf_token();

$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$quantity = max(1.0, (float)($_POST['quantity'] ?? 1));
$unit_cost = max(0.0, (float)($_POST['unit_cost'] ?? 0));
$expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
$notes = trim($_POST['notes'] ?? '');

$store_id = current_store_id();
$user_id = current_user_id();

if (!$supplier_id || !$product_id || $quantity <= 0) {
    json_error('Supplier, product, and valid quantity are required.');
}

$db = get_db();

// Verify supplier and product
$p_stmt = $db->prepare("SELECT name, purchase_cost FROM products WHERE id = :id LIMIT 1");
$p_stmt->execute(['id' => $product_id]);
$prod = $p_stmt->fetch();

if (!$prod) {
    json_error('Product not found.');
}

if ($unit_cost <= 0) {
    $unit_cost = (float)$prod['purchase_cost'];
}

$line_total = round($quantity * $unit_cost, 2);

$db->beginTransaction();
try {
    $po_num = next_document_number(PREFIX_PO);

    $stmt = $db->prepare("INSERT INTO purchase_orders 
        (po_number, store_id, supplier_id, po_date, expected_delivery_date, subtotal, grand_total, status, created_by, notes, created_at)
        VALUES 
        (:num, :store, :sup, CURDATE(), :exp, :sub, :tot, 'sent', :uid, :notes, NOW())");
    $stmt->execute([
        'num'   => $po_num,
        'store' => $store_id,
        'sup'   => $supplier_id,
        'exp'   => $expected_date,
        'sub'   => $line_total,
        'tot'   => $line_total,
        'uid'   => $user_id,
        'notes' => $notes
    ]);
    $po_id = (int)$db->lastInsertId();

    $item_stmt = $db->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_cost, line_total)
                               VALUES (:poid, :pid, :qty, :cost, :ltot)");
    $item_stmt->execute([
        'poid' => $po_id,
        'pid'  => $product_id,
        'qty'  => $quantity,
        'cost' => $unit_cost,
        'ltot' => $line_total
    ]);

    audit_log('create_po', 'purchasing', 'purchase_order', (string)$po_id, null, ['po_number' => $po_num, 'total' => $line_total]);

    $db->commit();
    json_success("Purchase Order $po_num created and sent to supplier!", ['po_number' => $po_num]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Failed to create purchase order: ' . $e->getMessage());
}
