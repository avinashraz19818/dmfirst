<?php
// ==============================================================================
// File: call.php (Client-Side Seamless Wallet Callback Endpoint - Fixed)
// Component: Handles balance, bet, win & bet_and_win actions from Gateway
// Integration Authority: @developer_maruf08
// ==============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ==========================================
// 1. Reseller & Gateway Configuration
// ==========================================
define('NETOOZE_PREFIX', 'dir_gfg'); // আন্ডারস্কোর ছাড়া প্রিফিক্স

// ==========================================
// 2. Double-Sided Logging System (aback.txt)
// ==========================================
function writeToABackLog($type, $data_string) {
    $log_file = __DIR__ . '/aback.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = "==================================================\n";
    $log_entry .= "[{$timestamp}] [{$type}]\n";
    $log_entry .= "--------------------------------------------------\n";
    $log_entry .= $data_string . "\n";
    $log_entry .= "==================================================\n\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function sendResponse($status, $balance = null, $error = null, $httpCode = 200) {
    $response = ["status" => $status];
    if ($balance !== null) {
        $response["balance"] = (float)number_format(floatval($balance), 2, '.', '');
    }
    if ($error !== null) {
        $response["error"] = $error;
    }
    
    $json_response = json_encode($response, JSON_PRETTY_PRINT);
    writeToABackLog("MY_PHP_OUTPUT_RESPONSE (HTTP Code: {$httpCode})", $json_response);

    http_response_code($httpCode);
    echo json_encode($response);
    exit;
}

// ==========================================
// 3. Database Connection
// ==========================================
$DB_HOST = 'localhost';
$DB_NAME = 'club532583_dmfirst';
$DB_USER = 'club532583_dmfirst';
$DB_PASS = 'club532583_dmfirst';

date_default_timezone_set('Asia/Kolkata');

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        writeToABackLog("DATABASE_CRITICAL_ERROR", "Database connection failed: " . $conn->connect_error);
        sendResponse("FAILED", null, "DATABASE_ERROR", 200);
    }
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    writeToABackLog("DATABASE_CRITICAL_EXCEPTION", $e->getMessage());
    sendResponse("FAILED", null, "DATABASE_ERROR", 200);
}

// ==========================================
// 4. Request Validation & Input Capture
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeToABackLog("INVALID_METHOD_REJECTED", "Method: " . $_SERVER['REQUEST_METHOD']);
    sendResponse("FAILED", null, "METHOD_NOT_ALLOWED", 405);
}

$raw_input = file_get_contents('php://input');
$incoming_meta = "URI: " . $_SERVER['REQUEST_URI'] . "\nPayload: " . $raw_input;
writeToABackLog("GATEWAY_INCOMING_REQUEST", $incoming_meta);

$data = json_decode($raw_input, true);
if (!$data) {
    writeToABackLog("JSON_DECODE_FAILED", "Could not parse JSON body.");
    sendResponse("FAILED", null, "INVALID_JSON", 400);
}

// ==========================================
// 5. Extract Fields & Normalize Actions
// ==========================================
$action          = strtolower(trim($data['action'] ?? ''));
$player_id_raw   = trim($data['player_id'] ?? ($data['user_id'] ?? ''));
$amount          = floatval($data['amount'] ?? 0);
$provider_txn_id = trim($data['provider_txn_id'] ?? ($data['provider_tx_id'] ?? ($data['serial_number'] ?? '')));
$proxy_txn_id    = trim($data['proxy_txn_id'] ?? '');
$game_code       = trim($data['game_code'] ?? ($data['game'] ?? ''));
$currency        = strtoupper(trim($data['currency'] ?? ($data['currency_code'] ?? 'BDT')));

$bet_amount = isset($data['bet_amount']) ? floatval($data['bet_amount']) : 0;
$win_amount = isset($data['win_amount']) ? floatval($data['win_amount']) : 0;

if (empty($action)) {
    if ($bet_amount > 0 && $win_amount > 0) {
        $action = 'bet_and_win';
    } elseif ($bet_amount > 0) {
        $action = 'bet';
        $amount = $bet_amount;
    } elseif ($win_amount > 0) {
        $action = 'win';
        $amount = $win_amount;
    } else {
        $action = 'balance';
    }
}

// ==========================================
// 6. 100% Reliable Player ID Extraction (Regex)
// ==========================================
preg_match('/\d+$/', $player_id_raw, $matches);
$user_id = isset($matches[0]) ? (string)(int)$matches[0] : '';

