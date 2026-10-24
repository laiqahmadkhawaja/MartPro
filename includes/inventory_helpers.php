<?php
/**
 * MartPro Inventory & Stock Control Helpers
 * Multi-location stock ledger, batch allocation (FIFO/FEFO), unit conversions
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

/**
 * Record an immutable stock movement and update the cached stock balance
 */
function record_stock_movement(
    int $product_id,
    int $store_id,
    string $movement_type,
    float $quantity_in,
    float $quantity_out,
    float $unit_cost,
    ?string $reference_doc = null,
    ?int $batch_id = null,
    ?string $reason = null,
    ?int $warehouse_id = null
): int {
    $db = get_db();
    $user_id = current_user_id();

    // 1. Insert Movement Record
    $stmt = $db->prepare("INSERT INTO stock_movements 
        (product_id, store_id, warehouse_id, batch_id, movement_type, quantity_in, quantity_out, unit_cost, reference_doc, user_id, reason, created_at)
        VALUES (:prod, :store, :wh, :batch, :mtype, :qin, :qout, :cost, :ref, :uid, :reason, NOW())");
    $stmt->execute([
        'prod'   => $product_id,
        'store'  => $store_id,
        'wh'     => $warehouse_id,
        'batch'  => $batch_id,
        'mtype'  => $movement_type,
        'qin'    => $quantity_in,
        'qout'   => $quantity_out,
        'cost'   => $unit_cost,
        'ref'    => $reference_doc,
        'uid'    => $user_id,
        'reason' => $reason
    ]);
    $movement_id = (int)$db->lastInsertId();

    // 2. Net quantity difference
    $net_qty = $quantity_in - $quantity_out;

    // 3. Update or Insert cached balance in stock_balances safely
    $find = $db->prepare("SELECT id FROM stock_balances WHERE product_id = :prod AND store_id = :store AND warehouse_id <=> :wh LIMIT 1 FOR UPDATE");
    $find->execute(['prod' => $product_id, 'store' => $store_id, 'wh' => $warehouse_id]);
    $existing_id = $find->fetchColumn();

    if ($existing_id) {
        $upd = $db->prepare("UPDATE stock_balances SET quantity = quantity + :net, updated_at = NOW() WHERE id = :id");
        $upd->execute(['net' => $net_qty, 'id' => $existing_id]);
    } else {
        $ins = $db->prepare("INSERT INTO stock_balances (product_id, store_id, warehouse_id, quantity, updated_at) VALUES (:prod, :store, :wh, :qty, NOW())");
        $ins->execute(['prod' => $product_id, 'store' => $store_id, 'wh' => $warehouse_id, 'qty' => $net_qty]);
    }

    return $movement_id;
}

/**
 * Get the current available stock for a product in a given store
 */
function get_available_stock(int $product_id, int $store_id): float {
    $db = get_db();
    $stmt = $db->prepare("SELECT quantity FROM stock_balances WHERE product_id = :prod AND store_id = :store AND warehouse_id IS NULL LIMIT 1");
    $stmt->execute(['prod' => $product_id, 'store' => $store_id]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (float)$val : 0.0;
}

/**
 * Allocate stock batches using FEFO (First Expired First Out) or FIFO
 * Deducts quantity from batches and returns allocations array with cost breakdown
 */
function allocate_stock_batches(int $product_id, int $store_id, float $requested_qty, string $strategy = 'FEFO'): array {
    $db = get_db();
    $order_clause = ($strategy === 'FEFO') 
        ? "ORDER BY (expiry_date IS NULL), expiry_date ASC, id ASC" 
        : "ORDER BY id ASC";

    $stmt = $db->prepare("SELECT id, batch_number, quantity_remaining, unit_cost, expiry_date 
                          FROM stock_batches 
                          WHERE product_id = :prod AND store_id = :store AND status = 'active' AND quantity_remaining > 0 
                          $order_clause 
                          FOR UPDATE");
    $stmt->execute(['prod' => $product_id, 'store' => $store_id]);
    $batches = $stmt->fetchAll();

    $allocations = [];
    $remaining_needed = $requested_qty;

    foreach ($batches as $batch) {
        if ($remaining_needed <= 0) break;
        $available = (float)$batch['quantity_remaining'];
        $take = min($available, $remaining_needed);

        // Deduct from batch
        $upd = $db->prepare("UPDATE stock_batches SET 
            quantity_remaining = quantity_remaining - :take1,
            status = IF(quantity_remaining - :take2 <= 0, 'exhausted', 'active')
            WHERE id = :id");
        $upd->execute(['take1' => $take, 'take2' => $take, 'id' => $batch['id']]);

        $allocations[] = [
            'batch_id'     => (int)$batch['id'],
            'batch_number' => $batch['batch_number'],
            'quantity'     => $take,
            'unit_cost'    => (float)$batch['unit_cost'],
            'expiry_date'  => $batch['expiry_date']
        ];

        $remaining_needed -= $take;
    }

    // If product does not track batches or batch stock was insufficient, fallback to standard product cost
    if ($remaining_needed > 0) {
        $p_stmt = $db->prepare("SELECT purchase_cost FROM products WHERE id = :id LIMIT 1");
        $p_stmt->execute(['id' => $product_id]);
        $std_cost = (float)$p_stmt->fetchColumn();

        $allocations[] = [
            'batch_id'     => null,
            'batch_number' => 'UNTRACKED',
            'quantity'     => $remaining_needed,
            'unit_cost'    => $std_cost,
            'expiry_date'  => null
        ];
    }

    return $allocations;
}
