<?php
/**
 * MartPro POS Customer Search AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/response.php';

require_login();

$term = trim($_GET['term'] ?? '');
$db = get_db();

if (empty($term)) {
    $stmt = $db->query("SELECT id, customer_code, name, phone, membership_tier, credit_limit, current_balance, loyalty_points 
                        FROM customers WHERE is_active = 1 ORDER BY id ASC LIMIT 10");
} else {
    $stmt = $db->prepare("SELECT id, customer_code, name, phone, membership_tier, credit_limit, current_balance, loyalty_points 
                          FROM customers 
                          WHERE (name LIKE :q1 OR phone LIKE :q2 OR customer_code LIKE :q3) AND is_active = 1 
                          ORDER BY name ASC LIMIT 15");
    $stmt->execute(['q1' => "%$term%", 'q2' => "%$term%", 'q3' => "%$term%"]);
}

$customers = $stmt->fetchAll();
json_success('Customers retrieved', $customers);
