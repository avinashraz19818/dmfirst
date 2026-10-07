<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../conn.php";
include "../../functions2.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

date_default_timezone_set("Asia/Dhaka");
$now   = date("Y-m-d H:i:s");
$today = date("Y-m-d");

// ১. Authorization Header চেক
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
$rewardPerDay = 2.00; // প্রতিদিনের রিওয়ার্ড অ্যামাউন্ট

// ২. রিচার্জ হিস্ট্রি চেক করা
$q = $conn->prepare("
    SELECT dinankavannuracisi, sthiti
    FROM thevani
    WHERE balakedara = ? AND sthiti = 1
    ORDER BY STR_TO_DATE(dinankavannuracisi, '%Y-%m-%d %H:%i:%s') ASC
    LIMIT 1
");
if (!$q) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => 'Query preparation failed: ' . $conn->error]);
    exit;
}

$q->bind_param("i", $balakedara);
$q->execute();
$info = $q->get_result()->fetch_assoc();
$q->close();

if (!$info) {
    echo json_encode([
        "code"           => 1,
        "msg"            => "You must recharge first to claim rewards",
        "serviceNowTime" => $now
    ]);
    exit;
}

$firstRechargeDate = date("Y-m-d", strtotime($info['dinankavannuracisi']));
$endDate           = date("Y-m-d", strtotime("$firstRechargeDate +7 days"));

if ($today > $endDate) {
    echo json_encode([
        "code"           => 1,
        "msg"            => "Reward period has expired",
        "serviceNowTime" => $now
    ]);
    exit;
}

// ৩. ডেইলি রিওয়ার্ড স্ট্যাটাস চেক
$chk = $conn->prepare("SELECT * FROM daily_reward WHERE user_id = ? LIMIT 1");
$chk->bind_param("i", $balakedara);
$chk->execute();
$res = $chk->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "code"           => 1,
        "msg"            => "Reward record not found",
        "serviceNowTime" => $now
    ]);
    exit;
}

$r = $res->fetch_assoc();
$chk->close();

$received    = (int)($r['received_days'] ?? 0);
$nextClaimAt = $r['next_claim_at'] ?? $now;

// চেক ১: ৭ দিন শেষ হয়েছে কিনা
if ($received >= $totalDays) {
    echo json_encode([
        "code"           => 1,
        "msg"            => "You have already claimed all 7 days rewards",
        "serviceNowTime" => $now
    ]);
    exit;
}

// চেক ২: আজকের সময় পূর্ণ হয়েছে কিনা
if ($now < $nextClaimAt) {
    echo json_encode([
        "code"           => 1,
        "msg"            => "Already claimed for today. Please wait for the next claim time.",
        "serviceNowTime" => $now
    ]);
    exit;
}

// ৪. ক্লেইম প্রসেস ও ডাটাবেজ আপডেট (Database Transaction)
$conn->begin_transaction();

try {
    // ক) ওয়ালেট ব্যালেন্স আপডেট করা
    $upWallet = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ?");
    if (!$upWallet) {
        throw new Exception("Wallet update prepare failed: " . $conn->error);
    }
    $upWallet->bind_param("di", $rewardPerDay, $balakedara);
    $upWallet->execute();
    $upWallet->close();

    // খ) পরবর্তী ক্লেইম টাইম ও ডে সংখ্যা আপডেট
    $nextClaimDate = date("Y-m-d H:i:s", strtotime("+1 day"));
    $newReceived   = $received + 1;
    $status        = ($newReceived >= $totalDays) ? 1 : 0;

    $upReward = $conn->prepare("
        UPDATE daily_reward 
        SET received_days = ?, 
            next_claim_at = ?, 
            status = ? 
        WHERE user_id = ?
    ");
    if (!$upReward) {
        throw new Exception("Reward update prepare failed: " . $conn->error);
    }
    $upReward->bind_param("isii", $newReceived, $nextClaimDate, $status, $balakedara);
    $upReward->execute();
    $upReward->close();

    // গ) হিস্ট্রি টেবিল অপশনাল চেক (যদি টেবিল থাকে সেভ করবে, না থাকলে স্কিপ করবে)
    $remark = "Newbie Gift Pack Reward Day " . $newReceived;
    @$conn->query("
        INSERT INTO history (balakedara, motta, vivarana, dinankavannuracisi) 
        VALUES ($balakedara, $rewardPerDay, '$remark', '$now')
    ");

    // সফল হলে কমিট
    $conn->commit();

    // নতুন ওয়ালেট ব্যালেন্স ফেচ করা
    $wq = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? LIMIT 1");
    $wq->bind_param("i", $balakedara);
    $wq->execute();
    $wrow = $wq->get_result()->fetch_assoc();
    $wq->close();
    $newWalletBalance = $wrow ? (float)$wrow['motta'] : 0.00;

    // সফল রেসপন্স
    echo json_encode([
        "code" => 0,
        "msg"  => "Success",
        "data" => [
            "rewardAmount"  => (float)$rewardPerDay,
            "receivedDays"  => $newReceived,
            "totalDays"     => $totalDays,
            "walletBalance" => $newWalletBalance,
            "nextClaimAt"   => $nextClaimDate
        ],
        "serviceNowTime" => $now
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "code"           => 500,
        "msg"            => "Failed to process reward: " . $e->getMessage(),
        "serviceNowTime" => $now
    ]);
    exit;
}
?>