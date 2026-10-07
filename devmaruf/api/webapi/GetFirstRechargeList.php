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
		// ✅ FIXED: Check for getAll parameter (it might be optional in some cases)
		if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
			$getAll = isset($shonupost['getAll']) ? htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['getAll'])) : false;
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			
			if(empty($getAll)){
				$shonustr = '{"getAll":false,"language":'.$language.',"random":"'.$random.'"}';
			} else {
				$shonustr = '{"getAll":true,"language":'.$language.',"random":"'.$random.'"}';
			}
			$shonusign = strtoupper(md5($shonustr));
			
			if($shonusign == $signature){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = $bearer[1];				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				
				if($data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
					$sesresult=$conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					
					if($sesnum == 1){
						$shonuid = $data_auth['payload']['id'];
						
						// ✅ Get first deposit amount
						$tiramisu = "SELECT motta 
							FROM thevani 
							WHERE balakedara = '$shonuid' AND sthiti = 1 
							ORDER BY shonu ASC 
							LIMIT 1";
						$mascarpone = $conn->query($tiramisu);
						$pannacotta = mysqli_fetch_array($mascarpone);
						$firstDeposit = !empty($pannacotta['motta']) ? $pannacotta['motta'] : 0;
						
						// check if already finished
						$cannoli = "SELECT id FROM egrahcer_sonub WHERE dr = '".$shonuid."' AND status = 1";
						$cassata = $conn->query($cannoli);
						$risotto = mysqli_num_rows($cassata);
						
						// ✅ Fetch rewards sorted by rechargeAmount
						$query = "SELECT * FROM tbl_firstdepositreward ORDER BY rechargeAmount ASC";
						$result = $conn->query($query);
						
						$data = [];
						if ($result && $result->num_rows > 0) {
							$rewards = [];
							while ($row = $result->fetch_assoc()) {
								$rewards[] = $row;
							}
							
							foreach($rewards as $index => $row) {
								$temp = [];
								$temp['id'] = (int)$row['id'];
								$temp['rewardAmount'] = (float)$row['rewardAmount'];
								$temp['rechargeAmount'] = (float)$row['rechargeAmount'];
								$temp['order'] = (int)$row['id'];
								$temp['state'] = 1;
								$temp['createTime'] = date("Y-m-d H:i:s", strtotime("-1 day"));
								$temp['lastUpdateTime'] = $shnunc;

								// 👇 Range-based condition
								$canReceive = false;
								
								if($firstDeposit > 0) {
									// If it's the last reward, check if deposit >= current amount
									if($index == count($rewards) - 1) {
										if($firstDeposit >= $row['rechargeAmount']) {
											$canReceive = true;
										}
									} 
									// For other rewards, check range: current <= deposit < next
									else {
										$nextAmount = $rewards[$index + 1]['rechargeAmount'];
										if($firstDeposit >= $row['rechargeAmount'] && $firstDeposit < $nextAmount) {
											$canReceive = true;
										}
									}
								}
								
								$temp['canReceive'] = $canReceive;
								$temp['isFinshed'] = $risotto > 0 ? true : false;
								$data[] = $temp;
							}
						}
						
						$res['data'] = $data;
						$res['code'] = 0;
						$res['msg'] = 'Succeed';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);					
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