<?php
// ==============================================================================
// Unified Game Launcher Bridge & Router (Full Hybrid Balance System)
// Integration Authority: @developer_maruf08
// ==============================================================================

define('GAME_LAUNCHER_ACCESS', true);
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../conn.php';
require_once '../../functions2.php';

/*==============================================================================
    WINLAB-VIP / ZENZVERSE GATEWAY CONFIGURATION
==============================================================================*/
define('RESELLER_API_TOKEN', '153246413fad0605a0c377b281a584f17b24cdc7f264047f94387563022c996c');
define('RESELLER_SECRET_KEY', '54e7ef7b8140cc3fd957b32a19c6c7e570ca3c947f66bcd342764e8355f81a9d');
define('RESELLER_PREFIX', 'dir_gfg_');
define('VERSE_API_URL', 'https://api-v1.zenzverse.pro/api/v1/launch/');

// Response Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key');
header('Access-Control-Allow-Credentials: true');
$reqOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $reqOrigin);
header('Vary: Origin');

date_default_timezone_set('Asia/Kolkata');

/*==============================================================================
    HELPER FUNCTIONS
==============================================================================*/
function game_url_reply($data, $code, $msg, $msgCode, $status = 200)
{
    http_response_code($status);
    echo json_encode(array(
        'data' => $data,
        'code' => $code,
        'msg' => $msg,
        'msgCode' => $msgCode,
        'serviceNowTime' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_SLASHES);
    exit;
}

function game_url_lottery_route($gameCode)
{
    $configPath = dirname(__DIR__, 3) . '/saas_lottery/config_live_v4.php';
    $config = is_file($configPath) ? include $configPath : array();
    $games = is_array($config) && isset($config['games']) && is_array($config['games']) ? $config['games'] : array();

    if (isset($games[$gameCode]) && is_array($games[$gameCode])) {
        $lottery = (string) ($games[$gameCode]['lottery'] ?? '');
        if ($lottery !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $lottery)) {
            return array('gameCode' => $gameCode, 'lottery' => $lottery);
        }
    }

    $fallbackMap = [
        'WinGo_30S' => 'WinGo', 'WinGo_1M' => 'WinGo', 'WinGo_3M' => 'WinGo', 'WinGo_5M' => 'WinGo',
        'TrxWinGo_30S' => 'TrxWinGo', 'TrxWinGo_1M' => 'TrxWinGo', 'TrxWinGo_3M' => 'TrxWinGo', 'TrxWinGo_5M' => 'TrxWinGo',
        'K3_1M' => 'K3', 'K3_3M' => 'K3', 'K3_5M' => 'K3', 'K3_10M' => 'K3',
        '5D_1M' => '5D', '5D_3M' => '5D', '5D_5M' => '5D', '5D_10M' => '5D',
        'MotoRace' => 'MotoRace'
    ];

    if (isset($fallbackMap[$gameCode])) {
        return array('gameCode' => $gameCode, 'lottery' => $fallbackMap[$gameCode]);
    }

    if (preg_match('/^([A-Za-z0-9]+)_[0-9]+[A-Za-z]*$/', $gameCode, $m)) {
        return array('gameCode' => $gameCode, 'lottery' => $m[1]);
    }

    return null;
}

function game_url_aviator_config()
{
    $path = dirname(__DIR__, 3) . '/config/aviator.php';
    $config = is_file($path) ? include $path : array();
    return is_array($config) ? $config : array();
}

function game_url_is_aviator($vendorCode, $gameCode, $config)
{
    $vendorCode = trim((string) $vendorCode);
    $gameCode = trim((string) $gameCode);

    $normalizedVendor = strtoupper($vendorCode);
    $isSpribeVendor = in_array($normalizedVendor, array('SPRIBE', '20'), true);
    $isTbVendor = in_array($normalizedVendor, array('TB_CHESS', '23'), true);
    $isLegacyRecommendedAviator = ($normalizedVendor === 'JILI' || $normalizedVendor === '18') && $gameCode === '800';

    if ($isSpribeVendor && (strcasecmp($gameCode, 'aviator') === 0 || $gameCode === '800')) return true;
    if ($isTbVendor && ($gameCode === '800' || strcasecmp($gameCode, 'aviator') === 0)) return true;
    if ($isLegacyRecommendedAviator) return true;

    $games = isset($config['games']) && is_array($config['games']) ? $config['games'] : array();
    foreach ($games as $game) {
        if (!is_array($game)) continue;
        $vendor = trim((string) ($game['vendorCode'] ?? ''));
        $code = trim((string) ($game['gameCode'] ?? ''));
        if ($vendor !== '' && $code !== '' && strcasecmp($vendorCode, $vendor) === 0 && strcasecmp($gameCode, $code) === 0) {
            return true;
        }
    }
    return false;
}

