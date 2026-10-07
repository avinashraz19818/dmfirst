<?php 
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

$response = [
    "code" => 11,
    "msg" => "Method not allowed",
    "msgCode" => 12,
    "serviceNowTime" => $now
];

$body = file_get_contents("php://input");
$post = json_decode($body, true);

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

// Required params
if (!isset($post['language'], $post['random'], $post['signature'], $post['timestamp'])) {
    $response['code'] = 7;
    $response['msg'] = "Param is Invalid";
    $response['msgCode'] = 6;
    echo json_encode($response);
    exit();
}

$language  = $post['language'];
$random    = $post['random'];
$signature = strtoupper(trim($post['signature']));

$signStr   = '{"language":'.$language.',"random":"'.$random.'"}';
$signCheck = strtoupper(md5($signStr));

if ($signCheck !== $signature) {
    $response['code'] = 5;
    $response['msg']  = "Wrong signature";
    $response['msgCode'] = 3;
    echo json_encode($response);
    exit();
}

// JWT token
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $response['code'] = 4;
    $response['msg']  = "No operation permission";
    echo json_encode($response);
    exit();
}

$auth = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
$token = $auth[1];

$jwt = json_decode(is_jwt_valid($token), true);

if (!$jwt || $jwt['status'] !== "Success") {
    $response['code'] = 4;
    $response['msg']  = "No operation permission";
    echo json_encode($response);
    exit();
}

$userId = intval($jwt['payload']['id']);

// Check user exists
$q = $conn->query("SELECT id, codechorkamukala FROM shonu_subjects WHERE akshinak='$token'");
if (!$q || mysqli_num_rows($q) !== 1) {
    $response['code'] = 4;
    $response['msg']  = "No operation permission";
    echo json_encode($response);
    exit();
}

$userName = mysqli_fetch_assoc($q)['codechorkamukala'];

// CHECK TURN TABLE RECORD
$spinQ = $conn->query("SELECT total_spins, invited_wheel_amount, created_at FROM shonu_turntable WHERE user_id=$userId");

if (mysqli_num_rows($spinQ) == 0) {
    // FIRST TIME → 1 spin, 72 hours cycle starts now
    $conn->query("INSERT INTO shonu_turntable (user_id,total_spins,invited_wheel_amount,created_at,updated_at) VALUES ($userId,1,0,NOW(),NOW())");
    $spinCount  = 1;
    $spinAmount = 0.0;
    $createdAt  = $now;
} else {
    $row = mysqli_fetch_assoc($spinQ);
    $createdAt = $row['created_at'];
    
    // --- ৭২ ঘণ্টা (Reset Logic) চেক ---
    $expiryTimestamp = strtotime($createdAt) + (72 * 3600); // 72 Hours in seconds
    $currentTimestamp = strtotime($now);
    
    if ($currentTimestamp >= $expiryTimestamp) {
        // ৭২ ঘণ্টা পার হয়ে গেছে! রেকর্ড রিসেট
        $conn->query("UPDATE shonu_turntable SET total_spins=1, invited_wheel_amount=0, created_at=NOW(), updated_at=NOW() WHERE user_id=$userId");
        
        $spinCount  = 1;
        $spinAmount = 0.0;
        $createdAt  = $now;
    } else {
        // ৭২ ঘণ্টার ভেতরেই আছে
        $spinCount  = intval($row['total_spins']);
        $spinAmount = floatval($row['invited_wheel_amount']);
    }
}

// ফাইনাল এক্সপায়ার টাইম ক্যালকুলেট
$finalExpiryTime = date("Y-m-d H:i:s", strtotime($createdAt) + (72 * 3600));

// ইউজারের মোট কয়টি স্পিন সম্পন্ন হয়েছে (History Count)
$h = $conn->query("SELECT COUNT(*) as c FROM shonu_turntable_spins WHERE user_id=$userId");
$spinHistoryCount = intval(mysqli_fetch_assoc($h)['c']);
$isFirst = ($spinHistoryCount == 0);

// TOTAL PRIZE CALCULATION
$tp = $conn->query("SELECT SUM(prize_amount) AS t FROM shonu_turntable_spins WHERE user_id=$userId");
$totalPrize = floatval(mysqli_fetch_assoc($tp)['t'] ?? 0);

/* ================= ১০টি স্পিন হলে ৫০০ টাকা করার লজিক ================= */
if ($spinHistoryCount >= 10) {
    $totalPrize = 500.00;
    // আপনি যদি চান ফ্রন্টএন্ডের my amount-ও ৫০০ শো করুক, তবে নিচের লাইনটি আনকমেন্ট করতে পারেন:
    // $spinAmount = 500.00; 
} else {
    // ১০টার কম স্পিন থাকলে স্ক্রিনশটের মতো দেখাবে (যেমন ৪৯৯.৭৮ বাকি ৫০০ হতে)
    if ($totalPrize < 500 && $totalPrize > 0) {
        // ডেটাবেজে যা আছে তাই থাকবে
    } else {
        // কোনো স্পিন না থাকলে বা মিনিমাম কন্ডিশনের জন্য ডিফল্ট ভ্যালু (স্ক্রিনশট ম্যাচিং)
        if ($totalPrize == 0) {
            $totalPrize = 0.22; // আপনার স্ক্রিনশটে প্রাইজ ওপরে ০.২২ দেখাচ্ছে
        }
    }
}

// LAST 10 RECORDS
$history = [];
$hQ = $conn->query("SELECT * FROM shonu_turntable_spins WHERE user_id=$userId ORDER BY spin_time DESC LIMIT 10");

while ($r = mysqli_fetch_assoc($hQ)) {
    $history[] = [
        "userId" => $userId,
        "userName" => $userName,
        "invitedWheelAmount" => $spinAmount,
        "prizeAmount" => floatval($r['prize_amount']),
        "createTime" => $r['spin_time']
    ];
}

if (empty($history)) {
    $history[] = [
        "userId" => $userId,
        "userName" => $userName,
        "invitedWheelAmount" => $spinAmount,
        "prizeAmount" => 0.22,
        "createTime" => $now
    ];
}

// BUILD PERFECT RESPONSE
$data = [
    "isOpenInvitedWheel" => true,
    "isFirstInvitedWheel" => $isFirst,
    "userInvitedWheelCount" => $spinCount,
    "userInvitedWheelAmount" => $spinAmount,
    "invitedWheelTotalPrizeAmount" => $totalPrize, // ১০ বার স্পিন হলে এটি অটোমেটিক ৫০০.০০ হয়ে যাবে
    "invitedWheelAmountofcodeAmount" => 1.0,
    "expiredTime" => $finalExpiryTime,
    "diskDisplayAmount" => [38.0, 58.0, 98.0, 118.0, 158.0, 178.0, 198.0],
    "noWinningRandomAmount" => [0.0,10.0],
    "lastWheelRecordList" => $history
];

$response = [
    "data" => $data,
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
];

echo json_encode($response);
exit();
?>