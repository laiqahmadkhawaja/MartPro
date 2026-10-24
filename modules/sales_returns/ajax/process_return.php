<?php
/**
 * MartPro Sales Returns & Refund Processing Engine
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
require_permission('pos.returns');
require_csrf_token();

$invoice_number = trim($_POST['invoice_number'] ?? '');
$items_raw = $_POST['return_items'] ?? '';
$return_items = is_array($items_raw) ? $items_raw : json_decode($items_raw, true);
$refund_method = $_POST['refund_method'] ?? 'cash';
$reason = trim($_POST['reason'] ?? 'Customer Return / Exchange');

$store_id = current_store_id();
$cashier_id = current_user_id();

if (empty($invoice_number) || empty($return_items) || !is_array($return_items)) {
    json_error('Invoice number and return items are required.');
}

$db = get_db();

// 1. Fetch original sale
$s_stmt = $db->prepare("SELECT * FROM sales WHERE invoice_number = :inv LIMIT 1");
$s_stmt->execute(['inv' => $invoice_number]);
$sale = $s_stmt->fetch();

if (!$sale) {
    json_error("Original sale invoice $invoice_number not found.");
}

$db->beginTransaction();
try {
    $total_refund = 0.0;
    $processed_items = [];

    foreach ($return_items as $ri) {
        $sale_item_id = (int)($ri['sale_item_id'] ?? 0);
        $ret_qty = max(0.0, (float)($ri['quantity'] ?? 0));
        $condition = $ri['condition'] ?? 'resalable'; // 'resalable', 'damaged', 'expired'

        if ($ret_qty <= 0) continue;

        // Fetch original sale item
        $si_stmt = $db->prepare("SELECT si.*, p.purchase_cost 
                                 FROM sale_items si
                                 JOIN products p ON si.product_id = p.id
                                 WHERE si.id = :si_id AND si.sale_id = :sid LIMIT 1");
        $si_stmt->execute(['si_id' => $sale_item_id, 'sid' => $sale['id']]);
        $orig_item = $si_stmt->fetch();

        if (!$orig_item) {
            throw new Exception("Sale item ID $sale_item_id does not belong to this invoice.");
        }

        // Check prior returns for this item
        $prior_stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM sale_return_items WHERE sale_item_id = :si_id");
        $prior_stmt->execute(['si_id' => $sale_item_id]);
        $prior_returned = (float)$prior_stmt->fetchColumn();

        $max_returnable = (float)$orig_item['quantity'] - $prior_returned;
        if ($ret_qty > $max_returnable) {
            throw new Exception("Cannot return $ret_qty units of '{$orig_item['product_name']}'. Only $max_returnable remaining returnable.");
        }

        $line_refund = round($ret_qty * (float)$orig_item['unit_price'], 2);
        $total_refund += $line_refund;

        $processed_items[] = [
            'sale_item_id' => $sale_item_id,
            'product_id'   => (int)$orig_item['product_id'],
            'batch_id'     => $orig_item['batch_id'],
            'qty'          => $ret_qty,
            'unit_price'   => (float)$orig_item['unit_price'],
            'cost_price'   => (float)$orig_item['cost_price'],
            'line_refund'  => $line_refund,
            'condition'    => $condition
        ];
    }

    if (empty($processed_items)) {
        throw new Exception("No valid quantities selected for return.");
    }

    // 2. Generate atomic Return number
    $return_number = next_document_number(PREFIX_SALES_RET);

    // 3. Insert sale_returns
    $ret_stmt = $db->prepare("INSERT INTO sale_returns 
        (return_number, original_sale_id, store_id, register_id, cashier_id, customer_id, refund_method, total_refund, reason, created_at)
        VALUES 
        (:rnum, :sid, :store, :reg, :cashier, :cust, :meth, :tot, :reason, NOW())");
    $ret_stmt->execute([
        'rnum'    => $return_number,
        'sid'     => $sale['id'],
        'store'   => $store_id,
        'reg'     => $sale['register_id'],
        'cashier' => $cashier_id,
        'cust'    => $sale['customer_id'],
        'meth'    => $refund_method,
        'tot'     => $total_refund,
        'reason'  => $reason
    ]);
    $return_id = (int)$db->lastInsertId();

    // 4. Insert Return Items & Restock if resalable
    $item_ins = $db->prepare("INSERT INTO sale_return_items 
        (sale_return_id, sale_item_id, product_id, batch_id, quantity, refund_unit_price, line_refund_total, item_condition)
        VALUES 
        (:rid, :si_id, :pid, :bid, :qty, :uprice, :ltot, :cond)");

    $total_restocked_cogs = 0.0;
    foreach ($processed_items as $pi) {
        $item_ins->execute([
            'rid'    => $return_id,
            'si_id'  => $pi['sale_item_id'],
            'pid'    => $pi['product_id'],
            'bid'    => $pi['batch_id'],
            'qty'    => $pi['qty'],
            'uprice' => $pi['unit_price'],
            'ltot'   => $pi['line_refund'],
            'cond'   => $pi['condition']
        ]);

        // Restock into inventory only if Resalable!
        if ($pi['condition'] === 'resalable') {
            $total_restocked_cogs += round($pi['qty'] * $pi['cost_price'], 2);
            record_stock_movement(
                $pi['product_id'],
                $store_id,
                'sales_return',
                $pi['qty'],
                0.0,
                $pi['cost_price'],
                $return_number,
                $pi['batch_id'],
                "Sales Return #$return_number against Invoice #$invoice_number"
            );
        } else {
            // Damaged goods movement
            record_stock_movement(
                $pi['product_id'],
                $store_id,
                'damage',
                0.0,
                0.0,
                $pi['cost_price'],
                $return_number,
                $pi['batch_id'],
                "Damaged Return #$return_number (Not added to sellable stock)"
            );
        }
    }

    // 5. Customer Credit adjustment if customer account refund
    if ($refund_method === 'customer_credit' && $sale['customer_id']) {
        $upd_c = $db->prepare("UPDATE customers SET current_balance = current_balance - :amt WHERE id = :id");
        $upd_c->execute(['amt' => $total_refund, 'id' => $sale['customer_id']]);

        $led = $db->prepare("INSERT INTO customer_ledger (customer_id, transaction_type, reference_id, debit, credit, running_balance, notes, created_by, created_at)
                             VALUES (:cid, 'return_credit', :ref, 0, :cr, 0, :notes, :uid, NOW())");
        $led->execute([
            'cid'   => $sale['customer_id'],
            'ref'   => $return_number,
            'cr'    => $total_refund,
            'notes' => "Sales Return Credit #$return_number",
            'uid'   => $cashier_id
        ]);
    }

    // 6. Reverse Sales Revenue in Accounting
    // Debit Sales Returns (4010), Credit Cash/Bank/AR
    $cr_account = ($refund_method === 'cash') ? get_account_id('1010') : 
                  (($refund_method === 'customer_credit') ? get_account_id('1100') : get_account_id('1030'));
    $j_lines = [
        [
            'account_id' => get_account_id('4010'), // Sales Return / Revenue
            'debit'      => $total_refund,
            'credit'     => 0,
            'memo'       => "Sales Return #$return_number"
        ],
        [
            'account_id' => $cr_account,
            'debit'      => 0,
            'credit'     => $total_refund,
            'memo'       => "Refund via $refund_method"
        ]
    ];
    post_journal_entry("Sales Return $return_number", $return_number, $j_lines);

    // 7. Reverse COGS & Restock Inventory in GL if resalable goods restocked
    if ($total_restocked_cogs > 0) {
        $cogs_rev_lines = [
            [
                'account_id' => get_account_id('1200'), // Merchandise Inventory
                'debit'      => $total_restocked_cogs,
                'credit'     => 0,
                'memo'       => "Inventory Restocked for Return #$return_number"
            ],
            [
                'account_id' => get_account_id('5010'), // COGS
                'debit'      => 0,
                'credit'     => $total_restocked_cogs,
                'memo'       => "COGS Reversal for Return #$return_number"
            ]
        ];
        post_journal_entry("Restock COGS Return $return_number", $return_number, $cogs_rev_lines);
    }

    audit_log('sales_return', 'pos', 'sale_return', (string)$return_id, null, [
        'return_number' => $return_number,
        'invoice'       => $invoice_number,
        'refund_amount' => $total_refund
    ]);

    $db->commit();
    json_success("Return #$return_number processed! Refund Amount: " . format_money($total_refund), ['return_number' => $return_number]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Return processing failed: ' . $e->getMessage());
}
