<?php
/**
 * MartPro Hardware & POS Peripherals Integration Module
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('settings.manage');

$db = get_db();
$devices = $db->query("SELECT * FROM hardware_devices ORDER BY id ASC")->fetchAll();

$page_title = 'Hardware Integration';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-navy mb-1">POS Hardware & Peripherals Management</h4>
        <p class="text-muted small mb-0">Configure thermal printers, barcode scanners, RS-232 weighing scales, and dual customer displays</p>
    </div>
</div>

<div class="row g-4">
    <!-- 1. Barcode Scanner Test Console -->
    <div class="col-lg-6">
        <div class="card p-4 border-0 shadow-sm h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-upc-scan fs-3 text-mp-primary"></i>
                <div>
                    <h5 class="fw-bold text-navy mb-0">USB & Bluetooth Barcode Scanner</h5>
                    <small class="text-muted">Standard HID Keyboard Emulation Mode</small>
                </div>
            </div>
            <p class="small text-muted mb-3">Test your handheld scanner input. Most POS scanners send an automatic carriage return (Enter key) after reading the barcode.</p>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Scanner Input Test Field</label>
                <input type="text" id="scanner-test-input" class="form-control form-control-lg fw-bold" placeholder="Scan any product barcode here..." autocomplete="off">
            </div>
            <div class="p-3 bg-light rounded-3 font-monospace small" id="scanner-output" style="min-height: 80px;">
                <span class="text-muted">Awaiting scanner beam input...</span>
            </div>
        </div>
    </div>

    <!-- 2. Electronic Weighing Scale Web Serial -->
    <div class="col-lg-6">
        <div class="card p-4 border-0 shadow-sm h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-speedometer2 fs-3 text-warning"></i>
                <div>
                    <h5 class="fw-bold text-navy mb-0">Electronic Weighing Scale (Web Serial API)</h5>
                    <small class="text-muted">RS-232 / USB Serial COM Port Communication</small>
                </div>
            </div>
            <p class="small text-muted mb-3">In modern browsers (Chrome/Edge), MartPro connects directly to certified POS weighing scales via the W3C Web Serial API.</p>
            
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-outline-warning" id="btn-connect-scale">
                    <i class="bi bi-usb-symbol me-1"></i> Connect Scale via Web Serial
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-scale-manual-test">
                    <i class="bi bi-play-circle me-1"></i> Send Ping
                </button>
            </div>

            <div class="bg-dark text-success p-3 rounded-3 font-monospace">
                <div class="small text-secondary">LIVE SCALE PORT STATUS: <span id="scale-status" class="text-warning">Ready / Emulation</span></div>
                <div class="fs-2 fw-bold" id="scale-live-weight">1.450 KG (Stable)</div>
            </div>
        </div>
    </div>

    <!-- 3. Thermal Receipt Printer Setup -->
    <div class="col-lg-6">
        <div class="card p-4 border-0 shadow-sm h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-printer fs-3 text-info"></i>
                <div>
                    <h5 class="fw-bold text-navy mb-0">Thermal Receipt Printers (ESC/POS)</h5>
                    <small class="text-muted">58mm and 80mm Roll Specifications</small>
                </div>
            </div>
            <p class="small text-muted mb-3">MartPro generates vector-crisp thermal printouts with itemized breakdowns, GST summaries, and return barcodes.</p>
            
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/print/receipt.php?invoice=INV-26-10001&paper=80mm" target="_blank" class="btn btn-primary btn-sm">
                    <i class="bi bi-printer me-1"></i> Test 80mm Print Layout
                </a>
                <a href="<?= BASE_URL ?>/print/receipt.php?invoice=INV-26-10001&paper=58mm" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer me-1"></i> Test 58mm Print Layout
                </a>
            </div>
        </div>
    </div>

    <!-- 4. Customer Secondary Screen Display -->
    <div class="col-lg-6">
        <div class="card p-4 border-0 shadow-sm h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-display fs-3 text-danger"></i>
                <div>
                    <h5 class="fw-bold text-navy mb-0">Customer Secondary Screen Display</h5>
                    <small class="text-muted">Dual Monitor Mirror / Customer Facing Pole Display</small>
                </div>
            </div>
            <p class="small text-muted mb-3">Open the high-contrast customer screen on your second POS monitor to display live item scans, totals, and store branding.</p>
            
            <a href="<?= BASE_URL ?>/modules/hardware/customer_display.php" target="_blank" class="btn btn-navy btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Open Customer Display Window
            </a>
        </div>
    </div>
</div>

<?php
$inline_js = "
$('#scanner-test-input').on('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const val = $(this).val();
        $('#scanner-output').html('<span class=\"text-success fw-bold\">[SCAN DETECTED]</span> Read Barcode: <strong>' + val + '</strong> at ' + new Date().toLocaleTimeString());
        $(this).val('');
    }
});

$('#btn-connect-scale').on('click', async function() {
    if ('serial' in navigator) {
        try {
            const port = await navigator.serial.requestPort();
            await port.open({ baudRate: 9600 });
            $('#scale-status').text('Connected to Serial Port!').removeClass('text-warning').addClass('text-success');
        } catch (err) {
            $('#scale-status').text('Serial selection cancelled or denied.').addClass('text-danger');
        }
    } else {
        alert('Web Serial API is available in Google Chrome and Microsoft Edge on desktop.');
    }
});
";
require_once __DIR__ . '/../../includes/footer.php';
?>
