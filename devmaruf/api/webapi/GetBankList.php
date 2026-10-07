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
		if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp']) && isset($shonupost['withdrawid'])) {
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$withdrawid = (int) htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['withdrawid']));
			$shonustr = '{"language":'.$language.',"random":"'.$random.'","withdrawid":'.$withdrawid.'}';
			$shonusign = strtoupper(md5($shonustr));
			if($shonusign == $signature){
				$bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
				$author = isset($bearer[1]) ? $bearer[1] : '';
				$is_jwt_valid = is_jwt_valid($author); // Assuming this function validates the JWT
				$data_auth = json_decode($is_jwt_valid, 1);
				if($data_auth['status'] === 'Success') {
					$sesquery = "SELECT akshinak
					  FROM shonu_subjects
					  WHERE akshinak = '$author'";
					$sesresult=$conn->query($sesquery);
					$sesnum = mysqli_num_rows($sesresult);
					if($sesnum == 1){
						http_response_code(200);
						
						// If withdrawid == 3, return TRC20 (USDT-TRC20) as single payment type
						if ($withdrawid === 3) {
						    $banklist = [
						        [
						            "bankID"   => 99999,
						            "bankLogo" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/payNameIcon_20240323192848q2ac.png",
						            "bankName" => "TRC20",
						            "reserved" => "3",
						            // additional optional fields can be added if frontend expects them
						            "paySysName" => "USDT",
						            "minPrice" => 0,
						            "maxPrice" => 0
						        ],
						        [
						            "bankID"   => 99998,
						            "bankLogo" => "https://ossimg.bdg123456.com/BDGWin/payNameIcon/payNameIcon_20240323192848q2ac.png",
						            "bankName" => "BEP20",
						            "reserved" => "3",
						            // additional optional fields can be added if frontend expects them
						            "paySysName" => "USDT",
						            "minPrice" => 0,
						            "maxPrice" => 0
						        ]
						    ];
						
						    echo json_encode([
						        "data" => [
						            "banklist" => $banklist
						        ],
						        "code" => 0,
						        "msg" => "Succeed",
						        "msgCode" => 0,
						        "serviceNowTime" => $shnunc
						    ]);
						    exit;
						}
						
						// Default: fetch banks from DB (reserved = '1' by default)
						$banklist = [];
						$sql = "SELECT bankID, bankName, bankLogo, reserved FROM banks WHERE status = 'Active' AND reserved = '1'";
						$result = mysqli_query($conn, $sql);
						if ($result && mysqli_num_rows($result) > 0) {
							while ($row = mysqli_fetch_assoc($result)) {
								$banklist[] = [
									"bankID" => (int)$row['bankID'],
									"bankLogo" => $row['bankLogo'],
									"bankName" => $row['bankName'],
									"reserved" => $row['reserved']
								];
							}
						}

						echo json_encode([
							"data" => [
								"banklist" => $banklist
							],
							"code" => 0,
							"msg" => "Succeed",
							"msgCode" => 0,
							"serviceNowTime" => $shnunc
						]);
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
