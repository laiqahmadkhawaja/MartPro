<?php
/**
 * MartPro Barcode & Shelf Label Printing Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('products.manage');

$db = get_db();
$products = $db->query("SELECT id, name, sku, primary_barcode, selling_price FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'Print Barcode Labels';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Barcode & Shelf Label Printing</h4>
        <p class="text-muted small mb-0">Generate product barcodes, shelf-edge price talkers, and thermal sticker labels</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card p-4 border-0 shadow-sm">
            <h5 class="fw-bold text-navy mb-3"><i class="bi bi-sliders me-2"></i> Label Parameters</h5>
            <form id="label-form" target="_blank" action="<?= BASE_URL ?>/print/barcode_label.php" method="GET">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select Product *</label>
                    <select name="product_id" id="label-product-select" class="form-select" required>
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-barcode="<?= $p['primary_barcode'] ?>" data-sku="<?= $p['sku'] ?>">
                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['primary_barcode']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Label Layout</label>
                        <select name="layout" class="form-select">
                            <option value="shelf">Shelf Talker (Price Large)</option>
                            <option value="sticker">Thermal Sticker (50x25mm)</option>
                            <option value="compact">Compact Jewelry / Small</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Copies to Print</label>
                        <input type="number" name="copies" class="form-control" value="6" min="1" max="100">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Barcode Symbology</label>
                    <select name="barcode_type" class="form-select">
                        <option value="CODE128">Code 128 (Universal Alpha-Numeric)</option>
                        <option value="EAN13">EAN-13 (Standard Retail)</option>
                    </select>
                </div>

                <div class="border-top pt-3 mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="show_price" id="show_price" value="1" checked>
                        <label class="form-check-label small" for="show_price">Display Retail Selling Price</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="show_sku" id="show_sku" value="1" checked>
                        <label class="form-check-label small" for="show_sku">Display SKU & Barcode Text</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_store" id="show_store" value="1" checked>
                        <label class="form-check-label small" for="show_store">Include MartPro Store Branding</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-printer me-1"></i> Generate & Print Labels
                </button>
            </form>
        </div>
    </div>

    <!-- Live Preview Card -->
    <div class="col-lg-7">
        <div class="card p-4 border-0 shadow-sm h-100">
            <h5 class="fw-bold text-navy mb-3"><i class="bi bi-eye me-2"></i> Live Visual Preview</h5>
            <div class="d-flex align-items-center justify-content-center bg-light p-4 rounded-3 flex-grow-1" style="min-height: 260px;">
                <div id="label-preview-box" class="card shadow-sm p-3 text-center border-secondary border-dashed" style="width: 250px; background: #FFF;">
                    <div class="small text-muted fw-bold mb-1">MartPro Supermarket</div>
                    <div class="fw-bold text-navy text-truncate" id="preview-title">Select a Product</div>
                    <div class="text-mp-primary fw-bold fs-3 my-1" id="preview-price">$0.00</div>
                    <div class="my-2 p-1 bg-white border">
                        <div style="font-family: monospace; letter-spacing: 3px; font-weight: bold; font-size: 1.1rem;" id="preview-barcode">|||| ||| |||||</div>
                        <div class="small text-muted" id="preview-code">000000000000</div>
                    </div>
                    <div class="small text-muted" style="font-size: 0.7rem;">Incl. GST / Tax</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
$(document).ready(function() {
    $('#label-product-select').on('change', function() {
        const opt = $(this).find(':selected');
        if (opt.val()) {
            $('#preview-title').text(opt.text().split('(')[0]);
            $('#preview-price').text('$ ' + parseFloat(opt.data('price')).toFixed(2));
            $('#preview-code').text(opt.data('barcode'));
            $('#preview-barcode').text('*' + opt.data('barcode') + '*');
        } else {
            $('#preview-title').text('Select a Product');
            $('#preview-price').text('$0.00');
            $('#preview-code').text('000000000000');
        }
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
