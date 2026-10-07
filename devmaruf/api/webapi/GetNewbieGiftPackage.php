<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../conn.php";
include "../../functions2.php";

header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Dhaka");
$now   = date("Y-m-d H:i:s");
$today = date("Y-m-d");

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode([
        'code'           => 4,
        'msg'            => 'Authorization missing',
        'serviceNowTime' => $now
    ]);
    exit;
}

$bearer = explode(' ', trim($_SERVER['HTTP_AUTHORIZATION']));
$token  = $bearer[1] ?? '';

if ($token === '') {
    http_response_code(401);
    echo json_encode([
        'code'           => 4,
        'msg'            => 'Token missing',
        'serviceNowTime' => $now
    ]);
    exit;
}

$jwtResult = is_jwt_valid($token);
$auth      = is_array($jwtResult) ? $jwtResult : json_decode($jwtResult, true);

if (!$auth || ($auth['status'] ?? '') !== 'Success') {
    http_response_code(401);
    echo json_encode([
        'code'           => 4,
        'msg'            => 'Invalid token',
        'serviceNowTime' => $now
    ]);
    exit;
}

$balakedara = (int)($auth['payload']['id'] ?? ($auth['payload']['user_id'] ?? 0));

if ($balakedara <= 0) {
    http_response_code(400);
    echo json_encode([
        'code'           => 4,
        'msg'            => 'User id missing in token',
        'serviceNowTime' => $now
    ]);
    exit;
}

$totalDays    = 7;
$rewardPerDay = 2.00;
$totalAmount  = $totalDays * $rewardPerDay; // 

$q = $conn->prepare("
    SELECT dinankavannuracisi, sthiti
    FROM thevani
    WHERE balakedara = ? AND sthiti = 1
    ORDER BY STR_TO_DATE(dinankavannuracisi, '%Y-%m-%d %H:%i:%s') ASC
    LIMIT 1
");
$q->bind_param("i", $balakedara);
$q->execute();
$info = $q->get_result()->fetch_assoc();
$q->close();

// never recharged → show locked pack
if (!$info) {
    echo json_encode([
        "data" => [
            "id"             => 0,
            "title"          => "Newbie Gift Pack",
            "description"    => "Newbie Gift Pack",
            "amount"         => (float)$totalAmount,
            "status"         => 0,             
            "receivedNumber" => 0,
            "totalNumber"    => $totalDays,
            "userId"         => $balakedara,
            "walletBalance"  => 0.00,
            "canReceive"     => false,
            "reason"         => "Not recharged",
            "nextClaim"      => null
        ],
        "code"           => 0,
        "msg"            => "Succeed",
        "msgCode"        => 0,
        "serviceNowTime" => $now
    ]);
    exit;
}

$firstRechargeDate = date("Y-m-d", strtotime($info['dinankavannuracisi']));
$endDate           = date("Y-m-d", strtotime("$firstRechargeDate +7 days")); // same as ReceiveAward.php

$chk = $conn->prepare("SELECT * FROM daily_reward WHERE user_id = ? LIMIT 1");
$chk->bind_param("i", $balakedara);
$chk->execute();
$res = $chk->get_result();

if ($res->num_rows == 0) {

    $depositVal = 0.00;

    $ins = $conn->prepare("
        INSERT INTO daily_reward
            (user_id, deposit_amount, reward_per_day, received_days, status, next_claim_at, created_at)
        VALUES (?, ?, ?, 0, 0, NOW(), NOW())
    ");
    $ins->bind_param("idd", $balakedara, $depositVal, $rewardPerDay);
    $ins->execute();
    $ins->close();

    // reload
    $chk->execute();
    $res = $chk->get_result();
}

$r = $res->fetch_assoc();
$chk->close();

$received    = isset($r['received_days']) ? (int)$r['received_days'] : 0;
$nextClaimAt = $r['next_claim_at'] ?? $now;

if ($received < 0) $received = 0;
if ($received > $totalDays) $received = $totalDays;

$wq = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? LIMIT 1");
$wq->bind_param("i", $balakedara);
$wq->execute();
$wrow = $wq->get_result()->fetch_assoc();
$wq->close();
$walletBalance = $wrow ? (float)$wrow['motta'] : 0.00;


$isCompleted   = ($received >= $totalDays);
$isExpiredTime = ($today > $endDate);   // 7 din ka window khatam

$canReceive = false;
$reason     = null;

if ($isExpiredTime) {
    $canReceive = false;
    $reason     = "Reward period expired";
    $status     = 2; // completed/finished
} elseif ($isCompleted) {
    $canReceive = false;
    $reason     = "Completed";
    $status     = 2;
} else {
    if ($now < $nextClaimAt) {
        $canReceive = false;
        $reason     = "Already claimed, wait for next claim time";
        $status     = 0; // waiting
    } else {
        $canReceive = true;
        $reason     = "Can receive";
        $status     = 1; // can claim now
    }
}

echo json_encode([
    "data" => [
        "id"             => isset($r['id']) ? (int)$r['id'] : 0,
        "title"          => "Newbie Gift Pack",
        "description"    => "Newbie Gift Pack",
        "amount"         => (float)$totalAmount, // 
        "status"         => $status,             // 
        "receivedNumber" => $received,
        "totalNumber"    => $totalDays,
        "userId"         => $balakedara,
        "walletBalance"  => $walletBalance,
        "canReceive"     => $canReceive,
        "reason"         => $reason,
        "nextClaim"      => $isExpiredTime ? null : $nextClaimAt
    ],
    "code"           => 0,
    "msg"            => "Succeed",
    "msgCode"        => 0,
    "serviceNowTime" => $now
]);
exit;