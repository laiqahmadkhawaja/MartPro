<?php
/**
 * MartPro Standardized JSON Response Formatter
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);

function json_response(bool $success, string $message = '', $data = null, array $errors = [], int $status_code = 200): void {
    // Clear any previous output buffering
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'errors'  => $errors
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success(string $message = 'Operation completed successfully.', $data = null, int $status_code = 200): void {
    json_response(true, $message, $data, [], $status_code);
}

function json_error(string $message = 'The operation could not be completed.', array $errors = [], int $status_code = 400, $data = null): void {
    json_response(false, $message, $data, $errors, $status_code);
}

function is_ajax_request(): bool {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        || (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false);
}
