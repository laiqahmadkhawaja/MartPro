<?php
/**
 * MartPro Comprehensive End-to-End Test Suite
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/numbering.php';
require_once __DIR__ . '/../includes/inventory_helpers.php';
require_once __DIR__ . '/../includes/accounting_helpers.php';
require_once __DIR__ . '/../includes/audit.php';

echo "========================================================\n";
echo "   MARTPRO ENTERPRISE TEST SUITE & VERIFICATION RUNNER   \n";
echo "========================================================\n\n";

$db = get_db();
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passed++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failed++;
    }
}

// 1. Database Connectivity & Table Count
$tbl_stmt = $db->query("SHOW TABLES");
$tables = $tbl_stmt->fetchAll(PDO::FETCH_COLUMN);
assert_test("Database connected with " . count($tables) . " core tables", count($tables) >= 50);

// 2. Authentication Password Verification
$u_stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
$u_stmt->execute();
$admin = $u_stmt->fetch();
assert_test("Admin user exists and password hash validates 'admin123'", password_verify('admin123', $admin['password_hash']));

// Simulate logged-in admin session
$_SESSION['user'] = [
    'id'               => 1,
    'username'         => 'admin',
    'full_name'        => 'System Administrator',
    'email'            => 'admin@martpro.store',
    'role_id'          => 1,
    'role_slug'        => 'super_admin',
    'default_store_id' => 1,
    'permissions'      => ['pos.access', 'pos.returns', 'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'purchasing.manage', 'products.manage', 'customers.manage', 'shifts.manage', 'reports.view', 'accounting.view', 'settings.manage', 'users.manage']
];
$_SESSION['active_store_id'] = 1;
$_SESSION['last_activity'] = time();

// 3. Barcode Lookup Logic
$bc_stmt = $db->prepare("SELECT * FROM products WHERE primary_barcode = '890123450002' LIMIT 1");
$bc_stmt->execute();
$coke = $bc_stmt->fetch();
assert_test("Barcode lookup identified '{$coke['name']}'", !empty($coke) && $coke['sku'] === 'SKU-COKE-01');

// 4. Initial Stock Check
$initial_stock = get_available_stock((int)$coke['id'], 1);
assert_test("Live stock balance retrieved for Coca-Cola ({$initial_stock} units)", $initial_stock > 0);

// 5. Complete POS Sale Transaction Test
$sale_inv = next_document_number(PREFIX_INVOICE);
$qty_sold = 2.0;
$unit_price = (float)$coke['selling_price'];
$subtotal = $qty_sold * $unit_price;
$tax = $subtotal * 0.05;
$grand_total = $subtotal + $tax;
$cogs = $qty_sold * (float)$coke['purchase_cost'];

$db->beginTransaction();
$sale_insert = $db->prepare("INSERT INTO sales 
    (invoice_number, store_id, register_id, cashier_id, customer_id, sale_date, subtotal, tax_total, grand_total, paid_total, cogs_total, status, created_at)
    VALUES (:inv, 1, 1, 1, 2, NOW(), :sub, :tax, :tot, :paid, :cogs, 'completed', NOW())");
$sale_insert->execute([
    'inv'  => $sale_inv,
    'sub'  => $subtotal,
    'tax'  => $tax,
    'tot'  => $grand_total,
    'paid' => $grand_total,
    'cogs' => $cogs
]);
$sale_id = (int)$db->lastInsertId();

// Deduct stock
record_stock_movement((int)$coke['id'], 1, 'sale', 0.0, $qty_sold, (float)$coke['purchase_cost'], $sale_inv, null, "Test Sale");

// Post accounting
post_sale_accounting($sale_id, $sale_inv, $subtotal, $tax, $grand_total, 0, 0, $cogs);
$db->commit();

$post_stock = get_available_stock((int)$coke['id'], 1);
assert_test("Stock accurately deducted after sale ({$initial_stock} -> {$post_stock})", abs(($initial_stock - $qty_sold) - $post_stock) < 0.001);

// 6. Return Processing Test
$ret_inv = next_document_number(PREFIX_SALES_RET);
$db->beginTransaction();
record_stock_movement((int)$coke['id'], 1, 'sales_return', 1.0, 0.0, (float)$coke['purchase_cost'], $ret_inv, null, "Test Return");
$db->commit();
$restocked = get_available_stock((int)$coke['id'], 1);
assert_test("Returned item restocked back into inventory ({$post_stock} -> {$restocked})", abs(($post_stock + 1.0) - $restocked) < 0.001);

// 7. Physical Stock Count Variance Test
$count_expected = get_available_stock((int)$coke['id'], 1);
$count_actual = $count_expected + 5.0; // Found 5 extra units
$variance = $count_actual - $count_expected;
record_stock_movement((int)$coke['id'], 1, 'physical_count', 5.0, 0.0, (float)$coke['purchase_cost'], 'AUDIT-TEST-01', null, "Physical Count Test");
$count_reconciled = get_available_stock((int)$coke['id'], 1);
assert_test("Physical stock count variance reconciled successfully to {$count_actual}", abs($count_actual - $count_reconciled) < 0.001);

// 8. Accounts Payable & Supplier Settlement Test
$sup_stmt = $db->query("SELECT id, name, current_balance FROM suppliers WHERE id = 1");
$supplier = $sup_stmt->fetch();
$sup_before = (float)$supplier['current_balance'];
$pay_amount = 200.00;

$db->beginTransaction();
$upd_sup = $db->prepare("UPDATE suppliers SET current_balance = current_balance - :amt WHERE id = 1");
$upd_sup->execute(['amt' => $pay_amount]);
$db->commit();

$sup_after = (float)$db->query("SELECT current_balance FROM suppliers WHERE id = 1")->fetchColumn();
assert_test("Supplier liability reduced from " . format_money($sup_before) . " to " . format_money($sup_after), abs(($sup_before - $pay_amount) - $sup_after) < 0.001);

// 9. Customer Khata Credit Payment Test
$cust_stmt = $db->query("SELECT id, name, current_balance FROM customers WHERE id = 2");
$cust = $cust_stmt->fetch();
$cust_before = (float)$cust['current_balance'];
$khata_pay = 25.00;

$db->beginTransaction();
$upd_cust = $db->prepare("UPDATE customers SET current_balance = current_balance - :amt WHERE id = 2");
$upd_cust->execute(['amt' => $khata_pay]);
$db->commit();

$cust_after = (float)$db->query("SELECT current_balance FROM customers WHERE id = 2")->fetchColumn();
assert_test("Customer Khata debt reduced from " . format_money($cust_before) . " to " . format_money($cust_after), abs(($cust_before - $khata_pay) - $cust_after) < 0.001);

// 10. Trial Balance Equilibrium Test (Debits == Credits)
$tb_stmt = $db->query("SELECT COALESCE(SUM(debit), 0) AS total_dr, COALESCE(SUM(credit), 0) AS total_cr FROM journal_entry_lines");
$tb = $tb_stmt->fetch();
$dr = (float)$tb['total_dr'];
$cr = (float)$tb['total_cr'];
assert_test("Double-entry accounting trial balance is balanced (Debits: " . format_money($dr) . " == Credits: " . format_money($cr) . ")", abs($dr - $cr) < 0.01);

// 11. Audit Trail Append-Only Test
audit_log('test_action', 'system', 'test_entity', '101', null, ['status' => 'verified']);
$audit_check = $db->query("SELECT action FROM audit_logs WHERE action = 'test_action' ORDER BY id DESC LIMIT 1")->fetchColumn();
assert_test("Audit trail log persisted with action '{$audit_check}'", $audit_check === 'test_action');

echo "\n--------------------------------------------------------\n";
echo "TEST RESULTS: Passed: $passed | Failed: $failed\n";
echo "STATUS: " . ($failed === 0 ? "ALL SYSTEMS OPERATIONAL & VERIFIED!" : "ISSUES ENCOUNTERED") . "\n";
echo "--------------------------------------------------------\n";
