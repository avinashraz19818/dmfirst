<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
    include "../../conn.php";
    
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
                $data = [
    "rewardList" => [],
    "taskList" => [],
    "vipRating" => "0,1,2,3,4,5,6,7,8,9,10",
    "memberGroup" => "0,1",
    "bindingType" => 0
];

// 1️⃣ rewardList: get all rows from table
$rewardQuery = "SELECT reward_type, reward_setting, prize_picture_url FROM recharge_spin_rewards ORDER BY id ASC";
$rewardResult = $conn->query($rewardQuery);
if ($rewardResult && $rewardResult->num_rows > 0) {
    while ($row = $rewardResult->fetch_assoc()) {
        $data['rewardList'][] = [
            "rewardType" => (int)$row['reward_type'],
            "rewardSetting" => $row['reward_setting'],
            "prizePicturesUrl" => $row['prize_picture_url']
        ];
    }
}

// 2️⃣ taskList: get unique spin mapping
$taskQuery = "SELECT DISTINCT target_amount, rotate_num FROM recharge_spin_rewards ORDER BY target_amount ASC";
$taskResult = $conn->query($taskQuery);
if ($taskResult && $taskResult->num_rows > 0) {
    while ($row = $taskResult->fetch_assoc()) {
        $data['taskList'][] = [
            "taskType" => 1,
            "targetAmount" => (float)$row['target_amount'],
            "rotateNum" => (int)$row['rotate_num']
        ];
    }
}
                
                $res['data'] = $data;
                $res['code'] = 0;
                $res['msg'] = 'Succeed';
                $res['msgCode'] = 0;
                http_response_code(200);
                echo json_encode($res);
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