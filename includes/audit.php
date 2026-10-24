<?php
/**
 * MartPro Audit Trail Logger
 * Append-only recording of user actions and critical entity mutations
 */

defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
require_once __DIR__ . '/../config/database.php';

function audit_log(
    string $action,
    string $module,
    ?string $entity_type = null,
    ?string $entity_id = null,
    $old_values = null,
    $new_values = null
): void {
    try {
        $db = get_db();
        $user_id = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown';

        // Filter out sensitive keys from JSON logs
        $sanitize = function ($data) {
            if (!is_array($data)) return $data;
            $sensitive = ['password', 'password_hash', 'cvv', 'card_number', 'secret', 'token', 'csrf_token'];
            foreach ($sensitive as $k) {
                if (isset($data[$k])) {
                    $data[$k] = '***REDACTED***';
                }
            }
            return $data;
        };

        $old_json = $old_values !== null ? json_encode($sanitize($old_values), JSON_UNESCAPED_UNICODE) : null;
        $new_json = $new_values !== null ? json_encode($sanitize($new_values), JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare("INSERT INTO audit_logs 
            (user_id, action, module, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (:uid, :action, :module, :etype, :eid, :old, :new, :ip, :ua, NOW())");
        $stmt->execute([
            'uid'    => $user_id,
            'action' => $action,
            'module' => $module,
            'etype'  => $entity_type,
            'eid'    => $entity_id,
            'old'    => $old_json,
            'new'    => $new_json,
            'ip'     => $ip,
            'ua'     => substr($agent, 0, 255)
        ]);
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}
