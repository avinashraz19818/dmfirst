<?php
// System headers
include "../../conn.php";
include "../../functions2.php";

// Disable MySQLi exception reporting so errors can be handled gracefully
mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . $origin);

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

$response = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'time' => $now,
];

// Handle request method check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data['giftCode'], $data['language'], $data['random'], $data['signature'])) {
    $response['code'] = 7;
    $response['msg'] = "Invalid params";
    echo json_encode($response);
    exit;
}

$giftCode  = $data['giftCode'];
$language  = $data['language'];
$random    = $data['random'];
$signature = $data['signature'];

/* ================= SIGNATURE VERIFICATION ================= */
$str = '{"giftCode":"'.$giftCode.'","language":'.$language.',"random":"'.$random.'"}';
$checkSign = strtoupper(md5($str));

if ($checkSign !== $signature) {
    $response['code'] = 5;
    $response['msg'] = "Wrong signature";
    echo json_encode($response);
    exit;
}

/* ================= AUTHENTICATION & TOKEN CHECK ================= */
$token = explode(" ", $_SERVER['HTTP_AUTHORIZATION'] ?? '')[1] ?? '';
$auth  = json_decode(is_jwt_valid($token), true);

if (!$auth || ($auth['status'] ?? '') !== 'Success') {
    $response['code'] = 4;
    $response['msg'] = "Invalid token";
    echo json_encode($response);
    exit;
}

$uid = $auth['payload']['id'];

$stmtUser = $conn->prepare("SELECT akshinak FROM shonu_subjects WHERE akshinak = ?");
$stmtUser->bind_param("s", $token);
$stmtUser->execute();
$userCheck = $stmtUser->get_result();

if (!$userCheck || $userCheck->num_rows === 0) {
    $response['code'] = 4;
    $response['msg'] = "No permission";
    echo json_encode($response);
    exit;
}

/* ================= GET CODE DATA ================= */
$stmtCode = $conn->prepare("SELECT * FROM hodike_nirvahaka WHERE enserie = ? AND shonu = '1'");
$stmtCode->bind_param("s", $giftCode);
$stmtCode->execute();
$q = $stmtCode->get_result();

if (!$q || $q->num_rows === 0) {
    $response['code'] = 1;
    $response['msg'] = "Invalid gift code";
    echo json_encode($response);
    exit;
}

$code = $q->fetch_assoc();

// FIX: Clean formatted numbers (removes commas or accidental spaces)
$max = (int)preg_replace('/[^0-9]/', '', (string)$code['utilisateurmax']);
$used = (int)preg_replace('/[^0-9]/', '', (string)$code['nombredutilisateurs']);

$price = (float)$code['prix'];
$remark = $code['remark'];
$multiplier = (float)($code['turnover_multiplier'] ?? 0);

/* ================= ALREADY CLAIMED CHECK ================= */
$stmtClaim = $conn->prepare("SELECT 1 FROM hodike_balakedara WHERE serial = ? AND userkani = ?");
$stmtClaim->bind_param("ss", $giftCode, $uid);
$stmtClaim->execute();
$check = $stmtClaim->get_result();

if ($check && $check->num_rows > 0) {
    $response['code'] = 1;
    $response['msg'] = "Already claimed";
    echo json_encode($response);
    exit;
}

/* ================= LIMIT CHECK ================= */
if ($used >= $max) {
    $response['code'] = 1;
    $response['msg'] = "Limit reached";
    echo json_encode($response);
    exit;
}

/* ================= RECHARGE CHECK ================= */
$recharge_required = (float)($code['recharge_required'] ?? 0);

if ($recharge_required > 0) {
    $stmtRecharge = $conn->prepare("SELECT SUM(motta) as total FROM thevani WHERE balakedara = ? AND sthiti = 1");
    $stmtRecharge->bind_param("s", $uid);
    $stmtRecharge->execute();
    $r = $stmtRecharge->get_result()->fetch_assoc();

    $totalRecharge = (float)($r['total'] ?? 0);

    if ($totalRecharge < $recharge_required) {
        $response['code'] = 1;
        $response['msg'] = "Recharge required";
        echo json_encode($response);
        exit;
    }
}

/* ================= DATABASE TRANSACTION ================= */
$conn->begin_transaction();

try {
    // 1. Atomic Update Code Usage (FIXED: Cast INT in SQL for safety)
    $stmtUpdateCode = $conn->prepare("
        UPDATE hodike_nirvahaka 
        SET nombredutilisateurs = CAST(nombredutilisateurs AS UNSIGNED) + 1 
        WHERE enserie = ? AND CAST(nombredutilisateurs AS UNSIGNED) < CAST(utilisateurmax AS UNSIGNED)
    ");
    $stmtUpdateCode->bind_param("s", $giftCode);
    $stmtUpdateCode->execute();

    if ($stmtUpdateCode->affected_rows === 0) {
        $conn->rollback();
        $response['code'] = 1;
        $response['msg'] = "Limit reached";
        echo json_encode($response);
        exit;
    }

    // 2. Insert Claim Log
    $stmtInsertClaim = $conn->prepare("
        INSERT INTO hodike_balakedara (userkani, serial, price, remark, shonu) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsertClaim->bind_param("ssdss", $uid, $giftCode, $price, $remark, $now);
    $stmtInsertClaim->execute();

    // 3. Update Balance and Turnover
    if ($multiplier <= 0) {
        $multiplier = 1.0;
    }
    $turnoverAdd = $price * $multiplier;

    $stmtUpdateUser = $conn->prepare("
        UPDATE shonu_kaichila 
        SET motta = ROUND(motta + ?, 2), 
            turnover = COALESCE(turnover, 0) + ? 
        WHERE balakedara = ?
    ");
    $stmtUpdateUser->bind_param("dds", $price, $turnoverAdd, $uid);
    $stmtUpdateUser->execute();

    // Commit Transaction
    $conn->commit();

    $response['code'] = 0;
    $response['msg'] = "Success";
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $response['code'] = 500;
    $response['msg'] = "Server Error";
    echo json_encode($response);
    exit;
}