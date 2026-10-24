<?php
/**
 * MartPro Customer Management & Khata Credit Ledger Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('customers.manage');

$db = get_db();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND (name LIKE :q OR phone LIKE :q OR customer_code LIKE :q)";
    $params['q'] = "%$search%";
}
$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Total Khata Receivables
$tot_stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE current_balance > 0");
$total_receivables = (float)$tot_stmt->fetchColumn();

$page_title = 'Customers & Khata';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Customer CRM & Khata Ledger</h4>
        <p class="text-muted small mb-0">Manage customer profiles, membership loyalty tiers, and store credit (Khata) accounts</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/loyalty/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-stars me-1"></i> Loyalty Points Engine
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#customerModal" id="btn-add-cust">
            <i class="bi bi-person-plus me-1"></i> Add New Customer
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 border-0 shadow-sm border-start border-danger border-4">
            <span class="text-muted small fw-semibold">Total Customer Credit (Khata Receivables)</span>
            <h3 class="fw-bold text-danger my-1"><?= format_money($total_receivables) ?></h3>
            <span class="small text-muted">Outstanding credit balance owed to store</span>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Membership Tier</th>
                    <th>Loyalty Points</th>
                    <th>Credit Limit</th>
                    <th>Khata Balance Due</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No customer records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-navy"><?= htmlspecialchars($c['name']) ?></div>
                                <code class="small"><?= htmlspecialchars($c['customer_code']) ?></code>
                            </td>
                            <td><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($c['phone']) ?></td>
                            <td>
                                <?php
                                $tier_colors = [
                                    'standard' => 'bg-secondary',
                                    'silver'   => 'bg-info',
                                    'gold'     => 'bg-warning text-dark',
                                    'vip'      => 'bg-danger',
                                    'wholesale'=> 'bg-primary'
                                ];
                                $t_badge = $tier_colors[$c['membership_tier']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $t_badge ?>"><?= strtoupper($c['membership_tier']) ?></span>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= (int)$c['loyalty_points'] ?> pts</span></td>
                            <td><?= format_money($c['credit_limit']) ?></td>
                            <td>
                                <?php if ($c['current_balance'] > 0): ?>
                                    <span class="fw-bold text-danger"><?= format_money($c['current_balance']) ?></span>
                                <?php else: ?>
                                    <span class="text-success fw-semibold">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($c['current_balance'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-pay-khata" 
                                            data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-bal="<?= $c['current_balance'] ?>">
                                        <i class="bi bi-cash me-1"></i> Settle
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Khata Payment Modal -->
<div class="modal fade" id="khataPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i> Receive Khata Debt Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-khata-pay">
                <?= csrf_token_field() ?>
                <input type="hidden" name="customer_id" id="khata-cust-id">
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded-3 mb-3 text-center">
                        <span class="text-muted small fw-bold">Customer</span>
                        <h5 class="fw-bold text-navy mb-1" id="khata-cust-name">John Doe</h5>
                        <div class="text-danger fw-bold" id="khata-cust-bal">Balance Due: $0.00</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Amount ($) *</label>
                            <input type="number" step="0.01" name="amount" id="khata-pay-amount" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Register Cash</option>
                                <option value="card">Card / POS</option>
                                <option value="digital">Digital Wallet / QR</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Receipt Memo</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional payment note">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold" id="btn-submit-khata"><i class="bi bi-check2"></i> Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Customer Add Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i> Register New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-cust-create">
                <?= csrf_token_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Michael Chang">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" required placeholder="e.g. +1 555 345 6789">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="client@email.com">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Membership Tier</label>
                            <select name="membership_tier" class="form-select">
                                <option value="standard">Standard</option>
                                <option value="silver">Silver</option>
                                <option value="gold">Gold</option>
                                <option value="vip">VIP</option>
                                <option value="wholesale">Wholesale</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Credit Limit ($)</label>
                            <input type="number" step="50" name="credit_limit" class="form-control" value="200">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-cust"><i class="bi bi-save me-1"></i> Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('.btn-pay-khata').on('click', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    const bal = $(this).data('bal');
    $('#khata-cust-id').val(id);
    $('#khata-cust-name').text(name);
    $('#khata-cust-bal').text('Balance Due: $ ' + parseFloat(bal).toFixed(2));
    $('#khata-pay-amount').val(parseFloat(bal).toFixed(2));
    $('#khataPayModal').modal('show');
});

$('#form-khata-pay').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/customers/ajax/record_payment.php', {
        data: data,
        button: '#btn-submit-khata'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#khataPayModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});

$('#form-cust-create').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/save_customer.php', {
        data: data,
        button: '#btn-save-cust'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#customerModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
