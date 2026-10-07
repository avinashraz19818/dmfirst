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

$data = [
    'extend1' => 0.0,
    'extend2' => 0.0,
    'extend3' => 0.0,
    'list' => [],
    'pageNo' => 1,
    'totalPage' => 0,
    'totalCount' => 0
];

http_response_code(200);
echo json_encode([
  'code' => 0,
  'msg' => 'Succeed',
  'msgCode' => 0,
  'serviceNowTime' => $now,
  'data' => $data
]);
exit;
?>