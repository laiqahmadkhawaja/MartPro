<?php
/**
 * MartPro POS Quick Add Customer
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_csrf_token();

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$tier = $_POST['membership_tier'] ?? 'standard';
$credit_limit = (float)($_POST['credit_limit'] ?? 0);

if (empty($name) || empty($phone)) {
    json_error('Customer name and phone number are required.');
}

$db = get_db();

// Check unique phone
$check = $db->prepare("SELECT id FROM customers WHERE phone = :phone LIMIT 1");
$check->execute(['phone' => $phone]);
if ($check->fetch()) {
    json_error('A customer with this phone number already exists.');
}

$cust_code = 'CUST-' . date('y') . '-' . substr(uniqid(), -4);

$stmt = $db->prepare("INSERT INTO customers (customer_code, name, phone, email, membership_tier, credit_limit, current_balance, loyalty_points, is_active, created_at)
                      VALUES (:code, :name, :phone, :email, :tier, :clim, 0, 0, 1, NOW())");
$stmt->execute([
    'code'  => $cust_code,
    'name'  => $name,
    'phone' => $phone,
    'email' => !empty($email) ? $email : null,
    'tier'  => $tier,
    'clim'  => $credit_limit
]);

$cust_id = (int)$db->lastInsertId();

audit_log('create', 'customers', 'customer', (string)$cust_id, null, ['name' => $name, 'phone' => $phone]);

json_success('Customer added successfully.', [
    'id'              => $cust_id,
    'customer_code'   => $cust_code,
    'name'            => $name,
    'phone'           => $phone,
    'membership_tier' => $tier,
    'credit_limit'    => $credit_limit,
    'current_balance' => 0.0,
    'loyalty_points'  => 0
]);
