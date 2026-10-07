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
	
	date_default_timezone_set('Asia/Kolkata');
	$shnunc = date("Y-m-d H:i:s");
	$res = [
		'code' => 11,
		'msg' => 'Method not allowed',
		'msgCode' => 12,
		'serviceNowTime' => $shnunc,
	];

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Validate request signature
 */
function validateSignature($postData) {
    if (!isset($postData['language'], $postData['random'], $postData['signature'])) {
        return false;
    }
    
    $language = $postData['language'];
    $random = $postData['random'];
    $signature = $postData['signature'];
    
    $signatureString = '{"language":' . $language . ',"random":"' . $random . '"}';
    $computedSignature = strtoupper(md5($signatureString));
    
    return hash_equals($computedSignature, $signature);
}

/**
 * Validate JWT token and user session
 */
function validateUserAuth($conn, $authorizationHeader) {
    if (!isset($authorizationHeader)) {
        return ['status' => 'Error', 'message' => 'Authorization header missing'];
    }
    
    $bearer = explode(" ", $authorizationHeader);
    if (count($bearer) !== 2 || $bearer[0] !== 'Bearer') {
        return ['status' => 'Error', 'message' => 'Invalid authorization format'];
    }
    
    $token = $bearer[1];
    $jwtValidation = is_jwt_valid($token);
    $authData = json_decode($jwtValidation, true);
    
    if ($authData['status'] !== 'Success') {
        return ['status' => 'Error', 'message' => 'Invalid token'];
    }
    
    // Check if user exists in database
    $stmt = $conn->prepare("SELECT akshinak FROM shonu_subjects WHERE akshinak = ?");
    if (!$stmt) {
        return ['status' => 'Error', 'message' => 'Database error'];
    }
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        return ['status' => 'Error', 'message' => 'User not found'];
    }
    
    $stmt->close();
    return ['status' => 'Success'];
}

/**
 * Fetch task data from database
 */
function fetchTaskData($conn) {
    $tasks = [];
    
    $sql = "SELECT taskID, taskAmount, rechargeAmount, taskPeople 
            FROM tbl_invitebonus 
            WHERE taskID BETWEEN 0 AND 13 
            ORDER BY taskID";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tasks[$row['taskID']] = [
            'taskAmount' => floatval($row['taskAmount']),
            'rechargeAmount' => floatval($row['rechargeAmount']),
            'taskPeople' => intval($row['taskPeople'])
        ];
    }
    
    $stmt->close();
    return $tasks;
}

/**
 * Generate task list structure
 */
function generateTaskList($taskData) {
    $taskList = [];
    $taskIds = [0, 2, 3, 4, 5, 6, 7, 8, 9, 10]; // Only required task IDs
    
    foreach ($taskIds as $index => $taskId) {
        if (isset($taskData[$taskId])) {
            $task = $taskData[$taskId];
            $taskList[] = [
                'taskID' => $taskId,
                'taskAmount' => $task['taskAmount'],
                'rechargeAmount' => $task['rechargeAmount'],
                'rechargeAmount_All' => null,
                'taskPeople' => $task['taskPeople'],
                'rechargePeople' => 0,
                'taskRechargePeople' => 0,
                'efficientPeople' => 0,
                'title' => null,
                'title2' => null,
                'isReceive' => 0,
                'isFinshed' => false,
                'beginDate' => null,
                'endDate' => null
            ];
        }
    }
    
    return $taskList;
}

// Main request processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and validate request body
        $requestBody = file_get_contents("php://input");
        $postData = json_decode($requestBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
        
        // Validate signature
        if (!validateSignature($postData)) {
            $response['code'] = 5;
            $response['msg'] = 'Wrong signature';
            $response['msgCode'] = 3;
            http_response_code(200);
            echo json_encode($response);
            exit;
        }
        
        // Validate authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $authValidation = validateUserAuth($conn, $authHeader);
        
        if ($authValidation['status'] !== 'Success') {
            $response['code'] = 401;
            $response['msg'] = 'Unauthorized: ' . ($authValidation['message'] ?? 'Invalid credentials');
            $response['msgCode'] = 401;
            http_response_code(200);
            echo json_encode($response);
            exit;
        }
        
        // Fetch task data
        $taskData = fetchTaskData($conn);
        if ($taskData === false) {
            throw new Exception('Failed to fetch task data');
        }
        
        // Prepare response data
        $responseData = [
            'totalPeople' => 0,
            'totalAmount' => 0,
            'taskList' => generateTaskList($taskData),
            'chirldrenListDataList' => null
        ];
        
        // Successful response
        $response['code'] = 0;
        $response['msg'] = 'Succeed';
        $response['msgCode'] = 0;
        $response['data'] = $responseData;
        $response['serviceNowTime'] = date("Y-m-d H:i:s");
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Error handling
        error_log("API Error: " . $e->getMessage());
        
        $response['code'] = 7;
        $response['msg'] = 'Param is Invalid or processing error';
        $response['msgCode'] = 6;
        $response['serviceNowTime'] = date("Y-m-d H:i:s");
        
        http_response_code(200);
        echo json_encode($response);
    }
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode($response);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>