<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
include "../../conn.php"; // Points to: mysqli connection
include "../../functions2.php"; // Points to: is_jwt_valid function

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");
$tomorrow = date("Y-m-d", strtotime("+1 day"));
$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

if ($_SERVER['REQUEST_METHOD'] != 'GET' && isset($shonupost['language']) && isset($shonupost['random']) && isset($shonupost['signature']) && isset($shonupost['timestamp'])) {
    $language = mysqli_real_escape_string($conn, $shonupost['language']);
    $random = mysqli_real_escape_string($conn, $shonupost['random']);
    $signature = mysqli_real_escape_string($conn, $shonupost['signature']);
    $shonustr = '{"language":'.$language.',"random":"'.$random.'"}';
    $shonusign = strtoupper(md5($shonustr));
    if ($shonusign == $signature) {
        $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION'])[1] ?? '';
        $jwt_data = json_decode(is_jwt_valid($bearer), true); // Points to: shonu_subjects (akshinak)
        if ($jwt_data['status'] === 'Success') {
            $user_query = "SELECT id FROM shonu_subjects WHERE akshinak = '$bearer'"; // Points to: shonu_subjects
            $user_result = $conn->query($user_query);
            if (mysqli_num_rows($user_result) == 1) {
                $user = mysqli_fetch_assoc($user_result);
                $user_id = mysqli_real_escape_string($conn, $user['id']);

                $safe_query = "SELECT SUM(amount) as safe_balance FROM safe_box WHERE user_id = '$user_id'"; // Points to: safe_box
                $safe_result = $conn->query($safe_query);
                $safe_amount = mysqli_fetch_assoc($safe_result)['safe_balance'] ?? 0.00;

                $today = date("Y-m-d");
                $today_deposit_query = "SELECT SUM(amount) as today_deposits FROM safe_box WHERE user_id = '$user_id' AND amount > 0 AND DATE(transaction_date) = '$today'"; // Points to: safe_box
                $today_deposit_result = $conn->query($today_deposit_query);
                $today_deposits = mysqli_fetch_assoc($today_deposit_result)['today_deposits'] ?? 0.00;

                $day_share_rate = 0.001;
                $safe_earnings = ($safe_amount - $today_deposits) > 0 ? ($safe_amount - $today_deposits) * $day_share_rate : 0.001;
                $will_safe_earnings = $safe_amount * $day_share_rate;

                $data = [
                    'state' => '1',
                    'shareTime' => 1,
                    'dayShareRate' => $day_share_rate,
                    'userDayShareRate' => $day_share_rate,
                    'safeAmount' => number_format($safe_amount, 2, '.', ''),
                    'safeEarnings' => number_format($safe_earnings, 2, '.', ''),
                    'willSafeEarnings' => number_format($will_safe_earnings, 2, '.', ''),
                    'safeTotalAmount' => number_format($safe_amount + $safe_earnings, 2, '.', ''),
                    'shareAmount' => "100",
                    'safeBoxCodeAmount' => "0",
                    'isOpenNewSetting' => "0",
                    'maxSafeAmount' => null
                ];

                echo json_encode([
                    "data" => $data,
                    "code" => 0,
                    "msg" => "Succeed",
                    "msgCode" => 0,
                    "serviceNowTime" => $shnunc,
                    "projectedDate" => $tomorrow
                ]);
            }
        }
    }
}
mysqli_close($conn);
?>