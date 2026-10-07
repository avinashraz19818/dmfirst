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

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        $shonustr = '{"language":' . $language . ',"random":"' . $random . '"}';
        $shonusign = strtoupper(md5($shonustr));
        if ($shonusign == $signature) {
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1];
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);
            if ($data_auth['status'] === 'Success') {
                $sesquery = "SELECT *
                      FROM shonu_subjects
                      WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);
                $sesnum = mysqli_num_rows($sesresult);
                if ($sesnum == 1) {
                    try {
                        $userQuery = "SELECT * FROM shonu_subjects WHERE akshinak = '$author'";
                        $userResult = $conn->query($userQuery);
                    
                        if ($userResult) {
                            $userRow = $userResult->fetch_assoc();
                            $userName = $userRow['id'];
                    
                            $chotta_userQuery = "SELECT * FROM shonu_kaichila WHERE balakedara = '$userName'";
                            $chotta_userResult = $conn->query($chotta_userQuery);
                    
                            if ($chotta_userResult) {
                                $chotta_userRow = $chotta_userResult->fetch_assoc();
                                $chotta_userchotta = $chotta_userRow['chotta'];
                            }
                    
                            $withdrawnAmount = $chotta_userchotta;
                    
                            if ($withdrawnAmount >= 0) {
                                // Now we're directly embedding variables in the query string.
                                $updateBalanceQuery = "UPDATE shonu_kaichila SET motta = motta + $withdrawnAmount, chotta = 0 WHERE balakedara = '$userName'";
                                
                                  // 3️⃣ ek transaction record banao
                                    $kani = uniqid("txn_"); // unique transaction id
                                    $userkani = $userName;
                                    $serial = $kani . rand(100000, 999999);
                                    $price = $withdrawnAmount;
                                    $shonu = 'System';
                                    $remark = 'Balance Moved In from API Games';
                                    $transaction_type = '1';
                            
                                    $insertTxn = "INSERT INTO aks_amount_in_out_table 
                                                  (userkani, serial, price, shonu, remark, processed)
                                                  VALUES 
                                                  ('$userkani', '$serial', '$price', '$shnunc', '$remark', '$transaction_type')";
                            
                                    mysqli_query($conn, $insertTxn);
                    
                                if ($conn->query($updateBalanceQuery)) {
                                    $res['code'] = 0;
                                    $res['msg'] = 'Withdrawal successful';
                                    $res['data'] = ['new_balance' => $withdrawnAmount];
                                } else {
                                    $res['code'] = 11;
                                    $res['msg'] = 'Failed to update local balance';
                                }
                            } else {
                                $res['code'] = 0;
                                $res['msg'] = 'No balance to withdraw';
                            }
                    
                            http_response_code(200);
                            echo json_encode($res);
                        } else {
                            $res['code'] = 8;
                            $res['msg'] = 'User not found';
                            http_response_code(400);
                            echo json_encode($res);
                        }
                    } catch (Exception $e) {
                        $res['code'] = 9;
                        $res['msg'] = 'Withdrawal failed: ' . $e->getMessage();
                        http_response_code(500);
                        echo json_encode($res);
                    }

                } else {
                    $res['code'] = 4;
                    $res['msg'] = 'No operation permission';
                    $res['msgCode'] = 2;
                    http_response_code(401);
                    echo json_encode($res);
                }
            } else {
                $res['code'] = 4;
                $res['msg'] = 'No operation permission';
                $res['msgCode'] = 2;
                http_response_code(401);
                echo json_encode($res);
            }
        } else {
            $res['code'] = 5;
            $res['msg'] = 'Wrong signature';
            $res['msgCode'] = 3;
            http_response_code(200);
            echo json_encode($res);
        }
    } else {
        $res['code'] = 7;
        $res['msg'] = 'Param is Invalid';
        $res['msgCode'] = 6;
        http_response_code(200);
        echo json_encode($res);
    }
} else {
    http_response_code(405);
    echo json_encode($res);
}