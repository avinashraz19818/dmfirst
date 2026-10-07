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
    'data' => []
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
                $sesquery = "SELECT id FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = mysqli_query($conn, $sesquery);
                
                if(mysqli_num_rows($sesresult) > 0) {
                    $user_data = mysqli_fetch_assoc($sesresult);
                    $user_id = $user_data['id'];
                    
                    // Get today's total bet amount for this user using optimized query
                    $curdate = date('Y-m-d');
                    
                    // Single optimized query with LEFT JOIN
                    $bet_query = "SELECT SUM(b.ketebida) as total 
                                  FROM (
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_drei
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_funf
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_zehn
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_kemuru
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_kemuru_drei
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_kemuru_funf
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_kemuru_zehn
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_aidudi
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_aidudi_drei
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_aidudi_funf
                                      UNION ALL
                                      SELECT ketebida, byabaharkarta, tiarikala FROM bajikattuttate_aidudi_zehn
                                  ) b
                                  LEFT JOIN demo d ON b.byabaharkarta = d.balakedara AND d.sthiti = '1'
                                  WHERE b.byabaharkarta = '$user_id' 
                                  AND DATE(b.tiarikala) = DATE('$curdate')
                                  AND d.balakedara IS NULL";
                    
                    $bet_result = mysqli_query($conn, $bet_query);
                    $bet_data = mysqli_fetch_assoc($bet_result);
                    $total_bet = $bet_data['total'] ?: 0;
                    
                    // Get daily award list
                    $award_query = "SELECT * FROM daily_award_list ORDER BY taskTarget ASC";
                    $award_result = mysqli_query($conn, $award_query);
                    
                    $award_data = [];
                    while($row = mysqli_fetch_assoc($award_result)) {
                        // Check if user has already received this award using lId
                        $check_received = "SELECT id FROM noitativni_sonub WHERE arthur = '$user_id' AND fleck = '{$row['lId']}' AND DATE(time) = DATE('$curdate') AND status = 1";
                        $check_result = mysqli_query($conn, $check_received);
                        
                        
                        $status = 1;
                        if(mysqli_num_rows($check_result) > 0) {
                            // Award already received - status should be 0
                            $status = 0;
                            $schedule = $row['taskTarget']; // Set schedule to taskTarget when received
                        } else {
                            // Award not received yet
                            $status_task = $row['status'];
                            if($total_bet >= $row['taskTarget'] && $status_task == 1) {
                                $status = 2; // Update status to 2 if target achieved but not received
                            }
                            // Set schedule to user's total bet amount for ALL awards
                            $schedule = $total_bet;
                        }
                        
                        $award_data[] = [
                            'userId' => (int)$user_id,
                            'mainConfigId' => (int)$row['mainConfigId'],
                            'configId' => (int)$row['configId'],
                            'schedule' => (float)$schedule,
                            'status' => (int)$status,
                            'taskTitle' => $row['taskTitle'],
                            'taskDescribe' => $row['taskDescribe'],
                            'taskId' => $row['taskId'],
                            'taskTarget' => (float)$row['taskTarget'],
                            'taskAwardAmount' => (float)$row['taskAwardAmount'],
                            'createDate' => $row['createDate'],
                            'targetTwo' => (float)$row['targetTwo'],
                            'targetItem' => (int)$row['targetItem'],
                            'targetSubItem' => $row['targetSubItem'],
                            'rechargeCategories' => $row['rechargeCategories'],
                            'scheduleTwo' => (float)$row['scheduleTwo'],
                            'lId' => (int)$row['lId']
                        ];
                    }
                    
                    // Exact response structure
                    $res = [
                        'data' => $award_data,
                        'code' => 0,
                        'msg' => 'Succeed',
                        'msgCode' => 0,
                        'serviceNowTime' => $shnunc
                    ];
                    
                } else {
                    $res = [
                        'data' => [],
                        'code' => 401,
                        'msg' => 'Unauthorized access',
                        'msgCode' => 401,
                        'serviceNowTime' => $shnunc
                    ];
                }
            } else {
                $res = [
                    'data' => [],
                    'code' => 401,
                    'msg' => 'Invalid token',
                    'msgCode' => 401,
                    'serviceNowTime' => $shnunc
                ];
            }
        } else {
            $res = [
                'data' => [],
                'code' => 403,
                'msg' => 'Invalid signature',
                'msgCode' => 403,
                'serviceNowTime' => $shnunc
            ];
        }
    } else {
        $res = [
            'data' => [],
            'code' => 400,
            'msg' => 'Missing required parameters',
            'msgCode' => 400,
            'serviceNowTime' => $shnunc
        ];
    }
} else {
    $res = [
        'data' => [],
        'code' => 405,
        'msg' => 'Method not allowed',
        'msgCode' => 405,
        'serviceNowTime' => $shnunc
    ];
}

echo json_encode($res);
?>