<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../../conn.php";

if (!isset($_POST['userId'])) {
    echo json_encode([
        "code" => 7,
        "msg" => "Param is Invalid",
        "msgCode" => 6,
        "serviceNowTime" => date("Y-m-d H:i:s")
    ]);
    exit;
}

$userId = intval($_POST['userId']);

// check if first time
$sql = "SELECT * FROM invited_wheel WHERE userId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    $amounts = [1.0, 2.0, 5.0, 10.0];
    shuffle($amounts);

    $initAmount = $amounts[0];

    $expired = date("Y-m-d H:i:s", strtotime("+2 days"));

    $ins = $conn->prepare("INSERT INTO invited_wheel (userId, amount, spin_count, is_first, expired_time) VALUES (?, ?, 0, 1, ?)");
    $ins->bind_param("ids", $userId, $initAmount, $expired);
    $ins->execute();

    $response = [
        "data" => [
            "userId" => $userId,
            "randomBox" => $amounts,
            "givenAmount" => $initAmount
        ],
        "code" => 0,
        "msg" => "First Open Success",
        "msgCode" => 0,
        "serviceNowTime" => date("Y-m-d H:i:s")
    ];
} else {
    $response = [
        "code" => 9,
        "msg" => "Already Opened",
        "msgCode" => 8,
        "serviceNowTime" => date("Y-m-d H:i:s")
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>