<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/json; charset=utf-8");

include "../../conn.php";
include "../../functions2.php";

date_default_timezone_set("Asia/Dhaka");
$now   = date("Y-m-d H:i:s");
$today = date("Y-m-d");

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $now,
];

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    $balakedara = 0;
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '');

    if (!empty($authHeader)) {
        $bearer = explode(' ', trim($authHeader));
        $token  = $bearer[1] ?? '';
        if ($token !== '') {
            $jwtResult = is_jwt_valid($token);
            $auth      = is_array($jwtResult) ? $jwtResult : json_decode($jwtResult, true);
            if ($auth && ($auth['status'] ?? '') === 'Success') {
                $balakedara = (int)($auth['payload']['id'] ?? ($auth['payload']['user_id'] ?? 0));
            }
        }
    }

    $totalDays    = 7;
    $rewardPerDay = 2.00;

    $isFinishUserGuidelines = "0"; 
    if ($balakedara > 0) {
        $gl_chk = $conn->prepare("SELECT user_id FROM user_guidelines WHERE user_id = ? LIMIT 1");
        if ($gl_chk) {
            $gl_chk->bind_param("i", $balakedara);
            $gl_chk->execute();
            $gl_res = $gl_chk->get_result();
            if ($gl_res->num_rows > 0) {
                $isFinishUserGuidelines = "1"; 
            }
            $gl_chk->close();
        }
    }

    $walletBalance = 0.00;
    if ($balakedara > 0) {
        $wq = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? LIMIT 1");
        if ($wq) {
            $wq->bind_param("i", $balakedara);
            $wq->execute();
            $wrow = $wq->get_result()->fetch_assoc();
            $wq->close();
            $walletBalance = $wrow ? (float)$wrow['motta'] : 0.00;
        }
    }

    $received = 0;
    $todayRewards = 0.0;
    $totalRewards = 0.0;
    $newbieGiftPackCount = 0;

    $response_data = [
        "isOpenJackpotReward"     => "1",
        "isOpenWashCode"          => "1",
        "unJackpotCount"          => 0,
        "isOpenActivityAward"     => "1",
        "isFinishUserGuidelines"  => $isFinishUserGuidelines,
        "unWeeklyAwardCount"      => 0,
        "isFirstUserDayRequest"   => true,
        "isOpenChampion"          => "0",
        "walletBalance"           => $walletBalance,
        "todayRewards"            => $todayRewards,
        "totalRewards"            => $totalRewards,
        "receivedDays"            => (int)$received,
        "totalDays"               => (int)$totalDays,
        "rewardPerDay"            => (float)$rewardPerDay,
        "amount"                  => (float)$rewardPerDay,
        "awardAmount"             => (float)$rewardPerDay,
        "received_days"           => (int)$received,
        "total_days"              => (int)$totalDays,
        "reward_per_day"          => (float)$rewardPerDay,
        "award_amount"            => (float)$rewardPerDay,
        "newbieGiftPack"          => [
            "receivedDays"  => (int)$received,
            "totalDays"     => (int)$totalDays,
            "awardAmount"   => (float)$rewardPerDay,
            "status"        => $newbieGiftPackCount
        ]
    ];

    echo json_encode([
        "data"             => $response_data,
        "code"             => 0,
        "msg"              => "Succeed",
        "msgCode"          => 0,
        "traceId"          => "",
        "serviceNowTime"   => $now
    ]);
    exit;
} else {
    http_response_code(405);
    echo json_encode($res);
    exit;
}
?>