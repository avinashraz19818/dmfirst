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
			$shonustr = '{"language":'.$language.',"random":"'.$random.'"}';	
			$shonusign = strtoupper(md5($shonustr));
			if($shonusign == $signature){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = $bearer[1] ?? '';				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				if($data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak
					  FROM shonu_subjects
					  WHERE akshinak = '$author'";
					$sesresult=$conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					if($sesnum == 1){
						$shonuid = $data_auth['payload']['id'];
						
						$existance = mysqli_query($conn,"SELECT `daysonearth`, `totalblessings` FROM `cihne` WHERE `identity`='".$shonuid."' ORDER BY `dearlord` DESC LIMIT 1");
						$existance_ar = mysqli_fetch_array($existance);
						
						// ফিক্স: যদি ডেটাবেজে ইউজার না থাকে তবে null এর বদলে ০ সেট হবে
						$daysonearth = isset($existance_ar['daysonearth']) ? (int)$existance_ar['daysonearth'] : 0;
						$totalblessings = isset($existance_ar['totalblessings']) ? (float)$existance_ar['totalblessings'] : 0.00;
						
						$data['signIn']['signCount'] = $daysonearth;
						$data['signIn']['isCycle'] = $daysonearth;
						$data['signIn']['signInSum'] = $totalblessings;
						
						$rewards = mysqli_query($conn, "SELECT * FROM signin_recharge_rewards WHERE status = 1 ORDER BY day ASC");
						$i = 0;
						while ($reward = mysqli_fetch_assoc($rewards)) {
							$data['signInRechargesList'][$i]['rechargesID'] = $reward['rechargesID'];
							$data['signInRechargesList'][$i]['amount'] = $reward['amount'];
							$data['signInRechargesList'][$i]['day'] = $reward['day'];
							$data['signInRechargesList'][$i]['bouns'] = $reward['bonus'];
							// ফিক্স: ভ্যারিয়েবল থেকে চেক করা হচ্ছে যাতে ওয়ার্নিং না আসে
							$data['signInRechargesList'][$i]['isReceive'] = ($daysonearth >= (int)$reward['day']) ? 1 : 0;
							$i++;
						}
						
						$res['data'] = $data;
						$res['code'] = 0;
						$res['msg'] = 'Succeed';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);																																																																	
					}
					else{
						$res['code'] = 4;
						$res['msg'] = 'No operation permission';
						$res['msgCode'] = 2;
						http_response_code(401);
						echo json_encode($res);
					}					
				}
				else{					
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