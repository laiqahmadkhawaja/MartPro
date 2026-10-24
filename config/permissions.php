<?php
/**
 * MartPro Permission Slugs Definition
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

const APP_PERMISSIONS = [
    'pos.access'          => 'Access POS Checkout Screen',
    'pos.discount'        => 'Apply Line or Bill Discounts',
    'pos.price_override'  => 'Manually Override Product Selling Price',
    'pos.void'            => 'Void or Cancel Transactions',
    'pos.returns'         => 'Process Sales Returns & Refunds',
    'inventory.view'      => 'View Inventory Stock Balances',
    'inventory.adjust'    => 'Perform Stock Adjustments',
    'inventory.transfer'  => 'Dispatch and Receive Stock Transfers',
    'inventory.count'     => 'Conduct Stock Counts & Audits',
    'purchasing.manage'   => 'Create POs, Receive GRNs, and Invoices',
    'products.manage'     => 'Create and Edit Products, Categories, Units',
    'customers.manage'    => 'Manage Customer Records & Credit Accounts',
    'shifts.manage'       => 'Open, Close Shifts, Drops, & Print Reports',
    'reports.view'        => 'Access Business Reports & Analytics',
    'accounting.view'     => 'View Chart of Accounts and Ledgers',
    'settings.manage'     => 'Configure Store, Business, and Hardware Settings',
    'users.manage'        => 'Manage User Accounts, Roles, and Permissions'
];
