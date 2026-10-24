<?php
/**
 * MartPro CSRF Protection Helper
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_field(): string {
    $token = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_token(): void {
    if (!verify_csrf_token()) {
        if (function_exists('is_ajax_request') && is_ajax_request()) {
            json_error('Security token invalid or expired. Please refresh the page.', [], 403);
        }
        http_response_code(403);
        die('<h3>403 Forbidden: Security Token Mismatch</h3><p>Your session or CSRF token has expired. Please refresh and try again.</p>');
    }
}
