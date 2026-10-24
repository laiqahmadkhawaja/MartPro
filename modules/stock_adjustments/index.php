<?php
/**
 * MartPro Stock Adjustments Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('inventory.adjust');

$db = get_db();
$store_id = current_store_id();

$stmt = $db->prepare("SELECT sa.*, u.full_name AS user_name,
                             (SELECT COUNT(*) FROM stock_adjustment_items WHERE adjustment_id = sa.id) AS item_count,
                             (SELECT SUM(quantity_difference) FROM stock_adjustment_items WHERE adjustment_id = sa.id) AS net_diff
                      FROM stock_adjustments sa
                      JOIN users u ON sa.user_id = u.id
                      WHERE sa.store_id = :store
                      ORDER BY sa.id DESC LIMIT 30");
$stmt->execute(['store' => $store_id]);
$adjustments = $stmt->fetchAll();

$products = $db->query("SELECT id, name, sku, primary_barcode, selling_price FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'Stock Adjustments';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Inventory Adjustments</h4>
        <p class="text-muted small mb-0">Record physical stock write-offs, damages, spoiled goods, or discovered inventory</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
        <i class="bi bi-plus-circle me-1"></i> New Stock Adjustment
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Adjustment No</th>
                    <th>Date & Time</th>
                    <th>Reason</th>
                    <th>Items</th>
                    <th>Net Quantity Change</th>
                    <th>Responsible User</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($adjustments)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No stock adjustments on record.</td></tr>
                <?php else: ?>
                    <?php foreach ($adjustments as $adj): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($adj['adjustment_number']) ?></code></td>
                            <td class="small text-muted"><?= format_datetime($adj['created_at']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($adj['reason']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $adj['item_count'] ?> items</span></td>
                            <td>
                                <?php if ($adj['net_diff'] > 0): ?>
                                    <span class="badge bg-success-subtle text-success">+<?= format_qty($adj['net_diff']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger"><?= format_qty($adj['net_diff']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($adj['user_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($adj['notes'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i> Record Stock Adjustment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-stock-adj">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Adjustment Type *</label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="decrease">Decrease Stock (-)</option>
                                <option value="increase">Increase Stock (+)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Quantity *</label>
                            <input type="number" step="0.01" name="quantity" class="form-control" placeholder="1.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason Category *</label>
                        <select name="reason" class="form-select" required>
                            <option value="Damaged / Broken in Store">Damaged / Broken in Store</option>
                            <option value="Expired / Spoiled Goods">Expired / Spoiled Goods</option>
                            <option value="Physical Inventory Discrepancy">Physical Inventory Discrepancy</option>
                            <option value="Found Unrecorded Stock">Found Unrecorded Stock</option>
                            <option value="Internal Store Consumption">Internal Store Consumption</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Supporting Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Provide additional details or authorization reference..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-submit-adj"><i class="bi bi-check2"></i> Post Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#form-stock-adj').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/stock_adjustments/ajax/save.php', {
        data: data,
        button: '#btn-submit-adj'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#adjustmentModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
