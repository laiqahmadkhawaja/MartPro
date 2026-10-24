<?php
/**
 * MartPro Customer Khata Debt Payment Handler
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/accounting_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('customers.manage');
require_csrf_token();

$customer_id = (int)($_POST['customer_id'] ?? 0);
$amount = max(0.01, (float)($_POST['amount'] ?? 0));
$payment_method = $_POST['payment_method'] ?? 'cash';
$notes = trim($_POST['notes'] ?? '');

$user_id = current_user_id();

if (!$customer_id || $amount <= 0) {
    json_error('Customer and valid payment amount are required.');
}

$db = get_db();
$c_stmt = $db->prepare("SELECT name, current_balance FROM customers WHERE id = :id LIMIT 1");
$c_stmt->execute(['id' => $customer_id]);
$cust = $c_stmt->fetch();

if (!$cust) {
    json_error('Customer not found.');
}

$db->beginTransaction();
try {
    $receipt_no = 'KHATA-PMT-' . date('ymd') . '-' . substr(uniqid(), -4);

    // Reduce Customer Debt
    $upd = $db->prepare("UPDATE customers SET current_balance = current_balance - :amt WHERE id = :id");
    $upd->execute(['amt' => $amount, 'id' => $customer_id]);

    $new_bal = (float)$cust['current_balance'] - $amount;

    // Record in Customer Ledger
    $led = $db->prepare("INSERT INTO customer_ledger 
        (customer_id, transaction_type, reference_id, debit, credit, running_balance, notes, created_by, created_at)
        VALUES 
        (:cid, 'payment', :ref, 0, :cr, :run, :notes, :uid, NOW())");
    $led->execute([
        'cid'   => $customer_id,
        'ref'   => $receipt_no,
        'cr'    => $amount,
        'run'   => $new_bal,
        'notes' => $notes ?: "Khata Settlement via " . ucfirst($payment_method),
        'uid'   => $user_id
    ]);

    // Accounting Entry:
    // Debit: Cash/Bank (1010/1030)
    // Credit: Accounts Receivable (1100)
    $dr_acc = ($payment_method === 'cash') ? get_account_id('1010') : get_account_id('1030');
    $j_lines = [
        [
            'account_id' => $dr_acc,
            'debit'      => $amount,
            'credit'     => 0,
            'memo'       => "Customer Khata Collection ($receipt_no)"
        ],
        [
            'account_id' => get_account_id('1100'), // AR
            'debit'      => 0,
            'credit'     => $amount,
            'memo'       => "Reduction of AR from {$cust['name']}"
        ]
    ];
    post_journal_entry("Khata Payment $receipt_no", $receipt_no, $j_lines);

    audit_log('khata_payment', 'customers', 'customer', (string)$customer_id, null, [
        'customer'   => $cust['name'],
        'amount'     => $amount,
        'receipt_no' => $receipt_no
    ]);

    $db->commit();
    json_success("Khata payment of " . format_money($amount) . " credited to {$cust['name']}! Receipt: $receipt_no");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Payment failed: ' . $e->getMessage());
}
