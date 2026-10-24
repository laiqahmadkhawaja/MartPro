<?php
/**
 * MartPro Core Helper Functions
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/database.php';

function get_setting(string $key, string $default = ''): string {
    static $settings_cache = null;
    if ($settings_cache === null) {
        $db = get_db();
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM business_settings");
            $settings_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $settings_cache = [];
        }
    }
    return $settings_cache[$key] ?? $default;
}

function format_money($amount): string {
    $symbol = get_setting('currency_symbol', '$');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function format_qty($qty): string {
    $num = (float)$qty;
    // Show decimals only if non-integer
    return (floor($num) == $num) ? number_format($num, 0) : number_format($num, 2);
}

function format_datetime(?string $dt): string {
    if (empty($dt)) return '—';
    return date('M d, Y h:i A', strtotime($dt));
}

function format_date(?string $d): string {
    if (empty($d)) return '—';
    return date('M d, Y', strtotime($d));
}

function get_active_stores(): array {
    $db = get_db();
    try {
        $stmt = $db->query("SELECT id, code, name, phone, address FROM stores WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function get_current_store(): array {
    $store_id = current_store_id();
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM stores WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $store_id]);
    $store = $stmt->fetch();
    return $store ?: [
        'id' => 1,
        'code' => 'STORE-001',
        'name' => 'MartPro Central Store',
        'receipt_header' => 'MartPro Supermarket',
        'receipt_footer' => 'Thank you for shopping with us!'
    ];
}
