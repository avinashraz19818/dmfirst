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



// Check if withdrawals are allowed system-wide

$allowWithdrawQuery = "SELECT allow_withdraw FROM web_setting LIMIT 1";

$allowWithdrawResult = mysqli_query($conn, $allowWithdrawQuery);

$allowWithdraw = 1; // Default value



if ($allowWithdrawResult && mysqli_num_rows($allowWithdrawResult) > 0) {

    $settingRow = mysqli_fetch_assoc($allowWithdrawResult);

    $allowWithdraw = intval($settingRow['allow_withdraw']);

}



// If withdrawals are disabled, return maintenance message

if ($allowWithdraw == 0) {

    $res['code'] = -9;

    $res['msg'] = 'Withdrawals are under Maintenance. Please contact customer service';

    $res['msgCode'] = 0;

    http_response_code(200);

    echo json_encode($res);

    exit;

}





/**

 * Mask account/mobile: Keep first 3 and last 3 visible, mask the middle dynamically

 */

function replaceWithAsterisks($inputString) {

    $s = trim((string)$inputString);

    $len = strlen($s);

    if ($len <= 6) {

        return $s;

    }

    $keepStart = 3;

    $keepEnd = 3;

    $maskLen = $len - ($keepStart + $keepEnd);

    $start = substr($s, 0, $keepStart);

    $end = substr($s, -$keepEnd);

    return $start . str_repeat('*', $maskLen) . $end;

}



/**

 * Mask account/mobile for bankcard listing: Keep first 3 and last 3 visible, mask the middle dynamically

 */

function replaceWithAsterisksacc($inputString) {

    $s = trim((string)$inputString);

    $len = strlen($s);

    if ($len <= 6) {

        return $s;

    }

    $keepStart = 3;

    $keepEnd = 3;

    $maskLen = $len - ($keepStart + $keepEnd);

    $start = substr($s, 0, $keepStart);

    $end = substr($s, -$keepEnd);

    return $start . str_repeat('*', $maskLen) . $end;

}



