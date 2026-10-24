<?php
/**
 * MartPro Application Constants
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

// User Roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_STORE_MANAGER', 'store_manager');
define('ROLE_HEAD_CASHIER', 'head_cashier');
define('ROLE_CASHIER', 'cashier');
define('ROLE_INVENTORY_MGR', 'inventory_manager');
define('ROLE_ACCOUNTANT', 'accountant');

// Payment Methods
const PAYMENT_METHODS = [
    'cash'            => 'Cash',
    'card'            => 'Credit / Debit Card',
    'digital_wallet'  => 'Digital Wallet / QR',
    'customer_credit' => 'Customer Credit (Khata)',
    'loyalty'         => 'Loyalty Points'
];

// Inventory Movement Types
const MOVEMENT_TYPES = [
    'opening_stock'    => 'Opening Stock',
    'purchase_receipt' => 'Purchase Receipt (GRN)',
    'sale'             => 'Customer Checkout Sale',
    'sales_return'     => 'Sales Return / Refund',
    'purchase_return'  => 'Supplier Return',
    'transfer_out'     => 'Transfer Dispatch Out',
    'transfer_in'      => 'Transfer Received In',
    'stock_adjustment' => 'Stock Adjustment',
    'damage'           => 'Damaged / Spoiled Stock',
    'waste'            => 'Waste / Expired Goods',
    'physical_count'   => 'Physical Count Variance'
];

// Stock Allocation Strategies
define('ALLOCATION_FIFO', 'FIFO');
define('ALLOCATION_FEFO', 'FEFO');

// Document Prefixes
define('PREFIX_INVOICE', 'INV');
define('PREFIX_PO', 'PO');
define('PREFIX_GRN', 'GRN');
define('PREFIX_PURCHASE_INV', 'PINV');
define('PREFIX_PURCHASE_RET', 'PRET');
define('PREFIX_SALES_RET', 'SRET');
define('PREFIX_TRANSFER', 'TRF');
define('PREFIX_ADJUSTMENT', 'ADJ');
define('PREFIX_COUNT', 'STK');
define('PREFIX_EXPENSE', 'EXP');
define('PREFIX_CASH', 'CSH');
