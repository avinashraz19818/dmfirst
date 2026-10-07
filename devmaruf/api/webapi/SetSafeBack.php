<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
require_once "../../conn.php"; 
require_once "../../functions2.php";

header('Content-Type: application/json');
$raw_post_data = file_get_contents("php://input");
$post_data = json_decode($raw_post_data, true);

// Amount validation
$amount = isset($post_data['amount']) ? floatval($post_data['amount']) : null;
if ($amount === null || $amount <= 0) {
    echo json_encode([
        "code" => 1, 
        "msg" => "Invalid amount", 
        "error_details" => ["field" => "amount"]
    ]);
    exit();
}

// JWT validation
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$jwt = explode(" ", $auth_header)[1] ?? '';
if (empty($jwt)) {
    echo json_encode(["code" => 1, "msg" => "Authorization token missing"]);
    exit();
}

$jwt_data = json_decode(is_jwt_valid($jwt), true);
if ($jwt_data['status'] !== 'Success') {
    echo json_encode(["code" => 1, "msg" => "JWT validation failed"]);
    exit();
}

// Get user ID from JWT
$jwt_safe = mysqli_real_escape_string($conn, $jwt);
$user_query = "SELECT id FROM shonu_subjects WHERE akshinak = '$jwt_safe' LIMIT 1";
$user_result = mysqli_query($conn, $user_query);

if (!$user_result) {
    echo json_encode(["code" => 1, "msg" => "Database error: " . mysqli_error($conn)]);
    exit();
}

$user = mysqli_fetch_assoc($user_result);
if (!$user) {
    echo json_encode(["code" => 1, "msg" => "User not found"]);
    exit();
}

$user_id = mysqli_real_escape_string($conn, $user['id']);

// Check safe box balance (sum of all transactions)
$safe_query = "SELECT COALESCE(SUM(amount), 0) as safe_balance FROM safe_box WHERE user_id = '$user_id'";
$safe_result = mysqli_query($conn, $safe_query);

if (!$safe_result) {
    echo json_encode(["code" => 1, "msg" => "Failed to check safe balance: " . mysqli_error($conn)]);
    exit();
}

$safe_data = mysqli_fetch_assoc($safe_result);
$safe_balance = floatval($safe_data['safe_balance'] ?? 0.00);

// Check if safe has enough balance for withdrawal
if ($safe_balance < $amount) {
    echo json_encode([
        "code" => 1, 
        "msg" => "Insufficient safe balance", 
        "data" => [
            "available_balance" => $safe_balance,
            "requested_amount" => $amount
        ]
    ]);
    exit();
}

// Get main balance
$main_query = "SELECT COALESCE(motta, 0) as motta FROM shonu_kaichila WHERE balakedara = '$user_id' LIMIT 1";
$main_result = mysqli_query($conn, $main_query);

if (!$main_result) {
    echo json_encode(["code" => 1, "msg" => "Failed to check main balance: " . mysqli_error($conn)]);
    exit();
}

$main_data = mysqli_fetch_assoc($main_result);
$main_balance = floatval($main_data['motta'] ?? 0.00);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Generate order number
    $order_num = 'WTH' . date("YmdHis") . rand(1000, 9999);
    
    // Withdrawal amount is negative in safe_box
    $withdrawal_amount = -$amount;
    
    // Insert withdrawal record into safe_box with type = 19
    $safe_insert_query = "INSERT INTO safe_box (user_id, amount, type, order_num, transaction_date, created_at, status) 
                         VALUES ('$user_id', '$withdrawal_amount', 19, '$order_num', NOW(), NOW(), 'completed')";
    
    if (!mysqli_query($conn, $safe_insert_query)) {
        throw new Exception("Failed to insert safe transaction: " . mysqli_error($conn));
    }
    
    // Update main balance (add the withdrawn amount)
    $new_main_balance = $main_balance + $amount;
    $main_update_query = "UPDATE shonu_kaichila SET motta = '$new_main_balance' WHERE balakedara = '$user_id'";
    
    if (!mysqli_query($conn, $main_update_query)) {
        throw new Exception("Failed to update main balance: " . mysqli_error($conn));
    }
    
    // Calculate new safe balance after withdrawal
    $new_safe_balance = $safe_balance - $amount;
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Return success response
    echo json_encode([
        "data" => [
            "new_main_balance" => number_format($new_main_balance, 2, '.', ''),
            "new_safe_balance" => number_format($new_safe_balance, 2, '.', ''),
            "transferred_amount" => number_format($amount, 2, '.', ''),
            "order_num" => $order_num,
            "transaction_date" => date("Y-m-d H:i:s"),
            "previous_main_balance" => number_format($main_balance, 2, '.', ''),
            "previous_safe_balance" => number_format($safe_balance, 2, '.', ''),
            "transaction_type" => 19
        ],
        "code" => 0,
        "msg" => "Transfer successful",
        "msgCode" => 0,
        "serviceNowTime" => date("Y-m-d H:i:s")
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    echo json_encode([
        "code" => 1, 
        "msg" => "Transfer failed: " . $e->getMessage()
    ]);
    
} finally {
    mysqli_close($conn);
}
?>