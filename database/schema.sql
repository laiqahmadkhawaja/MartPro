-- ============================================================================
-- MartPro Enterprise Retail POS & Store Management Database Schema
-- Standard: MySQL 8.0+ / MariaDB 10.4+ compatible
-- Engine: InnoDB, Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- Precision: DECIMAL(19,4) for monetary values and fractional stock quantities
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. ORGANIZATION & CONFIGURATION
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `legal_name` VARCHAR(150) DEFAULT NULL,
    `tagline` VARCHAR(255) DEFAULT 'Smart Store. Better Business.',
    `tax_number` VARCHAR(50) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `currency_code` VARCHAR(10) DEFAULT 'USD',
    `currency_symbol` VARCHAR(10) DEFAULT '$',
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `logo_url` VARCHAR(255) DEFAULT 'assets/images/logo.svg',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `receipt_header` TEXT DEFAULT NULL,
    `receipt_footer` VARCHAR(500) DEFAULT 'Thank you for shopping at MartPro!',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNSIGNED DEFAULT NULL,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `location` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `cash_drawer_limit` DECIMAL(19,4) DEFAULT 1000.0000,
    `printer_type` ENUM('58mm', '80mm', 'standard') DEFAULT '80mm',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_store_reg_code` (`store_id`, `code`),
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` LONGTEXT DEFAULT NULL,
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `numbering_sequences` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prefix` VARCHAR(20) NOT NULL UNIQUE,
    `current_number` INT UNSIGNED NOT NULL DEFAULT 1000,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tax_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `is_inclusive` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. AUTHENTICATION & RBAC
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_system` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(80) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `default_store_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `failed_login_count` INT UNSIGNED DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`default_store_id`) REFERENCES `stores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_store_access` (
    `user_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `store_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_login_ip` (`ip_address`),
    INDEX `idx_login_user` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. PRODUCT CATALOG MASTER DATA
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_brands` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `origin` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_units` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `short_code` VARCHAR(20) NOT NULL UNIQUE,
    `allow_decimal` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `unit_conversions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `from_unit_id` INT UNSIGNED NOT NULL,
    `to_unit_id` INT UNSIGNED NOT NULL,
    `multiplier` DECIMAL(19,6) NOT NULL,
    `description` VARCHAR(150) DEFAULT NULL,
    UNIQUE KEY `uk_conv` (`from_unit_id`, `to_unit_id`),
    FOREIGN KEY (`from_unit_id`) REFERENCES `product_units`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_unit_id`) REFERENCES `product_units`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `brand_id` INT UNSIGNED DEFAULT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `tax_category_id` INT UNSIGNED DEFAULT NULL,
    `primary_barcode` VARCHAR(60) NOT NULL UNIQUE,
    `purchase_cost` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `selling_price` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `wholesale_price` DECIMAL(19,4) DEFAULT NULL,
    `member_price` DECIMAL(19,4) DEFAULT NULL,
    `min_selling_price` DECIMAL(19,4) DEFAULT NULL,
    `reorder_level` DECIMAL(19,4) DEFAULT 5.0000,
    `max_stock_level` DECIMAL(19,4) DEFAULT 500.0000,
    `track_batches` TINYINT(1) DEFAULT 0,
    `track_expiry` TINYINT(1) DEFAULT 0,
    `is_variable_weight` TINYINT(1) DEFAULT 0,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_prod_name` (`name`),
    INDEX `idx_prod_sku` (`sku`),
    INDEX `idx_prod_barcode` (`primary_barcode`),
    FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`brand_id`) REFERENCES `product_brands`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`unit_id`) REFERENCES `product_units`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tax_category_id`) REFERENCES `tax_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_barcodes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `barcode` VARCHAR(60) NOT NULL UNIQUE,
    `barcode_type` VARCHAR(20) DEFAULT 'CODE128',
    `packaging_note` VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. INVENTORY & STOCK MANAGEMENT
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_batches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `batch_number` VARCHAR(60) NOT NULL,
    `supplier_id` INT UNSIGNED DEFAULT NULL,
    `quantity_received` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `quantity_remaining` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `unit_cost` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `manufacture_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'quarantined', 'expired', 'exhausted') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_batch_exp` (`expiry_date`),
    UNIQUE KEY `uk_prod_store_batch` (`product_id`, `store_id`, `batch_number`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_balances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED DEFAULT NULL,
    `quantity` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_prod_store_wh` (`product_id`, `store_id`, `warehouse_id`),
    INDEX `idx_stock_qty` (`quantity`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED DEFAULT NULL,
    `batch_id` INT UNSIGNED DEFAULT NULL,
    `movement_type` ENUM(
        'opening_stock', 'purchase_receipt', 'sale', 'sales_return',
        'purchase_return', 'transfer_out', 'transfer_in',
        'stock_adjustment', 'damage', 'waste', 'physical_count'
    ) NOT NULL,
    `quantity_in` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `quantity_out` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `unit_cost` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `reference_doc` VARCHAR(80) DEFAULT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mov_prod` (`product_id`),
    INDEX `idx_mov_store` (`store_id`),
    INDEX `idx_mov_date` (`created_at`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`batch_id`) REFERENCES `stock_batches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transfer_number` VARCHAR(50) NOT NULL UNIQUE,
    `source_store_id` INT UNSIGNED NOT NULL,
    `dest_store_id` INT UNSIGNED NOT NULL,
    `status` ENUM('draft', 'dispatched', 'received', 'cancelled') DEFAULT 'draft',
    `dispatched_by` INT UNSIGNED DEFAULT NULL,
    `dispatched_at` DATETIME DEFAULT NULL,
    `received_by` INT UNSIGNED DEFAULT NULL,
    `received_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`source_store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`dest_store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`dispatched_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transfer_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED DEFAULT NULL,
    `quantity_dispatched` DECIMAL(19,4) NOT NULL,
    `quantity_received` DECIMAL(19,4) DEFAULT 0.0000,
    `quantity_damaged` DECIMAL(19,4) DEFAULT 0.0000,
    FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_adjustments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `adjustment_number` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `approved_by` INT UNSIGNED DEFAULT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_adjustment_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `adjustment_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED DEFAULT NULL,
    `previous_qty` DECIMAL(19,4) NOT NULL,
    `adjusted_qty` DECIMAL(19,4) NOT NULL,
    `quantity_difference` DECIMAL(19,4) NOT NULL,
    `unit_cost` DECIMAL(19,4) NOT NULL,
    FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_counts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `count_number` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `count_date` DATE NOT NULL,
    `status` ENUM('in_progress', 'completed', 'posted', 'cancelled') DEFAULT 'in_progress',
    `created_by` INT UNSIGNED NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_count_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `stock_count_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `expected_qty` DECIMAL(19,4) NOT NULL,
    `counted_qty` DECIMAL(19,4) NOT NULL,
    `variance_qty` DECIMAL(19,4) NOT NULL,
    `unit_cost` DECIMAL(19,4) NOT NULL,
    FOREIGN KEY (`stock_count_id`) REFERENCES `stock_counts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. SUPPLIERS & PURCHASING
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `supplier_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `tax_number` VARCHAR(50) DEFAULT NULL,
    `payment_terms` VARCHAR(100) DEFAULT 'Net 30',
    `credit_limit` DECIMAL(19,4) DEFAULT 0.0000,
    `opening_balance` DECIMAL(19,4) DEFAULT 0.0000,
    `current_balance` DECIMAL(19,4) DEFAULT 0.0000,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `po_date` DATE NOT NULL,
    `expected_delivery_date` DATE DEFAULT NULL,
    `subtotal` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `discount_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `tax_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `freight` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `grand_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `status` ENUM('draft', 'sent', 'partial_received', 'received', 'cancelled') DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(19,4) NOT NULL,
    `unit_cost` DECIMAL(19,4) NOT NULL,
    `tax_percent` DECIMAL(8,4) DEFAULT 0.0000,
    `line_total` DECIMAL(19,4) NOT NULL,
    `quantity_received` DECIMAL(19,4) DEFAULT 0.0000,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `goods_receipts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `grn_number` VARCHAR(50) NOT NULL UNIQUE,
    `po_id` INT UNSIGNED DEFAULT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `received_date` DATE NOT NULL,
    `supplier_invoice_ref` VARCHAR(100) DEFAULT NULL,
    `total_amount` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `received_by` INT UNSIGNED NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `grn_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_number` VARCHAR(60) DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `quantity_received` DECIMAL(19,4) NOT NULL,
    `quantity_damaged` DECIMAL(19,4) DEFAULT 0.0000,
    `unit_cost` DECIMAL(19,4) NOT NULL,
    `line_total` DECIMAL(19,4) NOT NULL,
    FOREIGN KEY (`grn_id`) REFERENCES `goods_receipts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_invoices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `grn_id` INT UNSIGNED DEFAULT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `total_amount` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `paid_amount` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `balance_due` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `payment_status` ENUM('unpaid', 'partially_paid', 'paid') DEFAULT 'unpaid',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_sup_inv` (`supplier_id`, `invoice_number`),
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`grn_id`) REFERENCES `goods_receipts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_returns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_number` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `purchase_invoice_id` INT UNSIGNED DEFAULT NULL,
    `total_refund` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `reason` VARCHAR(255) NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_return_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_return_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED DEFAULT NULL,
    `quantity` DECIMAL(19,4) NOT NULL,
    `unit_cost` DECIMAL(19,4) NOT NULL,
    `line_total` DECIMAL(19,4) NOT NULL,
    FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `voucher_number` VARCHAR(50) NOT NULL UNIQUE,
    `supplier_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'bank_transfer', 'cheque', 'digital') NOT NULL,
    `amount` DECIMAL(19,4) NOT NULL,
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. CUSTOMERS, LOYALTY & KHATA
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `membership_tier` ENUM('standard', 'silver', 'gold', 'vip', 'wholesale') DEFAULT 'standard',
    `credit_limit` DECIMAL(19,4) DEFAULT 0.0000,
    `current_balance` DECIMAL(19,4) DEFAULT 0.0000, -- positive means customer owes money
    `loyalty_points` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cust_phone` (`phone`),
    INDEX `idx_cust_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_ledger` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL,
    `transaction_type` ENUM('sale_credit', 'payment', 'return_credit', 'manual_adjustment') NOT NULL,
    `reference_id` VARCHAR(60) DEFAULT NULL,
    `debit` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,   -- Increases debt
    `credit` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,  -- Decreases debt (payment received)
    `running_balance` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL,
    `sale_id` INT UNSIGNED DEFAULT NULL,
    `type` ENUM('earned', 'redeemed', 'expired', 'reversed') NOT NULL,
    `points` INT NOT NULL,
    `conversion_amount` DECIMAL(19,4) DEFAULT 0.0000,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. CASH REGISTERS & CASHIER SHIFTS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `register_shifts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shift_number` VARCHAR(50) NOT NULL UNIQUE,
    `register_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `opened_at` DATETIME NOT NULL,
    `opening_cash_float` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `opening_notes` TEXT DEFAULT NULL,
    `closed_at` DATETIME DEFAULT NULL,
    `closed_by` INT UNSIGNED DEFAULT NULL,
    `expected_cash` DECIMAL(19,4) DEFAULT 0.0000,
    `counted_cash` DECIMAL(19,4) DEFAULT 0.0000,
    `cash_variance` DECIMAL(19,4) DEFAULT 0.0000,
    `status` ENUM('open', 'closed') DEFAULT 'open',
    `closing_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_shift_status` (`status`),
    FOREIGN KEY (`register_id`) REFERENCES `registers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. SALES & ADVANCED POS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `register_id` INT UNSIGNED NOT NULL,
    `shift_id` INT UNSIGNED DEFAULT NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `sale_date` DATETIME NOT NULL,
    `subtotal` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `item_discount_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `bill_discount_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `tax_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `rounding_adjustment` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `grand_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `paid_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `change_due` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `customer_credit_amount` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `cogs_total` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `payment_status` ENUM('paid', 'partial', 'credit', 'refunded') DEFAULT 'paid',
    `status` ENUM('completed', 'voided', 'refunded') DEFAULT 'completed',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sales_inv` (`invoice_number`),
    INDEX `idx_sales_date` (`sale_date`),
    INDEX `idx_sales_store` (`store_id`),
    INDEX `idx_sales_cashier` (`cashier_id`),
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`register_id`) REFERENCES `registers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`shift_id`) REFERENCES `register_shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `sku` VARCHAR(50) NOT NULL,
    `unit_short_code` VARCHAR(20) NOT NULL,
    `quantity` DECIMAL(19,4) NOT NULL,
    `unit_price` DECIMAL(19,4) NOT NULL,
    `cost_price` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `discount_amount` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `tax_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `tax_amount` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `line_total` DECIMAL(19,4) NOT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `payment_method` ENUM('cash', 'card', 'digital_wallet', 'customer_credit', 'loyalty') NOT NULL,
    `amount` DECIMAL(19,4) NOT NULL,
    `reference_code` VARCHAR(100) DEFAULT NULL,
    `payment_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `held_sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `hold_reference` VARCHAR(50) NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `register_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `cart_data` LONGTEXT NOT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `held_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`register_id`) REFERENCES `registers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_returns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_number` VARCHAR(50) NOT NULL UNIQUE,
    `original_sale_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `register_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `refund_method` ENUM('cash', 'customer_credit', 'voucher') NOT NULL,
    `total_refund` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `reason` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`original_sale_id`) REFERENCES `sales`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`register_id`) REFERENCES `registers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_return_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_return_id` INT UNSIGNED NOT NULL,
    `sale_item_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED DEFAULT NULL,
    `quantity` DECIMAL(19,4) NOT NULL,
    `refund_unit_price` DECIMAL(19,4) NOT NULL,
    `line_refund_total` DECIMAL(19,4) NOT NULL,
    `item_condition` ENUM('resalable', 'damaged', 'expired') DEFAULT 'resalable',
    FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. CASH MANAGEMENT, EXPENSES & SAFE
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `safe_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `current_balance` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `register_id` INT UNSIGNED DEFAULT NULL,
    `safe_id` INT UNSIGNED DEFAULT NULL,
    `movement_type` ENUM('safe_drop', 'cash_in', 'cash_out', 'petty_cash') NOT NULL,
    `amount` DECIMAL(19,4) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `performed_by` INT UNSIGNED NOT NULL,
    `authorized_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`register_id`) REFERENCES `registers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`safe_id`) REFERENCES `safe_accounts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `expense_number` VARCHAR(50) NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(19,4) NOT NULL,
    `expense_date` DATE NOT NULL,
    `paid_from` ENUM('register_cash', 'petty_cash', 'bank', 'safe') DEFAULT 'register_cash',
    `payee` VARCHAR(150) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. PROMOTIONS & PRICING RULES
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `type` ENUM('percentage', 'fixed_amount', 'buy_x_get_y', 'bundle') NOT NULL,
    `discount_value` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `min_order_amount` DECIMAL(19,4) DEFAULT 0.0000,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. ACCOUNTING FOUNDATION
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entry_number` VARCHAR(50) NOT NULL UNIQUE,
    `entry_date` DATE NOT NULL,
    `source_document` VARCHAR(60) DEFAULT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entry_lines` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `journal_entry_id` INT UNSIGNED NOT NULL,
    `account_id` INT UNSIGNED NOT NULL,
    `debit` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `credit` DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    `memo` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. AUDIT TRAIL, HARDWARE & NOTIFICATIONS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` VARCHAR(50) DEFAULT NULL,
    `old_values` LONGTEXT DEFAULT NULL,
    `new_values` LONGTEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_module` (`module`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `target_role_id` INT UNSIGNED DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `link_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hardware_devices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `device_type` ENUM('barcode_scanner', 'thermal_printer', 'cash_drawer', 'weighing_scale', 'customer_display') NOT NULL,
    `connection_type` ENUM('usb_keyboard', 'web_serial', 'network', 'browser_print') NOT NULL,
    `port_identifier` VARCHAR(100) DEFAULT NULL,
    `baud_rate` INT DEFAULT 9600,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `idempotency_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `idempotency_key` VARCHAR(100) NOT NULL UNIQUE,
    `response_json` LONGTEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
