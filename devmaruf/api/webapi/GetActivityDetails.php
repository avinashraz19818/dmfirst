<?php 
// Fixed & Fully Optimized Web API for App Activity Details
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set('Asia/Dhaka');
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
    if (isset($shonupost['bannerId']) && isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
        $bannerId = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['bannerId']));
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        $shonustr = '{"bannerId":'.$bannerId.',"language":'.$language.',"random":"'.$random.'"}';
        $shonusign = strtoupper(md5($shonustr));
        
        if($shonusign == $signature){
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = isset($bearer[1]) ? $bearer[1] : '';                
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);
            
            if(isset($data_auth['status']) && $data_auth['status'] === 'Success') {
                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);
                $sesnum = mysqli_num_rows($sesresult);
                
                if($sesnum == 1){
                    // Fetch active activity banner details
                    $query = "SELECT title, img, coverUrl, jumpType 
                              FROM activity_banner_custom_data 
                              WHERE bannerId = '$bannerId' AND status = '1' 
                              LIMIT 1";
                    
                    $result = $conn->query($query);
                    
                    if($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $fixedId = "17225024498060d979egil58";
                        
                        $imgData = trim($row['img']);
                        $coverUrl = trim($row['coverUrl']);

                        // 🔥 DYNAMIC FORMAT CHECK: Smart rendering for HTML vs Image URL vs JSON
                        if (strpos($imgData, '<') === 0 || preg_match('/<[a-z][\s\S]*>/i', $imgData)) {
                            // 1. If it's HTML content, send raw HTML directly (No raw JSON brackets on screen)
                            $finalImg = $imgData;
                        } elseif (strpos($imgData, '[{"') === 0 || strpos($imgData, '[{"Id"') === 0) {
                            // 2. If already formatted JSON string, send as is
                            $finalImg = $imgData;
                        } else {
                            // 3. If single Image URL, format safely as App JSON structure
                            $imgArray = [
                                [
                                    "Id" => $fixedId,
                                    "Url" => $imgData
                                ]
                            ];
                            $finalImg = json_encode($imgArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                        
                        $data = [
                            'title' => $row['title'],
                            'img' => $finalImg,
                            'coverUrl' => $coverUrl,     
                            'jumpType' => (int)$row['jumpType']
                        ];
                        
                        $res['data'] = $data;
                        $res['code'] = 0;
                        $res['msg'] = 'Succeed';
                        $res['msgCode'] = 0;
                        http_response_code(200);
                        echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        exit();
                        
                    } else {
                        $res['code'] = 7;
                        $res['msg'] = 'Banner not found';
                        $res['msgCode'] = 0;
                        http_response_code(200);
                        echo json_encode($res);
                        exit();
                    }
                } else {
                    $res['code'] = 401;
                    $res['msg'] = 'Unauthorized';
                    $res['msgCode'] = 401;
                    http_response_code(200);
                    echo json_encode($res);
                    exit();
                }
            } else {
                $res['code'] = 401;
                $res['msg'] = 'Invalid token';
                $res['msgCode'] = 401;
                http_response_code(200);
                echo json_encode($res);
                exit();
            }
        } else {
            $res['code'] = 5;
            $res['msg'] = 'Wrong signature';
            $res['msgCode'] = 3;
            http_response_code(200);
            echo json_encode($res);
            exit();
        }
    } else {
        $res['code'] = 7;
        $res['msg'] = 'Param is Invalid';
        $res['msgCode'] = 6;
        http_response_code(200);
        echo json_encode($res);
        exit();
    }        
} else {        
    http_response_code(405);
    echo json_encode($res);
    exit();
}
?>