function game_url_aviator_callback_key($userId, $config)
{
    $secret = (string) ($config['callback_secret'] ?? '');
    return $secret === '' ? '' : hash_hmac('sha256', 'aviator-callback|' . (string) $userId, $secret);
}

function logGameTransaction($conn, $userId, $vendorCode, $gameCode, $gameNameEn, $amount, $timestamp, $hadARGameBalanceBefore = false, $existingARGameBalanceBefore = 0)
{
    try {
        if (!$hadARGameBalanceBefore && $amount > 0) {
            $serial = 'TRANS_' . uniqid();
            $insertQuery = "INSERT INTO game_moved_in_table (userkani, price, serial, remark, shonu) VALUES (?, ?, ?, 'Game In', ?)";
            $insertStmt = $conn->prepare($insertQuery);
            if (!$insertStmt) return ['success' => false];
            $insertStmt->bind_param("sdss", $userId, $amount, $serial, $timestamp);
            $insertStmt->execute();
            $insertStmt->close();
        }
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false];
    }
}

/*==============================================================================
    REQUEST & METHOD CHECK
==============================================================================*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    game_url_reply(null, 11, 'Method not allowed', 12, 405);
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
if (!is_array($body)) {
    game_url_reply(null, 7, 'Param is Invalid', 6);
}

$vendorCode = trim((string) (
    $body['vendorCode']
    ?? $body['vendor_code']
    ?? $body['vendorId']
    ?? $body['vendor_id']
    ?? ''
));
$gameCode = trim((string) (
    $body['gameCode']
    ?? $body['game_code']
    ?? $body['gameID']
    ?? $body['gameId']
    ?? ''
));
$returnUrlRaw  = $body['returnUrl'] ?? '';
$deviceTypeRaw = $body['deviceType'] ?? 0;
$languageRaw   = $body['language'] ?? 'en';
$randomRaw     = $body['random'] ?? '';
$gameNameEnRaw = $body['gameNameEn'] ?? '';

/*==============================================================================
    SIGNATURE VERIFICATION
==============================================================================*/
$providedSignature = strtoupper((string) ($body['signature'] ?? ''));
$signatureIsValid = false;

$signed = $body;
unset($signed['signature'], $signed['timestamp']);
foreach ($signed as $key => $value) {
    if (is_array($value) || is_object($value) || $value === null || $value === '') {
        unset($signed[$key]);
    }
}
ksort($signed, SORT_STRING);
$expectedSignature = strtoupper(md5(json_encode($signed, JSON_UNESCAPED_SLASHES)));

if ($providedSignature !== '' && hash_equals($expectedSignature, $providedSignature)) {
    $signatureIsValid = true;
} else {
    $possibleStrings = [
        '{"gameCode":"' . $gameCode . '","vendorCode":"' . $vendorCode . '","returnUrl":"' . $returnUrlRaw . '","deviceType":' . (int)$deviceTypeRaw . ',"language":' . (int)$languageRaw . ',"random":"' . $randomRaw . '"}',
        '{"deviceType":' . (int)$deviceTypeRaw . ',"gameCode":"' . $gameCode . '","language":' . (int)$languageRaw . ',"random":"' . $randomRaw . '","returnUrl":"' . $returnUrlRaw . '","vendorCode":"' . $vendorCode . '"}',
        '{"gameCode":"' . $gameCode . '","vendorCode":"' . $vendorCode . '","returnUrl":"' . $returnUrlRaw . '","deviceType":"' . $deviceTypeRaw . '","language":"' . $languageRaw . '","random":"' . $randomRaw . '"}',
        '{"deviceType":' . (int)$deviceTypeRaw . ',"gameCode":"' . $gameCode . '","language":"' . $languageRaw . '","random":"' . $randomRaw . '","returnUrl":"' . $returnUrlRaw . '","vendorCode":"' . $vendorCode . '"}'
    ];

    foreach ($possibleStrings as $strToCheck) {
        if (strcasecmp(strtoupper(md5($strToCheck)), $providedSignature) === 0) {
            $signatureIsValid = true;
            break;
        }
    }
}

if (!$signatureIsValid && !empty($providedSignature)) {
    game_url_reply(null, 5, 'Wrong signature', 3);
}

/*==============================================================================
    AUTH & JWT VALIDATION
==============================================================================*/
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', trim((string) $header), $matches)) {
    game_url_reply(null, 4, 'No operation permission', 2, 401);
}
$token = trim($matches[1]);
$verified = @json_decode(is_jwt_valid($token), true);
$userId = isset($verified['payload']['id']) ? (int) $verified['payload']['id'] : 0;

