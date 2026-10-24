<?php
/**
 * MartPro Authoritative POS Sale Checkout Engine
 * Atomic Database Transaction, Stock Allocation, Ledger Entries, Accounting Integration
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
require_permission('pos.access');
require_csrf_token();

$db = get_db();
$store_id = current_store_id();
$cashier_id = current_user_id();

// Parse input payload
$cart_raw = $_POST['cart'] ?? '';
$cart = is_array($cart_raw) ? $cart_raw : json_decode($cart_raw, true);

if (empty($cart) || !is_array($cart)) {
    json_error('Cart is empty. Please add items before checking out.');
}

$customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$register_id = !empty($_POST['register_id']) ? (int)$_POST['register_id'] : 1;
$bill_discount = max(0.0, (float)($_POST['bill_discount'] ?? 0));
$notes = trim($_POST['notes'] ?? '');

// Payments received
$paid_cash = max(0.0, (float)($_POST['paid_cash'] ?? 0));
$paid_card = max(0.0, (float)($_POST['paid_card'] ?? 0));
$paid_wallet = max(0.0, (float)($_POST['paid_wallet'] ?? 0));
$paid_credit = max(0.0, (float)($_POST['paid_credit'] ?? 0));
$paid_loyalty = max(0.0, (float)($_POST['paid_loyalty'] ?? 0));
$card_ref = trim($_POST['card_reference'] ?? '');

// Check Customer Credit Limit if customer credit is used
if ($paid_credit > 0) {
    if (!$customer_id) {
        json_error('Customer selection is required for store credit / Khata purchases.');
    }
    $c_stmt = $db->prepare("SELECT credit_limit, current_balance, name FROM customers WHERE id = :id LIMIT 1");
    $c_stmt->execute(['id' => $customer_id]);
    $cust = $c_stmt->fetch();
    if (!$cust) {
        json_error('Selected customer account not found.');
    }
    $new_balance = (float)$cust['current_balance'] + $paid_credit;
    if ((float)$cust['credit_limit'] > 0 && $new_balance > (float)$cust['credit_limit']) {
        json_error(sprintf(
            "Customer credit limit exceeded! Available credit: %s, Requested: %s",
            format_money((float)$cust['credit_limit'] - (float)$cust['current_balance']),
            format_money($paid_credit)
        ));
    }
}

// Find or verify active register shift
$shift_stmt = $db->prepare("SELECT id FROM register_shifts WHERE register_id = :reg AND store_id = :store AND status = 'open' LIMIT 1");
$shift_stmt->execute(['reg' => $register_id, 'store' => $store_id]);
$shift_id = $shift_stmt->fetchColumn();

// If no open shift, automatically initialize one for the cashier so sales are never blocked
if (!$shift_id) {
    $shift_num = 'SHF-' . date('y') . '-' . substr(uniqid(), -5);
    $new_shift = $db->prepare("INSERT INTO register_shifts (shift_number, register_id, store_id, cashier_id, opened_at, opening_cash_float, status)
                               VALUES (:snum, :reg, :store, :cashier, NOW(), 0.0, 'open')");
    $new_shift->execute([
        'snum'    => $shift_num,
        'reg'     => $register_id,
        'store'   => $store_id,
        'cashier' => $cashier_id
    ]);
    $shift_id = (int)$db->lastInsertId();
}

$allow_negative = (int)get_setting('allow_negative_stock', '0');
$strategy = get_setting('stock_allocation_strategy', ALLOCATION_FEFO);

// Begin Authoritative Database Transaction
$db->beginTransaction();

try {
    $subtotal = 0.0;
    $item_discount_total = 0.0;
    $tax_total = 0.0;
    $total_cogs = 0.0;
    $verified_items = [];

    // 1. Revalidate every product from database and calculate authoritative totals
    foreach ($cart as $item) {
        $product_id = (int)($item['id'] ?? 0);
        $qty = max(0.0001, (float)($item['qty'] ?? 1));
        $line_discount = max(0.0, (float)($item['discount'] ?? 0));

        // Lock product row to prevent concurrent race condition modifications
        $p_stmt = $db->prepare("SELECT p.*, tc.rate AS tax_rate, u.short_code AS unit_code 
                                FROM products p 
                                LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
                                JOIN product_units u ON p.unit_id = u.id 
                                WHERE p.id = :id AND p.is_active = 1 FOR UPDATE");
        $p_stmt->execute(['id' => $product_id]);
        $product = $p_stmt->fetch();

        if (!$product) {
            throw new Exception("Product ID {$product_id} is no longer active or available in catalog.");
        }

        // Verify stock availability
        $current_stock = get_available_stock($product_id, $store_id);
        if (!$allow_negative && ($current_stock < $qty)) {
            throw new Exception("Insufficient stock for '{$product['name']}'. Available: {$current_stock}, Requested: {$qty}");
        }

        // Check if manual price override was sent and permitted
        $unit_price = (float)$product['selling_price'];
        if (isset($item['custom_price']) && is_numeric($item['custom_price']) && has_permission('pos.price_override')) {
            $unit_price = max(0.0, (float)$item['custom_price']);
        }

        $line_subtotal = round($unit_price * $qty, 4);
        $tax_rate = (float)($product['tax_rate'] ?? 0);
        $tax_amount = round(($line_subtotal - $line_discount) * ($tax_rate / 100.0), 4);
        $line_total = round($line_subtotal - $line_discount + $tax_amount, 4);

        // Allocate batches & cost snapshot
        $allocations = allocate_stock_batches($product_id, $store_id, $qty, $strategy);
        $line_cogs = 0.0;
        foreach ($allocations as $alloc) {
            $line_cogs += round((float)$alloc['quantity'] * (float)$alloc['unit_cost'], 4);
        }

        $subtotal += $line_subtotal;
        $item_discount_total += $line_discount;
        $tax_total += $tax_amount;
        $total_cogs += $line_cogs;

        $verified_items[] = [
            'product_id'      => $product_id,
            'name'            => $product['name'],
            'sku'             => $product['sku'],
            'unit'            => $product['unit_code'],
            'qty'             => $qty,
            'unit_price'      => $unit_price,
            'cost_price'      => ($qty > 0) ? round($line_cogs / $qty, 4) : (float)$product['purchase_cost'],
            'discount'        => $line_discount,
            'tax_rate'        => $tax_rate,
            'tax_amount'      => $tax_amount,
            'line_total'      => $line_total,
            'allocations'     => $allocations
        ];
    }

    // Grand total calculation
    $grand_total = max(0.0, round($subtotal - $item_discount_total - $bill_discount + $tax_total, 2));

    $total_tendered = $paid_cash + $paid_card + $paid_wallet + $paid_credit + $paid_loyalty;
    if ($total_tendered < $grand_total) {
        throw new Exception(sprintf("Tender amount (%s) is less than required total (%s).", format_money($total_tendered), format_money($grand_total)));
    }

    $change_due = max(0.0, round($total_tendered - $grand_total, 2));
    // The actual cash collected is tendered minus change
    $net_cash_collected = max(0.0, round($paid_cash - $change_due, 2));

    // 2. Generate atomic sequential invoice number
    $invoice_number = next_document_number(PREFIX_INVOICE);

    // 3. Insert Sales Header
    $sale_insert = $db->prepare("INSERT INTO sales 
        (invoice_number, store_id, register_id, shift_id, cashier_id, customer_id, sale_date,
         subtotal, item_discount_total, bill_discount_total, tax_total, rounding_adjustment,
         grand_total, paid_total, change_due, customer_credit_amount, cogs_total, payment_status, status, notes, created_at)
        VALUES 
        (:inv, :store, :reg, :shift, :cashier, :cust, NOW(),
         :sub, :it_disc, :b_disc, :tax, 0,
         :gtot, :paid, :change, :cred, :cogs, :pay_stat, 'completed', :notes, NOW())");

    $payment_status = ($paid_credit > 0) ? 'credit' : 'paid';

    $sale_insert->execute([
        'inv'      => $invoice_number,
        'store'    => $store_id,
        'reg'      => $register_id,
        'shift'    => $shift_id,
        'cashier'  => $cashier_id,
        'cust'     => $customer_id,
        'sub'      => $subtotal,
        'it_disc'  => $item_discount_total,
        'b_disc'   => $bill_discount,
        'tax'      => $tax_total,
        'gtot'     => $grand_total,
        'paid'     => $total_tendered,
        'change'   => $change_due,
        'cred'     => $paid_credit,
        'cogs'     => $total_cogs,
        'pay_stat' => $payment_status,
        'notes'    => $notes
    ]);
    $sale_id = (int)$db->lastInsertId();

    // 4. Insert Sale Items & Record Stock Movements
    $item_insert = $db->prepare("INSERT INTO sale_items 
        (sale_id, product_id, batch_id, product_name, sku, unit_short_code, quantity, unit_price, cost_price, discount_amount, tax_rate, tax_amount, line_total)
        VALUES (:sid, :pid, :bid, :pname, :sku, :unit, :qty, :uprice, :cprice, :disc, :trate, :tamt, :ltot)");

    foreach ($verified_items as $vi) {
        $primary_batch_id = $vi['allocations'][0]['batch_id'] ?? null;
        $item_insert->execute([
            'sid'    => $sale_id,
            'pid'    => $vi['product_id'],
            'bid'    => $primary_batch_id,
            'pname'  => $vi['name'],
            'sku'    => $vi['sku'],
            'unit'   => $vi['unit'],
            'qty'    => $vi['qty'],
            'uprice' => $vi['unit_price'],
            'cprice' => $vi['cost_price'],
            'disc'   => $vi['discount'],
            'trate'  => $vi['tax_rate'],
            'tamt'   => $vi['tax_amount'],
            'ltot'   => $vi['line_total']
        ]);

        // Record Stock Movement Ledger
        record_stock_movement(
            $vi['product_id'],
            $store_id,
            'sale',
            0.0,
            $vi['qty'],
            $vi['cost_price'],
            $invoice_number,
            $primary_batch_id,
            "Sale Invoice #$invoice_number"
        );
    }

    // 5. Insert Payment Tenders
    $pay_insert = $db->prepare("INSERT INTO sale_payments (sale_id, payment_method, amount, reference_code, payment_time) VALUES (:sid, :meth, :amt, :ref, NOW())");
    if ($paid_cash > 0) {
        $pay_insert->execute(['sid' => $sale_id, 'meth' => 'cash', 'amt' => $net_cash_collected, 'ref' => 'CASH-TENDER']);
    }
    if ($paid_card > 0) {
        $pay_insert->execute(['sid' => $sale_id, 'meth' => 'card', 'amt' => $paid_card, 'ref' => $card_ref ?: 'CARD-TERMINAL']);
    }
    if ($paid_wallet > 0) {
        $pay_insert->execute(['sid' => $sale_id, 'meth' => 'digital_wallet', 'amt' => $paid_wallet, 'ref' => 'QR-DIGITAL']);
    }
    if ($paid_credit > 0) {
        $pay_insert->execute(['sid' => $sale_id, 'meth' => 'customer_credit', 'amt' => $paid_credit, 'ref' => 'KHATA-CREDIT']);
    }
    if ($paid_loyalty > 0) {
        $pay_insert->execute(['sid' => $sale_id, 'meth' => 'loyalty', 'amt' => $paid_loyalty, 'ref' => 'POINTS-REDEMPTION']);
    }

    // 6. Update Customer Khata Ledger if credit was used
    if ($paid_credit > 0 && $customer_id) {
        $upd_c = $db->prepare("UPDATE customers SET current_balance = current_balance + :amt WHERE id = :id");
        $upd_c->execute(['amt' => $paid_credit, 'id' => $customer_id]);

        $bal_check = $db->prepare("SELECT current_balance FROM customers WHERE id = :id LIMIT 1");
        $bal_check->execute(['id' => $customer_id]);
        $curr_bal = (float)$bal_check->fetchColumn();

        $c_led = $db->prepare("INSERT INTO customer_ledger (customer_id, transaction_type, reference_id, debit, credit, running_balance, notes, created_by, created_at)
                               VALUES (:cid, 'sale_credit', :ref, :dr, 0, :run, :notes, :uid, NOW())");
        $c_led->execute([
            'cid'   => $customer_id,
            'ref'   => $invoice_number,
            'dr'    => $paid_credit,
            'run'   => $curr_bal,
            'notes' => "POS Credit Sale #$invoice_number",
            'uid'   => $cashier_id
        ]);
    }

    // 7. Loyalty Points (Earn: 1 point per $10 spent on settled payments)
    if ($customer_id && ($grand_total - $paid_credit) > 0) {
        $spend_per_point = max(1.0, (float)get_setting('loyalty_spend_per_point', '10.00'));
        $points_earned = (int)floor(($grand_total - $paid_credit) / $spend_per_point);

        if ($points_earned > 0) {
            $upd_pts = $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + :pts WHERE id = :id");
            $upd_pts->execute(['pts' => $points_earned, 'id' => $customer_id]);

            $pts_log = $db->prepare("INSERT INTO loyalty_transactions (customer_id, sale_id, type, points, notes, created_at)
                                     VALUES (:cid, :sid, 'earned', :pts, :notes, NOW())");
            $pts_log->execute([
                'cid'   => $customer_id,
                'sid'   => $sale_id,
                'pts'   => $points_earned,
                'notes' => "Points earned for Invoice #$invoice_number"
            ]);
        }
    }

    // 8. Post Balanced Double-Entry Accounting
    post_sale_accounting(
        $sale_id,
        $invoice_number,
        $subtotal - $item_discount_total - $bill_discount,
        $tax_total,
        $net_cash_collected,
        $paid_card + $paid_wallet,
        $paid_credit,
        $total_cogs
    );

    // 9. Centralized Audit Log
    audit_log('sale_checkout', 'pos', 'sale', (string)$sale_id, null, [
        'invoice'     => $invoice_number,
        'grand_total' => $grand_total,
        'items_count' => count($verified_items),
        'cashier_id'  => $cashier_id
    ]);

    // Commit Transaction!
    $db->commit();

    json_success('Checkout completed successfully!', [
        'sale_id'        => $sale_id,
        'invoice_number' => $invoice_number,
        'grand_total'    => $grand_total,
        'paid_total'     => $total_tendered,
        'change_due'     => $change_due,
        'receipt_url'    => BASE_URL . '/print/receipt.php?invoice=' . urlencode($invoice_number)
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("POS Checkout Failure: " . $e->getMessage());
    json_error($e->getMessage(), [], 422);
}
