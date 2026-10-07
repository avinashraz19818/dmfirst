<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
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

        $pageNo = isset($shonupost['pageNo']) ? intval($shonupost['pageNo']) : 1;
        $pageSize = isset($shonupost['pageSize']) ? intval($shonupost['pageSize']) : 10;

        if ($pageNo < 1) $pageNo = 1;

        $shonustr = '{"language":'.$language.',"pageNo":'.$pageNo.',"pageSize":'.$pageSize.',"random":"'.$random.'"}';
        if($pageNo > 9){
            $shonustr = '{"language":'.$language.',"pageNo":"'.$pageNo.'","pageSize":'.$pageSize.',"random":"'.$random.'"}';
        }

        $shonusign = strtoupper(md5($shonustr));

        if ($shonusign == $signature) {

            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1];
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);

            if ($data_auth['status'] === 'Success') {

                $userId = $data_auth['payload']['id'];

                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);

                if ($sesresult && mysqli_num_rows($sesresult) == 1) {

                    $offset = ($pageNo - 1) * $pageSize;
                    $finalList = [];

                    // 1) Normal notifications
                    $notiQuery = "SELECT * FROM notification WHERE user_id = $userId ORDER BY created_at DESC LIMIT $offset, $pageSize";
                    $notiResult = $conn->query($notiQuery);
                    
                    if ($notiResult) {
                        while ($row = $notiResult->fetch_assoc()) {
                            $finalList[] = [
                                'messageID' => $row['id'] ?? 0,
                                'category' => 'system',
                                'addTime' => $row['created_at'],
                                'title' => $row['title'],
                                'state' => $row['state'],
                                'messages' => "Your account was just logged in on {$row['created_at']}. If you have any questions, please feel free to contact online customer service! I wish you happy gaming and lots of ৳ profits!",
                            ];
                        }
                    }

                    // 2) Deposit/Withdrawal notifications
                    $dwQuery = "SELECT * FROM deposit_withdrawal_notification WHERE user_id = $userId ORDER BY created_at DESC LIMIT $offset, $pageSize";
                    $dwResult = $conn->query($dwQuery);
                    
                    if ($dwResult) {
                        while ($row = $dwResult->fetch_assoc()) {
                            $finalList[] = [
                                'messageID' => $row['id'] ?? 0,
                                'category' => 'transaction',
                                'addTime' => $row['created_at'],
                                'title' => $row['title'],
                                'state' => $row['state'],
                                'messages' => $row['message'],
                            ];
                        }
                    }

                    // 3) Deposit notifications (thevani)
                    $thevaniQuery = "SELECT * FROM thevani WHERE balakedara = $userId ORDER BY dinankavannuracisi DESC LIMIT $offset, $pageSize";
                    $thevaniResult = $conn->query($thevaniQuery);
                    
                    if ($thevaniResult) {
                        while ($row = $thevaniResult->fetch_assoc()) {
                            $amt = $row['motta'];
                            $currency = "৳";
                            
                            if ($row['mula'] === "USDT") {
                                $currency = "$";
                            }

                            if ($row['sthiti'] == 1) {
                                $title = "Deposit Successful";
                                $message = "Your deposit of {$currency}{$amt} has been successful.";
                            } else {
                                $title = "Deposit Pending";
                                $message = "Your deposit of {$currency}{$amt} is currently pending.";
                            }

                            $finalList[] = [
                                'messageID' => $row['id'] ?? $row['serial'] ?? 0,
                                'category' => 'deposit',
                                'addTime' => $row['dinankavannuracisi'],
                                'title' => $title,
                                'state' => $row['sthiti'],
                                'messages' => $message,
                            ];
                        }
                    }

                    // 4) Withdrawal notifications (hintegedukolli)
                    $wgQuery = "SELECT * FROM hintegedukolli WHERE balakedara = $userId ORDER BY dinankavannuracisi DESC LIMIT $offset, $pageSize";
                    $wgResult = $conn->query($wgQuery);
                    
                    if ($wgResult) {
                        while ($row = $wgResult->fetch_assoc()) {
                            $amt = $row['motta'];
                            $currency = "₹";

                            if ($row['madari'] === "USDT") {
                                $currency = "$";
                            }

                            if ($row['sthiti'] == 1) {
                                $title = "Withdrawal Successful";
                                $message = "Your withdrawal request of {$currency}{$amt} has been successful.";
                            } elseif ($row['sthiti'] == 2) {
                                $title = "Withdrawal Rejected";
                                $message = "Your withdrawal request of {$currency}{$amt} has been rejected.";
                            } else {
                                continue;
                            }

                            $finalList[] = [
                                'messageID' => $row['id'] ?? $row['serial'] ?? 0,
                                'category'  => 'withdrawal',
                                'addTime'   => $row['dinankavannuracisi'],
                                'title'     => $title,
                                'state'     => $row['sthiti'],
                                'messages'  => $message,
                            ];
                        }
                    }

                    // 5) Gift code notifications
                    $giftQuery = "SELECT * FROM hodike_balakedara WHERE userkani = $userId ORDER BY shonu DESC LIMIT $offset, $pageSize";
                    $giftResult = $conn->query($giftQuery);
                    
                    if ($giftResult) {
                        while ($row = $giftResult->fetch_assoc()) {
                            $amt = $row['price'];
                            $currency = "৳";

                            $finalList[] = [
                                'messageID' => $row['serial'] ?? $row['id'] ?? 0,
                                'category' => 'giftcode',
                                'addTime' => $row['shonu'],
                                'title' => "Gift Code",
                                'state' => 1,
                                'messages' => "Successfully Redeemed Gift Code of {$currency}{$amt}.",
                            ];
                        }
                    }

                    // 6) Feedback notifications
                    $feedbackQuery = "SELECT * FROM users_feedback WHERE user_id = $userId ORDER BY created_at DESC LIMIT $offset, $pageSize";
                    $feedbackResult = $conn->query($feedbackQuery);
                    
                    if ($feedbackResult) {
                        while ($row = $feedbackResult->fetch_assoc()) {
                            $finalList[] = [
                                'messageID' => $row['id'] ?? 0,
                                'category' => 'feedback',
                                'addTime' => $row['created_at'],
                                'title' => "Feedback Submitted",
                                'state' => 1,
                                'messages' => "Your feedback has been received: \"{$row['content']}\"",
                            ];
                        }
                    }

                    // Sort by latest time
                    usort($finalList, function($a, $b) {
                        return strtotime($b['addTime']) - strtotime($a['addTime']);
                    });

                    // Calculate total counts with error handling
                    $totalCount = 0;
                    $countQueries = [
                        "SELECT COUNT(*) AS total FROM notification WHERE user_id=$userId",
                        "SELECT COUNT(*) AS total FROM deposit_withdrawal_notification WHERE user_id=$userId",
                        "SELECT COUNT(*) AS total FROM thevani WHERE balakedara=$userId",
                        "SELECT COUNT(*) AS total FROM hintegedukolli WHERE balakedara=$userId",
                        "SELECT COUNT(*) AS total FROM hodike_balakedara WHERE userkani=$userId",
                        "SELECT COUNT(*) AS total FROM users_feedback WHERE user_id=$userId"
                    ];

                    foreach ($countQueries as $countQuery) {
                        $countResult = $conn->query($countQuery);
                        if ($countResult && $countRow = $countResult->fetch_assoc()) {
                            $totalCount += (int)$countRow['total'];
                        }
                    }

                    $data = [
                        'list' => $finalList,
                        'pageNo' => $pageNo,
                        'totalPage' => ceil($totalCount / $pageSize),
                        'totalCount' => $totalCount
                    ];

                    $res['data'] = $data;
                    $res['code'] = 0;
                    $res['msg'] = 'Succeed';
                    $res['msgCode'] = 0;

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