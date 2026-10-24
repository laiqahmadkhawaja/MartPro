<?php
/**
 * MartPro Cashier Shifts, X-Report & Z-Report Module
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

// 1. Fetch current open shift for this store
$open_stmt = $db->prepare("SELECT rs.*, r.name AS register_name, u.full_name AS cashier_name 
                           FROM register_shifts rs
                           JOIN registers r ON rs.register_id = r.id
                           JOIN users u ON rs.cashier_id = u.id
                           WHERE rs.store_id = :store AND rs.status = 'open' LIMIT 1");
$open_stmt->execute(['store' => $store_id]);
$active_shift = $open_stmt->fetch();

$active_sales = 0.0;
$active_orders = 0;
if ($active_shift) {
    $stat_stmt = $db->prepare("SELECT COUNT(*) AS total_tx, COALESCE(SUM(grand_total), 0) AS total_sales 
                               FROM sales WHERE shift_id = :sid AND status = 'completed'");
    $stat_stmt->execute(['sid' => $active_shift['id']]);
    $st = $stat_stmt->fetch();
    $active_sales = (float)$st['total_sales'];
    $active_orders = (int)$st['total_tx'];
}

// 2. Fetch past closed shifts (Z-Reports)
$past_stmt = $db->prepare("SELECT rs.*, r.name AS register_name, u1.full_name AS cashier_name, u2.full_name AS closer_name
                           FROM register_shifts rs
                           JOIN registers r ON rs.register_id = r.id
                           JOIN users u1 ON rs.cashier_id = u1.id
                           LEFT JOIN users u2 ON rs.closed_by = u2.id
                           WHERE rs.store_id = :store AND rs.status = 'closed'
                           ORDER BY rs.id DESC LIMIT 20");
$past_stmt->execute(['store' => $store_id]);
$closed_shifts = $past_stmt->fetchAll();

$registers = $db->prepare("SELECT id, name, code FROM registers WHERE store_id = :store AND is_active = 1");
$registers->execute(['store' => $store_id]);
$reg_list = $registers->fetchAll();

$page_title = 'Cashier Shifts & X/Z Reports';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Cashier Shifts & Cash Reconciliation</h4>
        <p class="text-muted small mb-0">Monitor cash drawer floats, generate mid-shift X-Reports, perform safe drops, and execute blind Z-closings</p>
    </div>
    <div>
        <?php if (!$active_shift): ?>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#openShiftModal">
                <i class="bi bi-play-circle me-1"></i> Open New Shift
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Active Shift Banner / Card -->
<?php if ($active_shift): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-success border-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <span class="badge bg-success-subtle text-success mb-2"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i> Active Open Shift</span>
                    <h5 class="fw-bold text-navy mb-1"><?= htmlspecialchars($active_shift['shift_number']) ?> &mdash; <?= htmlspecialchars($active_shift['register_name']) ?></h5>
                    <div class="small text-muted">
                        Cashier: <strong><?= htmlspecialchars($active_shift['cashier_name']) ?></strong> &bull;
                        Opened: <?= format_datetime($active_shift['opened_at']) ?> &bull;
                        Opening Float: <strong class="text-mp-primary"><?= format_money($active_shift['opening_cash_float']) ?></strong>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-md-end">
                        <span class="small text-muted d-block">Shift Gross Sales</span>
                        <h4 class="fw-bold text-navy mb-0"><?= format_money($active_sales) ?></h4>
                        <small class="text-muted"><?= $active_orders ?> checkout orders</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-print-xreport">
                            <i class="bi bi-receipt me-1"></i> X-Report
                        </button>
                        <button type="button" class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#closeShiftModal">
                            <i class="bi bi-lock me-1"></i> Blind Close Shift
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info d-flex align-items-center p-3 mb-4 shadow-sm">
        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">No Active Shift Open</h6>
            <p class="small mb-0">Open a new cashier shift with an opening cash float to begin checkout and track register reconciliation.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Closed Shifts History Table (Z-Reports) -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-clock-history me-2 text-primary"></i> Finalized Shift Audit History (Z-Reports)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Shift Ref</th>
                    <th>Register</th>
                    <th>Cashier</th>
                    <th>Opened / Closed</th>
                    <th>Opening Float</th>
                    <th>Expected Cash</th>
                    <th>Counted Cash</th>
                    <th>Variance</th>
                    <th>Closing Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($closed_shifts)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No finalized shifts on record yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($closed_shifts as $cs): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($cs['shift_number']) ?></code></td>
                            <td><?= htmlspecialchars($cs['register_name']) ?></td>
                            <td><?= htmlspecialchars($cs['cashier_name']) ?></td>
                            <td class="small text-muted">
                                <div><?= format_datetime($cs['opened_at']) ?></div>
                                <div><?= format_datetime($cs['closed_at']) ?></div>
                            </td>
                            <td class="small"><?= format_money($cs['opening_cash_float']) ?></td>
                            <td class="small fw-semibold"><?= format_money($cs['expected_cash']) ?></td>
                            <td class="small fw-bold"><?= format_money($cs['counted_cash']) ?></td>
                            <td>
                                <?php if ($cs['cash_variance'] < 0): ?>
                                    <span class="badge bg-danger-subtle text-danger fw-bold">Shortage: <?= format_money($cs['cash_variance']) ?></span>
                                <?php elseif ($cs['cash_variance'] > 0): ?>
                                    <span class="badge bg-success-subtle text-success fw-bold">Overage: +<?= format_money($cs['cash_variance']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-success border">Exact $0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($cs['closing_notes'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Open Shift Modal -->
<div class="modal fade" id="openShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-play-circle me-2"></i> Open Cashier Shift</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-open-shift">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Register Lane *</label>
                        <select name="register_id" class="form-select" required>
                            <?php foreach ($reg_list as $reg): ?>
                                <option value="<?= $reg['id'] ?>"><?= htmlspecialchars($reg['name']) ?> (<?= htmlspecialchars($reg['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Opening Cash Float ($) *</label>
                        <input type="number" step="0.01" name="opening_cash_float" class="form-control form-control-lg fw-bold" value="100.00" required>
                        <small class="text-muted">Coins and bills placed into the drawer at start of shift.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Opening Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Standard morning float"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-open-shift"><i class="bi bi-check2"></i> Open Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Blind Close Shift Modal -->
<?php if ($active_shift): ?>
    <div class="modal fade" id="closeShiftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-lock me-2"></i> Blind Shift Close (Z-Report)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="form-close-shift">
                    <?= csrf_token_field() ?>
                    <input type="hidden" name="shift_id" value="<?= $active_shift['id'] ?>">
                    <div class="modal-body p-4">
                        <div class="alert alert-warning small">
                            <i class="bi bi-shield-lock me-1"></i> <strong>Blind Closing Protocol:</strong> Count all cash physical bills and coins in your drawer. Do not look at expected totals. The system will independently compare and record any discrepancy.
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Physical Counted Cash ($) *</label>
                            <input type="number" step="0.01" name="counted_cash" class="form-control form-control-lg fw-bold" placeholder="0.00" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Closing Notes / Discrepancy Reason</label>
                            <textarea name="closing_notes" class="form-control" rows="2" placeholder="Explain any notable register occurrences..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger fw-bold" id="btn-save-close-shift"><i class="bi bi-lock-fill"></i> Close Shift & Produce Z-Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$inline_js = "
$('#form-open-shift').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/shifts/ajax/open_shift.php', {
        data: data,
        button: '#btn-save-open-shift'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#openShiftModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});

$('#form-close-shift').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/shifts/ajax/close_shift.php', {
        data: data,
        button: '#btn-save-close-shift'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#closeShiftModal').modal('hide');
        setTimeout(() => window.location.reload(), 1000);
    });
});

$('#btn-print-xreport').on('click', function() {
    window.print();
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
