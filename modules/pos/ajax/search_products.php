<?php
/**
 * MartPro POS Product Search AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/inventory_helpers.php';

require_login();

$term = trim($_GET['term'] ?? '');
$store_id = current_store_id();

if (strlen($term) < 1) {
    json_success('Empty search', []);
}

$db = get_db();
$stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.primary_barcode, p.selling_price, p.member_price, u.short_code AS unit_name, tc.rate AS tax_rate, COALESCE(sb.quantity, 0) AS stock_qty
                      FROM products p 
                      JOIN product_units u ON p.unit_id = u.id
                      LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
                      LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.store_id = :store
                      WHERE (p.name LIKE :q1 OR p.sku LIKE :q2 OR p.primary_barcode LIKE :q3) AND p.is_active = 1
                      ORDER BY p.name ASC LIMIT 12");
$stmt->execute(['store' => $store_id, 'q1' => "%$term%", 'q2' => "%$term%", 'q3' => "%$term%"]);
$results = $stmt->fetchAll();

json_success('Products retrieved', $results);
