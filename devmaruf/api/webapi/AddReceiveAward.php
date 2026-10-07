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
		if (isset($shonupost['language'], $shonupost['random'], $shonupost['signature'], $shonupost['rewardType'], $shonupost['timestamp'], $shonupost['vipLevel'])) {
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$taskId = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['rewardType']));
			$lvl = (int) htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['vipLevel'])); // Get the level
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$shonustr = '{"language":'.$language.',"random":"'.$random.'","taskId":'.$taskId.'}';
			$shonusign = strtoupper(md5($shonustr));
			
			if($shonusign != $signature){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = isset($bearer[1]) ? $bearer[1] : null; // Ensure the author variable is set
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				if($data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
					$sesresult = $conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					if($sesnum == 1){
						$shonuid = $data_auth['payload']['id'];
						
						// Initialize balance variable
						$balance = 0;

						if($taskId == 1){
						    $balanceArray = [60, 200, 700, 1900, 7000, 16900, 70000, 169000, 880000, 1890000];
						}
						else if($taskId == 2){
							$balanceArray = [30, 100, 300, 900, 2500, 7000, 29600, 70000, 369000, 890000];
						} else {
							// Handle unexpected taskId
							$res['code'] = 8;
							$res['msg'] = 'Invalid taskId';
							$res['msgCode'] = 7;
							http_response_code(200);
							echo json_encode($res);
							exit();
						}

						// Check if the level is valid
						if ($lvl >= 1 && $lvl <= 10) {
						    $balance = $balanceArray[$lvl - 1]; // Get balance based on level (0 indexed)

							// --- NEW: ensure single-claim only ---
							// Use transaction to avoid race conditions
							$alreadyClaimed = false;
							$conn->begin_transaction();

							try {
							    // Check existing claim in viprec for this user/type/level
							    $chk_sql = "SELECT id FROM viprec WHERE user_id = '". $conn->real_escape_string($shonuid) ."' AND type = '". $conn->real_escape_string($taskId) ."' AND lvl = '". intval($lvl) ."' LIMIT 1";
							    $chk_res = $conn->query($chk_sql);
							    if ($chk_res && $chk_res->num_rows > 0) {
							        $alreadyClaimed = true;
							    }

							    if ($alreadyClaimed) {
							        // rollback and respond
							        $conn->rollback();
							        $res['code'] = 10;
							        $res['msg'] = 'Already claimed';
							        $res['msgCode'] = 9;
							        http_response_code(200);
							        echo json_encode($res);
							        exit();
							    }

							    // Insert claim record
							    $viprec = "INSERT INTO viprec (user_id, type, motta, created_at, lvl) VALUES ('".$conn->real_escape_string($shonuid)."', '".$conn->real_escape_string($taskId)."', '".$conn->real_escape_string($balance)."', '".$conn->real_escape_string($shnunc)."', '".intval($lvl)."')";
							    $ins_res = $conn->query($viprec);
							    if (!$ins_res) {
							        throw new Exception("Failed to insert viprec: " . $conn->error);
							    }

							    // Update motta once
							    $nabikarana = "UPDATE shonu_kaichila SET motta = motta + '". $conn->real_escape_string($balance) ."' WHERE balakedara='". $conn->real_escape_string($shonuid) ."'";
							    $upd_res = $conn->query($nabikarana);
							    if (!$upd_res) {
							        throw new Exception("Failed to update motta: " . $conn->error);
							    }

							    // Commit transaction
							    $conn->commit();

							    $res = [
	                                'data' => [
	                                    'integral' => 0,
	                                    'balance' => (int)$balance
	                                ],
	                                'code' => 0,
	                                'msg' => 'Succeed',
	                                'msgCode' => 0
	                            ];
								http_response_code(200);
								echo json_encode($res);
								exit();
							} catch (Exception $e) {
							    // rollback on any failure
							    $conn->rollback();
							    $res['code'] = 12;
							    $res['msg'] = 'Server error';
							    $res['msgCode'] = 11;
							    $res['error'] = $e->getMessage();
							    http_response_code(500);
							    echo json_encode($res);
							    exit();
							}
						} else {
							$res['code'] = 9;
							$res['msg'] = 'Invalid level';
							$res['msgCode'] = 8;
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
			}
			else{
				$res['code'] = 5;
				$res['msg'] = 'Wrong signature';
				$res['msgCode'] = 3;
				http_response_code(200);
				echo json_encode($res);
			}
		}
		else{
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