<?php
/**
 * MartPro Customer-Facing Secondary Display Screen
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MartPro Customer Display</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.css">
    <style>
        body {
            background-color: #0F172A;
            color: #FFFFFF;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem;
        }
        .customer-banner {
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 1.5rem;
        }
        .big-total-card {
            background: #162338;
            border: 2px solid #16A34A;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: right;
        }
        .total-amount-display {
            font-size: 5rem;
            font-weight: 900;
            color: #39E75F;
            font-family: monospace;
            line-height: 1;
        }
    </style>
</head>
<body>

<div class="customer-banner d-flex justify-content-between align-items-center">
    <img src="<?= BASE_URL ?>/assets/images/logo-dark.svg" alt="MartPro Logo" height="55">
    <div class="text-end">
        <h4 class="fw-bold mb-0 text-white">Welcome to MartPro</h4>
        <small class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Register Online</small>
    </div>
</div>

<div class="row align-items-center my-auto">
    <div class="col-lg-7">
        <div class="p-4 rounded-4 bg-navy-secondary border border-secondary border-opacity-25">
            <span class="text-uppercase small fw-bold text-success mb-2 d-block">Current Item Scanned</span>
            <h2 class="fw-bold text-white mb-2" id="cust-item-name">Welcome to MartPro</h2>
            <div class="fs-4 text-light opacity-75">Quantity: <span id="cust-item-qty">0</span> &bull; Price: <span id="cust-item-price">$ 0.00</span></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="big-total-card shadow-lg">
            <div class="text-uppercase small fw-bold text-light opacity-75 mb-2">Total Amount Payable</div>
            <div class="total-amount-display" id="cust-total-due">$ 0.00</div>
        </div>
    </div>
</div>

<div class="text-center text-muted small border-top border-secondary border-opacity-25 pt-3">
    <span>Smart Store. Better Business. &bull; Thank you for shopping at MartPro Supermarkets!</span>
</div>

<script>
    // Live synchronization with POS Terminal via Web BroadcastChannel API
    const displayChannel = window.BroadcastChannel ? new BroadcastChannel('martpro_pos_display') : null;
    if (displayChannel) {
        displayChannel.onmessage = function(e) {
            const d = e.data || {};
            if (d.item) {
                document.getElementById('cust-item-name').innerText = d.item.name || '';
                document.getElementById('cust-item-qty').innerText = d.item.qty || 1;
                document.getElementById('cust-item-price').innerText = '$ ' + parseFloat(d.item.price || 0).toFixed(2);
            } else {
                document.getElementById('cust-item-name').innerText = 'Welcome to MartPro';
                document.getElementById('cust-item-qty').innerText = '0';
                document.getElementById('cust-item-price').innerText = '$ 0.00';
            }
            document.getElementById('cust-total-due').innerText = '$ ' + parseFloat(d.grandTotal || 0).toFixed(2);
        };
    }
</script>
</body>
</html>