if (empty($user_id) || intval($user_id) <= 0) {
    writeToABackLog("VALIDATION_ERROR", "Could not extract numeric user_id from: {$player_id_raw}");
    sendResponse("FAILED", null, "PLAYER_NOT_FOUND", 200);
}

if (!in_array($action, ['balance', 'bet', 'win', 'bet_and_win'])) {
    writeToABackLog("VALIDATION_ERROR", "Invalid action requested: {$action}");
    sendResponse("FAILED", null, "INVALID_ACTION", 400);
}

// ==========================================
// 7. Duplicate Transaction Check (Idempotency)
// ==========================================
if (($action === 'bet' || $action === 'win') && !empty($provider_txn_id)) {
    try {
        $dup_stmt = $conn->prepare("SELECT id FROM game_bet_logs WHERE serial_number = ? LIMIT 1");
        if ($dup_stmt) {
            $dup_stmt->bind_param("s", $provider_txn_id);
            $dup_stmt->execute();
            $dup_result = $dup_stmt->get_result();
            if ($dup_result && $dup_result->num_rows > 0) {
                writeToABackLog("DUPLICATE_TXN_TRIGGER", "Transaction ID {$provider_txn_id} already exists. Returning current balance.");
                
                $bal_stmt = $conn->prepare("SELECT balance FROM argame_balances WHERE user_id = ? LIMIT 1");
                $bal_stmt->bind_param("s", $user_id);
                $bal_stmt->execute();
                $bal_res = $bal_stmt->get_result();
                $balance = 0.00;
                if ($bal_res && $bal_res->num_rows > 0) {
                    $balance = floatval($bal_res->fetch_assoc()['balance']);
                }
                $bal_stmt->close();
                $dup_stmt->close();
                sendResponse("SUCCESS", $balance);
            }
            $dup_stmt->close();
        }
    } catch (Throwable $e) {
        writeToABackLog("DUPLICATE_CHECK_EXCEPTION", $e->getMessage());
    }
}

// ==========================================
// 8. Get Player Balance (Game Wallet -> Main Wallet Fallback)
// ==========================================
$current_balance = 0.0;
$has_game_wallet = false;

$balance_stmt = $conn->prepare("SELECT balance FROM argame_balances WHERE user_id = ? LIMIT 1");
if ($balance_stmt) {
    $balance_stmt->bind_param("s", $user_id);
    $balance_stmt->execute();
    $balance_result = $balance_stmt->get_result();
    if ($balance_result && $balance_result->num_rows > 0) {
        $has_game_wallet = true;
        $current_balance = floatval($balance_result->fetch_assoc()['balance']);
    }
    $balance_stmt->close();
}

