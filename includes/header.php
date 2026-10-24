<?php
defined('MARTPRO_INIT') or require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

require_login();
$user = current_user();
$current_store = get_current_store();
$all_stores = get_active_stores();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>MartPro Enterprise</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/favicon.svg">
    
    <!-- Locally Stored Bootstrap 5.3 & Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.css">
    
    <!-- Core MartPro CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css" media="print">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="<?= $extra_css ?>">
    <?php endif; ?>
</head>
<body>
<div id="app-wrapper">
    <?php if (!isset($hide_sidebar) || !$hide_sidebar): ?>
        <?php require_once __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>

    <div id="main-content" class="<?= (isset($hide_sidebar) && $hide_sidebar) ? 'm-0' : '' ?>">
        <?php if (!isset($hide_navbar) || !$hide_navbar): ?>
            <?php require_once __DIR__ . '/navbar.php'; ?>
        <?php endif; ?>
        <main class="p-3 p-md-4 flex-grow-1">
