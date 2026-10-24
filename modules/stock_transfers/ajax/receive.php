<?php
/**
 * MartPro Stock Transfer Receive AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('inventory.transfer');
require_csrf_token();

$transfer_id = (int)($_POST['transfer_id'] ?? 0);
$user_id = current_user_id();
$current_store = current_store_id();

$db = get_db();
$stmt = $db->prepare("SELECT * FROM stock_transfers WHERE id = :id AND status = 'dispatched' LIMIT 1");
$stmt->execute(['id' => $transfer_id]);
$trf = $stmt->fetch();

if (!$trf) {
    json_error('Transfer not found or already received/cancelled.');
}

if ((int)$trf['dest_store_id'] !== $current_store) {
    json_error('You must switch to the destination store to receive this transfer.');
}

// Get transfer items
$items_stmt = $db->prepare("SELECT ti.*, p.purchase_cost 
                            FROM stock_transfer_items ti 
                            JOIN products p ON ti.product_id = p.id 
                            WHERE ti.transfer_id = :id");
$items_stmt->execute(['id' => $transfer_id]);
$items = $items_stmt->fetchAll();

$db->beginTransaction();
try {
    foreach ($items as $item) {
        $qty_rec = (float)$item['quantity_dispatched'];

        // Update item received qty
        $upd_item = $db->prepare("UPDATE stock_transfer_items SET quantity_received = :qrec WHERE id = :id");
        $upd_item->execute(['qrec' => $qty_rec, 'id' => $item['id']]);

        // Add Stock to Destination Store
        record_stock_movement(
            $item['product_id'],
            $current_store,
            'transfer_in',
            $qty_rec,
            0.0,
            (float)$item['purchase_cost'],
            $trf['transfer_number'],
            null,
            "Received transfer from Store ID {$trf['source_store_id']}"
        );
    }

    // Mark transfer as received
    $upd_trf = $db->prepare("UPDATE stock_transfers SET status = 'received', received_by = :uid, received_at = NOW() WHERE id = :id");
    $upd_trf->execute(['uid' => $user_id, 'id' => $transfer_id]);

    audit_log('transfer_receive', 'inventory', 'stock_transfer', (string)$transfer_id, null, ['trf_number' => $trf['transfer_number']]);

    $db->commit();
    json_success("Transfer {$trf['transfer_number']} received and stock added to inventory!");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Receipt failed: ' . $e->getMessage());
}
