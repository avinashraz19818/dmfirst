<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php

include "../../conn.php";
include "../../functions2.php";

	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: *');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

// Base64 URLs array
$data = [
    "aHR0cHM6Ly9zaWtraW1nZy5vcmc=",
    "aHR0cHM6Ly9zaWtraW1nZy5uZXQ=",
    "aHR0cHM6Ly9zaWtraW1nZy5pbg=="
];

// Always success (HTTP 200)
http_response_code(200);

$res = [
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now,
    "data" => $data
];

echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
?>