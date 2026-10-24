<?php
/**
 * MartPro Reports CSV Export Utility
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
require_permission('reports.view');

$type = $_GET['report_type'] ?? 'sales';
$db = get_db();
$store_id = current_store_id();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=martpro_' . $type . '_report_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

if ($type === 'products') {
    fputcsv($output, ['Product Name', 'SKU', 'Units Sold', 'Total Revenue', 'Total COGS', 'Gross Profit']);
    $stmt = $db->prepare("SELECT si.product_name, si.sku, SUM(si.quantity) AS total_qty,
                                 SUM(si.line_total) AS total_rev,
                                 SUM(si.quantity * si.cost_price) AS total_cogs
                          FROM sale_items si
                          JOIN sales s ON si.sale_id = s.id
                          WHERE s.store_id = :store AND s.status = 'completed'
                          GROUP BY si.product_id, si.product_name, si.sku
                          ORDER BY total_rev DESC");
    $stmt->execute(['store' => $store_id]);
    while ($row = $stmt->fetch()) {
        $gp = (float)$row['total_rev'] - (float)$row['total_cogs'];
        fputcsv($output, [$row['product_name'], $row['sku'], $row['total_qty'], $row['total_rev'], $row['total_cogs'], $gp]);
    }
} else {
    fputcsv($output, ['Invoice Number', 'Date', 'Cashier', 'Customer', 'Subtotal', 'Tax', 'Grand Total', 'COGS', 'Gross Profit']);
    $stmt = $db->prepare("SELECT s.invoice_number, s.sale_date, u.full_name AS cashier, COALESCE(c.name, 'Walk-in') AS customer,
                                 s.subtotal, s.tax_total, s.grand_total, s.cogs_total
                          FROM sales s
                          JOIN users u ON s.cashier_id = u.id
                          LEFT JOIN customers c ON s.customer_id = c.id
                          WHERE s.store_id = :store AND s.status = 'completed'
                          ORDER BY s.id DESC");
    $stmt->execute(['store' => $store_id]);
    while ($row = $stmt->fetch()) {
        $gp = (float)$row['grand_total'] - (float)$row['tax_total'] - (float)$row['cogs_total'];
        fputcsv($output, [
            $row['invoice_number'],
            $row['sale_date'],
            $row['cashier'],
            $row['customer'],
            $row['subtotal'],
            $row['tax_total'],
            $row['grand_total'],
            $row['cogs_total'],
            $gp
        ]);
    }
}

fclose($output);
exit;
