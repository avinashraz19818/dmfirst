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
	
	date_default_timezone_set("Asia/Kolkata");
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
				// Authorization হেডার চেক করার আগে টোকেন আছে কিনা নিশ্চিত হওয়া ভালো
				$author = '';
				if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
					$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
					$author = $bearer[1] ?? '';
				}
								
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				
				if($data_auth && isset($data_auth['status']) && $data_auth['status'] === 'Success') {
					// SQL Injection রোধ করতে টোকেনটি এস্কেপ করা হলো
					$safe_author = mysqli_real_escape_string($conn, $author);
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$safe_author'";
					$sesresult = $conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					
					if($sesnum == 1){
						$userId = (int)$data_auth['payload']['id'];
						
						$vipquery = "SELECT expe, lvl FROM vip WHERE userid = " . $userId;
						$vipresult = $conn->query($vipquery);
						$viparr = mysqli_fetch_array($vipresult);
						
						$data['userId'] = $userId;
						
						// ফিক্স: যদি ডাটাবেজে রেকর্ড না থাকে ($viparr যদি null হয়) তবে ডিফল্ট ০ সেট হবে
						$data['vipLevel'] = isset($viparr['lvl']) ? (int)$viparr['lvl'] : 0;
						$data['nickName'] = $data_auth['payload']['codechorkamukala'] ?? 'Unknown';
						$data['exp'] = isset($viparr['expe']) ? (int)$viparr['expe'] : 0;

						// চলতি মাসের বাকি দিন হিসাব করা
						$currentDay = (int) date('j');
						$daysInCurrentMonth = (int) date('t'); 
						$daysLeft = ($daysInCurrentMonth - $currentDay) + 1; 

						$data['settlementDate'] = $daysLeft;

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