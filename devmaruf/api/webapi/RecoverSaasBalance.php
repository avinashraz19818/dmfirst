<?php
include_once "../../conn.php";
include_once "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
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
    if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
        
        $rawLanguage = $shonupost['language']; // যেভাবে এসেছে সেভাবেই রাখা হলো (Integer/String)
        $rawRandom = (string)$shonupost['random'];
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, (string)$shonupost['signature']));

        $language = htmlspecialchars(mysqli_real_escape_string($conn, (string)$rawLanguage));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $rawRandom));

        //---------------------------------------------------------
        // ফিক্স: ক্লায়েন্ট সাধারণত সংখ্যা পাঠালে সিগনেচার স্ট্রিং-এ কোটেশন থাকে না
        // ফরম্যাট: {"language":0,"random":"2b5a654ec9b04186a165e044183765cb"}
        //---------------------------------------------------------
        if (is_numeric($rawLanguage)) {
            $shonustr = '{"language":' . $rawLanguage . ',"random":"' . $rawRandom . '"}';
        } else {
            $shonustr = '{"language":"' . $rawLanguage . '","random":"' . $rawRandom . '"}';
        }
        
        $shonusign = strtoupper(md5($shonustr));

        if ($shonusign == $signature) {
            // Robust Authorization token extraction
            $author = '';
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
                if (count($parts) === 2) {
                    $author = $parts[1];
                } else {
                    $author = $_SERVER['HTTP_AUTHORIZATION'];
                }
            }
            if (empty($author)) {
                if (isset($shonupost['author'])) { $author = $shonupost['author']; }
                elseif (isset($shonupost['token'])) { $author = $shonupost['token']; }
                elseif (isset($_GET['author'])) { $author = $_GET['author']; }
                elseif (isset($_GET['token'])) { $author = $_GET['token']; }
            }
            $author = htmlspecialchars(mysqli_real_escape_string($conn, (string)$author));

            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);

            if ($data_auth['status'] === 'Success') {
                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);
                $sesnum = mysqli_num_rows($sesresult);

                if ($sesnum == 1) {
                    $userName = intval($data_auth['payload']['id']);

                    // Main Wallet ব্যালেন্স চেক
                    $balanceQuery = "SELECT motta FROM shonu_kaichila WHERE balakedara = '$userName'";
                    $balanceResult = $conn->query($balanceQuery);

                    if ($balanceResult && $balanceResult->num_rows > 0) {
                        // argame_balances থেকে রিকভারি প্রসেস
                        $arGameQuery = "SELECT balance FROM argame_balances WHERE user_id = '$userName'";
                        $arGameResult = $conn->query($arGameQuery);
                        
                        if ($arGameResult && $arGameResult->num_rows > 0) {
                            $arGameRow = $arGameResult->fetch_assoc();
                            $withdrawAmount = (float)$arGameRow['balance'];
                            
                            if ($withdrawAmount > 0) {
                                // 1. মেইন ওয়ালেটে ব্যালেন্স যোগ
                                $updateMainBalanceQuery = "UPDATE shonu_kaichila SET motta = motta + $withdrawAmount WHERE balakedara = '$userName'";
                                $mainUpdate = $conn->query($updateMainBalanceQuery);
                                
                                if ($mainUpdate) {
                                    // 2. গেম ওয়ালেট ০ করা
                                    $updateArGameQuery = "UPDATE argame_balances SET balance = 0, updated_at = NOW() WHERE user_id = '$userName'";
                                    $conn->query($updateArGameQuery);

                                    // হিস্ট্রি লগ রাখা
                                    $serial = 'RECALL_' . uniqid();
                                    $timestamp = date("Y-m-d H:i:s");
                                    $logQuery = "INSERT INTO game_moved_out_table (userkani, price, serial, remark, shonu) VALUES ('$userName', '$withdrawAmount', '$serial', 'Game Out', '$timestamp')";
                                    $conn->query($logQuery);
                                    
                                    $res = [
                                        "data" => [
                                            "amount" => $withdrawAmount,
                                            "uRate" => 93,
                                            "uGold" => 0.25
                                        ],
                                        "code" => 0,
                                        "msg" => "Recovery successful",
                                        "msgCode" => 0,
                                        "serviceNowTime" => date("Y-m-d H:i:s")
                                    ];
                                    http_response_code(200);
                                    echo json_encode($res);
                                    exit;
                                } else {
                                    $res = [
                                        "status" => false,
                                        "message" => "Failed to update main wallet",
                                    ];
                                    http_response_code(500);
                                    echo json_encode($res);
                                    exit;
                                }
                            } else {
                                $res = [
                                    "data" => [
                                        "amount" => 0,
                                        "uRate" => 93,
                                        "uGold" => 0.25
                                    ],
                                    "code" => 0,
                                    "msg" => "No balance to recover",
                                    "msgCode" => 0,
                                    "serviceNowTime" => date("Y-m-d H:i:s")
                                ];
                                http_response_code(200);
                                echo json_encode($res);
                                exit;
                            }
                        } else {
                             $res = [
                                "data" => [
                                    "amount" => 0,
                                    "uRate" => 93,
                                    "uGold" => 0.25
                                ],
                                "code" => 0,
                                "msg" => "No ARGame wallet found",
                                "msgCode" => 0,
                                "serviceNowTime" => date("Y-m-d H:i:s")
                            ];
                            http_response_code(200);
                            echo json_encode($res);
                            exit;
                        }
                    } else {
                        $res['code'] = 8;
                        $res['msg'] = 'Balance not found for user';
                        http_response_code(400);
                        echo json_encode($res);
                    }
                } else {
                    $res['code'] = 4;
                    $res['msg'] = 'No operation permission';
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
            // ডিবাগ ফিল্ডটি রেখে দেওয়া হলো যেন কোনো সমস্যা হলে দ্রুত ট্র্যাক করা যায়
            $res['code'] = 5;
            $res['msg'] = 'Wrong signature';
            $res['msgCode'] = 3;
            $res['debug'] = [
                'server_expected_string' => $shonustr,
                'server_generated_md5'   => $shonusign,
                'client_sent_signature'  => (string)$shonupost['signature']
            ];
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