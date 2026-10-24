<?php
/**
 * MartPro Enterprise External Device / Handheld API
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/inventory_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = get_db();

switch ($action) {
    case 'ping':
        json_success('MartPro Server API Online', ['version' => APP_VERSION, 'server_time' => date('c')]);
        break;

    case 'lookup_product':
        $barcode = trim($_GET['barcode'] ?? '');
        if (empty($barcode)) {
            json_error('Barcode required.');
        }
        $stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.primary_barcode, p.selling_price, u.short_code AS unit 
                              FROM products p 
                              JOIN product_units u ON p.unit_id = u.id 
                              WHERE (p.primary_barcode = :bc1 OR p.sku = :bc2) AND p.is_active = 1 LIMIT 1");
        $stmt->execute(['bc1' => $barcode, 'bc2' => $barcode]);
        $prod = $stmt->fetch();
        if ($prod) {
            json_success('Product found', $prod);
        } else {
            json_error('Product not found in catalog.', [], 404);
        }
        break;

    default:
        json_error('Invalid or unspecified API endpoint action.', [], 400);
        break;
}
