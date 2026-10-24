-- ============================================================================
-- MartPro Enterprise Retail POS & Store Management Seed Data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Company Profile
INSERT INTO `companies` (`id`, `name`, `legal_name`, `tagline`, `tax_number`, `phone`, `email`, `address`, `currency_code`, `currency_symbol`, `timezone`)
VALUES (1, 'MartPro Supermarkets', 'MartPro Retail Operations Pvt Ltd', 'Smart Store. Better Business.', 'TX-98745210', '+1 (555) 234-5678', 'contact@martpro.store', '100 Enterprise Boulevard, Suite 500, New York, NY 10001', 'USD', '$', 'UTC')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. Stores & Warehouses
INSERT INTO `stores` (`id`, `company_id`, `code`, `name`, `phone`, `email`, `address`, `receipt_header`, `receipt_footer`, `is_active`) VALUES
(1, 1, 'STORE-001', 'MartPro Central Supermarket', '+1 (555) 234-5678', 'central@martpro.store', '100 Enterprise Blvd, New York, NY', 'MartPro Central Supermarket\nTax ID: TX-98745210\nTel: +1 (555) 234-5678', 'Thank you for shopping at MartPro!\nKeep receipt for returns within 7 days.', 1),
(2, 1, 'STORE-002', 'MartPro Westside Express', '+1 (555) 876-5432', 'westside@martpro.store', '450 West 42nd St, New York, NY', 'MartPro Westside Express\nTax ID: TX-98745210\nTel: +1 (555) 876-5432', 'Fast & Fresh! See you again.', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `warehouses` (`id`, `store_id`, `code`, `name`, `location`, `is_active`) VALUES
(1, 1, 'WH-MAIN', 'Central Distribution Warehouse', 'Building B, Logistics Park, NJ', 1),
(2, 2, 'WH-WEST', 'Westside Stockroom', 'Store Basement Storage, Westside', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 3. Registers
INSERT INTO `registers` (`id`, `store_id`, `code`, `name`, `cash_drawer_limit`, `printer_type`, `is_active`) VALUES
(1, 1, 'REG-01', 'Express Lane 1', 1500.0000, '80mm', 1),
(2, 1, 'REG-02', 'Main Lane 2', 2000.0000, '80mm', 1),
(3, 2, 'REG-W1', 'West Checkout 1', 1000.0000, '58mm', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 4. Numbering Sequences
INSERT INTO `numbering_sequences` (`prefix`, `current_number`) VALUES
('INV', 10001),
('PO', 2001),
('GRN', 3001),
('PINV', 4001),
('PRET', 5001),
('SRET', 6001),
('TRF', 7001),
('ADJ', 8001),
('STK', 9001),
('EXP', 1001),
('CSH', 1001)
ON DUPLICATE KEY UPDATE `prefix`=VALUES(`prefix`);

-- 5. Tax Categories
INSERT INTO `tax_categories` (`id`, `name`, `rate`, `is_inclusive`, `is_active`) VALUES
(1, 'General Sales Tax (5%)', 5.0000, 0, 1),
(2, 'Zero Rated (0%)', 0.0000, 0, 1),
(3, 'Exempt', 0.0000, 0, 1),
(4, 'Luxury Tax (10%)', 10.0000, 0, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 6. Roles & Permissions
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full platform access and administrative control', 1),
(2, 'Store Manager', 'store_manager', 'Store operations, inventory, shift approvals, and reports', 1),
(3, 'Head Cashier', 'head_cashier', 'Senior cashier with override, void, and shift review rights', 1),
(4, 'Cashier', 'cashier', 'POS checkout, sales, and shift operations', 1),
(5, 'Inventory Manager', 'inventory_manager', 'Purchasing, receiving, adjustments, and stock control', 1),
(6, 'Accountant', 'accountant', 'Ledgers, accounts payable, receivables, and financial audits', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `permissions` (`id`, `module`, `slug`, `name`, `description`) VALUES
(1, 'pos', 'pos.access', 'Access POS Checkout', 'Can open and use POS register checkout'),
(2, 'pos', 'pos.discount', 'Apply POS Discounts', 'Can apply line and bill discounts'),
(3, 'pos', 'pos.price_override', 'Price Override', 'Can override standard product prices at checkout'),
(4, 'pos', 'pos.void', 'Void & Cancel Sale', 'Can void active or held sales'),
(5, 'pos', 'pos.returns', 'Process Returns & Refunds', 'Can process customer returns and cash refunds'),
(6, 'inventory', 'inventory.view', 'View Inventory', 'Can view stock balances and ledger'),
(7, 'inventory', 'inventory.adjust', 'Adjust Stock', 'Can create and post stock adjustments'),
(8, 'inventory', 'inventory.transfer', 'Transfer Stock', 'Can dispatch and receive stock transfers'),
(9, 'inventory', 'inventory.count', 'Physical Stock Count', 'Can perform and reconcile physical stocktakes'),
(10, 'purchasing', 'purchasing.manage', 'Manage Purchasing', 'Can manage purchase orders and GRN receiving'),
(11, 'products', 'products.manage', 'Manage Product Catalog', 'Can create, edit, and deactivate products'),
(12, 'customers', 'customers.manage', 'Manage Customers & Credit', 'Can manage customers, tiers, and khata credit'),
(13, 'shifts', 'shifts.manage', 'Shift & Cash Drawer', 'Can open, close, drop cash, and view X/Z reports'),
(14, 'reports', 'reports.view', 'View Business Reports', 'Can view sales, inventory, and profitability reports'),
(15, 'accounting', 'accounting.view', 'Financial Accounting', 'Can view journal entries and ledger balances'),
(16, 'settings', 'settings.manage', 'System Settings', 'Can modify business, tax, and hardware settings'),
(17, 'users', 'users.manage', 'User Administration', 'Can manage users, roles, and access controls')
ON DUPLICATE KEY UPDATE `slug`=VALUES(`slug`);

-- Grant all permissions to Super Admin (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Manager permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE `module` IN ('pos', 'inventory', 'purchasing', 'products', 'customers', 'shifts', 'reports');

-- Cashier permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` WHERE `slug` IN ('pos.access', 'shifts.manage');

-- 7. Users (Default password: admin123 / manager123 / cashier123)
-- Hash for 'admin123': $2y$10$mzJTEKkMRTmPAHJ67GoV0OYU29a2n6ClMFbuWVn8EAgfIdYKrXKQS
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `password_hash`, `phone`, `role_id`, `default_store_id`, `is_active`) VALUES
(1, 'admin', 'System Administrator', 'admin@martpro.store', '$2y$10$mzJTEKkMRTmPAHJ67GoV0OYU29a2n6ClMFbuWVn8EAgfIdYKrXKQS', '+1 (555) 100-0001', 1, 1, 1),
(2, 'manager', 'Sarah Jenkins (Manager)', 'manager@martpro.store', '$2y$10$mzJTEKkMRTmPAHJ67GoV0OYU29a2n6ClMFbuWVn8EAgfIdYKrXKQS', '+1 (555) 100-0002', 2, 1, 1),
(3, 'cashier', 'Alex Rivera (Cashier)', 'cashier@martpro.store', '$2y$10$mzJTEKkMRTmPAHJ67GoV0OYU29a2n6ClMFbuWVn8EAgfIdYKrXKQS', '+1 (555) 100-0003', 4, 1, 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

INSERT IGNORE INTO `user_store_access` (`user_id`, `store_id`) VALUES
(1, 1), (1, 2),
(2, 1), (2, 2),
(3, 1);

-- 8. Product Categories, Brands & Units
INSERT INTO `product_categories` (`id`, `parent_id`, `name`, `code`, `description`, `is_active`) VALUES
(1, NULL, 'Groceries & Staples', 'CAT-GROC', 'Rice, flour, cooking oils, sugar, and grains', 1),
(2, NULL, 'Dairy & Bakery', 'CAT-DAIRY', 'Fresh milk, cheese, butter, yogurt, and bread', 1),
(3, NULL, 'Beverages & Drinks', 'CAT-BEV', 'Juices, soda, energy drinks, mineral water, tea, and coffee', 1),
(4, NULL, 'Fresh Fruits & Veg', 'CAT-PROD', 'Fresh daily farm produce with weight scale support', 1),
(5, NULL, 'Snacks & Confectionery', 'CAT-SNAK', 'Biscuits, chips, chocolates, and cookies', 1),
(6, NULL, 'Household & Cleaning', 'CAT-HSHLD', 'Detergents, soaps, paper towels, and cleaners', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `product_brands` (`id`, `name`, `origin`, `is_active`) VALUES
(1, 'Nestle', 'Switzerland', 1),
(2, 'PepsiCo', 'USA', 1),
(3, 'Coca-Cola Company', 'USA', 1),
(4, 'Unilever', 'UK/Netherlands', 1),
(5, 'MartPro Farm Fresh', 'Local', 1),
(6, 'P&G Home', 'USA', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `product_units` (`id`, `name`, `short_code`, `allow_decimal`) VALUES
(1, 'Piece / Each', 'pcs', 0),
(2, 'Kilogram', 'kg', 1),
(3, 'Gram', 'g', 1),
(4, 'Liter', 'ltr', 1),
(5, 'Carton / Box', 'ctn', 0),
(6, 'Pack / Bundle', 'pack', 0)
ON DUPLICATE KEY UPDATE `short_code`=VALUES(`short_code`);

INSERT INTO `unit_conversions` (`from_unit_id`, `to_unit_id`, `multiplier`, `description`) VALUES
(5, 1, 24.000000, '1 Carton = 24 Pieces'),
(2, 3, 1000.000000, '1 Kilogram = 1000 Grams'),
(4, 3, 1000.000000, '1 Liter = 1000 Milliliters')
ON DUPLICATE KEY UPDATE `multiplier`=VALUES(`multiplier`);

-- 9. Products Master Data
INSERT INTO `products` (`id`, `sku`, `name`, `description`, `category_id`, `brand_id`, `unit_id`, `tax_category_id`, `primary_barcode`, `purchase_cost`, `selling_price`, `wholesale_price`, `member_price`, `reorder_level`, `track_batches`, `track_expiry`, `is_variable_weight`, `is_active`) VALUES
(1, 'SKU-MILK-01', 'Nestle Full Cream Milk 1L', 'Pasteurized homogenized cow milk', 2, 1, 4, 2, '890123450001', 1.8000, 2.5000, 2.3000, 2.4000, 20.0000, 1, 1, 0, 1),
(2, 'SKU-COKE-01', 'Coca-Cola Classic Can 330ml', 'Original carbonated soft drink', 3, 3, 1, 1, '890123450002', 0.6500, 1.2500, 1.0500, 1.1500, 50.0000, 1, 1, 0, 1),
(3, 'SKU-LAYS-01', 'Lays Classic Salted Chips 50g', 'Crispy potato chips salted flavor', 5, 2, 1, 1, '890123450003', 0.7000, 1.5000, 1.3000, 1.4000, 30.0000, 0, 1, 0, 1),
(4, 'SKU-RICE-01', 'Basmati Premium Royal Rice 5kg', 'Aged extra long grain fragrant basmati rice', 1, 5, 1, 2, '890123450004', 8.5000, 12.9900, 11.5000, 12.0000, 15.0000, 0, 0, 0, 1),
(5, 'SKU-APPLES-01', 'Fresh Gala Red Apples', 'Crisp sweet eating apples sold by weight', 4, 5, 2, 2, '200100100000', 1.8000, 3.2000, 2.9000, 3.0000, 25.0000, 0, 0, 1, 1),
(6, 'SKU-TIDE-01', 'Tide Liquid Laundry Detergent 2L', 'Concentrated deep clean stain remover', 6, 6, 1, 1, '890123450006', 7.2000, 11.5000, 10.2000, 10.8000, 10.0000, 0, 0, 0, 1),
(7, 'SKU-BREAD-01', 'MartPro Soft Whole Wheat Bread', 'Freshly baked sliced loaf', 2, 5, 1, 2, '890123450007', 1.1000, 2.2000, 1.9000, 2.0000, 15.0000, 1, 1, 0, 1),
(8, 'SKU-WATER-01', 'Pure Mineral Spring Water 500ml', 'Natural hydrating spring water bottle', 3, 1, 1, 2, '890123450008', 0.2500, 0.7500, 0.6000, 0.6500, 100.0000, 0, 0, 0, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Additional Barcodes
INSERT INTO `product_barcodes` (`product_id`, `barcode`, `barcode_type`, `packaging_note`) VALUES
(1, '890123450001', 'EAN13', 'Single Bottle 1L'),
(2, '890123450002', 'EAN13', 'Single Can 330ml'),
(2, '890123450999', 'CODE128', 'Pack of 6 Cans Promo')
ON DUPLICATE KEY UPDATE `barcode`=VALUES(`barcode`);

-- 10. Stock Batches & Initial Balances
INSERT INTO `stock_batches` (`id`, `product_id`, `store_id`, `batch_number`, `quantity_received`, `quantity_remaining`, `unit_cost`, `manufacture_date`, `expiry_date`, `status`) VALUES
(1, 1, 1, 'BATCH-MLK-2026A', 100.0000, 85.0000, 1.8000, '2026-09-01', '2026-12-15', 'active'),
(2, 2, 1, 'BATCH-COK-2026B', 240.0000, 210.0000, 0.6500, '2026-08-01', '2027-08-01', 'active'),
(3, 7, 1, 'BATCH-BRD-2026C', 50.0000, 38.0000, 1.1000, '2026-10-20', '2026-10-30', 'active')
ON DUPLICATE KEY UPDATE `batch_number`=VALUES(`batch_number`);

INSERT INTO `stock_balances` (`product_id`, `store_id`, `warehouse_id`, `quantity`) VALUES
(1, 1, NULL, 85.0000),
(2, 1, NULL, 210.0000),
(3, 1, NULL, 120.0000),
(4, 1, NULL, 65.0000),
(5, 1, NULL, 95.5000),
(6, 1, NULL, 40.0000),
(7, 1, NULL, 38.0000),
(8, 1, NULL, 350.0000),
-- Store 2 stock
(1, 2, NULL, 40.0000),
(2, 2, NULL, 90.0000),
(4, 2, NULL, 25.0000)
ON DUPLICATE KEY UPDATE `quantity`=VALUES(`quantity`);

-- Initial Stock Movements
INSERT INTO `stock_movements` (`product_id`, `store_id`, `movement_type`, `quantity_in`, `quantity_out`, `unit_cost`, `reference_doc`, `user_id`, `reason`) VALUES
(1, 1, 'opening_stock', 85.0000, 0.0000, 1.8000, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(2, 1, 'opening_stock', 210.0000, 0.0000, 0.6500, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(3, 1, 'opening_stock', 120.0000, 0.0000, 0.7000, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(4, 1, 'opening_stock', 65.0000, 0.0000, 8.5000, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(5, 1, 'opening_stock', 95.5000, 0.0000, 1.8000, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(6, 1, 'opening_stock', 40.0000, 0.0000, 7.2000, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(7, 1, 'opening_stock', 38.0000, 0.0000, 1.1000, 'OPENING-2026', 1, 'System Opening Balance Initialization'),
(8, 1, 'opening_stock', 350.0000, 0.0000, 0.2500, 'OPENING-2026', 1, 'System Opening Balance Initialization');

-- 11. Suppliers
INSERT INTO `suppliers` (`id`, `supplier_code`, `name`, `contact_person`, `phone`, `email`, `address`, `tax_number`, `payment_terms`, `credit_limit`, `current_balance`, `is_active`) VALUES
(1, 'SUP-001', 'Metro Wholesale Distributors', 'David Miller', '+1 (555) 789-0123', 'david@metrowholesale.com', '400 Industrial Way, Newark, NJ', 'US-99887766', 'Net 30', 25000.0000, 4200.0000, 1),
(2, 'SUP-002', 'Global Beverage Supply Inc', 'Karen White', '+1 (555) 321-7654', 'orders@globalbev.com', '75 Logistics Blvd, Secaucus, NJ', 'US-44332211', 'Net 15', 15000.0000, 1850.0000, 1),
(3, 'SUP-003', 'Sunrise Organic Farms & Produce', 'Roberto Gomez', '+1 (555) 654-9870', 'roberto@sunrisegreens.com', 'Farm Road 12, Chester, NY', 'US-11223344', 'Due on Receipt', 5000.0000, 320.0000, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 12. Customers & Khata Accounts
INSERT INTO `customers` (`id`, `customer_code`, `name`, `phone`, `email`, `address`, `membership_tier`, `credit_limit`, `current_balance`, `loyalty_points`, `is_active`) VALUES
(1, 'CUST-001', 'Walk-in Retail Customer', '0000000000', 'walkin@martpro.store', 'In-Store', 'standard', 0.0000, 0.0000, 0, 1),
(2, 'CUST-002', 'Michael Chang', '+1 (555) 345-6789', 'mchang@gmail.com', '124 Maple Ave, Brooklyn, NY', 'gold', 500.0000, 75.5000, 140, 1),
(3, 'CUST-003', 'Elena Rostova (Corner Cafe)', '+1 (555) 987-6543', 'elena@cornercafe.nyc', '88 Greenwich St, New York, NY', 'wholesale', 2000.0000, 480.0000, 420, 1),
(4, 'CUST-004', 'James & Lisa Taylor', '+1 (555) 456-7890', 'taylor.family@outlook.com', '55 5th Ave, Apt 4B, New York, NY', 'silver', 300.0000, 0.0000, 95, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Opening Ledger for Michael Chang (Owes 75.50)
INSERT INTO `customer_ledger` (`customer_id`, `transaction_type`, `reference_id`, `debit`, `credit`, `running_balance`, `notes`, `created_by`) VALUES
(2, 'manual_adjustment', 'OPENING-KHATA', 75.5000, 0.0000, 75.5000, 'Previous Month Account Receivable Carried Forward', 1),
(3, 'manual_adjustment', 'OPENING-KHATA', 480.0000, 0.0000, 480.0000, 'Commercial Khata Credit Carried Forward', 1);

-- 13. Safe Accounts & Initial Float
INSERT INTO `safe_accounts` (`id`, `store_id`, `name`, `current_balance`) VALUES
(1, 1, 'Central Store Main Drop Safe', 5000.0000),
(2, 2, 'Westside Branch Cash Safe', 2500.0000)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 14. Expense Categories
INSERT INTO `expense_categories` (`id`, `name`, `description`) VALUES
(1, 'Store Utilities (Electricity & Water)', 'Monthly utility electricity, water and heating bills'),
(2, 'Cleaning & Store Supplies', 'Paper towels, sanitizer, mop heads, garbage rolls'),
(3, 'Tea & Staff Refreshments', 'Daily staff snacks and refreshments'),
(4, 'Equipment Maintenance', 'Barcode scanner cables, POS thermal rolls, AC repair')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 15. Promotions
INSERT INTO `promotions` (`id`, `title`, `type`, `discount_value`, `start_date`, `end_date`, `min_order_amount`, `is_active`) VALUES
(1, 'Weekend Super Saver (5% Off Above $50)', 'percentage', 5.0000, '2026-01-01', '2026-12-31', 50.0000, 1),
(2, 'Welcome Back Fixed Voucher ($2 Off)', 'fixed_amount', 2.0000, '2026-01-01', '2026-12-31', 20.0000, 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

-- 16. Chart of Accounts
INSERT INTO `chart_of_accounts` (`id`, `code`, `name`, `type`, `is_active`) VALUES
(1, '1010', 'Cash on Hand (Registers & Drawer)', 'asset', 1),
(2, '1020', 'Main Store Safe Cash', 'asset', 1),
(3, '1030', 'Bank Operating Account', 'asset', 1),
(4, '1100', 'Accounts Receivable (Customer Khata)', 'asset', 1),
(5, '1200', 'Merchandise Inventory', 'asset', 1),
(6, '2010', 'Accounts Payable (Suppliers)', 'liability', 1),
(7, '2020', 'Sales Tax Payable', 'liability', 1),
(8, '4010', 'Retail Sales Revenue', 'revenue', 1),
(9, '4020', 'Sales Discounts & Promotions', 'revenue', 1),
(10, '5010', 'Cost of Goods Sold (COGS)', 'expense', 1),
(11, '6010', 'Store Operating Expenses', 'expense', 1)
ON DUPLICATE KEY UPDATE `code`=VALUES(`code`);

-- 17. Business Settings
INSERT INTO `business_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('app_name', 'MartPro', 'general'),
('app_tagline', 'Smart Store. Better Business.', 'general'),
('currency_symbol', '$', 'general'),
('currency_code', 'USD', 'general'),
('timezone', 'America/New_York', 'general'),
('tax_mode', 'exclusive', 'pos'),
('default_tax_rate', '5.00', 'pos'),
('allow_negative_stock', '0', 'inventory'),
('allow_manual_price_override', '1', 'pos'),
('max_cashier_discount_percent', '15.00', 'pos'),
('loyalty_spend_per_point', '10.00', 'loyalty'),
('loyalty_redemption_rate', '0.10', 'loyalty'),
('scale_barcode_prefix', '20', 'hardware'),
('scale_weight_decimals', '3', 'hardware'),
('receipt_printer_width', '80mm', 'hardware')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

-- 18. Sample Hardware Devices
INSERT INTO `hardware_devices` (`id`, `name`, `device_type`, `connection_type`, `port_identifier`, `baud_rate`, `is_active`, `notes`) VALUES
(1, 'Honeywell Xenon USB Barcode Scanner', 'barcode_scanner', 'usb_keyboard', 'HID-Keyboard', 9600, 1, 'Standard USB keyboard wedge emulation'),
(2, 'Epson TM-T88VI Thermal Receipt Printer', 'thermal_printer', 'browser_print', 'USB-001', 9600, 1, '80mm direct thermal slip printer with ESC/POS drawer kick'),
(3, 'Datalogic Magellan Scale (RS-232 / Web Serial)', 'weighing_scale', 'web_serial', 'COM3', 9600, 1, 'Continuous weight output 9600 8N1')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;
