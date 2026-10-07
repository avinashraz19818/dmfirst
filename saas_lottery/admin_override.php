<?php
/**
 * Period-bound WinGo admin overrides.
 *
 * The legacy admin tables only store the selected number.  This helper keeps
 * the selected number tied to one exact SaaS issue so an old, newly-synced
 * history row can never consume an override intended for the current round.
 */

function sl_admin_override_game_config($gameCode)
{
    static $games = array(
        'WinGo_30S' => array(
            'interval_seconds' => 30,
            'interval_code' => 5,
            'manual_table' => 'hastacalita_phalitansa_zehn',
            'queue_table' => 'gelluonduhogu_zehn_zehn',
            'period_table' => 'gelluonduhogu_zehn',
        ),
        'WinGo_1M' => array(
            'interval_seconds' => 60,
            'interval_code' => 1,
            'manual_table' => 'hastacalita_phalitansa',
            'queue_table' => 'gelluonduhogu_zehn_zehn_1',
            'period_table' => 'gelluonduhogu',
        ),
        'WinGo_3M' => array(
            'interval_seconds' => 180,
            'interval_code' => 2,
            'manual_table' => 'hastacalita_phalitansa_drei',
            'queue_table' => 'gelluonduhogu_zehn_zehn_2',
            'period_table' => 'gelluonduhogu_drei',
        ),
        'WinGo_5M' => array(
            'interval_seconds' => 300,
            'interval_code' => 3,
            'manual_table' => 'hastacalita_phalitansa_funf',
            'queue_table' => 'gelluonduhogu_zehn_zehn_3',
            'period_table' => 'gelluonduhogu_funf',
        ),
    );

    return isset($games[$gameCode]) ? $games[$gameCode] : null;
}

function sl_admin_override_table_exists($conn, $table)
{
    if (!($conn instanceof mysqli) || !preg_match('/^[A-Za-z0-9_]+$/', (string)$table)) {
        return false;
    }
    $escaped = $conn->real_escape_string((string)$table);
    $result = $conn->query("SHOW TABLES LIKE '" . $escaped . "'");
    return $result && $result->num_rows > 0;
}

function sl_admin_override_ensure_schema($conn)
{
    static $installed = false;
    if ($installed) {
        return;
    }
    if (!($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available');
    }

    $sql = "CREATE TABLE IF NOT EXISTS saas_lottery_overrides ("
        . "game_code VARCHAR(32) NOT NULL,"
        . "issue_number VARCHAR(40) NOT NULL,"
        . "premium VARCHAR(64) NOT NULL,"
        . "created_by VARCHAR(100) NOT NULL,"
        . "created_at DATETIME NOT NULL,"
        . "PRIMARY KEY(game_code,issue_number),"
        . "KEY idx_saas_override_created(created_at)"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to create the WinGo override table');
    }
    $installed = true;
}

function sl_admin_override_period_id($gameCode, $timestamp = null)
{
    $config = sl_admin_override_game_config($gameCode);
    if (!$config) {
        throw new InvalidArgumentException('Unsupported WinGo game code');
    }

    $now = $timestamp === null ? time() : (int)$timestamp;
    $interval = (int)$config['interval_seconds'];
    global $conn;
    if (isset($conn) && $conn instanceof mysqli && sl_wingo_trend_mode_get($conn) === 'back_one') {
        // Full Back Trend runs the local issue clock one complete interval late.
        $now -= $interval;
    }
    $dayStart = (int)strtotime(gmdate('Y-m-d', $now) . ' 00:00:00 UTC');
    $sequence = (int)floor(($now - $dayStart) / $interval) + 1;

    return gmdate('Ymd', $now)
        . sprintf('%03d%02d%04d', 100, (int)$config['interval_code'], $sequence);
}

