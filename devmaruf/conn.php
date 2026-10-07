<?php
date_default_timezone_set('Asia/Kolkata');

// Retained endpoints read this key directly. Keep a missing header from
// becoming a PHP warning while their normal authorization checks reject it.
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
        ? (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        : 'Bearer ';
}

// Several legacy read-only handlers still emit a reflected CORS header. This
// callback runs immediately before PHP sends headers and replaces that value
// with the same-origin/explicit-allowlist policy used by the modern API layer.
if (function_exists('header_register_callback') && !defined('BDG_CORS_HEADER_GUARD')) {
    define('BDG_CORS_HEADER_GUARD', true);
    header_register_callback(static function (): void {
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') return;
        $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
        $requestHost = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        $allowed = array_filter(array_map('trim', explode(',', (string)getenv('APP_ALLOWED_ORIGINS'))));
        if (($originHost !== '' && $requestHost !== '' && hash_equals($requestHost, $originHost)) || in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin', false);
        }
    });
}

$dbConfig = require dirname(__DIR__) . '/config/database.php';
if (!defined('DB_SERVER')) define('DB_SERVER', (string)$dbConfig['host']);
if (!defined('DB_USERNAME')) define('DB_USERNAME', (string)$dbConfig['user']);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', (string)$dbConfig['password']);
if (!defined('DB_NAME')) define('DB_NAME', (string)$dbConfig['database']);

$conn = mysqli_init();
if ($conn === false) {
    throw new RuntimeException('Database driver initialization failed');
}
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 8);
if (!$conn->real_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, (int)$dbConfig['port'])) {
    error_log('[database] Connection failed: ' . $conn->connect_error);
    // Keep public output generic; callers can handle the unavailable connection.
}
if (!$conn->connect_errno) {
    $conn->set_charset((string)$dbConfig['charset']);
}
?>
