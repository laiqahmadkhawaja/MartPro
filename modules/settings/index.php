<?php
/**
 * MartPro System Settings & Configuration Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('settings.manage');

$db = get_db();
$settings_rows = $db->query("SELECT setting_key, setting_value FROM business_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = 'Settings';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">System Settings & Store Preferences</h4>
        <p class="text-muted small mb-0">Configure enterprise currency, inventory rules, POS checkout controls, and thermal receipt layouts</p>
    </div>
</div>

<form id="form-settings">
    <?= csrf_token_field() ?>
    <div class="row g-4">
        <!-- 1. General & Currency -->
        <div class="col-lg-6">
            <div class="card p-4 border-0 shadow-sm h-100">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-gear me-2 text-primary"></i> General Store Identity</h5>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Application Brand Name</label>
                    <input type="text" name="settings[app_name]" class="form-control" value="<?= htmlspecialchars($settings_rows['app_name'] ?? 'MartPro') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Store Tagline</label>
                    <input type="text" name="settings[app_tagline]" class="form-control" value="<?= htmlspecialchars($settings_rows['app_tagline'] ?? 'Smart Store. Better Business.') ?>">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Currency Symbol</label>
                        <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= htmlspecialchars($settings_rows['currency_symbol'] ?? '$') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Currency Code</label>
                        <input type="text" name="settings[currency_code]" class="form-control" value="<?= htmlspecialchars($settings_rows['currency_code'] ?? 'USD') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Timezone</label>
                    <input type="text" name="settings[timezone]" class="form-control" value="<?= htmlspecialchars($settings_rows['timezone'] ?? 'America/New_York') ?>">
                </div>
            </div>
        </div>

        <!-- 2. POS Policies & Guardrails -->
        <div class="col-lg-6">
            <div class="card p-4 border-0 shadow-sm h-100">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-shield-check me-2 text-success"></i> POS Guardrails & Inventory Policies</h5>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Negative Stock Selling Policy</label>
                    <select name="settings[allow_negative_stock]" class="form-select">
                        <option value="0" <?= (($settings_rows['allow_negative_stock'] ?? '0') === '0') ? 'selected' : '' ?>>Disallow Selling When Stock Out (Strict Zero Floor)</option>
                        <option value="1" <?= (($settings_rows['allow_negative_stock'] ?? '0') === '1') ? 'selected' : '' ?>>Allow Negative Inventory (Backorder Mode)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Manual Price Override Permission</label>
                    <select name="settings[allow_manual_price_override]" class="form-select">
                        <option value="1" <?= (($settings_rows['allow_manual_price_override'] ?? '1') === '1') ? 'selected' : '' ?>>Enabled for Authorized Cashiers/Managers</option>
                        <option value="0" <?= (($settings_rows['allow_manual_price_override'] ?? '1') === '0') ? 'selected' : '' ?>>Strictly Locked to Catalog Master Price</option>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Max Cashier Discount (%)</label>
                        <input type="number" step="1" name="settings[max_cashier_discount_percent]" class="form-control" value="<?= htmlspecialchars($settings_rows['max_cashier_discount_percent'] ?? '15.00') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Scale Barcode Prefix</label>
                        <input type="text" name="settings[scale_barcode_prefix]" class="form-control" value="<?= htmlspecialchars($settings_rows['scale_barcode_prefix'] ?? '20') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Default Receipt Thermal Paper Width</label>
                    <select name="settings[receipt_printer_width]" class="form-select">
                        <option value="80mm" <?= (($settings_rows['receipt_printer_width'] ?? '80mm') === '80mm') ? 'selected' : '' ?>>80mm Standard Thermal Slip</option>
                        <option value="58mm" <?= (($settings_rows['receipt_printer_width'] ?? '80mm') === '58mm') ? 'selected' : '' ?>>58mm Compact Narrow Thermal Roll</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 3. Loyalty Program Rules -->
        <div class="col-lg-12">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-stars me-2 text-warning"></i> Customer Loyalty & Rewards Engine</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Eligible Spend to Earn 1 Point ($)</label>
                        <input type="number" step="0.5" name="settings[loyalty_spend_per_point]" class="form-control" value="<?= htmlspecialchars($settings_rows['loyalty_spend_per_point'] ?? '10.00') ?>">
                        <small class="text-muted">e.g. $10 spent earns 1 reward point.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Redemption Value per Point ($)</label>
                        <input type="number" step="0.01" name="settings[loyalty_redemption_rate]" class="form-control" value="<?= htmlspecialchars($settings_rows['loyalty_redemption_rate'] ?? '0.10') ?>">
                        <small class="text-muted">e.g. 1 point = $0.10 credit (100 points = $10 discount).</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm fw-bold" id="btn-save-settings">
                <i class="bi bi-check2-circle me-1"></i> Save Configuration
            </button>
        </div>
    </div>
</form>

<?php
$inline_js = "
$('#form-settings').on('submit', function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    MartPro.ajax(MartPro.baseUrl + '/modules/settings/ajax/save.php', {
        data: data,
        button: '#btn-save-settings'
    }).then(function(res) {
        MartPro.notify('success', res.message);
    });
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
