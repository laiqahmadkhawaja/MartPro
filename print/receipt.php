<?php
/**
 * MartPro Thermal Receipt Printing Template (58mm / 80mm)
 * Optimized for Thermal Direct Heads:
 * - Monoline Sans-Serif (Arial / Segoe UI / Helvetica)
 * - Uniform Bold Weight (700)
 * - Pure #000000 Black (No Grays / No Colors)
 * - Anti-Aliasing Fringe Suppression
 * - Exact Printable Roll Widths (48mm for 58mm, 72mm for 80mm)
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
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: geometricPrecision;
            box-sizing: border-box;
        }
        body {
            background-color: #F1F5F9;
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif;
            font-weight: 700;
            color: #000000;
            padding: 20px;
        }
        .receipt-card {
            background: #FFFFFF;
            margin: 0 auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 10px;
            border-radius: 4px;
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif;
            font-weight: 700;
            color: #000000;
        }
        .receipt-card, .receipt-card * {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        /* Exact printable widths */
        .width-80mm {
            width: 72mm;
            max-width: 72mm;
            font-size: 12px;
            line-height: 1.25;
        }
        .width-58mm {
            width: 48mm;
            max-width: 48mm;
            font-size: 10px;
            line-height: 1.2;
        }
        .dashed-line {
            border-top: 1px dashed #000000 !important;
            margin: 6px 0;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: inherit;
        }
        .receipt-table th, .receipt-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        /* Hierarchy via font size only */
        .receipt-brand { font-size: 18px; line-height: 1.2; }
        .receipt-store { font-size: 14px; line-height: 1.2; }
        .receipt-grand-total { font-size: 15px; }
        .receipt-micro { font-size: 9px; line-height: 1.15; }

        .width-58mm .receipt-brand { font-size: 15px; }
        .width-58mm .receipt-store { font-size: 12px; }
        .width-58mm .receipt-grand-total { font-size: 13px; }
        .width-58mm .receipt-micro { font-size: 8px; }
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
        <div class="receipt-brand mb-1">MartPro</div>
        <div class="receipt-store"><?= htmlspecialchars($sale['store_name']) ?></div>
        <div class="receipt-micro"><?= nl2br(htmlspecialchars($sale['receipt_header'] ?? $sale['store_address'])) ?></div>
        <div class="receipt-micro">Tel: <?= htmlspecialchars($sale['store_phone']) ?></div>
    </div>

    <div class="dashed-line"></div>

    <!-- Metadata -->
    <table class="receipt-table">
        <tr><td>Invoice #:</td><td class="text-end"><?= htmlspecialchars($sale['invoice_number']) ?></td></tr>
        <tr><td>Date:</td><td class="text-end"><?= format_datetime($sale['sale_date']) ?></td></tr>
        <tr><td>Register:</td><td class="text-end"><?= htmlspecialchars($sale['register_name']) ?></td></tr>
        <tr><td>Cashier:</td><td class="text-end"><?= htmlspecialchars($sale['cashier_name']) ?></td></tr>
        <?php if (!empty($sale['customer_name'])): ?>
            <tr><td>Customer:</td><td class="text-end"><?= htmlspecialchars($sale['customer_name']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="dashed-line"></div>

    <!-- Line Items Table -->
    <table class="receipt-table">
        <thead>
            <tr style="border-bottom: 1px solid #000000;">
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
                        <div><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="receipt-micro"><?= htmlspecialchars($item['sku']) ?></div>
                    </td>
                    <td class="text-center align-top"><?= format_qty($item['quantity']) ?></td>
                    <td class="text-end align-top"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-end align-top"><?= number_format((float)$item['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="dashed-line"></div>

    <!-- Financial Breakdown -->
    <table class="receipt-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-end"><?= format_money($sale['subtotal']) ?></td>
        </tr>
        <?php if ($sale['item_discount_total'] > 0 || $sale['bill_discount_total'] > 0): ?>
            <tr>
                <td>Total Discounts:</td>
                <td class="text-end">-<?= format_money($sale['item_discount_total'] + $sale['bill_discount_total']) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Sales Tax:</td>
            <td class="text-end"><?= format_money($sale['tax_total']) ?></td>
        </tr>
        <tr class="receipt-grand-total" style="border-top: 1px solid #000000; border-bottom: 1px solid #000000;">
            <td class="py-1">GRAND TOTAL:</td>
            <td class="text-end py-1"><?= format_money($sale['grand_total']) ?></td>
        </tr>
    </table>

    <!-- Payment Tenders -->
    <table class="receipt-table mt-1">
        <?php foreach ($payments as $p): ?>
            <tr>
                <td>Paid by <?= strtoupper(str_replace('_', ' ', $p['payment_method'])) ?>:</td>
                <td class="text-end"><?= format_money($p['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($sale['change_due'] > 0): ?>
            <tr>
                <td>Change Returned:</td>
                <td class="text-end"><?= format_money($sale['change_due']) ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($sale['customer_credit_amount'] > 0): ?>
            <tr>
                <td>Khata Credit Balance:</td>
                <td class="text-end"><?= format_money($sale['customer_credit_amount']) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="dashed-line"></div>

    <!-- Customer Loyalty Info -->
    <?php if (!empty($sale['customer_id'])): ?>
        <div class="text-center mb-1">
            <div>Member Loyalty Points: <?= (int)$sale['loyalty_points'] ?> pts</div>
        </div>
        <div class="dashed-line"></div>
    <?php endif; ?>

    <!-- Barcode for return scanning -->
    <div class="text-center my-2">
        <div style="letter-spacing: 3px; font-size: 13px;">*<?= htmlspecialchars($sale['invoice_number']) ?>*</div>
        <div class="receipt-micro">Scan barcode for returns or inquiry</div>
    </div>

    <!-- Footer -->
    <div class="receipt-footer text-center">
        <div class="receipt-micro"><?= nl2br(htmlspecialchars($sale['receipt_footer'])) ?></div>
        <div class="receipt-micro mt-1">Powered by MartPro Enterprise Retail POS</div>
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
