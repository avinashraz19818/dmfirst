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
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        
        $shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
        $shonusign = strtoupper(md5($shonustr));
        
        if ($shonusign == $signature) {
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1];                
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, true);
            
            if ($data_auth['status'] === 'Success') {
                $sesquery = "SELECT id, akshinak FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);
                $sesnum = mysqli_num_rows($sesresult);
                
                if ($sesnum == 1) {
                    $sesresult = $sesresult->fetch_assoc();
                    $userId = $sesresult['id'];

                    // Check if bank account is linked
                    $bankLinkQuery = "SELECT * FROM bankcard WHERE userid = '$userId'";
                    $bankLinkResult = $conn->query($bankLinkQuery);

                    if ($bankLinkResult && mysqli_num_rows($bankLinkResult) > 0) {
                        // Bank account is linked, proceed with sumRotateNum calculation
                        $totalMottaQuery = "SELECT SUM(motta) as totalMotta FROM thevani WHERE balakedara = '$userId' AND sthiti = 1 AND dinankavannuracisi >= CURDATE()";
                        $totalMottaResult = $conn->query($totalMottaQuery);

                        if ($totalMottaResult) {
                            $totalMottaRow = $totalMottaResult->fetch_assoc();
                            $totalMotta = $totalMottaRow['totalMotta'] ?? 0.0;
                            
                            $sumRotateNum = 0; // Default spin
                            $totalMotta = floatval($totalMotta); // Ensure number format
                            
                            $query = "
                                SELECT rotate_num 
                                FROM recharge_spin_rewards 
                                WHERE target_amount <= $totalMotta 
                                ORDER BY target_amount DESC 
                                LIMIT 1
                            ";
                            
                            $result = $conn->query($query);
                            
                            if ($result && $row = $result->fetch_assoc()) {
                                $sumRotateNum = (int)$row['rotate_num'];
                            }
                            
                            
                            $query = mysqli_query($conn, "SELECT spin_prizevalue FROM web_setting LIMIT 1");
                            $row = mysqli_fetch_assoc($query);
                            $spinPrize = $row['spin_prizevalue'];


                            // Fetch surplusRotateNum before any database updates
                            $surplusRotateQuery = "SELECT spin FROM shonu_kaichila WHERE balakedara = '$userId'";
                            $surplusRotateResult = $conn->query($surplusRotateQuery);

                            $surplusRotateNum = 0;
                            if ($surplusRotateResult && $surplusRotateResult->num_rows > 0) {
                                $surplusRotateRow = $surplusRotateResult->fetch_assoc();
                                $surplusRotateNum = (int)$surplusRotateRow['spin'];
                            }

                            // Check if surplusRotateNum >= sumRotateNum (user exhausted all draws)
                            if ($surplusRotateNum >= $sumRotateNum) {
                                // Custom response for exhausted draws
                                $res['data'] = [
                                    'msgCode' => 905,
                                    'bindingType' => null,
                                    'rewardType' => null,
                                    'rewardSetting' => "",
                                ];
                                $res['code'] = 1;
                                $res['msg'] = 'Your draws have been exhausted';
                                $res['msgCode'] = 905;
                                $res['serviceNowTime'] = $shnunc;

                                http_response_code(200);
                                echo json_encode($res);
                                exit; // Ensure the script stops here
                            }

                           
                            $insertQuery = "INSERT INTO spinrec (user_id, type, prize, time) VALUES ('$userId', '1', '$spinPrize', '$shnunc')";
                            $conn->query($insertQuery);
                            $spinup = "UPDATE shonu_kaichila SET spin = spin + 1, motta = motta + $spinPrize WHERE balakedara = '$userId'";
                            $conn->query($spinup);
                            $exp = "UPDATE vip SET expe = expe + $spinPrize WHERE userid='$userId'";
                            $conn->query($exp); 

                            // Fetch updated surplusRotateNum after updates
                            $updatedSurplusRotateResult = $conn->query($surplusRotateQuery);
                            $updatedSurplusRotateNum = 0;
                            if ($updatedSurplusRotateResult && $updatedSurplusRotateResult->num_rows > 0) {
                                $updatedSurplusRotateRow = $updatedSurplusRotateResult->fetch_assoc();
                                $updatedSurplusRotateNum = (int)$updatedSurplusRotateRow['spin'];
                            }

                            // Successful response when bank account is linked and updates were made
                            $res['data'] = [
                                'msgCode' => 200,
                                'bindingType' => 1,
                                'rewardType' => 1,
                                'rewardSetting' => $spinPrize,
                                'sumRotateNum' => $sumRotateNum,
                                'surplusRotateNum' => $updatedSurplusRotateNum,
                            ];
                            $res['code'] = 0;
                            $res['msg'] = 'Succeed';
                            $res['msgCode'] = 200;
                            $res['serviceNowTime'] = $shnunc;

                            http_response_code(200);
                            echo json_encode($res);
                        } else {
                            $res['code'] = 5;
                            $res['msg'] = 'Error fetching data from the database';
                            $res['msgCode'] = 3;
                            http_response_code(500);
                            echo json_encode($res);
                        }
                    } else {
                        // Bank account is not linked, send alternative response
                        $res['data'] = [
                            'msgCode' => 904,
                            'bindingType' => 1,
                            'rewardType' => null,
                            'rewardSetting' => "",
                            'sumRotateNum' => 0,
                            'surplusRotateNum' => 0,
                        ];
                        $res['code'] = 1;
                        $res['msg'] = 'You can draw prizes after binding your bank card';
                        $res['msgCode'] = 904;
                        $res['serviceNowTime'] = $shnunc;

                        http_response_code(200);
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