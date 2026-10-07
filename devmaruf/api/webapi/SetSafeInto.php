<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once "../../conn.php";
require_once "../../functions2.php";

$response = [
    'code' => 1,
    'msg' => 'Unknown error',
    'msgCode' => 0,
    'serviceNowTime' => date('Y-m-d H:i:s'),
    'data' => null
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['msg'] = 'Method not allowed';
    $response['code'] = 405;
    http_response_code(405);
    echo json_encode($response);
    exit();
}

$raw_post_data = file_get_contents('php://input');
if (empty($raw_post_data)) {
    $response['msg'] = 'Empty request body';
    echo json_encode($response);
    exit();
}

$post_data = json_decode($raw_post_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['msg'] = 'Invalid JSON: ' . json_last_error_msg();
    echo json_encode($response);
    exit();
}

$amount = isset($post_data['amount']) ? floatval($post_data['amount']) : 0;
if ($amount <= 0) {
    $response['msg'] = 'Invalid amount. Amount must be greater than 0';
    $response['error_details'] = ['field' => 'amount', 'value' => $amount];
    echo json_encode($response);
    exit();
}

$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($auth_header)) {
    $response['msg'] = 'Authorization header missing';
    $response['code'] = 401;
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$jwt_parts = explode(' ', $auth_header);
$jwt = $jwt_parts[1] ?? '';
if (empty($jwt)) {
    $response['msg'] = 'JWT token missing';
    $response['code'] = 401;
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$jwt_result = is_jwt_valid($jwt);
$jwt_data = json_decode($jwt_result, true);

if ($jwt_data['status'] !== 'Success' || empty($jwt_data['payload']['id'])) {
    $response['msg'] = 'Invalid or expired token';
    $response['code'] = 401;
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$user_id_from_jwt = intval($jwt_data['payload']['id']);

$safe_jwt = mysqli_real_escape_string($conn, $jwt);
$user_query = "SELECT id FROM shonu_subjects WHERE akshinak = '$safe_jwt' AND id = $user_id_from_jwt LIMIT 1";
$user_result = mysqli_query($conn, $user_query);

if (!$user_result || mysqli_num_rows($user_result) === 0) {
    $response['msg'] = 'User not found or token mismatch';
    $response['code'] = 401;
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$user_data = mysqli_fetch_assoc($user_result);
$user_id = intval($user_data['id']);

$balance_query = "SELECT motta FROM shonu_kaichila WHERE balakedara = $user_id LIMIT 1";
$balance_result = mysqli_query($conn, $balance_query);

if (!$balance_result || mysqli_num_rows($balance_result) === 0) {
    $response['msg'] = 'User balance not found';
    echo json_encode($response);
    exit();
}

$balance_data = mysqli_fetch_assoc($balance_result);
$main_balance = floatval($balance_data['motta']);

if ($main_balance < $amount) {
    $response['msg'] = 'Insufficient funds';
    $response['data'] = [
        'current_balance' => $main_balance,
        'requested_amount' => $amount,
        'deficit' => $amount - $main_balance
    ];
    echo json_encode($response);
    exit();
}

mysqli_begin_transaction($conn);

try {
    $new_balance = $main_balance - $amount;
    
    // Update main balance
    $update_query = "UPDATE shonu_kaichila SET motta = $new_balance WHERE balakedara = $user_id";
    if (!mysqli_query($conn, $update_query)) {
        throw new Exception('Failed to update balance: ' . mysqli_error($conn));
    }
    
    $current_time = date('Y-m-d H:i:s');
    // Generate order number
    $order_num = 'DEP' . date('YmdHis') . rand(1000, 9999);
    
    // Insert into safe_box with type = 18
    $safe_query = "INSERT INTO safe_box (user_id, amount, transaction_date, status, type, order_num) 
                   VALUES ($user_id, $amount, '$current_time', 'completed', 18, '$order_num')";
    
    if (!mysqli_query($conn, $safe_query)) {
        throw new Exception('Failed to create safe box record: ' . mysqli_error($conn));
    }
    
    $transaction_id = mysqli_insert_id($conn);
    
    mysqli_commit($conn);
    
    // Success response
    $response['code'] = 0;
    $response['msg'] = 'Transfer successful';
    $response['msgCode'] = 0;
    $response['data'] = [
        'user_id' => $user_id,
        'transaction_id' => $transaction_id,
        'order_num' => $order_num,
        'previous_balance' => $main_balance,
        'new_balance' => $new_balance,
        'transferred_amount' => $amount,
        'transaction_date' => $current_time,
        'transaction_type' => 18,
        'transaction_status' => 'completed'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    $response['msg'] = 'Transaction failed: ' . $e->getMessage();
    $response['error_details'] = [
        'error' => $e->getMessage(),
        'user_id' => $user_id,
        'amount' => $amount
    ];
    
    error_log('SafeBox Transfer Error: ' . $e->getMessage() . ' User: ' . $user_id);
    
    echo json_encode($response);
}

mysqli_close($conn);
exit();
?>