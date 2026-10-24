<?php
/**
 * MartPro Blind Shift Close & Cash Variance Reconciliation Engine
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('shifts.manage');
require_csrf_token();

$shift_id = (int)($_POST['shift_id'] ?? 0);
$counted_cash = max(0.0, (float)($_POST['counted_cash'] ?? 0));
$closing_notes = trim($_POST['closing_notes'] ?? '');

$user_id = current_user_id();
$db = get_db();

// 1. Fetch Open Shift
$stmt = $db->prepare("SELECT * FROM register_shifts WHERE id = :id AND status = 'open' LIMIT 1");
$stmt->execute(['id' => $shift_id]);
$shift = $stmt->fetch();

if (!$shift) {
    json_error('Shift not found or already closed.');
}

// 2. Calculate Authoritative Cash Movements during this shift:
// Cash Sales
$cs_stmt = $db->prepare("SELECT COALESCE(SUM(sp.amount), 0) 
                         FROM sale_payments sp
                         JOIN sales s ON sp.sale_id = s.id
                         WHERE s.shift_id = :sid AND sp.payment_method = 'cash'");
$cs_stmt->execute(['sid' => $shift_id]);
$total_cash_sales = (float)$cs_stmt->fetchColumn();

// Cash Refunds
$cr_stmt = $db->prepare("SELECT COALESCE(SUM(sr.total_refund), 0)
                         FROM sale_returns sr
                         JOIN sales s ON sr.original_sale_id = s.id
                         WHERE s.shift_id = :sid AND sr.refund_method = 'cash'");
$cr_stmt->execute(['sid' => $shift_id]);
$total_cash_refunds = (float)$cr_stmt->fetchColumn();

// Cash Safe Drops from this register
$cd_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_movements WHERE register_id = :reg AND movement_type = 'safe_drop' AND created_at >= :opened");
$cd_stmt->execute(['reg' => $shift['register_id'], 'opened' => $shift['opened_at']]);
$total_safe_drops = (float)$cd_stmt->fetchColumn();

// Petty Cash Expenses paid from register drawer
$exp_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE store_id = :store AND paid_from = 'register_cash' AND created_at >= :opened");
$exp_stmt->execute(['store' => $shift['store_id'], 'opened' => $shift['opened_at']]);
$total_drawer_expenses = (float)$exp_stmt->fetchColumn();

// Expected Cash in Drawer:
// Opening Float + Cash Sales - Cash Refunds - Safe Drops - Drawer Expenses
$expected_cash = round((float)$shift['opening_cash_float'] + $total_cash_sales - $total_cash_refunds - $total_safe_drops - $total_drawer_expenses, 2);

// Cash Variance: (Counted - Expected). Positive = Overage, Negative = Shortage
$variance = round($counted_cash - $expected_cash, 2);

// Close Shift
$upd = $db->prepare("UPDATE register_shifts SET 
    closed_at = NOW(),
    closed_by = :uid,
    expected_cash = :exp,
    counted_cash = :cnt,
    cash_variance = :var,
    status = 'closed',
    closing_notes = :notes
    WHERE id = :id");
$upd->execute([
    'uid'   => $user_id,
    'exp'   => $expected_cash,
    'cnt'   => $counted_cash,
    'var'   => $variance,
    'notes' => $closing_notes,
    'id'    => $shift_id
]);

audit_log('close_shift', 'shifts', 'register_shift', (string)$shift_id, null, [
    'shift_number'  => $shift['shift_number'],
    'expected_cash' => $expected_cash,
    'counted_cash'  => $counted_cash,
    'variance'      => $variance
]);

json_success("Shift closed! Expected: " . format_money($expected_cash) . ", Counted: " . format_money($counted_cash) . ", Variance: " . format_money($variance), [
    'shift_id'      => $shift_id,
    'expected_cash' => $expected_cash,
    'counted_cash'  => $counted_cash,
    'cash_variance' => $variance
]);
