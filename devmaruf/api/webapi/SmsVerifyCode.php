<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
include "../../conn.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: *' );
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
	if (isset($shonupost['codeType']) && isset($shonupost['language']) && isset($shonupost['phone']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
		$codeType = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['codeType']));
		$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
		$phone = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['phone']));
		$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
		$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
		$shonustr = '{"codeType":' . $codeType . ',"language":' . $language . ',"phone":"' . $phone . '","random":"' . $random . '"}';
		$shonusign = strtoupper(md5($shonustr));
		if ($shonusign == $signature) {
			if (substr($phone, 0, 2) == "91") {
				$mobile = substr($phone, 2);
			} else {
				$mobile = $phone;
			}
			$samasye = "SELECT id
				  FROM shonu_subjects WHERE mobile = $mobile";
			$samasyephalitansa = $conn->query($samasye);
			$samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
			if ($samasyephalitansa_dhadi == 1) {
				function generateOTP()
                {
                    $characters = '123456789';
                    $charactersLength = strlen($characters);
                    $randomString = '';
                    for ($i = 0; $i < 6; $i++) {
                        $randomString .= $characters[rand(0, $charactersLength - 1)];
                    }
                    return $randomString;
                }
                
                $otp = generateOTP();
                $createdate = date("Y-m-d H:i:s");
                
                
                $sql = mysqli_query($conn, "INSERT INTO `otp_record` (`mobile`, `otp`, `type`, `createdate`) VALUES ('" . $mobile . "','" . $otp . "','Reset PSWD','" . $createdate . "')");
                
                // Laaffic OTP API
                $apiKey = "LpjTX6Vr";
                $apiSecret = "HkZfhvCi";
                $appId = "W7NT1eVk";  
                $mobile = $mobile;  
                $content = "Your OTP is: $otp"; 
                $senderId = "1234"; 
                $orderId = time() . $otp;  
                
                date_default_timezone_set("UTC");
                $timeStamp = time();
                $sign = md5($apiKey . $apiSecret . $timeStamp);
                
                
                $dataArr = [
                    'appId' => $appId,
                    'numbers' => $mobile,
                    'content' => $content,
                    'senderId' => $senderId,
                    'orderId' => $orderId
                ];
                
                
                $data = json_encode($dataArr);
                
                
                $headers = [
                    'Content-Type: application/json;charset=UTF-8',
                    "Sign: $sign",
                    "Timestamp: $timeStamp",
                    "Api-Key: $apiKey"
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.laaffic.com/v3/sendSms");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 600);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                
                $response = curl_exec($ch);
                curl_close($ch);
                


				if ($response === FALSE) {
					$res['code'] = 1;
					$res['msg'] = 'SMS sending failed';
					$res['msgCode'] = 140;
					$res['okdebug'] = $response;
					http_response_code(200);
					echo json_encode($res);
				} else {
					$res['code'] = 0;
					$res['msg'] = 'Succeed';
					$res['msgCode'] = 0;
					$res['okdebug'] = $response;
					http_response_code(200);
					echo json_encode($res);
				}
			} else {
				if ($codeType == 1) {
					function generateOTP()
					{
						$characters = '123456789';
						$charactersLength = strlen($characters);
						$randomString = '';
						for ($i = 0; $i < 6; $i++) {
							$randomString .= $characters[rand(0, $charactersLength - 1)];
						}
						return $randomString;
					}

					$otp = generateOTP();
					$createdate = date("Y-m-d H:i:s");

					// Save OTP in database
					$sql = mysqli_query($conn, "INSERT INTO `otp_record` (`mobile`, `otp`, `type`, `createdate`) VALUES ('" . $mobile . "','" . $otp . "','Reset PSWD','" . $createdate . "')");

					// DVHosting OTP API
					$api_key = "bDqJFiq9";
					$otp_url = "https://api.laaffic.com/v3/sendSms" . urlencode($api_key) . "&number=" . urlencode($mobile) . "&otp=" . urlencode($otp);

					$arrContextOptions = array(
						"ssl" => array(
							"verify_peer" => false,
							"verify_peer_name" => false,
						),
					);

					$response = file_get_contents($otp_url, false, stream_context_create($arrContextOptions));

					if ($response === FALSE) {
						$res['code'] = 1;
						$res['msg'] = 'SMS sending failed';
						$res['msgCode'] = 140;
						http_response_code(200);
						echo json_encode($res);
					} else {
						$res['code'] = 0;
						$res['msg'] = 'Succeed';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);
					}
				} else {
					$res['code'] = 1;
					$res['msg'] = 'User does not exist';
					$res['msgCode'] = 101;
					http_response_code(200);
					echo json_encode($res);
				}
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