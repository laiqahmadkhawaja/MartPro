<?php
/**
 * MartPro Supplier Settlement / Accounts Payable Payment Handler
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/accounting_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('purchasing.manage');
require_csrf_token();

$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$amount = max(0.01, (float)($_POST['amount'] ?? 0));
$payment_method = $_POST['payment_method'] ?? 'bank_transfer';
$ref_number = trim($_POST['reference_number'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$store_id = current_store_id();
$user_id = current_user_id();

if (!$supplier_id || $amount <= 0) {
    json_error('Supplier and valid payment amount are required.');
}

$db = get_db();
$s_stmt = $db->prepare("SELECT name, current_balance FROM suppliers WHERE id = :id LIMIT 1");
$s_stmt->execute(['id' => $supplier_id]);
$supplier = $s_stmt->fetch();

if (!$supplier) {
    json_error('Supplier not found.');
}

$db->beginTransaction();
try {
    $voucher_no = 'PMT-SUP-' . date('ymd') . '-' . substr(uniqid(), -4);

    // 1. Record Supplier Payment
    $stmt = $db->prepare("INSERT INTO supplier_payments 
        (voucher_number, supplier_id, store_id, payment_date, payment_method, amount, reference_number, notes, created_by, created_at)
        VALUES 
        (:vnum, :sup, :store, CURDATE(), :meth, :amt, :ref, :notes, :uid, NOW())");
    $stmt->execute([
        'vnum'  => $voucher_no,
        'sup'   => $supplier_id,
        'store' => $store_id,
        'meth'  => $payment_method,
        'amt'   => $amount,
        'ref'   => $ref_number ?: 'PAYMENT-VOUCHER',
        'notes' => $notes,
        'uid'   => $user_id
    ]);
    $pay_id = (int)$db->lastInsertId();

    // 2. Reduce Supplier Liability Balance
    $upd = $db->prepare("UPDATE suppliers SET current_balance = current_balance - :amt WHERE id = :id");
    $upd->execute(['amt' => $amount, 'id' => $supplier_id]);

    // 3. Post Balanced Accounting Entry:
    // Debit Accounts Payable (2010), Credit Cash/Bank (1010/1030)
    $credit_acc = ($payment_method === 'cash') ? get_account_id('1010') : get_account_id('1030');
    $j_lines = [
        [
            'account_id' => get_account_id('2010'), // AP
            'debit'      => $amount,
            'credit'     => 0,
            'memo'       => "Payment to {$supplier['name']} ($voucher_no)"
        ],
        [
            'account_id' => $credit_acc,
            'debit'      => 0,
            'credit'     => $amount,
            'memo'       => "Settlement via $payment_method"
        ]
    ];
    post_journal_entry("Supplier Payment $voucher_no", $voucher_no, $j_lines);

    audit_log('supplier_payment', 'purchasing', 'supplier_payment', (string)$pay_id, null, [
        'voucher'  => $voucher_no,
        'supplier' => $supplier['name'],
        'amount'   => $amount
    ]);

    $db->commit();
    json_success("Payment of " . format_money($amount) . " recorded successfully! Voucher: $voucher_no");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Payment recording failed: ' . $e->getMessage());
}
