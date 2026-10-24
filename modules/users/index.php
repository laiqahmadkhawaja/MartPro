<?php
/**
 * MartPro User Management & Role Administration Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('users.manage');

$db = get_db();

$users = $db->query("SELECT u.*, r.name AS role_name, r.slug AS role_slug, s.name AS store_name
                     FROM users u
                     JOIN roles r ON u.role_id = r.id
                     LEFT JOIN stores s ON u.default_store_id = s.id
                     ORDER BY u.id ASC")->fetchAll();

$roles = $db->query("SELECT id, name FROM roles ORDER BY id ASC")->fetchAll();
$stores = $db->query("SELECT id, name FROM stores WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

$page_title = 'Users & Roles';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">User Accounts & Role Permissions</h4>
        <p class="text-muted small mb-0">Manage staff access credentials, assign branch locations, and enforce role-based access controls</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" id="btn-add-user">
        <i class="bi bi-person-plus me-1"></i> Add New User
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>User Details</th>
                    <th>Role</th>
                    <th>Assigned Store</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-navy"><?= htmlspecialchars($u['full_name']) ?></div>
                            <small class="text-muted">@<?= htmlspecialchars($u['username']) ?> &bull; <?= htmlspecialchars($u['email']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-navy-secondary text-white"><?= htmlspecialchars($u['role_name']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($u['store_name'] ?: 'All Stores') ?></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= format_datetime($u['last_login_at']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-user" data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>' title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold" id="userModalLabel"><i class="bi bi-person-plus me-2"></i> Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-user">
                <?= csrf_token_field() ?>
                <input type="hidden" name="user_id" id="user-id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input type="text" name="full_name" id="user-fullname" class="form-control" required placeholder="e.g. John Cashier">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Username *</label>
                            <input type="text" name="username" id="user-name" class="form-control" required placeholder="john">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Email *</label>
                            <input type="email" name="email" id="user-email" class="form-control" required placeholder="john@martpro.store">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" id="pwd-label">Password *</label>
                        <input type="password" name="password" id="user-pwd" class="form-control" placeholder="••••••••">
                        <small class="text-muted" id="pwd-hint">Leave blank when editing to keep existing password.</small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Role *</label>
                            <select name="role_id" id="user-role" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Default Store</label>
                            <select name="default_store_id" id="user-store" class="form-select">
                                <?php foreach ($stores as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="user-active" value="1" checked>
                        <label class="form-check-label small" for="user-active">Account is Active & Permitted to Login</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-user"><i class="bi bi-save me-1"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#btn-add-user').on('click', function() {
    $('#form-user')[0].reset();
    $('#user-id').val('');
    $('#pwd-hint').hide();
    $('#userModalLabel').html('<i class=\"bi bi-person-plus me-2\"></i> Add New User');
});

$('.btn-edit-user').on('click', function() {
    const u = $(this).data('user');
    $('#user-id').val(u.id);
    $('#user-fullname').val(u.full_name);
    $('#user-name').val(u.username);
    $('#user-email').val(u.email);
    $('#user-role').val(u.role_id);
    $('#user-store').val(u.default_store_id || 1);
    $('#user-active').prop('checked', u.is_active == 1);
    $('#pwd-hint').show();
    $('#userModalLabel').html('<i class=\"bi bi-pencil-square me-2\"></i> Edit User: ' + u.username);
    $('#userModal').modal('show');
});

$('#form-user').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/users/ajax/save.php', {
        data: data,
        button: '#btn-save-user'
    }).then(function(res) {
        MartPro.notify('success', res.message);
        $('#userModal').modal('hide');
        setTimeout(() => window.location.reload(), 800);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
