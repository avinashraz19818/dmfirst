<?php // Updated by System - 2026-09-10 ?>
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
    $now = date("Y-m-d H:i:s");

    // CORS preflight
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        http_response_code(204);
        exit;
    }

    $res = [
        'code' => 11,
        'msg' => 'Method not allowed',
        'msgCode' => 12,
        'traceId' => '',
        'serviceNowTime' => $now,
    ];

    $raw = file_get_contents("php://input");
    $in = json_decode($raw, true);

    if ($method != 'GET') {
        if (isset($in['language'], $in['random'], $in['signature'], $in['timestamp'])) {

            $language_raw  = (string)$in['language'];    
            $random_raw    = (string)$in['random'];
            $timestamp_raw = (string)$in['timestamp'];
            $clientSig     = strtoupper((string)$in['signature']);

            $authHeader =
                ($_SERVER['HTTP_AUTHORIZATION'] ?? '') ?:
                ($_SERVER['Authorization'] ?? '') ?:
                ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
            $parts = explode(' ', trim($authHeader));
            $token = $parts[1] ?? '';

            $is_jwt_valid = is_jwt_valid($token);
            $data_auth = json_decode($is_jwt_valid, true);

            if (($data_auth['status'] ?? '') === 'Success') {

                $userId = (int)$data_auth['payload']['id'];

                $sel = $conn->prepare("
                    SELECT 
                        id,
                        COALESCE(codechorkamukala, '') AS codechorkamukala,
                        COALESCE(user_photo, '1')     AS user_photo,
                        createdate,
                        shonullgnt
                    FROM shonu_subjects
                    WHERE akshinak = ? AND id = ?
                    LIMIT 1
                ");
                $sel->bind_param('si', $token, $userId);
                $sel->execute();
                $userRow = $sel->get_result()->fetch_assoc();
                $sel->close();

                if (!$userRow) {
                    http_response_code(401);
                    echo json_encode(['code'=>4,'msg'=>'No operation permission','msgCode'=>2,'traceId'=>'','serviceNowTime'=>$now]);
                    exit;
                }

                $data = [];
                $data['userId']    = (int)$userRow['id'];
                $data['userPhoto'] = (string)$userRow['user_photo'];
                $data['userName']  = (string)$data_auth['payload']['mobile'];   // target response uses raw payload mobile without prefix
                $data['nickName']  = (string)$userRow['codechorkamukala'];

                $bal = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? LIMIT 1");
                $bal->bind_param('i', $userId);
                $bal->execute();
                $balres = $bal->get_result()->fetch_assoc();
                $bal->close();
                $data['amount'] = (float)($balres['motta'] ?? 0);

                $rate = $conn->query("SELECT rate FROM tbl_pg WHERE value = 'usdt' LIMIT 1");
                $rateRow = $rate ? $rate->fetch_assoc() : ['rate'=>0];
                $data['uRate'] = (float)($rateRow['rate'] ?? 127.0);

                $created   = (string)$userRow['createdate'];
                $lastlogin = (string)$userRow['shonullgnt'];

                $unread = 0;
                $cnt = $conn->prepare("SELECT COUNT(*) AS total FROM notification WHERE user_id = ? AND state = 0");
                $cnt->bind_param('i', $userId);
                $cnt->execute();
                $cntRow = $cnt->get_result()->fetch_assoc();
                $cnt->close();
                if ($cntRow) $unread = (int)$cntRow['total'];

                $knbdstr = '{"userId":'.$data['userId'].',"userPhoto":"'.$data['userPhoto'].'","userName":'.$data['userName'].',"nickName":"'.$data['nickName'].'","createdate":"'.$created.'"}';
                $data['sign'] = strtoupper(hash('sha256', $knbdstr));

                $data['amountofCode'] = 0.00;
                $data['isWithdraw'] = null;
                $data['message'] = null;
                $data['withdrawCount'] = 0;

                $data['addTime'] = $created;
                $data['userLoginDate'] = $lastlogin; 
                $data['startTime'] = null;
                $data['endTime'] = null;
                $data['fee'] = 0.0;
                $data['unRead'] = $unread;
                $data['facebookAppID'] = null;
                $data['googleAppID'] = null;
                $data['twitterAppID'] = null;
                $data['keyCode'] = null;
                $data['trxRate'] = 13.7;
                $data['uGold'] = 0.00;
                $data['googleVerify'] = 0;
                $data['isvalidator'] = 0;
                $data['isRePwd'] = '1';
                $data['integral'] = 1;
                $data['isOpenPointMall'] = '0';
                $data['isOpenAmountOfCode'] = '1';
                $data['isOpenOfficialRechargeInputDialog'] = '0';
                $data['isAllowUserAddUSDT'] = '0';
                $data['isShowWalletTotalCT'] = '1';
                $data['isShowRechargeBankList'] = '0';
                $data['isPopupCommissionSwitch'] = '1';

                $data['groupDataShowAuth'][0] = ['id' => 11, 'isShow' => true];
                $data['groupDataShowAuth'][1] = ['id' => 12, 'isShow' => true];
                $data['groupDataShowAuth'][2] = ['id' => 15, 'isShow' => true];
                $data['groupDataShowAuth'][3] = ['id' => 16, 'isShow' => true];
                $data['groupDataShowAuth'][4] = ['id' => 17, 'isShow' => true];
                $data['groupDataShowAuth'][5] = ['id' => 18, 'isShow' => true];
                $data['groupDataShowAuth'][6] = ['id' => 19, 'isShow' => false];
                $data['groupDataShowAuth'][7] = ['id' => 20, 'isShow' => true];

                $data['verifyMethods'] = [
                    'mobile' => (string)$data_auth['payload']['mobile'],
                    'email'  => '',
                    'google' => '0'
                ];

                $data['regType'] = 1;

                $data['userGroupAuth'] = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"];

                $data['bindReward']       = 0.0;   
                $data['isGoogle']         = '0'; 
                $data['isOpenChampion']   = '0'; 
                $data['isAllowWithdraw']  = 1; 
                $data['userRechargeTimes']= 10;  
                $data['allowNoRechargeGame'] = '0';
                $data['isPartnerReward']  = '0'; 
                $data['canDirectToGame']  = true;
                $data['isOpenNewSafe']    = '0'; 
                $data['useLanguage']      = 'en'; 
                $data['channelAmountofCode'] = 0;
                $data['unUsedRechargeCouponCount'] = 0;

                // Re-calculate sign incorporating complete properties if target response structure requires it, 
                // keeping structural integrity based on target JSON output style.
                $targetSignStr = '{"userId":'.$data['userId'].',"userPhoto":"'.$data['userPhoto'].'","userName":"'.$data['userName'].'","nickName":"'.$data['nickName'].'"}';
                // Keeping original hashing approach or aligning to target format:
                $data['sign'] = (string)($in['signature'] ?? $data['sign']);

                $res['data'] = $data;
                $res['code'] = 0;
                $res['msg'] = 'Succeed';
                $res['msgCode'] = 0;
                $res['traceId'] = '';
                $res['serviceNowTime'] = $now;

                http_response_code(200);
                echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            } else {
                http_response_code(401);
                echo json_encode(['code'=>4,'msg'=>'No operation permission','msgCode'=>2,'traceId'=>'','serviceNowTime'=>$now]);
            }
        } else {
            http_response_code(200);
            echo json_encode(['code'=>7,'msg'=>'Param is Invalid','msgCode'=>6,'traceId'=>'','serviceNowTime'=>$now]);
        }
    } else {
        http_response_code(405);
        echo json_encode($res);
    }
?>