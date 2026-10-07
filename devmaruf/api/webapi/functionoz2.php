<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
include "../../conn.php";

function logData($data, $type) {
    $logFile = __DIR__ . "/logs/{$type}.log";
    $logEntry = date('Y-m-d H:i:s') . ' ' . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

$request = file_get_contents('php://input');
logData(['request' => $request], 'requests');
$requestData = json_decode($request, true);
logData(['decodedRequest' => $requestData], 'decoded_requests');

if (json_last_error() !== JSON_ERROR_NONE) {
    $response = [
        'status' => '0002',
        'balance' => 0,
        'err_text' => 'Invalid JSON format'
    ];
    logData(['error' => json_last_error_msg(), 'request' => $request], 'errors');
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$response = [];
$phone = $requestData['uid'] ?? '';

if (empty($phone) || !ctype_alnum($phone) || strlen($phone) > 30) {
    $response = [
        'status' => '0001',
        'balance' => 0,
        'err_text' => 'Invalid UID'
    ];
    echo json_encode($response);
    exit();
}

// Query to get user ID by phone
$query = "SELECT id FROM shonu_subjects WHERE mobile = '$phone'";
$result = mysqli_query($conn, $query);

if ($result && $row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
} else {
    $response = [
        'status' => '0001',
        'balance' => 0,
        'err_text' => 'User not found'
    ];
    echo json_encode($response);
    exit();
}

// Define $amount globally for all cases
$amount = $requestData['amount'] ?? 0;
$bet = $requestData['bet'] ?? null;
$win = $requestData['win'] ?? null;

// Switch statement based on action
switch ($requestData['action'] ?? null) {
    case 6:
        // Case 6: Retrieve balance
        $query = "SELECT motta FROM shonu_kaichila WHERE balakedara = $id";
        $result = mysqli_query($conn, $query);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            $balance = $row['motta'];
            $response = [
                'status' => '0000',
                'balance' => $balance,
                'err_text' => ''
            ];
        } else {
            $response = [
                'status' => '0001',
                'balance' => 0,
                'err_text' => 'User not found'
            ];
        }
        break;

    case 8:
        if ($bet === null || $win === null || !is_numeric($bet) || !is_numeric($win)) {
            $response = [
                'status' => '0001',
                'balance' => 0,
                'err_text' => 'Invalid parameters'
            ];
            echo json_encode($response);
            exit();
        }

        // Check if bet has already been processed
        $checkBetQuery = "SELECT bet_status FROM shonu_kaichila WHERE balakedara = $id";
        $checkBetResult = mysqli_query($conn, $checkBetQuery);
        

        // Retrieve the current balance
        $query = "SELECT motta FROM shonu_kaichila WHERE balakedara = $id";
        $result = mysqli_query($conn, $query);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            $currentBalance = $row['motta'];
            $newBalance = $currentBalance + $win - abs($bet);

            // Update the balance
            $updateQuery = "UPDATE shonu_kaichila SET motta = $newBalance WHERE balakedara = $id";
            $updateResult = mysqli_query($conn, $updateQuery);

            // Update the 'vip' and 'shonu_kaichila' tables for expe and rebet
            $updateVipQuery = "UPDATE vip SET expe = expe + $bet WHERE userid = $id";
            $updateRebetQuery = "UPDATE shonu_kaichila SET rebet = rebet + $bet WHERE balakedara = $id";

            mysqli_query($conn, $updateVipQuery);
            mysqli_query($conn, $updateRebetQuery);

            if ($updateResult) {
                $response = [
                    'status' => '0000',
                    'balance' => $newBalance,
                    'err_text' => ''
                ];
            } else {
                $response = [
                    'status' => '0001',
                    'balance' => 0,
                    'err_text' => 'Balance update failed'
                ];
            }
        } else {
            $response = [
                'status' => '0001',
                'balance' => 0,
                'err_text' => 'User not found'
            ];
        }
        break;

    case 9:
        if ($amount <= 0) {
            $response = [
                'status' => '0001',
                'balance' => 0,
                'err_text' => 'Invalid amount'
            ];
        } else {
            $updateQuery = "UPDATE shonu_kaichila SET motta = motta - $amount WHERE balakedara = $id AND motta >= $amount";
            $updateResult = mysqli_query($conn, $updateQuery);

            // Update the 'vip' and 'shonu_kaichila' tables for expe and rebet on bet cancel
            $updateVipQuery = "UPDATE vip SET expe = expe + $amount WHERE userid = $id";
            $updateRebetQuery = "UPDATE shonu_kaichila SET rebet = rebet + $amount WHERE balakedara = $id";

            mysqli_query($conn, $updateVipQuery);
            mysqli_query($conn, $updateRebetQuery);

            if (mysqli_affected_rows($conn) > 0) {
                $query = "SELECT motta FROM shonu_kaichila WHERE balakedara = $id";
                $result = mysqli_query($conn, $query);
                $row = mysqli_fetch_assoc($result);
                $balance = $row['motta'];

                $response = [
                    'status' => '0000',
                    'balance' => $balance,
                    'err_text' => ''
                ];
            } else {
                $response = [
                    'status' => '0001',
                    'balance' => 0,
                    'err_text' => 'Insufficient balance or user not found'
                ];
            }
        }
        break;

    case 10:
        // Case 10: Add specified amount to user's balance in shonu_kaichila
        logData(['action' => $requestData['action'], 'parameters' => $requestData], 'action_logs');

        $amount = $requestData['amount'] ?? 0;

        if (!isset($id) || $amount <= 0) {
            $response = [
                'status' => '0001',
                'balance' => 0,
                'err_text' => 'Invalid parameters'
            ];
        } else {
            // Fetch user's current balance
            $query = "SELECT motta FROM shonu_kaichila WHERE balakedara = '$id'";
            $result = mysqli_query($conn, $query);

            if ($result && $row = mysqli_fetch_assoc($result)) {
                $currentBalance = $row['motta'];
                $newBalance = $currentBalance + $amount;

                // Update balance
                $updateQuery = "UPDATE shonu_kaichila SET motta = $newBalance WHERE balakedara = '$id'";
                $updateResult = mysqli_query($conn, $updateQuery);

                if ($updateResult) {
                    $response = [
                        'status' => '0000',
                        'balance' => $newBalance,
                        'err_text' => ''
                    ];
                } else {
                    error_log('Database query failed: ' . mysqli_error($conn));
                    $response = [
                        'status' => '0002',
                        'balance' => 0,
                        'err_text' => 'Database error'
                    ];
                }
            } else {
                $response = [
                    'status' => '0001',
                    'balance' => 0,
                    'err_text' => 'User not found'
                ];
            }
        }
        break;

    case 11:
        // Case 11: Add specified amount only if balance allows cancellation in shonu_kaichila
        logData(['action' => $requestData['action'], 'parameters' => $requestData], 'action_logs');

        $amount = $requestData['amount'] ?? 0;

        if (!isset($id) || $amount <= 0) {
            $response = [
                'status' => '0001',
                'balance' => 0,
                'err_text' => 'Invalid parameters'
            ];
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);

            try {
                // Fetch user's current balance
                $query = "SELECT motta FROM shonu_kaichila WHERE balakedara = '$id'";
                $result = mysqli_query($conn, $query);

                if ($result && $row = mysqli_fetch_assoc($result)) {
                    $currentBalance = $row['motta'];

                    // Check if balance is sufficient for cancellation
                    if ($currentBalance >= $amount) {
                        $newBalance = $currentBalance + $amount;

                        // Update balance
                        $updateQuery = "UPDATE shonu_kaichila SET motta = $newBalance WHERE balakedara = '$id'";
                        $updateResult = mysqli_query($conn, $updateQuery);

                        if ($updateResult) {
                            // Commit transaction
                            mysqli_commit($conn);
                            $response = [
                                'status' => '0000',
                                'balance' => $newBalance,
                                'err_text' => ''
                            ];
                        } else {
                            throw new Exception("Balance update failed.");
                        }
                    } else {
                        // Rollback if insufficient balance
                        mysqli_rollback($conn);
                        $response = [
                            'status' => '0001',
                            'balance' => 0,
                            'err_text' => 'Insufficient balance'
                        ];
                    }
                } else {
                    // Rollback if user is not found
                    mysqli_rollback($conn);
                    $response = [
                        'status' => '0001',
                        'balance' => 0,
                        'err_text' => 'User not found'
                    ];
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                error_log('Transaction failed: ' . $e->getMessage());
                $response = [
                    'status' => '0002',
                    'balance' => 0,
                    'err_text' => 'Database error'
                ];
            }
        }
        break;

    default:
        $response = [
            'status' => '0003',
            'balance' => 0,
            'err_text' => 'Invalid action'
        ];
        break;
}

header('Content-Type: application/json');
echo json_encode($response);
?>