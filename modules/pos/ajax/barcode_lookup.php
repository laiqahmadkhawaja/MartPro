<?php
/**
 * MartPro POS Barcode Lookup & Variable Weight Parser
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/inventory_helpers.php';

require_login();

$barcode = trim($_POST['barcode'] ?? $_GET['barcode'] ?? '');
$store_id = current_store_id();

if (empty($barcode)) {
    json_error('Barcode is required.');
}

$db = get_db();

// 1. Check for multiplier syntax: "5*890123450002"
$multiplier = 1.0;
if (strpos($barcode, '*') !== false) {
    $parts = explode('*', $barcode, 2);
    if (is_numeric($parts[0]) && (float)$parts[0] > 0) {
        $multiplier = (float)$parts[0];
        $barcode = trim($parts[1]);
    }
}

// 2. Check for Scale Variable Weight Barcode (e.g. Starts with '20' prefix: 20 + 5-digit PLU + 5-digit Weight in Grams)
$scale_prefix = get_setting('scale_barcode_prefix', '20');
$is_scale_barcode = false;
$scale_weight = null;

if (strlen($barcode) === 13 && substr($barcode, 0, strlen($scale_prefix)) === $scale_prefix) {
    // Variable weight barcode format: [Prefix: 2] [Product Code: 5] [Weight in grams: 5] [Checksum: 1]
    $plu_code = substr($barcode, strlen($scale_prefix), 5);
    $weight_grams = (float)substr($barcode, strlen($scale_prefix) + 5, 5);
    $scale_weight = $weight_grams / 1000.0; // In Kg
    $is_scale_barcode = true;
    
    // Search product by barcode pattern or SKU
    $stmt = $db->prepare("SELECT p.*, tc.rate AS tax_rate, u.short_code AS unit_name, u.allow_decimal
                          FROM products p 
                          LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id 
                          JOIN product_units u ON p.unit_id = u.id
                          WHERE (p.primary_barcode LIKE :plu1 OR p.sku LIKE :plu2) AND p.is_active = 1 LIMIT 1");
    $stmt->execute(['plu1' => "%$plu_code%", 'plu2' => "%$plu_code%"]);
    $product = $stmt->fetch();
} else {
    // Standard Barcode Lookup
    $stmt = $db->prepare("SELECT p.*, tc.rate AS tax_rate, u.short_code AS unit_name, u.allow_decimal
                          FROM products p 
                          LEFT JOIN product_barcodes pb ON p.id = pb.product_id
                          LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id 
                          JOIN product_units u ON p.unit_id = u.id
                          WHERE (p.primary_barcode = :bc1 OR pb.barcode = :bc2 OR p.sku = :bc3) AND p.is_active = 1 LIMIT 1");
    $stmt->execute(['bc1' => $barcode, 'bc2' => $barcode, 'bc3' => $barcode]);
    $product = $stmt->fetch();
}

if (!$product) {
    json_error("Product not found for barcode: $barcode", [], 404);
}

// Get live stock in current store
$available_stock = get_available_stock((int)$product['id'], $store_id);

$qty = $is_scale_barcode ? $scale_weight : $multiplier;

json_success('Product identified successfully.', [
    'id'              => (int)$product['id'],
    'sku'             => $product['sku'],
    'name'            => $product['name'],
    'barcode'         => $product['primary_barcode'],
    'unit'            => $product['unit_name'],
    'allow_decimal'   => (bool)$product['allow_decimal'],
    'selling_price'   => (float)$product['selling_price'],
    'wholesale_price' => (float)($product['wholesale_price'] ?? $product['selling_price']),
    'member_price'    => (float)($product['member_price'] ?? $product['selling_price']),
    'tax_rate'        => (float)($product['tax_rate'] ?? 0),
    'stock'           => (float)$available_stock,
    'quantity'        => (float)$qty
]);