if (!is_array($verified) || ($verified['status'] ?? '') !== 'Success' || $userId < 1) {
    game_url_reply(null, 4, 'No operation permission', 2, 401);
}

$stmt = $conn->prepare('SELECT id, mobile, akshinak FROM shonu_subjects WHERE id = ? AND akshinak = ? LIMIT 1');
if (!$stmt) {
    game_url_reply(null, 4, 'No operation permission', 2, 401);
}
$stmt->bind_param('is', $userId, $token);
$stmt->execute();
$result = $stmt->get_result();
$active = $result && $result->num_rows === 1;
$stmt->close();

if (!$active) {
    game_url_reply(null, 4, 'No operation permission', 2, 401);
}

/*==============================================================================
    DEMO & RECHARGE CHECK
==============================================================================*/
$demoCheckQuery = "SELECT COUNT(*) as demo_count FROM demo WHERE balakedara = '$userId' AND (sthiti IS NULL OR sthiti = '1')";
$demoCheckResult = $conn->query($demoCheckQuery);
if ($demoCheckResult && $demoCheckRow = mysqli_fetch_assoc($demoCheckResult)) {
    if ($demoCheckRow['demo_count'] > 0) {
        game_url_reply(null, 401, 'Demo accounts cannot launch games', 0);
    }
}

$rechargeQuery = "SELECT 1 FROM thevani WHERE balakedara = '$userId' AND sthiti = 1 LIMIT 1";
$rechargeResult = mysqli_query($conn, $rechargeQuery);
if (mysqli_num_rows($rechargeResult) == 0) {
    game_url_reply(null, 400, 'Please recharge first to play games!', 400);
}

/*==============================================================================
    ORIGIN & INITIAL SETUP
==============================================================================*/
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$origin = rtrim($protocol . ($_SERVER['HTTP_HOST'] ?? 'dmfirst.club9.eu.cc'), '/');
$shnunc = date("Y-m-d H:i:s");

/*==============================================================================
    1. AVIATOR CUSTOM BYPASS
==============================================================================*/
if ($gameCode == "a04d1f3eb8ccec8a4823bdf18e3f0e84BDwsdsqa") {
    $gameLaunchUrl = "https://rkwinbd.com/av/index.php"; 
    $separator = (parse_url($gameLaunchUrl, PHP_URL_QUERY) == NULL) ? '?' : '&';
    $gameLaunchUrl .= $separator . "userId=" . intval($userId);

    game_url_reply([
        'url' => $gameLaunchUrl,
        'returnType' => 1
    ], 0, 'Game launched successfully', 0);
}

/*==============================================================================
    2. SAAS LOTTERY ROUTE
==============================================================================*/
$isLottery = strcasecmp($vendorCode, 'ARLottery') === 0;
if ($isLottery) {
    $route = $gameCode !== '' ? game_url_lottery_route($gameCode) : null;
    
    $lang = preg_replace('/[^A-Za-z-]/', '', (string) ($body['language'] ?? 'en')) ?: 'en';
    $url = $origin . '/?Token=' . rawurlencode($token)
        . '&Skin=blackGoldStyle'
        . '&Lang=' . rawurlencode($lang);

    if ($route !== null) {
        $url .= '#/saasLottery/' . rawurlencode($route['lottery'])
            . '?gameCode=' . rawurlencode($route['gameCode'])
            . '&lottery=' . rawurlencode($route['lottery']);
    } else if ($gameCode !== '') {
        $url .= '#/saasLottery/' . rawurlencode($gameCode)
            . '?gameCode=' . rawurlencode($gameCode)
            . '&lottery=' . rawurlencode($gameCode);
    }

    game_url_reply(array('url' => $url, 'returnType' => 1), 0, 'Succeed', 0);
}

/*==============================================================================
    3. WALLET BALANCE SHIFT & LOGGING SYSTEM (YOUR SPECIFIED LOGIC)
==============================================================================*/
// Fetch Main Wallet Balance
$stmtMain = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ?");
$mainBalance = 0.0;
if ($stmtMain) {
    $stmtMain->bind_param("s", $userId);
    $stmtMain->execute();
    $resultMain = $stmtMain->get_result();
    if ($resultMain && $resultMain->num_rows > 0) {
        $rowMain = $resultMain->fetch_assoc();
        $mainBalance = floatval($rowMain['motta']);
    }
    $stmtMain->close();
}

// Check Game Wallet (argame_balances)
$checkStmt = $conn->prepare("SELECT balance FROM argame_balances WHERE user_id = ?");
$hadARGameBalanceBefore = false;
$existingARGameBalance = 0.0;
$finalBalance = $mainBalance;

