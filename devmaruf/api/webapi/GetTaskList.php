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
					$findowncode = "SELECT owncode FROM shonu_subjects WHERE id = ".$shonuid;
					$owncodeqr = $conn->query($findowncode);
					$owncodear = mysqli_fetch_array($owncodeqr);
					$owncode = $owncodear['owncode'];
					
					$data['totalPeople'] = 50000;
					$data['totalAmount'] = null;
					
					// Fetch ALL task data from database
					$sql = "SELECT taskID, taskAmount, rechargeAmount, taskPeople 
							FROM tbl_invitebonus 
							WHERE taskID BETWEEN 0 AND 13 
							ORDER BY taskID";

					$result = $conn->query($sql);
					$taskData = [];

					if ($result && $result->num_rows > 0) {
						while ($row = $result->fetch_assoc()) {
							$taskID = $row['taskID'];
							$taskData[$taskID] = [
								'taskAmount' => $row['taskAmount'],
								'rechargeAmount' => $row['rechargeAmount'],
								'taskPeople' => $row['taskPeople']
							];
						}
					} else {
						// Default values if no data found
						for ($i = 0; $i <= 13; $i++) {
							$taskData[$i] = [
								'taskAmount' => 0,
								'rechargeAmount' => 300,
								'taskPeople' => 0
							];
						}
					}

					// Calculate pannacotta (users with total_motta >= 300)
					$tiramisu = "SELECT SUM(`motta`) as total_motta
					FROM `thevani`
					WHERE `sthiti` = 1 AND `balakedara` IN (SELECT `id` FROM `shonu_subjects` WHERE `code` = '$owncode')
					GROUP BY `balakedara`
					HAVING total_motta >= 300";
					$mascarpone = $conn->query($tiramisu);
					$pannacotta = mysqli_num_rows($mascarpone);
					
					// Calculate efficient people (total referrals)
					$effqry = "SELECT `id` FROM `shonu_subjects` WHERE `code` = '$owncode'";
					$effrn = $conn->query($effqry);
					$effrw = mysqli_num_rows($effrn);
					
					// Task List 0 - taskID 0
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 1 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][0]['taskID'] = 0;
					$data['taskList'][0]['taskAmount'] = $taskData[0]['taskAmount'];
					$data['taskList'][0]['rechargeAmount'] = $taskData[0]['rechargeAmount'];
					$data['taskList'][0]['rechargeAmount_All'] = null;
					$data['taskList'][0]['taskPeople'] = $taskData[0]['taskPeople'];
					$data['taskList'][0]['rechargePeople'] = $pannacotta;
					$data['taskList'][0]['taskRechargePeople'] = $taskData[0]['taskPeople'];
					$data['taskList'][0]['efficientPeople'] = $effrw;
					$data['taskList'][0]['title'] = null;
					$data['taskList'][0]['title2'] = null;
					$data['taskList'][0]['isReceive'] = $isreceived;
					$data['taskList'][0]['isFinshed'] = $pannacotta >= $taskData[0]['taskPeople'] ? true : false;
					$data['taskList'][0]['beginDate'] = '2025-01-16';
					$data['taskList'][0]['endDate'] = '2099-01-16';
					
					// Task List 1 - taskID 2
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 2 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					$data['taskList'][1]['taskID'] = 2;
					$data['taskList'][1]['taskAmount'] = $taskData[2]['taskAmount'];
					$data['taskList'][1]['rechargeAmount'] = $taskData[2]['rechargeAmount'];
					$data['taskList'][1]['rechargeAmount_All'] = null;
					$data['taskList'][1]['taskPeople'] = $taskData[2]['taskPeople'];
					$data['taskList'][1]['rechargePeople'] = $pannacotta;
					$data['taskList'][1]['taskRechargePeople'] = $taskData[2]['taskPeople'];
					$data['taskList'][1]['efficientPeople'] = $effrw;
					$data['taskList'][1]['title'] = null;
					$data['taskList'][1]['title2'] = null;
					$data['taskList'][1]['isReceive'] = $isreceived;
					$data['taskList'][1]['isFinshed'] = $pannacotta >= $taskData[2]['taskPeople'] ? true : false;
					$data['taskList'][1]['beginDate'] = '2025-01-16';
					$data['taskList'][1]['endDate'] = '2099-01-16';
					
					// Task List 2 - taskID 3
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 3 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][2]['taskID'] = 3;
					$data['taskList'][2]['taskAmount'] = $taskData[3]['taskAmount'];
					$data['taskList'][2]['rechargeAmount'] = $taskData[3]['rechargeAmount'];
					$data['taskList'][2]['rechargeAmount_All'] = null;
					$data['taskList'][2]['taskPeople'] = $taskData[3]['taskPeople'];
					$data['taskList'][2]['rechargePeople'] = $pannacotta;
					$data['taskList'][2]['taskRechargePeople'] = $taskData[3]['taskPeople'];
					$data['taskList'][2]['efficientPeople'] = $effrw;
					$data['taskList'][2]['title'] = null;
					$data['taskList'][2]['title2'] = null;
					$data['taskList'][2]['isReceive'] = $isreceived;
					$data['taskList'][2]['isFinshed'] = $pannacotta >= $taskData[3]['taskPeople'] ? true : false;
					$data['taskList'][2]['beginDate'] = '2025-01-16';
					$data['taskList'][2]['endDate'] = '2099-01-16';
					
					// Task List 3 - taskID 4
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 4 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][3]['taskID'] = 4;
					$data['taskList'][3]['taskAmount'] = $taskData[4]['taskAmount'];
					$data['taskList'][3]['rechargeAmount'] = $taskData[4]['rechargeAmount'];
					$data['taskList'][3]['rechargeAmount_All'] = null;
					$data['taskList'][3]['taskPeople'] = $taskData[4]['taskPeople'];
					$data['taskList'][3]['rechargePeople'] = $pannacotta;
					$data['taskList'][3]['taskRechargePeople'] = $taskData[4]['taskPeople'];
					$data['taskList'][3]['efficientPeople'] = $effrw;
					$data['taskList'][3]['title'] = null;
					$data['taskList'][3]['title2'] = null;
					$data['taskList'][3]['isReceive'] = $isreceived;
					$data['taskList'][3]['isFinshed'] = $pannacotta >= $taskData[4]['taskPeople'] ? true : false;
					$data['taskList'][3]['beginDate'] = '2025-01-16';
					$data['taskList'][3]['endDate'] = '2099-01-16';
					
					// Task List 4 - taskID 5
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 5 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][4]['taskID'] = 5;
					$data['taskList'][4]['taskAmount'] = $taskData[5]['taskAmount'];
					$data['taskList'][4]['rechargeAmount'] = $taskData[5]['rechargeAmount'];
					$data['taskList'][4]['rechargeAmount_All'] = null;
					$data['taskList'][4]['taskPeople'] = $taskData[5]['taskPeople'];
					$data['taskList'][4]['rechargePeople'] = $pannacotta;
					$data['taskList'][4]['taskRechargePeople'] = $taskData[5]['taskPeople'];
					$data['taskList'][4]['efficientPeople'] = $effrw;
					$data['taskList'][4]['title'] = null;
					$data['taskList'][4]['title2'] = null;
					$data['taskList'][4]['isReceive'] = $isreceived;
					$data['taskList'][4]['isFinshed'] = $pannacotta >= $taskData[5]['taskPeople'] ? true : false;
					$data['taskList'][4]['beginDate'] = '2025-01-16';
					$data['taskList'][4]['endDate'] = '2099-01-16';
					
					// Task List 5 - taskID 6
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 6 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][5]['taskID'] = 6;
					$data['taskList'][5]['taskAmount'] = $taskData[6]['taskAmount'];
					$data['taskList'][5]['rechargeAmount'] = $taskData[6]['rechargeAmount'];
					$data['taskList'][5]['rechargeAmount_All'] = null;
					$data['taskList'][5]['taskPeople'] = $taskData[6]['taskPeople'];
					$data['taskList'][5]['rechargePeople'] = $pannacotta;
					$data['taskList'][5]['taskRechargePeople'] = $taskData[6]['taskPeople'];
					$data['taskList'][5]['efficientPeople'] = $effrw;
					$data['taskList'][5]['title'] = null;
					$data['taskList'][5]['title2'] = null;
					$data['taskList'][5]['isReceive'] = $isreceived;
					$data['taskList'][5]['isFinshed'] = $pannacotta >= $taskData[6]['taskPeople'] ? true : false;
					$data['taskList'][5]['beginDate'] = '2025-01-16';
					$data['taskList'][5]['endDate'] = '2099-01-16';
					
					// Task List 6 - taskID 7
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 7 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][6]['taskID'] = 7;
					$data['taskList'][6]['taskAmount'] = $taskData[7]['taskAmount'];
					$data['taskList'][6]['rechargeAmount'] = $taskData[7]['rechargeAmount'];
					$data['taskList'][6]['rechargeAmount_All'] = null;
					$data['taskList'][6]['taskPeople'] = $taskData[7]['taskPeople'];
					$data['taskList'][6]['rechargePeople'] = $pannacotta;
					$data['taskList'][6]['taskRechargePeople'] = $taskData[7]['taskPeople'];
					$data['taskList'][6]['efficientPeople'] = $effrw;
					$data['taskList'][6]['title'] = null;
					$data['taskList'][6]['title2'] = null;
					$data['taskList'][6]['isReceive'] = $isreceived;
					$data['taskList'][6]['isFinshed'] = $pannacotta >= $taskData[7]['taskPeople'] ? true : false;
					$data['taskList'][6]['beginDate'] = '2025-01-16';
					$data['taskList'][6]['endDate'] = '2099-01-16';
					
					// Task List 7 - taskID 8
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 8 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][7]['taskID'] = 8;
					$data['taskList'][7]['taskAmount'] = $taskData[8]['taskAmount'];
					$data['taskList'][7]['rechargeAmount'] = $taskData[8]['rechargeAmount'];
					$data['taskList'][7]['rechargeAmount_All'] = null;
					$data['taskList'][7]['taskPeople'] = $taskData[8]['taskPeople'];
					$data['taskList'][7]['rechargePeople'] = $pannacotta;
					$data['taskList'][7]['taskRechargePeople'] = $taskData[8]['taskPeople'];
					$data['taskList'][7]['efficientPeople'] = $effrw;
					$data['taskList'][7]['title'] = null;
					$data['taskList'][7]['title2'] = null;
					$data['taskList'][7]['isReceive'] = $isreceived;
					$data['taskList'][7]['isFinshed'] = $pannacotta >= $taskData[8]['taskPeople'] ? true : false;
					$data['taskList'][7]['beginDate'] = '2025-01-16';
					$data['taskList'][7]['endDate'] = '2099-01-26';
					
					// Task List 8 - taskID 9
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 9 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][8]['taskID'] = 9;
					$data['taskList'][8]['taskAmount'] = $taskData[9]['taskAmount'];
					$data['taskList'][8]['rechargeAmount'] = $taskData[9]['rechargeAmount'];
					$data['taskList'][8]['rechargeAmount_All'] = null;
					$data['taskList'][8]['taskPeople'] = $taskData[9]['taskPeople'];
					$data['taskList'][8]['rechargePeople'] = $pannacotta;
					$data['taskList'][8]['taskRechargePeople'] = $taskData[9]['taskPeople'];
					$data['taskList'][8]['efficientPeople'] = $effrw;
					$data['taskList'][8]['title'] = null;
					$data['taskList'][8]['title2'] = null;
					$data['taskList'][8]['isReceive'] = $isreceived;
					$data['taskList'][8]['isFinshed'] = $pannacotta >= $taskData[9]['taskPeople'] ? true : false;
					$data['taskList'][8]['beginDate'] = '2025-01-16';
					$data['taskList'][8]['endDate'] = '2099-01-16';
					
					// Task List 9 - taskID 10
					$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 10 AND status = 1";
					$cassata = $conn->query($cannoli);
					$risotto = mysqli_num_rows($cassata);
					
					
					$isreceived = 0;
					if($risotto>0){
					    $isreceived =1;
					}
					
					$data['taskList'][9]['taskID'] = 10;
					$data['taskList'][9]['taskAmount'] = $taskData[10]['taskAmount'];
					$data['taskList'][9]['rechargeAmount'] = $taskData[10]['rechargeAmount'];
					$data['taskList'][9]['rechargeAmount_All'] = null;
					$data['taskList'][9]['taskPeople'] = $taskData[10]['taskPeople'];
					$data['taskList'][9]['rechargePeople'] = $pannacotta;
					$data['taskList'][9]['taskRechargePeople'] = $taskData[10]['taskPeople'];
					$data['taskList'][9]['efficientPeople'] = $effrw;
					$data['taskList'][9]['title'] = null;
					$data['taskList'][9]['title2'] = null;
					$data['taskList'][9]['isReceive'] = $isreceived;
					$data['taskList'][9]['isFinshed'] = $pannacotta >= $taskData[10]['taskPeople'] ? true : false;
					$data['taskList'][9]['beginDate'] = '2025-01-16';
					$data['taskList'][9]['endDate'] = '2099-01-16';
					
				// 	// Task List 10 - taskID 11
				// 	$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 11 AND status = 1";
				// 	$cassata = $conn->query($cannoli);
				// 	$risotto = mysqli_num_rows($cassata);
					
				// 	$data['taskList'][10]['taskID'] = 11;
				// 	$data['taskList'][10]['taskAmount'] = $taskData[11]['taskAmount'];
				// 	$data['taskList'][10]['rechargeAmount'] = $taskData[11]['rechargeAmount'];
				// 	$data['taskList'][10]['rechargeAmount_All'] = null;
				// 	$data['taskList'][10]['taskPeople'] = $taskData[11]['taskPeople'];
				// 	$data['taskList'][10]['rechargePeople'] = $pannacotta;
				// 	$data['taskList'][10]['taskRechargePeople'] = $taskData[11]['taskPeople'];
				// 	$data['taskList'][10]['efficientPeople'] = $effrw;
				// 	$data['taskList'][10]['title'] = null;
				// 	$data['taskList'][10]['title2'] = null;
				// 	$data['taskList'][10]['isReceive'] = $risotto >= 1 ? 1 : 0;
				// 	$data['taskList'][10]['isFinshed'] = $pannacotta >= $taskData[11]['taskPeople'] ? true : false;
				// 	$data['taskList'][10]['beginDate'] = '2025-01-16';
				// 	$data['taskList'][10]['endDate'] = '2099-01-16';
					
				// 	// Task List 11 - taskID 12
				// 	$cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 12 AND status = 1";
				// 	$cassata = $conn->query($cannoli);
				// 	$risotto = mysqli_num_rows($cassata);
					
				// 	$data['taskList'][11]['taskID'] = 12;
				// 	$data['taskList'][11]['taskAmount'] = $taskData[12]['taskAmount'];
				// 	$data['taskList'][11]['rechargeAmount'] = $taskData[12]['rechargeAmount'];
				// 	$data['taskList'][11]['rechargeAmount_All'] = null;
				// 	$data['taskList'][11]['taskPeople'] = $taskData[12]['taskPeople'];
				// 	$data['taskList'][11]['rechargePeople'] = $pannacotta;
				// 	$data['taskList'][11]['taskRechargePeople'] = $taskData[12]['taskPeople'];
				// 	$data['taskList'][11]['efficientPeople'] = $effrw;
				// 	$data['taskList'][11]['title'] = null;
				// 	$data['taskList'][11]['title2'] = null;
				// 	$data['taskList'][11]['isReceive'] = $risotto >= 1 ? 1 : 0;
				// 	$data['taskList'][11]['isFinshed'] = $pannacotta >= $taskData[12]['taskPeople'] ? true : false;
				// 	$data['taskList'][11]['beginDate'] = '2025-01-16';
				// 	$data['taskList'][11]['endDate'] = '2099-01-16';
					
					$data['chirldrenListDataList'] = null;
					
					$res['data'] = $data;
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