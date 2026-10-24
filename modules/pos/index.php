<?php
/**
 * MartPro Advanced POS Checkout Terminal
 */

define('MARTPRO_INIT', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('pos.access');

$db = get_db();
$store = get_current_store();
$user = current_user();

// Fetch active register for this store
$reg_stmt = $db->prepare("SELECT * FROM registers WHERE store_id = :store AND is_active = 1 ORDER BY id ASC LIMIT 1");
$reg_stmt->execute(['store' => $store['id']]);
$register = $reg_stmt->fetch() ?: ['id' => 1, 'code' => 'REG-01', 'name' => 'Main Register'];

// Fetch quick-access products for touch tiles
$quick_prods_stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.primary_barcode, p.selling_price, u.short_code AS unit_name
                                  FROM products p 
                                  JOIN product_units u ON p.unit_id = u.id
                                  WHERE p.is_active = 1 ORDER BY p.id ASC LIMIT 8");
$quick_prods_stmt->execute();
$quick_products = $quick_prods_stmt->fetchAll();

$page_title = 'POS Terminal';
$hide_sidebar = true; // Full screen dedicated layout for POS
$extra_css = BASE_URL . '/assets/css/pos.css';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pos-container">
    <!-- POS Top Header Bar -->
    <div class="pos-header-bar">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-light btn-sm py-1 px-2" title="Back to Dashboard">
                <i class="bi bi-arrow-left"></i>
            </a>
            <img src="<?= BASE_URL ?>/assets/images/logo-icon.svg" height="28" alt="Logo">
            <span class="fw-bold fs-6 ms-1">Mart<span class="text-bright-green">Pro</span> POS</span>
            <span class="badge bg-navy-secondary border border-secondary border-opacity-50 text-light small d-none d-sm-inline">
                <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($store['name']) ?>
            </span>
            <span class="badge bg-navy-secondary border border-secondary border-opacity-50 text-light small d-none d-md-inline">
                <i class="bi bi-display me-1"></i> <?= htmlspecialchars($register['name']) ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Shift Status -->
            <span class="badge bg-success small"><i class="bi bi-circle-fill me-1" style="font-size:0.55rem;"></i> Shift Active</span>
            
            <!-- Hold & Recall Buttons -->
            <button type="button" class="btn btn-outline-light btn-sm py-1" id="btn-hold-sale" title="Hold Transaction (F4)">
                <i class="bi bi-pause-circle me-1"></i> Hold <span class="d-none d-md-inline">[F4]</span>
            </button>
            <button type="button" class="btn btn-outline-light btn-sm py-1" id="btn-recall-sale" title="Recall Suspended (F7)">
                <i class="bi bi-arrow-repeat me-1"></i> Recall <span class="d-none d-md-inline">[F7]</span>
            </button>

            <!-- Electronic Scale Tool -->
            <button type="button" class="btn btn-outline-warning btn-sm py-1" data-bs-toggle="modal" data-bs-target="#scaleModal" title="Electronic Scale Integration">
                <i class="bi bi-speedometer2 me-1"></i> Scale
            </button>
        </div>
    </div>

    <!-- POS Main Work Area -->
    <div class="pos-body">
        <!-- Left: Cart & Product Input -->
        <div class="pos-cart-panel">
            <!-- Scanner & Search Bar -->
            <div class="p-2 border-bottom bg-light">
                <div class="row g-2 align-items-center">
                    <div class="col-8 col-sm-9">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-mp-primary">
                                <i class="bi bi-upc-scan fs-5"></i>
                            </span>
                            <input type="text" id="pos-barcode-input" class="form-control form-control-lg border-start-0 fw-bold" 
                                   placeholder="Scan barcode or type 5*BARCODE [F1]" autocomplete="off" autofocus>
                        </div>
                    </div>
                    <div class="col-4 col-sm-3 d-flex gap-1">
                        <button type="button" class="btn btn-navy flex-fill" data-bs-toggle="modal" data-bs-target="#searchProductModal" title="Search Product (F2)">
                            <i class="bi bi-search me-1"></i> Search <span class="d-none d-sm-inline">[F2]</span>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1 px-1">
                    <small class="text-muted" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle me-1"></i> Tip: Use <code>3*BARCODE</code> for instant quantity multiplication.
                    </small>
                    <span id="scan-feedback" class="small fw-semibold text-success"></span>
                </div>
            </div>

            <!-- Cart Table Items -->
            <div class="pos-cart-table-wrapper" id="cart-container">
                <div class="text-center py-5 text-muted" id="cart-empty-message">
                    <i class="bi bi-cart-x fs-1 text-secondary opacity-50 mb-2 d-block"></i>
                    <h5 class="fw-semibold">Cart is empty</h5>
                    <p class="small mb-0">Scan barcode or pick a product from quick items below.</p>
                </div>
                <div id="cart-items-list" class="d-none"></div>
            </div>

            <!-- Quick Access Touch Product Tiles -->
            <div class="p-2 bg-light border-top">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold text-navy text-uppercase"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Fast Touch Items</span>
                </div>
                <div class="row g-2">
                    <?php foreach ($quick_products as $qp): ?>
                        <div class="col-6 col-sm-4 col-md-3">
                            <button type="button" class="btn btn-white border w-100 text-start p-2 shadow-sm quick-item-btn" 
                                    data-barcode="<?= htmlspecialchars($qp['primary_barcode']) ?>">
                                <div class="fw-bold text-navy text-truncate" style="font-size: 0.85rem;"><?= htmlspecialchars($qp['name']) ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="text-mp-primary fw-bold" style="font-size: 0.85rem;"><?= format_money($qp['selling_price']) ?></span>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;"><?= htmlspecialchars($qp['unit_name']) ?></span>
                                </div>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Totals, Customer & Checkout Panel -->
        <div class="pos-pay-panel">
            <!-- Selected Customer Card -->
            <div class="card mb-3 border p-2 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle fs-4 text-mp-primary"></i>
                        <div>
                            <div class="fw-bold text-navy small" id="pos-customer-name">Walk-in Retail Customer</div>
                            <small class="text-muted" id="pos-customer-details">Guest Checkout</small>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" data-bs-toggle="modal" data-bs-target="#customerSelectModal" title="Select Customer (F6)">
                            <i class="bi bi-people"></i> Select
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2" data-bs-toggle="modal" data-bs-target="#addCustomerModal" title="New Customer">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Big High-Contrast Total Display -->
            <div class="pos-total-display shadow">
                <div class="pos-total-label">Total Payable Due</div>
                <div class="pos-total-amount" id="display-grand-total"><?= format_money(0) ?></div>
            </div>

            <!-- Calculation Summary -->
            <table class="pos-summary-table">
                <tr>
                    <td class="text-muted">Subtotal:</td>
                    <td class="text-end fw-semibold text-navy" id="summary-subtotal">$0.00</td>
                </tr>
                <tr>
                    <td class="text-muted">Item Discounts:</td>
                    <td class="text-end text-danger fw-semibold" id="summary-item-discount">-$0.00</td>
                </tr>
                <tr>
                    <td class="text-muted">
                        Bill Discount:
                        <a href="javascript:void(0)" class="ms-1 small text-primary" id="btn-edit-bill-discount">[Edit]</a>
                    </td>
                    <td class="text-end text-danger fw-semibold" id="summary-bill-discount">-$0.00</td>
                </tr>
                <tr>
                    <td class="text-muted">Tax (GST 5%):</td>
                    <td class="text-end fw-semibold text-navy" id="summary-tax">+$0.00</td>
                </tr>
            </table>

            <!-- Quick Cash Tender Chips -->
            <div class="mb-2">
                <div class="small fw-semibold text-muted mb-1">Quick Cash Pay:</div>
                <div class="quick-cash-chips">
                    <button type="button" class="quick-cash-chip btn-quick-cash" data-amount="exact">Exact Cash</button>
                    <button type="button" class="quick-cash-chip btn-quick-cash" data-amount="5">$5</button>
                    <button type="button" class="quick-cash-chip btn-quick-cash" data-amount="10">$10</button>
                    <button type="button" class="quick-cash-chip btn-quick-cash" data-amount="20">$20</button>
                    <button type="button" class="quick-cash-chip btn-quick-cash" data-amount="50">$50</button>
                    <button type="button" class="quick-cash-chip btn-quick-cash" data-amount="100">$100</button>
                </div>
            </div>

            <!-- Tender Action Grid -->
            <div class="pos-tender-grid">
                <button type="button" class="btn btn-success btn-tender" id="btn-open-payment" title="Open Payment Dialog (F8)">
                    <i class="bi bi-wallet2 fs-4"></i>
                    <span>Pay / Split [F8]</span>
                </button>
                <button type="button" class="btn btn-primary btn-tender" id="btn-instant-cash" title="Instant Cash Checkout (F9)">
                    <i class="bi bi-cash-stack fs-4"></i>
                    <span>Quick Cash [F9]</span>
                </button>
            </div>

            <!-- Footer Action Controls -->
            <div class="d-flex gap-2 mt-auto">
                <button type="button" class="btn btn-outline-danger w-100 py-2 fw-semibold" id="btn-clear-cart" title="Clear Cart (F10)">
                    <i class="bi bi-trash3 me-1"></i> Clear Cart [F10]
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. PAYMENT MODAL -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold" id="paymentModalLabel"><i class="bi bi-credit-card-2-front me-2"></i> Complete Sale Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 text-center mb-3">
                    <span class="text-muted small text-uppercase fw-bold">Grand Total Due</span>
                    <h2 class="fw-bold text-mp-primary mb-0" id="modal-due-amount">$0.00</h2>
                </div>

                <!-- Payment Tenders Form -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-navy"><i class="bi bi-cash me-1 text-success"></i> Cash Received</label>
                    <input type="number" step="0.01" class="form-control form-control-lg fw-bold tender-input" id="tender-cash" placeholder="0.00">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label fw-semibold small text-navy"><i class="bi bi-credit-card me-1 text-primary"></i> Card / Digital</label>
                        <input type="number" step="0.01" class="form-control tender-input" id="tender-card" placeholder="0.00">
                    </div>
                    <div class="col-5">
                        <label class="form-label fw-semibold small text-muted">Card Ref / Auth</label>
                        <input type="text" class="form-control" id="tender-card-ref" placeholder="TXN-1234">
                    </div>
                </div>

                <div class="mb-3" id="credit-khata-block">
                    <label class="form-label fw-semibold small text-navy"><i class="bi bi-journal-text me-1 text-warning"></i> Customer Credit (Khata)</label>
                    <input type="number" step="0.01" class="form-control tender-input" id="tender-credit" placeholder="0.00">
                    <small class="text-muted" id="credit-limit-hint">Available Limit: Select customer first</small>
                </div>

                <!-- Change Due Box -->
                <div class="p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold text-navy">Change Due:</span>
                    <span class="fw-bold fs-4 text-success" id="modal-change-due">$0.00</span>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4 fw-bold" id="btn-submit-payment">
                    <i class="bi bi-check2-circle me-1"></i> Authorize & Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 2. SEARCH PRODUCT MODAL -->
<div class="modal fade" id="searchProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-search me-2"></i> Search Product Catalog</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="modal-search-input" class="form-control" placeholder="Type product name, SKU, or barcode..." autofocus>
                </div>
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Product Name</th>
                                <th>SKU / Barcode</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="modal-search-results">
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Type in the search box to find products...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. CUSTOMER SELECT MODAL -->
<div class="modal fade" id="customerSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-people me-2"></i> Select Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="cust-search-input" class="form-control" placeholder="Search by name, phone, or customer code...">
                </div>
                <div class="list-group" id="cust-search-results" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 4. QUICK ADD CUSTOMER MODAL -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i> Quick Add Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-quick-add-customer">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Customer Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" required placeholder="e.g. +1 555 123 4567">
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
                            <input type="number" step="10" name="credit_limit" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i> Save & Select</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. RECALL HELD SALES MODAL -->
<div class="modal fade" id="recallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-2"></i> Held Transactions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>Held At</th>
                                <th>Items</th>
                                <th>Note</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="held-sales-table-body">
                            <tr><td colspan="6" class="text-center py-4 text-muted">Loading held sales...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6. ELECTRONIC SCALE SIMULATOR & HARDWARE MODAL -->
<div class="modal fade" id="scaleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-speedometer2 me-2"></i> Electronic Weighing Scale</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted small">Reads real-time weight from attached RS-232 / USB scale via Browser Web Serial API or Manual Entry.</p>
                
                <div class="bg-dark text-success p-3 rounded-3 my-3 font-monospace">
                    <span class="fs-6 d-block text-secondary">LIVE SCALE READING</span>
                    <span class="fs-1 fw-bold" id="live-weight-display">1.450</span> <span class="fs-5">KG</span>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <button type="button" class="btn btn-outline-primary w-100" id="btn-scale-tare"><i class="bi bi-arrow-clockwise"></i> Zero / Tare</button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-outline-secondary w-100" id="btn-scale-sim"><i class="bi bi-dice-5"></i> Sim Random</button>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">Manual Weight (Kg)</span>
                    <input type="number" step="0.001" id="scale-manual-weight" class="form-control" value="1.450">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success fw-bold" id="btn-apply-scale-weight"><i class="bi bi-check2"></i> Apply Weight to Product</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = BASE_URL . '/modules/pos/pos.js';
require_once __DIR__ . '/../../includes/footer.php';
?>
