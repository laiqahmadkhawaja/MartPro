<?php
/**
 * MartPro Multi-Location Stock Transfers Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('inventory.transfer');

$db = get_db();
$store_id = current_store_id();

$stmt = $db->prepare("SELECT st.*, s1.name AS source_name, s2.name AS dest_name,
                             u1.full_name AS dispatcher_name, u2.full_name AS receiver_name,
                             (SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = st.id) AS item_count,
                             (SELECT SUM(quantity_dispatched) FROM stock_transfer_items WHERE transfer_id = st.id) AS total_qty
                      FROM stock_transfers st
                      JOIN stores s1 ON st.source_store_id = s1.id
                      JOIN stores s2 ON st.dest_store_id = s2.id
                      LEFT JOIN users u1 ON st.dispatched_by = u1.id
                      LEFT JOIN users u2 ON st.received_by = u2.id
                      WHERE st.source_store_id = :store1 OR st.dest_store_id = :store2
                      ORDER BY st.id DESC LIMIT 30");
$stmt->execute(['store1' => $store_id, 'store2' => $store_id]);
$transfers = $stmt->fetchAll();

$all_stores = $db->query("SELECT id, name FROM stores WHERE is_active = 1")->fetchAll();
$products = $db->query("SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'Stock Transfers';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Multi-Store Stock Transfers</h4>
        <p class="text-muted small mb-0">Inter-branch inventory dispatch, in-transit monitoring, and goods receipt</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#dispatchModal">
        <i class="bi bi-send me-1"></i> Dispatch New Transfer
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Transfer No</th>
                    <th>Source Store</th>
                    <th>Destination Store</th>
                    <th>Items / Qty</th>
                    <th>Dispatched Date</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No stock transfers on record for this store.</td></tr>
                <?php else: ?>
                    <?php foreach ($transfers as $trf): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($trf['transfer_number']) ?></code></td>
                            <td><?= htmlspecialchars($trf['source_name']) ?></td>
                            <td class="fw-semibold text-mp-primary"><?= htmlspecialchars($trf['dest_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= format_qty($trf['total_qty']) ?> units (<?= $trf['item_count'] ?> SKUs)</span></td>
                            <td class="small text-muted"><?= format_datetime($trf['dispatched_at']) ?></td>
                            <td>
                                <?php if ($trf['status'] === 'received'): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-all me-1"></i> Received</span>
                                <?php elseif ($trf['status'] === 'dispatched'): ?>
                                    <span class="badge bg-warning-subtle text-warning"><i class="bi bi-truck me-1"></i> In Transit</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= ucfirst($trf['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($trf['status'] === 'dispatched' && $trf['dest_store_id'] == $store_id): ?>
                                    <button type="button" class="btn btn-sm btn-success btn-receive-trf" data-id="<?= $trf['id'] ?>">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small"><?= $trf['status'] === 'received' ? 'Completed' : 'Dispatched' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Dispatch Transfer Modal -->
<div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i> Dispatch Stock Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-dispatch-trf">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Destination Store *</label>
                        <select name="dest_store_id" class="form-select" required>
                            <option value="">-- Choose Store --</option>
                            <?php foreach ($all_stores as $st): ?>
                                <?php if ($st['id'] != $store_id): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Product to Transfer *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Quantity to Transfer *</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" placeholder="10.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Vehicle Ref</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Van driver Steve dispatch">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-submit-dispatch"><i class="bi bi-send"></i> Dispatch Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#form-dispatch-trf').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/stock_transfers/ajax/dispatch.php', {
        data: data,
        button: '#btn-submit-dispatch'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#dispatchModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});

$(document).on('click', '.btn-receive-trf', function() {
    if (!confirm('Confirm receiving this transfer and adding items into your store inventory?')) return;
    const id = $(this).data('id');
    MartPro.ajax(MartPro.baseUrl + '/modules/stock_transfers/ajax/receive.php', {
        data: { transfer_id: id }
    }).then(function(res) {
        MartPro.notify('success', res.message);
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
