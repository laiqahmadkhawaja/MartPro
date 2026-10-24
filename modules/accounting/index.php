<?php
/**
 * MartPro Financial Accounting & General Ledger Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('accounting.view');

$db = get_db();

// Chart of accounts
$accounts = $db->query("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY code ASC")->fetchAll();

// Recent Journal Entries
$stmt = $db->query("SELECT je.*, u.full_name AS user_name,
                           (SELECT SUM(debit) FROM journal_entry_lines WHERE journal_entry_id = je.id) AS total_debit
                    FROM journal_entries je
                    LEFT JOIN users u ON je.created_by = u.id
                    ORDER BY je.id DESC LIMIT 25");
$entries = $stmt->fetchAll();

// Trial Balance aggregates
$tb_stmt = $db->query("SELECT ca.code, ca.name, ca.type,
                              COALESCE(SUM(jel.debit), 0) AS total_dr,
                              COALESCE(SUM(jel.credit), 0) AS total_cr
                       FROM chart_of_accounts ca
                       LEFT JOIN journal_entry_lines jel ON ca.id = jel.account_id
                       GROUP BY ca.id, ca.code, ca.name, ca.type
                       ORDER BY ca.code ASC");
$trial_balance = $tb_stmt->fetchAll();

$sum_dr = (float)array_sum(array_column($trial_balance, 'total_dr'));
$sum_cr = (float)array_sum(array_column($trial_balance, 'total_cr'));

$page_title = 'Accounting & Ledger';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">General Ledger & Trial Balance</h4>
        <p class="text-muted small mb-0">Automatic double-entry bookkeeping journal entries posted from sales, receipts, and settlements</p>
    </div>
</div>

<!-- Trial Balance Balanced Verification Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold text-navy"><i class="bi bi-scale me-2 text-primary"></i> Trial Balance Health & Equilibrium</span>
        <?php if (abs($sum_dr - $sum_cr) < 0.01): ?>
            <span class="badge bg-success-subtle text-success fs-6"><i class="bi bi-check-circle-fill me-1"></i> Books Balanced (Dr = Cr)</span>
        <?php else: ?>
            <span class="badge bg-danger-subtle text-danger fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i> Discrepancy Detected</span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Account Code</th>
                    <th>Account Title</th>
                    <th>Class</th>
                    <th class="text-end">Total Debits</th>
                    <th class="text-end">Total Credits</th>
                    <th class="text-end">Net Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trial_balance as $tb): ?>
                    <?php $net = (float)$tb['total_dr'] - (float)$tb['total_cr']; ?>
                    <tr>
                        <td class="fw-bold"><code><?= htmlspecialchars($tb['code']) ?></code></td>
                        <td class="fw-semibold text-navy"><?= htmlspecialchars($tb['name']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= strtoupper($tb['type']) ?></span></td>
                        <td class="text-end"><?= format_money($tb['total_dr']) ?></td>
                        <td class="text-end"><?= format_money($tb['total_cr']) ?></td>
                        <td class="text-end fw-bold <?= $net >= 0 ? 'text-navy' : 'text-danger' ?>">
                            <?= format_money(abs($net)) ?> <?= $net >= 0 ? 'Dr' : 'Cr' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3" class="text-uppercase">Total Trial Balance:</td>
                    <td class="text-end text-mp-primary fs-6"><?= format_money($sum_dr) ?></td>
                    <td class="text-end text-mp-primary fs-6"><?= format_money($sum_cr) ?></td>
                    <td class="text-end text-success">Equilibrium OK</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Journal Entries Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-journal-text me-2 text-primary"></i> Recent Double-Entry Journal Entries</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Entry Ref</th>
                    <th>Date</th>
                    <th>Source Document</th>
                    <th>Description</th>
                    <th>Total Amount</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No journal entries posted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $je): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($je['entry_number']) ?></code></td>
                            <td class="small text-muted"><?= format_date($je['entry_date']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($je['source_document'] ?: '—') ?></span></td>
                            <td class="fw-semibold"><?= htmlspecialchars($je['description']) ?></td>
                            <td class="fw-bold text-mp-primary"><?= format_money($je['total_debit']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($je['user_name'] ?: 'System Automation') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
