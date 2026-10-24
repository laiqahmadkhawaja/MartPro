<?php
/**
 * MartPro Enterprise Reports & Analytics Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('reports.view');

$db = get_db();
$store_id = current_store_id();

$tab = $_GET['tab'] ?? 'sales';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. Sales & Profit Breakdown
$sales_stmt = $db->prepare("SELECT s.*, u.full_name AS cashier_name, COALESCE(c.name, 'Walk-in') AS customer_name
                            FROM sales s
                            JOIN users u ON s.cashier_id = u.id
                            LEFT JOIN customers c ON s.customer_id = c.id
                            WHERE s.store_id = :store AND DATE(s.sale_date) BETWEEN :s_d AND :e_d AND s.status = 'completed'
                            ORDER BY s.id DESC");
$sales_stmt->execute(['store' => $store_id, 's_d' => $start_date, 'e_d' => $end_date]);
$sales_report = $sales_stmt->fetchAll();

// 2. Product Performance
$prod_stmt = $db->prepare("SELECT si.product_id, si.product_name, si.sku, 
                                  SUM(si.quantity) AS units_sold,
                                  SUM(si.line_total) AS total_revenue,
                                  SUM(si.quantity * si.cost_price) AS total_cost
                           FROM sale_items si
                           JOIN sales s ON si.sale_id = s.id
                           WHERE s.store_id = :store AND DATE(s.sale_date) BETWEEN :s_d AND :e_d AND s.status = 'completed'
                           GROUP BY si.product_id, si.product_name, si.sku
                           ORDER BY units_sold DESC LIMIT 30");
$prod_stmt->execute(['store' => $store_id, 's_d' => $start_date, 'e_d' => $end_date]);
$product_report = $prod_stmt->fetchAll();

// 3. Cashier Performance
$cashier_stmt = $db->prepare("SELECT u.full_name, COUNT(s.id) AS tx_count,
                                     SUM(s.grand_total) AS total_sales,
                                     SUM(s.tax_total) AS total_tax,
                                     SUM(s.bill_discount_total + s.item_discount_total) AS total_discounts
                              FROM users u
                              JOIN sales s ON u.id = s.cashier_id
                              WHERE s.store_id = :store AND DATE(s.sale_date) BETWEEN :s_d AND :e_d AND s.status = 'completed'
                              GROUP BY u.id, u.full_name");
$cashier_stmt->execute(['store' => $store_id, 's_d' => $start_date, 'e_d' => $end_date]);
$cashier_report = $cashier_stmt->fetchAll();

$page_title = 'Reports & Analytics';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Business Analytics & Operational Reports</h4>
        <p class="text-muted small mb-0">Financial sales profitability, cashier performance audits, and product sales velocity</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/reports/ajax/export.php?report_type=<?= urlencode($tab) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export to CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm shadow-sm">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm p-3 mb-4 no-print">
    <form method="GET" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="col-sm-6 col-md-4">
            <label class="form-label small fw-semibold">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="col-sm-6 col-md-4">
            <label class="form-label small fw-semibold">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-navy w-100 py-2"><i class="bi bi-filter me-1"></i> Apply Filter</button>
        </div>
    </form>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-pills mb-3 no-print">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'sales' ? 'active bg-navy' : '' ?>" href="?tab=sales&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
            <i class="bi bi-receipt me-1"></i> Sales & Profitability
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'products' ? 'active bg-navy' : '' ?>" href="?tab=products&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
            <i class="bi bi-box-seam me-1"></i> Top Selling Products
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'cashiers' ? 'active bg-navy' : '' ?>" href="?tab=cashiers&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
            <i class="bi bi-person-badge me-1"></i> Cashier Audits
        </a>
    </li>
</ul>

<!-- Report Contents -->
<?php if ($tab === 'sales'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <span class="fw-bold text-navy">Sales & Margin Report (<?= format_date($start_date) ?> to <?= format_date($end_date) ?>)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Revenue</th>
                        <th>Recorded COGS</th>
                        <th>Gross Profit</th>
                        <th>Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_report)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No sales recorded for this date range.</td></tr>
                    <?php else: ?>
                        <?php 
                        $t_rev = 0; $t_cogs = 0; $t_gp = 0;
                        foreach ($sales_report as $sr): 
                            $gp = (float)$sr['grand_total'] - (float)$sr['tax_total'] - (float)$sr['cogs_total'];
                            $m_pct = ((float)$sr['grand_total'] > 0) ? ($gp / (float)$sr['grand_total']) * 100 : 0;
                            $t_rev += (float)$sr['grand_total'];
                            $t_cogs += (float)$sr['cogs_total'];
                            $t_gp += $gp;
                        ?>
                            <tr>
                                <td class="fw-bold text-navy"><code><?= htmlspecialchars($sr['invoice_number']) ?></code></td>
                                <td class="small text-muted"><?= format_datetime($sr['sale_date']) ?></td>
                                <td><?= htmlspecialchars($sr['customer_name']) ?></td>
                                <td class="small"><?= htmlspecialchars($sr['cashier_name']) ?></td>
                                <td class="fw-bold text-navy"><?= format_money($sr['grand_total']) ?></td>
                                <td class="small text-muted"><?= format_money($sr['cogs_total']) ?></td>
                                <td class="fw-bold text-success"><?= format_money($gp) ?></td>
                                <td><span class="badge bg-success-subtle text-success"><?= number_format($m_pct, 1) ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4">PERIOD TOTALS:</td>
                            <td class="text-navy"><?= format_money($t_rev) ?></td>
                            <td class="text-muted"><?= format_money($t_cogs) ?></td>
                            <td class="text-success"><?= format_money($t_gp) ?></td>
                            <td><?= $t_rev > 0 ? number_format(($t_gp / $t_rev) * 100, 1) : 0 ?>%</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($tab === 'products'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <span class="fw-bold text-navy">Top Product Sales Velocity</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Units Sold</th>
                        <th>Gross Revenue</th>
                        <th>Total Cost</th>
                        <th>Gross Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($product_report)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No product sales in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($product_report as $pr): 
                            $gp = (float)$pr['total_revenue'] - (float)$pr['total_cost'];
                        ?>
                            <tr>
                                <td class="fw-bold text-navy"><?= htmlspecialchars($pr['product_name']) ?></td>
                                <td class="small text-muted"><code><?= htmlspecialchars($pr['sku']) ?></code></td>
                                <td><span class="badge bg-light text-dark border"><?= format_qty($pr['units_sold']) ?></span></td>
                                <td class="fw-bold text-navy"><?= format_money($pr['total_revenue']) ?></td>
                                <td class="small text-muted"><?= format_money($pr['total_cost']) ?></td>
                                <td class="fw-bold text-success"><?= format_money($gp) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <span class="fw-bold text-navy">Cashier Operational Performance</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Cashier Name</th>
                        <th>Completed Checkouts</th>
                        <th>Total Sales Generated</th>
                        <th>Taxes Handled</th>
                        <th>Discounts Granted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cashier_report)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No cashier activity recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cashier_report as $cr): ?>
                            <tr>
                                <td class="fw-bold text-navy"><?= htmlspecialchars($cr['full_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= $cr['tx_count'] ?> sales</span></td>
                                <td class="fw-bold text-mp-primary"><?= format_money($cr['total_sales']) ?></td>
                                <td class="small"><?= format_money($cr['total_tax']) ?></td>
                                <td class="small text-danger"><?= format_money($cr['total_discounts']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
