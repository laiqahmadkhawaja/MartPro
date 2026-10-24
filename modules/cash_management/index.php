<?php
/**
 * MartPro Cash Management & Safe Accounts Module
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

// Safe Account Balance
$safe_stmt = $db->prepare("SELECT * FROM safe_accounts WHERE store_id = :store LIMIT 1");
$safe_stmt->execute(['store' => $store_id]);
$safe = $safe_stmt->fetch() ?: ['id' => 1, 'name' => 'Main Drop Safe', 'current_balance' => 0];

// Recent Cash Movements
$stmt = $db->prepare("SELECT cm.*, u.full_name AS user_name, r.name AS register_name 
                      FROM cash_movements cm
                      JOIN users u ON cm.performed_by = u.id
                      LEFT JOIN registers r ON cm.register_id = r.id
                      WHERE cm.store_id = :store
                      ORDER BY cm.id DESC LIMIT 20");
$stmt->execute(['store' => $store_id]);
$movements = $stmt->fetchAll();

$registers = $db->prepare("SELECT id, name FROM registers WHERE store_id = :store AND is_active = 1");
$registers->execute(['store' => $store_id]);
$reg_list = $registers->fetchAll();

$page_title = 'Safe & Cash Drops';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Safe Accounts & Cash Management</h4>
        <p class="text-muted small mb-0">Manage store vault cash, drawer drops, change float replenishment, and petty cash</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-receipt-cutoff me-1"></i> Store Expenses
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#safeDropModal">
            <i class="bi bi-shield-lock me-1"></i> Perform Safe Drop
        </button>
    </div>
</div>

<!-- Safe Account Balance Card -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-success border-4">
            <span class="text-muted small fw-semibold"><?= htmlspecialchars($safe['name']) ?> Vault Balance</span>
            <h3 class="fw-bold text-navy my-1"><?= format_money($safe['current_balance']) ?></h3>
            <span class="small text-muted">Secured store safe funds</span>
        </div>
    </div>
</div>

<!-- Cash Movements Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-journal-arrow-down me-2 text-primary"></i> Cash Drops & Transfers Log</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Ref No</th>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Register Lane</th>
                    <th>Amount</th>
                    <th>Reason / Memo</th>
                    <th>Authorized User</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No cash movements recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($m['reference_no']) ?></code></td>
                            <td class="small text-muted"><?= format_datetime($m['created_at']) ?></td>
                            <td>
                                <?php if ($m['movement_type'] === 'safe_drop'): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-down-circle me-1"></i> Safe Drop</span>
                                <?php elseif ($m['movement_type'] === 'cash_in'): ?>
                                    <span class="badge bg-info-subtle text-info">Cash In</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Cash Out</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['register_name'] ?: 'Store Vault') ?></td>
                            <td class="fw-bold text-navy"><?= format_money($m['amount']) ?></td>
                            <td class="small"><?= htmlspecialchars($m['reason']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($m['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Safe Drop Modal -->
<div class="modal fade" id="safeDropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2"></i> Register Cash Safe Drop</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-safe-drop">
                <?= csrf_token_field() ?>
                <input type="hidden" name="movement_type" value="safe_drop">
                <input type="hidden" name="safe_id" value="<?= $safe['id'] ?>">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Source Register Lane *</label>
                        <select name="register_id" class="form-select" required>
                            <?php foreach ($reg_list as $reg): ?>
                                <option value="<?= $reg['id'] ?>"><?= htmlspecialchars($reg['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Drop Amount ($) *</label>
                        <input type="number" step="10" name="amount" class="form-control form-control-lg fw-bold" placeholder="500.00" required>
                        <small class="text-muted">Cash taken from drawer and dropped into safe.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason / Drop Reference</label>
                        <input type="text" name="reason" class="form-control" value="Mid-shift Cash Drawer Drop" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-drop"><i class="bi bi-check2"></i> Execute Safe Drop</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#form-safe-drop').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/cash_management/ajax/safe_drop.php', {
        data: data,
        button: '#btn-save-drop'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#safeDropModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
