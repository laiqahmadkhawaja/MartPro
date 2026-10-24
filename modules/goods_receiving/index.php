<?php
/**
 * MartPro Goods Receiving Notes (GRN) Module
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

$stmt = $db->prepare("SELECT gr.*, s.name AS supplier_name, u.full_name AS receiver_name,
                             (SELECT COUNT(*) FROM goods_receipt_items WHERE grn_id = gr.id) AS item_count,
                             (SELECT SUM(quantity_received) FROM goods_receipt_items WHERE grn_id = gr.id) AS total_units
                      FROM goods_receipts gr
                      JOIN suppliers s ON gr.supplier_id = s.id
                      JOIN users u ON gr.received_by = u.id
                      WHERE gr.store_id = :store
                      ORDER BY gr.id DESC LIMIT 30");
$stmt->execute(['store' => $store_id]);
$grns = $stmt->fetchAll();

$suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$products = $db->query("SELECT id, name, sku, purchase_cost, track_batches, track_expiry FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$open_pos = $db->prepare("SELECT id, po_number, grand_total FROM purchase_orders WHERE store_id = :store AND status = 'sent' ORDER BY id DESC");
$open_pos->execute(['store' => $store_id]);
$pending_pos = $open_pos->fetchAll();

$page_title = 'Goods Receiving (GRN)';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Goods Receiving (GRN)</h4>
        <p class="text-muted small mb-0">Record incoming supplier shipments, batch allocations, expiry dates, and landed costs</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#grnModal">
        <i class="bi bi-box-arrow-in-down me-1"></i> Receive Shipment (GRN)
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>GRN Number</th>
                    <th>Supplier</th>
                    <th>Received Date</th>
                    <th>Vendor Invoice Ref</th>
                    <th>Items / Qty</th>
                    <th>Total Value</th>
                    <th>Received By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grns)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No goods receiving notes on record yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($grns as $g): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($g['grn_number']) ?></code></td>
                            <td class="fw-semibold"><?= htmlspecialchars($g['supplier_name']) ?></td>
                            <td class="small text-muted"><?= format_date($g['received_date']) ?></td>
                            <td class="small"><code><?= htmlspecialchars($g['supplier_invoice_ref']) ?></code></td>
                            <td><span class="badge bg-light text-dark border"><?= format_qty($g['total_units']) ?> units</span></td>
                            <td class="fw-bold text-mp-primary"><?= format_money($g['total_amount']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($g['receiver_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- GRN Modal -->
<div class="modal fade" id="grnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i> Receive Inbound Shipment (GRN)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-grn">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Linked Purchase Order (Optional)</label>
                            <select name="po_id" class="form-select">
                                <option value="">-- Direct Delivery (No PO) --</option>
                                <?php foreach ($pending_pos as $po): ?>
                                    <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number']) ?> (<?= format_money($po['grand_total']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Product *</label>
                            <select name="product_id" id="grn-product" class="form-select" required>
                                <option value="">-- Choose Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-cost="<?= $p['purchase_cost'] ?>">
                                        <?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Quantity Received *</label>
                            <input type="number" step="0.01" name="quantity_received" class="form-control" value="24" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Landed Cost/Unit ($) *</label>
                            <input type="number" step="0.01" name="unit_cost" id="grn-cost" class="form-control" placeholder="0.00" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Batch Number</label>
                            <input type="text" name="batch_number" class="form-control" placeholder="e.g. BATCH-2026-X1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Expiry Date (FEFO)</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Supplier Delivery Invoice Ref</label>
                            <input type="text" name="supplier_invoice_ref" class="form-control" placeholder="e.g. INV-9874">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-grn"><i class="bi bi-check2"></i> Post GRN & Add to Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#grn-product').on('change', function() {
    const cost = $(this).find(':selected').data('cost');
    if (cost) {
        $('#grn-cost').val(parseFloat(cost).toFixed(2));
    }
});

$('#form-grn').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/goods_receiving/ajax/receive.php', {
        data: data,
        button: '#btn-save-grn'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#grnModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
