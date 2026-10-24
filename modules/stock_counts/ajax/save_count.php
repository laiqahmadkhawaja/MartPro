<?php
/**
 * MartPro Physical Stock Count & Reconciliation AJAX Engine
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/numbering.php';
require_once __DIR__ . '/../../../includes/inventory_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('inventory.count');
require_csrf_token();

$counts_raw = $_POST['items'] ?? '';
$counts = is_array($counts_raw) ? $counts_raw : json_decode($counts_raw, true);
$notes = trim($_POST['notes'] ?? '');

if (empty($counts) || !is_array($counts)) {
    json_error('No count items provided.');
}

$db = get_db();
$store_id = current_store_id();
$user_id = current_user_id();

$db->beginTransaction();
try {
    $count_num = next_document_number(PREFIX_COUNT);

    $stmt = $db->prepare("INSERT INTO stock_counts (count_number, store_id, count_date, status, created_by, notes, created_at)
                          VALUES (:num, :store, CURDATE(), 'posted', :uid, :notes, NOW())");
    $stmt->execute([
        'num'   => $count_num,
        'store' => $store_id,
        'uid'   => $user_id,
        'notes' => $notes ?: 'Physical Stock Audit Reconciliation'
    ]);
    $count_id = (int)$db->lastInsertId();

    $item_stmt = $db->prepare("INSERT INTO stock_count_items (stock_count_id, product_id, expected_qty, counted_qty, variance_qty, unit_cost)
                               VALUES (:cid, :pid, :exp, :cnt, :var, :cost)");

    foreach ($counts as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $counted_qty = max(0.0, (float)($item['counted_qty'] ?? 0));

        $p_stmt = $db->prepare("SELECT purchase_cost FROM products WHERE id = :id LIMIT 1");
        $p_stmt->execute(['id' => $product_id]);
        $cost = (float)$p_stmt->fetchColumn();

        $expected_qty = get_available_stock($product_id, $store_id);
        $variance = $counted_qty - $expected_qty;

        $item_stmt->execute([
            'cid'  => $count_id,
            'pid'  => $product_id,
            'exp'  => $expected_qty,
            'cnt'  => $counted_qty,
            'var'  => $variance,
            'cost' => $cost
        ]);

        // If discrepancy exists, record stock adjustment movement to reconcile system to reality
        if (abs($variance) > 0.0001) {
            $qin = ($variance > 0) ? $variance : 0.0;
            $qout = ($variance < 0) ? abs($variance) : 0.0;

            record_stock_movement(
                $product_id,
                $store_id,
                'physical_count',
                $qin,
                $qout,
                $cost,
                $count_num,
                null,
                "Physical Stocktake Reconciliation #$count_num (Expected: $expected_qty, Counted: $counted_qty)"
            );
        }
    }

    audit_log('stock_count', 'inventory', 'stock_count', (string)$count_id, null, ['count_number' => $count_num]);

    $db->commit();
    json_success("Physical count #$count_num posted and stock balances reconciled!", ['count_number' => $count_num]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Failed to post count: ' . $e->getMessage());
}
