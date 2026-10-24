<?php
/**
 * MartPro Open Register Shift AJAX Handler
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('shifts.manage');
require_csrf_token();

$register_id = (int)($_POST['register_id'] ?? 1);
$opening_float = max(0.0, (float)($_POST['opening_cash_float'] ?? 0));
$notes = trim($_POST['notes'] ?? '');

$store_id = current_store_id();
$cashier_id = current_user_id();

$db = get_db();

// Check if open shift already exists for this register
$check = $db->prepare("SELECT id, shift_number FROM register_shifts WHERE register_id = :reg AND store_id = :store AND status = 'open' LIMIT 1");
$check->execute(['reg' => $register_id, 'store' => $store_id]);
if ($check->fetch()) {
    json_error('An open shift is already active on this register. Please close it first.');
}

$shift_number = 'SHF-' . date('ymd') . '-' . substr(uniqid(), -4);

$stmt = $db->prepare("INSERT INTO register_shifts 
    (shift_number, register_id, store_id, cashier_id, opened_at, opening_cash_float, opening_notes, status, created_at)
    VALUES 
    (:snum, :reg, :store, :cashier, NOW(), :float, :notes, 'open', NOW())");
$stmt->execute([
    'snum'    => $shift_number,
    'reg'     => $register_id,
    'store'   => $store_id,
    'cashier' => $cashier_id,
    'float'   => $opening_float,
    'notes'   => $notes
]);

$shift_id = (int)$db->lastInsertId();

audit_log('open_shift', 'shifts', 'register_shift', (string)$shift_id, null, [
    'shift_number'  => $shift_number,
    'opening_float' => $opening_float
]);

json_success("Register shift #$shift_number opened with float of " . format_money($opening_float) . "!", [
    'shift_id'     => $shift_id,
    'shift_number' => $shift_number
]);
