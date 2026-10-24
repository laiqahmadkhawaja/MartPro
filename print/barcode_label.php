<?php
/**
 * MartPro Shelf-Edge & Barcode Label Print View
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$product_id = (int)($_GET['product_id'] ?? 0);
$copies = max(1, min(100, (int)($_GET['copies'] ?? 1)));
$layout = $_GET['layout'] ?? 'shelf';
$show_price = !empty($_GET['show_price']);
$show_sku = !empty($_GET['show_sku']);
$show_store = !empty($_GET['show_store']);

$db = get_db();
$stmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $product_id]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Labels - <?= htmlspecialchars($product['name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; padding: 20px; }
        .label-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-start;
        }
        .barcode-label {
            background: #fff;
            border: 1px dashed #94a3b8;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            page-break-inside: avoid;
        }
        .layout-shelf {
            width: 220px;
            height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .layout-sticker {
            width: 180px;
            height: 95px;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            padding: 4px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .barcode-label { border: 1px solid #000; }
        }
    </style>
</head>
<body>

<div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-primary btn-sm px-4 me-2">Print Labels</button>
    <button onclick="window.close()" class="btn btn-secondary btn-sm px-3">Close</button>
</div>

<div class="label-grid">
    <?php for ($i = 0; $i < $copies; $i++): ?>
        <div class="barcode-label <?= ($layout === 'sticker') ? 'layout-sticker' : 'layout-shelf' ?>">
            <?php if ($show_store): ?>
                <div class="small fw-bold text-muted" style="font-size: 9px; text-transform: uppercase;">MartPro Supermarket</div>
            <?php endif; ?>

            <div class="fw-bold text-dark text-truncate" style="font-size: 11px;"><?= htmlspecialchars($product['name']) ?></div>

            <?php if ($show_price): ?>
                <div class="fw-bold text-success" style="font-size: 18px; line-height: 1;"><?= format_money($product['selling_price']) ?></div>
            <?php endif; ?>

            <!-- Barcode SVG / Pattern Representation -->
            <div style="font-family: monospace; letter-spacing: 2px; font-weight: bold; font-size: 14px; margin: 2px 0;">
                *<?= htmlspecialchars($product['primary_barcode']) ?>*
            </div>

            <?php if ($show_sku): ?>
                <div class="text-muted" style="font-size: 8px;">SKU: <?= htmlspecialchars($product['sku']) ?></div>
            <?php endif; ?>
        </div>
    <?php endfor; ?>
</div>

</body>
</html>
