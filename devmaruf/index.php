<?php
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$currentHost = preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$originHost = $origin !== '' ? (string)(parse_url($origin, PHP_URL_HOST) ?: '') : '';
if ($origin !== '' && $currentHost !== '' && strcasecmp($originHost, $currentHost) === 0) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
date_default_timezone_set('Asia/Kolkata');
$res = [
    'code' => 11,
    'msg' => 'Url is not exist',
    'msgCode' => 5,
    'serviceNowTime' => date('Y-m-d H:i:s'),
];
http_response_code(404);
echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
