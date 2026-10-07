<?php
include("../serive/samparka.php");

function logError($m){
    file_put_contents(
        __DIR__.'/error.log',
        date('Y-m-d H:i:s')." | ".$m.PHP_EOL,
        FILE_APPEND
    );
}

/* ================= TELEGRAM FUNCTION ================= */
function sendTelegram($message){
    $botToken = "8438463257:AAEEv_zqkWc4orI06zk7aiZ5-2Zg0YwCw-w";
    $chatId   = "@MY_MAX_CLUB";
    $url = "https://api.telegram.org/bot".$botToken."/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text'    => $message
    ];

    file_get_contents($url . "?" . http_build_query($data));
}

/* ================= GET DATA ================= */
// API সাধারণত JSON Body অথবা Form-Data হিসেবে Callback পাঠায়
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// যদি JSON হিসেবে না আসে তবে $_POST ব্যবহার করবে
if (!$data) {
    $data = $_POST;
}

logError("RAW NOTIFY DATA: ".json_encode($data));

/* ================= VALIDATION CHECK ================= */
if (!isset($data['out_trade_no'], $data['money'])) {
    logError("INVALID DATA RECEIVED");
    echo "FAIL";
    exit;
}

$merchantSign = "3ff34e4ea7cb86fee514ebc95d16da8f"; // আপনার সিকিউরিটি সাইন

/* ================= SIGN VERIFY ================= */
if (isset($data['sign']) && $data['sign'] !== $merchantSign) {
    logError("SIGN FAIL | Received: ".$data['sign']);
    echo "FAIL";
    exit;
}

$appOrderNo = mysqli_real_escape_string($conn, $data['out_trade_no']);

/* ================= FIND ORDER ================= */
$order = mysqli_query($conn, "
    SELECT motta, balakedara 
    FROM thevani 
    WHERE dharavahi='$appOrderNo' AND sthiti='0'
");

if (mysqli_num_rows($order) == 0) {
    logError("ORDER NOT FOUND OR ALREADY PROCESSED | Order No: ".$appOrderNo);
    echo "SUCCESS";
    exit;
}

$o = mysqli_fetch_array($order);
$amount = $o['motta'];
$uid    = $o['balakedara'];

/* ================= ADD BALANCE & TURNOVER ================= */
mysqli_query($conn, "
    UPDATE shonu_kaichila 
    SET motta = motta + '$amount', 
        turnover = turnover + '$amount' 
    WHERE balakedara='$uid'
");

/* ================= UPDATE ORDER STATUS ================= */
mysqli_query($conn, "
    UPDATE thevani 
    SET sthiti='1' 
    WHERE dharavahi='$appOrderNo'
");

/* ================= TELEGRAM MESSAGE ================= */
$time = date("Y-m-d H:i:s");

$msg = "💰 Deposit Success\n\n".
       "👤 User ID: ".$uid."\n".
       "💵 Amount: ".$amount." BDT\n".
       "🔄 Turnover Added: ".$amount."\n".
       "🆔 Order No: ".$appOrderNo."\n".
       "⏰ Time: ".$time;

sendTelegram($msg);

/* ================= LOG ================= */
logError("BALANCE & TURNOVER ADDED | UID: {$uid} | AMT: {$amount}");

// গেটওয়ে রেসপন্স
echo "SUCCESS";
exit;
?>