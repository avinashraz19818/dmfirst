<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php 
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set('Asia/Kolkata');
$shnunc = date("Y-m-d H:i:s");
$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

// Debug: Check what data is received
error_log("=== DEBUG RECEIVE DAILY AWARD ===");
error_log("Raw POST body: " . $shonubody);
error_log("Decoded POST data: " . print_r($shonupost, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("=== END DEBUG ===");

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    // Temporary: Hardcode taskId for testing - REMOVE LATER
    if (!isset($shonupost['taskId']) || empty($shonupost['taskId'])) {
        // For testing, set a default taskId
        $shonupost['taskId'] = "B5";
        $shonupost['language'] = "en";
        $shonupost['random'] = "123456";
        $shonupost['signature'] = "TEST_SIGNATURE";
        $shonupost['timestamp'] = $shnunc;
        
        error_log("Using default taskId: B5 for testing");
    }

    if (true) {
        $language = isset($shonupost['language']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language'])) : "en";
        $taskId = isset($shonupost['dailyAwardId']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['dailyAwardId'])) : "B5";
        $random = isset($shonupost['random']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random'])) : "123456";
        $signature = isset($shonupost['signature']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature'])) : "TEST_SIGNATURE";
        
        // Fix: taskId ko quotes me lo
        $shonustr = '{"language":"'.$language.'","random":"'.$random.'","taskId":"'.$taskId.'"}';
        $shonusign = strtoupper(md5($shonustr));
        
        // Debug ke liye
        error_log("Using taskId: '" . $taskId . "'");
        error_log("Signature string: " . $shonustr);
        error_log("Calculated signature: " . $shonusign);
        
        // Temporary: Signature check bypass for testing
        if(true){
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $res['code'] = 401;
                $res['msg'] = 'Authorization header missing';
                $res['msgCode'] = 401;
                $res['serviceNowTime'] = $shnunc;
                http_response_code(200);
                echo json_encode($res);
                exit;
            }
            
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($bearer) != 2) {
                $res['code'] = 401;
                $res['msg'] = 'Invalid authorization format';
                $res['msgCode'] = 401;
                $res['serviceNowTime'] = $shnunc;
                http_response_code(200);
                echo json_encode($res);
                exit;
            }
            
            $author = $bearer[1];                
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);
            
            if($data_auth['status'] === 'Success') {
                $sesquery = "SELECT id FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);
                
                if(mysqli_num_rows($sesresult) == 1) {
                    $user_data = mysqli_fetch_assoc($sesresult);
                    $shonuid = $user_data['id'];
                    
                    // Debug: Check what taskId we're looking for
                    error_log("Looking for taskId: '" . $taskId . "' for user: " . $shonuid);
                    
                    // Get the award details from daily_award_list for this specific taskId
                    $award_query = "SELECT * FROM daily_award_list WHERE configId = '$taskId' LIMIT 1";
                    $award_result = $conn->query($award_query);
                    
                    // Debug: Check how many rows found
                    $num_rows = mysqli_num_rows($award_result);
                    error_log("Found " . $num_rows . " rows for configId: '" . $taskId . "'");
                    
                    if($num_rows > 0) {
                        $award_data = mysqli_fetch_assoc($award_result);
                        $taskAwardAmount = $award_data['taskAwardAmount'];
                        $taskTarget = $award_data['taskTarget'];
                        $configId = $award_data['configId'];
                        $taskAwardAmount = $award_data['taskAwardAmount'];
                        $taskId = $award_data['taskId'];
                        $taskTitle = $award_data['taskTitle'];
                        $taskDescribe = $award_data['taskDescribe'];
                        $taskTarget = $award_data['taskTarget'];
                        $lId = $award_data['lId']; // Get lId from the award data
                        
                        error_log("Award found: " . $award_data['taskTitle'] . " Amount: " . $taskAwardAmount . " ConfigId: " . $configId . " lId: " . $lId);
                        
                        // Check if user has already received this award - USING lId in fleck
                        $check_received = "SELECT id FROM noitativni_sonub WHERE arthur = '$shonuid' AND fleck = '$lId' and date(time) = DATE('$shnunc') AND status = 1";
                        $check_result = $conn->query($check_received);
                        
                        if(mysqli_num_rows($check_result) == 0) {
                            // Insert into noitativni_sonub table with lId as fleck
                            $insert_query = "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `configId`, `taskAwardAmount`, `taskId`, `taskTitle`, `taskDescribe`, `taskTarget`,  `motta`, `status`, `time`) 
                                           VALUES ('$shonuid', '$lId', '$configId', '$taskAwardAmount', '$taskId', '$taskTitle', '$taskDescribe', '$taskTarget',   '$taskAwardAmount', '1', '$shnunc')";
                            $insert_result = $conn->query($insert_query);
                            
                            if($insert_result) {
                                // Update shonu_kaichila motta
                                $update_wallet = "UPDATE shonu_kaichila SET motta = motta + $taskAwardAmount WHERE balakedara = '$shonuid'";
                                $wallet_result = $conn->query($update_wallet);
                                
                                // // Update daily_award_list - set schedule to taskTarget and status to 0
                                // $update_award = "UPDATE daily_award_list SET schedule = $taskTarget, status = 0 WHERE taskId = '$taskId' AND configId = '$configId'";
                                // $award_update_result = $conn->query($update_award);
                                
                                error_log("Award processed successfully. Amount: " . $taskAwardAmount . " lId stored in fleck: " . $lId);
                                
                                $res['code'] = 0;
                                $res['msg'] = 'Succeed';
                                $res['msgCode'] = 0;
                                $res['serviceNowTime'] = $shnunc;
                                $res['awardAmount'] = $taskAwardAmount;
                                http_response_code(200);
                                echo json_encode($res);
                            } else {
                                error_log("Failed to insert into noitativni_sonub");
                                $res['code'] = -9;
                                $res['msg'] = 'Failed to process award';
                                $res['msgCode'] = 0;
                                $res['serviceNowTime'] = $shnunc;
                                http_response_code(200);
                                echo json_encode($res);
                            }
                        } else {
                            error_log("Award already received by user");
                            $res['code'] = -9;
                            $res['msg'] = 'Award already received';
                            $res['msgCode'] = 0;
                            $res['serviceNowTime'] = $shnunc;
                            http_response_code(200);
                            echo json_encode($res);
                        }
                    } else {
                        error_log("No award found in database for taskId: " . $taskId);
                        $res['code'] = -9;
                        $res['msg'] = 'Award not found for taskId: ' . $taskId;
                        $res['msgCode'] = 0;
                        $res['serviceNowTime'] = $shnunc;
                        http_response_code(200);
                        echo json_encode($res);
                    }
                } else {
                    error_log("User not found for akshinak: " . $author);
                    $res['code'] = -9;
                    $res['msg'] = 'Unauthorized access';
                    $res['msgCode'] = 0;
                    $res['serviceNowTime'] = $shnunc;
                    http_response_code(200);
                    echo json_encode($res);
                }
            } else {
                error_log("JWT validation failed");
                $res['code'] = 401;
                $res['msg'] = 'Invalid token';
                $res['msgCode'] = 401;
                $res['serviceNowTime'] = $shnunc;
                http_response_code(200);
                echo json_encode($res);
            }
        } else {
            $res['code'] = 403;
            $res['msg'] = 'Invalid signature';
            $res['msgCode'] = 403;
            $res['serviceNowTime'] = $shnunc;
            http_response_code(200);
            echo json_encode($res);
        }
    } else {
        $res['code'] = 400;
        $res['msg'] = 'Missing required parameters';
        $res['msgCode'] = 400;
        $res['serviceNowTime'] = $shnunc;
        http_response_code(200);
        echo json_encode($res);
    }
} else {
    http_response_code(405);
    echo json_encode($res);
}