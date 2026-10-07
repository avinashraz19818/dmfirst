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
    if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
        
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        $shonustr = '{"language":"'.$language.'","random":"'.$random.'"}';
        $shonusign = strtoupper(md5($shonustr));
        $signature = $shonusign;
        
        if($shonusign == $signature){
            $bearer = isset($_SERVER['HTTP_AUTHORIZATION']) ? explode(" ", $_SERVER['HTTP_AUTHORIZATION']) : [];
            $author = isset($bearer[1]) ? $bearer[1] : '';
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);
            
            if($data_auth['status'] === 'Success') {
                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);
                
                if(mysqli_num_rows($sesresult) == 1){
                    
                    // ✅ ORDER BY id ASC ব্যবহার করা হয়েছে (কম ID আগে আসবে)
                    $withdrawQuery = "SELECT * FROM withdrawal_types WHERE status = 'Active' ORDER BY id ASC";
                    $withdrawResult = $conn->query($withdrawQuery);
                    
                    $withdrawlist = [];
                    $index = 0;
                    
                    if ($withdrawResult && $withdrawResult->num_rows > 0) {
                        while ($row = $withdrawResult->fetch_assoc()) {
                            $withdrawlist[$index]['withdrawID'] = (int)$row['withdrawID'];
                            $withdrawlist[$index]['name'] = $row['name'];
                            $withdrawlist[$index]['isAdd'] = (int)$row['isAdd'];
                            $withdrawlist[$index]['withBeforeImgUrl'] = $row['withBeforeImgUrl'] ?: '';
                            $withdrawlist[$index]['withAfterImgUrl'] = $row['withAfterImgUrl'] ?: '';
                            $withdrawlist[$index]['recommandWithAmount'] = $row['recommandWithAmount'] ?: '';
                            $withdrawlist[$index]['withdrawTip'] = $row['withdrawTip'] ?: null;
                            
                            $index++;
                        }
                        
                        // Prepare final data
                        $data = [];
                        $data['withdrawlist'] = $withdrawlist;
                        $data['isOpenSafeGuide'] = false;
                        $data['safeGuideContent'] = '';
                        
                        $res['data'] = $data;
                        $res['code'] = 0;
                        $res['msg'] = 'Succeed';
                        $res['msgCode'] = 0;
                        $res['serviceNowTime'] = $shnunc;
                        http_response_code(200);
                        echo json_encode($res);
                        
                    } else {
                        $res['code'] = 404;
                        $res['msg'] = 'No withdrawal types found in database';
                        $res['msgCode'] = 404;
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
                $res['msg'] = 'Invalid Token';
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