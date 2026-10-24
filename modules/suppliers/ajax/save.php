<?php
/**
 * MartPro Supplier Save/Update AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('purchasing.manage');
require_csrf_token();

$id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
$name = trim($_POST['name'] ?? '');
$contact_person = trim($_POST['contact_person'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$tax_number = trim($_POST['tax_number'] ?? '');
$payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');
$credit_limit = max(0.0, (float)($_POST['credit_limit'] ?? 0));
$opening_balance = max(0.0, (float)($_POST['opening_balance'] ?? 0));

if (empty($name) || empty($phone)) {
    json_error('Supplier Name and Contact Phone are required.');
}

$db = get_db();

if ($id) {
    $stmt = $db->prepare("UPDATE suppliers SET 
        name = :name, contact_person = :cp, phone = :phone, email = :email,
        address = :addr, tax_number = :tax, payment_terms = :terms, credit_limit = :clim,
        updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        'name'  => $name,
        'cp'    => $contact_person,
        'phone' => $phone,
        'email' => $email,
        'addr'  => $address,
        'tax'   => $tax_number,
        'terms' => $payment_terms,
        'clim'  => $credit_limit,
        'id'    => $id
    ]);
    audit_log('update', 'suppliers', 'supplier', (string)$id, null, ['name' => $name]);
    json_success('Supplier details updated successfully.');
} else {
    $code = 'SUP-' . date('y') . '-' . substr(uniqid(), -4);
    $stmt = $db->prepare("INSERT INTO suppliers 
        (supplier_code, name, contact_person, phone, email, address, tax_number, payment_terms, credit_limit, opening_balance, current_balance, is_active, created_at)
        VALUES 
        (:code, :name, :cp, :phone, :email, :addr, :tax, :terms, :clim, :op_bal, :curr_bal, 1, NOW())");
    $stmt->execute([
        'code'     => $code,
        'name'     => $name,
        'cp'       => $contact_person,
        'phone'    => $phone,
        'email'    => $email,
        'addr'     => $address,
        'tax'      => $tax_number,
        'terms'    => $payment_terms,
        'clim'     => $credit_limit,
        'op_bal'   => $opening_balance,
        'curr_bal' => $opening_balance
    ]);
    $new_id = (int)$db->lastInsertId();
    audit_log('create', 'suppliers', 'supplier', (string)$new_id, null, ['name' => $name, 'code' => $code]);
    json_success('Supplier added successfully.', ['id' => $new_id]);
}
