<?php
/**
 * MartPro Thermal Receipt Printing Template (58mm / 80mm)
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$invoice_number = trim($_GET['invoice'] ?? '');
if (empty($invoice_number)) {
    die("Receipt Error: Invoice number is missing.");
}

$db = get_db();
$sale_stmt = $db->prepare("SELECT s.*, st.name AS store_name, st.phone AS store_phone, st.address AS store_address,
                                  st.receipt_header, st.receipt_footer, reg.name AS register_name,
                                  u.full_name AS cashier_name, c.name AS customer_name, c.phone AS customer_phone, c.loyalty_points
                           FROM sales s
                           JOIN stores st ON s.store_id = st.id
                           JOIN registers reg ON s.register_id = reg.id
                           JOIN users u ON s.cashier_id = u.id
                           LEFT JOIN customers c ON s.customer_id = c.id
                           WHERE s.invoice_number = :inv LIMIT 1");
$sale_stmt->execute(['inv' => $invoice_number]);
$sale = $sale_stmt->fetch();

if (!$sale) {
    die("Receipt Error: Transaction #$invoice_number not found.");
}

// Items
$items_stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = :sid");
$items_stmt->execute(['sid' => $sale['id']]);
$items = $items_stmt->fetchAll();

// Payments
$pays_stmt = $db->prepare("SELECT * FROM sale_payments WHERE sale_id = :sid");
$pays_stmt->execute(['sid' => $sale['id']]);
$payments = $pays_stmt->fetchAll();

$paper_width = $_GET['paper'] ?? get_setting('receipt_printer_width', '80mm');
$is_58 = ($paper_width === '58mm');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= htmlspecialchars($sale['invoice_number']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <style>
        body {
            background-color: #F1F5F9;
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
        }
        .receipt-card {
            background: #FFFFFF;
            margin: 0 auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 16px;
            border-radius: 6px;
        }
        .width-80mm { width: 330px; }
        .width-58mm { width: 260px; font-size: 11px; }
        .dashed-line { border-top: 1px dashed #333; margin: 8px 0; }
        .barcode-box {
            font-family: 'Libre Barcode 39', monospace, sans-serif;
            font-size: 32px;
            letter-spacing: 2px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-primary btn-sm px-4 me-2">Print Receipt</button>
    <button onclick="window.close()" class="btn btn-secondary btn-sm px-3">Close</button>
</div>

<div class="receipt-card <?= $is_58 ? 'width-58mm receipt-container-58mm' : 'width-80mm receipt-container-80mm' ?>">
    <!-- Header -->
    <div class="receipt-header text-center">
        <h4 class="fw-bold mb-1" style="font-family: sans-serif;">Mart<span style="color:#16A34A;">Pro</span></h4>
        <div class="fw-bold"><?= htmlspecialchars($sale['store_name']) ?></div>
        <div class="small"><?= nl2br(htmlspecialchars($sale['receipt_header'] ?? $sale['store_address'])) ?></div>
        <div class="small">Tel: <?= htmlspecialchars($sale['store_phone']) ?></div>
    </div>

    <div class="dashed-line"></div>

    <!-- Metadata -->
    <table class="receipt-table small">
        <tr><td><strong>Invoice #:</strong></td><td class="text-end fw-bold"><?= htmlspecialchars($sale['invoice_number']) ?></td></tr>
        <tr><td><strong>Date:</strong></td><td class="text-end"><?= format_datetime($sale['sale_date']) ?></td></tr>
        <tr><td><strong>Register:</strong></td><td class="text-end"><?= htmlspecialchars($sale['register_name']) ?></td></tr>
        <tr><td><strong>Cashier:</strong></td><td class="text-end"><?= htmlspecialchars($sale['cashier_name']) ?></td></tr>
        <?php if (!empty($sale['customer_name'])): ?>
            <tr><td><strong>Customer:</strong></td><td class="text-end"><?= htmlspecialchars($sale['customer_name']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="dashed-line"></div>

    <!-- Line Items Table -->
    <table class="receipt-table">
        <thead>
            <tr style="border-bottom: 1px solid #000;">
                <th style="width: 50%;">Item</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-end" style="width: 15%;">Price</th>
                <th class="text-end" style="width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                        <small style="color: #666;"><?= htmlspecialchars($item['sku']) ?></small>
                    </td>
                    <td class="text-center align-top"><?= format_qty($item['quantity']) ?></td>
                    <td class="text-end align-top"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-end align-top fw-bold"><?= number_format((float)$item['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="dashed-line"></div>

    <!-- Financial Breakdown -->
    <table class="receipt-table small">
        <tr>
            <td>Subtotal:</td>
            <td class="text-end fw-bold"><?= format_money($sale['subtotal']) ?></td>
        </tr>
        <?php if ($sale['item_discount_total'] > 0 || $sale['bill_discount_total'] > 0): ?>
            <tr>
                <td>Total Discounts:</td>
                <td class="text-end text-danger">-<?= format_money($sale['item_discount_total'] + $sale['bill_discount_total']) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Sales Tax:</td>
            <td class="text-end"><?= format_money($sale['tax_total']) ?></td>
        </tr>
        <tr class="fw-bold fs-6" style="border-top: 1px solid #000; border-bottom: 1px solid #000;">
            <td class="py-1">GRAND TOTAL:</td>
            <td class="text-end py-1"><?= format_money($sale['grand_total']) ?></td>
        </tr>
    </table>

    <!-- Payment Tenders -->
    <table class="receipt-table small mt-2">
        <?php foreach ($payments as $p): ?>
            <tr>
                <td>Paid by <?= strtoupper(str_replace('_', ' ', $p['payment_method'])) ?>:</td>
                <td class="text-end fw-bold"><?= format_money($p['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($sale['change_due'] > 0): ?>
            <tr>
                <td>Change Returned:</td>
                <td class="text-end fw-bold"><?= format_money($sale['change_due']) ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($sale['customer_credit_amount'] > 0): ?>
            <tr>
                <td>Khata Credit Balance Owed:</td>
                <td class="text-end text-danger fw-bold"><?= format_money($sale['customer_credit_amount']) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="dashed-line"></div>

    <!-- Customer Loyalty Info -->
    <?php if (!empty($sale['customer_id'])): ?>
        <div class="text-center small mb-2">
            <div>Member Loyalty Points Balance: <strong><?= (int)$sale['loyalty_points'] ?> pts</strong></div>
        </div>
        <div class="dashed-line"></div>
    <?php endif; ?>

    <!-- Barcode for return scanning -->
    <div class="text-center my-2">
        <div class="fw-bold" style="letter-spacing: 3px; font-size: 14px;">*<?= htmlspecialchars($sale['invoice_number']) ?>*</div>
        <small style="font-size: 9px; color: #777;">Scan barcode for returns or inquiry</small>
    </div>

    <!-- Footer -->
    <div class="receipt-footer text-center small text-muted">
        <div><?= nl2br(htmlspecialchars($sale['receipt_footer'])) ?></div>
        <div class="mt-1" style="font-size: 9px;">Powered by MartPro Enterprise Retail POS</div>
    </div>
</div>

<script>
window.onload = function() {
    // Auto-trigger print dialog if requested
    <?php if (isset($_GET['autoprint'])): ?>
        window.print();
    <?php endif; ?>
};
</script>
</body>
</html>
