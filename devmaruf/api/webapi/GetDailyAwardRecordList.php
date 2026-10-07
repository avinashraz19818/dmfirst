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
        $language   = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $pageNo     = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['pageNo']));
        if($pageNo > 1){
            echo '{
                "data": {
                    "list": [],
                    "pageNo": 1,
                    "totalPage": 0,
                    "totalCount": 0
                },
                "code": 0,
                "msg": "Succeed",
                "msgCode": 0,
                "serviceNowTime": "2025-11-30 22:39:37"
            }';
            
            die();
        }

        if(true){
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
                    
                   
                    $award_query = "SELECT * FROM noitativni_sonub WHERE arthur = '$user_id'  ORDER BY id ASC";
                    $award_result = mysqli_query($conn, $award_query);
                    
                   
                    while($row = mysqli_fetch_assoc($award_result)) {
                        
                        $award_data[] = [
                            'userId' => (int)$user_id,
                            'configId' => (int)$row['configId'],
                            'awardAmount' => (float)$row['taskAwardAmount'],
                            'configDetailId' => (int)$row['fleck'],
                            'taskTitle' => $row['taskTitle'],
                            'taskDescribe' => $row['taskDescribe'],
                            'taskId' => $row['taskId'],
                            'taskTarget' => (float)$row['taskTarget'],
                            'taskAwardAmount' => (float)$row['taskAwardAmount'],
                            'createDate' => $row['time']
                          
                        ];
                    }
                    
                    $data_final['list'] = $award_data;
              
                    // Exact response structure
                    $res = [
                        'data' => $data_final,
                        'code' => 0,
                        'msg' => 'Succeed',
                        'msgCode' => 0,
                        'pageNo' => 1,
                        'totalPage' => 1,
                        'totalCount' => 2,
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