// যদি গেম ওয়ালেটে ব্যালেন্স না থাকে, তাহলে মেইন ওয়ালেট চেক করা
if ($current_balance <= 0) {
    $main_stmt = $conn->prepare("SELECT motta FROM shonu_kaichila WHERE balakedara = ? LIMIT 1");
    if ($main_stmt) {
        $main_stmt->bind_param("s", $user_id);
        $main_stmt->execute();
        $main_result = $main_stmt->get_result();
        
        if ($main_result && $main_result->num_rows > 0) {
            $main_balance = floatval($main_result->fetch_assoc()['motta']);
            if ($main_balance > 0) {
                $current_balance = $main_balance;
                
                // গেম ওয়ালেটে ব্যালেন্স শিফট করে নেওয়া
                if ($has_game_wallet) {
                    $up_gw = $conn->prepare("UPDATE argame_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
                    if ($up_gw) {
                        $up_gw->bind_param("ds", $current_balance, $user_id);
                        $up_gw->execute();
                        $up_gw->close();
                    }
                } else {
                    $ins_gw = $conn->prepare("INSERT INTO argame_balances (user_id, balance, updated_at) VALUES (?, ?, NOW())");
                    if ($ins_gw) {
                        $ins_gw->bind_param("sd", $user_id, $current_balance);
                        $ins_gw->execute();
                        $ins_gw->close();
                    }
                }
            }
        }
        $main_stmt->close();
    }
}

// ==========================================
// 9. Process Gateway Actions
// ==========================================
try {
    switch ($action) {
        
        // ── 1. GET BALANCE ──
        case 'balance':
            sendResponse("SUCCESS", $current_balance);
            break;
        
        // ── 2. PROCESS BET (DEBIT) ──
        case 'bet':
            if ($current_balance < $amount) {
                writeToABackLog("INSUFFICIENT_FUNDS_LOG", "User: {$user_id} | Balance: {$current_balance} | Requested Bet: {$amount}");
                sendResponse("FAILED", $current_balance, "INSUFFICIENT_FUNDS", 200);
            }
            
            $new_balance = $current_balance - $amount;
            
            $update_stmt = $conn->prepare("UPDATE argame_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("ds", $new_balance, $user_id);
            
            if (!$update_stmt->execute()) {
                writeToABackLog("BET_UPDATE_FAILED", $update_stmt->error);
                sendResponse("FAILED", $current_balance, "UPDATE_FAILED", 200);
            }
            $update_stmt->close();
            
            logBetTransaction($conn, $user_id, $game_code, $amount, 0, $provider_txn_id, $proxy_txn_id, $current_balance, $new_balance);
            updateVipAndRebate($conn, $user_id, $amount);
            
            sendResponse("SUCCESS", $new_balance);
            break;
            
        // ── 3. PROCESS BET & WIN ──
        case 'bet_and_win':
            if ($current_balance < $bet_amount) {
                writeToABackLog("INSUFFICIENT_FUNDS_LOG", "User: {$user_id} | MultiAction Bet Required: {$bet_amount}");
                sendResponse("FAILED", $current_balance, "INSUFFICIENT_FUNDS", 200);
            }
            
            $new_balance = $current_balance - $bet_amount + $win_amount;
            
            $update_stmt = $conn->prepare("UPDATE argame_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("ds", $new_balance, $user_id);
            
            if (!$update_stmt->execute()) {
                sendResponse("FAILED", $current_balance, "UPDATE_FAILED", 200);
            }
            $update_stmt->close();
            
            logBetTransaction($conn, $user_id, $game_code, $bet_amount, $win_amount, $provider_txn_id, $proxy_txn_id, $current_balance, $new_balance);
            updateVipAndRebate($conn, $user_id, $bet_amount);
            
            sendResponse("SUCCESS", $new_balance);
            break;
        
        // ── 4. PROCESS WIN (CREDIT) ──
        case 'win':
            $new_balance = $current_balance + $amount;
            
            $update_stmt = $conn->prepare("UPDATE argame_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("ds", $new_balance, $user_id);
            
            if (!$update_stmt->execute()) {
                sendResponse("FAILED", $current_balance, "UPDATE_FAILED", 200);
            }
            $update_stmt->close();
            
            logBetTransaction($conn, $user_id, $game_code, 0, $amount, $provider_txn_id, $proxy_txn_id, $current_balance, $new_balance);
            
            sendResponse("SUCCESS", $new_balance);
            break;
    }
} catch (Exception $e) {
    writeToABackLog("ENGINE_CRASH_EXCEPTION", $e->getMessage());
    sendResponse("FAILED", null, $e->getMessage(), 200);
}

// ==========================================
// 10. Helper: Log Bet Transaction
// ==========================================
function logBetTransaction($conn, $user_id, $game_code, $bet_amount, $win_amount, $serial_number, $proxy_txn_id, $balance_before, $balance_after) {
    try {
        $profit_loss = $win_amount - $bet_amount;
        $vendor_code = 'winlab';
        $game_round = $proxy_txn_id ?: $serial_number;
        
        $log_stmt = $conn->prepare(
            "INSERT INTO game_bet_logs (user_id, vendor_code, game_code, bet_amount, win_amount, profit_loss, balance_before, balance_after, serial_number, game_round, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        if ($log_stmt) {
            $log_stmt->bind_param(
                "sssdddddss",
                $user_id, $vendor_code, $game_code,
                $bet_amount, $win_amount, $profit_loss,
                $balance_before, $balance_after,
                $serial_number, $game_round
            );
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Throwable $e) {
        writeToABackLog("TRANSACTION_LOG_EXCEPTION", $e->getMessage());
    }
}

// ==========================================
// 11. Helper: Update VIP Level & Rebate
// ==========================================
function updateVipAndRebate($conn, $user_id, $bet_amount) {
    try {
        $lvl = 0;
        $lvl_stmt = @$conn->prepare("SELECT lvl FROM vip WHERE userid = ? LIMIT 1");
        if ($lvl_stmt) {
            $lvl_stmt->bind_param("s", $user_id);
            $lvl_stmt->execute();
            $lvl_res = $lvl_stmt->get_result();
            if ($lvl_res && $lvl_res->num_rows > 0) {
                $lrw = $lvl_res->fetch_assoc();
                $lvl = intval($lrw['lvl']);
            }
            $lvl_stmt->close();
        }

        if (in_array($lvl, [0,1,2])) { $rate = 0.05; }
        elseif (in_array($lvl, [3,4,5])) { $rate = 0.10; }
        elseif (in_array($lvl, [6,7,8])) { $rate = 0.15; }
        elseif ($lvl === 9) { $rate = 0.20; }
        elseif ($lvl === 10) { $rate = 0.30; }
        else { $rate = 0.05; }

        $washVolume = intval($bet_amount);
        $rebAmt = (float)($washVolume * $rate);

        $colCheck = @$conn->query("SHOW COLUMNS FROM rebetrec LIKE 'prakara'");
        $hasPrakara = ($colCheck && $colCheck->num_rows > 0);
        
        if ($hasPrakara) {
            $codeType = 3;
            $ins = @$conn->prepare("INSERT INTO rebetrec (user_id, rebet, rate, motta, created_at, prakara) VALUES (?, ?, ?, ?, NOW(), ?)");
            if ($ins) { $ins->bind_param("isddi", $user_id, $washVolume, $rate, $rebAmt, $codeType); @$ins->execute(); $ins->close(); }
        } else {
            $ins = @$conn->prepare("INSERT INTO rebetrec (user_id, rebet, rate, motta, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($ins) { $ins->bind_param("isdd", $user_id, $washVolume, $rate, $rebAmt); @$ins->execute(); $ins->close(); }
        }

        $ck = @$conn->prepare("SELECT rebet FROM shonu_kaichila WHERE balakedara = ? LIMIT 1");
        if ($ck) {
            $ck->bind_param("s", $user_id);
            $ck->execute();
            $ckr = $ck->get_result();
            if ($ckr && $ckr->num_rows === 1) {
                $up = @$conn->prepare("UPDATE shonu_kaichila SET rebet = rebet + ? WHERE balakedara = ?");
                if ($up) { $up->bind_param("ds", $washVolume, $user_id); @$up->execute(); $up->close(); }
            }
            $ck->close();
        }

        // VIP Level Update
        $vip_stmt = $conn->prepare("SELECT expe, lvl FROM vip WHERE userid = ? LIMIT 1");
        if ($vip_stmt) {
            $vip_stmt->bind_param("s", $user_id);
            $vip_stmt->execute();
            $vipresult = $vip_stmt->get_result();
            
            if ($vipresult && $vipresult->num_rows >= 1) {
                $viparr = $vipresult->fetch_assoc();
                $expe = $viparr['expe'] + $bet_amount;
                $orlvl = $viparr['lvl'];
                $new_lvl = $orlvl;
                $giveamt = 0;

                if ($expe >= 3000 && $expe < 30000) { $new_lvl = 1; if ($new_lvl - $orlvl == 1) $giveamt = 90; }
                elseif ($expe >= 30000 && $expe < 400000) { $new_lvl = 2; if ($new_lvl - $orlvl == 1) $giveamt = 270; }
                elseif ($expe >= 400000 && $expe < 4000000) { $new_lvl = 3; if ($new_lvl - $orlvl == 1) $giveamt = 980; }
                elseif ($expe >= 4000000 && $expe < 20000000) { $new_lvl = 4; if ($new_lvl - $orlvl == 1) $giveamt = 2780; }
                elseif ($expe >= 20000000) { $new_lvl = 5; if ($new_lvl - $orlvl == 1) $giveamt = 8790; }

                if ($giveamt > 0) {
                    $nabikarana = $conn->prepare("UPDATE shonu_kaichila SET motta = motta + ? WHERE balakedara = ?");
                    if ($nabikarana) { $nabikarana->bind_param("ds", $giveamt, $user_id); $nabikarana->execute(); $nabikarana->close(); }
                }
                
                $up_vip = $conn->prepare("UPDATE vip SET expe = ?, lvl = ?, createdate = NOW() WHERE userid = ?");
                if ($up_vip) { $up_vip->bind_param("dis", $expe, $new_lvl, $user_id); $up_vip->execute(); $up_vip->close(); }
            }
            $vip_stmt->close();
        }
    } catch (Throwable $e) {
        writeToABackLog("VIP_REBATE_PROGRESSION_EXCEPTION", $e->getMessage());
    } 
}
?>