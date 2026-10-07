<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php 
	include "../../conn.php";
	include "../../functions2.php";
	
	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: * ');
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
			
			$shonustr = '{"amount":'.$amount.',"betCount":'.$betCount.',"gameType":'.$gameType.',"issuenumber":"'.$issuenumber.'","language":'.$language.',"random":"'.$random.'","selectType":'.$selectType.',"typeId":'.$typeId.'}';
			$shonusign = strtoupper(md5($shonustr));
			
			if($shonusign == $signature){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = isset($bearer[1]) ? $bearer[1] : '';
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				
				if(!empty($data_auth) && isset($data_auth['status']) && $data_auth['status'] === 'Success') {
					$sesquery = "SELECT * 
					  FROM shonu_subjects
					  WHERE akshinak = '".mysqli_real_escape_string($conn, $author)."'";
					$sesresult=$conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
				
					
					if($sesnum == 1){
					    
					    $userdatarow = mysqli_fetch_array($sesresult);									
					    
    					$is_demo_bet = 0;
    					
    					$uid = $userdatarow['id'];
    					
    					$demoQuery = "SELECT 1 FROM demo WHERE balakedara = '$uid'";
                        $demoResult = $conn->query($demoQuery);
                        
                        if ($demoResult->num_rows > 0) {
                              $is_demo_bet = 1;
                        }
                        
    					$is_betrestrict = $userdatarow['is_betrestrict'];
    					
    					
    					if($is_betrestrict == 1 || $is_betrestrict == '1'){
    					    	$res['code'] = -9;
        						$res['msg'] = 'You dont have permission to Bet';
        						$res['msgCode'] = 0;
        						http_response_code(200);
        						echo json_encode($res);
        						
        						die();
    					}

						// ******** NEW: Check if User has Advance Balance to Block Betting ********
						$adv_check_sql = "SELECT SUM(advance_amount) AS total_adv FROM advance_user WHERE balakedara = ?";
						$total_adv = 0;
						if ($stmt_adv = mysqli_prepare($conn, $adv_check_sql)) {
							mysqli_stmt_bind_param($stmt_adv, "i", $uid);
							mysqli_stmt_execute($stmt_adv);
							$res_adv = mysqli_stmt_get_result($stmt_adv);
							if ($res_adv && $row_adv = mysqli_fetch_assoc($res_adv)) {
								$total_adv = floatval($row_adv['total_adv']);
							}
							mysqli_stmt_close($stmt_adv);
						} else {
							error_log("Failed to prepare adv_check_sql: " . mysqli_error($conn));
						}

						if ($total_adv > 0) {
							$res['code'] = -9;
							$res['msg'] = 'আপনার ৳' . number_format($total_adv, 2) . ' এডভান্স ব্যালেন্স আছে, বেট করতে আপনার টিচারের সাথে যোগাযোগ করুন।';
							$res['msgCode'] = 0;
							http_response_code(200);
							echo json_encode($res);
							die();
						}
						// ******** END NEW ADVANCE LOGIC ********
    					
    					$currentSecond = date('s');
                        $secondsRemaining = 60 - $currentSecond;
                        
						if($typeId == 1){
							$lordjesus = 'bajikattuttate';
							$sonofgod = 'gelluonduhogu';
                            
                            
                            if ($secondsRemaining <= 5) {
                                	$res['code'] = -9;
            						$res['msg'] = 'Betting Closed for this period';
            						$res['msgCode'] = 0;
            						http_response_code(200);
            						echo json_encode($res);
            						
            						die();
                            }
                            
						}
						else if($typeId == 2){
							$lordjesus = 'bajikattuttate_drei';
							$sonofgod = 'gelluonduhogu_drei';
							
							if ($secondsRemaining <= 5) {
                                	$res['code'] = -9;
            						$res['msg'] = 'Betting Closed for this period';
            						$res['msgCode'] = 0;
            						http_response_code(200);
            						echo json_encode($res);
            						
            						die();
                            }
						}
						else if($typeId == 3){
							$lordjesus = 'bajikattuttate_funf';
							$sonofgod = 'gelluonduhogu_funf';
							
							if ($secondsRemaining <= 5) {
                                	$res['code'] = -9;
            						$res['msg'] = 'Betting Closed for this period';
            						$res['msgCode'] = 0;
            						http_response_code(200);
            						echo json_encode($res);
            						
            						die();
                            }
                            
						}
						else if($typeId == 4){
						    $second = (int)date('s');

                           
                            $nextBoundary = ($currentSecond < 30) ? 30 : 60;
                            $secondsRemaining = $nextBoundary - $currentSecond;
                            
                            if ($secondsRemaining <= 5) {
                                	$res['code'] = -9;
            						$res['msg'] = 'Betting Closed for this period';
            						$res['msgCode'] = 0;
            						http_response_code(200);
            						echo json_encode($res);
            						
            						die();
                            }
							$lordjesus = 'bajikattuttate_zehn';
							$sonofgod = 'gelluonduhogu_zehn';
						}
						
						if($betCount >= 1){
							if($amount >= 1){
								$samasye = "SELECT atadaaidi
								  FROM ".$sonofgod."
								  ORDER BY kramasankhye DESC LIMIT 1";
								$samasyephalitansa=$conn->query($samasye);
								$samasyesreni = mysqli_fetch_array($samasyephalitansa);
								
								if($samasyesreni['atadaaidi'] == $issuenumber){
									$totalamount = $amount * $betCount;								
									$balquery = "SELECT motta
									  FROM shonu_kaichila
									  WHERE balakedara = ".intval($data_auth['payload']['id']);
									$balresult = $conn->query($balquery);
									$balarr = mysqli_fetch_array($balresult);									
									$shonubalance = $balarr['motta'];								
									
									if($shonubalance >= $totalamount){
										$byabaharkarta = intval($data_auth['payload']['id']);
										
										// ===== NEW: Block betting for agents (safe-response to avoid client logout) =====
										$agentCheckSql = "SELECT id FROM tb_agent WHERE userid = '".intval($byabaharkarta)."' AND status = 1 LIMIT 1";
										$agentCheckRes = mysqli_query($conn, $agentCheckSql);
										if ($agentCheckRes && mysqli_num_rows($agentCheckRes) > 0) {
											// Unique non-auth code + flags so client won't auto-logout
											$res = [
												'code' => -9,
												'msg' => 'Error',
												'msgCode' => 'Betting is blocked for agents',
												'agentBlocked' => true,
												'dontLogout' => true,
												'serviceNowTime' => $shnunc,
											];
											http_response_code(200);
											echo json_encode($res);
											exit;
										}
										// ===== END AGENT BLOCK =====
										
										// ===== UPDATED: Check TOTAL recharge amount >= 500 =====
										// For demo users, skip the recharge check
										if ($is_demo_bet == 1) {
											// Demo users can bet without recharge check
											$sesabida = sprintf("%.2f", $totalamount * 0.98);
											$tathya = mysqli_query($conn,"INSERT INTO `".$lordjesus."` (`byabaharkarta`,`kalaparichaya`,`prakar`,`ojana`,`menge`,`wettanzahl`,`ketebida`,`phalaphala`,`sesabida`,`tiarikala` , `is_demo_bet`  ) VALUES ('".$byabaharkarta."','".$issuenumber."','".$gameType."','".$selectType."','".$amount."','".$betCount."','".$totalamount."','perte','".$sesabida."','".$shnunc."'  , '$is_demo_bet'  )");
											
											if($tathya){
												$mottanutan = $shonubalance - $totalamount;
												
												$nabikarana = "UPDATE shonu_kaichila set motta='$mottanutan'  ,   turnover = GREATEST(turnover - '$totalamount', 0)  where balakedara='$byabaharkarta'";
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
										    // First fetch minimum recharge amount from web_setting
                                            $min_recharge_query = "SELECT min_recharge FROM web_setting WHERE id = 1 LIMIT 1";
                                            $min_recharge_result = $conn->query($min_recharge_query);
                                            $minRechargeRequired = 100; // default value
                                            
                                            if ($min_recharge_result && mysqli_num_rows($min_recharge_result) > 0) {
                                                $minRechargeRow = mysqli_fetch_array($min_recharge_result);
                                                $minRechargeRequired = floatval($minRechargeRow['min_recharge']);
                                            }
											// Regular users must have total recharge >= 500
											$total_recharge_query = "SELECT SUM(motta) as total_recharge_amount FROM thevani WHERE balakedara = '".intval($byabaharkarta)."' AND sthiti = 1";
											$total_recharge_result = $conn->query($total_recharge_query);
											
											if ($total_recharge_result && mysqli_num_rows($total_recharge_result) > 0) {
												$totalRechargeRow = mysqli_fetch_array($total_recharge_result);
												$totalRechargeAmount = floatval($totalRechargeRow['total_recharge_amount']);
												
												// Check if total recharge is >= 500
												if ($totalRechargeAmount >= $minRechargeRequired) {
													// Total recharge ₹500 or more, allow betting
													$sesabida = sprintf("%.2f", $totalamount * 0.98);
													$tathya = mysqli_query($conn,"INSERT INTO `".$lordjesus."` (`byabaharkarta`,`kalaparichaya`,`prakar`,`ojana`,`menge`,`wettanzahl`,`ketebida`,`phalaphala`,`sesabida`,`tiarikala` , `is_demo_bet`  ) VALUES ('".$byabaharkarta."','".$issuenumber."','".$gameType."','".$selectType."','".$amount."','".$betCount."','".$totalamount."','perte','".$sesabida."','".$shnunc."'  , '$is_demo_bet'  )");
													
													if($tathya){
														$mottanutan = $shonubalance - $totalamount;
														
														$nabikarana = "UPDATE shonu_kaichila set motta='$mottanutan'  ,   turnover = GREATEST(turnover - '$totalamount', 0)  where balakedara='$byabaharkarta'";
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
													// Total recharge is less than 500
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
										}
										// ===== END UPDATED CODE =====
										
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
								unset($res['msgCode']);
								unset($res['serviceNowTime']);
								http_response_code(200);
								echo json_encode($res);
							}
						} else {
							$res['code'] = 7;
							$res['msg'] = "Invalid value for parameter 'BetCount'";
							unset($res['msgCode']);
							unset($res['serviceNowTime']);
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
				$res['msgCode'] = 1;
				http_response_code(403);
				echo json_encode($res);
			}
		} else {
			$res['code'] = 9;
			$res['msg'] = 'Missing parameters';
			unset($res['msgCode']);
			unset($res['serviceNowTime']);
			http_response_code(200);
			echo json_encode($res);
		}
	}
	else{
		http_response_code(405);
		echo json_encode($res);
	}	
?>