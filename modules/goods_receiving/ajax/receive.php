<?php
/**
 * MartPro Goods Receiving Note (GRN) AJAX Endpoint
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
require_once __DIR__ . '/../../../includes/accounting_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('purchasing.manage');
require_csrf_token();

$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$po_id = !empty($_POST['po_id']) ? (int)$_POST['po_id'] : null;
$quantity_received = max(0.0001, (float)($_POST['quantity_received'] ?? 0));
$unit_cost = max(0.0, (float)($_POST['unit_cost'] ?? 0));
$batch_number = trim($_POST['batch_number'] ?? '');
$expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
$supplier_invoice_ref = trim($_POST['supplier_invoice_ref'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$store_id = current_store_id();
$user_id = current_user_id();

if (!$supplier_id || !$product_id || $quantity_received <= 0) {
    json_error('Supplier, product, and received quantity are required.');
}

$db = get_db();

// Verify product
$p_stmt = $db->prepare("SELECT name, sku, track_batches, track_expiry FROM products WHERE id = :id LIMIT 1");
$p_stmt->execute(['id' => $product_id]);
$product = $p_stmt->fetch();

if (!$product) {
    json_error('Product not found.');
}

if (!empty($product['track_batches']) && empty($batch_number)) {
    $batch_number = 'BATCH-' . date('ymd') . '-' . substr(uniqid(), -3);
}

$line_total = round($quantity_received * $unit_cost, 2);

$db->beginTransaction();
try {
    $grn_number = next_document_number(PREFIX_GRN);

    // 1. Insert GRN Header
    $stmt = $db->prepare("INSERT INTO goods_receipts 
        (grn_number, po_id, store_id, supplier_id, received_date, supplier_invoice_ref, total_amount, received_by, notes, created_at)
        VALUES 
        (:num, :poid, :store, :sup, CURDATE(), :inv_ref, :tot, :uid, :notes, NOW())");
    $stmt->execute([
        'num'     => $grn_number,
        'poid'    => $po_id,
        'store'   => $store_id,
        'sup'     => $supplier_id,
        'inv_ref' => $supplier_invoice_ref ?: "INV-VND-" . date('ymd'),
        'tot'     => $line_total,
        'uid'     => $user_id,
        'notes'   => $notes
    ]);
    $grn_id = (int)$db->lastInsertId();

    // 2. Insert GRN Items
    $item_stmt = $db->prepare("INSERT INTO goods_receipt_items 
        (grn_id, product_id, batch_number, expiry_date, quantity_received, unit_cost, line_total)
        VALUES (:gid, :pid, :bnum, :exp, :qrec, :cost, :ltot)");
    $item_stmt->execute([
        'gid'  => $grn_id,
        'pid'  => $product_id,
        'bnum' => $batch_number ?: null,
        'exp'  => $expiry_date ?: null,
        'qrec' => $quantity_received,
        'cost' => $unit_cost,
        'ltot' => $line_total
    ]);

    // 3. Create or Update Stock Batch if tracked
    $batch_id = null;
    if (!empty($batch_number)) {
        $b_insert = $db->prepare("INSERT INTO stock_batches 
            (product_id, store_id, batch_number, supplier_id, quantity_received, quantity_remaining, unit_cost, expiry_date, status, created_at)
            VALUES 
            (:pid, :store, :bnum, :sup, :qrec1, :qrec2, :cost, :exp, 'active', NOW())
            ON DUPLICATE KEY UPDATE quantity_received = quantity_received + VALUES(quantity_received), quantity_remaining = quantity_remaining + VALUES(quantity_remaining)");
        $b_insert->execute([
            'pid'   => $product_id,
            'store' => $store_id,
            'bnum'  => $batch_number,
            'sup'   => $supplier_id,
            'qrec1' => $quantity_received,
            'qrec2' => $quantity_received,
            'cost'  => $unit_cost,
            'exp'   => $expiry_date ?: null
        ]);
        $batch_id = (int)$db->lastInsertId();
    }

    // 4. Update Product latest purchase cost
    $upd_cost = $db->prepare("UPDATE products SET purchase_cost = :cost WHERE id = :id");
    $upd_cost->execute(['cost' => $unit_cost, 'id' => $product_id]);

    // 5. Record Stock Movement Ledger (adds inventory)
    record_stock_movement(
        $product_id,
        $store_id,
        'purchase_receipt',
        $quantity_received,
        0.0,
        $unit_cost,
        $grn_number,
        $batch_id,
        "Received on GRN #$grn_number from Supplier ID $supplier_id"
    );

    // 6. Increase Supplier Payable Balance & Create Purchase Invoice Record
    $upd_sup = $db->prepare("UPDATE suppliers SET current_balance = current_balance + :amt WHERE id = :id");
    $upd_sup->execute(['amt' => $line_total, 'id' => $supplier_id]);

    $inv_num = 'PINV-' . date('y') . '-' . substr(uniqid(), -5);
    $p_inv = $db->prepare("INSERT INTO purchase_invoices 
        (invoice_number, supplier_id, store_id, grn_id, invoice_date, due_date, total_amount, paid_amount, balance_due, payment_status, created_by, created_at)
        VALUES 
        (:inv, :sup, :store, :grn, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), :tot1, 0, :tot2, 'unpaid', :uid, NOW())");
    $p_inv->execute([
        'inv'   => $inv_num,
        'sup'   => $supplier_id,
        'store' => $store_id,
        'grn'   => $grn_id,
        'tot1'  => $line_total,
        'tot2'  => $line_total,
        'uid'   => $user_id
    ]);

    // 7. Post Balanced Inventory Receipt Accounting:
    // Debit 1200 (Merchandise Inventory), Credit 2010 (Accounts Payable)
    $grn_lines = [
        [
            'account_id' => get_account_id('1200'),
            'debit'      => $line_total,
            'credit'     => 0,
            'memo'       => "Stock received on GRN #$grn_number"
        ],
        [
            'account_id' => get_account_id('2010'),
            'debit'      => 0,
            'credit'     => $line_total,
            'memo'       => "AP liability to Supplier #$supplier_id on GRN #$grn_number"
        ]
    ];
    post_journal_entry("Goods Receipt $grn_number", $grn_number, $grn_lines);

    // 8. Mark PO fulfilled if linked
    if ($po_id) {
        $upd_po = $db->prepare("UPDATE purchase_orders SET status = 'received' WHERE id = :poid");
        $upd_po->execute(['poid' => $po_id]);
    }

    audit_log('receive_grn', 'purchasing', 'goods_receipt', (string)$grn_id, null, ['grn_number' => $grn_number, 'amount' => $line_total]);

    $db->commit();
    json_success("Goods received and stocked! GRN Ref: $grn_number", ['grn_number' => $grn_number]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Receiving failed: ' . $e->getMessage());
}
