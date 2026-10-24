<?php
/**
 * MartPro Product Catalog Management Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('products.manage');

$db = get_db();
$store_id = current_store_id();

// Search & Filters
$search = trim($_GET['search'] ?? '');
$cat_filter = !empty($_GET['category']) ? (int)$_GET['category'] : null;
$stock_filter = $_GET['stock_status'] ?? '';

// Build Query
$sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name, u.short_code AS unit_name,
               COALESCE(sb.quantity, 0) AS current_stock
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN product_brands b ON p.brand_id = b.id
        JOIN product_units u ON p.unit_id = u.id
        LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.store_id = :store
        WHERE 1=1";
$params = ['store' => $store_id];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE :q OR p.sku LIKE :q OR p.primary_barcode LIKE :q)";
    $params['q'] = "%$search%";
}
if ($cat_filter) {
    $sql .= " AND p.category_id = :cat";
    $params['cat'] = $cat_filter;
}
if ($stock_filter === 'low') {
    $sql .= " AND COALESCE(sb.quantity, 0) <= p.reorder_level";
} elseif ($stock_filter === 'out') {
    $sql .= " AND COALESCE(sb.quantity, 0) <= 0";
}

$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lookups for Modal Form
$categories = $db->query("SELECT id, name FROM product_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$brands = $db->query("SELECT id, name FROM product_brands WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$units = $db->query("SELECT id, name, short_code FROM product_units ORDER BY name ASC")->fetchAll();
$taxes = $db->query("SELECT id, name, rate FROM tax_categories WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

$page_title = 'Product Catalog';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Product Catalog & Master Data</h4>
        <p class="text-muted small mb-0">Manage retail SKUs, pricing, units, and inventory tracking policies</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/modules/products/ajax/export.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-file-earmark-excel"></i> Export CSV
        </a>
        <a href="<?= BASE_URL ?>/modules/barcodes/index.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-printer"></i> Print Labels
        </a>
        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#productModal" id="btn-add-product">
            <i class="bi bi-plus-circle"></i> Add New Product
        </button>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card mb-4 border-0 shadow-sm p-3">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search product name, SKU, or barcode..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($cat_filter == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-md-2">
            <select name="stock_status" class="form-select">
                <option value="">All Stock</option>
                <option value="low" <?= ($stock_filter === 'low') ? 'selected' : '' ?>>Low Stock</option>
                <option value="out" <?= ($stock_filter === 'out') ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-navy flex-fill"><i class="bi bi-filter"></i> Filter</button>
            <?php if (!empty($search) || $cat_filter || !empty($stock_filter)): ?>
                <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Product Details</th>
                    <th>Category / Brand</th>
                    <th>Unit</th>
                    <th>Purchase Cost</th>
                    <th>Selling Price</th>
                    <th>Current Stock</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-box fs-1 opacity-50 mb-2 d-block"></i>
                            No products match your search or filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-navy"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="small text-muted">
                                    <span class="me-2">SKU: <code><?= htmlspecialchars($p['sku']) ?></code></span>
                                    <span>Barcode: <code><?= htmlspecialchars($p['primary_barcode']) ?></code></span>
                                </div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($p['category_name'] ?: 'Uncategorized') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['brand_name'] ?: '—') ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['unit_name']) ?></span></td>
                            <td class="small"><?= format_money($p['purchase_cost']) ?></td>
                            <td class="fw-bold text-mp-primary"><?= format_money($p['selling_price']) ?></td>
                            <td>
                                <?php if ($p['current_stock'] <= 0): ?>
                                    <span class="badge bg-danger-subtle text-danger fw-bold">Out of Stock (0)</span>
                                <?php elseif ($p['current_stock'] <= $p['reorder_level']): ?>
                                    <span class="badge bg-warning-subtle text-warning fw-bold">Low (<?= format_qty($p['current_stock']) ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success fw-bold"><?= format_qty($p['current_stock']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-product" data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>' title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-toggle-status" data-id="<?= $p['id'] ?>" title="Toggle Active">
                                        <i class="bi bi-power"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Product Add / Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold" id="productModalLabel"><i class="bi bi-box-seam me-2"></i> Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-product">
                <?= csrf_token_field() ?>
                <input type="hidden" name="product_id" id="prod-id">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Product Name *</label>
                            <input type="text" name="name" id="prod-name" class="form-control" placeholder="e.g. Nestle Pure Life 1.5L" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">SKU Code *</label>
                            <input type="text" name="sku" id="prod-sku" class="form-control" placeholder="SKU-NEST-01" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Primary Barcode *</label>
                            <div class="input-group">
                                <input type="text" name="primary_barcode" id="prod-barcode" class="form-control" placeholder="890123456789" required>
                                <button type="button" class="btn btn-outline-secondary" id="btn-gen-barcode" title="Generate Barcode"><i class="bi bi-magic"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Category</label>
                            <select name="category_id" id="prod-cat" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Brand</label>
                            <select name="brand_id" id="prod-brand" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Stock Unit *</label>
                            <select name="unit_id" id="prod-unit" class="form-select" required>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['short_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Purchase Cost ($) *</label>
                            <input type="number" step="0.01" name="purchase_cost" id="prod-cost" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Selling Price ($) *</label>
                            <input type="number" step="0.01" name="selling_price" id="prod-price" class="form-control" placeholder="0.00" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Wholesale Price ($)</label>
                            <input type="number" step="0.01" name="wholesale_price" id="prod-wprice" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tax Category</label>
                            <select name="tax_category_id" id="prod-tax" class="form-select">
                                <?php foreach ($taxes as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Reorder Alert Level</label>
                            <input type="number" step="1" name="reorder_level" id="prod-reorder" class="form-control" value="5">
                        </div>

                        <div class="col-md-6" id="opening-stock-block">
                            <label class="form-label small fw-semibold">Initial Opening Stock (New Product)</label>
                            <input type="number" step="0.01" name="opening_stock" id="prod-opening-stock" class="form-control" placeholder="0.00">
                        </div>

                        <div class="col-12 border-top pt-3">
                            <div class="d-flex gap-4 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="track_batches" id="prod-tbatches" value="1">
                                    <label class="form-check-label small" for="prod-tbatches">Track Stock Batches</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="track_expiry" id="prod-texpiry" value="1">
                                    <label class="form-check-label small" for="prod-texpiry">Track Expiry Date (FEFO)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_variable_weight" id="prod-vweight" value="1">
                                    <label class="form-check-label small" for="prod-vweight">Weighable Produce (Scale Item)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold" id="btn-save-prod"><i class="bi bi-check2"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$(document).ready(function() {
    $('#btn-add-product').on('click', function() {
        $('#form-product')[0].reset();
        $('#prod-id').val('');
        $('#opening-stock-block').show();
        $('#productModalLabel').html('<i class=\"bi bi-box-seam me-2\"></i> Add New Product');
    });

    $('#btn-gen-barcode').on('click', function() {
        const randBc = '890' + Math.floor(100000000 + Math.random() * 900000000);
        $('#prod-barcode').val(randBc);
    });

    $('.btn-edit-product').on('click', function() {
        const p = $(this).data('product');
        $('#prod-id').val(p.id);
        $('#prod-name').val(p.name);
        $('#prod-sku').val(p.sku);
        $('#prod-barcode').val(p.primary_barcode);
        $('#prod-cat').val(p.category_id || '');
        $('#prod-brand').val(p.brand_id || '');
        $('#prod-unit').val(p.unit_id);
        $('#prod-cost').val(p.purchase_cost);
        $('#prod-price').val(p.selling_price);
        $('#prod-wprice').val(p.wholesale_price || '');
        $('#prod-tax').val(p.tax_category_id || 1);
        $('#prod-reorder').val(p.reorder_level);
        $('#prod-tbatches').prop('checked', p.track_batches == 1);
        $('#prod-texpiry').prop('checked', p.track_expiry == 1);
        $('#prod-vweight').prop('checked', p.is_variable_weight == 1);
        $('#opening-stock-block').hide();
        $('#productModalLabel').html('<i class=\"bi bi-pencil-square me-2\"></i> Edit Product: ' + p.name);
        $('#productModal').modal('show');
    });

    $('#form-product').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize();
        MartPro.ajax(MartPro.baseUrl + '/modules/products/ajax/save.php', {
            data: data,
            button: '#btn-save-prod'
        }).then(function(res) {
            MartPro.notify('success', res.message);
            $('#productModal').modal('hide');
            setTimeout(() => window.location.reload(), 800);
        });
    });

    $('.btn-toggle-status').on('click', function() {
        if (!confirm('Toggle active/inactive status for this product?')) return;
        const id = $(this).data('id');
        MartPro.ajax(MartPro.baseUrl + '/modules/products/ajax/toggle_status.php', {
            data: { product_id: id }
        }).then(function() {
            MartPro.notify('success', 'Product status changed.');
            setTimeout(() => window.location.reload(), 500);
        });
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