function sl_admin_override_result_exists($conn, $gameCode, $issueNumber)
{
    if (!sl_admin_override_table_exists($conn, 'saas_lottery_results')) {
        return false;
    }
    $stmt = $conn->prepare('SELECT 1 FROM saas_lottery_results WHERE game_code=? AND issue_number=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $gameCode, $issueNumber);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function sl_admin_override_remove_unsettled_result($conn, $gameCode, $issueNumber)
{
    if (!sl_admin_override_table_exists($conn, 'saas_lottery_results')) {
        return;
    }

    if (sl_admin_override_table_exists($conn, 'saas_lottery_bets')) {
        $stmt = $conn->prepare(
            "DELETE r FROM saas_lottery_results r "
            . "LEFT JOIN saas_lottery_bets b ON b.game_code=r.game_code "
            . "AND b.issue_number=r.issue_number AND b.status<>'pending' "
            . "WHERE r.game_code=? AND r.issue_number=? AND b.id IS NULL"
        );
    } else {
        $stmt = $conn->prepare('DELETE FROM saas_lottery_results WHERE game_code=? AND issue_number=?');
    }
    if (!$stmt) {
        throw new RuntimeException('Unable to verify the active WinGo period');
    }
    $stmt->bind_param('ss', $gameCode, $issueNumber);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to verify the active WinGo period');
    }
    $stmt->close();
}

function sl_admin_override_get($conn, $gameCode, $issueNumber = null)
{
    sl_admin_override_ensure_schema($conn);

    if ($issueNumber !== null) {
        $stmt = $conn->prepare('SELECT issue_number,premium,created_by,created_at FROM saas_lottery_overrides WHERE game_code=? AND issue_number=? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $gameCode, $issueNumber);
    } else {
        $stmt = $conn->prepare('SELECT issue_number,premium,created_by,created_at FROM saas_lottery_overrides WHERE game_code=? ORDER BY created_at DESC,issue_number DESC LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $gameCode);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$row || !preg_match('/^[0-9]$/', (string)$row['premium'])) {
        return null;
    }
    return $row;
}

function sl_admin_override_get_pending($conn, $gameCode)
{
    sl_admin_override_ensure_schema($conn);
    if (!sl_admin_override_table_exists($conn, 'saas_lottery_results')) {
        return sl_admin_override_get($conn, $gameCode);
    }

    $stmt = $conn->prepare(
        'SELECT o.issue_number,o.premium,o.created_by,o.created_at '
        . 'FROM saas_lottery_overrides o '
        . 'LEFT JOIN saas_lottery_results r '
        . 'ON r.game_code=o.game_code AND r.issue_number=o.issue_number '
        . 'WHERE o.game_code=? AND r.id IS NULL '
        . 'ORDER BY o.created_at DESC,o.issue_number DESC LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $gameCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row && preg_match('/^[0-9]$/', (string)$row['premium']) ? $row : null;
}

function sl_admin_override_delete_pending($conn, $gameCode)
{
    sl_admin_override_ensure_schema($conn);
    if (sl_admin_override_table_exists($conn, 'saas_lottery_results')) {
        $stmt = $conn->prepare(
            'DELETE o FROM saas_lottery_overrides o '
            . 'LEFT JOIN saas_lottery_results r '
            . 'ON r.game_code=o.game_code AND r.issue_number=o.issue_number '
            . 'WHERE o.game_code=? AND r.id IS NULL'
        );
    } else {
        $stmt = $conn->prepare('DELETE FROM saas_lottery_overrides WHERE game_code=?');
    }
    if (!$stmt) {
        throw new RuntimeException('Unable to clear the pending WinGo override');
    }
    $stmt->bind_param('s', $gameCode);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to clear the pending WinGo override');
    }
    $stmt->close();
}

function sl_admin_override_legacy_issue($conn, $gameCode)
{
    $config = sl_admin_override_game_config($gameCode);
    if (!$config || !sl_admin_override_table_exists($conn, $config['period_table'])) {
        return '';
    }
    $table = $config['period_table'];
    $result = $conn->query("SELECT atadaaidi FROM `{$table}` ORDER BY kramasankhye DESC LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;
    $issue = $row ? (string)$row['atadaaidi'] : '';
    return preg_match('/^[0-9]{17}$/', $issue) ? $issue : '';
}

function sl_admin_override_set($conn, $gameCode, $number, $requestedIssue, $createdBy)
{
    $config = sl_admin_override_game_config($gameCode);
    $number = (int)$number;
    if (!$config || $number < 0 || $number > 9) {
        throw new InvalidArgumentException('Invalid WinGo override');
    }
    sl_admin_override_ensure_schema($conn);

    // The server's active issue is authoritative.  A stale admin tab is never
    // allowed to target an already-finished period.
    $now = time();
    $currentIssue = sl_admin_override_period_id($gameCode, $now);
    $requestedIssue = preg_match('/^[0-9]{17}$/', (string)$requestedIssue)
        ? (string)$requestedIssue
        : '';
    $targetIssue = $requestedIssue === $currentIssue ? $requestedIssue : $currentIssue;

    // Old history mirrors could pre-create this UTC issue several hours too
    // early. Remove only an un-settled row; any period tied to a completed bet
    // remains immutable.
    sl_admin_override_remove_unsettled_result($conn, $gameCode, $targetIssue);
    if (sl_admin_override_result_exists($conn, $gameCode, $targetIssue)) {
        $targetIssue = sl_admin_override_period_id(
            $gameCode,
            $now + (int)$config['interval_seconds']
        );
    }

    $premium = (string)$number;
    $createdBy = trim((string)$createdBy);
    $createdBy = $createdBy === '' ? 'admin' : substr($createdBy, 0, 100);
    $manualTable = $config['manual_table'];
    $queueTable = $config['queue_table'];

    $conn->begin_transaction();
    try {
        // Replace only an unresolved selection. Keep settled issue rows so a
        // delayed legacy cron can still resolve the same immutable number.
        sl_admin_override_delete_pending($conn, $gameCode);

        $insert = $conn->prepare('INSERT INTO saas_lottery_overrides(game_code,issue_number,premium,created_by,created_at) VALUES (?,?,?,?,NOW())');
        if (!$insert) {
            throw new RuntimeException('Unable to save the WinGo override');
        }
        $insert->bind_param('ssss', $gameCode, $targetIssue, $premium, $createdBy);
        if (!$insert->execute()) {
            throw new RuntimeException('Unable to save the WinGo override');
        }
        $insert->close();

        if (!sl_admin_override_table_exists($conn, $manualTable)) {
            throw new RuntimeException('Legacy WinGo number table is missing');
        }
        if (!$conn->query("UPDATE `{$manualTable}` SET sthiti='0'")) {
            throw new RuntimeException('Unable to reset the WinGo number state');
        }
        $activate = $conn->prepare("UPDATE `{$manualTable}` SET sthiti='1' WHERE sankhye=?");
        if (!$activate) {
            throw new RuntimeException('Unable to activate the WinGo number');
        }
        $activate->bind_param('i', $number);
        if (!$activate->execute() || $activate->affected_rows < 1) {
            throw new RuntimeException('Selected WinGo number is not available');
        }
        $activate->close();

        // Preserve compatibility with installations that still inspect the
        // one-row legacy queue.  The exact issue remains authoritative above.
        if (sl_admin_override_table_exists($conn, $queueTable)) {
            $conn->query("DELETE FROM `{$queueTable}`");
            $queue = $conn->prepare("INSERT INTO `{$queueTable}` (id,manual_number) VALUES (1,?) ON DUPLICATE KEY UPDATE manual_number=VALUES(manual_number)");
            if ($queue) {
                $queue->bind_param('i', $number);
                $queue->execute();
                $queue->close();
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return array('issue_number' => $targetIssue, 'premium' => $premium);
}

function sl_admin_override_clear($conn, $gameCode)
{
    $config = sl_admin_override_game_config($gameCode);
    if (!$config) {
        throw new InvalidArgumentException('Unsupported WinGo game code');
    }
    sl_admin_override_ensure_schema($conn);

    sl_admin_override_delete_pending($conn, $gameCode);
    $manualTable = $config['manual_table'];
    if (sl_admin_override_table_exists($conn, $manualTable)) {
        $conn->query("UPDATE `{$manualTable}` SET sthiti='0'");
    }
    $queueTable = $config['queue_table'];
    if (sl_admin_override_table_exists($conn, $queueTable)) {
        $conn->query("DELETE FROM `{$queueTable}`");
    }
}

function sl_admin_override_mark_applied($conn, $gameCode, $issueNumber)
{
    $override = sl_admin_override_get($conn, $gameCode, $issueNumber);
    if (!$override) {
        return;
    }
    $config = sl_admin_override_game_config($gameCode);
    if (!$config) {
        return;
    }

    // Keep the period-bound row as an audit/legacy synchronization record.
    // It can never affect another issue because every consumer matches the
    // exact issue_number.  The next admin selection replaces it.
    $manualTable = $config['manual_table'];
    if (sl_admin_override_table_exists($conn, $manualTable)) {
        $conn->query("UPDATE `{$manualTable}` SET sthiti='0'");
    }
    $queueTable = $config['queue_table'];
    if (sl_admin_override_table_exists($conn, $queueTable)) {
        $conn->query("DELETE FROM `{$queueTable}`");
    }
}

/**
 * Global WinGo trend mode.
 *
 * same:      local period N uses provider result N.
 * back_one:  local WinGo clock is one full period behind; provider issue N
 *            stays issue N with its own winning result.
 *
 * Only the WinGo family reads this setting. TrxWinGo, K3, 5D and MotoRace
 * always remain on their normal provider mapping.
 */
function sl_wingo_trend_mode_ensure_schema($conn)
{
    static $installed = false;
    if ($installed) {
        return;
    }
    if (!($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available');
    }
    $sql = "CREATE TABLE IF NOT EXISTS app_settings ("
        . "setting_key VARCHAR(100) NOT NULL,"
        . "setting_value TEXT NOT NULL,"
        . "group_name VARCHAR(80) NOT NULL DEFAULT 'General',"
        . "updated_at DATETIME NOT NULL,"
        . "PRIMARY KEY(setting_key),"
        . "KEY idx_app_settings_group(group_name)"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to prepare the WinGo trend setting');
    }
    $installed = true;
}

function sl_wingo_trend_mode_normalize($mode)
{
    $mode = strtolower(trim((string)$mode));
    return in_array($mode, array('back', 'back_one', 'one_back', '1'), true)
        ? 'back_one'
        : 'same';
}

function sl_wingo_trend_mode_get($conn)
{
    static $cachedMode = null;
    if ($cachedMode !== null) {
        return $cachedMode;
    }
    try {
        sl_wingo_trend_mode_ensure_schema($conn);
        $key = 'wingo_trend_mode';
        $stmt = $conn->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
        if (!$stmt) {
            return 'same';
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $value = null;
        $stmt->bind_result($value);
        $found = $stmt->fetch();
        $stmt->close();
        $cachedMode = $found ? sl_wingo_trend_mode_normalize($value) : 'same';
        return $cachedMode;
    } catch (Throwable $e) {
        error_log('[WinGo trend mode read] ' . $e->getMessage());
        return 'same';
    }
}

function sl_wingo_trend_mode_set($conn, $mode, $changedBy = 'admin')
{
    $mode = sl_wingo_trend_mode_normalize($mode);
    sl_wingo_trend_mode_ensure_schema($conn);
    $key = 'wingo_trend_mode';
    $group = 'Lottery & Game';
    $stmt = $conn->prepare(
        'INSERT INTO app_settings(setting_key,setting_value,group_name,updated_at) '
        . 'VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE '
        . 'setting_value=VALUES(setting_value),group_name=VALUES(group_name),updated_at=NOW()'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to save the WinGo trend mode');
    }
    $stmt->bind_param('sss', $key, $mode, $group);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to save the WinGo trend mode');
    }
    $stmt->close();

    // Keep a small audit trail when the modern audit table is present.
    if (sl_admin_override_table_exists($conn, 'app_audit_logs')) {
        $admin = trim((string)$changedBy);
        $admin = $admin === '' ? 'admin' : substr($admin, 0, 100);
        $action = 'wingo_trend_mode_update';
        $entityType = 'lottery';
        $entityId = 'WinGo';
        $metadata = json_encode(array('mode'=>$mode), JSON_UNESCAPED_SLASHES);
        $audit = $conn->prepare(
            'INSERT INTO app_audit_logs(admin_name,action,entity_type,entity_id,metadata_json,created_at) '
            . 'VALUES (?,?,?,?,?,NOW())'
        );
        if ($audit) {
            $audit->bind_param('sssss', $admin, $action, $entityType, $entityId, $metadata);
            $audit->execute();
            $audit->close();
        }
    }
    return $mode;
}

