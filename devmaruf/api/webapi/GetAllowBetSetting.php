<?php
include "../../conn.php";
include "../../functions2.php";

//rechage chek added by kids_op serch me telegram 
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
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        $shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
        $shonusign = strtoupper(md5($shonustr));
        
        if ($shonusign == $signature) {
            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1]; 
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);
            $byabaharkarta = $data_auth['payload']['id'];
            $rechargeQuery = "SELECT 1 FROM thevani WHERE balakedara = '$byabaharkarta' AND sthiti = 1 LIMIT 1";
            $rechargeResult = mysqli_query($conn, $rechargeQuery);
            
            
            
            
// Fetch `allowbet` and `min_recharge` column values from database
$sql = "SELECT allowbet, min_recharge FROM web_setting LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $allowbet = (bool) $row['allowbet']; // Convert to boolean (true/false)
    $min_recharge = $row['min_recharge']; // Fetch min_recharge value
} else {
    $allowbet = false; // Default value if not found
    $min_recharge = 100; // Default value if not found (change if needed)
}

// Now apply `allowbet` value to `canDirectToGame`
$canDirectToGame = $allowbet;

if (mysqli_num_rows($rechargeResult) == 0) {
    $res['data'] = [
        'canDirectToGame' => $canDirectToGame, // ✅ Now using `allowbet`
        'userRechargeTimes' => 0,
        'allowNoRechargeGame' => '0',
        'userRechargeAmount' => 0.0,
        'lowestRechargeAmountToGame' => $min_recharge, // ✅ Using `min_recharge`
    ];


                $res['code'] = 0;
                $res['msg'] = 'Succeed';
                $res['msgCode'] = 0;
                $res['serviceNowTime'] = $shnunc;
                http_response_code(200);
                echo json_encode($res);
            } else {
                $data = [
                    [
                        'id' => 4,
                        'typeNameCode' => 9304,
                        'categoryCode' => 'Slot',
                        'categoryName' => '电子游戏',
                        'state' => 1,
                        'sort' => 7,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_202403062335332eu1.png'
                    ],
                    [
                        'id' => 1,
                        'typeNameCode' => 9301,
                        'categoryCode' => 'Lottery',
                        'categoryName' => '彩票',
                        'state' => 1,
                        'sort' => 6,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_2024030623351551lj.png'
                    ],
                    [
                        'id' => 5,
                        'typeNameCode' => 9305,
                        'categoryCode' => 'Sport',
                        'categoryName' => '体育游戏',
                        'state' => 1,
                        'sort' => 5,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_20240306233509iqfa.png'
                    ],
                    [
                        'id' => 6,
                        'typeNameCode' => 9306,
                        'categoryCode' => 'Video',
                        'categoryName' => '视讯游戏',
                        'state' => 1,
                        'sort' => 4,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_20240306233502xkvs.png'
                    ],
                    [
                        'id' => 7,
                        'typeNameCode' => 9307,
                        'categoryCode' => 'Chess',
                        'categoryName' => '棋牌游戏',
                        'state' => 1,
                        'sort' => 3,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_202403062335551n7x.png'
                    ],
                    [
                        'id' => 3,
                        'typeNameCode' => 9303,
                        'categoryCode' => 'Fish',
                        'categoryName' => '捕鱼游戏',
                        'state' => 1,
                        'sort' => 2,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_202403062334443pb8.png'
                    ],
                    [
                        'id' => 8,
                        'typeNameCode' => 9308,
                        'categoryCode' => 'Flash',
                        'categoryName' => '小游戏',
                        'state' => 1,
                        'sort' => 1,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_20240306233424oag3.png'
                    ],
                    [
                        'id' => 2,
                        'typeNameCode' => 9302,
                        'categoryCode' => 'Popular',
                        'categoryName' => '热门游戏',
                        'state' => 1,
                        'sort' => 0,
                        'categoryImg' => 'https://pub-628304d7b25d454abf303bfafba6a2e0.r2.dev/ALADDINN/gamecategory/gamecategory_20240306233417aiaq.png'
                    ]
                ];
                
                $res['data'] = $data;
                $res['code'] = 0;
                $res['msg'] = 'Succeed';
                $res['msgCode'] = 0;
                $res['serviceNowTime'] = $shnunc;
                http_response_code(200);
                echo json_encode($res);
            }
        } else {
            // Invalid signature
            $res['code'] = 5;
            $res['msg'] = 'Wrong signature';
            $res['msgCode'] = 3;
            http_response_code(200);
            echo json_encode($res);
        }
    } else {
        // Missing required parameters
        $res['code'] = 7;
        $res['msg'] = 'Param is Invalid';
        $res['msgCode'] = 6;
        http_response_code(200);
        echo json_encode($res);
    }
} else {
    // Invalid request method
    http_response_code(405);
    echo json_encode($res);
}
?>
