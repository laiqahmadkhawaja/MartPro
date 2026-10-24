<?php
defined('MARTPRO_INIT') or require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db = get_db();
$store_id = current_store_id();

// Date range filters
$date_preset = $_GET['period'] ?? 'today';
$today = date('Y-m-d');

switch ($date_preset) {
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = $start_date;
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date = $today;
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = $today;
        break;
    case 'today':
    default:
        $start_date = $today;
        $end_date = $today;
        break;
}

// 1. Sales & Profit KPIs for selected period
$sales_stmt = $db->prepare("SELECT 
    COUNT(id) AS total_orders,
    COALESCE(SUM(grand_total), 0) AS total_sales,
    COALESCE(SUM(subtotal), 0) AS total_subtotal,
    COALESCE(SUM(tax_total), 0) AS total_tax,
    COALESCE(SUM(bill_discount_total + item_discount_total), 0) AS total_discounts,
    COALESCE(SUM(cogs_total), 0) AS total_cogs
    FROM sales 
    WHERE store_id = :store AND DATE(sale_date) BETWEEN :start_d AND :end_d AND status = 'completed'");
$sales_stmt->execute(['store' => $store_id, 'start_d' => $start_date, 'end_d' => $end_date]);
$sales_kpi = $sales_stmt->fetch();

$net_sales = max(0.0, (float)$sales_kpi['total_subtotal'] - (float)$sales_kpi['total_discounts']);
$gross_profit = $net_sales - (float)$sales_kpi['total_cogs'];
$margin_pct = ($net_sales > 0) ? ($gross_profit / $net_sales) * 100 : 0.0;
$avg_basket = ($sales_kpi['total_orders'] > 0) ? (float)$sales_kpi['total_sales'] / (int)$sales_kpi['total_orders'] : 0.0;

// 2. Outstanding Receivables (Khata) & Payables
$rec_stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE current_balance > 0");
$total_receivables = (float)$rec_stmt->fetchColumn();

$pay_stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE current_balance > 0");
$total_payables = (float)$pay_stmt->fetchColumn();

// 3. Low stock and Expired counts
$low_stock_stmt = $db->prepare("SELECT COUNT(*) 
    FROM products p 
    JOIN stock_balances sb ON p.id = sb.product_id 
    WHERE sb.store_id = :store AND sb.quantity <= p.reorder_level AND p.is_active = 1");
$low_stock_stmt->execute(['store' => $store_id]);
$low_stock_count = (int)$low_stock_stmt->fetchColumn();

$expired_stmt = $db->prepare("SELECT COUNT(*) FROM stock_batches WHERE store_id = :store AND expiry_date <= CURDATE() AND quantity_remaining > 0");
$expired_stmt->execute(['store' => $store_id]);
$expired_batch_count = (int)$expired_stmt->fetchColumn();

// 4. Low stock products list
$low_items_stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.reorder_level, sb.quantity, u.short_code 
    FROM products p 
    JOIN stock_balances sb ON p.id = sb.product_id 
    JOIN product_units u ON p.unit_id = u.id
    WHERE sb.store_id = :store AND sb.quantity <= p.reorder_level AND p.is_active = 1
    ORDER BY sb.quantity ASC LIMIT 5");
$low_items_stmt->execute(['store' => $store_id]);
$low_stock_items = $low_items_stmt->fetchAll();

// 5. Recent 6 Sales Transactions
$recent_sales_stmt = $db->prepare("SELECT s.invoice_number, s.sale_date, s.grand_total, s.payment_status, u.full_name AS cashier_name, COALESCE(c.name, 'Walk-in Customer') AS customer_name
    FROM sales s
    JOIN users u ON s.cashier_id = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.store_id = :store
    ORDER BY s.id DESC LIMIT 6");
$recent_sales_stmt->execute(['store' => $store_id]);
$recent_sales = $recent_sales_stmt->fetchAll();

// 6. Last 7 Days Sales Trend Data for Chart
$trend_stmt = $db->prepare("SELECT DATE(sale_date) AS s_date, COALESCE(SUM(grand_total), 0) AS daily_sales 
    FROM sales 
    WHERE store_id = :store AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status = 'completed'
    GROUP BY DATE(sale_date) ORDER BY s_date ASC");
$trend_stmt->execute(['store' => $store_id]);
$trend_data = $trend_stmt->fetchAll();

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($d));
    $found = false;
    foreach ($trend_data as $row) {
        if ($row['s_date'] === $d) {
            $chart_values[] = (float)$row['daily_sales'];
            $found = true;
            break;
        }
    }
    if (!$found) $chart_values[] = 0.0;
}

$page_title = 'Executive Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Store Performance Dashboard</h4>
        <p class="text-muted small mb-0">Overview for <strong><?= htmlspecialchars($current_store['name']) ?></strong></p>
    </div>
    
    <!-- Filter Bar -->
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group shadow-sm">
            <a href="?period=today" class="btn btn-sm <?= ($date_preset === 'today') ? 'btn-primary' : 'btn-outline-secondary' ?>">Today</a>
            <a href="?period=yesterday" class="btn btn-sm <?= ($date_preset === 'yesterday') ? 'btn-primary' : 'btn-outline-secondary' ?>">Yesterday</a>
            <a href="?period=week" class="btn btn-sm <?= ($date_preset === 'week') ? 'btn-primary' : 'btn-outline-secondary' ?>">7 Days</a>
            <a href="?period=month" class="btn btn-sm <?= ($date_preset === 'month') ? 'btn-primary' : 'btn-outline-secondary' ?>">This Month</a>
        </div>
        <a href="<?= BASE_URL ?>/modules/pos/index.php" class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm px-3">
            <i class="bi bi-upc-scan"></i> Open POS
        </a>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card kpi-card h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Net Sales</span>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
            <h3 class="fw-bold text-navy mb-1"><?= format_money($sales_kpi['total_sales']) ?></h3>
            <span class="small text-muted"><?= (int)$sales_kpi['total_orders'] ?> transactions</span>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card kpi-card h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Gross Profit</span>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-graph-up"></i>
                </div>
            </div>
            <h3 class="fw-bold text-navy mb-1"><?= format_money($gross_profit) ?></h3>
            <span class="small text-success fw-semibold"><i class="bi bi-percent"></i> <?= number_format($margin_pct, 1) ?>% margin</span>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card kpi-card kpi-info h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Average Basket</span>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-cart-check"></i>
                </div>
            </div>
            <h3 class="fw-bold text-navy mb-1"><?= format_money($avg_basket) ?></h3>
            <span class="small text-muted">Per customer checkout</span>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card kpi-card <?= $low_stock_count > 0 ? 'kpi-warning' : '' ?> h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Stock Alerts</span>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
            <h3 class="fw-bold text-navy mb-1"><?= $low_stock_count ?></h3>
            <span class="small text-danger fw-semibold"><?= $expired_batch_count ?> batches near/expired</span>
        </div>
    </div>
</div>

<!-- Secondary Financial Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-6">
        <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <div>
                <span class="text-muted small fw-semibold d-block">Outstanding Customer Credit (Khata)</span>
                <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_money($total_receivables) ?></h4>
            </div>
            <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-outline-danger btn-sm">View Khata</a>
        </div>
    </div>
    <div class="col-sm-6 col-lg-6">
        <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <div>
                <span class="text-muted small fw-semibold d-block">Outstanding Supplier Payables</span>
                <h4 class="fw-bold text-warning mb-0 mt-1"><?= format_money($total_payables) ?></h4>
            </div>
            <a href="<?= BASE_URL ?>/modules/accounts_payable/index.php" class="btn btn-outline-warning btn-sm">View Payables</a>
        </div>
    </div>
</div>

<!-- Charts and Recent Activity -->
<div class="row g-3 mb-4">
    <!-- 7 Day Sales Trend Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-navy fw-bold"><i class="bi bi-graph-up me-2 text-mp-primary"></i> 7-Day Revenue Trend</span>
                <span class="badge bg-light text-dark border">Daily Sales</span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 260px;">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Quick Action List -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-navy fw-bold"><i class="bi bi-bell me-2 text-warning"></i> Reorder Alerts</span>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="small fw-semibold">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($low_stock_items)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle text-success fs-2 mb-2 d-block"></i>
                        All inventory levels are above reorder thresholds!
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($low_stock_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <div class="fw-semibold text-navy small"><?= htmlspecialchars($item['name']) ?></div>
                                    <span class="text-muted" style="font-size: 0.75rem;">SKU: <?= htmlspecialchars($item['sku']) ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger"><?= format_qty($item['quantity']) ?> <?= htmlspecialchars($item['short_code']) ?></span>
                                    <div class="text-muted" style="font-size: 0.7rem;">Min: <?= format_qty($item['reorder_level']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Invoices Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="text-navy fw-bold"><i class="bi bi-receipt me-2 text-primary"></i> Recent Completed Sales</span>
        <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-sm btn-outline-secondary">View Sales History</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Date & Time</th>
                    <th>Cashier</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_sales)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No transactions recorded yet today. Click "POS Checkout" to begin selling!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td class="fw-bold text-navy"><?= htmlspecialchars($sale['invoice_number']) ?></td>
                            <td><?= htmlspecialchars($sale['customer_name']) ?></td>
                            <td class="small text-muted"><?= format_datetime($sale['sale_date']) ?></td>
                            <td class="small"><?= htmlspecialchars($sale['cashier_name']) ?></td>
                            <td class="fw-bold text-mp-primary"><?= format_money($sale['grand_total']) ?></td>
                            <td><span class="badge bg-success-subtle text-success"><?= ucfirst($sale['payment_status']) ?></span></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/print/receipt.php?invoice=<?= urlencode($sale['invoice_number']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Print Receipt">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$inline_js = "
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesTrendChart');
    if (ctx && window.Chart) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: " . json_encode($chart_labels) . ",
                datasets: [{
                    label: 'Gross Sales ($)',
                    data: " . json_encode($chart_values) . ",
                    borderColor: '#16A34A',
                    backgroundColor: 'rgba(22, 163, 74, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#16A34A',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
