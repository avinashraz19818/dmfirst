<?php 
	include "../../conn.php";
	include "../../functions2.php";
	
	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('vary: Origin');
	
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
		// payTypeId বাদ দেওয়া হয়েছে কন্ডিশন থেকে, যেন optional হিসেবে কাজ করতে পারে
		if (isset($shonupost['language']) && isset($shonupost['payid']) && isset($shonupost['random']) && isset($shonupost['signature'])) {
			
			// Null coalescing (??) ব্যবহার করে safe sanitization করা হয়েছে
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language'] ?? ''));
			$payTypeId = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['payTypeId'] ?? ''));
			$payid = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['payid'] ?? ''));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random'] ?? ''));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature'] ?? ''));
			
			$shonustr = '{"language":'.$language.',"payTypeId":'.$payTypeId.',"payid":'.$payid.',"random":"'.$random.'"}';
			$shonusign = strtoupper(md5($shonustr));

			if($shonusign){
				$bearer = isset($_SERVER['HTTP_AUTHORIZATION']) ? explode(" ", $_SERVER['HTTP_AUTHORIZATION']) : [];
				$author = isset($bearer[1]) ? $bearer[1] : '';				
				
				$is_jwt_valid = is_jwt_valid($author);
				$data_auth = json_decode($is_jwt_valid, 1);
				if(isset($data_auth['status']) && $data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
					$sesresult=$conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					if($sesnum == 1){
						$sites = 'https://tigroclub5.com';
						
						$data["rechargetypelist"] = [];
						$data['banklist'] = null;
						$data['localUsdtlist'] = null;
						$data['thirdPayBankList'] = null;

						// ১. গ্লোবাল ডিপোজিট কন্ট্রোল চেক (web_setting টেবিল থেকে)
						$allowDeposit = 1;
						$settingQuery = mysqli_query($conn, "SELECT allow_deposit FROM web_setting LIMIT 1");
						if ($settingQuery && mysqli_num_rows($settingQuery) > 0) {
							$settingRow = mysqli_fetch_assoc($settingQuery);
							$allowDeposit = intval($settingRow['allow_deposit']);
						}

						// যদি এডমিন থেকে ডিপোজিট সিস্টেম সম্পূর্ণ অফ থাকে
						if ($allowDeposit === 0) {
							$res['data'] = $data;
							$res['code'] = 0;
							$res['msg'] = 'Deposit system is currently suspended';
							$res['msgCode'] = 0;
							http_response_code(200);
							echo json_encode($res);
							exit;
						}

						// ২. ডেটাবেজ থেকে ডাইনামিকলি পেমেন্ট মেথড তুলে আনা (শুধুমাত্র Active গুলো আসবে)
						$payid = (int)$payid;
						$gatewayQuery = "SELECT * FROM payment_types WHERE payID = '$payid' AND status = 'Active' ORDER BY sort ASC";
						$gatewayResult = $conn->query($gatewayQuery);
						
						$index = 0;
						while ($row = mysqli_fetch_assoc($gatewayResult)) {
							// স্কোপ টেক্সট থেকে কুইক কনফিগারেশন অ্যারে তৈরি করা
							$quickConfigList = [];
							if (!empty($row['scope'])) {
								$amounts = explode('|', $row['scope']);
								foreach ($amounts as $amt) {
									$quickConfigList[] = [
										"rechargeAmount" => (float)$amt,
										"giftAmount" => 0.0
									];
								}
							}

							// এডমিন প্যানেলের ডেটাবেজ অনুযায়ী ইউজার রেসপন্স সাজানো
							$data["rechargetypelist"][$index] = [
								"payTypeID" => (int)$row['payTypeID'],
								"payID" => (int)$row['payID'],
								"payName" => $row['payName'],
								"paySysName" => $row['paySysName'],
								"miniPrice" => (int)$row['minPrice'],
								"maxPrice" => (int)$row['maxPrice'],
								"scope" => $row['scope'],
								// ইউআরএল স্ট্রাকচার এডমিন বা লোকাল অনুযায়ী ডাইনামিক সেট করা
								"paySendUrl" => (!empty($row['payNameUrl']) ? $row['payNameUrl'] : $sites."/pay/gateway.php"),
								"parameters" => '',
								"startTime" => "00:00",
								"endTime" => "24:00",
								"rechargeRifts" => (float)$row['maxRechargeRifts'],
								"c2cUnitAmount" => null,
								"quickConfig" => "",
								"quickConfigList" => $quickConfigList,
								"random" => 0.8192269882695508,
								"sort" => (int)$row['sort']
							];
							$index++;
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