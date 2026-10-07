<?php
// Updated by System - 2026-07-20
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

// Read body
$body = file_get_contents("php://input");
$post = json_decode($body, true);

// Default response
$response = [
    "data" => null,
    "code" => 11,
    "msg" => "Method not allowed",
    "msgCode" => 12,
    "serviceNowTime" => $now
];

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

// Validate Params
if (!isset($post['language'], $post['random'], $post['signature'], $post['timestamp'])) {
    $response["code"] = 7;
    $response["msg"] = "Param is Invalid";
    $response["msgCode"] = 6;
    echo json_encode($response);
    exit();
}

$language   = $post['language'];
$random     = $post['random'];
$signature  = strtoupper($post['signature']);

$signStr = '{"language":'.$language.',"random":"'.$random.'"}';
$signCheck = strtoupper(md5($signStr));

if ($signature !== $signCheck) {
    $response["code"] = 5;
    $response["msg"]  = "Wrong signature";
    $response["msgCode"] = 3;
    echo json_encode($response);
    exit();
}

// TOKEN VERIFY
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $response["code"] = 4;
    $response["msg"]  = "No operation permission";
    echo json_encode($response);
    exit();
}

$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
$token = trim($bearer[1]);

$jwt = json_decode(is_jwt_valid($token), true);
if (!$jwt || $jwt["status"] !== "Success") {
    $response["code"] = 4;
    $response["msg"]  = "No operation permission";
    echo json_encode($response);
    exit();
}

$userId = intval($jwt["payload"]["id"]);

// Check user exists
$uQ = $conn->query("SELECT codechorkamukala FROM shonu_subjects WHERE akshinak='$token'");
if (!$uQ || mysqli_num_rows($uQ) !== 1) {
    $response["code"] = 4;
    $response["msg"] = "No operation permission";
    echo json_encode($response);
    exit();
}
$userName = mysqli_fetch_assoc($uQ)['codechorkamukala'];

// ---- ইউজারের এভেলেবল স্পিন চেক ----
$ttQ = $conn->query("SELECT total_spins, invited_wheel_amount FROM shonu_turntable WHERE user_id=$userId");
$availableSpins = 0;
if (mysqli_num_rows($ttQ) > 0) {
    $ttRow = mysqli_fetch_assoc($ttQ);
    $availableSpins = intval($ttRow['total_spins']);
}

if ($availableSpins <= 0) {
    $response["code"] = 1;
    $response["msg"]  = "You don't have enough spins!";
    echo json_encode($response);
    exit();
}

// ---- ইউজারের বর্তমান স্পিন নাম্বার চেক (টোটাল কয়টি স্পিন হিস্ট্রি আছে + ১) ----
$spinCountQ = $conn->query("SELECT COUNT(*) AS c FROM shonu_turntable_spins WHERE user_id=$userId");
$spinHistoryCount = intval(mysqli_fetch_assoc($spinCountQ)['c']);
$currentSpinNumber = $spinHistoryCount + 1; // বর্তমান স্পিন রান (১ থেকে ১০)
$isFirstTime = ($spinHistoryCount == 0);


