<?php
/**
 * ============================================
 * ZenPay Merchant Callback / IPN Handler
 * Developed By: ZenPay Integration Team
 * URL: /pay/Zenpay_notify.php
 * ============================================
 */

// ১. ডাটাবেজ কানেকশন, কনফিগারেশন ফাইল ও টাইমজোন লোড
require_once("../serive/samparka.php");
require_once("zenpayconfig.php"); 
date_default_timezone_set("Asia/Dhaka");

/* ===============================
   LOG FUNCTION
=================================*/
function logData($msg) {
    file_put_contents(
        "zenpay_callback_log.txt",
        date('Y-m-d H:i:s') . " - " . $msg . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/* ===============================
   GET RAW JSON FROM ZENPAY (call.php)
=================================*/
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

logData("RECEIVED NOTIFICATION RAW DATA: " . $raw);

if (!$data) {
    logData("ERROR: Invalid or Empty JSON Data");
    echo "fail";
    exit;
}

/* ===============================
   EXTRACT REQUIRED FIELDS
=================================*/
$receivedApiKey = $data['api_key'] ?? '';      // ZenPay থেকে আসা API Key
$orderNo        = $data['merOrderNo'] ?? '';    // অর্ডার আইডি
$amount         = $data['amount'] ?? 0;         // অ্যামাউন্ট
$orderStatus    = $data['orderStatus'] ?? '';   // স্ট্যাটাস (Success)

// বেসিক ফিল্ড চেক
if (empty($orderNo) || empty($receivedApiKey)) {
    logData("ERROR: Required fields missing (Order ID or API Key)");
    echo "fail";
    exit;
}

/* ===============================
   API KEY VALIDATION ENGINE
=================================*/
// zenpayconfig.php ফাইলে থাকা ZENPAY_APP_ID এর সাথে পাঠানো api_key মেলানো হচ্ছে
$expectedApiKey = defined('ZENPAY_APP_ID') ? ZENPAY_APP_ID : '';

if (empty($expectedApiKey) || $receivedApiKey !== $expectedApiKey) {
    logData("SECURITY WARNING: API Key Mismatch! Received: '{$receivedApiKey}' | Expected: '{$expectedApiKey}'");
    echo "fail";
    exit;
}

/* ===============================
   CHECK PAYMENT STATUS
=================================*/
if (strtoupper($orderStatus) !== 'SUCCESS') {
    logData("Payment not successful. Status received: " . $orderStatus);
    echo "ok"; 
    exit;
}

/* ===============================
   CHECK ORDER EXISTS IN DATABASE
=================================*/
$orderNoSafe = mysqli_real_escape_string($conn, $orderNo);
$check = mysqli_query($conn,
    "SELECT motta, balakedara, sthiti
     FROM thevani 
     WHERE dharavahi = '{$orderNoSafe}' 
     LIMIT 1"
);

if (!$check || mysqli_num_rows($check) < 1) {
    logData("Order ID: " . $orderNo . " not found in Database");
    echo "ok"; 
    exit;
}

$row = mysqli_fetch_assoc($check);

/* Duplicate Protection: অর্ডার পূর্বে প্রসেস হয়েছে কিনা */
if ($row['sthiti'] == '1') {
    logData("Order: " . $orderNo . " already processed previously.");
    echo "ok";
    exit;
}

$userId = $row['balakedara'];
$realAmount = floatval($amount);

/* ===============================
   UPDATE CUSTOMER BALANCE
=================================*/
$updateBalance = mysqli_query($conn,
    "UPDATE shonu_kaichila
     SET motta = ROUND(motta + {$realAmount}, 2)
     WHERE balakedara = '{$userId}'"
);

if (!$updateBalance) {
    logData("Balance update error for User ID " . $userId . ": " . mysqli_error($conn));
    echo "fail";
    exit;
}

/* ===============================
   UPDATE ORDER STATUS TO SUCCESS
=================================*/
$updateOrder = mysqli_query($conn,
    "UPDATE thevani 
     SET sthiti = '1'
     WHERE dharavahi = '{$orderNoSafe}'"
);

if (!$updateOrder) {
    logData("Order status update error for Order ID " . $orderNo . ": " . mysqli_error($conn));
    echo "fail";
    exit;
}

// সফল প্রসেস লগিং
logData("SUCCESSFUL CALLBACK | Order: " . $orderNo . " | User ID: " . $userId . " | Amount Added: ৳" . $realAmount);

echo "ok";
exit;
?>