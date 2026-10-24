<?php
/**
 * MartPro Loyalty & Membership Engine
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('customers.manage');

$db = get_db();

// Fetch Loyalty Transactions Log
$tx_stmt = $db->query("SELECT lt.*, c.name AS customer_name, c.phone 
                       FROM loyalty_transactions lt
                       JOIN customers c ON lt.customer_id = c.id
                       ORDER BY lt.id DESC LIMIT 30");
$transactions = $tx_stmt->fetchAll();

// Top Loyalty Members
$top_stmt = $db->query("SELECT name, phone, membership_tier, loyalty_points 
                        FROM customers 
                        WHERE loyalty_points > 0 
                        ORDER BY loyalty_points DESC LIMIT 10");
$top_members = $top_stmt->fetchAll();

$page_title = 'Loyalty Engine';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Customer Loyalty & Reward Points</h4>
        <p class="text-muted small mb-0">Configure points earn rates, redemption rules, and track customer reward ledgers</p>
    </div>
</div>

<!-- Loyalty Program Config Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-primary border-4">
            <span class="text-muted small fw-semibold">Points Earning Rate</span>
            <h4 class="fw-bold text-navy my-1">1 Point per $10.00</h4>
            <span class="small text-muted">Eligible on all retail checkouts</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-success border-4">
            <span class="text-muted small fw-semibold">Redemption Conversion</span>
            <h4 class="fw-bold text-success my-1">100 Points = $10.00</h4>
            <span class="small text-muted">Instant credit deduction at checkout</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-info border-4">
            <span class="text-muted small fw-semibold">Active Loyalty Accounts</span>
            <h4 class="fw-bold text-navy my-1"><?= count($top_members) ?></h4>
            <span class="small text-muted">Members currently holding points</span>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Loyalty Members Table -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <span class="fw-bold text-navy"><i class="bi bi-trophy me-2 text-warning"></i> Top Loyalty Members</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Tier</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_members)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No points issued yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($top_members as $tm): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-navy"><?= htmlspecialchars($tm['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($tm['phone']) ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= strtoupper($tm['membership_tier']) ?></span></td>
                                    <td class="text-end fw-bold text-mp-primary"><?= (int)$tm['loyalty_points'] ?> pts</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Points Movement Log -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <span class="fw-bold text-navy"><i class="bi bi-clock-history me-2 text-primary"></i> Points Activity Ledger</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Memo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No points transactions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td class="small text-muted"><?= format_datetime($tx['created_at']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($tx['customer_name']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($tx['type'] === 'earned'): ?>
                                            <span class="badge bg-success-subtle text-success">Earned</span>
                                        <?php elseif ($tx['type'] === 'redeemed'): ?>
                                            <span class="badge bg-danger-subtle text-danger">Redeemed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= ucfirst($tx['type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold <?= $tx['type'] === 'earned' ? 'text-success' : 'text-danger' ?>">
                                        <?= ($tx['type'] === 'earned' ? '+' : '-') . abs((int)$tx['points']) ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($tx['notes'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
