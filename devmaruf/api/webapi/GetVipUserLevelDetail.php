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

date_default_timezone_set("Asia/Kolkata");
$current_time = date("Y-m-d H:i:s");

$response = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $current_time,
];

$request_body = file_get_contents("php://input");
$post_data = json_decode($request_body, true);

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    if (isset($post_data['language']) && isset($post_data['random']) && isset($post_data['signature']) && isset($post_data['timestamp'])) {
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $post_data['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $post_data['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $post_data['signature']));
        $sign_string = '{"language":'.$language.',"random":"'.$random.'"}';
        $calculated_sign = strtoupper(md5($sign_string));
        
        if($calculated_sign == $signature){
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $token = $bearer[1];                
            $jwt_validation = is_jwt_valid($token);
            $auth_data = json_decode($jwt_validation, true);
            
            if($auth_data['status'] === 'Success') {
                $user_query = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$token'";
                $user_result = $conn->query($user_query);
                $user_count = mysqli_num_rows($user_result);
                
                if($user_count == 1){
                    $user_id = $auth_data['payload']['id'];
                    
                    $vip_query = "SELECT expe, lvl FROM vip WHERE userid = $user_id";
                    $vip_result = $conn->query($vip_query);
                    $vip_data = mysqli_fetch_array($vip_result);
                    
                    $current_exp = isset($vip_data['expe']) ? (int)$vip_data['expe'] : 0;
                    $current_level = isset($vip_data['lvl']) ? (int)$vip_data['lvl'] : 0;
                    
                    $vip_levels_query = "SELECT * FROM vip_money_management ORDER BY vip_level";
                    $vip_levels_result = $conn->query($vip_levels_query);
                    
                    $vip_levels = [];
                    if ($vip_levels_result) {
                        while ($row = mysqli_fetch_assoc($vip_levels_result)) {
                            $vip_level = (int)$row['vip_level'];
                            $vip_levels[] = [
                                "id" => $vip_level,
                                "vipName" => $row['vip_name'],
                                "status" => (int)$row['status'],
                                "currentExp" => $current_exp,
                                "upgrade" => (int)$row['upgrade_exp'],
                                "relegationExp" => (int)$row['relegation_exp'],
                                "relegation" => (int)$row['relegation'],
                                "deductExp" => (int)$row['deduct_exp'],
                                "amount" => (int)$row['amount'],
                                "upgradeStatus" => $current_level >= $vip_level ? 1 : 0
                            ];
                        }
                    }
                    
                    $response['data'] = $vip_levels;
                    $response['code'] = 0;
                    $response['msg'] = 'Succeed';
                    $response['msgCode'] = 0;
                    $response['serviceNowTime'] = $current_time;
                    http_response_code(200);
                    echo json_encode($response);    
                }
                else{
                    $response = [
                        'code' => 4,
                        'msg' => 'No operation permission',
                        'msgCode' => 2,
                        'serviceNowTime' => $current_time
                    ];
                    http_response_code(401);
                    echo json_encode($response);
                }                    
            }
            else{                    
                $response = [
                    'code' => 4,
                    'msg' => 'No operation permission',
                    'msgCode' => 2,
                    'serviceNowTime' => $current_time
                ];
                http_response_code(401);
                echo json_encode($response);                    
            }
        }
        else{
            $response = [
                'code' => 5,
                'msg' => 'Wrong signature',
                'msgCode' => 3,
                'serviceNowTime' => $current_time
            ];
            http_response_code(200);
            echo json_encode($response);                
        }
    }
    else{
        $response = [
            'code' => 7,
            'msg' => 'Param is Invalid',
            'msgCode' => 6,
            'serviceNowTime' => $current_time
        ];
        http_response_code(200);
        echo json_encode($response);            
    }        
} else {        
    http_response_code(405);
    echo json_encode($response);
}
?>