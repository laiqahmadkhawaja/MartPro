<?php
/**
 * MartPro Audit Trail & Supervisory Activity Log Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('settings.manage');

$db = get_db();
$search_action = trim($_GET['action'] ?? '');
$search_module = trim($_GET['module'] ?? '');

$sql = "SELECT al.*, u.username, u.full_name 
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1";
$params = [];

if (!empty($search_action)) {
    $sql .= " AND al.action LIKE :act";
    $params['act'] = "%$search_action%";
}
if (!empty($search_module)) {
    $sql .= " AND al.module = :mod";
    $params['mod'] = $search_module;
}

$sql .= " ORDER BY al.id DESC LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$page_title = 'Audit Trail';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Centralized System Audit Trail</h4>
        <p class="text-muted small mb-0">Append-only compliance log recording logins, checkouts, voids, adjustments, and price overrides</p>
    </div>
</div>

<div class="card mb-4 border-0 shadow-sm p-3">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <input type="text" name="action" class="form-control" placeholder="Search action (e.g. sale_checkout, login, update)..." value="<?= htmlspecialchars($search_action) ?>">
        </div>
        <div class="col-md-4">
            <select name="module" class="form-select">
                <option value="">All Modules</option>
                <option value="pos" <?= ($search_module === 'pos') ? 'selected' : '' ?>>POS / Checkout</option>
                <option value="inventory" <?= ($search_module === 'inventory') ? 'selected' : '' ?>>Inventory & Stock</option>
                <option value="purchasing" <?= ($search_module === 'purchasing') ? 'selected' : '' ?>>Purchasing & GRN</option>
                <option value="auth" <?= ($search_module === 'auth') ? 'selected' : '' ?>>Authentication</option>
                <option value="settings" <?= ($search_module === 'settings') ? 'selected' : '' ?>>Settings</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-navy flex-fill"><i class="bi bi-filter"></i> Filter Logs</button>
            <?php if (!empty($search_action) || !empty($search_module)): ?>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details / Payload</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No audit entries found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-muted text-nowrap"><?= format_datetime($l['created_at']) ?></td>
                            <td class="fw-bold text-navy"><?= htmlspecialchars($l['username'] ?: 'System') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['module']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($l['action']) ?></span></td>
                            <td><?= htmlspecialchars($l['entity_type'] ? $l['entity_type'] . '#' . $l['entity_id'] : '—') ?></td>
                            <td class="text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($l['new_values'] ?? '') ?>">
                                <?= htmlspecialchars($l['new_values'] ?: '—') ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($l['ip_address']) ?></td>
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
