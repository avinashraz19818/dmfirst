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
				$author = $bearer[1];				
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
						
						$todayMidnight = date("Y-m-d 00:00:00");

                        $recharge = mysqli_query($conn, "SELECT SUM(`motta`) as allrech FROM `thevani` WHERE `balakedara` = '".$shonuid."' AND `sthiti` = '1' AND `dinankavannuracisi` >= '$todayMidnight'");

						$rechargear = mysqli_fetch_array($recharge);
						$allrech = $rechargear['allrech'];
						
						$existance = mysqli_query($conn,"SELECT `dearlord` FROM `cihne` WHERE `identity`='".$shonuid."'");
						$existanceno = mysqli_num_rows($existance);
						if($existanceno == 0){
							if($allrech >= 300){
		// Step 1: Check already exists
$check = mysqli_query($conn, "SELECT * FROM cihne WHERE identity = '$shonuid' AND daysonearth = 1");
if (mysqli_num_rows($check) == 0) {

    // Step 2: Get bonus from DB
    $rewardQuery = mysqli_query($conn, "SELECT `bonus` FROM `signin_recharge_rewards` WHERE day = 1 AND status = 1 LIMIT 1");
    $rewardData = mysqli_fetch_assoc($rewardQuery);
    $day1bonus = $rewardData ? $rewardData['bonus'] : 0;

    // Step 3: Insert and update wallet only once
    $crdt = date("Y-m-d H:i:s");
    mysqli_query($conn, "INSERT INTO `cihne` (`identity`, `daysonearth`, `todayblessings`, `totalblessings`, `amen`) 
        VALUES ('$shonuid', '1', '$day1bonus', '$day1bonus', '$crdt')");

    mysqli_query($conn, "UPDATE shonu_kaichila SET motta = motta + $day1bonus WHERE balakedara='$shonuid'");
}

							    $conn->query($balanceup);
								$data = null;
								$res['data'] = $data;
								$res['code'] = 0;
								$res['msg'] = 'Succeed';
								$res['msgCode'] = 0;
								http_response_code(200);
								echo json_encode($res);	
							}
							else{
								$data = null;
								$res['data'] = $data;
								$res['code'] = 1;
								$res['msg'] = 'The recharge amount is not up to the standard';
								$res['msgCode'] = 502;
								http_response_code(200);
								echo json_encode($res);	
							}
						}
						else if($existanceno > 0 && $existanceno < 7){
							$crdt = date("Y-m-d H:i:m");
							$existance = mysqli_query($conn,"SELECT `dearlord` FROM `cihne` WHERE `identity`='".$shonuid."' AND DATE(`amen`) = DATE('".$crdt."')");
							$existanceno = mysqli_num_rows($existance);
							if($existanceno == 0){
								$existance = mysqli_query($conn,"SELECT `dearlord`, `amen` FROM `cihne` WHERE `identity`='".$shonuid."'");
								$existanceno = mysqli_num_rows($existance);
								$daysonearth = $existanceno + 1;
								// Get the reward data from DB based on day (from signin_recharge_rewards)
$rewardQuery = mysqli_query($conn, "SELECT * FROM signin_recharge_rewards WHERE day = '$daysonearth' AND status = 1 LIMIT 1");
$rewardData = mysqli_fetch_assoc($rewardQuery);

if ($rewardData) {
    $todayblessings = $rewardData['bonus'];
    $rechtobe = $rewardData['amount'];

    // Get total blessings so far from existing records
    $blessingsQuery = mysqli_query($conn, "SELECT SUM(`todayblessings`) AS total FROM `cihne` WHERE `identity`='".$shonuid."'");
    $blessingsResult = mysqli_fetch_assoc($blessingsQuery);
    $totalblessings = $blessingsResult['total'] + $todayblessings;
}








								if($allrech >= $rechtobe){
									$sql= mysqli_query($conn,"INSERT INTO `cihne` (`identity`, `daysonearth`, `todayblessings`, `totalblessings`, `amen`) VALUES ('".$shonuid."','".$daysonearth."','".$todayblessings."','".$totalblessings."','".$crdt."')");
									$balanceup = "UPDATE shonu_kaichila SET motta = motta + $todayblessings WHERE balakedara='$shonuid'";
							        $conn->query($balanceup);
									$data = null;
									$res['data'] = $data;
									$res['code'] = 0;
									$res['msg'] = 'Succeed';
									$res['msgCode'] = 0;
									http_response_code(200);
									echo json_encode($res);
								}
								else{
									$data = null;
									$res['data'] = $data;
									$res['code'] = 1;
									$res['msg'] = 'The recharge amount is not up to the standard';
									$res['msgCode'] = 502;
									http_response_code(200);
									echo json_encode($res);
								}								
							}
							else{
								$data = null;
								$res['data'] = $data;
								$res['code'] = 1;
								$res['msg'] = 'Received Today';
								$res['msgCode'] = 501;
								http_response_code(200);
								echo json_encode($res);	
							}
						}
						else{
							$data = null;
							$res['data'] = $data;
							$res['code'] = 1;
							$res['msg'] = 'The recharge amount is not up to the standard';
							$res['msgCode'] = 502;
							http_response_code(200);
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