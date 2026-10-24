<?php
/**
 * MartPro Save Business Settings AJAX Endpoint
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/audit.php';

require_login();
require_permission('settings.manage');
require_csrf_token();

$settings = $_POST['settings'] ?? [];
if (!is_array($settings)) {
    json_error('Invalid settings payload.');
}

$db = get_db();
$stmt = $db->prepare("INSERT INTO business_settings (setting_key, setting_value, updated_at) 
                      VALUES (:key, :val, NOW())
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");

foreach ($settings as $key => $val) {
    $stmt->execute(['key' => $key, 'val' => trim($val)]);
}

audit_log('update_settings', 'settings', 'business_settings', 'global', null, $settings);

json_success('System configuration saved successfully.');
