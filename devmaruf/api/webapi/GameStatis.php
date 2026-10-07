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
			$endDate = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['endDate']));
			$startDate = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['startDate']));
			
			// সিগনেচার ভ্যালিডেশন
			$shonustr = '{"endDate":"'.$endDate.'","language":'.$language.',"random":"'.$random.'","startDate":"'.$startDate.'"}';
			
			$shonusign = strtoupper(md5($shonustr));
			if($shonusign == $signature){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = $bearer[1];				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				
				if($data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
					$sesresult = $conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					
					if($sesnum == 1){
						$shonuid = $data_auth['payload']['id'];
						
						// ১. লটারির ১২টি টেবিল থেকে মোট হিসাব আনার কুয়েরি
						$lottery_query = "SELECT SUM(ketebida) as total_bet, SUM(sesabida) as total_profit, COUNT(parichaya) as bet_count FROM (
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_aidudi UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_kemuru UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_drei UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_kemuru_drei UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_aidudi_drei UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_funf UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_kemuru_funf UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_aidudi_funf UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_zehn UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_kemuru_zehn UNION ALL
							SELECT parichaya, ketebida, sesabida, tiarikala, byabaharkarta FROM bajikattuttate_aidudi_zehn
						) as all_lotteries 
						WHERE byabaharkarta = $shonuid AND DATE(tiarikala) >= DATE('".$startDate."') AND DATE(tiarikala) <= DATE('".$endDate."')";
						
						$lottery_res = $conn->query($lottery_query);
						
						$data = [];
						$gameStatis = [];
						$sumBetAmount = 0;
						
						// লটারির ডেটা প্রোসেসিং
						if ($lottery_res && $row = $lottery_res->fetch_assoc()) {
							if ((int)$row['bet_count'] > 0) {
								$betAmt = (float)$row['total_bet'];
								$sumBetAmount += $betAmt;
								
								$gameStatis[] = [
									'gameType' => "10",
									'gameTypeName' => 'Lottery',
									'betAmount' => (string)$betAmt,
									'betCount' => (int)$row['bet_count'],
									'betWinLossAmount' => (string)$row['total_profit']
								];
							}
						}
						
						// ২. API গেম থেকে গ্রুপ করে ডেটা আনার কুয়েরি
						$api_game_query = "SELECT vendor_code, game_code, 
											SUM(bet_amount) as total_bet, 
											SUM(profit_loss) as total_profit_loss, 
											COUNT(id) as bet_count 
										FROM game_bet_logs 
										WHERE user_id = '$shonuid' 
										AND DATE(created_at) >= DATE('$startDate') 
										AND DATE(created_at) <= DATE('$endDate') 
										GROUP BY vendor_code, game_code 
										ORDER BY total_bet DESC";
									
						$api_game_res = $conn->query($api_game_query);
						
						// API গেমের ডেটা প্রোসেসিং
						if ($api_game_res && $api_game_res->num_rows > 0) {
							while ($row = $api_game_res->fetch_assoc()) {
								$betAmt = (float)$row['total_bet'];
								$winLoss = (float)$row['total_profit_loss'];
								$betCount = (int)$row['bet_count'];
								$gameCode = $row['game_code'];
								$vendorCode = $row['vendor_code'];

								$sumBetAmount += $betAmt;

								$gameStatis[] = [
									'gameType' => (string)$gameCode,
									'gameTypeName' => (string)(strtoupper($vendorCode) . ' - ' . substr($gameCode, 0, 6)),
									'betAmount' => (string)$betAmt,
									'betCount' => (int)$betCount,
									'betWinLossAmount' => (string)$winLoss
								];
							}
						}
						
						// ফাইনাল রেসপন্স অবজেক্ট
						$data['gameStatis'] = $gameStatis;
						$data['sumBetAmount'] = $sumBetAmount;									
						
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