<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../conn.php";
include "../../functions2.php";

header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Dhaka");
$now = date("Y-m-d H:i:s");

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['code' => 4, 'msg' => 'Authorization missing']);
    exit;
}

$bearer = explode(' ', trim($_SERVER['HTTP_AUTHORIZATION']));
$token  = $bearer[1] ?? '';

$jwtResult = is_jwt_valid($token);
$auth      = is_array($jwtResult) ? $jwtResult : json_decode($jwtResult, true);

if (!$auth || ($auth['status'] ?? '') !== 'Success') {
    http_response_code(401);
    echo json_encode(['code' => 4, 'msg' => 'Invalid token']);
    exit;
}

$balakedara = (int)($auth['payload']['id'] ?? ($auth['payload']['user_id'] ?? 0));

if ($balakedara <= 0) {
    http_response_code(400);
    echo json_encode(['code' => 4, 'msg' => 'User id missing']);
    exit;
}

// INSERT OR UPDATE লজিক ব্যবহার করা হয়েছে যেন ডেটা ১০০% সেভ হয়
$ins = $conn->prepare("INSERT INTO user_guidelines (user_id, is_finished, created_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE is_finished = 1");
if (!$ins) {
    // যদি ডাটাবেজ টেবিল তৈরিতে ভুল থাকে তবে এখানে এরর দেখাবে
    echo json_encode(['code' => 500, 'msg' => 'DB Error: ' . $conn->error]);
    exit;
}
$ins->bind_param("is", $balakedara, $now);
$ins->execute();
$ins->close();

echo json_encode([
    "data"           => true,
    "code"           => 0,
    "msg"            => "Succeed",
    "msgCode"        => 0,
    "traceId"        => "",
    "serviceNowTime" => $now
]);
exit;
?>