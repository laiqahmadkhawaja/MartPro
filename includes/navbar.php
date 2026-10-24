<?php
defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
?>
<header class="top-navbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary d-lg-none" type="button" id="sidebar-toggle-btn" aria-label="Toggle navigation">
            <i class="bi bi-list fs-5"></i>
        </button>

        <!-- Store Switcher -->
        <div class="d-none d-sm-flex align-items-center gap-2 bg-light px-2 py-1 rounded-3 border">
            <i class="bi bi-shop text-mp-primary"></i>
            <select class="form-select form-select-sm border-0 bg-transparent fw-semibold" id="store-switcher" style="cursor:pointer; width:auto;">
                <?php foreach ($all_stores as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= ($st['id'] == $current_store['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Right Navbar Actions -->
    <div class="d-flex align-items-center gap-2">
        <!-- Direct POS Action Button -->
        <a href="<?= BASE_URL ?>/modules/pos/index.php" class="btn btn-primary d-flex align-items-center gap-1 shadow-sm px-3 py-1">
            <i class="bi bi-upc-scan fs-5"></i>
            <span class="d-none d-md-inline">POS Checkout</span>
        </a>

        <!-- Dark/Light Theme Button -->
        <button class="btn btn-outline-secondary btn-sm rounded-circle p-2" id="theme-toggle-btn" title="Toggle Theme" type="button">
            <i class="bi bi-moon fs-6"></i>
        </button>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2 border rounded-pill py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                    <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="d-none d-md-block text-start lh-1 me-1">
                    <span class="fw-bold d-block text-navy" style="font-size: 0.85rem;"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>
                    <span class="badge bg-secondary" style="font-size: 0.65rem;"><?= htmlspecialchars($user['role_name'] ?? 'Staff') ?></span>
                </div>
                <i class="bi bi-chevron-down text-muted" style="font-size: 0.75rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-bold text-navy"><?= htmlspecialchars($user['full_name']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                </li>
                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/modules/shifts/index.php"><i class="bi bi-clock-history me-2"></i> Cashier Shifts & X/Z Reports</a></li>
                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/modules/settings/index.php"><i class="bi bi-gear me-2"></i> Settings & Configuration</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>
