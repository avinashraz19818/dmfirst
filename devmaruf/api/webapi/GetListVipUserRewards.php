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
    
    // ১. শুধুমাত্র মূল দরকারি প্যারামিটারগুলো চেক করা হচ্ছে
    if (
        isset($shonupost['language']) && 
        isset($shonupost['random']) && 
        isset($shonupost['signature']) && 
        isset($shonupost['timestamp'])
    ) {
        
        // rewardType এবং vipLevel যদি না পাঠানো হয়, তবে ডিফল্ট মান (যেমন: 0) ধরে নেওয়া হবে
        $rawRewardType = isset($shonupost['rewardType']) ? $shonupost['rewardType'] : 0;
        $rawVipLevel   = isset($shonupost['vipLevel'])   ? $shonupost['vipLevel'] : 0;

        $language  = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $taskId    = htmlspecialchars(mysqli_real_escape_string($conn, $rawRewardType));
        $lvl       = htmlspecialchars(mysqli_real_escape_string($conn, $rawVipLevel));
        $random    = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        
        $shonustr = '{"language":'.$language.',"random":"'.$random.'","taskId":'.$taskId.'}';
        $shonusign = strtoupper(md5($shonustr));

        $is_authorized = false;
        $shonuid = null;

        // ২. সিগনেচার ভ্যালিডেশন
        if ($shonusign == $signature) {
            $is_authorized = true; 
        } 
        
        // সিগনেচার না মিললে JWT চেক করা হবে (আপনার আগের কোডের লজিক ঠিক রেখে)
        if (!$is_authorized) {
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $res['code'] = 4;
                $res['msg'] = 'Authorization header missing';
                $res['msgCode'] = 4;
                http_response_code(401);
                echo json_encode($res);
                exit;
            }

            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            if (count($bearer) < 2) {
                $res['code'] = 4;
                $res['msg'] = 'Authorization header invalid format';
                $res['msgCode'] = 4;
                http_response_code(401);
                echo json_encode($res);
                exit;
            }

            $author = $bearer[1];                
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, true);

            if (isset($data_auth['status']) && $data_auth['status'] === 'Success') {
                $shonuid = $data_auth['payload']['id'];
                $is_authorized = true;
            } else {
                $res['code'] = 5;
                $res['msg'] = 'Invalid Token or Wrong signature';
                $res['msgCode'] = 3;
                http_response_code(401);
                echo json_encode($res);
                exit;
            }
        }

        // ৩. অথরাইজড হলে ডাটাবেজ থেকে ডাটা নিয়ে আসা
        if ($is_authorized) {
            
            if (empty($shonuid) && isset($shonupost['userId'])) {
                $shonuid = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['userId']));
            }

            $vipCheckQuery = "SELECT lvl FROM vip WHERE userid = '$shonuid'";
            $vipCheckResult = $conn->query($vipCheckQuery);

            if ($vipCheckResult && $vipCheckResult->num_rows > 0) {
                $vipRow = $vipCheckResult->fetch_assoc();
                $userLvl = (int)$vipRow['lvl'];

                $allowedTypes = [];
                for ($i = 1; $i <= $userLvl; $i++) {
                    $allowedTypes[] = $i;
                }

                // ডিফল্ট ডাটা স্ট্রাকচার
                $data = [
                    [
                        'id' => 0,
                        'rewardType' => 1,
                        'integral' => 0,
                        'balance' => 0,
                        'status' => 1,  
                        'rate' => 0.0
                    ],
                    [
                        'id' => 0,
                        'rewardType' => 2,
                        'integral' => 0,
                        'balance' => 0,
                        'status' => 1,  
                        'rate' => 0.0
                    ]
                ];

                $currentMonthStart = date('Y-m-01 00:00:00');
                $currentMonthEnd = date('Y-m-t 23:59:59');

                $statusQuery = "SELECT type AS rewardType, status, motta, created_at  
                                FROM viprec 
                                WHERE user_id = '$shonuid'  
                                  AND lvl = $lvl  
                                  AND (
                                      type != 2  
                                      OR (type = 2 AND created_at BETWEEN '$currentMonthStart' AND '$currentMonthEnd')
                                  )";
                $statusResult = $conn->query($statusQuery);

                while ($row = $statusResult->fetch_assoc()) {
                    foreach ($data as &$rewardData) {
                        if ($rewardData['rewardType'] == $row['rewardType']) {
                            $rewardData['balance'] = $row['motta'];
                            $rewardData['status'] = $row['status'];
                            break;
                        }
                    }
                }

                $res = [
                    'data' => $data,
                    'code' => 0,
                    'msg' => 'Succeed',
                    'msgCode' => 0,
                    'serviceNowTime' => $shnunc,
                ];
            } else {
                $res = [
                    'data' => [],
                    'code' => 0,
                    'msg' => 'VIP not open: No level found for user',
                    'msgCode' => 0,
                    'serviceNowTime' => $shnunc,
                ];
            }
            http_response_code(200);
            echo json_encode($res);
        }
    } else {
        $res['code'] = 7;
        $res['msg'] = 'Param is Invalid';
        $res['msgCode'] = 6;
        http_response_code(200); // ক্লায়েন্ট এরর এড়াতে ২০০ রেসপন্স কোড রাখা হয়েছে
        echo json_encode($res);
    }       
} else {        
    http_response_code(405);
    echo json_encode($res);
}   
?>