<?php
/**
 * MartPro Product Catalog CSV Export
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
require_permission('products.manage');

$db = get_db();
$store_id = current_store_id();

$stmt = $db->prepare("SELECT p.id, p.sku, p.name, p.primary_barcode, c.name AS category_name, b.name AS brand_name,
                             u.short_code AS unit_name, p.purchase_cost, p.selling_price, p.wholesale_price,
                             p.reorder_level, COALESCE(sb.quantity, 0) AS stock_quantity,
                             IF(p.is_active=1, 'Active', 'Inactive') AS status
                      FROM products p
                      LEFT JOIN product_categories c ON p.category_id = c.id
                      LEFT JOIN product_brands b ON p.brand_id = b.id
                      JOIN product_units u ON p.unit_id = u.id
                      LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.store_id = :store
                      ORDER BY p.id ASC");
$stmt->execute(['store' => $store_id]);
$products = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=martpro_products_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'SKU', 'Product Name', 'Barcode', 'Category', 'Brand', 'Unit', 'Purchase Cost', 'Selling Price', 'Wholesale Price', 'Reorder Level', 'Current Stock', 'Status']);

foreach ($products as $row) {
    fputcsv($output, [
        $row['id'],
        $row['sku'],
        $row['name'],
        $row['primary_barcode'],
        $row['category_name'] ?: 'Uncategorized',
        $row['brand_name'] ?: 'None',
        $row['unit_name'],
        $row['purchase_cost'],
        $row['selling_price'],
        $row['wholesale_price'] ?: '',
        $row['reorder_level'],
        $row['stock_quantity'],
        $row['status']
    ]);
}
fclose($output);
exit;
