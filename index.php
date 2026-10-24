<?php
/**
 * MartPro Enterprise Retail POS - Application Root
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
require_once __DIR__ . '/modules/dashboard/index.php';
