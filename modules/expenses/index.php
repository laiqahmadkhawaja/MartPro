<?php
/**
 * MartPro Store Expenses Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('shifts.manage');

$db = get_db();
$store_id = current_store_id();

$stmt = $db->prepare("SELECT e.*, ec.name AS category_name, u.full_name AS recorder_name
                      FROM expenses e
                      JOIN expense_categories ec ON e.category_id = ec.id
                      JOIN users u ON e.created_by = u.id
                      WHERE e.store_id = :store
                      ORDER BY e.id DESC LIMIT 30");
$stmt->execute(['store' => $store_id]);
$expenses = $stmt->fetchAll();

$categories = $db->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll();

$tot_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE store_id = :store AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())");
$tot_stmt->execute(['store' => $store_id]);
$month_expenses = (float)$tot_stmt->fetchColumn();

$page_title = 'Store Expenses';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Store Operating Expenses</h4>
        <p class="text-muted small mb-0">Record and monitor day-to-day utilities, maintenance, and petty cash store disbursements</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#expenseModal">
        <i class="bi bi-plus-circle me-1"></i> Record New Expense
    </button>
</div>

<!-- Monthly Expense KPI -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-danger border-4">
            <span class="text-muted small fw-semibold">This Month Operating Expenses</span>
            <h3 class="fw-bold text-navy my-1"><?= format_money($month_expenses) ?></h3>
            <span class="small text-muted">All categories combined</span>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Expense Ref</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Payee / Vendor</th>
                    <th>Paid From</th>
                    <th>Amount</th>
                    <th>Recorded By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No operating expenses recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($e['expense_number']) ?></code></td>
                            <td class="small text-muted"><?= format_date($e['expense_date']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($e['category_name']) ?></span></td>
                            <td class="fw-semibold"><?= htmlspecialchars($e['payee']) ?></td>
                            <td><span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $e['paid_from'])) ?></span></td>
                            <td class="fw-bold text-danger"><?= format_money($e['amount']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($e['recorder_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($e['notes'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff me-2"></i> Record Store Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-expense">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expense Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount ($) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Paid From</label>
                            <select name="paid_from" class="form-select">
                                <option value="register_cash">Register Cash Drawer</option>
                                <option value="safe">Store Vault Safe</option>
                                <option value="bank">Bank Account</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Paid To (Payee) *</label>
                        <input type="text" name="payee" class="form-control" placeholder="e.g. Electric Power Co / Cleaner Supplies" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Receipt Number</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Supporting memo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-exp"><i class="bi bi-check2"></i> Record Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#form-expense').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/expenses/ajax/save.php', {
        data: data,
        button: '#btn-save-exp'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#expenseModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
