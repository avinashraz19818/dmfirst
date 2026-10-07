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
    'traceId' => '',
    'serviceNowTime' => $shnunc,
];

$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
        
        $language = trim($shonupost['language']);
        $random = trim($shonupost['random']);
        $clientSignature = strtoupper(trim($shonupost['signature']));
        
        // ✅ JSON Encode ব্যবহার করে Canonical string তৈরি (যা কোটেশন ও ব্যাকস্ল্যাশ হ্যান্ডেল করে)
        $shonustr = json_encode([
            'language' => $language,
            'random'   => $random
        ], JSON_UNESCAPED_SLASHES);

        $serverSignature = strtoupper(md5($shonustr));
        
        if ($serverSignature === $clientSignature) {
            $bearer = isset($_SERVER['HTTP_AUTHORIZATION']) ? explode(" ", $_SERVER['HTTP_AUTHORIZATION']) : [];
            $author = isset($bearer[1]) ? $bearer[1] : '';
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, true);
            
            if (!empty($data_auth['status']) && $data_auth['status'] === 'Success') {
                $shonuid = (int)($data_auth['payload']['id']);
                
                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author' LIMIT 1";
                $sesresult = $conn->query($sesquery);
                
                if ($sesresult && mysqli_num_rows($sesresult) == 1) {
                    
                    // User Table থেকে মোবাইল নম্বর তোলা
                    $userQuery = "SELECT duravani FROM shonu_subjects WHERE id = '$shonuid' LIMIT 1";
                    $userResult = $conn->query($userQuery);
                    
                    $mobileNo = "";
                    if ($userResult && mysqli_num_rows($userResult) > 0) {
                        $userRow = mysqli_fetch_assoc($userResult);
                        $mobileNo = (string)$userRow['duravani'];
                    }
                    
                    $res['data'] = $mobileNo;
                    $res['code'] = 0;
                    $res['msg'] = 'Succeed';
                    $res['msgCode'] = 0;
                    $res['traceId'] = '';
                    $res['serviceNowTime'] = $shnunc;
                    
                    http_response_code(200);
                    echo json_encode($res);
                    exit;
                    
                } else {
                    $res['code'] = 4;
                    $res['msg'] = 'No operation permission';
                    $res['msgCode'] = 2;
                    http_response_code(401);
                    echo json_encode($res);
                    exit;
                }
            } else {
                $res['code'] = 4;
                $res['msg'] = 'Invalid Token';
                $res['msgCode'] = 2;
                http_response_code(401);
                echo json_encode($res);
                exit;
            }
        } else {
            $res['code'] = 5;
            $res['msg'] = 'Wrong signature';
            $res['msgCode'] = 3;
            $res['traceId'] = '';
            http_response_code(200);
            echo json_encode($res);
            exit;
        }
    } else {
        $res['code'] = 7;
        $res['msg'] = 'Param is Invalid';
        $res['msgCode'] = 6;
        http_response_code(200);
        echo json_encode($res);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode($res);
    exit;
}
?>