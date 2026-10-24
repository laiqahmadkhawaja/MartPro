<?php
/**
 * MartPro Purchase Orders Module
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

$stmt = $db->prepare("SELECT po.*, s.name AS supplier_name, u.full_name AS creator_name,
                             (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) AS item_count
                      FROM purchase_orders po
                      JOIN suppliers s ON po.supplier_id = s.id
                      JOIN users u ON po.created_by = u.id
                      WHERE po.store_id = :store
                      ORDER BY po.id DESC LIMIT 30");
$stmt->execute(['store' => $store_id]);
$orders = $stmt->fetchAll();

$suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$products = $db->query("SELECT id, name, sku, purchase_cost FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'Purchase Orders';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Purchase Orders (PO)</h4>
        <p class="text-muted small mb-0">Create supplier procurement orders, track delivery schedules, and manage fulfillment</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/goods_receiving/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-box-arrow-in-down me-1"></i> Receive Goods (GRN)
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#poModal">
            <i class="bi bi-plus-circle me-1"></i> Create Purchase Order
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected Delivery</th>
                    <th>Total Value</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No purchase orders on record.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($o['po_number']) ?></code></td>
                            <td class="fw-semibold"><?= htmlspecialchars($o['supplier_name']) ?></td>
                            <td class="small text-muted"><?= format_date($o['po_date']) ?></td>
                            <td class="small"><?= format_date($o['expected_delivery_date']) ?></td>
                            <td class="fw-bold text-mp-primary"><?= format_money($o['grand_total']) ?></td>
                            <td>
                                <?php if ($o['status'] === 'received'): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i> Received</span>
                                <?php elseif ($o['status'] === 'sent'): ?>
                                    <span class="badge bg-warning-subtle text-warning"><i class="bi bi-send me-1"></i> Sent to Vendor</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= ucfirst($o['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($o['status'] !== 'received'): ?>
                                    <a href="<?= BASE_URL ?>/modules/goods_receiving/index.php?po_id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive GRN
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Fulfilled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Purchase Order Modal -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2"></i> Create Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-po">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Supplier *</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Product *</label>
                        <select name="product_id" id="po-product" class="form-select" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-cost="<?= $p['purchase_cost'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Order Quantity *</label>
                            <input type="number" step="1" name="quantity" class="form-control" value="20" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Unit Purchase Cost ($) *</label>
                            <input type="number" step="0.01" name="unit_cost" id="po-cost" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expected Delivery Date</label>
                        <input type="date" name="expected_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Order Notes / Instructions</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Standard delivery packaging"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-po"><i class="bi bi-check2"></i> Create & Dispatch PO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#po-product').on('change', function() {
    const cost = $(this).find(':selected').data('cost');
    if (cost) {
        $('#po-cost').val(parseFloat(cost).toFixed(2));
    }
});

$('#form-po').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/purchase_orders/ajax/save.php', {
        data: data,
        button: '#btn-save-po'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#poModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
