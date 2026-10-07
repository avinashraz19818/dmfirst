<?php
// প্রয়োজনীয় ফাইল ইনক্লুড করা হচ্ছে
include "../../conn.php";
include "../../functions2.php";

// রেসপন্স হেডার সেট করা (JSON আউটপুট এবং সিকিউরিটির জন্য)
header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

// টাইমজোন এবং বর্তমান সময় সেট করা
date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

// ডিফল্ট রেসপন্স (যদি রিকোয়েস্ট মেথড POST না হয়)
$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

// শুধুমাত্র POST রিকোয়েস্ট অ্যালাউ করা হচ্ছে
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // ১. অথরাইজেশন হেডার চেক
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $res = [
            'code' => 4,
            'msg' => 'Authorization header missing',
            'msgCode' => 2,
            'serviceNowTime' => $shnunc
        ];
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

    // Authorization হেডার থেকে টোকেন আলাদা করা
    $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
    $author = $bearer[1] ?? '';
    
    // টোকেন খালি কিনা চেক
    if (empty($author)) {
        $res = [
            'code' => 4,
            'msg' => 'Token missing',
            'msgCode' => 2,
            'serviceNowTime' => $shnunc
        ];
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

    // ২. JWT টোকেন ভ্যালিডেশন
    $is_jwt_valid = is_jwt_valid($author);
    $data_auth = json_decode($is_jwt_valid, true);

    // টোকেন যদি সাকসেসফুল বা সঠিক হয়
    if($data_auth && isset($data_auth['status']) && $data_auth['status'] === 'Success') {

        $userId = $data_auth['payload']['id'];

        // রিকোয়েস্ট বডি (JSON) রিড করা
        $shonubody = file_get_contents("php://input");
        $shonupost = json_decode($shonubody, true);

        // পেজিনেশন হ্যান্ডেলিং (pageNo এবং pageSize সেট করা)
        $pageNo = isset($shonupost['pageNo']) ? max(1, (int)$shonupost['pageNo']) : 1;
        $pageSize = isset($shonupost['pageSize']) ? max(1, (int)$shonupost['pageSize']) : 10;
        $offset = ($pageNo - 1) * $pageSize;

        try {
            // ৩. মোট রেকর্ডের সংখ্যা গণনা (Total Count)
            $countQuery = "SELECT COUNT(*) AS total FROM reward_center WHERE user_id=?";
            $countStmt = $conn->prepare($countQuery);
            
            // নোট: আপনার user_id যদি ডাটাবেজে INT বা সংখ্যা হয়, তবে "s" এর বদলে "i" ব্যবহার করবেন
            $countStmt->bind_param("s", $userId); 
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalCount = ($countResult && $row = $countResult->fetch_assoc()) ? (int)$row['total'] : 0;
            $totalPage = $pageSize > 0 ? ceil($totalCount / $pageSize) : 0;

            $list = [];
            // যদি রেকর্ড থাকে, তবেই মেইন কুয়েরি রান হবে
            if ($totalCount > 0) {
                $query = "SELECT receiveTaskId, vipLevel, vipRewardType, amount, rewardType, createTime, receiveStatus, recordType, maxRewardableAmount, maxRewardableRate FROM reward_center WHERE user_id=? ORDER BY createTime DESC LIMIT ?, ?";
                $stmt = $conn->prepare($query);
                
                // এখানে user_id এর জন্য "s" এবং offset ও pageSize এর জন্য "ii" ব্যবহার করা হয়েছে
                $stmt->bind_param("sii", $userId, $offset, $pageSize);
                $stmt->execute();
                $result = $stmt->get_result();

                if($result && $result->num_rows > 0){
                    while($row = $result->fetch_assoc()){
                        $list[] = [
                            'receiveTaskId' => $row['receiveTaskId'],
                            'vipLevel' => $row['vipLevel'],
                            'vipRewardType' => $row['vipRewardType'],
                            'amount' => (int)$row['amount'],
                            'rewardType' => (int)$row['rewardType'],
                            'createTime' => $row['createTime'],
                            'receiveStatus' => (int)$row['receiveStatus'],
                            'recordType' => (int)$row['recordType'],
                            'maxRewardableAmount' => $row['maxRewardableAmount'] ? (int)$row['maxRewardableAmount'] : null,
                            'maxRewardableRate' => $row['maxRewardableRate'] ? (float)$row['maxRewardableRate'] : null
                        ];
                    }
                }
            }

            // সফলভাবে ডেটা রিটার্ন করার রেসপন্স
            $res = [
                'data' => [
                    'list' => $list,
                    'pageNo' => $pageNo,
                    'totalPage' => $totalPage,
                    'totalCount' => $totalCount
                ],
                'code' => 0,
                'msg' => 'Succeed',
                'msgCode' => 0,
                'serviceNowTime' => $shnunc
            ];

            echo json_encode($res);
            exit();

        } catch (Exception $e) {
            // ৪. ডেটাবেজে কোনো সমস্যা হলে আসল ভুলটা এখানে মেসেজে দেখা যাবে
            $res = [
                'code' => 500,
                'msg' => 'Database error: ' . $e->getMessage(), // এখানে আসল এররটি যুক্ত করা হয়েছে ট্রাবলশুটের জন্য
                'msgCode' => 500,
                'serviceNowTime' => $shnunc
            ];
            http_response_code(500);
            echo json_encode($res);
            exit();
        }

    } else {
        // টোকেন ভুল বা এক্সপায়ার হয়ে গেলে
        $res = [
            'code' => 4,
            'msg' => 'Invalid or expired token',
            'msgCode' => 2,
            'serviceNowTime' => $shnunc
        ];
        http_response_code(401);
        echo json_encode($res);
        exit();
    }

} else {
    // CORS বা OPTIONS রিকোয়েস্ট হ্যান্ডেল করার জন্য
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    // POST ছাড়া অন্য কোনো মেথড হলে 405 Method Not Allowed পাঠানো হবে
    http_response_code(405);
    echo json_encode($res);
    exit();
}
?>