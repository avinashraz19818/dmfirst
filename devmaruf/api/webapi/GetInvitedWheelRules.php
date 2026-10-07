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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");

function api($code, $msg, $data = null, $msgCode = 0){
    echo json_encode([
        "code" => $code,
        "msg" => $msg,
        "msgCode" => $msgCode,
        "serviceNowTime" => date("Y-m-d H:i:s"),
        "data" => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$response = [
    "data" => "When the accumulated amount reaches the displayed amount, you can apply for a bonus.<div><br></div><div><br></div><div>Every time you invite a friend to register,Minimum deposit<font color=\"#ff0000\">100BDT or more</font>! you will get 1-time free spin.</div><div><br></div><div><br></div><div>The activity lasts for 3 days. After the activity period ends, the accumulated rewards will be reset, and the activity will start again.</div><div><br></div><div><br></div><div>Each user can enjoy 1 free spin opportunity per day.</div><div><br></div><div><br></div><div>After approval of the application, the bonus will be directly deposited into your balance.</div><div><br></div><div><br></div><div>The bonus requires one time of wagering before withdrawal.</div><div><br></div><div><br></div><div>To successfully recommend someone, the invitee must use the inviter's invitation code.</div><div><br></div><div><font color=\"#ff9c00\">STAR</font><font color=\"#9c00ff\">WIN</font> reserves the interpretation of the activity. If you have any questions, please get in touch with customer service.</div>",
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => date("Y-m-d H:i:s")
];

http_response_code(200);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
?>