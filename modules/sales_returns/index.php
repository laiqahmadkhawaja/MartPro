<?php
/**
 * MartPro Sales Returns & Refunds Interface
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('pos.returns');

$db = get_db();
$store_id = current_store_id();

$lookup_invoice = trim($_GET['invoice'] ?? '');
$target_sale = null;
$sale_items = [];

if (!empty($lookup_invoice)) {
    $s_stmt = $db->prepare("SELECT s.*, u.full_name AS cashier_name, COALESCE(c.name, 'Walk-in Retail Customer') AS customer_name
                            FROM sales s
                            JOIN users u ON s.cashier_id = u.id
                            LEFT JOIN customers c ON s.customer_id = c.id
                            WHERE s.invoice_number = :inv LIMIT 1");
    $s_stmt->execute(['inv' => $lookup_invoice]);
    $target_sale = $s_stmt->fetch();

    if ($target_sale) {
        $si_stmt = $db->prepare("SELECT si.*, 
                                        COALESCE((SELECT SUM(quantity) FROM sale_return_items WHERE sale_item_id = si.id), 0) AS previously_returned
                                 FROM sale_items si
                                 WHERE si.sale_id = :sid");
        $si_stmt->execute(['sid' => $target_sale['id']]);
        $sale_items = $si_stmt->fetchAll();
    }
}

// Recent Returns History
$ret_stmt = $db->prepare("SELECT sr.*, s.invoice_number, u.full_name AS cashier_name,
                                 (SELECT COUNT(*) FROM sale_return_items WHERE sale_return_id = sr.id) AS item_count
                          FROM sale_returns sr
                          JOIN sales s ON sr.original_sale_id = s.id
                          JOIN users u ON sr.cashier_id = u.id
                          WHERE sr.store_id = :store
                          ORDER BY sr.id DESC LIMIT 15");
$ret_stmt->execute(['store' => $store_id]);
$past_returns = $ret_stmt->fetchAll();

$page_title = 'Sales Returns & Refunds';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Customer Sales Returns & Refunds</h4>
        <p class="text-muted small mb-0">Look up original invoices, verify return eligibility, classify resalable/damaged items, and issue refunds</p>
    </div>
</div>

<!-- Invoice Lookup Card -->
<div class="card mb-4 border-0 shadow-sm p-4">
    <h6 class="fw-bold text-navy mb-3"><i class="bi bi-search me-2"></i> Step 1: Look Up Original Receipt / Invoice</h6>
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-muted"></i></span>
                <input type="text" name="invoice" class="form-control form-control-lg fw-bold" placeholder="Scan or enter Invoice # (e.g. INV-26-10001)" value="<?= htmlspecialchars($lookup_invoice) ?>" required autofocus>
            </div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-navy btn-lg w-100 fw-bold">
                <i class="bi bi-arrow-right-circle me-1"></i> Retrieve Invoice
            </button>
        </div>
    </form>
</div>

<!-- Return Items Worksheet -->
<?php if ($target_sale): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-3">
            <div>
                <span class="fw-bold fs-6">Invoice #<?= htmlspecialchars($target_sale['invoice_number']) ?></span>
                <span class="small opacity-75 ms-2">Customer: <?= htmlspecialchars($target_sale['customer_name']) ?> | Date: <?= format_datetime($target_sale['sale_date']) ?></span>
            </div>
            <span class="badge bg-success fs-6">Original Total: <?= format_money($target_sale['grand_total']) ?></span>
        </div>
        <form id="form-process-return">
            <?= csrf_token_field() ?>
            <input type="hidden" name="invoice_number" value="<?= htmlspecialchars($target_sale['invoice_number']) ?>">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name & SKU</th>
                            <th>Original Qty</th>
                            <th>Already Returned</th>
                            <th>Eligible to Return</th>
                            <th style="width: 140px;">Return Qty</th>
                            <th style="width: 160px;">Condition</th>
                            <th class="text-end">Unit Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_items as $item): ?>
                            <?php $remaining_returnable = (float)$item['quantity'] - (float)$item['previously_returned']; ?>
                            <tr class="return-item-row" data-id="<?= $item['id'] ?>" data-price="<?= $item['unit_price'] ?>">
                                <td>
                                    <div class="fw-bold text-navy"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                </td>
                                <td><?= format_qty($item['quantity']) ?> <?= htmlspecialchars($item['unit_short_code']) ?></td>
                                <td><?= format_qty($item['previously_returned']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= format_qty($remaining_returnable) ?></span></td>
                                <td>
                                    <input type="number" step="1" max="<?= $remaining_returnable ?>" min="0" 
                                           class="form-control form-control-sm return-qty-input fw-bold" 
                                           value="0" <?= $remaining_returnable <= 0 ? 'disabled' : '' ?>>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm return-condition-select" <?= $remaining_returnable <= 0 ? 'disabled' : '' ?>>
                                        <option value="resalable">Resalable (Restock)</option>
                                        <option value="damaged">Damaged / Broken</option>
                                        <option value="expired">Expired Goods</option>
                                    </select>
                                </td>
                                <td class="text-end fw-bold"><?= format_money($item['unit_price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-light p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Refund Method</label>
                        <select name="refund_method" class="form-select">
                            <option value="cash">Cash Refund</option>
                            <option value="customer_credit">Customer Credit (Khata)</option>
                            <option value="voucher">Store Voucher</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Return Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Defective packaging or customer exchange" required>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="small text-muted d-block mb-1">Total Refund Calculated:</span>
                        <h4 class="fw-bold text-danger mb-2" id="display-refund-total">$ 0.00</h4>
                        <button type="submit" class="btn btn-danger fw-bold px-4" id="btn-submit-return">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Authorize & Issue Refund
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?php elseif (!empty($lookup_invoice)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> No sales invoice found matching <strong><?= htmlspecialchars($lookup_invoice) ?></strong>. Please verify the invoice number or barcode.
    </div>
<?php endif; ?>

<!-- Recent Returns History Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold text-navy"><i class="bi bi-clock-history me-2 text-danger"></i> Recent Processed Returns</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Return No</th>
                    <th>Original Invoice</th>
                    <th>Date & Time</th>
                    <th>Refund Method</th>
                    <th>Items</th>
                    <th>Refund Issued</th>
                    <th>Reason</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($past_returns)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No returns processed yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($past_returns as $pr): ?>
                        <tr>
                            <td class="fw-bold text-danger"><code><?= htmlspecialchars($pr['return_number']) ?></code></td>
                            <td><code><?= htmlspecialchars($pr['invoice_number']) ?></code></td>
                            <td class="small text-muted"><?= format_datetime($pr['created_at']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $pr['refund_method'])) ?></span></td>
                            <td><span class="badge bg-secondary"><?= $pr['item_count'] ?> items</span></td>
                            <td class="fw-bold text-danger"><?= format_money($pr['total_refund']) ?></td>
                            <td class="small"><?= htmlspecialchars($pr['reason']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($pr['cashier_name']) ?></td>
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
    function recalcReturnTotal() {
        let total = 0;
        $('.return-item-row').each(function() {
            const qty = parseFloat($(this).find('.return-qty-input').val() || 0);
            const price = parseFloat($(this).data('price') || 0);
            total += (qty * price);
        });
        $('#display-refund-total').text('$ ' + total.toFixed(2));
    }

    $('.return-qty-input').on('input', recalcReturnTotal);

    $('#form-process-return').on('submit', function(e) {
        e.preventDefault();
        const items = [];
        $('.return-item-row').each(function() {
            const qty = parseFloat($(this).find('.return-qty-input').val() || 0);
            if (qty > 0) {
                items.push({
                    sale_item_id: $(this).data('id'),
                    quantity: qty,
                    condition: $(this).find('.return-condition-select').val()
                });
            }
        });

        if (items.length === 0) {
            MartPro.notify('warning', 'Please enter a return quantity greater than 0 for at least one item.');
            return;
        }

        const payload = {
            invoice_number: $('input[name=\"invoice_number\"]').val(),
            refund_method: $('select[name=\"refund_method\"]').val(),
            reason: $('input[name=\"reason\"]').val(),
            return_items: JSON.stringify(items)
        };

        MartPro.ajax(MartPro.baseUrl + '/modules/sales_returns/ajax/process_return.php', {
            data: payload,
            button: '#btn-submit-return'
        }).then(function(res) {
            MartPro.notify('success', res.message);
            setTimeout(() => window.location.href = 'index.php', 1200);
        });
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
