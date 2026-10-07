<?php 
	include "../../conn.php";
	include "../../functions2.php";
	
header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');

date_default_timezone_set('Asia/Kolkata');
$shnunc = date("Y-m-d H:i:s");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

// ==========================================
// 🔴 DB BASED ON / OFF CHECK
// ==========================================
$regStatusQuery = "SELECT register_status FROM web_setting LIMIT 1";
$regStatusResult = $conn->query($regStatusQuery);

$is_registration_open = 1; // Default ON
if ($regStatusResult && $regStatusResult->num_rows > 0) {
    $rowStatus = $regStatusResult->fetch_assoc();
    $is_registration_open = (int)$rowStatus['register_status'];
}

if ($is_registration_open === 0) {
    $res['code'] = 500;
    $res['msg'] = 'Registration is currently disabled by administrator.';
    $res['msgCode'] = 999;
    http_response_code(200);
    echo json_encode($res);
    exit();
}

$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    if (
        isset($shonupost['domainurl']) &&
        isset($shonupost['invitecode']) &&
        isset($shonupost['language']) &&
        isset($shonupost['phonetype']) &&
        isset($shonupost['pwd']) &&
        isset($shonupost['random']) &&
        isset($shonupost['registerType']) &&
        isset($shonupost['signature']) &&
        isset($shonupost['timestamp']) &&
        isset($shonupost['username']) &&
        isset($shonupost['track'])
    ) {
        $domainurl = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['domainurl']));
        $invitecode = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['invitecode']));
        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $phonetype = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['phonetype']));
        $pwd = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['pwd']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $registerType = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['registerType']));
        $username = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['username']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        
        $trackData = $shonupost['track'];

        if (substr($username, 0, 2) == "91") {
            $username = substr($username, 2);
        }

        // ==========================================
        // 🌐 IP & DEVICE FINGERPRINT DETECTION
        // ==========================================
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';

        $ipaddress = mysqli_real_escape_string($conn, $ipaddress);
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) : 'UNKNOWN';

        // ১. সাবনেট আইপি চেক
        $ip_parts = explode('.', $ipaddress);
        $ip_subnet = isset($ip_parts[0], $ip_parts[1], $ip_parts[2]) ? $ip_parts[0].'.'.$ip_parts[1].'.'.$ip_parts[2] : $ipaddress;
        
        $checkSubnetQuery = "SELECT COUNT(id) as total_subnet FROM shonu_subjects WHERE ip LIKE '$ip_subnet.%'";
        $checkSubnetResult = $conn->query($checkSubnetQuery);
        if ($checkSubnetResult) {
            $subnetRow = $checkSubnetResult->fetch_assoc();
            if ((int)$subnetRow['total_subnet'] >= 5) {
                $res['code'] = 500;
                $res['msg'] = 'Maximum account creation limit reached from this network range.';
                $res['msgCode'] = 999; 
                http_response_code(200);
                echo json_encode($res);
                exit();
            }
        }

        // ২. ডিভাইস ফিঙ্গারপ্রিন্ট চেক
        $checkDeviceQuery = "SELECT COUNT(id) as total_device FROM shonu_subjects WHERE tnegaresunohs = '$user_agent'";
        $checkDeviceResult = $conn->query($checkDeviceQuery);
        if ($checkDeviceResult) {
            $deviceRow = $checkDeviceResult->fetch_assoc();
            if ((int)$deviceRow['total_device'] >= 5) {
                $res['code'] = 500;
                $res['msg'] = 'You have already created 5 accounts from this device.';
                $res['msgCode'] = 999; 
                http_response_code(200);
                echo json_encode($res);
                exit();
            }
        }
        
        $samasye = "SELECT id FROM shonu_subjects WHERE owncode = '".$invitecode."'";
        $samasyephalitansa = $conn->query($samasye);
        $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
        if ($samasyephalitansa_dhadi == 1) {
            $samasye = "SELECT id FROM shonu_subjects WHERE mobile = '$username'";
            $samasyephalitansa = $conn->query($samasye);
            $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
            if ($samasyephalitansa_dhadi == 0) {
                $samasye = "SELECT code FROM shonu_subjects WHERE owncode = '".$invitecode."'";
                $samasyephalitansa = $conn->query($samasye);
                $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
                if ($samasyephalitansa_dhadi == 1) {
                    $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);
                    $code1 = $samasyephalitansa_sreni['code'];
                    $samasye = "SELECT code FROM shonu_subjects WHERE owncode = '".$code1."'";
                    $samasyephalitansa = $conn->query($samasye);
                    $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
                    if ($samasyephalitansa_dhadi == 1) {
                        $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);
                        $code2 = $samasyephalitansa_sreni['code'];
                        $samasye = "SELECT code FROM shonu_subjects WHERE owncode = '".$code2."'";
                        $samasyephalitansa = $conn->query($samasye);
                        $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
                        if ($samasyephalitansa_dhadi == 1) {
                            $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);
                            $code3 = $samasyephalitansa_sreni['code'];
                            $samasye = "SELECT code FROM shonu_subjects WHERE owncode = '".$code3."'";
                            $samasyephalitansa = $conn->query($samasye);
                            $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
                            if ($samasyephalitansa_dhadi == 1) {
                                $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);
                                $code4 = $samasyephalitansa_sreni['code'];
                                $samasye = "SELECT code FROM shonu_subjects WHERE owncode = '".$code4."'";
                                $samasyephalitansa = $conn->query($samasye);
                                $samasyephalitansa_dhadi = mysqli_num_rows($samasyephalitansa);
                                if ($samasyephalitansa_dhadi == 1) {
                                    $samasyephalitansa_sreni = mysqli_fetch_array($samasyephalitansa);
                                    $code5 = $samasyephalitansa_sreni['code'];
                                } else {
                                    $code5 = null;
                                }
                            } else {
                                $code4 = null;
                                $code5 = null;
                            }
                        } else {
                            $code3 = null;
                            $code4 = null;
                            $code5 = null;
                        }
                    } else {
                        $code2 = null;
                        $code3 = null;
                        $code4 = null;
                        $code5 = null;
                    }
                } else {
                    $code1 = null;
                    $code2 = null;
                    $code3 = null;
                    $code4 = null;
                    $code5 = null;
                }
                
                function generateRandomNumber() {
                    return mt_rand(100000000000, 999999999999);
                }
                function checkNumberExists($conn, $number) {
                    try {
                        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM shonu_subjects WHERE owncode = ?");
                        $stmt->bind_param("s", $number);
                        $stmt->execute();
                        $stmt->bind_result($count);
                        $stmt->fetch();
                        $stmt->close();
                        return $count > 0;
                    } catch (mysqli_sql_exception $e) {
                    }
                    return false;
                }
                
                do {
                    $codethiefstfu = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                } while (checkNumberExists($conn, $codethiefstfu));
                $query = "SELECT id FROM shonu_subjects ORDER BY id DESC LIMIT 1";
                $result = mysqli_query($conn, $query);
                $row = mysqli_fetch_assoc($result);
                $nextId = isset($row['id']) ? $row['id'] + 1 : 1;
                $nextId = str_pad($nextId, 6, '0', STR_PAD_LEFT);
                $owncode = $codethiefstfu . $nextId;
                
                $shnunc = date("Y-m-d H:i:s");
                
                function generateUniqueString($length = 8) {
                    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $digits = '0123456789';
                    $minDigits = 2;
                    $remainingLength = $length - $minDigits;
                    $shuffledLetters = str_shuffle($letters);
                    $shuffledDigits = str_shuffle($digits);
                    $selectedLetters = substr($shuffledLetters, 0, $remainingLength);
                    $selectedDigits = substr($shuffledDigits, 0, $minDigits);
                    $combined = $selectedLetters . $selectedDigits;
                    return 'Member' . str_shuffle($combined);
                }
                $codechorkamukala = generateUniqueString();
                $password = md5($pwd);
                
                $tathya = mysqli_query($conn, "INSERT INTO `shonu_subjects` (`mobile`,`email`,`password`,`code`,`code1`,`code2`,`code3`,`code4`,`code5`,`owncode`,`privacy`,`status`,`createdate`,`ip`,`ishonup`,`pwd`,`shonullgnt`,`tnegaresunohs`,`codechorkamukala`) VALUES ('".$username."','','".$password."','".$invitecode."','".$code1."','".$code2."','".$code3."','".$code4."','".$code5."','".$owncode."','on','1','".$shnunc."','".$ipaddress."','".$ipaddress."','".$pwd."','".$shnunc."','".$user_agent."','".$codechorkamukala."' )");

                $last_id = $conn->insert_id;

                $status = 1;
                $expiresIn = time() + 86400;

                // ===== UID GENERATE =====
                $uid = str_pad($last_id, 6, '0', STR_PAD_LEFT);

                // ===== JWT PAYLOAD =====
                $shnutkn_load = [
                    'id' => $last_id,
                    'uid' => $uid,
                    'mobile' => $username,
                    'status' => $status,
                    'expire' => $expiresIn,
                    'ishonup' => $ipaddress,
                    'codechorkamukala' => $codechorkamukala
                ];
                
                // --- Give new user spins from invitewheel_setting ---
                $settingsQuery = mysqli_query($conn, "SELECT start_spin FROM invitewheel_setting WHERE id = 1");
                if ($settingsQuery && mysqli_num_rows($settingsQuery) > 0) {
                    $settingsData = mysqli_fetch_assoc($settingsQuery);
                    $startSpinValue = (int)$settingsData['start_spin'];
                } else {
                    $startSpinValue = 2;
                    mysqli_query($conn, "INSERT INTO invitewheel_setting (start_spin) VALUES ('$startSpinValue') ON DUPLICATE KEY UPDATE start_spin = '$startSpinValue'");
                }
                
                $checkTurntable = mysqli_query($conn, "SELECT user_id FROM shonu_turntable WHERE user_id = '$last_id'");
                if (mysqli_num_rows($checkTurntable) == 0) {
                    $insertQuery = "INSERT INTO `shonu_turntable` (`user_id`, `total_spins`, `invited_wheel_amount`, `created_at`, `updated_at`) 
                                    VALUES ('$last_id', '$startSpinValue', '0.00', '$shnunc', '$shnunc')";
                
                    if(mysqli_query($conn, $insertQuery)) {
                        error_log("New user $last_id given $startSpinValue spins");
                    } else {
                        error_log("Error inserting turntable for user $last_id: " . mysqli_error($conn));
                    }
                }
                
                $status = 1;
                $expiresIn = time() + 86400;
                $shnutkn_head = ['alg' => 'HS256', 'typ' => 'JWT'];
                $shnutkn_load = [
                    'id' => $last_id,
                    'mobile' => $username,
                    'status' => $status,
                    'expire' => $expiresIn,
                    'ishonup' => $ipaddress,
                    'codechorkamukala' => $codechorkamukala
                ];
                
                // Fetch register_bonus value from web_setting table
                $minRechargeQuery = "SELECT register_bonus FROM web_setting LIMIT 1";
                $minRechargeResult = $conn->query($minRechargeQuery);

                if ($minRechargeResult && $minRechargeResult->num_rows > 0) { 
                    $row = $minRechargeResult->fetch_assoc(); 
                    $amount = $row['register_bonus']; 
                } else { 
                    $amount = 0; 
                }
                
                $akshinak = generate_jwt($shnutkn_head, $shnutkn_load);
                
                $pwderrsql = "UPDATE shonu_subjects SET akshinak='".$akshinak."' WHERE id='$last_id'";
                $conn->query($pwderrsql);
                
                $tathya = mysqli_query($conn, "INSERT INTO `shonu_kaichila` (`balakedara`,`motta`,`bonus`,`dinankavannuracisi`) VALUES ('".$last_id."','".$amount."','".$amount."','".$shnunc."')");
                
                $res['data']['tokenHeader'] = 'Bearer ';
                $res['data']['token'] = $akshinak;
                $res['code'] = 0;
                $res['msg'] = 'Succeed';
                $res['msgCode'] = 0;
                $res['serviceNowTime'] = $shnunc;
                http_response_code(200);
                echo json_encode($res);
            } else {
                $res['code'] = 1;
                $res['msg'] = 'Phone number have been registered';
                $res['msgCode'] = 111;
                http_response_code(200);
                echo json_encode($res);
            }
        } else {
            $res['code'] = 8;
            $res['msg'] = 'Invitor Not Existed';
            $res['msgCode'] = 110;
            http_response_code(200);
            echo json_encode($res);
        }
    } else {
        http_response_code(405);
        echo json_encode($res);
    }
}
?>