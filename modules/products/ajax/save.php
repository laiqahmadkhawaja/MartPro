<?php
/**
 * MartPro Product Save/Update AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';
require_once __DIR__ . '/../../../includes/inventory_helpers.php';

require_login();
require_permission('products.manage');
require_csrf_token();

$id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$name = trim($_POST['name'] ?? '');
$sku = trim($_POST['sku'] ?? '');
$barcode = trim($_POST['primary_barcode'] ?? '');
$category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
$unit_id = (int)($_POST['unit_id'] ?? 1);
$tax_cat_id = !empty($_POST['tax_category_id']) ? (int)$_POST['tax_category_id'] : 1;
$purchase_cost = max(0.0, (float)($_POST['purchase_cost'] ?? 0));
$selling_price = max(0.0, (float)($_POST['selling_price'] ?? 0));
$wholesale_price = !empty($_POST['wholesale_price']) ? (float)$_POST['wholesale_price'] : null;
$reorder_level = max(0.0, (float)($_POST['reorder_level'] ?? 5));
$track_batches = !empty($_POST['track_batches']) ? 1 : 0;
$track_expiry = !empty($_POST['track_expiry']) ? 1 : 0;
$is_variable_weight = !empty($_POST['is_variable_weight']) ? 1 : 0;
$opening_stock = max(0.0, (float)($_POST['opening_stock'] ?? 0));

if (empty($name) || empty($sku) || empty($barcode)) {
    json_error('Product Name, SKU, and Primary Barcode are required.');
}

if ($selling_price < $purchase_cost) {
    // Warning or margin check
}

$db = get_db();

// Check uniqueness of SKU and Barcode
if ($id) {
    $check = $db->prepare("SELECT id FROM products WHERE (sku = :sku OR primary_barcode = :bc) AND id != :id LIMIT 1");
    $check->execute(['sku' => $sku, 'bc' => $barcode, 'id' => $id]);
} else {
    $check = $db->prepare("SELECT id FROM products WHERE sku = :sku OR primary_barcode = :bc LIMIT 1");
    $check->execute(['sku' => $sku, 'bc' => $barcode]);
}

if ($check->fetch()) {
    json_error('Another product with this SKU or Barcode already exists in the catalog.');
}

if ($id) {
    // Update existing product
    $stmt = $db->prepare("UPDATE products SET 
        name = :name, sku = :sku, primary_barcode = :bc, category_id = :cat, brand_id = :brand,
        unit_id = :unit, tax_category_id = :tax, purchase_cost = :pcost, selling_price = :sprice,
        wholesale_price = :wprice, reorder_level = :reorder, track_batches = :tbatch,
        track_expiry = :texp, is_variable_weight = :vweight, updated_at = NOW()
        WHERE id = :id");
    $stmt->execute([
        'name'    => $name,
        'sku'     => $sku,
        'bc'      => $barcode,
        'cat'     => $category_id,
        'brand'   => $brand_id,
        'unit'    => $unit_id,
        'tax'     => $tax_cat_id,
        'pcost'   => $purchase_cost,
        'sprice'  => $selling_price,
        'wprice'  => $wholesale_price,
        'reorder' => $reorder_level,
        'tbatch'  => $track_batches,
        'texp'    => $track_expiry,
        'vweight' => $is_variable_weight,
        'id'      => $id
    ]);

    audit_log('update', 'products', 'product', (string)$id, null, ['name' => $name, 'price' => $selling_price]);
    json_success('Product details updated successfully.');
} else {
    // Create new product
    $stmt = $db->prepare("INSERT INTO products 
        (name, sku, primary_barcode, category_id, brand_id, unit_id, tax_category_id,
         purchase_cost, selling_price, wholesale_price, reorder_level, track_batches,
         track_expiry, is_variable_weight, is_active, created_at)
        VALUES 
        (:name, :sku, :bc, :cat, :brand, :unit, :tax,
         :pcost, :sprice, :wprice, :reorder, :tbatch,
         :texp, :vweight, 1, NOW())");
    $stmt->execute([
        'name'    => $name,
        'sku'     => $sku,
        'bc'      => $barcode,
        'cat'     => $category_id,
        'brand'   => $brand_id,
        'unit'    => $unit_id,
        'tax'     => $tax_cat_id,
        'pcost'   => $purchase_cost,
        'sprice'  => $selling_price,
        'wprice'  => $wholesale_price,
        'reorder' => $reorder_level,
        'tbatch'  => $track_batches,
        'texp'    => $track_expiry,
        'vweight' => $is_variable_weight
    ]);

    $new_id = (int)$db->lastInsertId();

    // If opening stock specified, initialize in stock ledger
    if ($opening_stock > 0) {
        $store_id = current_store_id();
        record_stock_movement(
            $new_id,
            $store_id,
            'opening_stock',
            $opening_stock,
            0.0,
            $purchase_cost,
            'INIT-' . date('y'),
            null,
            'Initial Opening Stock on Product Creation'
        );
    }

    audit_log('create', 'products', 'product', (string)$new_id, null, ['name' => $name, 'sku' => $sku]);
    json_success('Product created successfully and added to catalog.');
}
