<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php 
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, *');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

date_default_timezone_set('Asia/Kolkata');
$now = date("Y-m-d H:i:s");

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $now,
];

$body = file_get_contents("php://input");
$post = json_decode($body, true);

if ($_SERVER['REQUEST_METHOD'] != 'GET') {

    if (
        isset($post['bankid']) &&
        isset($post['codeType']) &&
        isset($post['language']) &&
        isset($post['random']) &&
        isset($post['signature']) &&
        isset($post['timestamp']) &&
        isset($post['withdrawid'])
    ) {			
        $bankid         = mysqli_real_escape_string($conn, $post['bankid']);
        $codeType       = mysqli_real_escape_string($conn, $post['codeType']);
        $language       = mysqli_real_escape_string($conn, $post['language']);		
        $random         = mysqli_real_escape_string($conn, $post['random']);
        $signature      = mysqli_real_escape_string($conn, $post['signature']);
        $smsCode        = mysqli_real_escape_string($conn, $post['smsCode'] ?? '');
        $type           = mysqli_real_escape_string($conn, $post['type'] ?? '');
        $usdtRemarkName = mysqli_real_escape_string($conn, $post['usdtRemarkName'] ?? '');
        $usdtaddress    = mysqli_real_escape_string($conn, $post['usdtaddress'] ?? '');
        $withdrawid     = (int) mysqli_real_escape_string($conn, $post['withdrawid']);

        $str = '{"bankid":'.$bankid.',"codeType":'.$codeType.',"language":'.$language.',"random":"'.$random.'","usdtRemarkName":"'.$usdtRemarkName.'","usdtaddress":"'.$usdtaddress.'","withdrawid":'.$withdrawid.'}';							
        $generatedSig = strtoupper(md5($str));

        if ($generatedSig === strtoupper($signature)) {

            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1] ?? '';				
            $jwtStatus = is_jwt_valid($author);
            $authData = json_decode($jwtStatus, true);

            if ($authData['status'] === 'Success') {

                $shonuid = $authData['payload']['id'];

                // ✅ Verify active session
                $sessionCheck = $conn->query("SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author' LIMIT 1");
                if ($sessionCheck && $sessionCheck->num_rows === 1) {

                    // ✅ Check if address already exists
                    $check = $conn->query("SELECT * FROM `usdt_waddress` WHERE `account` = '$usdtaddress' AND `userid` = '$shonuid'");
                    if ($check && $check->num_rows > 0) {
                        $res = [
                            'code' => -9,
                            'msg' => 'USDT address already exists',
                            'msgCode' => 0,
                        ];
                        http_response_code(200);
                        echo json_encode($res);
                        exit;
                    }

                    // ✅ Determine wallet name based on bankid
                    $walletName = '';
                    if ($bankid == 99999) {
                        $walletName = 'TRC20';
                    } elseif ($bankid == 99998) {
                        $walletName = 'BEP20';
                    } else {
                        $walletName = 'USDT';
                    }

                    // ✅ Insert new USDT address
                    $insert = $conn->query("
                        INSERT INTO `usdt_waddress` 
                        (`userid`, `account`, `name`, `wallet_name`, `type`, `createdat`)
                        VALUES 
                        ('$shonuid', '$usdtaddress', '$usdtRemarkName', '$walletName', '3', NOW())
                    ");

                    if ($insert) {
                        $res = [
                            'code' => 0,
                            'msg' => 'Succeed',
                            'msgCode' => 0,
                            'data' => null,
                        ];
                    } else {
                        $res = [
                            'code' => 9,
                            'msg' => 'Failed to add USDT address',
                            'msgCode' => 8,
                        ];
                    }
                    http_response_code(200);
                    echo json_encode($res);
                    exit;

                } else {
                    $res = [
                        'code' => 4,
                        'msg' => 'No operation permission',
                        'msgCode' => 2
                    ];
                    http_response_code(401);
                    echo json_encode($res);
                    exit;
                }

            } else {
                $res = [
                    'code' => 4,
                    'msg' => 'Invalid or expired token',
                    'msgCode' => 2
                ];
                http_response_code(401);
                echo json_encode($res);
                exit;
            }

        } else {
            $res = [
                'code' => 5,
                'msg' => 'Wrong signature',
                'msgCode' => 3
            ];
            http_response_code(200);
            echo json_encode($res);
            exit;
        }

    } else {
        $res = [
            'code' => 7,
            'msg' => 'Param is Invalid',
            'msgCode' => 6
        ];
        http_response_code(200);
        echo json_encode($res);
        exit;
    }

} else {		
    http_response_code(405);
    echo json_encode($res);
}

mysqli_close($conn);
?>