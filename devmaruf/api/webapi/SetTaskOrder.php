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
		if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['taskId']) && isset($shonupost['timestamp'])) {
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
						
                    $sql = "SELECT taskID, taskAmount, rechargeAmount, taskPeople 
                            FROM tbl_invitebonus 
                            WHERE taskID BETWEEN 0 AND 13";
                    
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        
                        while ($row = $result->fetch_assoc()) {
                            $taskID = $row['taskID'];
                            $taskAmount = $row['taskAmount'];
                            $rechargeAmount = $row['rechargeAmount'];
                            $taskPeople = $row['taskPeople'];
                    
                            ${'taskAmount' . $taskID} = $taskAmount;
                            ${'rechargeAmount' . $taskID} = $rechargeAmount;
                            ${'taskPeople' . $taskID} = $taskPeople;
                    
                            
                        }
                    } else {
                        echo "No data found";
                        die();
                    }
                    						
        				if($taskId == 0){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 1 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '1', '".$taskAmount0."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount0 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 1, no insertion done.";
                            }
                        }
                        else if($taskId == 2){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 2 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '2', '".$taskAmount2."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount2 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 2, no insertion done.";
                            }
                        }
                        else if($taskId == 3){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 3 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '3', '".$taskAmount3."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount3 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 3, no insertion done.";
                            }
                        }
                        else if($taskId == 4){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 4 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '4', '".$taskAmount4."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount4 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 4, no insertion done.";
                            }
                        }
                        else if($taskId == 5){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 5 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '5', '".$taskAmount5."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount5 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 5, no insertion done.";
                            }
                        }
                        else if($taskId == 6){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 6 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '6', '".$taskAmount6."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount6 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 6, no insertion done.";
                            }
                        }
                        else if($taskId == 7){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 7 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '7', '".$taskAmount7."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount7 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 7, no insertion done.";
                            }
                        }
                        else if($taskId == 8){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 8 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '8', '".$taskAmount8."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount8 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 8, no insertion done.";
                            }
                        }
                        else if($taskId == 9){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 9 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '9', '".$taskAmount9."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount9 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 9, no insertion done.";
                            }
                        }
                        else if($taskId == 10){
                            $cannoli = "SELECT id FROM noitativni_sonub WHERE arthur = '".$shonuid."' AND fleck = 10 AND status = 1";
                            $cassata = $conn->query($cannoli);
                            $risotto = mysqli_num_rows($cassata);
                            
                            if($risotto == 0){
                                $tathya = mysqli_query($conn, "INSERT INTO `noitativni_sonub` (`arthur`, `fleck`, `motta`, `status`, `time`) VALUES ('".$shonuid."', '9', '".$taskAmount10."', '1', '".$shnunc."')");
                                $nabikarana = "UPDATE shonu_kaichila SET motta = motta + $taskAmount10 WHERE balakedara='$shonuid'";
                                $conn->query($nabikarana);
                            } else {
                                // echo "Entry already exists for task 9, no insertion done.";
                            }
                        }
                        
						else if($taskId == 10){
							$tathya = mysqli_query($conn,"INSERT INTO `noitativni_sonub` (`arthur`,`fleck`,`motta`,`status`,`time`) VALUES ('".$shonuid."','10','".$taskAmount10."','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $taskAmount10 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 11){
							$tathya = mysqli_query($conn,"INSERT INTO `noitativni_sonub` (`arthur`,`fleck`,`motta`,`status`,`time`) VALUES ('".$shonuid."','11','".$taskAmount11."','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $taskAmount11 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 12){
							$tathya = mysqli_query($conn,"INSERT INTO `noitativni_sonub` (`arthur`,`fleck`,`motta`,`status`,`time`) VALUES ('".$shonuid."','12','".$taskAmount12."','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $taskAmount12 where balakedara='$shonuid'";
							$conn->query($nabikarana);
						}
						else if($taskId == 13){
							$tathya = mysqli_query($conn,"INSERT INTO `noitativni_sonub` (`arthur`,`fleck`,`motta`,`status`,`time`) VALUES ('".$shonuid."','13','".$taskAmount13."','1','".$shnunc."')");
							
							$nabikarana = "UPDATE shonu_kaichila set motta = motta + $taskAmount13 where balakedara='$shonuid'";
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