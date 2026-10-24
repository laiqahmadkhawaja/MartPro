<?php
/**
 * MartPro Sales Transactions History Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db = get_db();
$store_id = current_store_id();

$search = trim($_GET['search'] ?? '');
$date_filter = trim($_GET['date'] ?? '');

$sql = "SELECT s.*, u.full_name AS cashier_name, COALESCE(c.name, 'Walk-in Retail Customer') AS customer_name,
               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) AS item_count
        FROM sales s
        JOIN users u ON s.cashier_id = u.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.store_id = :store";
$params = ['store' => $store_id];

if (!empty($search)) {
    $sql .= " AND (s.invoice_number LIKE :q OR c.name LIKE :q OR c.phone LIKE :q)";
    $params['q'] = "%$search%";
}
if (!empty($date_filter)) {
    $sql .= " AND DATE(s.sale_date) = :d";
    $params['d'] = $date_filter;
}

$sql .= " ORDER BY s.id DESC LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$page_title = 'Sales History';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Sales Transaction Registry</h4>
        <p class="text-muted small mb-0">Browse completed customer invoices, reprint thermal receipts, or initiate returns</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/sales_returns/index.php" class="btn btn-outline-danger btn-sm shadow-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Process Returns & Refunds
        </a>
        <a href="<?= BASE_URL ?>/modules/pos/index.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-upc-scan me-1"></i> POS Checkout
        </a>
    </div>
</div>

<!-- Search & Date Filter -->
<div class="card mb-4 border-0 shadow-sm p-3">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by Invoice # (e.g. INV-26-10001) or Customer..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-navy flex-fill"><i class="bi bi-filter"></i> Search</button>
            <?php if (!empty($search) || !empty($date_filter)): ?>
                <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Sales Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Items</th>
                    <th>Tax</th>
                    <th>Grand Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No sales invoices found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($s['invoice_number']) ?></code></td>
                            <td class="small text-muted"><?= format_datetime($s['sale_date']) ?></td>
                            <td><?= htmlspecialchars($s['customer_name']) ?></td>
                            <td class="small"><?= htmlspecialchars($s['cashier_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $s['item_count'] ?> items</span></td>
                            <td class="small"><?= format_money($s['tax_total']) ?></td>
                            <td class="fw-bold text-mp-primary"><?= format_money($s['grand_total']) ?></td>
                            <td>
                                <?php if ($s['status'] === 'refunded'): ?>
                                    <span class="badge bg-danger-subtle text-danger">Refunded</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">Completed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL ?>/print/receipt.php?invoice=<?= urlencode($s['invoice_number']) ?>" target="_blank" class="btn btn-outline-secondary" title="Reprint Receipt">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/modules/sales_returns/index.php?invoice=<?= urlencode($s['invoice_number']) ?>" class="btn btn-outline-danger" title="Process Return">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
