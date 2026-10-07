<?php 
	include "../../conn.php";
	include "../../functions2.php";
	
	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: *');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('Vary: Origin');
	
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
		if (isset($shonupost['language'], $shonupost['random'], $shonupost['signature'], $shonupost['timestamp'])) {
			
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$pageNo = isset($shonupost['pageNo']) ? max(1, intval($shonupost['pageNo'])) : 1;
			$pageSize = isset($shonupost['pageSize']) ? max(1, intval($shonupost['pageSize'])) : 10;
			
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$typeId = isset($shonupost['typeId']) ? intval($shonupost['typeId']) : 1;
			
			$langVal = is_numeric($language) ? intval($language) : '"'.$language.'"';
			
			// Signature Matching
			$shonustr = '{"language":'.$langVal.',"pageNo":'.$pageNo.',"pageSize":'.$pageSize.',"random":"'.$random.'","typeId":'.$typeId.'}';
			$shonusign = strtoupper(md5($shonustr));
			
			if($shonusign === $signature){
				$headers = apache_request_headers();
				$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : (isset($headers['Authorization']) ? $headers['Authorization'] : '');
				$bearer = explode(" ", $authHeader);
				$author = isset($bearer[1]) ? trim($bearer[1]) : '';				
				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, true);
				
				if(isset($data_auth['status']) && $data_auth['status'] === 'Success') {
					$stmt = $conn->prepare("SELECT akshinak FROM shonu_subjects WHERE akshinak = ?");
					$stmt->bind_param("s", $author);
					$stmt->execute();
					$sesresult = $stmt->get_result();
					
					if($sesresult && $sesresult->num_rows == 1){
						
						switch($typeId){
							case 1: $jayshriram = 'bajikattuttate'; break;
							case 2: $jayshriram = 'bajikattuttate_drei'; break;
							case 3: $jayshriram = 'bajikattuttate_funf'; break;
							case 4: $jayshriram = 'bajikattuttate_zehn'; break;
							default: $jayshriram = 'bajikattuttate'; break;
						}
						
						$shonuid = $data_auth['payload']['id'];
						$samatolana = ($pageNo - 1) * $pageSize;
						
						$samasye = "SELECT kalaparichaya, ojana, menge, wettanzahl, ketebida, phalaphala, sesabida, ergebnis, zufallig, tiarikala
									FROM `".$jayshriram."` WHERE byabaharkarta = ?
									ORDER BY parichaya DESC LIMIT ? OFFSET ?";
						
						$stmt_samasye = $conn->prepare($samasye);
						$stmt_samasye->bind_param("iii", $shonuid, $pageSize, $samatolana);
						$stmt_samasye->execute();
						$samasyephalitansa = $stmt_samasye->get_result();
						
						$data['list'] = [];
						if ($samasyephalitansa && $samasyephalitansa->num_rows > 0) {
							$i = 0;
							while ($row = $samasyephalitansa->fetch_assoc()) {
								
								// ojana ম্যাপিং
								switch($row['ojana']){
									case 10: $ojana = 'red'; break;
									case 11: $ojana = 'green'; break;
									case 12: $ojana = 'violet'; break;
									case 13: $ojana = 'big'; break;
									case 14: $ojana = 'small'; break;
									default: $ojana = $row['ojana']; break;
								}	
								
								// চেক করা হচ্ছে ergebnis (Result Number) এখনও ডাটাবেজে এসেছে কিনা
								$hasResult = ($row['ergebnis'] !== null && $row['ergebnis'] !== '' && $row['ergebnis'] >= 0);

								// --- ১. গেম রেজাল্ট এখনও প্রকাশ না হলে (Pending) ---
								if (!$hasResult || $row['phalaphala'] == 'pending' || $row['phalaphala'] == '') {
									$data['list'][] = [
										'orderNumber' => $i,
										'issueNumber' => $row['kalaparichaya'],
										'amount' => $row['menge'],
										'betCount' => $row['wettanzahl'],
										'realAmount' => floatval($row['ketebida']),
										'fee' => 0,
										'number' => 0,
										'selectType' => $ojana,
										'state' => 2, // 2 = Pending / Waiting
										'profitAmount' => 0,
										'amountLoss' => 0,
										'premium' => $row['zufallig'],
										'gameType' => $typeId,
										'addTime' => $row['tiarikala'],
									];
								}
								// --- ২. নিশ্চিত উইন (phalaphala == 'gagner') ---
								else if ($row['phalaphala'] == 'gagner') {
									$ergebnis = intval($row['ergebnis']);
									$ketebida = floatval($row['ketebida']);
									$fee = $ketebida / 100 * 2;
									
									$data['list'][] = [
										'orderNumber' => $i,
										'issueNumber' => $row['kalaparichaya'],
										'amount' => $row['menge'],
										'betCount' => $row['wettanzahl'],
										'realAmount' => $ketebida - $fee,
										'fee' => $fee,
										'number' => $ergebnis,
										'selectType' => $ojana,
										'state' => 1, // 1 = Win
										'profitAmount' => floatval($row['sesabida']),
										'amountLoss' => 0,
										'premium' => $row['zufallig'],
										'gameType' => $typeId,
										'addTime' => $row['tiarikala'],
									];
								}
								// --- ৩. নিশ্চিত লস (phalaphala == 'perte' এবং রেজাল্ট নাম্বার ডাটাবেজে উপস্থিত) ---
								else {
									$lossValue = floatval($row['ketebida']);
									
									$data['list'][] = [
										'orderNumber' => $i,
										'issueNumber' => $row['kalaparichaya'],
										'amount' => $row['menge'],
										'betCount' => $row['wettanzahl'],
										'realAmount' => $lossValue,
										'fee' => 0,
										'number' => intval($row['ergebnis']),
										'selectType' => $ojana,
										'state' => 0, // 0 = Failed / Loss
										'profitAmount' => $lossValue,
										'amountLoss' => $lossValue,
										'premium' => $row['zufallig'],
										'gameType' => $typeId,
										'addTime' => $row['tiarikala'],
									];
								}
								$i++;
							}
						}
						
						// মোট পেজ গণনার কুয়েরি
						$stmt_count = $conn->prepare("SELECT COUNT(parichaya) AS total FROM `".$jayshriram."` WHERE byabaharkarta = ?");
						$stmt_count->bind_param("i", $shonuid);
						$stmt_count->execute();
						$samasyephalitansa_ondu = $stmt_count->get_result();
						$row_count = $samasyephalitansa_ondu->fetch_assoc();
						$samasyephalitansa_sankhye = intval($row_count['total']);
						
						$data['pageNo'] = $pageNo;
						$data['pageSize'] = $pageSize;
						$data['totalPage'] = $samasyephalitansa_sankhye > 0 ? ceil($samasyephalitansa_sankhye / $pageSize) : 1;
						$data['totalCount'] = $samasyephalitansa_sankhye;
						
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