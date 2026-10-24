<?php
/**
 * MartPro Safe Drop & Cash Movement AJAX Engine
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

$type = $_POST['movement_type'] ?? 'safe_drop'; // 'safe_drop', 'cash_in', 'cash_out'
$amount = max(0.01, (float)($_POST['amount'] ?? 0));
$register_id = !empty($_POST['register_id']) ? (int)$_POST['register_id'] : 1;
$safe_id = !empty($_POST['safe_id']) ? (int)$_POST['safe_id'] : 1;
$reason = trim($_POST['reason'] ?? 'Mid-day Safe Drop');

$store_id = current_store_id();
$user_id = current_user_id();

if ($amount <= 0 || empty($reason)) {
    json_error('Amount and reason are required.');
}

$db = get_db();
$db->beginTransaction();

try {
    $ref_no = next_document_number(PREFIX_CASH);

    $stmt = $db->prepare("INSERT INTO cash_movements 
        (reference_no, store_id, register_id, safe_id, movement_type, amount, reason, performed_by, created_at)
        VALUES 
        (:ref, :store, :reg, :safe, :mtype, :amt, :reason, :uid, NOW())");
    $stmt->execute([
        'ref'    => $ref_no,
        'store'  => $store_id,
        'reg'    => $register_id,
        'safe'   => $safe_id,
        'mtype'  => $type,
        'amt'    => $amount,
        'reason' => $reason,
        'uid'    => $user_id
    ]);
    $mov_id = (int)$db->lastInsertId();

    if ($type === 'safe_drop' || $type === 'cash_in') {
        // Increase safe account balance
        $upd_safe = $db->prepare("UPDATE safe_accounts SET current_balance = current_balance + :amt WHERE id = :id");
        $upd_safe->execute(['amt' => $amount, 'id' => $safe_id]);

        // Accounting entry: Debit Safe Cash (1020), Credit Register Cash (1010)
        $j_lines = [
            [
                'account_id' => get_account_id('1020'), // Safe
                'debit'      => $amount,
                'credit'     => 0,
                'memo'       => "Safe drop from Register ($ref_no)"
            ],
            [
                'account_id' => get_account_id('1010'), // Drawer
                'debit'      => 0,
                'credit'     => $amount,
                'memo'       => "Cash drop into safe"
            ]
        ];
        post_journal_entry("Safe Drop $ref_no", $ref_no, $j_lines);
    } else {
        // Cash out from safe
        $upd_safe = $db->prepare("UPDATE safe_accounts SET current_balance = current_balance - :amt WHERE id = :id");
        $upd_safe->execute(['amt' => $amount, 'id' => $safe_id]);
    }

    audit_log('cash_movement', 'shifts', 'cash_movement', (string)$mov_id, null, [
        'type'   => $type,
        'amount' => $amount,
        'ref'    => $ref_no
    ]);

    $db->commit();
    json_success("Cash movement of " . format_money($amount) . " recorded! Ref: $ref_no", ['reference_no' => $ref_no]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Cash movement failed: ' . $e->getMessage());
}