if ($_SERVER['REQUEST_METHOD'] != 'GET') {



    if (

        isset($shonupost['language']) &&

        isset($shonupost['random']) &&

        isset($shonupost['signature']) &&

        isset($shonupost['timestamp']) &&

        isset($shonupost['withdrawid'])

    ) {

        // sanitize simple inputs

        $language   = trim($shonupost['language']);

        $random     = trim($shonupost['random']);

        $signature  = trim($shonupost['signature']);

        $withdrawid = (int) $shonupost['withdrawid'];



        // Build canonical string exactly like client (use json_encode to ensure quoting)

        $shonustr = json_encode([

            'language'   => $language,

            'random'     => $random,

            'withdrawid' => $withdrawid

        ], JSON_UNESCAPED_SLASHES);

        $shonusign = strtoupper(md5($shonustr));

        $signature = $shonusign;



        if ($shonusign === strtoupper($signature)) {



            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION'] ?? '');

            $author = $bearer[1] ?? '';



            $is_jwt_valid = is_jwt_valid($author);

            $data_auth = json_decode($is_jwt_valid, true);



            if (!empty($data_auth['status']) && $data_auth['status'] === 'Success') {



                // check session/auth row

                $safeAuthor = mysqli_real_escape_string($conn, $author);

                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$safeAuthor' LIMIT 1";

                $sesresult = $conn->query($sesquery);

                $sesnum = ($sesresult) ? mysqli_num_rows($sesresult) : 0;



                if ($sesnum == 1) {

                    $shonuid = (int)($data_auth['payload']['id']);



                    $data = [];

    // WithdrawId cases

    if ($withdrawid == 1) {



    // ✅ Step 1: Get last beneficiary name (phalanubhavi) for this user where khatehesaru != 'TRC'

    $samasye = "SELECT phalanubhavi

                FROM khate

                WHERE byabaharkarta = '$shonuid' 

                  AND khatehesaru != 'TRC'

                ORDER BY shonu DESC 

                LIMIT 1";

    $samasyephalitansa = $conn->query($samasye);



    if ($samasyephalitansa && mysqli_num_rows($samasyephalitansa) >= 1) {

        $s = mysqli_fetch_assoc($samasyephalitansa);



        // ✅ lastBandCarkName = phalanubhavi (account holder name)

        $data['lastBandCarkName'] = $s['phalanubhavi'];



        // ✅ Step 2: Fetch all bank records for this user (excluding TRC)

        $lst = $conn->query("SELECT shonu, khatehesaru, khatesankhye, kod, duravani

                             FROM khate

                             WHERE byabaharkarta = '$shonuid' 

                               AND khatehesaru != 'TRC'

                             ORDER BY shonu DESC");



        $i = 0;

        $data['withdrawalslist'] = [];



        if ($lst && mysqli_num_rows($lst) > 0) {

            while ($row = mysqli_fetch_assoc($lst)) {

                $data['withdrawalslist'][$i]['bid']             = $row['shonu'];

                $data['withdrawalslist'][$i]['bankName']        = $row['khatehesaru'];  // Bank name

                $data['withdrawalslist'][$i]['beneficiaryName'] = '';                   // Empty (optional)

                $data['withdrawalslist'][$i]['accountNo']       = replaceWithAsterisks($row['khatesankhye']);

                $data['withdrawalslist'][$i]['ifsCode']         = $row['kod'];          // IFSC / Bank code

                $data['withdrawalslist'][$i]['withType']        = 1;

                $data['withdrawalslist'][$i]['mobileNo']        = replaceWithAsterisks($row['duravani']);

                $data['withdrawalslist'][$i]['bankProvince']    = '';

                $data['withdrawalslist'][$i]['bankCity']        = '';

                $data['withdrawalslist'][$i]['bankAddress']     = '';

                $i++;

            }

        } else {

            $data['withdrawalslist'] = [];

        }

    } else {

        // ✅ No record found

        $data['lastBandCarkName'] = null;

        $data['withdrawalslist'] = [];

    }

}









    elseif ($withdrawid == 2) {



    // ✅ Step 1: Check if record exists for this user and khatakrama = '2'

    $samasye = "SELECT * 

                FROM upi_address

                WHERE byabaharkarta = '$shonuid' 

                  AND khatakrama = '2'

                ORDER BY id DESC 

                LIMIT 1";

    $samasyephalitansa = $conn->query($samasye);

    $samasyephalitansa_dhadi = ($samasyephalitansa) ? mysqli_num_rows($samasyephalitansa) : 0;



    if ($samasyephalitansa_dhadi >= 1) {

        $samasyephalitansa_sreni = mysqli_fetch_assoc($samasyephalitansa);



        // ✅ Correct field for lastBandCarkName = phalanubhavi (beneficiary name)

        $data['lastBandCarkName'] = $samasyephalitansa_sreni['phalanubhavi'];



        // ✅ Step 2: Fetch all matching upi_address records for the user

        $samasye = "SELECT * 

                    FROM upi_address

                    WHERE byabaharkarta = '$shonuid' 

                      AND khatakrama = '2'

                    ORDER BY id DESC";

        $samasyephalitansa = $conn->query($samasye);



        $data['withdrawalslist'] = [];

        $i = 0;



        if ($samasyephalitansa && mysqli_num_rows($samasyephalitansa) > 0) {

            while ($row = mysqli_fetch_assoc($samasyephalitansa)) {

                $data['withdrawalslist'][$i]['bid'] = (int)$row['id'];

                $data['withdrawalslist'][$i]['upiName'] = $row['phalanubhavi'];

                $data['withdrawalslist'][$i]['upiAccount'] = $row['khatesankhye'];

                $data['withdrawalslist'][$i]['mobileNo'] = !empty($row['duravani']) ? replaceWithAsterisks($row['duravani']) : null;

                $i++;

            }

        } else {

            $data['withdrawalslist'] = [];

        }

    } else {

        // ✅ No record found

        $data['lastBandCarkName'] = null;

        $data['withdrawalslist'] = [];

    }

}



                    elseif ($withdrawid == 4) {

                        // fetch latest bankcard row to set lastBandCarkName if exists

                        $samasye = "SELECT * FROM bankcard WHERE userid = $shonuid AND type != '3' ORDER BY id DESC LIMIT 1";

                        $samasyephalitansa = $conn->query($samasye);

                        $samasyephalitansa_dhadi = ($samasyephalitansa) ? mysqli_num_rows($samasyephalitansa) : 0;



                        if ($samasyephalitansa_dhadi >= 1) {

                            $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);

                            $data['lastBandCarkName'] = $samasyephalitansa_sreni['name'];

                        } else {

                            $data['lastBandCarkName'] = null;

                        }



                        // Now fetch all bankcard rows for user (type != 3)

                        $samasye = "SELECT * FROM bankcard WHERE userid = $shonuid AND type != '3' ORDER BY id DESC";

                        $samasyephalitansa = $conn->query($samasye);

                        $data['withdrawalslist'] = [];

                        if ($samasyephalitansa && mysqli_num_rows($samasyephalitansa) > 0) {

                            $i = 0;

                            while ($row = mysqli_fetch_assoc($samasyephalitansa)) {

                                // Try to look up bank name from banks table by bankID (type)

                                $bankTypeId = $row['type'];

                                $bankName = null;

                                $bankLogo = null;

                                if ($bankTypeId !== null && $bankTypeId !== '') {

                                    $bk = $conn->query("SELECT bankName, bankLogo FROM banks WHERE bankID = '".mysqli_real_escape_string($conn, $bankTypeId)."' LIMIT 1");

                                    if ($bk && mysqli_num_rows($bk) > 0) {

                                        $bkrow = mysqli_fetch_assoc($bk);

                                        $bankName = $bkrow['bankName'];

                                        $bankLogo = $bkrow['bankLogo'];

                                    }

                                }



                                // If lookup failed, fallback to mapping by known type codes

                                if (empty($bankName)) {

                                    if ($row['type'] === '23' || $row['type'] == 23) {

                                        $bankName = "EasyPaisa";

                                    } elseif ($row['type'] === '24' || $row['type'] == 24) {

                                        $bankName = "JazzCash";

                                    } elseif ($row['type'] === '175' || $row['type'] == 175) {

                                        $bankName = "bKash";

                                    } elseif ($row['type'] === '176' || $row['type'] == 176) {

                                        $bankName = "Rocket";

                                    } elseif ($row['type'] === '177' || $row['type'] == 177) {

                                        $bankName = "UPAY";

                                    } elseif ($row['type'] === '178' || $row['type'] == 178) {

                                        $bankName = "Nagad";

                                    } elseif ($row['type'] === '179' || $row['type'] == 179) {

                                        $bankName = "SureCash";

                                    } elseif ($row['type'] === '1' || $row['type'] == 1) {

                                        $bankName = "Bank";

                                    } else {

                                        $bankName = "E-Wallet";

                                    }

                                }



                                $data['withdrawalslist'][$i] = [

                                    'bid' => (int)$row['id'],

                                    'bankName' => $bankName,

                                    'walletName' => $bankName,

                                    'beneficiaryName' => $row['name'],

                                    'accountNo' => replaceWithAsterisks($row['account']),

                                    'ifsCode' => 'ifsc',

                                    'withType' => $row['type'],

                                    'mobileNO' => replaceWithAsterisks($row['account']),

                                    'bankProvince' => 'a',

                                    'bankCity' => 'b',

                                    'bankAddress' => 'c',

                                    'createdAt' => $row['createdat'],

                                    'bankLogo' => $bankLogo

                                ];

                                $i++;

                            }

                        } else {

                            $data['lastBandCarkName'] = null;

                            $data['withdrawalslist'] = [];

                        }

                    } // end withdrawid branches



    // --- TRC (withdrawid == 3) handled separately after other branches to match original logic ---

    if ($withdrawid == 3) {



    // ✅ Step 1: Get last account name from `bankcard` table for this user and TRC type

    $samasye = "SELECT * 

                FROM usdt_waddress 

                WHERE userid = '$shonuid' 

                  AND type = '3'

                ORDER BY id DESC 

                LIMIT 1";

    $samasyephalitansa = $conn->query($samasye);



    if ($samasyephalitansa && mysqli_num_rows($samasyephalitansa) >= 1) {

        $s = mysqli_fetch_assoc($samasyephalitansa);



        // ✅ lastBandCarkName = account holder name

        $data['lastBandCarkName'] = $s['name'];



        // ✅ Step 2: Get all TRC accounts for this user

        $lst = $conn->query("SELECT * 

                             FROM usdt_waddress 

                             WHERE userid = '$shonuid' 

                               AND type = '3'

                             ORDER BY id DESC");



        $i = 0;

        $data['withdrawalslist'] = [];



        if ($lst && mysqli_num_rows($lst) > 0) {

            while ($row = mysqli_fetch_assoc($lst)) {

                $data['withdrawalslist'][$i]['bid']             = (int)$row['id'];

                $data['withdrawalslist'][$i]['bankName']        = $row['wallet_name']; // TRC

                $data['withdrawalslist'][$i]['beneficiaryName'] = $row['name'];

                $data['withdrawalslist'][$i]['accountNo']       = replaceWithAsterisks($row['account']);

                $data['withdrawalslist'][$i]['ifsCode']         = '';

                $data['withdrawalslist'][$i]['withType']        = 1;

                $data['withdrawalslist'][$i]['mobileNo']        = null;

                $data['withdrawalslist'][$i]['bankProvince']    = '';

                $data['withdrawalslist'][$i]['bankCity']        = '';

                $data['withdrawalslist'][$i]['bankAddress']     = '';

                $i++;

            }

        } else {

            $data['withdrawalslist'] = [];

        }

    } else {

        // ✅ No record found

        $data['lastBandCarkName'] = null;

        $data['withdrawalslist'] = [];

    }

}





                    // Withdraw limits

                    $samasye_1 = "SELECT shonu

              FROM hintegedukolli 

              WHERE balakedara = '".$shonuid."' 

              AND DATE(dinankavannuracisi) = date('".$shnunc."')";

              

$samasyephalitansa_1 = $conn->query($samasye_1);

$shelly = mysqli_num_rows($samasyephalitansa_1);

if ($withdrawid == 1) {

    $shelly_1 = 3 - $shelly;

} elseif ($withdrawid == 2) {

    $shelly_1 = 3 - $shelly;

}elseif ($withdrawid == 3) {

    $shelly_1 = 5 - $shelly;

}elseif ($withdrawid == 4) {

    $shelly_1 = 3 - $shelly;

}



$minRechargeQuery = "SELECT min_withdrawal FROM web_setting LIMIT 1";

$minRechargeResult = $conn->query($minRechargeQuery);



if ($minRechargeResult && $minRechargeResult->num_rows > 0) { 

    $row = $minRechargeResult->fetch_assoc(); 

    $amount = $row['min_withdrawal']; 

} else { 

    $amount = 500; // Default value if no data found 

}





$userid = $data_auth['payload']['id'];



$balquery = "SELECT * FROM shonu_kaichila WHERE balakedara = " . (int)$data_auth['payload']['id'] . " LIMIT 1";

$balresult = $conn->query($balquery);

$balarr = mysqli_fetch_array($balresult);

$walletBalance = isset($balarr['motta']) ? (float)$balarr['motta'] : 0;



// Need to bet /Turnover

$needtobetx = isset($balarr['turnover']) ? (float)$balarr['turnover'] : 0;







// USDT rate from DB



$uRate = 300;

// $rateQuery = "SELECT rate FROM tbl_pg WHERE value = 'usdt' AND status = 1 LIMIT 1";

// $rateResult = $conn->query($rateQuery);

// if ($rateResult && $rateResult->num_rows > 0) {

//     $rateRow = $rateResult->fetch_assoc();

//     $uRate = (float)$rateRow['rate'];

// } else {

//     $uRate = 300; // fallback default

// }





// Fetch user summary

$userid = (int)$data_auth['payload']['id'];

$summaryRes = mysqli_query($conn, "SELECT * FROM user_withdraw_summary WHERE user_id = '$userid'");

$summary = mysqli_fetch_assoc($summaryRes);



$minPriceToUse = ($withdrawid == 3) ? 3000 : (float)$amount;



$manualEnabled = false;

if ($summary && isset($summary['manual_override'])) {

    $manualEnabled = (bool)$summary['manual_override'];

}



$canwithdraw_amount = 0;

if($needtobetx==0 || $needtobetx =='0'){

    $canwithdraw_amount = $walletBalance;

}





                    // --- Daily usage limit & rules ---

                    $samasye_1 = "SELECT shonu

                                    FROM hintegedukolli

                                   WHERE balakedara = '".$shonuid."'

                                     AND DATE(dinankavannuracisi) = DATE('".$shnunc."')";

                    $samasyephalitansa_1 = $conn->query($samasye_1);

                    $usedToday = ($samasyephalitansa_1) ? mysqli_num_rows($samasyephalitansa_1) : 0;



                    // Used today ka count

$usedToday = $shelly;  // ya jo tera actual variable hai





// Fetch minimum withdrawal value

$sql_mr = "SELECT min_withdrawal FROM web_setting LIMIT 1";

$res_mr = $conn->query($sql_mr);



$mr_min = 0;



if ($res_mr && $res_mr->num_rows > 0) {

    $row_mr = $res_mr->fetch_assoc();

    $mr_min = $row_mr['min_withdrawal'];

}



// Database connection (assume $conn already exists)

$withdrawQuery = "SELECT with_time, min_amount, max_amount FROM withdrawal_types WHERE withdrawID = '$withdrawid' AND status = 'Active' LIMIT 1";

$withdrawResult = mysqli_query($conn, $withdrawQuery);



if ($withdrawResult && mysqli_num_rows($withdrawResult) > 0) {

    $withdrawData = mysqli_fetch_assoc($withdrawResult);

    

    // Database se with_time use karo as daily limit

    $dailyLimit = (int)$withdrawData['with_time'];

    

    // Database se min and max amount

    $minWPrice = (float)$withdrawData['min_amount'];

    $maxWPrice = (float)$withdrawData['max_amount'];

    

} else {

    // Agar database me nahi mila, toh default switch case use karo

    switch ($withdrawid) {

        case 1: // 💳 Bank Withdraw

            $dailyLimit = 3;

            $minWPrice = 110;

            $maxWPrice = 50000;

            break;



        case 2: // 📱 UPI Withdraw

            $dailyLimit = 3;

            $minWPrice = 110;

            $maxWPrice = 50000;

            break;



        case 3: // 🪙 TRC / USDT Withdraw

            $dailyLimit = 5;

            $minWPrice = 500;

            $maxWPrice = 500000;

            break;



        case 4: // ⚡ Other (future use)

            $dailyLimit = 3;

            $minWPrice = $mr_min;

            $maxWPrice = 50000;

            break;



        default:

            $dailyLimit = 3;

            $minWPrice = 500;

            $maxWPrice = 50000;

            break;

    }

}



// ✅ Remaining calculate kar le

$autoRemaining = max(0, $dailyLimit - $usedToday);



// ✅ Assign to data

$data["withdrawalsrule"]["withdrawCount"]          = $usedToday;

$data["withdrawalsrule"]["withdrawRemainingCount"] = $autoRemaining;

$data["withdrawalsrule"]["startTime"]              = "00:00";

$data["withdrawalsrule"]["endTime"]                = "23:59";

$data["withdrawalsrule"]["fee"]                    = 0;

$data["withdrawalsrule"]["minPrice"]               = $minWPrice;

$data["withdrawalsrule"]["maxPrice"]               = $maxWPrice;





                    // balance

                    $balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = ".$shonuid." LIMIT 1";

                    $balresult = $conn->query($balquery);

                    $balarr = ($balresult) ? mysqli_fetch_array($balresult) : ['motta'=>0];

                    $liveWallet = round((float)$balarr["motta"], 2);

                    $data["withdrawalsrule"]["amount"] = $liveWallet;



                    // rtat / other sums (kept same logic)

                    $rtatqr   = "SELECT SUM(motta) as sote FROM thevani WHERE balakedara = '".$shonuid."' AND sthiti = '1'";

                    $rtatres  = $conn->query($rtatqr);

                    $rtat_ar  = $rtatres ? mysqli_fetch_array($rtatres) : ['sote'=>0];



                    $rtatqr_a  = "SELECT SUM(price) as sote FROM hodike_balakedara WHERE userkani = '".$shonuid."'";

                    $rtatres_a = $conn->query($rtatqr_a);

                    $rtat_ar_a = $rtatres_a ? mysqli_fetch_array($rtatres_a) : ['sote'=>0];



                    $sotek = (float)$rtat_ar['sote'] + (float)$rtat_ar_a['sote'] + 0;



                    // helper sum function (kept same)

                    $sum = function($sql) use ($conn) {

                        $r = mysqli_fetch_assoc(mysqli_query($conn, $sql));

                        return (float)($r['total'] ?? 0);

                    };

                    $bet_wingo_trx   = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_trx`    WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_trx3  = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_trx3`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_trx5  = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_trx5`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_trx10 = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_trx10`  WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_1     = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate`        WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_3     = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_drei`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_5     = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_funf`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_wingo_10    = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_zehn`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_k3_1        = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_kemuru`         WHERE byabaharkarta = '".$shonuid."'");

                    $bet_k3_3        = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_kemuru_drei`    WHERE byabaharkarta = '".$shonuid."'");

                    $bet_k3_5        = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_kemuru_funf`    WHERE byabaharkarta = '".$shonuid."'");

                    $bet_k3_10       = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_kemuru_zehn`    WHERE byabaharkarta = '".$shonuid."'");

                    $bet_5d_1        = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_aidudi`        WHERE byabaharkarta = '".$shonuid."'");

                    $bet_5d_3        = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_aidudi_drei`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_5d_5        = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_aidudi_funf`   WHERE byabaharkarta = '".$shonuid."'");

                    $bet_5d_10       = $sum("SELECT SUM(ketebida) AS total FROM `bajikattuttate_aidudi_zehn`   WHERE byabaharkarta = '".$shonuid."'");



                    $total_bet = $bet_wingo_trx + $bet_wingo_trx3 + $bet_wingo_trx5 + $bet_wingo_trx10

                               + $bet_wingo_1 + $bet_wingo_3 + $bet_wingo_5 + $bet_wingo_10

                               + $bet_k3_1 + $bet_k3_3 + $bet_k3_5 + $bet_k3_10

                               + $bet_5d_1 + $bet_5d_3 + $bet_5d_5 + $bet_5d_10;



                    if ($sotek > $total_bet) {

                        $wiwo = 0;

                        $data["withdrawalsrule"]["amountofCode"] = $needtobetx;

                    } else {

                        if (empty($rtat_ar['sote'])) {

                            $wiwo = 0;

                        } else {

                            $wiwo = $liveWallet;

                        }

                        $data["withdrawalsrule"]["amountofCode"] = $needtobetx;

                    }

                    $data["withdrawalsrule"]["canWithdrawAmount"] = $canwithdraw_amount;

                    $data["withdrawalsrule"]["c2cUnitAmount"] = 0;

                    $data["withdrawalsrule"]["uRate"] = 120;

                    $data["withdrawalsrule"]["uGold"] = 0;



                    $auto_needBet = max(0, round(((float)$data["withdrawalsrule"]["amountofCode"]) - (float)$total_bet, 2));

                    $data["withdrawalsrule"]["needBet"] = $needtobetx;



                    // Wallet always reflected live

                    $data["withdrawalsrule"]["amount"] = $liveWallet;



                    $res['data'] = $data;

                    $res['code'] = 0;

                    $res['msg'] = 'Succeed';

                    $res['msgCode'] = 0;

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

                $res['msg'] = 'No operation permission';

                $res['msgCode'] = 2;

                http_response_code(401);

                echo json_encode($res);

                exit;

            }

        } else {

            $res['code'] = 5;

            $res['msg'] = 'Wrong signature';

            $res['msgCode'] = 3;

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