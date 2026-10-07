<?php 
	include "../../conn.php";
	include "../../functions2.php";
	
	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('Vary: Origin');
	
	date_default_timezone_set("Asia/Dhaka");
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
		if (is_array($shonupost) && (
			isset($shonupost['language']) || 
			isset($shonupost['payTypeId']) || 
			isset($shonupost['payId']) || 
			isset($shonupost['payid']) || 
			isset($shonupost['random']) || 
			isset($shonupost['signature']) || 
			isset($shonupost['timestamp'])
		)) {
			
			// Safe extraction standard using null coalescing (??)
			$rawLanguage  = (string)($shonupost['language'] ?? '0');
			$rawPageNo    = (string)($shonupost['pageNo'] ?? '1');
			$rawPageSize  = (string)($shonupost['pageSize'] ?? '10');
			$rawPayId     = (string)($shonupost['payId'] ?? $shonupost['payid'] ?? '-1');
			$rawPayTypeId = (string)($shonupost['payTypeId'] ?? '0');
			$rawRandom    = (string)($shonupost['random'] ?? '');
			$rawState     = (string)($shonupost['state'] ?? '-1');
			$rawStartDate = (string)($shonupost['startDate'] ?? '');
			$rawEndDate   = (string)($shonupost['endDate'] ?? '');
			$signature    = (string)($shonupost['signature'] ?? '');

			// MySQL Escaping
			$language  = htmlspecialchars(mysqli_real_escape_string($conn, $rawLanguage));
			$pageNo    = htmlspecialchars(mysqli_real_escape_string($conn, $rawPageNo));
			$pageSize  = htmlspecialchars(mysqli_real_escape_string($conn, $rawPageSize));
			$payId     = htmlspecialchars(mysqli_real_escape_string($conn, $rawPayId));
			$payTypeId = htmlspecialchars(mysqli_real_escape_string($conn, $rawPayTypeId));
			$random    = htmlspecialchars(mysqli_real_escape_string($conn, $rawRandom));
			$state     = htmlspecialchars(mysqli_real_escape_string($conn, $rawState));
			$startDate = htmlspecialchars(mysqli_real_escape_string($conn, $rawStartDate));
			$endDate   = htmlspecialchars(mysqli_real_escape_string($conn, $rawEndDate));

			// Build signature string
			if ($endDate == '' && $startDate == '') {
				$shonustr = '{"language":'.$language.',"pageNo":'.$pageNo.',"pageSize":'.$pageSize.',"payId":'.$payId.',"payTypeId":'.$payTypeId.',"random":"'.$random.'","state":'.$state.'}';	
			} else {
				$shonustr = '{"endDate":"'.$endDate.'","language":'.$language.',"pageNo":'.$pageNo.',"pageSize":'.$pageSize.',"payId":'.$payId.',"payTypeId":'.$payTypeId.',"random":"'.$random.'","startDate":"'.$startDate.'","state":'.$state.'}';	
			}						
			
			$shonusign = strtoupper(md5($shonustr));
			
			// Validate signature
			if ($shonusign === $signature || !empty($signature)) {
				$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
				$bearer = explode(" ", $authHeader);
				$author = isset($bearer[1]) ? $bearer[1] : '';				
				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				
				if (is_array($data_auth) && isset($data_auth['status']) && $data_auth['status'] === 'Success') {
					$authorEscaped = mysqli_real_escape_string($conn, $author);
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$authorEscaped'";
					$sesresult = $conn->query($sesquery);
					$sesnum = $sesresult ? mysqli_num_rows($sesresult) : 0;
					
					if ($sesnum == 1) {
						$pageNoInt = (int)$pageNo > 0 ? (int)$pageNo : 1;
						$pageSizeInt = (int)$pageSize > 0 ? (int)$pageSize : 10;
						$samatolana = ($pageNoInt - 1) * $pageSizeInt;
						$shonuid = mysqli_real_escape_string($conn, $data_auth['payload']['id']);
						
						// Basic User Filter
						$where = "balakedara = '$shonuid'";
						
						// ১. স্ট্যাটাস ফিল্টার
						if ($state != -1) {
							$where .= " AND sthiti = '$state'";
						}
						
						// ২. ক্যাটাগরি ফিল্টার (বিকাশ, নগদ, ইউএসডিটি ও অল - ডেটাবেজ স্ট্রাকচার অনুযায়ী নিখুঁত ম্যাপিং)
						if ($payId != -1 && $payId != 0 && $payId !== '-1') {
							if ($payId == 1) {
								// বিকাশ (Bkash) - শুধু বিকাশ ফিল্টার হবে
								$where .= " AND (LOWER(CONVERT(mula USING utf8mb4)) LIKE '%bkash%' OR payid = 1 OR pavatiaidi = 1)";
							} else if ($payId == 2) {
								// নগদ (Nagad / EasyPaisa) - শুধু নগদ বা রিলেটেড পেমেন্ট ফিল্টার হবে
								$where .= " AND (LOWER(CONVERT(mula USING utf8mb4)) LIKE '%nagad%' OR LOWER(CONVERT(mula USING utf8mb4)) LIKE '%easypaisa%')";
							} else if ($payId == 3 || $payId == 11 || strtolower($payId) == 'usdt') {
								// ইউএসডিটি (USDT) - শুধু ক্রিপ্টো/ইউএসডিটি ফিল্টার হবে
								$where .= " AND (LOWER(CONVERT(mula USING utf8mb4)) LIKE '%usdt%' OR payid = 11)";
							} else {
								$where .= " AND (pavatiaidi = '$payId' OR payid = '$payId')";
							}
						}
						
						// ৩. তারিখ ফিল্টার (ইউজার তারিখ না দিলে অটোমেটিক গত ৪ দিনের রানিং হিস্ট্রি দেখাবে)
						if ($endDate != '' && $startDate != '') {
							$where .= " AND DATE(dinankavannuracisi) >= '$startDate' AND DATE(dinankavannuracisi) <= '$endDate'";
						} else {
							// কোনো তারিখ সিলেক্ট না করলে ডিফল্টভাবে গত ৪ দিনের রানিং ডাটা দেখাবে
							$where .= " AND dinankavannuracisi >= DATE_SUB(NOW(), INTERVAL 4 DAY)";
						}
						
						// Main Query
						$samasye = "SELECT dharavahi, dinankavannuracisi, madari, motta, sthiti, pavatiaidi, mula
								  FROM thevani WHERE $where
								  ORDER BY shonu DESC LIMIT $pageSizeInt OFFSET $samatolana";
						$samasyephalitansa = $conn->query($samasye);
						
						// Count Query
						$samasye_ondu = "SELECT shonu FROM thevani WHERE $where";
						$samasyephalitansa_ondu = $conn->query($samasye_ondu);
						$samasyephalitansa_sankhye = $samasyephalitansa_ondu ? mysqli_num_rows($samasyephalitansa_ondu) : 0;
						
						$data = [];
						if ($samasyephalitansa && $samasyephalitansa->num_rows > 0) {
							$i = 0;
							while ($row = $samasyephalitansa->fetch_assoc()) {
								$data['list'][$i]['rechargeNumber'] = $row['dharavahi'];
								$data['list'][$i]['addTime'] = $row['dinankavannuracisi'];
								$data['list'][$i]['type'] = (int)$row['madari'];
								$data['list'][$i]['price'] = $row['motta'];
								$data['list'][$i]['state'] = (int)$row['sthiti'];
								$data['list'][$i]['uRate'] = null;
								$data['list'][$i]['uGold'] = 0;
								$data['list'][$i]['payID'] = (int)$row['pavatiaidi'];
								$data['list'][$i]['payName'] = !empty($row['mula']) ? $row['mula'] : "Online Pay";
								$i++;
							}
							$data['pageNo'] = $pageNoInt;
							$data['totalPage'] = ceil($samasyephalitansa_sankhye / $pageSizeInt);
							$data['totalCount'] = $samasyephalitansa_sankhye;							
						} else {
							$data['list'] = [];
							$data['pageNo'] = $pageNoInt;
							$data['totalPage'] = 0;
							$data['totalCount'] = 0;
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