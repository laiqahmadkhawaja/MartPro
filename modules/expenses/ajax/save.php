<?php
/**
 * MartPro Store Expenses AJAX Handler
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
require_once __DIR__ . '/../../../includes/accounting_helpers.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('shifts.manage');
require_csrf_token();

$category_id = (int)($_POST['category_id'] ?? 0);
$amount = max(0.01, (float)($_POST['amount'] ?? 0));
$payee = trim($_POST['payee'] ?? '');
$paid_from = $_POST['paid_from'] ?? 'register_cash';
$notes = trim($_POST['notes'] ?? '');

$store_id = current_store_id();
$user_id = current_user_id();

if (!$category_id || $amount <= 0 || empty($payee)) {
    json_error('Expense category, payee, and valid amount are required.');
}

$db = get_db();
$db->beginTransaction();

try {
    $exp_number = next_document_number(PREFIX_EXPENSE);

    $stmt = $db->prepare("INSERT INTO expenses 
        (expense_number, store_id, category_id, amount, expense_date, paid_from, payee, notes, created_by, created_at)
        VALUES 
        (:num, :store, :cat, :amt, CURDATE(), :pfrom, :payee, :notes, :uid, NOW())");
    $stmt->execute([
        'num'   => $exp_number,
        'store' => $store_id,
        'cat'   => $category_id,
        'amt'   => $amount,
        'pfrom' => $paid_from,
        'payee' => $payee,
        'notes' => $notes,
        'uid'   => $user_id
    ]);
    $exp_id = (int)$db->lastInsertId();

    // Accounting Entry:
    // Debit Operating Expenses (6010), Credit Cash/Bank (1010/1020/1030)
    $credit_acc = ($paid_from === 'register_cash') ? get_account_id('1010') : 
                  (($paid_from === 'safe') ? get_account_id('1020') : get_account_id('1030'));

    $j_lines = [
        [
            'account_id' => get_account_id('6010'), // Expenses
            'debit'      => $amount,
            'credit'     => 0,
            'memo'       => "Store Expense $exp_number - $payee"
        ],
        [
            'account_id' => $credit_acc,
            'debit'      => 0,
            'credit'     => $amount,
            'memo'       => "Paid from $paid_from"
        ]
    ];
    post_journal_entry("Expense $exp_number", $exp_number, $j_lines);

    audit_log('record_expense', 'accounting', 'expense', (string)$exp_id, null, [
        'exp_number' => $exp_number,
        'amount'     => $amount,
        'payee'      => $payee
    ]);

    $db->commit();
    json_success("Expense of " . format_money($amount) . " recorded! Ref: $exp_number", ['expense_number' => $exp_number]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Expense recording failed: ' . $e->getMessage());
}
