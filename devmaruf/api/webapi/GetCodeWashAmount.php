<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php 
include "../../functions2.php";
include "../../conn.php";

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

try {
    $shonubody = file_get_contents("php://input");
    $shonupost = json_decode($shonubody, true);

    if ($_SERVER['REQUEST_METHOD'] != 'GET') {
        if (isset($shonupost['language'], $shonupost['random'], $shonupost['signature'], $shonupost['timestamp'])) {
            $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
            $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
            $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));

            $shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
            $shonusign = strtoupper(md5($shonustr));

            if ($shonusign) {
                $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
                $author = $bearer[1];                
                $is_jwt_valid = is_jwt_valid($author);
                $data_auth = json_decode($is_jwt_valid, true);

                if ($data_auth['status']) {
                    $userId = $data_auth['payload']['id'];

                    $lvlquery = "SELECT lvl FROM vip WHERE userid = '$userId'";
                    $lvlresult = $conn->query($lvlquery);
                    $lvlData = $lvlresult->fetch_assoc();
                    $lvl = $lvlData ? $lvlData['lvl'] : 0;

                    $reamountq = "SELECT rebet FROM shonu_kaichila WHERE balakedara = '$userId'";
                    $reamount = $conn->query($reamountq);
                    $rc = $reamount->fetch_assoc();
                    $u = $rc['rebet'];

                    $pageNo = isset($shonupost['pageNo']) ? (int)$shonupost['pageNo'] : 1;
                    $pageSize = 10;

                    $washRate = null;
                    if (in_array($lvl, [0, 1, 2])) {
                        $washRate = 0.05;
                    } elseif (in_array($lvl, [3, 4, 5])) {
                        $washRate = 0.1;
                    } elseif (in_array($lvl, [6, 7, 8])) {
                        $washRate = 0.15;
                    } elseif ($lvl == 9) {
                        $washRate = 0.2;
                    } elseif ($lvl == 10) {
                        $washRate = 0.3;
                    }

                    $state = isset($shonupost['state']) ? (int)$shonupost['state'] : -1;
                    $codeType = isset($shonupost['codeType']) ? (int)$shonupost['codeType'] : -1;

                    if ($state == -1) {
                        if ($codeType == -1 || $codeType == 3) {
                            $samatolana = ($pageNo - 1) * $pageSize;

                            $todayMidnight = date("Y-m-d") . " 00:00:00"; // Start of the current day at midnight
                            $samasye = "SELECT rebet, rate, motta 
                                        FROM rebetrec 
                                        WHERE user_id = $userId 
                                        AND created_at >= '$todayMidnight'
                                        ORDER BY id DESC 
                                        LIMIT $pageSize OFFSET $samatolana";
                            $samasyephalitansa = $conn->query($samasye);
                            $samasyephalitansa_sankhye = mysqli_num_rows($samasyephalitansa);

                            $washList = [];
                            $dayRebate = 0.0;

                            if ($samasyephalitansa_sankhye > 0) {
                                while ($row = mysqli_fetch_assoc($samasyephalitansa)) {
                                    $washVolume = (int)$row['rebet'];
                                    $rebateAmount = (float)$row['motta'];
                                    $washList[] = [
                                        'washVolume' => $washVolume,
                                        'washRate' => $washRate,
                                        'rebateAmount' => $rebateAmount
                                    ];
                                    $dayRebate += $rebateAmount;
                                }

                                $samasye_ondu = "SELECT rate, rebet, motta
                                                 FROM rebetrec 
                                                 WHERE user_id = $userId 
                                                 AND created_at >= '$todayMidnight'";
                                $samasyephalitansa_ondu = $conn->query($samasye_ondu);
                                $samasyephalitansa_sankhye_m = mysqli_num_rows($samasyephalitansa_ondu);
                                $totalPage = ceil($samasyephalitansa_sankhye_m / $pageSize);
                            } else {
                                $washList = [];
                                $totalPage = 0;
                            }

                            $totalRebateQuery = "SELECT SUM(motta) as totalRebate FROM rebetrec WHERE user_id = $userId";
                            $totalRebateResult = $conn->query($totalRebateQuery);
                            $totalRebateRow = $totalRebateResult->fetch_assoc();
                            $totalRebate = (float)$totalRebateRow['totalRebate'] ?? 0.0;

                            $res['data'] = [
                                'codeWashAmount' => (int)$u,
                                'dayRebate' => (float)number_format($dayRebate, 3, '.', ''),
                                'totalRebate' => (float)number_format($totalRebate, 3, '.', ''),
                                'washRate' => $washRate,
                                'washList' => $washList,
                                'pageNo' => (int)$pageNo,
                                'totalPage' => $totalPage,
                                'totalCount' => $samasyephalitansa_sankhye_m ?? 0
                            ];
                            $res['code'] = 0;
                            $res['msg'] = 'Succeed';
                            $res['msgCode'] = 0;
                            $res['serviceNowTime'] = $shnunc;

                            http_response_code(200);
                            echo json_encode($res);
                        }
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
} catch (Exception $e) {
    $res['code'] = 10;
    $res['msg'] = 'Internal server error';
    $res['msgCode'] = 5;
    http_response_code(500);
    echo json_encode($res);
} 
?>