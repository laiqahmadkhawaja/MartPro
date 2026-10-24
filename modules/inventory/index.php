<?php
/**
 * MartPro Inventory, Stock Balances & Batches Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('inventory.view');

$db = get_db();
$store_id = current_store_id();

// 1. Stock Valuation KPI
$val_stmt = $db->prepare("SELECT 
    COUNT(DISTINCT p.id) AS total_skus,
    COALESCE(SUM(sb.quantity), 0) AS total_units,
    COALESCE(SUM(sb.quantity * p.purchase_cost), 0) AS total_cost_valuation,
    COALESCE(SUM(sb.quantity * p.selling_price), 0) AS total_retail_valuation
    FROM products p
    JOIN stock_balances sb ON p.id = sb.product_id
    WHERE sb.store_id = :store AND p.is_active = 1");
$val_stmt->execute(['store' => $store_id]);
$val = $val_stmt->fetch();

// 2. Active Batches with Expiry Alerts
$batch_stmt = $db->prepare("SELECT b.*, p.name AS product_name, p.sku, u.short_code AS unit_name,
                                   DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry
                            FROM stock_batches b
                            JOIN products p ON b.product_id = p.id
                            JOIN product_units u ON p.unit_id = u.id
                            WHERE b.store_id = :store AND b.quantity_remaining > 0
                            ORDER BY (b.expiry_date IS NULL), b.expiry_date ASC");
$batch_stmt->execute(['store' => $store_id]);
$batches = $batch_stmt->fetchAll();

// 3. Stock Movement Ledger (Recent 15)
$mov_stmt = $db->prepare("SELECT sm.*, p.name AS product_name, p.sku, u.short_code AS unit_name, usr.full_name AS user_name
                          FROM stock_movements sm
                          JOIN products p ON sm.product_id = p.id
                          JOIN product_units u ON p.unit_id = u.id
                          LEFT JOIN users usr ON sm.user_id = usr.id
                          WHERE sm.store_id = :store
                          ORDER BY sm.id DESC LIMIT 15");
$mov_stmt->execute(['store' => $store_id]);
$movements = $mov_stmt->fetchAll();

$page_title = 'Inventory & Batches';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Inventory Management & Stock Ledger</h4>
        <p class="text-muted small mb-0">Multi-location stock balances, batch expiry tracking (FIFO/FEFO), and movement history</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/stock_adjustments/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-sliders me-1"></i> Stock Adjustment
        </a>
        <a href="<?= BASE_URL ?>/modules/stock_transfers/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-arrow-left-right me-1"></i> Stock Transfer
        </a>
        <a href="<?= BASE_URL ?>/modules/stock_counts/index.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-check2-square me-1"></i> Physical Count
        </a>
    </div>
</div>

<!-- Stock Valuation Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card p-3 border-0 shadow-sm">
            <span class="text-muted small fw-semibold">Active Catalog SKUs</span>
            <h3 class="fw-bold text-navy my-1"><?= number_format($val['total_skus']) ?></h3>
            <span class="small text-muted">Stock keeping units</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card p-3 border-0 shadow-sm">
            <span class="text-muted small fw-semibold">Total Stock Units</span>
            <h3 class="fw-bold text-navy my-1"><?= format_qty($val['total_units']) ?></h3>
            <span class="small text-muted">Items on hand</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card p-3 border-0 shadow-sm">
            <span class="text-muted small fw-semibold">Cost Valuation</span>
            <h3 class="fw-bold text-navy my-1"><?= format_money($val['total_cost_valuation']) ?></h3>
            <span class="small text-muted">Total inventory asset value</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card p-3 border-0 shadow-sm">
            <span class="text-muted small fw-semibold">Retail Valuation</span>
            <h3 class="fw-bold text-mp-primary my-1"><?= format_money($val['total_retail_valuation']) ?></h3>
            <span class="small text-success fw-semibold">Est. Margin: <?= format_money((float)$val['total_retail_valuation'] - (float)$val['total_cost_valuation']) ?></span>
        </div>
    </div>
</div>

<!-- Batch & Expiry Tracking Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold text-navy"><i class="bi bi-calendar-event me-2 text-primary"></i> Tracked Stock Batches & FEFO Expiry Status</span>
        <span class="badge bg-light text-dark border"><?= count($batches) ?> Active Batches</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Batch Number</th>
                    <th>Product</th>
                    <th>Remaining Qty</th>
                    <th>Unit Cost</th>
                    <th>Manufacture Date</th>
                    <th>Expiry Date</th>
                    <th>Expiry Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No active tracked batches in this store.</td></tr>
                <?php else: ?>
                    <?php foreach ($batches as $b): ?>
                        <tr>
                            <td class="fw-bold text-navy"><code><?= htmlspecialchars($b['batch_number']) ?></code></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($b['product_name']) ?></div>
                                <small class="text-muted">SKU: <?= htmlspecialchars($b['sku']) ?></small>
                            </td>
                            <td class="fw-bold"><?= format_qty($b['quantity_remaining']) ?> <?= htmlspecialchars($b['unit_name']) ?></td>
                            <td><?= format_money($b['unit_cost']) ?></td>
                            <td class="small"><?= format_date($b['manufacture_date']) ?></td>
                            <td class="small fw-semibold"><?= format_date($b['expiry_date']) ?></td>
                            <td>
                                <?php if ($b['expiry_date'] && $b['days_to_expiry'] <= 0): ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-octagon me-1"></i> Expired</span>
                                <?php elseif ($b['expiry_date'] && $b['days_to_expiry'] <= 30): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> <?= $b['days_to_expiry'] ?> days left</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">Good</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock Movement Ledger Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold text-navy"><i class="bi bi-journal-text me-2 text-mp-primary"></i> Stock Movements Audit Ledger</span>
        <span class="badge bg-light text-dark border">Immutable Movement Log</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Product</th>
                    <th>Movement Type</th>
                    <th>Qty In</th>
                    <th>Qty Out</th>
                    <th>Unit Cost</th>
                    <th>Reference</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No stock movements recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td class="small text-muted"><?= format_datetime($m['created_at']) ?></td>
                            <td>
                                <div class="fw-semibold text-navy"><?= htmlspecialchars($m['product_name']) ?></div>
                                <small class="text-muted">SKU: <?= htmlspecialchars($m['sku']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= MOVEMENT_TYPES[$m['movement_type']] ?? ucfirst($m['movement_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($m['quantity_in'] > 0): ?>
                                    <span class="text-success fw-bold">+<?= format_qty($m['quantity_in']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($m['quantity_out'] > 0): ?>
                                    <span class="text-danger fw-bold">-<?= format_qty($m['quantity_out']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= format_money($m['unit_cost']) ?></td>
                            <td class="small fw-semibold"><?= htmlspecialchars($m['reference_doc'] ?: '—') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($m['user_name'] ?: 'System') ?></td>
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
