<?php
/**
 * MartPro Supplier Management Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('purchasing.manage');

$db = get_db();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM suppliers WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND (name LIKE :q OR contact_person LIKE :q OR phone LIKE :q OR supplier_code LIKE :q)";
    $params['q'] = "%$search%";
}
$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

$page_title = 'Suppliers';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">Supplier Directory & Accounts</h4>
        <p class="text-muted small mb-0">Manage wholesale vendors, credit agreements, terms, and purchase liabilities</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/accounts_payable/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-cash-coin me-1"></i> Accounts Payable
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" id="btn-add-supplier">
            <i class="bi bi-plus-circle me-1"></i> Add Supplier
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Supplier Info</th>
                    <th>Contact Person</th>
                    <th>Phone / Email</th>
                    <th>Payment Terms</th>
                    <th>Credit Limit</th>
                    <th>Outstanding Payable</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No suppliers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-navy"><?= htmlspecialchars($s['name']) ?></div>
                                <code class="small"><?= htmlspecialchars($s['supplier_code']) ?></code>
                            </td>
                            <td><?= htmlspecialchars($s['contact_person'] ?: '—') ?></td>
                            <td>
                                <div><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($s['phone']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($s['email'] ?: '—') ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['payment_terms']) ?></span></td>
                            <td><?= format_money($s['credit_limit']) ?></td>
                            <td>
                                <?php if ($s['current_balance'] > 0): ?>
                                    <span class="fw-bold text-danger"><?= format_money($s['current_balance']) ?></span>
                                <?php else: ?>
                                    <span class="text-success fw-semibold">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-sup" data-supplier='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="<?= BASE_URL ?>/modules/purchase_orders/index.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Create PO">
                                    <i class="bi bi-file-earmark-plus"></i> PO
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold" id="supplierModalLabel"><i class="bi bi-truck me-2"></i> Add New Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-supplier">
                <?= csrf_token_field() ?>
                <input type="hidden" name="supplier_id" id="sup-id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Supplier Company Name *</label>
                            <input type="text" name="name" id="sup-name" class="form-control" required placeholder="e.g. Metro Wholesale Distributors">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Person</label>
                            <input type="text" name="contact_person" id="sup-cp" class="form-control" placeholder="e.g. David Miller">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number *</label>
                            <input type="tel" name="phone" id="sup-phone" class="form-control" required placeholder="+1 555 789 0123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" id="sup-email" class="form-control" placeholder="orders@supplier.com">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Payment Terms</label>
                            <select name="payment_terms" id="sup-terms" class="form-select">
                                <option value="Due on Receipt">Due on Receipt</option>
                                <option value="Net 15">Net 15 Days</option>
                                <option value="Net 30">Net 30 Days</option>
                                <option value="Net 60">Net 60 Days</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Credit Limit ($)</label>
                            <input type="number" step="100" name="credit_limit" id="sup-clim" class="form-control" value="10000">
                        </div>
                        <div class="col-md-4" id="opening-balance-block">
                            <label class="form-label small fw-semibold">Opening Payable Balance ($)</label>
                            <input type="number" step="0.01" name="opening_balance" id="sup-opbal" class="form-control" value="0.00">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Warehouse / Dispatch Address</label>
                            <textarea name="address" id="sup-addr" class="form-control" rows="2" placeholder="Full street address..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-sup"><i class="bi bi-save me-1"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#btn-add-supplier').on('click', function() {
    $('#form-supplier')[0].reset();
    $('#sup-id').val('');
    $('#opening-balance-block').show();
    $('#supplierModalLabel').html('<i class=\"bi bi-truck me-2\"></i> Add New Supplier');
});

$('.btn-edit-sup').on('click', function() {
    const s = $(this).data('supplier');
    $('#sup-id').val(s.id);
    $('#sup-name').val(s.name);
    $('#sup-cp').val(s.contact_person || '');
    $('#sup-phone').val(s.phone);
    $('#sup-email').val(s.email || '');
    $('#sup-terms').val(s.payment_terms);
    $('#sup-clim').val(s.credit_limit);
    $('#sup-addr').val(s.address || '');
    $('#opening-balance-block').hide();
    $('#supplierModalLabel').html('<i class=\"bi bi-pencil-square me-2\"></i> Edit Supplier: ' + s.name);
    $('#supplierModal').modal('show');
});

$('#form-supplier').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/suppliers/ajax/save.php', {
        data: data,
        button: '#btn-save-sup'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#supplierModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
