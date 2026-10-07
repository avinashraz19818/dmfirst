<?php
// Prevent PHP warnings/notices from breaking the JSON output format
ini_set('display_errors', 0);
error_reporting(E_ALL);

include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');

// Restricted CORS whitelist
$allowed_origins = [
    'https://maxclub1.com',
    'https://maxclub.site'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: *');
header('Vary: Origin');

date_default_timezone_set('Asia/Kolkata');
$shnunc = date("Y-m-d H:i:s");

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $shonubody = file_get_contents("php://input");
    $shonupost = json_decode($shonubody, true) ?? [];

    if (
        isset($shonupost['typeId'], $shonupost['language'], $shonupost['random'], $shonupost['signature'], $shonupost['timestamp'])
    ) {
        $typeId = $shonupost['typeId'];
        $language = $shonupost['language'];
        $random = $shonupost['random'];
        $signature = $shonupost['signature'];

        $shonustr = '{"language":' . $language . ',"random":"' . $random . '","typeId":' . $typeId . '}';
        $shonusign = strtoupper(md5($shonustr));

        // Safely extract Authorization Bearer token without throwing undefined index warnings
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $bearer = explode(" ", $authHeader);
        $author = $bearer[1] ?? '';

        if (hash_equals($shonusign, strtoupper($signature))) {
            // Correct zero-indexed array declaration
            $data = [
                [
                    'typeID' => 3,
                    'name' => 'Live Chat',
                    'url' => 'https://maxclub.site/support/',
                    'sort' => 0
                ]
            ];

            $res = [
                'code' => 0,
                'msg' => 'Succeed',
                'msgCode' => 0,
                'serviceNowTime' => $shnunc,
                'data' => $data
            ];
            http_response_code(200);
        } else {
            $res = [
                'code' => 5,
                'msg' => 'Wrong signature',
                'msgCode' => 3,
                'serviceNowTime' => $shnunc,
            ];
            http_response_code(200);
        }
    } else {
        $res = [
            'code' => 7,
            'msg' => 'Param is Invalid',
            'msgCode' => 6,
            'serviceNowTime' => $shnunc,
        ];
        http_response_code(200);
    }
} else {
    http_response_code(405);
}

// Single JSON response point
echo json_encode($res);
exit;