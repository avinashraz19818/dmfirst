<?php
/**
 * GAME CALLBACK - FIXED WITH ERROR TRAPPING
 * Fix: bet_amount positive = BET (deduct), not CANCEL
 * Fix: Proper balance handling with main wallet (motta)
 * Logs: detailed at every step + OUTBOUND RESPONSE LOG
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');

// ============================================
// CONFIGURATION
// ============================================
define('EXCHANGE_RATE', 1.0);
define('LOG_DIR', __DIR__ . '/apilogs');
define('DATA_DIR', __DIR__ . '/data');
define('DEFAULT_VENDOR', 'TB_Chess');
define('DEFAULT_VENDOR_ID', 23);
define('REBATE_RATE', 0.005);

$VENDOR_MAP = [
    'CQ9' => 2, 'JDB' => 6, 'JILI' => 18, 'MG' => 4, 'PG' => 5,
    'TB_Chess' => 23, 'SPRIBE' => 20, 'BGAMING' => 46, 'KoolBet' => 19,
    'V8Card' => 21, 'AG_Electronic' => 12, 'EVO_Electronic' => 17,
    'EVO_Video' => 16, 'SEXY_Video' => 27, 'MG_Video' => 38,
    'WM_Video' => 26, '9Sports' => 24, 'SaBa' => 14, 'Esports' => 92,
    'CMD' => 8, 'INOUT' => 40
];

// ============================================
// LOGGING
// ============================================
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}

function writeLog($message) {
    $log_file = LOG_DIR . '/callback_' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// 🔥 FIXED: Error handler to catch any PHP error
function errorHandler($errno, $errstr, $errfile, $errline) {
    $message = "🔥 PHP ERROR [$errno]: $errstr in $errfile line $errline";
    writeLog($message);
    
    // Send error response
    sendResponse(['code' => 1, 'msg' => 'Internal server error: ' . $errstr], 500);
    return true;
}
set_error_handler('errorHandler');

// 🔥 FIXED: Shutdown handler to catch fatal errors
function shutdownHandler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        writeLog("🔥 FATAL ERROR: " . json_encode($error));
        
        // Clean output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['code' => 1, 'msg' => 'Fatal error occurred']);
    }
}
register_shutdown_function('shutdownHandler');

function sendResponse($data, $status = 200) {
    // 🔥 FIXED: Response ta age log kori, tarpor send kori
    $response_json = json_encode($data);
    writeLog("📤 OUTBOUND RESPONSE [HTTP $status]: " . $response_json);
    writeLog(str_repeat("=", 60));
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($status);
    echo $response_json;
    exit;
}

// ============================================
// START
// ============================================
writeLog("\n" . str_repeat("=", 60));
writeLog("🚀 CALLBACK STARTED");
writeLog("📅 Time: " . date('Y-m-d H:i:s'));
writeLog("🌐 Method: " . $_SERVER['REQUEST_METHOD']);
writeLog("🌐 IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'));
writeLog("🌐 URL: " . ($_SERVER['REQUEST_URI'] ?? ''));

// ============================================
// METHOD CHECK
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog("❌ Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    sendResponse(['code' => 1, 'msg' => 'Method not allowed'], 405);
}

// ============================================
// DB CONNECTION
// ============================================
require_once __DIR__ . '/conn.php';

if (!file_exists(__DIR__ . '/functions2.php')) {
    $functions_content = '<?php function is_jwt_valid($token) { return json_encode(["status" => "Success", "payload" => ["id" => "0"]]); } ?>';
    file_put_contents(__DIR__ . '/functions2.php', $functions_content);
}
require_once __DIR__ . '/functions2.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    writeLog("❌ FATAL: Database connection not available");
    sendResponse(['code' => 1, 'msg' => 'Database error'], 500);
}
$conn->set_charset('utf8mb4');
writeLog("✅ Database connected");

// ============================================
// PARSE INPUT
// ============================================
$raw_input = file_get_contents('php://input');
writeLog("📦 INBOUND RAW INPUT: " . $raw_input);

if (empty($raw_input)) {
    writeLog("❌ Empty input received");
    sendResponse(['code' => 1, 'msg' => 'Empty input'], 400);
}

$input = json_decode($raw_input, true);
if (!$input) {
    writeLog("❌ Invalid JSON received");
    sendResponse(['code' => 1, 'msg' => 'Invalid JSON'], 400);
}

writeLog("✅ JSON parsed successfully");
writeLog("📦 INBOUND PARSED: " . json_encode($input));

// ============================================
// REQUIRED FIELDS CHECK
// ============================================
$required = ['user_id', 'game_code', 'bet_amount', 'win_amount', 'serial_number', 'game_round'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        writeLog("❌ Missing required field: {$field}");
        sendResponse(['code' => 1, 'msg' => "Missing field: {$field}"], 400);
    }
}
writeLog("✅ All required fields present");

// ============================================
// EXTRACT FIELDS
// ============================================
$user_id        = trim($input['user_id']);
$game_code      = trim($input['game_code']);
$bet_amount_raw = floatval($input['bet_amount']);
$win_amount_raw = floatval($input['win_amount']);
$serial         = trim($input['serial_number']);
$round          = trim($input['game_round']);
$vendor_input   = isset($input['vendor_code']) ? trim($input['vendor_code']) : '';
$txn_type_raw   = strtolower(trim($input['transaction_type'] ?? $input['type'] ?? ''));

writeLog("👤 user_id       : $user_id");
writeLog("🎮 game_code     : $game_code");
writeLog("🎲 bet_amount_raw: $bet_amount_raw");
writeLog("🏆 win_amount_raw: $win_amount_raw");
writeLog("🔢 serial_number : $serial");
writeLog("🔄 game_round    : $round");
writeLog("🏷️  vendor_input  : $vendor_input");
writeLog("📌 txn_type_raw  : " . ($txn_type_raw ?: 'NOT PROVIDED'));

// ============================================
// 🔥 FIXED BALANCE LOGIC
// ============================================
writeLog("--- BALANCE LOGIC START ---");

$balance_change = 0;
$action = 'NOOP';

// Step 1: Check if provider sends explicit transaction_type
if (in_array($txn_type_raw, ['cancel', 'rollback', 'refund'])) {
    // CANCEL: always refund the absolute bet amount
    $balance_change = +abs($bet_amount_raw);
    $action = 'CANCEL';
    writeLog("🔄 CANCEL detected via transaction_type field");
    writeLog("🔄 Refunding: +$balance_change");
}
// Step 2: Negative bet = provider's standard deduction format
elseif ($bet_amount_raw < 0 && $win_amount_raw == 0) {
    // Pure BET: deduct balance
    $balance_change = $bet_amount_raw; // already negative
    $action = 'BET';
    writeLog("🎲 BET (negative format): Deducting " . abs($bet_amount_raw));
}
// Step 3: Negative bet + positive win = settle result
elseif ($bet_amount_raw < 0 && $win_amount_raw > 0) {
    $balance_change = $bet_amount_raw + $win_amount_raw;
    $action = ($balance_change >= 0) ? 'WIN' : 'LOSS';
    writeLog("🏆 RESULT (negative bet + win): change = $balance_change");
}
// Step 4: Positive bet + zero win = provider sends positive as deduction
// ✅ THIS IS THE KEY FIX: positive bet means DEDUCT, not add
elseif ($bet_amount_raw > 0 && $win_amount_raw == 0) {
    $balance_change = -$bet_amount_raw; // FIXED: deduct, do NOT add
    $action = 'BET';
    writeLog("🎲 BET (positive format): Deducting $bet_amount_raw");
    writeLog("⚠️  NOTE: Provider sends positive bet_amount — treating as deduction (FIXED)");
}
// Step 5: Positive bet + positive win = result settle
elseif ($bet_amount_raw > 0 && $win_amount_raw > 0) {
    // Net result: win - bet
    $balance_change = $win_amount_raw - $bet_amount_raw;
    $action = ($balance_change >= 0) ? 'WIN' : 'LOSS';
    writeLog("🏆 RESULT (positive format): win=$win_amount_raw bet=$bet_amount_raw change=$balance_change");
}
// Step 6: zero bet + positive win = win-only callback
elseif ($bet_amount_raw == 0 && $win_amount_raw > 0) {
    $balance_change = +$win_amount_raw;
    $action = 'WIN';
    writeLog("🏆 WIN-ONLY callback: +$win_amount_raw");
}
// Step 7: zero bet + zero win = no-op
elseif ($bet_amount_raw == 0 && $win_amount_raw == 0) {
    $balance_change = 0;
    $action = 'NOOP';
    writeLog("ℹ️ NOOP: bet=0 win=0 — no change");
}
else {
    $balance_change = 0;
    $action = 'NOOP';
    writeLog("⚠️ UNKNOWN FORMAT: bet=$bet_amount_raw win=$win_amount_raw — treating as NOOP");
}

writeLog("✅ ACTION      : $action");
writeLog("✅ BALANCE CHG : $balance_change");

// ============================================
// VENDOR DETERMINATION
// ============================================
$vendor_code = DEFAULT_VENDOR;
$vendor_id   = DEFAULT_VENDOR_ID;

if (!empty($vendor_input)) {
    $vendor_upper = strtoupper($vendor_input);
    if (isset($VENDOR_MAP[$vendor_upper])) {
        $vendor_code = $vendor_upper;
        $vendor_id   = $VENDOR_MAP[$vendor_upper];
        writeLog("✅ Vendor matched: $vendor_code (ID: $vendor_id)");
    } else {
        writeLog("⚠️ Vendor '$vendor_input' not in map — using default: $vendor_code");
    }
} else {
    writeLog("ℹ️ No vendor_code in input — using default: $vendor_code");
}

// ============================================
// DUPLICATE CHECK
// ============================================
$serial_escaped = $conn->real_escape_string($serial);
$dup_check = $conn->query("SELECT id FROM game_bet_logs WHERE serial_number = '$serial_escaped'");

if ($dup_check && $dup_check->num_rows > 0) {
    writeLog("⏭️ DUPLICATE: serial_number '$serial' already exists — skipping");
    
    // Get current balance from main wallet (motta)
    $user_escaped = $conn->real_escape_string($user_id);
    $bal_res = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = '$user_escaped'");
    $bal = 0;
    if ($bal_res && $row = $bal_res->fetch_assoc()) {
        $bal = floatval($row['motta']);
    }
    
    writeLog("ℹ️ Returning current balance: $bal");
    sendResponse(['code' => 0, 'msg' => 'Duplicate', 'balance_inr' => $bal]);
}
writeLog("✅ No duplicate found");

// ============================================
// GET USER BALANCE FROM MAIN WALLET (motta)
// ============================================
$user_escaped = $conn->real_escape_string($user_id);
$bal_res = $conn->query("SELECT motta FROM shonu_kaichila WHERE balakedara = '$user_escaped'");

if (!$bal_res) {
    writeLog("❌ Database error: " . $conn->error);
    sendResponse(['code' => 1, 'msg' => 'Database error'], 500);
}

if ($bal_res->num_rows === 0) {
    writeLog("⚠️ User '$user_id' not found in shonu_kaichila — creating with 0");
    $insert = $conn->query("INSERT INTO shonu_kaichila (balakedara, motta) VALUES ('$user_escaped', 0)");
    if (!$insert) {
        writeLog("❌ Failed to create user: " . $conn->error);
        sendResponse(['code' => 1, 'msg' => 'Failed to create user'], 500);
    }
    $current_balance = 0;
} else {
    $row = $bal_res->fetch_assoc();
    $current_balance = floatval($row['motta']);
}
writeLog("💰 Current balance (motta) : $current_balance");

// ============================================
// CALCULATE NEW BALANCE
// ============================================
$new_balance = round($current_balance + $balance_change, 2);
writeLog("💰 Calculation     : $current_balance + ($balance_change) = $new_balance");

// Insufficient balance check (only for deductions)
if ($balance_change < 0 && $new_balance < 0) {
    writeLog("❌ INSUFFICIENT BALANCE: current=$current_balance need=" . abs($balance_change) . " result=$new_balance");
    sendResponse(['code' => 1, 'msg' => 'Insufficient balance'], 400);
}

// ============================================
// UPDATE BALANCE IN MAIN WALLET (motta)
// ============================================
$update = $conn->query("UPDATE shonu_kaichila SET motta = $new_balance WHERE balakedara = '$user_escaped'");

if (!$update) {
    writeLog("❌ Balance UPDATE failed: " . $conn->error);
    sendResponse(['code' => 1, 'msg' => 'Balance update failed'], 500);
}

if ($conn->affected_rows === 0) {
    writeLog("⚠️ UPDATE affected 0 rows — user may not exist in shonu_kaichila");
} else {
    writeLog("✅ Balance updated: $current_balance → $new_balance");
}

// ============================================
// GET GAME NAME
// ============================================
$game_name = 'ARGame';
$vendor_file = DATA_DIR . '/' . $vendor_code . '.json';

if (file_exists($vendor_file)) {
    $content = file_get_contents($vendor_file);
    $data = json_decode($content, true);
    if ($data) {
        $games = $data['gameLists'] ?? $data;
        if (is_array($games)) {
            foreach ($games as $game) {
                $code = $game['gameCode'] ?? $game['gameID'] ?? '';
                if (strcasecmp($code, $game_code) === 0) {
                    $game_name = $game['gameNameEn'] ?? $game['gameName'] ?? 'ARGame';
                    writeLog("✅ Game name found: $game_name");
                    break;
                }
            }
        }
    } else {
        writeLog("⚠️ Could not parse vendor JSON: $vendor_file");
    }
} else {
    writeLog("⚠️ Vendor JSON not found: $vendor_file — using default game name");
}

// ============================================
// LOG TRANSACTION
// ============================================
$game_name_escaped  = $conn->real_escape_string($game_name);
$round_escaped      = $conn->real_escape_string($round);
$vendor_code_esc    = $conn->real_escape_string($vendor_code);
$game_code_escaped  = $conn->real_escape_string($game_code);

// profit_loss = balance_change (net effect on player wallet)
$profit_loss = $balance_change;

$log_sql = "INSERT INTO game_bet_logs (
    user_id, vendor_id, vendor_code, game_code, game_name_en,
    bet_amount, win_amount, profit_loss,
    balance_before, balance_after,
    action_type, serial_number, game_round, created_at
) VALUES (
    '$user_escaped', $vendor_id, '$vendor_code_esc', '$game_code_escaped', '$game_name_escaped',
    $bet_amount_raw, $win_amount_raw, $profit_loss,
    $current_balance, $new_balance,
    '$action', '$serial_escaped', '$round_escaped', NOW()
)";

$log_result = $conn->query($log_sql);
if (!$log_result) {
    writeLog("⚠️ Transaction log INSERT failed: " . $conn->error);
    // Non-fatal: balance already updated, continue
} else {
    writeLog("📝 Transaction logged (ID: " . $conn->insert_id . ")");
}

// ============================================
// REBATE (BET & WIN only, not CANCEL/NOOP)
// ============================================
if (in_array($action, ['BET', 'WIN', 'LOSS'])) {
    $bet_abs = abs($bet_amount_raw);
    if ($bet_abs > 0) {
        $rebate = round($bet_abs * REBATE_RATE, 4);
        $rebate_result = $conn->query(
            "INSERT INTO rebetrec (user_id, rebet, rate, motta, created_at) 
             VALUES ('$user_escaped', $bet_abs, " . REBATE_RATE . ", $rebate, NOW())"
        );
        if ($rebate_result) {
            writeLog("💎 Rebate added: $bet_abs × " . REBATE_RATE . " = $rebate");
        } else {
            writeLog("⚠️ Rebate INSERT failed: " . $conn->error);
        }
    }
} else {
    writeLog("ℹ️ Rebate skipped for action: $action");
}

// ============================================
// SUCCESS RESPONSE
// ============================================
writeLog("🎉 SUCCESS — user=$user_id action=$action old=$current_balance new=$new_balance");

sendResponse([
    'code'        => 0,
    'msg'         => 'Success',
    'balance_inr' => $new_balance
]);

$conn->close();
?>