if ($checkStmt) {
    $checkStmt->bind_param("s", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult && $checkResult->num_rows > 0) {
        $hadARGameBalanceBefore = true;
        $balRow = $checkResult->fetch_assoc();
        $existingARGameBalance = floatval($balRow['balance']);
        $finalBalance = $existingARGameBalance + $mainBalance;
    }
    $checkStmt->close();
}

// Shift money to Game Wallet
if ($hadARGameBalanceBefore) {
    $updateStmt = $conn->prepare("UPDATE argame_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("ds", $finalBalance, $userId);
        $updateStmt->execute();
        $updateStmt->close();
    }
} else {
    $insertStmt2 = $conn->prepare("INSERT INTO argame_balances (user_id, balance, updated_at) VALUES (?, ?, NOW())");
    if ($insertStmt2) {
        $insertStmt2->bind_param("sd", $userId, $mainBalance);
        $insertStmt2->execute();
        $insertStmt2->close();
    }
}

// Reset main wallet to 0
if ($mainBalance > 0) {
    $updateMainWallet = $conn->prepare("UPDATE shonu_kaichila SET motta = 0 WHERE balakedara = ?");
    if ($updateMainWallet) {
        $updateMainWallet->bind_param("s", $userId);
        $updateMainWallet->execute();
        $updateMainWallet->close();
    }
}

// Insert into game_moved_in_table log
if (!$hadARGameBalanceBefore && $mainBalance > 0) {
    logGameTransaction($conn, $userId, $vendorCode, $gameCode, $gameNameEnRaw, $mainBalance, $shnunc, $hadARGameBalanceBefore, $existingARGameBalance);
}

/*==============================================================================
    4. NATIVE AVIATOR ENGINE ROUTE
==============================================================================*/
$aviatorConfig = game_url_aviator_config();
$isAviator = game_url_is_aviator($vendorCode, $gameCode, $aviatorConfig);

if ($isAviator) {
    $serviceUrl = trim((string) ($aviatorConfig['service_url'] ?? ''));
    $serviceParts = @parse_url($serviceUrl);
    if (!is_array($serviceParts) || strtolower((string) ($serviceParts['scheme'] ?? '')) !== 'https' || empty($serviceParts['host'])) {
        game_url_reply(null, 8, 'Game service unavailable', 8, 503);
    }

    $callbackKey = game_url_aviator_callback_key($userId, $aviatorConfig);
    if ($callbackKey === '') {
        game_url_reply(null, 8, 'Game service unavailable', 8, 503);
    }

    $callbackUrl = $origin . '/Callback.php?k=' . rawurlencode($callbackKey);
    $query = http_build_query(array(
        'token' => $token,
        'member_account' => (string) $userId,
        'callback_url' => $callbackUrl,
        'balance' => number_format($finalBalance, 2, '.', ''),
        'return_url' => $origin,
    ), '', '&', PHP_QUERY_RFC3986);

    $url = rtrim($serviceUrl, '/') . '/?' . $query;
    game_url_reply(array(
        'url' => $url,
        'returnType' => 1,
        'openMethod' => 'same_tab'
    ), 0, 'Succeed', 0);
}

/*==============================================================================
    5. EXTERNAL GATEWAY (Winlab / Zenzverse Launcher)
==============================================================================*/
$formattedPlayerId = rtrim(RESELLER_PREFIX, '_') . '_' . $userId;

$api_payload = [
    'api_token'     => RESELLER_API_TOKEN,
    'player_id'     => (string)$formattedPlayerId,
    'game_uid'      => (string)$gameCode,
    'mode'          => 'seamless',
    'currency_code' => 'BDT',
    'home_url'      => !empty($returnUrlRaw) ? $returnUrlRaw : $origin
];

$ch = curl_init(VERSE_API_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . RESELLER_API_TOKEN
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$api_response = curl_exec($ch);
$curl_error   = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    game_url_reply(null, 8, 'Failed to connect to gateway', 8);
} else {
    $api_result = json_decode($api_response, true);
    if ($api_result && isset($api_result['status']) && strtoupper($api_result['status']) === 'SUCCESS' && isset($api_result['game_launch_url'])) {
        game_url_reply([
            'url'        => $api_result['game_launch_url'],
            'returnType' => 'game_url'
        ], 0, 'Game launched successfully', 0);
    } else {
        $errorMsg = $api_result['message'] ?? $api_result['msg'] ?? 'Failed to launch game (Insufficient GGR or Invalid Game)';
        game_url_reply(null, 8, $errorMsg, 8);
    }
}