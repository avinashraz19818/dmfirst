<?php
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

// Read RAW JSON Payload
$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    
    // Authorization Check
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $bearer = explode(" ", $authHeader);
    $author = $bearer[1] ?? '';

    $is_jwt_valid = is_jwt_valid($author);
    $data_auth = json_decode($is_jwt_valid, true);

    if (isset($data_auth['status']) && $data_auth['status'] === 'Success') {
        
        // Fetch inputs dynamically
        $payid = (int)($shonupost['payid'] ?? $shonupost['payId'] ?? 0);
        $payTypeId = (int)($shonupost['payTypeId'] ?? $shonupost['paytypeid'] ?? 0);
        $price = (float)($shonupost['price'] ?? $shonupost['amount'] ?? $shonupost['rechargeAmount'] ?? 0);

        // Fetch User's ID (id) along with akshinak
        $stmt = $conn->prepare("SELECT id, akshinak FROM shonu_subjects WHERE akshinak = ?");
        $stmt->bind_param("s", $author);
        $stmt->execute();
        $sesresult = $stmt->get_result();

        if ($sesresult && $sesresult->num_rows === 1) {
            $userData = $sesresult->fetch_assoc();
            
            // Use Numeric User ID (id) instead of token string
            $userId = $userData['id'] ?? $userData['akshinak']; 

            $sites = 'https://dmfirst.club9.eu.cc';
            
            // Generate Unique Order ID
            $orderId = "ORD" . date("YmdHis") . rand(1000, 9999);

            // Determine Gateway Redirect URL
            $redirectUrl = "";

            if ($payid === 2 || in_array($payTypeId, [1010, 1012, 1013, 1014, 1015])) {
                $redirectUrl = $sites . "/pay/nagad.php?uid=" . urlencode($userId) . "&amount=" . $price;
            } elseif ($payid === 1 || in_array($payTypeId, [1023, 1025, 1026, 1027, 1028])) {
                $redirectUrl = $sites . "/pay/bkash.php?uid=" . urlencode($userId) . "&amount=" . $price;
            } elseif ($payid === 11 || $payid === 13 || in_array($payTypeId, [2123, 2190, 2191, 2192])) {
                $redirectUrl = $sites . "/pay/usdt_gateway.php?order_id=" . $orderId . "&amount=" . $price;
            } else {
                if ($payid == 2) {
                    $redirectUrl = $sites . "/pay/nagad.php?uid=" . urlencode($userId) . "&amount=" . $price;
                } else {
                    $redirectUrl = $sites . "/pay/bkash.php?uid=" . urlencode($userId) . "&amount=" . $price;
                }
            }

            // Success Response Payload
            $res['code'] = 0;
            $res['msg'] = 'Succeed';
            $res['msgCode'] = 0;
            $res['data'] = [
                'orderId'      => $orderId,
                'price'        => $price,
                'payUrl'       => $redirectUrl,
                'paySendUrl'   => $redirectUrl,
                'url'          => $redirectUrl,
                'typeUrl'      => $redirectUrl,
                'rechargeUrl'  => $redirectUrl,
                'link'         => $redirectUrl,
                'redirectUrl'  => $redirectUrl
            ];

            http_response_code(200);
            echo json_encode($res);
        } else {
            $res['code'] = 4;
            $res['msg'] = 'No operation permission';
            $res['msgCode'] = 2;
            http_response_code(401);
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
    http_response_code(405);
    echo json_encode($res);
}
?>