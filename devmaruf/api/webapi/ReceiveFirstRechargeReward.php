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
		if (isset($shonupost['language']) && isset($shonupost['taskId']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
			$language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
			$taskId = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['taskId']));
			$random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
			$signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
			$shonustr = '{"language":'.$language.',"random":"'.$random.'","taskId":'.$taskId.'}';
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
						
						
						// SQL query to fetch all data
$sql = "SELECT id, rewardAmount, rechargeAmount FROM tbl_firstdepositreward";
$result = $conn->query($sql);

// Variables to store data
$id1 = $id2 = $id3 = $id4 = $id5 = $id6 = $id7 = $id8 = 0;
$rewardAmount1 = $rewardAmount2 = $rewardAmount3 = $rewardAmount4 = $rewardAmount5 = $rewardAmount6 = $rewardAmount7 = $rewardAmount8 = 0;
$rechargeAmount1 = $rechargeAmount2 = $rechargeAmount3 = $rechargeAmount4 = $rechargeAmount5 = $rechargeAmount6 = $rechargeAmount7 = $rechargeAmount8 = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        ${"id".$id} = $id;
        ${"rewardAmount".$id} = $row['rewardAmount'];
        ${"rechargeAmount".$id} = $row['rechargeAmount'];
    }

    

} else {
    echo "No data found.";
}
						
						if($taskId == 1){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','1','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount1 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 2){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','2','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount2 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 3){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','3','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount3 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 4){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','4','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount4 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 5){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','5','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount5 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 6){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','6','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount6 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 7){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','7','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount7 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 8){
							$tathya = mysqli_query($conn,"INSERT INTO `egrahcer_sonub` (`dr`,`sturgis`,`status`,`time`) VALUES ('".$shonuid."','8','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $rewardAmount8 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						
						$res['code'] = 0;
						$res['msg'] = 'Succeed';
						$res['msgCode'] = 0;
						http_response_code(200);
						echo json_encode($res);			
					}
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