<?php
header("Content-Type: application/json; charset=UTF-8");

/* ===== CONFIG ===== */
$appId     = "6752332636";
$appKey    = "749bbbb46aaa4cb7bd6e6841dcb3512e";

$apiUrl    = "http://150.5.128.21/apid/newbankPay/crtAgencyOrder.do";
$notifyURL = "http://amarclub01.com/pay1/lotus_notify.php";

/* ===== TELEGRAM BOT CONFIG ===== */
$telegramBotToken = "8438463257:AAEEv_zqkWc4orI06zk7aiZ5-2Zg0YwCw-w";
$telegramChatId   = "@MAX_CLUB_WD"; // Channel username

/* ===== INPUT ===== */
$name   = trim($_POST['name'] ?? '');
$number = trim($_POST['number'] ?? '');
$amount = trim($_POST['amount'] ?? '');
$email  = trim($_POST['email'] ?? '');

/* ===== VALIDATION ===== */
if (!$name || !$number || !$amount) {
    echo json_encode(["code" => 400, "msg" => "Missing parameters"]);
    exit;
}

/* ===== BLOCKED NUMBERS ===== */
$blockedNumbers = [
    "01603795727",
    "01306198209"
];

if (in_array($number, $blockedNumbers)) {
    echo json_encode(["code" => 403, "msg" => "This number is blocked from making withdrawals."]);
    exit;
}

/* ===== ORDER ===== */
$appOrderNo = "DF" . date("YmdHis") . rand(1000, 9999);

/* ===== EXTENDED PARAMS ===== */
$userEmail = $email ? $email : $number . "@gmail.com";

$extParams = json_encode([
    "phoneNum" => $number,
    "email"    => $userEmail
], JSON_UNESCAPED_SLASHES);

/* ===== REQUEST PARAMS ===== */
$params = [
    "appId"      => $appId,
    "appOrderNo" => $appOrderNo,
    "orderAmt"   => number_format($amount, 2, ".", ""),
    "payId"      => "2601",
    "accNo"      => $number,
    "accName"    => $name,
    "bankCode"   => "BDWALLET",
    "bankName"   => "BD WALLET",
    "notifyURL"  => $notifyURL,
    "extParams"  => $extParams
];

/* ===== SIGN DATA ===== */
$signData = [
    "accName"    => $params['accName'],
    "accNo"      => $params['accNo'],
    "appId"      => $params['appId'],
    "appOrderNo" => $params['appOrderNo'],
    "bankName"   => $params['bankName'],
    "orderAmt"   => $params['orderAmt'],
    "payId"      => $params['payId'],
];

ksort($signData);

$signStr = "";
foreach ($signData as $k => $v) {
    $signStr .= $k . "=" . $v . "&";
}
$signStr .= "key=" . $appKey;

/* ===== SIGN ===== */
$params['sign'] = strtoupper(md5($signStr));

/* ===== CURL PAYMENT REQUEST ===== */
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30
]);

$response = curl_exec($ch);
curl_close($ch);

/* ===== TELEGRAM NOTIFICATION ===== */
$resData = json_decode($response, true);
$status  = (isset($resData['code']) && $resData['code'] == 0) ? "SUCCESS" : "PENDING/FAILED";
$time    = date("Y-m-d H:i:s");

$telegramMessage = "📥 *NEW WITHDRAWAL REQUEST*\n\n"
                 . "👤 *Name:* " . $name . "\n"
                 . "📱 *Number:* " . $number . "\n"
                 . "💰 *Amount:* ৳" . $params['orderAmt'] . "\n"
                 . "🕒 *Time:* " . $time . "\n"
                 . "📌 *Status:* " . $status;

// Send request to Telegram
$tgUrl = "https://api.telegram.org/bot" . $telegramBotToken . "/sendMessage";
$tgParams = [
    'chat_id'    => $telegramChatId,
    'text'       => $telegramMessage,
    'parse_mode' => 'Markdown'
];

$chTg = curl_init();
curl_setopt_array($chTg, [
    CURLOPT_URL            => $tgUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($tgParams),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10
]);
curl_exec($chTg);
curl_close($chTg);

/* ===== RETURN RESPONSE ===== */
echo $response;