<?php
/**
 * MartPro Physical Inventory Audit / Stocktake Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('inventory.count');

$db = get_db();
$store_id = current_store_id();

// Fetch past counts
$stmt = $db->prepare("SELECT sc.*, u.full_name AS auditor_name,
                             (SELECT COUNT(*) FROM stock_count_items WHERE stock_count_id = sc.id) AS item_count,
                             (SELECT SUM(ABS(variance_qty)) FROM stock_count_items WHERE stock_count_id = sc.id) AS total_variance
                      FROM stock_counts sc
                      JOIN users u ON sc.created_by = u.id
                      WHERE sc.store_id = :store
                      ORDER BY sc.id DESC LIMIT 15");
$stmt->execute(['store' => $store_id]);
$past_counts = $stmt->fetchAll();

// Fetch current stock snapshot for worksheet
$prod_stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.primary_barcode, u.short_code AS unit_name, p.purchase_cost,
                                  COALESCE(sb.quantity, 0) AS expected_stock
                           FROM products p
                           JOIN product_units u ON p.unit_id = u.id
                           LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.store_id = :store
                           WHERE p.is_active = 1
                           ORDER BY p.name ASC");
$prod_stmt->execute(['store' => $store_id]);
$worksheet_products = $prod_stmt->fetchAll();

$page_title = 'Physical Stock Audit';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Physical Stock Audit & Count</h4>
        <p class="text-muted small mb-0">Reconcile expected database quantities against actual floor shelf counts</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="collapse" data-bs-target="#worksheet-collapse">
        <i class="bi bi-pencil-square me-1"></i> Open Live Count Worksheet
    </button>
</div>

<!-- Live Counting Worksheet (Collapsible) -->
<div class="collapse mb-4" id="worksheet-collapse">
    <div class="card border-0 shadow">
        <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold fs-6"><i class="bi bi-clipboard-check me-2"></i> Active Physical Inventory Count Worksheet</span>
            <span class="badge bg-mp-primary"><?= count($worksheet_products) ?> Catalog Products</span>
        </div>
        <div class="card-body p-0">
            <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                <small class="text-muted">Enter the actual counted shelf quantities. The system will compute variances automatically.</small>
                <button type="button" class="btn btn-success btn-sm fw-bold px-3" id="btn-post-stocktake">
                    <i class="bi bi-check2-all me-1"></i> Reconcile & Post Audit
                </button>
            </div>
            <div class="table-responsive" style="max-height: 400px;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Product</th>
                            <th>SKU / Barcode</th>
                            <th>Unit</th>
                            <th>Expected System Qty</th>
                            <th style="width: 160px;">Actual Counted Qty</th>
                            <th>Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($worksheet_products as $wp): ?>
                            <tr class="count-row" data-id="<?= $wp['id'] ?>" data-expected="<?= $wp['expected_stock'] ?>">
                                <td class="fw-bold text-navy"><?= htmlspecialchars($wp['name']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($wp['sku']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($wp['unit_name']) ?></span></td>
                                <td class="fw-semibold expected-cell"><?= format_qty($wp['expected_stock']) ?></td>
                                <td>
                                    <input type="number" step="0.01" class="form-control form-control-sm input-counted fw-bold" 
                                           value="<?= format_qty($wp['expected_stock']) ?>">
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted border variance-cell">0.00</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Past Stock Audit Reports Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-clock-history me-2 text-primary"></i> Historical Stocktake Audits</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Audit Ref</th>
                    <th>Date</th>
                    <th>Auditor</th>
                    <th>SKUs Audited</th>
                    <th>Discrepancy Units</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($past_counts)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No physical counts performed yet. Click "Open Live Count Worksheet" to start one!</td></tr>
                <?php else: ?>
                    <?php foreach ($past_counts as $pc): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($pc['count_number']) ?></code></td>
                            <td class="small text-muted"><?= format_date($pc['count_date']) ?></td>
                            <td class="small"><?= htmlspecialchars($pc['auditor_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $pc['item_count'] ?> items</span></td>
                            <td>
                                <?php if ($pc['total_variance'] > 0): ?>
                                    <span class="badge bg-warning-subtle text-warning fw-bold"><?= format_qty($pc['total_variance']) ?> units</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">Balanced</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-success">Posted & Reconciled</span></td>
                            <td class="small text-muted"><?= htmlspecialchars($pc['notes'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$inline_js = "
$(document).ready(function() {
    $(document).on('input', '.input-counted', function() {
        const row = $(this).closest('.count-row');
        const expected = parseFloat(row.data('expected') || 0);
        const counted = parseFloat($(this).val() || 0);
        const variance = counted - expected;
        const cell = row.find('.variance-cell');

        cell.text((variance > 0 ? '+' : '') + variance.toFixed(2));
        if (Math.abs(variance) < 0.001) {
            cell.attr('class', 'badge bg-light text-muted border variance-cell');
        } else if (variance > 0) {
            cell.attr('class', 'badge bg-success-subtle text-success border variance-cell');
        } else {
            cell.attr('class', 'badge bg-danger-subtle text-danger border variance-cell');
        }
    });

    $('#btn-post-stocktake').on('click', function() {
        if (!confirm('Are you sure you want to finalize and post this physical count? System stock balances will be adjusted to match actual counted quantities.')) return;

        const items = [];
        $('.count-row').each(function() {
            items.push({
                product_id: $(this).data('id'),
                counted_qty: parseFloat($(this).find('.input-counted').val() || 0)
            });
        });

        MartPro.ajax(MartPro.baseUrl + '/modules/stock_counts/ajax/save_count.php', {
            data: {
                items: JSON.stringify(items),
                notes: 'Floor audit completed on ' + new Date().toLocaleDateString()
            },
            button: this
        }).then(function(res) {
            MartPro.notify('success', res.message);
            setTimeout(() => window.location.reload(), 800);
        });
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
