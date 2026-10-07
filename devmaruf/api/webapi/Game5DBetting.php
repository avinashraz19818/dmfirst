<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php 
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers:  * ');
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
    if (isset($shonupost['amount']) && isset($shonupost['betCount']) && isset($shonupost['gameType']) && isset($shonupost['issuenumber']) && 
        isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['selectType']) && isset($shonupost['signature']) && 
        isset($shonupost['timestamp']) && isset($shonupost['typeId'])) {
        
        $amount = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['amount']));
        $betCount = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['betCount']));
        $gameType = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['gameType']));
        $issuenumber = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['issuenumber']));
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $selectType = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['selectType']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        $typeId = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['typeId']));
        
        $shonustr = '{"amount":'.$amount.',"betCount":'.$betCount.',"gameType":'.$gameType.',"issuenumber":"'.$issuenumber.'","language":'.$language.',"random":"'.$random.'","selectType":"'.$selectType.'","typeId":'.$typeId.'}';
        $shonusign = strtoupper(md5($shonustr));
        
        if ($shonusign == $signature) {
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1];				
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, true);
            
            if ($data_auth['status'] === 'Success') {
                $sesquery = "SELECT * FROM shonu_subjects WHERE akshinak = '".mysqli_real_escape_string($conn, $author)."'";
                $sesresult = $conn->query($sesquery);
                
                if ($sesresult->num_rows == 1) {
                    $userdatarow = $sesresult->fetch_assoc();
                    $is_betrestrict = $userdatarow['is_betrestrict'];

                    // ✅ Bet restriction check added here
                    if ($is_betrestrict == 1 || $is_betrestrict === '1') {
                        $res['code'] = -9;
                        $res['msg'] = 'You don\'t have permission to Bet';
                        $res['msgCode'] = 0;
                        http_response_code(200);
                        echo json_encode($res);
                        exit;
                    }
                    // ✅ If bet restrict is 0 → continue normal flow

                    $currentMinute = date('i');
                    $currentSecond = date('s');
                    $periodLength = 1;
                    $nextPeriodStart = ceil($currentMinute / $periodLength) * $periodLength;
                    $secondsRemaining = (($nextPeriodStart * 60) - ($currentMinute * 60 + $currentSecond));

                    // Betting period restrictions based on typeId
                    if ($typeId == 5) {
                        $secondsRemaining = 60 - $currentSecond;
                        if ($secondsRemaining <= 5) {
                            $res['code'] = -9;
                            $res['msg'] = 'Betting Closed for this period';
                            $res['msgCode'] = 0;
                            http_response_code(200);
                            echo json_encode($res);
                            exit;
                        }
                        $oedajnahb = 'bajikattuttate_aidudi';
                        $mothermary = 'gelluonduhogu_aidudi';
                    } elseif ($typeId == 6) {
                        $periodLength = 3;
                        $nextPeriodStart = ceil($currentMinute / $periodLength) * $periodLength;
                        $secondsRemaining = (($nextPeriodStart * 60) - ($currentMinute * 60 + $currentSecond));
                        if ($secondsRemaining <= 5) {
                            $res['code'] = -9;
                            $res['msg'] = 'Betting Closed for this period';
                            $res['msgCode'] = 0;
                            http_response_code(200);
                            echo json_encode($res);
                            exit;
                        }
                        $oedajnahb = 'bajikattuttate_aidudi_drei';
                        $mothermary = 'gelluonduhogu_aidudi_drei';
                    } elseif ($typeId == 7) {
                        $periodLength = 5;
                        $nextPeriodStart = ceil($currentMinute / $periodLength) * $periodLength;
                        $secondsRemaining = (($nextPeriodStart * 60) - ($currentMinute * 60 + $currentSecond));
                        if ($secondsRemaining <= 5) {
                            $res['code'] = -9;
                            $res['msg'] = 'Betting Closed for this period';
                            $res['msgCode'] = 0;
                            http_response_code(200);
                            echo json_encode($res);
                            exit;
                        }
                        $oedajnahb = 'bajikattuttate_aidudi_funf';
                        $mothermary = 'gelluonduhogu_aidudi_funf';
                    } elseif ($typeId == 8) {
                        $periodLength = 10;
                        $nextPeriodStart = ceil($currentMinute / $periodLength) * $periodLength;
                        $secondsRemaining = (($nextPeriodStart * 60) - ($currentMinute * 60 + $currentSecond));
                        if ($secondsRemaining <= 5) {
                            $res['code'] = -9;
                            $res['msg'] = 'Betting Closed for this period';
                            $res['msgCode'] = 0;
                            http_response_code(200);
                            echo json_encode($res);
                            exit;
                        }
                        $oedajnahb = 'bajikattuttate_aidudi_zehn';
                        $mothermary = 'gelluonduhogu_aidudi_zehn';
                    }
                    
                    if ($betCount >= 1) {
                        if ($amount >= 1) {
                            $samasye = "SELECT atadaaidi FROM ".$mothermary." ORDER BY kramasankhye DESC LIMIT 1";
                            $samasyephalitansa = $conn->query($samasye);
                            $samasyesreni = mysqli_fetch_array($samasyephalitansa);
                            if ($samasyesreni['atadaaidi'] == $issuenumber) {
                                $stplode = explode("|", $selectType);
                                $stcnt = count($stplode);
                                $totalamount = $amount * $betCount * $stcnt;								
                                $balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = ".$data_auth['payload']['id'];
                                $balresult = $conn->query($balquery);
                                $balarr = mysqli_fetch_array($balresult);									
                                $shonubalance = $balarr['motta'];								
                                if ($shonubalance >= $totalamount) {
                                    $byabaharkarta = $data_auth['payload']['id'];
                                    
                                    	// Regular users must have total recharge >= 500
											$total_recharge_query = "SELECT SUM(motta) as total_recharge_amount FROM thevani WHERE balakedara = '".intval($byabaharkarta)."' AND sthiti = 1";
											$total_recharge_result = $conn->query($total_recharge_query);
											
											if ($total_recharge_result && mysqli_num_rows($total_recharge_result) > 0) {
												$totalRechargeRow = mysqli_fetch_array($total_recharge_result);
												$totalRechargeAmount = floatval($totalRechargeRow['total_recharge_amount']);
												
												// Check if total recharge is >= 500
												if ($totalRechargeAmount >= 100) {
													// Total recharge ₹500 or more, allow betting
													$sesabida = sprintf("%.2f", $totalamount * 0.98);
													$tathya = mysqli_query($conn,"INSERT INTO `".$lordjesus."` (`byabaharkarta`,`kalaparichaya`,`prakar`,`ojana`,`menge`,`wettanzahl`,`ketebida`,`phalaphala`,`sesabida`,`tiarikala` , `is_demo_bet`  ) VALUES ('".$byabaharkarta."','".$issuenumber."','".$gameType."','".$selectType."','".$amount."','".$betCount."','".$totalamount."','perte','".$sesabida."','".$shnunc."'  , '$is_demo_bet'  )");
													
													if($tathya){
														$mottanutan = $shonubalance - $totalamount;
														
														$nabikarana = "UPDATE shonu_kaichila set motta='$mottanutan'  ,   mottta = GREATEST(mottta - '$totalamount', 0)  where balakedara='$byabaharkarta'";
														$conn->query($nabikarana);
														
														// WALLET SYNC: deduct bet amount from user_withdraw_summary.wallet
                                                        $__delta     = round((float)$totalamount, 2);   // bet amount
                                                        $__negDelta  = -$__delta;                       // negative for deduction
                                                
                                                // 1) Try UPDATE first
                                                mysqli_query($conn, "
                                                    UPDATE user_withdraw_summary
                                                    SET wallet = ROUND(wallet - {$__delta}, 2),
                                                        updated_at = '{$shnunc}'
                                                    WHERE user_id = '".intval($byabaharkarta)."'
                                                ");
                                                
                                                // 2) If no row updated, INSERT a new one with negative wallet
                                                if (mysqli_affected_rows($conn) === 0) {
                                                    mysqli_query($conn, "
                                                        INSERT INTO user_withdraw_summary
                                                            (user_id, wallet, total_recharge, total_purchase, total_bet,
                                                             min_withdraw, can_withdraw_amount, amount_of_code, need_to_bet,
                                                             withdraw_start_time, withdraw_end_time, withdraw_remaining_times,
                                                             manual_override, manual_updated_at, updated_at)
                                                        VALUES
                                                            ('".intval($byabaharkarta)."', '{$__negDelta}', 0, 0, 0,
                                                             0, 0, 0, 0,
                                                             NULL, NULL, 0,
                                                             0, NULL, '{$shnunc}')
                                                    ");
                                                }
                                                
                                                include "commission.php";
                                                include "vip.php";
                                                $res['data'] = null;
                                                $res['code'] = 0;
                                                $res['msg'] = 'Succeed';
                                                $res['msgCode'] = 0;
                                                http_response_code(200);
                                                echo json_encode($res);
                                            } else {
                                                $res['code'] = 8;
                                                $res['msg'] = 'Database error: Bet insertion failed';
                                                $res['msgCode'] = 500;
                                                http_response_code(200);
                                                echo json_encode($res);
                                            }
                                        } else {
                                            // First recharge exists but amount is less than 200
                                            $res['code'] = -9;
                                            $res['msg'] = 'First recharge must be at least ₹' . $minRechargeRequired . ' to start betting';
                                            $res['msgCode'] = 0;
                                            http_response_code(200);
                                            echo json_encode($res);
                                        }
                                    } else {
										// No completed recharge found
										$res['code'] = -9;
										$res['msg'] = 'First recharge must be at least ₹' . $minRechargeRequired . ' to start betting';
										$res['msgCode'] = 0;
										http_response_code(200);
										echo json_encode($res);
									}
                                    // ===== END FIRST RECHARGE CHECK =====
                                    
                                } else {
                                    $res['code'] = 1;
                                    $res['msg'] = 'Balance is not enough';
                                    $res['msgCode'] = 142;
                                    http_response_code(200);
                                    echo json_encode($res);	
                                }
                            } else {
                                $res['code'] = 1;
                                $res['msg'] = 'The current period is settled';
                                $res['msgCode'] = 404;
                                http_response_code(200);
                                echo json_encode($res);
                            }																																				
                        } else {
                            $res['code'] = 7;
                            $res['msg'] = "Invalid value for parameter 'Amount'";
                            unset($res['msgCode'], $res['serviceNowTime']);
                            http_response_code(200);
                            echo json_encode($res);
                        }
                    } else {
                        $res['code'] = 7;
                        $res['msg'] = "Invalid value for parameter 'BetCount'";
                        unset($res['msgCode'], $res['serviceNowTime']);
                        http_response_code(200);
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
?>