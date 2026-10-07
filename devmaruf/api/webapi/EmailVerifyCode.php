<?php
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

// Handle CORS Preflight
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 11, 'msg' => 'Method not allowed', 'msgCode' => 12, 'serviceNowTime' => $now]);
    exit;
}

// 1. Authenticate Request via Bearer JWT Token
$authHeader = ($_SERVER['HTTP_AUTHORIZATION'] ?? '') ?:
              ($_SERVER['Authorization'] ?? '') ?:
              ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

$parts = explode(' ', trim($authHeader));
$token = $parts[1] ?? '';

$is_jwt_valid = is_jwt_valid($token);
$data_auth = json_decode($is_jwt_valid, true);

if (($data_auth['status'] ?? '') !== 'Success') {
    http_response_code(401);
    echo json_encode(['code' => 4, 'msg' => 'Unauthorized or invalid token', 'msgCode' => 2, 'serviceNowTime' => $now]);
    exit;
}

$userId = (int)$data_auth['payload']['id'];

// 2. Auto-create verification table if it does not exist
$conn->query("
    CREATE TABLE IF NOT EXISTS `email_verification_codes` (
      `user_id` INT NOT NULL,
      `email` VARCHAR(150) NOT NULL,
      `code` VARCHAR(10) NOT NULL,
      `expires_at` DATETIME NOT NULL,
      PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 3. Parse Raw Input
$raw = file_get_contents("php://input");
$in = json_decode($raw, true) ?? [];

// Get fields flexibly from various common key names
$email = filter_var(trim($in['email'] ?? $in['userEmail'] ?? ''), FILTER_VALIDATE_EMAIL);
$code  = trim((string)($in['code'] ?? $in['verifyCode'] ?? $in['otp'] ?? ''));

// Determine action automatically
$actionRaw = strtolower((string)($in['action'] ?? $in['type'] ?? ''));

if (!empty($code) || in_array($actionRaw, ['bind_email', 'bind', 'verify', '2'], true)) {
    $action = 'bind_email';
} else {
    $action = 'send_code';
}

if (!$email) {
    echo json_encode(['code' => 7, 'msg' => 'Invalid or missing email address', 'msgCode' => 6, 'serviceNowTime' => $now]);
    exit;
}

// --------------------------------------------------------------------------
// ACTION 1: Send Verification Code to Email
// --------------------------------------------------------------------------
if ($action === 'send_code') {
    
    // Check if email is already taken by another account
    $checkStmt = $conn->prepare("SELECT id FROM shonu_subjects WHERE email = ? AND id != ? LIMIT 1");
    $checkStmt->bind_param("si", $email, $userId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        echo json_encode(['code' => 8, 'msg' => 'Email is already bound to another account', 'msgCode' => 8, 'serviceNowTime' => $now]);
        exit;
    }
    $checkStmt->close();

    // Generate 6-digit verification code
    $verifyCode = sprintf("%06d", mt_rand(1, 999999));
    $expiresAt = date("Y-m-d H:i:s", strtotime("+5 minutes"));

    // Save/Update code in DB
    $stmt = $conn->prepare("
        INSERT INTO email_verification_codes (user_id, email, code, expires_at) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE email = VALUES(email), code = VALUES(code), expires_at = VALUES(expires_at)
    ");
    $stmt->bind_param("isss", $userId, $email, $verifyCode, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // Send Mail
    $subject = "Your Verification Code";
    $message = "Your verification code is: " . $verifyCode . "\n\nValid for 5 minutes.";
    $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n" .
               "X-Mailer: PHP/" . phpversion();

    if (@mail($email, $subject, $message, $headers)) {
        echo json_encode(['code' => 0, 'msg' => 'Succeed', 'msgCode' => 0, 'serviceNowTime' => $now]);
    } else {
        echo json_encode(['code' => 9, 'msg' => 'Failed to send verification email', 'msgCode' => 9, 'serviceNowTime' => $now]);
    }
    exit;
}

// --------------------------------------------------------------------------
// ACTION 2: Verify Code and Bind Email
// --------------------------------------------------------------------------
if ($action === 'bind_email') {

    if (empty($code)) {
        echo json_encode(['code' => 7, 'msg' => 'Verification code is required', 'msgCode' => 6, 'serviceNowTime' => $now]);
        exit;
    }

    // Verify code in DB
    $stmt = $conn->prepare("
        SELECT code, expires_at 
        FROM email_verification_codes 
        WHERE user_id = ? AND email = ? 
        LIMIT 1
    ");
    $stmt->bind_param("is", $userId, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['code' => 10, 'msg' => 'No verification code requested for this email', 'msgCode' => 10, 'serviceNowTime' => $now]);
        exit;
    }

    if ($row['code'] !== $code) {
        echo json_encode(['code' => 10, 'msg' => 'Incorrect verification code', 'msgCode' => 10, 'serviceNowTime' => $now]);
        exit;
    }

    if (strtotime($row['expires_at']) < strtotime($now)) {
        echo json_encode(['code' => 10, 'msg' => 'Verification code has expired', 'msgCode' => 10, 'serviceNowTime' => $now]);
        exit;
    }

    // Update user's email in shonu_subjects
    $updateStmt = $conn->prepare("UPDATE shonu_subjects SET email = ? WHERE id = ?");
    $updateStmt->bind_param("si", $email, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    // Clean up used code
    $delStmt = $conn->prepare("DELETE FROM email_verification_codes WHERE user_id = ?");
    $delStmt->bind_param("i", $userId);
    $delStmt->execute();
    $delStmt->close();

    echo json_encode(['code' => 0, 'msg' => 'Succeed', 'msgCode' => 0, 'serviceNowTime' => $now]);
    exit;
}
?>