<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");

// Raw JSON Input রিসিভ করা হচ্ছে
$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if (isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
    
    $rawLanguage = $shonupost['language']; // Integer বা String যেভাবে এসেছে
    $rawRandom = (string)$shonupost['random'];
    $client_signature = htmlspecialchars(mysqli_real_escape_string($conn, (string)$shonupost['signature']));

    // সিগনেচার ভ্যালিডেশনের জন্য স্ট্রিং তৈরি (আগের ফিক্স অনুযায়ী)
    if (is_numeric($rawLanguage)) {
        $shonustr = '{"language":' . $rawLanguage . ',"random":"' . $rawRandom . '"}';
    } else {
        $shonustr = '{"language":"' . $rawLanguage . '","random":"' . $rawRandom . '"}';
    }
    
    $server_signature = strtoupper(md5($shonustr));

    // সিগনেচার ম্যাচিং চেক
    if ($server_signature == $client_signature) {
        
        // Authorization token নিষ্কাশন
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
        }
        
        $author = htmlspecialchars(mysqli_real_escape_string($conn, (string)$author));
        $is_jwt_valid = is_jwt_valid($author);
        $data_auth = json_decode($is_jwt_valid, true);

        if (isset($data_auth['status']) && $data_auth['status'] === 'Success') {
            
            $user_token = mysqli_real_escape_string($conn, $author);
            $query1 = "SELECT id FROM shonu_subjects WHERE akshinak = '$user_token' LIMIT 1";
            $result1 = mysqli_query($conn, $query1);
            $user_data = mysqli_fetch_assoc($result1);

            if (!$user_data) {
                http_response_code(200);
                echo json_encode(["code" => 1, "msg" => "User not found"]);
                exit();
            }
            
            $user_id = mysqli_real_escape_string($conn, $user_data['id']);

            // Step 1: Fetch total amount transferred to safe box
            $safe_query = "SELECT SUM(amount) as safeAmount FROM safe_box WHERE user_id = '$user_id'";
            $safe_result = mysqli_query($conn, $safe_query);
            if (!$safe_result) {
                http_response_code(200);
                echo json_encode(["code" => 1, "msg" => "Query failed: " . mysqli_error($conn)]);
                exit();
            }
            $safeAmount = mysqli_fetch_assoc($safe_result)['safeAmount'] ?? 0;

            // Step 2: Define daily interest rate
            $dayShareRate = 0.005; // 0.5%

            // Step 3: Calculate daily income
            $safeEarnings = $safeAmount * $dayShareRate;

            // Step 4: Calculate total withdrawable amount
            $balance = $safeAmount + $safeEarnings;

            // Response
            $response = [
                "data" => [
                    "balance" => (float)$balance,
                    "transferredAmount" => (float)$safeAmount,
                    "dayShareRate" => $dayShareRate,
                    "safeEarnings" => (float)$safeEarnings
                ],
                "code" => 0,
                "msg" => "Succeed",
                "msgCode" => 0,
                "serviceNowTime" => date("Y-m-d H:i:s")
            ];
            http_response_code(200);
            echo json_encode($response);
        } else {
            http_response_code(401);
            echo json_encode(["code" => 1, "msg" => "Unauthorized"]);
        }
    } else {
        // সিগনেচার অমিল হলে ডিবাগ ডাটা সহ রেসপন্স
        http_response_code(200);
        echo json_encode([
            "code" => 5,
            "msg" => "Wrong signature",
            "msgCode" => 3,
            "serviceNowTime" => date("Y-m-d H:i:s"),
            "debug" => [
                "server_expected_string" => $shonustr,
                "server_generated_md5" => $server_signature,
                "client_sent_signature" => $client_signature
            ]
        ]);
    }
} else {
    http_response_code(200);
    echo json_encode(["code" => 7, "msg" => "Param is Invalid", "msgCode" => 6]);
}

mysqli_close($conn);
?>