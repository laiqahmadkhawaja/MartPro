<?php
/**
 * MartPro Accounts Payable & Supplier Settlement Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('purchasing.manage');

$db = get_db();
$store_id = current_store_id();

// Total Supplier Liabilities
$tot_stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE current_balance > 0");
$total_liabilities = (float)$tot_stmt->fetchColumn();

// Suppliers with balance
$suppliers = $db->query("SELECT * FROM suppliers WHERE current_balance > 0 ORDER BY current_balance DESC")->fetchAll();

// Recent Payment Vouchers
$pmt_stmt = $db->prepare("SELECT sp.*, s.name AS supplier_name, u.full_name AS user_name 
                          FROM supplier_payments sp
                          JOIN suppliers s ON sp.supplier_id = s.id
                          JOIN users u ON sp.created_by = u.id
                          ORDER BY sp.id DESC LIMIT 20");
$pmt_stmt->execute();
$recent_payments = $pmt_stmt->fetchAll();

$all_suppliers = $db->query("SELECT id, name, current_balance FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'Accounts Payable';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Accounts Payable & Supplier Settlements</h4>
        <p class="text-muted small mb-0">Track outstanding vendor bills, due dates, and record cash/bank settlements</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
        <i class="bi bi-cash-stack me-1"></i> Record Supplier Payment
    </button>
</div>

<!-- Payables KPI Card -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-warning border-4">
            <span class="text-muted small fw-semibold">Total Accounts Payable Liability</span>
            <h3 class="fw-bold text-danger my-1"><?= format_money($total_liabilities) ?></h3>
            <span class="small text-muted"><?= count($suppliers) ?> vendors with outstanding balances</span>
        </div>
    </div>
</div>

<!-- Outstanding Suppliers List -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-clock-history me-2 text-warning"></i> Outstanding Supplier Liabilities</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Contact Phone</th>
                    <th>Payment Terms</th>
                    <th>Credit Limit</th>
                    <th>Balance Due</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">All supplier balances are completely settled!</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-navy"><?= htmlspecialchars($s['name']) ?></div>
                                <code class="small"><?= htmlspecialchars($s['supplier_code']) ?></code>
                            </td>
                            <td><?= htmlspecialchars($s['phone']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['payment_terms']) ?></span></td>
                            <td><?= format_money($s['credit_limit']) ?></td>
                            <td class="fw-bold text-danger"><?= format_money($s['current_balance']) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-success btn-pay-sup" data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?>" data-bal="<?= $s['current_balance'] ?>">
                                    <i class="bi bi-check2 me-1"></i> Pay Balance
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Settlements History -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-receipt-cutoff me-2 text-primary"></i> Recent Payment Vouchers</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Voucher No</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th>Payment Method</th>
                    <th>Reference</th>
                    <th>Amount Paid</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_payments)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No payment vouchers on record yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_payments as $rp): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($rp['voucher_number']) ?></code></td>
                            <td class="fw-semibold"><?= htmlspecialchars($rp['supplier_name']) ?></td>
                            <td class="small text-muted"><?= format_date($rp['payment_date']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $rp['payment_method'])) ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars($rp['reference_number'] ?: '—') ?></td>
                            <td class="fw-bold text-success"><?= format_money($rp['amount']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($rp['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supplier Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i> Record Supplier Settlement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-sup-pay">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Supplier *</label>
                        <select name="supplier_id" id="modal-sup-select" class="form-select" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($all_suppliers as $as): ?>
                                <option value="<?= $as['id'] ?>" data-bal="<?= $as['current_balance'] ?>">
                                    <?= htmlspecialchars($as['name']) ?> (Owed: <?= format_money($as['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount to Pay ($) *</label>
                            <input type="number" step="0.01" name="amount" id="modal-pay-amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Method *</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Register Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="digital">Online / Digital</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cheque / Bank Reference No</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="e.g. CHQ-998822 / Wire ref">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Payment allocation memo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold" id="btn-submit-pay"><i class="bi bi-check2"></i> Record & Post Settlement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('.btn-pay-sup').on('click', function() {
    const id = $(this).data('id');
    const bal = $(this).data('bal');
    $('#modal-sup-select').val(id);
    $('#modal-pay-amount').val(parseFloat(bal).toFixed(2));
    $('#paymentModal').modal('show');
});

$('#form-sup-pay').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/accounts_payable/ajax/pay.php', {
        data: data,
        button: '#btn-submit-pay'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#paymentModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
