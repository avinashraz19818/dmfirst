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
	header('Access-Control-Allow-Origin: *' );
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
		// Handle Captcha Display
		if (!isset($shonupost['captchaId']) && !isset($shonupost['userPositionx'])) {
			$query = "SELECT captchaId, backgroundImage, sliderImage, correctPositionx FROM captcha_data ORDER BY RAND() LIMIT 1";
			$result = $conn->query($query);

			if ($result && $result->num_rows > 0) {
				$row = $result->fetch_assoc();

				$res['data'] = [
					'captchaId' => $row['captchaId'],
					'backgroundImage' => $row['backgroundImage'],
					'sliderImage' => $row['sliderImage'],
					
				];
				$res['code'] = 0;
				$res['msg'] = 'success';
				$res['msgCode'] = 0;
				http_response_code(200);
				echo json_encode($res);
				exit;
			} else {
				$res['code'] = 8;
				$res['msg'] = 'No captcha data found';
				$res['msgCode'] = 9;
				http_response_code(404);
				echo json_encode($res);
				exit;
			}
		}

		// Invalid Parameters
		$res['code'] = 7;
		$res['msg'] = 'Invalid request parameters';
		$res['msgCode'] = 6;
		http_response_code(400);
		echo json_encode($res);
		exit;
	} else {
		// Method Not Allowed
		http_response_code(405);
		echo json_encode($res);
		exit;
	}
?>