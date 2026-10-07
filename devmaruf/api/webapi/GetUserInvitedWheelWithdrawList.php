<?php
include "../../conn.php";
include "../../functions2.php";

header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');

// Allow any origin (CORS)
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Vary: Origin');

date_default_timezone_set("Asia/Dhaka");
$shnunc = date("Y-m-d H:i:s");

$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $res['code'] = 4;
        $res['msg'] = 'Authorization header missing';
        $res['msgCode'] = 2;
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

    $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
    $author = $bearer[1] ?? '';

    if (empty($author)) {
        $res['code'] = 4;
        $res['msg'] = 'Token missing';
        $res['msgCode'] = 2;
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

    $is_jwt_valid = is_jwt_valid($author);
    $data_auth = json_decode($is_jwt_valid, true);
    if ($data_auth && isset($data_auth['status']) && $data_auth['status'] === 'Success') {

        $userId = $data_auth['payload']['id'];

        $shonubody = file_get_contents("php://input");
        $shonupost = json_decode($shonubody, true);

        $pageNo   = isset($shonupost['pageNo']) ? max(1, (int)$shonupost['pageNo']) : 1;
        $pageSize = isset($shonupost['pageSize']) ? max(1, (int)$shonupost['pageSize']) : 10;
        $offset   = ($pageNo - 1) * $pageSize;

        try {
            // ১. টেবিলের নাম পরিবর্তন করে shonu_withdrawals করা হয়েছে
            $countQuery = "
                SELECT COUNT(*) AS total 
                FROM shonu_withdrawals 
                WHERE user_id = ? 
                  AND withdrawal_type = 'turntable'
            ";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->bind_param("s", $userId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalCount = ($countResult && $row = $countResult->fetch_assoc()) ? (int)$row['total'] : 0;
            $totalPage  = $pageSize > 0 ? ceil($totalCount / $pageSize) : 0;

            $list = [];

            if ($totalCount > 0) {
                // ২. টেবিলের নাম shonu_withdrawals এবং sthithi এর বদলে status কলাম ব্যবহার করা হয়েছে
                $query = "
                    SELECT 
                        id,
                        user_id,
                        withdrawal_type,
                        amount,
                        status,
                        created_at
                    FROM shonu_withdrawals
                    WHERE user_id = ?
                      AND withdrawal_type = 'turntable'
                    ORDER BY created_at DESC
                    LIMIT ?, ?
                ";

                $stmt = $conn->prepare($query);
                $stmt->bind_param("sii", $userId, $offset, $pageSize);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        
                        // ৩. আপনার সাবমিট করা 'status' টেক্সট ভ্যালু অনুযায়ী ম্যাপ করা হয়েছে
                        $auditState = 1; // Default Pending
                        $reason = 'Withdrawal pending';
                        
                        if ($row['status'] === 'completed' || $row['status'] === 'approved') {
                            $auditState = 2; // Approved/Completed
                            $reason = 'Withdrawal successful';
                        } elseif ($row['status'] === 'rejected' || $row['status'] === 'failed') {
                            $auditState = 3; // Rejected
                            $reason = 'Withdrawal rejected';
                        }
                        
                        $list[] = [
                            'orderNo'        => (string)$row['id'],
                            'userId'         => (int)$row['user_id'],
                            'withdrawAmount' => (float)$row['amount'],
                            'auditState'     => $auditState,
                            'createTime'     => $row['created_at'],
                            'reason'         => $reason
                        ];
                    }
                }
            }

            // ----------------- FINAL RESPONSE -----------------
            $res = [
                'code' => 0,
                'msg'  => 'Success',
                'msgCode' => 0,
                'serviceNowTime' => $shnunc,
                'data' => [
                    'list'       => $list,
                    'pageNo'     => $pageNo,
                    'totalPage'  => $totalPage,
                    'totalCount' => $totalCount
                ]
            ];

            echo json_encode($res, JSON_NUMERIC_CHECK);
            exit();

        } catch (Exception $e) {
            error_log("Withdrawal history error: " . $e->getMessage());
            $res['code'] = 500;
            $res['msg'] = 'Database error: ' . $e->getMessage();
            $res['msgCode'] = 500;
            http_response_code(500);
            echo json_encode($res);
            exit();
        }

    } else {
        $res['code'] = 4;
        $res['msg'] = 'Invalid or expired token';
        $res['msgCode'] = 2;
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
} else {
    $res['code'] = 11;
    $res['msg'] = 'Method not allowed';
    $res['msgCode'] = 12;
    http_response_code(405);
    echo json_encode($res);
    exit();
}
?>