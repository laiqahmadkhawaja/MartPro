<?php
/**
 * MartPro Switch Active Store Session Handler
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/response.php';

require_login();
require_csrf_token();

$store_id = (int)($_POST['store_id'] ?? 1);
set_active_store($store_id);

json_success("Active store location switched.", ['active_store_id' => $store_id]);
