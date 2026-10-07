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
$uQ = $conn->query("SELECT id FROM shonu_subjects WHERE akshinak='$token'");
if (!$uQ || mysqli_num_rows($uQ) !== 1) {
    $response["code"] = 4;
    $response["msg"] = "No operation permission";
    echo json_encode($response);
    exit();
}

/* ================== এককালীন ক্লেম চেকিং লজিক ================== */

// চেক করা হচ্ছে এই ইউজার আগে কখনো ক্লেম করেছে কিনা
$giftCheckQ = $conn->query("SELECT status FROM shonu_register_gifts WHERE user_id = $userId");

if (mysqli_num_rows($giftCheckQ) > 0) {
    $giftRow = mysqli_fetch_assoc($giftCheckQ);
    
    // যদি status ১ হয়, তার মানে ইউজার অলরেডি আগে ক্লেম করে ফেলেছে
    if (intval($giftRow['status']) === 1) {
        $response["code"] = 1; // কাস্টম এরর কোড
        $response["msg"]  = "You have already claimed your registration gift!";
        $response["msgCode"] = 101; 
        echo json_encode($response);
        exit();
    }
}

/* ================== গিফট প্রসেসিং ও ব্যালেন্স অ্যাড ================== */

$giftAmount = 20.00; // রেজিস্ট্রেশন গিফটের অ্যামাউন্ট (আপনার ইচ্ছা মতো পরিবর্তন করতে পারেন)

// ১. ইউজারের মেইন ওয়ালেটে ব্যালেন্স যোগ করা (shonu_kaichila টেবিল অনুযায়ী)
$updateBalance = $conn->query("UPDATE shonu_kaichila SET turnover = turnover + $giftAmount WHERE balakedara = '$userId'");

if ($updateBalance) {
    
    // ২. গিফট ট্র্যাকিং টেবিলে স্টেটাস আপডেট বা ইনসার্ট করা
    if (mysqli_num_rows($giftCheckQ) == 0) {
        // যদি টেবিলে ইউজারের কোনো রেকর্ডই না থাকে, তবে নতুন রো ইনসার্ট হবে (status = 1)
        $conn->query("INSERT INTO shonu_register_gifts (user_id, status, claim_time) VALUES ($userId, 1, '$now')");
    } else {
        // রেকর্ড থাকলে তা আপডেট করে ১ করে দেওয়া হবে
        $conn->query("UPDATE shonu_register_gifts SET status = 1, claim_time = '$now' WHERE user_id = $userId");
    }

    // সফল রেসপন্স
    $response = [
        "data" => [
            "bonusAmount" => floatval($giftAmount),
            "isSuccess" => true
        ],
        "code" => 0,
        "msg" => "Gift claimed successfully!",
        "msgCode" => 0,
        "serviceNowTime" => $now
    ];
    
} else {
    $response["code"] = 2;
    $response["msg"]  = "Failed to process gift. Try again later.";
    $response["msgCode"] = 500;
}

echo json_encode($response);
exit();
?>