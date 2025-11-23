<?php
// inc/functions.php
require_once __DIR__ . '/db.php';

/**
 * Write to audit log
 */
function audit_log($user_identifier, $user_id, $action, $entity = null, $entity_id = null) {
    $db = db_connect();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_identifier, user_id, action, entity, entity_id, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param('sissis',
            $user_identifier,
            $user_id,
            $action,
            $entity,
            $entity_id,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Escape HTML safely
 */
function esc($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
