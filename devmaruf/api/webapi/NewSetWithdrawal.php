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
		if (isset($shonupost['amount']) && isset($shonupost['bid']) && isset($shonupost['language']) && isset($shonupost['pwd']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp']) && isset($shonupost['type'])) {
			$amount = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['amount']));
			$bid = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['bid']));
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));			
			$pwd = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['pwd']));			
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$type = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['type']));
			
			// Recreate signature string
			$shonustr = '{"amount":'.$amount.',"bid":"'.$bid.'","language":'.$language.',"pwd":"'.$pwd.'","random":"'.$random.'","type":'.$type.'}';
			$shonusign = strtoupper(md5($shonustr));
			
			if ($shonusign) {
				// Authorization & JWT
				$bearer = explode(" ", isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '');
				$author = isset($bearer[1]) ? $bearer[1] : (isset($bearer[0]) ? $bearer[0] : '');
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, true);
				if ($data_auth['status'] === 'Success') {
					$shonuid = intval($data_auth['payload']['id']);
					
					// fetch stored password for user
					$user_query = "SELECT password FROM shonu_subjects WHERE id = ?";
					if ($stmt = mysqli_prepare($conn, $user_query)) {
						mysqli_stmt_bind_param($stmt, "i", $shonuid);
						mysqli_stmt_execute($stmt);
						$user_result = mysqli_stmt_get_result($stmt);
						$user_arr = mysqli_fetch_array($user_result);
						mysqli_stmt_close($stmt);
					} else {
						$user_arr = [];
					}

					// password check
					if (!isset($user_arr['password']) || md5($pwd) != $user_arr['password']) {
						$res['code'] = -9;
						$res['msg'] = 'Wrong password Entered';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);
						mysqli_close($conn);
						exit;
					}

					// ******** ADVANCE & RELEASE AMOUNT CHECKING LOGIC ********
					$adv_check_sql = "SELECT id, advance_amount, IFNULL(release_amount, 0) AS release_amount FROM advance_user WHERE balakedara = ? LIMIT 1";
					$target_adv_id = 0;
					$allowed_release_wd = 0;
					$has_advance_record = false;

					if ($stmt_adv = mysqli_prepare($conn, $adv_check_sql)) {
						mysqli_stmt_bind_param($stmt_adv, "i", $shonuid);
						mysqli_stmt_execute($stmt_adv);
						$res_adv = mysqli_stmt_get_result($stmt_adv);
						if ($res_adv && $row_adv = mysqli_fetch_assoc($res_adv)) {
							$has_advance_record = true;
							$target_adv_id = intval($row_adv['id']);
							$total_adv = floatval($row_adv['advance_amount']);
							$allowed_release_wd = floatval($row_adv['release_amount']);
						}
						mysqli_stmt_close($stmt_adv);
					}

					if ($has_advance_record) {
						if ($allowed_release_wd <= 0) {
							$res['code'] = -9;
							$res['msg'] = 'আপনার এডভান্স ব্যালেন্স আছে, উইথড্র করতে আপনার টিচারের সাথে যোগাযোগ করুন।';
							$res['msgCode'] = 0;
							http_response_code(200);
							echo json_encode($res);
							mysqli_close($conn);
							exit;
						} elseif ($amount > $allowed_release_wd) {
							$res['code'] = -9;
							$res['msg'] = 'আপনি সর্বোচ্চ ৳' . number_format($allowed_release_wd, 2) . ' উইথড্র করতে পারবেন।';
							$res['msgCode'] = 0;
							http_response_code(200);
							echo json_encode($res);
							mysqli_close($conn);
							exit;
						}
					}
					// ******** END ADVANCE CHECKING LOGIC ********

					// DAILY LIMIT: how many withdraws today?
					$samasye_1 = "SELECT shonu FROM hintegedukolli WHERE balakedara = ? AND DATE(dinankavannuracisi) = DATE(?)";
					if ($stmt = mysqli_prepare($conn, $samasye_1)) {
						mysqli_stmt_bind_param($stmt, "is", $shonuid, $shnunc);
						mysqli_stmt_execute($stmt);
						$samasyephalitansa_1 = mysqli_stmt_get_result($stmt);
						$shelly = $samasyephalitansa_1 ? mysqli_num_rows($samasyephalitansa_1) : 0;
						mysqli_stmt_close($stmt);
					} else {
						// fallback
						$samasyephalitansa_1 = $conn->query("SELECT shonu FROM hintegedukolli WHERE balakedara = '$shonuid' AND DATE(dinankavannuracisi) = DATE('".$shnunc."')");
						$shelly = $samasyephalitansa_1 ? mysqli_num_rows($samasyephalitansa_1) : 0;
					}
					
					$shelly_1 = 3 - $shelly;
					
					if ($shelly_1 <= 0) {
						$res['code'] = -9;
						$res['msg'] = 'No more withdraw request available today';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);
						mysqli_close($conn);
						exit;
					}

					// Check existing pending withdrawal
					$pending_check_sql = "SELECT COUNT(*) AS cnt FROM hintegedukolli WHERE balakedara = ? AND sthiti = 0";
					$has_pending = 0;
					if ($stmt = mysqli_prepare($conn, $pending_check_sql)) {
						mysqli_stmt_bind_param($stmt, "i", $shonuid);
						mysqli_stmt_execute($stmt);
						$res_pending = mysqli_stmt_get_result($stmt);
						if ($res_pending && $rowp = mysqli_fetch_assoc($res_pending)) {
							$has_pending = intval($rowp['cnt']);
						}
						mysqli_stmt_close($stmt);
					} else {
						error_log("Failed to prepare pending_check_sql: " . mysqli_error($conn));
						$has_pending = 1;
					}

					if ($has_pending > 0) {
						$res['code'] = -9;
						$res['msg'] = 'Your previous withdrawal request in on pending.Please wait for the existing withdrawal';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);
						mysqli_close($conn);
						exit;
					}

					// Check if user is a demo user
					$checkDemoQuery = "SELECT 1 FROM demo WHERE balakedara = ?";
					$isDemoUser = false;
					if ($stmt = mysqli_prepare($conn, $checkDemoQuery)) {
						mysqli_stmt_bind_param($stmt, "i", $shonuid);
						mysqli_stmt_execute($stmt);
						$r = mysqli_stmt_get_result($stmt);
						$isDemoUser = ($r && mysqli_num_rows($r) > 0);
						mysqli_stmt_close($stmt);
					} else {
						$isDemoUser = mysqli_num_rows($conn->query("SELECT 1 FROM demo WHERE balakedara = '$shonuid'")) > 0;
					}

					// ******** RECHARGE / DEPOSIT CHECK FOR WITHDRAWAL ********
					$min_recharge_query = "SELECT min_recharge FROM web_setting WHERE id = 1 LIMIT 1";
					$min_recharge_result = $conn->query($min_recharge_query);
					$minRechargeRequired = 100;

					if ($min_recharge_result && $min_recharge_result->num_rows > 0) {
						$minRechargeRow = $min_recharge_result->fetch_assoc();
						$minRechargeRequired = floatval($minRechargeRow['min_recharge']);
					}

					$dep_check_sql = "SELECT SUM(motta) AS total_deposit FROM thevani WHERE balakedara = ? AND sthiti = 1";
					$total_deposit = 0;

					if ($stmt_dep = mysqli_prepare($conn, $dep_check_sql)) {
						mysqli_stmt_bind_param($stmt_dep, "i", $shonuid);
						mysqli_stmt_execute($stmt_dep);
						$res_dep = mysqli_stmt_get_result($stmt_dep);
						if ($res_dep && $row_dep = mysqli_fetch_assoc($res_dep)) {
							$total_deposit = floatval($row_dep['total_deposit']);
						}
						mysqli_stmt_close($stmt_dep);
					}

					if (!$isDemoUser && $total_deposit < $minRechargeRequired) {
						$res['code'] = -9;
						$res['msg'] = 'উইথড্র করতে কমপক্ষে ৳' . number_format($minRechargeRequired, 2) . ' ডিপোজিট করতে হবে।';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);
						mysqli_close($conn);
						exit;
					}
					// ******** END RECHARGE / DEPOSIT CHECK ********

					// fetch balance
					$balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = ?";
					$balarr = null;
					if ($stmt = mysqli_prepare($conn, $balquery)) {
						mysqli_stmt_bind_param($stmt, "i", $shonuid);
						mysqli_stmt_execute($stmt);
						$resbal = mysqli_stmt_get_result($stmt);
						$balarr = $resbal ? mysqli_fetch_array($resbal) : null;
						mysqli_stmt_close($stmt);
					} else {
						$resbal = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = '$shonuid'");
						$balarr = $resbal ? mysqli_fetch_array($resbal) : null;
					}
					
					// Fetch minimum withdrawal value
					$sql_mr = "SELECT min_withdrawal FROM web_setting LIMIT 1";
					$res_mr = $conn->query($sql_mr);

					$mr_min = 0;

					if ($res_mr && $res_mr->num_rows > 0) {
						$row_mr = $res_mr->fetch_assoc();
						$mr_min = $row_mr['min_withdrawal'];
					}

					// Validate amount range and balance
					if ($amount >= $mr_min && $amount <= 50000) {
						if ($balarr !== null && $amount <= $balarr['motta']) {
							
							mysqli_begin_transaction($conn);
							$mottanutan = $balarr['motta'] - $amount;

							// 1. Deduct main wallet balance
							$upd = "UPDATE shonu_kaichila SET motta = ? WHERE balakedara = ?";
							$ok_upd = false;
							if ($ustmt = mysqli_prepare($conn, $upd)) {
								mysqli_stmt_bind_param($ustmt, "di", $mottanutan, $shonuid);
								$ok_upd = mysqli_stmt_execute($ustmt);
								if (!$ok_upd) {
									error_log("Balance update failed: " . mysqli_stmt_error($ustmt));
								}
								mysqli_stmt_close($ustmt);
							} else {
								error_log("Prepare failed (balance update): " . mysqli_error($conn));
							}

							// 2. Deduct release_amount from advance_user if user had advance
							if ($ok_upd && $has_advance_record && $target_adv_id > 0) {
								$new_release_balance = max(0, $allowed_release_wd - $amount);
								$upd_adv_rel = "UPDATE advance_user SET release_amount = ? WHERE id = ?";
								if ($astmt = mysqli_prepare($conn, $upd_adv_rel)) {
									mysqli_stmt_bind_param($astmt, "di", $new_release_balance, $target_adv_id);
									mysqli_stmt_execute($astmt);
									mysqli_stmt_close($astmt);
								}
							}

							if ($ok_upd) {
								$date = date("Ymd");
								$time = time();
								$serial = 'W' . $date . $time . rand(1000, 9999);
								$sthiti = $isDemoUser ? 1 : 0;
								$tike = 'Applied';

								$ins = "INSERT INTO hintegedukolli (balakedara, motta, dharavahi, khateshonu, dinankavannuracisi, madari, tike, sthiti) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
								$ins_ok = false;
								if ($istmt = mysqli_prepare($conn, $ins)) {
									mysqli_stmt_bind_param($istmt, "idsssssi", $shonuid, $amount, $serial, $bid, $shnunc, $type, $tike, $sthiti);
									$ins_ok = mysqli_stmt_execute($istmt);
									if (!$ins_ok) {
										error_log("Insert hintegedukolli failed: " . mysqli_stmt_error($istmt));
									}
									mysqli_stmt_close($istmt);
								} else {
									error_log("Prepare failed (insert hintegedukolli): " . mysqli_error($conn));
									$ins_ok = false;
								}

								if ($ins_ok) {
									mysqli_commit($conn);
									
									// Currency Symbol Fix Notification
									$currencySymbol = ($type == "USDT" || $type == "3") ? "$" : "৳";
									$formattedAmount = $currencySymbol . $amount;

									$title = "Withdrawal Request";
									$message = "Your Withdrawal request of {$formattedAmount} has been requested Successfully";
									$state = 0;

									$noti_sql = "INSERT INTO deposit_withdrawal_notification (user_id, title, message, state, created_at) VALUES (?, ?, ?, ?, ?)";

									if ($nstmt = mysqli_prepare($conn, $noti_sql)) {
										mysqli_stmt_bind_param($nstmt, "issis", $shonuid, $title, $message, $state, $shnunc);
										mysqli_stmt_execute($nstmt);
										mysqli_stmt_close($nstmt);
									}

									$res['data'] = [
										'shonuid' => $shonuid,
										'serial' => $serial,
										'amount' => $amount,
										'type' => $type,
										'time' => $shnunc
									];
									$res['code'] = 0;
									$res['msg'] = 'Succeed';
									$res['msgCode'] = 0;
									http_response_code(200);
								} else {
									mysqli_rollback($conn);
									$res['code'] = 1;
									$res['msg'] = 'Database insertion error';
									$res['msgCode'] = 101;
									http_response_code(500);
								}
							} else {
								mysqli_rollback($conn);
								$res['code'] = 1;
								$res['msg'] = 'Failed to update balance';
								$res['msgCode'] = 101;
								http_response_code(500);
							}
						   
						} else {
							$res['code'] = 1;
							$res['msg'] = 'Insufficient balance';
							$res['msgCode'] = 142;
							$res['debug'] = $balarr ? $balarr['motta'] : null;
							http_response_code(200);
						}
						
					} else {
						$res['code'] = 1;
						$res['msg'] = 'invalid amount range';
						$res['msgCode'] = 142;
						http_response_code(200);
					}
				} else {
					$res['code'] = 4;
					$res['msg'] = 'No operation permission';
					$res['msgCode'] = 2;
					http_response_code(401);
				}
			} else {
				$res['code'] = 5;
				$res['msg'] = 'Wrong signature';
				$res['msgCode'] = 3;
				http_response_code(200);
			}
		} else {
			$res['code'] = 7;
			$res['msg'] = 'Param is Invalid';
			$res['msgCode'] = 6;
			http_response_code(200);
		}
	} else {
		http_response_code(405);
		echo json_encode($res);
		exit;
	}
	
	echo json_encode($res);
	mysqli_close($conn); 
?>