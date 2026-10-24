<?php
/**
 * MartPro Pricing & Promotions Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('pos.discount');

$db = get_db();
$promotions = $db->query("SELECT * FROM promotions ORDER BY id DESC")->fetchAll();

$page_title = 'Promotions';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Pricing Rules & Promotions Engine</h4>
        <p class="text-muted small mb-0">Create automated basket discounts, seasonal percentage specials, and minimum order vouchers</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#promoModal">
        <i class="bi bi-tag me-1"></i> Add Promotion Rule
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Promotion Title</th>
                    <th>Discount Type</th>
                    <th>Discount Value</th>
                    <th>Minimum Spend</th>
                    <th>Effective Dates</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promotions)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No active promotional campaigns configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($promotions as $pr): ?>
                        <tr>
                            <td class="fw-bold text-navy"><?= htmlspecialchars($pr['title']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $pr['type'])) ?></span></td>
                            <td class="fw-bold text-mp-primary">
                                <?= $pr['type'] === 'percentage' ? number_format($pr['discount_value'], 1) . '%' : format_money($pr['discount_value']) ?>
                            </td>
                            <td><?= $pr['min_order_amount'] > 0 ? format_money($pr['min_order_amount']) : 'No Minimum' ?></td>
                            <td class="small text-muted"><?= format_date($pr['start_date']) ?> &rarr; <?= format_date($pr['end_date']) ?></td>
                            <td><span class="badge bg-success">Active</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Promo Modal -->
<div class="modal fade" id="promoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-tag me-2"></i> Create Promotion Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-promo">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Promotion Campaign Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Weekend Flash Sale">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Type</label>
                            <select name="type" class="form-select">
                                <option value="percentage">Percentage Off (%)</option>
                                <option value="fixed_amount">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Value *</label>
                            <input type="number" step="0.5" name="discount_value" class="form-control" placeholder="10" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Minimum Order Basket ($)</label>
                        <input type="number" step="5" name="min_order_amount" class="form-control" value="0.00">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-12-31') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-promo"><i class="bi bi-check2"></i> Save Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#form-promo').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/promotions/ajax/save.php', {
        data: data,
        button: '#btn-save-promo'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#promoModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
