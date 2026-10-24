<?php
defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
$curr_script = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<aside id="sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="<?= BASE_URL ?>/index.php" class="d-flex align-items-center">
            <img src="<?= BASE_URL ?>/assets/images/logo-dark.svg" alt="MartPro Logo" height="38">
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="py-2 flex-grow-1">
        <div class="nav-section-title">Main Operations</div>
        <a href="<?= BASE_URL ?>/index.php" class="sidebar-link <?= (strpos($curr_script, 'dashboard') !== false || substr($curr_script, -9) === 'index.php' && strpos($curr_script, 'modules') === false) ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/pos/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/pos') !== false) ? 'active' : '' ?>">
            <i class="bi bi-upc-scan text-bright-green"></i>
            <span class="fw-bold text-white">POS Terminal</span>
        </a>

        <div class="nav-section-title">Catalog & Stock</div>
        <a href="<?= BASE_URL ?>/modules/products/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/products') !== false) ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i>
            <span>Products Catalog</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/inventory') !== false) ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i>
            <span>Stock & Batches</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock_adjustments/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/stock_adjustments') !== false) ? 'active' : '' ?>">
            <i class="bi bi-sliders"></i>
            <span>Stock Adjustments</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock_transfers/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/stock_transfers') !== false) ? 'active' : '' ?>">
            <i class="bi bi-arrow-left-right"></i>
            <span>Stock Transfers</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/stock_counts/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/stock_counts') !== false) ? 'active' : '' ?>">
            <i class="bi bi-check2-square"></i>
            <span>Stocktake / Audit</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/barcodes/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/barcodes') !== false) ? 'active' : '' ?>">
            <i class="bi bi-printer"></i>
            <span>Print Labels</span>
        </a>

        <div class="nav-section-title">Purchasing</div>
        <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/suppliers') !== false) ? 'active' : '' ?>">
            <i class="bi bi-truck"></i>
            <span>Suppliers</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/purchase_orders/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/purchase_orders') !== false) ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i>
            <span>Purchase Orders</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/goods_receiving/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/goods_receiving') !== false) ? 'active' : '' ?>">
            <i class="bi bi-box-arrow-in-down"></i>
            <span>Goods Receiving (GRN)</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounts_payable/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/accounts_payable') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i>
            <span>Accounts Payable</span>
        </a>

        <div class="nav-section-title">Sales & CRM</div>
        <a href="<?= BASE_URL ?>/modules/sales/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/sales') !== false && strpos($curr_script, 'sales_returns') === false) ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i>
            <span>Sales History</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/sales_returns/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/sales_returns') !== false) ? 'active' : '' ?>">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Returns & Refunds</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/customers/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/customers') !== false) ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>Customers & Khata</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/loyalty/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/loyalty') !== false) ? 'active' : '' ?>">
            <i class="bi bi-stars"></i>
            <span>Loyalty Points</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/promotions/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/promotions') !== false) ? 'active' : '' ?>">
            <i class="bi bi-tag"></i>
            <span>Promotions</span>
        </a>

        <div class="nav-section-title">Cash & Financials</div>
        <a href="<?= BASE_URL ?>/modules/shifts/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/shifts') !== false) ? 'active' : '' ?>">
            <i class="bi bi-safe2"></i>
            <span>Shifts & X/Z Reports</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/cash_management/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/cash_management') !== false) ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i>
            <span>Safe & Cash Drops</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/expenses') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Store Expenses</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/accounting/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/accounting') !== false) ? 'active' : '' ?>">
            <i class="bi bi-journal-bookmark"></i>
            <span>General Ledger</span>
        </a>

        <div class="nav-section-title">System & Analytics</div>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/reports') !== false) ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Reports & Analytics</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/users') !== false) ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i>
            <span>Users & Roles</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/hardware/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/hardware') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cpu"></i>
            <span>Hardware Devices</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/settings/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/settings') !== false) ? 'active' : '' ?>">
            <i class="bi bi-sliders2"></i>
            <span>Settings</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/audit_logs/index.php" class="sidebar-link <?= (strpos($curr_script, 'modules/audit_logs') !== false) ? 'active' : '' ?>">
            <i class="bi bi-shield-check"></i>
            <span>Audit Trail</span>
        </a>
    </div>

    <!-- Sidebar Footer -->
    <div class="p-3 border-top border-secondary border-opacity-25 text-center text-muted small">
        <small>MartPro v<?= APP_VERSION ?></small>
    </div>
</aside>
