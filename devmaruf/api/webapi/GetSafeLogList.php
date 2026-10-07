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
header('Vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");
$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $author = $bearer[1] ?? '';
    if (empty($author)) {
        $res['code'] = 4;
        $res['msg'] = 'JWT token missing';
        $res['msgCode'] = 2;
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

    $is_jwt_valid = is_jwt_valid($author);
    $data_auth = json_decode($is_jwt_valid, true);
    if ($data_auth['status'] === 'Success') {
        $sesquery = "SELECT id FROM shonu_subjects WHERE akshinak = '$author'";
        $sesresult = $conn->query($sesquery);
        $sesnum = mysqli_num_rows($sesresult);
        if ($sesnum == 1) {
            $user = mysqli_fetch_assoc($sesresult);
            $user_id = mysqli_real_escape_string($conn, $user['id']);

            $shonubody = file_get_contents("php://input");
            error_log("GetSafeLogList Request Body: " . $shonubody);
            $shonupost = json_decode($shonubody, true);
            $page_no = isset($shonupost['pageNo']) ? intval($shonupost['pageNo']) : 1;

            $page_size = 10;
            $offset = ($page_no - 1) * $page_size;

            $list_query = "SELECT transaction_date, amount, order_num, day_share_rate, safe_earnings, earnings, type 
                           FROM safe_box 
                           WHERE user_id = '$user_id' 
                           ORDER BY transaction_date DESC 
                           LIMIT $offset, $page_size";
            $list_result = $conn->query($list_query);
            if (!$list_result) {
                $res['code'] = 1;
                $res['msg'] = "Failed to fetch safe log list: " . mysqli_error($conn);
                $res['msgCode'] = 1;
                http_response_code(500);
                echo json_encode($res);
                exit();
            }

            $transactions = [];
            while ($row = mysqli_fetch_assoc($list_result)) {
                $order_num = $row['order_num'];
                if (empty($order_num)) {
                    $order_num = date("YmdHis", strtotime($row['transaction_date'])) . rand(100, 999);
                    $update_query = "UPDATE safe_box 
                                     SET order_num = '$order_num' 
                                     WHERE user_id = '$user_id' AND transaction_date = '{$row['transaction_date']}' AND amount = '{$row['amount']}'";
                    $conn->query($update_query);
                }

                $transactions[] = [
                    "addTime" => $row['transaction_date'],
                    "type" => (int)$row['type'],
                    "dayShareRate" => floatval(number_format($row['day_share_rate'], 5, '.', '')),
                    "orderNum" => $order_num,
                    "safeEarnings" => $row['safe_earnings'] !== null ? floatval(number_format($row['safe_earnings'], 2, '.', '')) : null,
                    "earnings" => floatval(number_format($row['earnings'], 2, '.', '')),
                    "amount" => number_format(abs($row['amount']), 2, '.', '')
                ];
            }

            $deposit_query = "SELECT SUM(amount) as total_deposits 
                              FROM safe_box 
                              WHERE user_id = '$user_id' AND amount > 0";
            $deposit_result = $conn->query($deposit_query);
            $total_deposits = $deposit_result ? (mysqli_fetch_assoc($deposit_result)['total_deposits'] ?? 0.0) : 0.0;

            $withdrawal_query = "SELECT SUM(amount) as total_withdrawals 
                                 FROM safe_box 
                                 WHERE user_id = '$user_id' AND amount < 0";
            $withdrawal_result = $conn->query($withdrawal_query);
            $total_withdrawals = $withdrawal_result ? abs(mysqli_fetch_assoc($withdrawal_result)['total_withdrawals'] ?? 0.0) : 0.0;

            $revenue_query = "SELECT SUM(amount) as total_safe 
                              FROM safe_box 
                              WHERE user_id = '$user_id'";
            $revenue_result = $conn->query($revenue_query);
            $total_revenue = $revenue_result ? (mysqli_fetch_assoc($revenue_result)['total_safe'] ?? 0.0) : 0.0;

            $total_query = "SELECT COUNT(*) as total_count 
                            FROM safe_box 
                            WHERE user_id = '$user_id'";
            $total_result = $conn->query($total_query);
            $total_data = mysqli_fetch_assoc($total_result);
            $total_count = $total_data['total_count'] ?? 0;
            $total_pages = $total_count > 0 ? ceil($total_count / $page_size) : 0;

            $data = [
                // 'extend1' => floatval(number_format($total_deposits, 2, '.', '')),
                // 'extend2' => floatval(number_format($total_withdrawals, 2, '.', '')),
                // 'extend3' => floatval(number_format($total_revenue, 2, '.', '')),
                
                'extend1' => floatval(number_format($total_revenue, 2, '.', '')),
                'extend2' => floatval(number_format($total_deposits, 2, '.', '')),
                'extend3' => floatval(number_format($total_withdrawals, 2, '.', '')),
                
                'list' => $transactions,
                'pageNo' => $page_no,
                'totalPage' => $total_pages,
                'totalCount' => $total_count
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
    $res['code'] = 11;
    $res['msg'] = 'Method not allowed';
    $res['msgCode'] = 12;
    http_response_code(405);
    echo json_encode($res);
}

mysqli_close($conn);
?>