// -------------------------------------------------------------
//  MODE 1 → FIRST TIME USER (১ম স্পিন - বক্স ওপেন)
// -------------------------------------------------------------
if ($isFirstTime) {

    // ১ম স্পিনেই বড় অ্যামাউন্ট দিয়ে ৪০০ টাকার ওপরে নিয়ে যাওয়ার জন্য বক্স ফিক্সড করা হলো
    $boxAmounts = [
        485.50,
        491.20,
        475.80,
        488.40
    ];

    // pick random box
    $selectedIndex = array_rand($boxAmounts);
    $selectedAmount = $boxAmounts[$selectedIndex];

    // Prepare list
    $firstWheelList = [];
    foreach ($boxAmounts as $i => $amt) {
        $firstWheelList[] = [
            "amount" => floatval($amt),
            "isSelected" => ($i == $selectedIndex)
        ];
    }

    // Insert spin record
    $amtDb = number_format($selectedAmount, 2, '.', '');
    $userNameEsc = mysqli_real_escape_string($conn, $userName);
    $conn->query("
        INSERT INTO shonu_turntable_spins (user_id, prize_amount, spin_time, user_name)
        VALUES ($userId, $amtDb, '$now', '$userNameEsc')
    ");

    // Update turntable spin count
    $ttCheck = $conn->query("SELECT id FROM shonu_turntable WHERE user_id=$userId");
    if (mysqli_num_rows($ttCheck) == 0) {
        $conn->query("
            INSERT INTO shonu_turntable (user_id, total_spins, invited_wheel_amount, created_at, updated_at)
            VALUES ($userId, 0, $amtDb, NOW(), NOW())
        ");
    } else {
        $conn->query("
            UPDATE shonu_turntable
            SET total_spins = GREATEST(total_spins - 1, 0),
                invited_wheel_amount = $amtDb,
                updated_at = NOW()
            WHERE user_id = $userId
        ");
    }

    // Final response
    $response = [
        "data" => [
            "isFirstInvitedWheel" => true,
            "prizeAmount" => floatval($selectedAmount),
            "isWin" => ($selectedAmount > 0),
            "firstInvitedWheelDatas" => $firstWheelList
        ],
        "code" => 0,
        "msg" => "Succeed",
        "msgCode" => 0,
        "serviceNowTime" => $now
    ];

    echo json_encode($response);
    exit();
}


// -------------------------------------------------------------
//  MODE 2 → NORMAL SPIN & 10TH SPIN MAGIC (২ থেকে ১০ নম্বর স্পিন)
// -------------------------------------------------------------

// এ পর্যন্ত ইউজার মোট কত টাকা জিতেছে তা বের করা
$hPrizeQ = $conn->query("SELECT SUM(prize_amount) as earned FROM shonu_turntable_spins WHERE user_id = $userId");
$prizeData = mysqli_fetch_assoc($hPrizeQ);
$totalEarnedSoFar = floatval($prizeData['earned'] ?? 0);

$prize = 0.00;

if ($currentSpinNumber < 10) {
    // ২ থেকে ৯ নম্বর স্পিনের জন্য চাকার স্বাভাবিক র‍্যান্ডম প্রাইজ ডিস্ট্রিবিউশন
    $wheelPrizes = [
        ['amount'=>0.50,'probability'=>0.35],
        ['amount'=>1.00,'probability'=>0.25],
        ['amount'=>2.00,'probability'=>0.15],
        ['amount'=>0.20,'probability'=>0.10],
        ['amount'=>0.10,'probability'=>0.08],
        ['amount'=>3.00,'probability'=>0.05],
        ['amount'=>5.00,'probability'=>0.02]
    ];

    $r = mt_rand() / mt_getrandmax();
    $cum = 0;
    $selected = null;

    foreach ($wheelPrizes as $p) {
        $cum += $p['probability'];
        if ($r <= $cum) {
            $selected = $p;
            break;
        }
    }

    if (!$selected) $selected = ['amount'=>0.10];
    
    $prize = floatval($selected['amount']);
    
    // সেফটি চেক: যদি কোনো কারণে ৯ নম্বর স্পিনেই ব্যালেন্স ৫০০ বা তার বেশি টাচ করতে চায়, তবে তা আটকে দেওয়া হবে
    if (($totalEarnedSoFar + $prize) >= 499.50) {
        $prize = 0.05; 
    }
} 
else if ($currentSpinNumber == 10) {
    // 🔥 ঠিক ১০ নম্বর স্পিনে ম্যাজিক লজিক! ৫০০ মেলাতে আর যতটুকু বাকি আছে নিখুঁতভাবে পুরো টাকাটাই প্রাইজ দেওয়া হবে।
    $prize = 500.00 - $totalEarnedSoFar;
    if ($prize < 0) {
        $prize = 0.00;
    }
} 
else {
    // ১০টার বেশি স্পিন যদি অ্যাডমিন প্যানেল থেকে কোনোভাবে এক্সট্রা দেওয়া হয়ে থাকে
    $prize = 50;
}

$isWin = ($prize > 0);

// INSERT SPIN RECORD
$prizeDb = number_format($prize, 2, '.', '');
$userNameEsc = mysqli_real_escape_string($conn, $userName);

$conn->query("
    INSERT INTO shonu_turntable_spins (user_id, prize_amount, spin_time, user_name)
    VALUES ($userId, $prizeDb, '$now', '$userNameEsc')
");

// ১০ নম্বর স্পিন হয়ে গেলে ফাইনাল সেফটি হিসেবে ডেটাবেজেও মেম্বারের টোটাল ব্যালেন্স ৫০০ ফিক্সড করে দেওয়া হচ্ছে
if ($currentSpinNumber == 10) {
    $conn->query("
        UPDATE shonu_turntable
        SET total_spins = GREATEST(total_spins - 1, 0),
            invited_wheel_amount = 500.00,
            updated_at = NOW()
        WHERE user_id = $userId
    ");
} else {
    $conn->query("
        UPDATE shonu_turntable
        SET total_spins = GREATEST(total_spins - 1, 0),
            invited_wheel_amount = invited_wheel_amount + $prizeDb,
            updated_at = NOW()
        WHERE user_id = $userId
    ");
}

// FINAL RESPONSE (NORMAL SPIN)
$response = [
    "data" => [
        "isFirstInvitedWheel" => false,
        "prizeAmount" => floatval($prize),
        "isWin" => $isWin,
        "firstInvitedWheelDatas" => null
    ],
    "code" => 0,
    "msg" => "Succeed",
    "msgCode" => 0,
    "serviceNowTime" => $now
];

echo json_encode($response);
exit();
?>