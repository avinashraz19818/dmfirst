<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php 
header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');
header('vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include "../../conn.php";
include "../../functions2.php";

date_default_timezone_set('Asia/Kolkata');
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
    $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
    $token = $bearer[1];                
    $jwt_validation = is_jwt_valid($token);
    $auth_data = json_decode($jwt_validation, true);
    
    if($auth_data['status'] === 'Success') {
        $user_query = "SELECT akshinak, owncode FROM shonu_subjects WHERE akshinak = '$token'";
        $user_result = $conn->query($user_query);
        $user_count = mysqli_num_rows($user_result);
        
        if($user_count == 1) {
            $user_data = mysqli_fetch_array($user_result);
            $invitation_code = $user_data['owncode'];
            
            $page_no = isset($post_data['pageNo']) ? (int)$post_data['pageNo'] : 1;
            $page_size = isset($post_data['pageSize']) ? (int)$post_data['pageSize'] : 10;
            $offset = ($page_no - 1) * $page_size;

            $total_query = "SELECT COUNT(*) as total FROM shonu_subjects WHERE code = '$invitation_code'";
            $total_result = $conn->query($total_query);
            $total_count = mysqli_fetch_assoc($total_result)['total'];
            $total_pages = ceil($total_count / $page_size);

            $users_query = "SELECT id, codechorkamukala, createdate, status 
                            FROM shonu_subjects 
                            WHERE code = '$invitation_code' 
                            ORDER BY createdate DESC 
                            LIMIT $offset, $page_size";
            $users_result = $conn->query($users_query);

            $list = [];
            $seven_days_ago = date("Y-m-d H:i:s", strtotime("-7 days"));

            $reward_config = [
                1 => [],
                2 => [],
                3 => []
            ];

            $config_query = "SELECT * FROM partner_rewards_config ORDER BY type, min_recharge";
            $config_result = $conn->query($config_query);

            if ($config_result) {
                while ($row = $config_result->fetch_assoc()) {
                    $type = (int)$row['type'];
                    $reward_config[$type][] = [
                        'recharge' => (float)$row['min_recharge'],
                        'bet' => (float)$row['bet']
                    ];
                }
            }

            while ($user = mysqli_fetch_assoc($users_result)) {
                $user_id = $user['id'];
                
                $dep_query = "SELECT motta, dinankavannuracisi 
                            FROM thevani 
                            WHERE balakedara = '$user_id' 
                            AND sthiti = '1' 
                            AND dinankavannuracisi >= '$seven_days_ago'
                            ORDER BY dinankavannuracisi ASC";
                $dep_result = $conn->query($dep_query);
                
                $deposits = [0, 0, 0];
                $deposit_count = 0;
                while ($dep_row = mysqli_fetch_assoc($dep_result)) {
                    if ($deposit_count < 3) {
                        $deposits[$deposit_count] = (float)$dep_row['motta'];
                    }
                    $deposit_count++;
                }

                $bet_tables = [
                    'bajikattuttate', 'bajikattuttate_drei', 'bajikattuttate_funf', 'bajikattuttate_zehn',
                    'bajikattuttate_kemuru', 'bajikattuttate_kemuru_drei', 'bajikattuttate_kemuru_funf',
                    'bajikattuttate_kemuru_zehn', 'bajikattuttate_aidudi', 'bajikattuttate_aidudi_drei',
                    'bajikattuttate_aidudi_funf', 'bajikattuttate_aidudi_zehn'
                ];
                
                $turnover = 0;
                foreach ($bet_tables as $table) {
                    $bet_query = "SELECT SUM(ketebida) as total 
                                FROM `$table` 
                                WHERE byabaharkarta = '$user_id' 
                                AND tiarikala >= '$seven_days_ago'";
                    $bet_result = $conn->query($bet_query);
                    $turnover += (float)(mysqli_fetch_assoc($bet_result)['total'] ?? 0);
                }

                $status = 0; 
                $status_second = 0;
                $status_third = 0;

                if ($deposits[0] > 0) {
                    $status = 1;
                    foreach ($reward_config[1] as $level) {
                        if ($deposits[0] >= $level['recharge'] && $turnover >= $level['bet']) {
                            $status = 2;
                            break;
                        }
                    }
                }

                if ($deposits[1] > 0) {
                    $status_second = 1;
                    foreach ($reward_config[2] as $level) {
                        if ($deposits[1] >= $level['recharge'] && $turnover >= $level['bet']) {
                            $status_second = 2;
                            break;
                        }
                    }
                    if ($status_second == 1) $status_second = 3; 
                }

                if ($deposits[2] > 0) {
                    $status_third = 1;
                    foreach ($reward_config[3] as $level) {
                        if ($deposits[2] >= $level['recharge'] && $turnover >= $level['bet']) {
                            $status_third = 2;
                            break;
                        }
                    }
                    if ($status_third == 1) $status_third = 3; 
                }

                $list[] = [
                    'nickName' => $user['codechorkamukala'],
                    'userId' => (int)$user['id'],
                    'registerTime' => date("Y-m-d H:i:s", strtotime($user['createdate'])),
                    'status' => $status,
                    'firstAmount' => number_format($deposits[0], 4, '.', ''),
                    'secondAmount' => number_format($deposits[1], 4, '.', ''),
                    'thirdAmount' => number_format($deposits[2], 4, '.', ''),
                    'turnover' => number_format($turnover, 4, '.', ''),
                    'statusSecond' => $status_second,
                    'statusThird' => $status_third
                ];
            }

            $data = [
                'list' => $list,
                'pageNo' => $page_no,
                'totalPage' => $total_pages,
                'totalCount' => $total_count
            ];

            $response['data'] = $data;
            $response['code'] = 0;
            $response['msg'] = 'Succeed';
            $response['msgCode'] = 0;
            $response['serviceNowTime'] = $current_time;
            http_response_code(200);
            echo json_encode($response);
        } else {
            $response = [
                'code' => 4,
                'msg' => 'No operation permission',
                'msgCode' => 2,
                'serviceNowTime' => $current_time
            ];
            http_response_code(401);
            echo json_encode($response);
        }
    } else {
        $response = [
            'code' => 4,
            'msg' => 'No operation permission',
            'msgCode' => 2,
            'serviceNowTime' => $current_time
        ];
        http_response_code(401);
        echo json_encode($response);
    }
} else {
    http_response_code(405);
    echo json_encode($response);
}
?>