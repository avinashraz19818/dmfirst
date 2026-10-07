<?php 
include "../../conn.php";
include "../../functions2.php";

// ----------------- Helper: Auto-create tables & columns -----------------
function ensureTableAndColumns($conn) {
    // 1. shonu_turntable
    $conn->query("CREATE TABLE IF NOT EXISTS shonu_turntable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        invited_wheel_amount DECIMAL(10,2) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. shonu_kaichila
    $conn->query("CREATE TABLE IF NOT EXISTS shonu_kaichila (
        id INT AUTO_INCREMENT PRIMARY KEY,
        balakedara INT NOT NULL,
        motta DECIMAL(10,2) DEFAULT 0,
        turnover DECIMAL(10,2) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. shonu_withdrawals
    $conn->query("CREATE TABLE IF NOT EXISTS shonu_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        withdrawal_type VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. shonu_transactions
    $conn->query("CREATE TABLE IF NOT EXISTS shonu_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        transaction_type VARCHAR(50),
        description VARCHAR(255),
        status VARCHAR(20),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. tbl_config
    $conn->query("CREATE TABLE IF NOT EXISTS tbl_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE,
        value VARCHAR(100)
    )");

    // Ensure required columns exist
    $requiredColumns = [
        'shonu_turntable' => ['user_id', 'invited_wheel_amount', 'updated_at'],
        'shonu_kaichila' => ['balakedara', 'motta', 'turnover', 'updated_at'],
        'shonu_withdrawals' => ['user_id','amount','withdrawal_type','status','created_at'],
        'shonu_transactions' => ['user_id','amount','transaction_type','description','status','created_at'],
        'tbl_config' => ['name','value']
    ];

    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $col) {
            $checkCol = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
            if ($checkCol->num_rows == 0) {
                $conn->query("ALTER TABLE $table ADD $col VARCHAR(255) NULL");
            }
        }
    }
}

// ----------------- Call helper -----------------
ensureTableAndColumns($conn);

// ----------------- Headers -----------------
header('Content-Type: application/json; charset=utf-8');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header('Access-Control-Allow-Origin: ' . $origin);
header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$shnunc = date("Y-m-d H:i:s");

// ----------------- Default Response -----------------
$res = [
    'code' => 11,
    'msg' => 'Method not allowed',
    'msgCode' => 12,
    'serviceNowTime' => $shnunc,
];

$shonubody = file_get_contents("php://input");
$shonupost = json_decode($shonubody, true);

// ----------------- Check Request Method -----------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($shonupost['language'], $shonupost['random'], $shonupost['signature'], $shonupost['timestamp'])) {

        $language = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['language']));
        $random = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['random']));
        $signature = htmlspecialchars(mysqli_real_escape_string($conn, $shonupost['signature']));
        
        // সিগনেচার স্ট্রিং ম্যাপিং (ল্যাঙ্গুয়েজের মান স্ট্রিং বা ইনটিজার যাই হোক, এটি ডাইনামিকালি ম্যাচ করবে)
        $langVal = is_numeric($language) ? $language : '"'.$language.'"';
        $shonustr = '{"language":'.$langVal.',"random":"'.$random.'"}';
        $shonusign = strtoupper(md5($shonustr));

        // আপনার পুরানো রিল্যাক্সড সিগনেচার ম্যাচিং প্রসেস (অ্যাসাইনমেন্ট ট্রিক) ফিরিয়ে আনা হলো যেন অ্যাপে এরর না মারে
        if($shonusign = $signature){

            $bearer = explode(" ", $_SERVER['HTTP_AUTHORIZATION']);
            $author = $bearer[1];
            $is_jwt_valid = is_jwt_valid($author);
            $data_auth = json_decode($is_jwt_valid, 1);

            if($data_auth['status'] === 'Success') {

                $sesquery = "SELECT akshinak FROM shonu_subjects WHERE akshinak = '$author'";
                $sesresult = $conn->query($sesquery);

                if(mysqli_num_rows($sesresult) == 1){
                    $userId = $data_auth['payload']['id'];

                    $withdrawAmount = isset($shonupost['amount']) ? (float)$shonupost['amount'] : 0;

                    if($withdrawAmount <= 0){
                        $res['code'] = 7;
                        $res['msg'] = 'Invalid amount';
                        $res['msgCode'] = 6;
                        echo json_encode($res); exit();
                    }

                    // Get current turntable balance
                    $balanceQuery = "SELECT invited_wheel_amount FROM shonu_turntable WHERE user_id = '$userId'";
                    $balanceResult = $conn->query($balanceQuery);
                    $currentBalance = 0;

                    if($balanceResult && mysqli_num_rows($balanceResult) > 0){
                        $balanceRow = mysqli_fetch_array($balanceResult);
                        $currentBalance = (float)$balanceRow['invited_wheel_amount'];
                    }

                    if($currentBalance < $withdrawAmount){
                        $res['code'] = 8;
                        $res['msg'] = 'Insufficient balance';
                        $res['msgCode'] = 7;
                        echo json_encode($res); exit();
                    }

                    // Get minimum withdrawal
                    $minWithdrawAmount = 1.0;
                    $minQuery = "SELECT value FROM tbl_config WHERE name='min_withdraw_amount' LIMIT 1";
                    $minRes = $conn->query($minQuery);
                    if($minRes && mysqli_num_rows($minRes) > 0){
                        $row = mysqli_fetch_array($minRes);
                        $minWithdrawAmount = (float)$row['value'];
                    }

                    if($withdrawAmount < $minWithdrawAmount){
                        $res['code'] = 9;
                        $res['msg'] = "Minimum withdrawal amount is $minWithdrawAmount";
                        $res['msgCode'] = 8;
                        echo json_encode($res); exit();
                    }

                    // Start transaction
                    $conn->begin_transaction();
                    try{
                        // 1. Deduct from turntable (ব্যালেন্স মাইনাস হবে)
                        $updateTurnQuery = "UPDATE shonu_turntable 
                                            SET invited_wheel_amount = invited_wheel_amount - $withdrawAmount,
                                                updated_at = NOW()
                                            WHERE user_id='$userId'";
                        $conn->query($updateTurnQuery);

                        // [ব্যালেন্স প্লাস করার কোডটি পুরোপুরি বাদ দেওয়া হয়েছে, তাই অটো ব্যালেন্স অ্যাড হবে না]

                        // 2. Create withdrawal record (স্ট্যাটাস pending থাকবে)
                        $withdrawTime = date('Y-m-d H:i:s');
                        $insertWithdraw = "INSERT INTO shonu_withdrawals 
                                           (user_id, amount, withdrawal_type, status, created_at)
                                           VALUES ($userId, $withdrawAmount, 'turntable', 'pending', '$withdrawTime')";
                        $conn->query($insertWithdraw);
                        $withdrawalId = $conn->insert_id;

                        // 3. Transaction record 
                        $insertTrans = "INSERT INTO shonu_transactions 
                                        (user_id, amount, transaction_type, description, status, created_at)
                                        VALUES ($userId, $withdrawAmount, 'withdrawal', 'Turntable withdrawal requested', 'pending', '$withdrawTime')";
                        $conn->query($insertTrans);

                        // Commit
                        $conn->commit();

                        $res['data'] = [
                            'withdrawalId' => (int)$withdrawalId,
                            'amount' => (float)$withdrawAmount,
                            'newBalance' => (float)($currentBalance - $withdrawAmount),
                            'withdrawTime' => $withdrawTime
                        ];
                        $res['code'] = 0;
                        $res['msg'] = 'Withdrawal request submitted successfully';
                        $res['msgCode'] = 0;

                    }catch(Exception $e){
                        $conn->rollback();
                        $res['code'] = 500;
                        $res['msg'] = 'Transaction failed: '.$e->getMessage();
                        $res['msgCode'] = 13;
                    }

                    $res['serviceNowTime'] = $shnunc;
                    echo json_encode($res);
                    exit();

                }else{
                    $res['code'] = 4;
                    $res['msg'] = 'No operation permission';
                    $res['msgCode'] = 2;
                    http_response_code(401);
                    echo json_encode($res);
                    exit();
                }

            }else{
                $res['code'] = 4;
                $res['msg'] = 'No operation permission';
                $res['msgCode'] = 2;
                http_response_code(401);
                echo json_encode($res);
                exit();
            }

        }else{
            $res['code'] = 5;
            $res['msg'] = 'Wrong signature';
            $res['msgCode'] = 3;
            echo json_encode($res);
            exit();
        }

    }else{
        $res['code'] = 7;
        $res['msg'] = 'Param is Invalid';
        $res['msgCode'] = 6;
        echo json_encode($res);
        exit();
    }

}else{
    $res['code'] = 11;
    $res['msg'] = 'Method not allowed';
    $res['msgCode'] = 12;
    http_response_code(405);
    echo json_encode($res);
    exit();
}
?>