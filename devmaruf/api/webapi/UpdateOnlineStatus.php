<?php
require_once __DIR__ . '/_app_api.php';
app_api_require_post();
$user = app_api_user();

if (app_setting_bool('presence_tracking_enabled', true)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $salt = getenv('APP_HASH_SALT') ?: 'local-presence';
    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . $salt) : null;
    $uaHash = $ua !== '' ? hash('sha256', $ua . '|' . $salt) : null;
    $uid = (int)$user['id'];
    $stmt = $conn->prepare('INSERT INTO app_user_presence(user_id,last_seen,ip_hash,user_agent_hash,updated_at) VALUES (?,NOW(),?,?,NOW()) ON DUPLICATE KEY UPDATE last_seen=NOW(),ip_hash=VALUES(ip_hash),user_agent_hash=VALUES(user_agent_hash),updated_at=NOW()');
    $stmt->bind_param('iss', $uid, $ipHash, $uaHash);
    $stmt->execute();
    $stmt->close();
}

app_api_send(['online' => true, 'lastSeen' => date('Y-m-d H:i:s')]);
