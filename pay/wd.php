<?php
/**
 * =========================================================================================
 * ENTERPRISE FINTECH PAYOUT & BALANCE DASHBOARD SYSTEM
 * Architecture: Single-File PHP MVC Hybrid with Embedded Dynamic UI/UX System
 * Language: PHP 8.x + HTML5 + Modern CSS3 (Variables, Grid, Flexbox) + Vanilla ES6 JS
 * =========================================================================================
 */

header('Content-Type: text/html; charset=utf-8');

// =========================================================================================
// SECTION 1: GLOBAL CONFIGURATION & SECURITY HEADERS
// =========================================================================================
define('APP_NAME', 'OKExPay Enterprise Terminal');
define('APP_VERSION', 'v4.2.0-PROD');

$mchId      = '5442';
$apiKey     = '3ff34e4ea7cb86fee514ebc95d16da8f';
$balanceUrl = 'https://api.wpay.life/v1/balance';
$payoutUrl  = 'https://api.wpay.life/v1/Payout';

// =========================================================================================
// SECTION 2: HELPER FUNCTIONS & API WRAPPERS
// =========================================================================================

function generateSign(array $params, string $apiKey): string {
    $filtered = array_filter($params, function ($v, $k) {
        return $k !== 'sign' && $v !== '' && $v !== null;
    }, ARRAY_FILTER_USE_BOTH);

    ksort($filtered);

    $signParts = [];
    foreach ($filtered as $k => $v) {
        $signParts[] = $k . '=' . $v;
    }

    $signString = implode('&', $signParts) . '&key=' . $apiKey;
    return strtolower(md5($signString));
}

function executeCurl(string $url, array $params): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['code' => -1, 'msg' => 'Network Connection Failure: ' . $error];
    }

    return json_decode($response, true) ?? ['code' => -2, 'msg' => 'Invalid JSON Response Payload'];
}

// =========================================================================================
// SECTION 3: CORE APPLICATION BUSINESS LOGIC
// =========================================================================================

// Fetch Merchant Balance
$balanceParams = ['mchId' => $mchId, 'currency' => 'BDT'];
$balanceParams['sign'] = generateSign($balanceParams, $apiKey);
$balanceRes = executeCurl($balanceUrl, $balanceParams);

$foundBalance = '0.00';
$balanceStatus = false;

if (isset($balanceRes['code']) && $balanceRes['code'] == 0 && !empty($balanceRes['data']['Balance'])) {
    foreach ($balanceRes['data']['Balance'] as $item) {
        if (strtoupper($item['code']) === 'BDT') {
            $foundBalance = number_format((float)$item['balance'], 2, '.', ',');
            $balanceStatus = true;
            break;
        }
    }
}

// Channel Limit Definitions based on Admin Panel
$channelLimits = [
    'BKASH'  => ['min' => 300, 'max' => 50000],
    'NAGAD'  => ['min' => 300, 'max' => 50000],
    'ROCKET' => ['min' => 100, 'max' => 25000]
];

// Handle Payout Form Request
$payoutRes = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payout'])) {
    $amount = (int)$_POST['money'];
    $payType = strtoupper($_POST['pay_type']);

    $minLimit = $channelLimits[$payType]['min'] ?? 300;
    $maxLimit = $channelLimits[$payType]['max'] ?? 50000;

    if ($amount < $minLimit || $amount > $maxLimit) {
        $payoutRes = [
            'code' => 1,
            'msg' => "Transaction Rejected: For {$payType}, limit threshold is minimum {$minLimit} BDT and maximum " . number_format($maxLimit) . " BDT."
        ];
    } else {
        $payoutParams = [
            'mchId'        => $mchId,
            'currency'     => 'BDT',
            'out_trade_no' => 'WD' . date('YmdHis') . rand(1000, 9999),
            'pay_type'     => $payType,
            'account'      => trim($_POST['account']),
            'userName'     => trim($_POST['userName']),
            'money'        => $amount,
            'attach'       => 'system_portal_withdrawal',
            'notify_url'   => 'https://www.sandbox.wpay.one/callback/payout'
        ];

        $payoutParams['sign'] = generateSign($payoutParams, $apiKey);
        $payoutRes = executeCurl($payoutUrl, $payoutParams);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo APP_NAME; ?> - Enterprise Terminal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-body: #090d16;
            --bg-side: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-active: rgba(99, 102, 241, 0.5);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.35);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-sub: #64748b;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --sidebar-width: 280px;
            --radius-lg: 16px;
            --radius-md: 12px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-sans); background-color: var(--bg-body); color: var(--text-main); min-height: 100vh; }
        .app-container { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: var(--sidebar-width); background: var(--bg-side);
            border-right: 1px solid var(--border-glass);
            display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100;
        }
        .brand-header { padding: 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-glass); }
        .brand-logo { width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), #a855f7); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; }
        .brand-title { font-size: 18px; font-weight: 800; }
        .brand-title span { color: var(--primary); }

        .nav-menu { padding: 20px 16px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; border-radius: var(--radius-md); font-weight: 600; font-size: 14px; }
        .nav-item.active { background: rgba(99, 102, 241, 0.12); color: #fff; border: 1px solid var(--border-active); }

        .main-wrapper { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; }
        .topbar { height: 75px; border-bottom: 1px solid var(--border-glass); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; background: rgba(9, 13, 22, 0.8); }
        .content-body { padding: 32px; flex: 1; }

        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-lg); padding: 24px; }
        .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .stat-title { font-size: 13px; color: var(--text-muted); font-weight: 600; }
        .stat-value { font-size: 26px; font-weight: 800; font-family: var(--font-mono); }

        .dashboard-main-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; }
        .panel-box { background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-lg); padding: 28px; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-glass); }
        .panel-title { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        .form-layout { display: flex; flex-direction: column; gap: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; justify-content: space-between; }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 16px; color: var(--text-sub); }
        .custom-input { width: 100%; padding: 14px 16px 14px 44px; background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-glass); border-radius: var(--radius-md); color: #fff; font-size: 14px; }
        .custom-input:focus { outline: none; border-color: var(--primary); }

        .method-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .method-card { background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 12px; text-align: center; cursor: pointer; }
        .method-card input { display: none; }
        .method-card.active { background: rgba(99, 102, 241, 0.15); border-color: var(--primary); }
        .method-card span { display: block; font-size: 12px; font-weight: 700; margin-top: 4px; }

        .btn-primary { width: 100%; padding: 16px; background: linear-gradient(135deg, var(--primary), #4338ca); border: none; border-radius: var(--radius-md); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; }
        .alert-box { padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px; display: flex; gap: 12px; }
        .alert-success { background: rgba(16, 185, 129, 0.12); border: 1px solid var(--success); color: #34d399; }
        .alert-danger { background: rgba(239, 68, 68, 0.12); border: 1px solid var(--danger); color: #f87171; }
    </style>
</head>
<body>

<div class="app-container">
    <aside class="sidebar">
        <div class="brand-header">
            <div class="brand-logo"><i class="fa-solid fa-bolt"></i></div>
            <div class="brand-title">OKEX<span>PAY</span></div>
        </div>
        <nav class="nav-menu">
            <a href="#" class="nav-item active"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-paper-plane"></i><span>Payout Engine</span></a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div style="font-size: 14px; font-weight: 600;">Merchant Terminal ID: MCH-<?php echo $mchId; ?></div>
        </header>

        <main class="content-body">
            <section class="analytics-grid">
                <div class="stat-card">
                    <div class="stat-header"><span class="stat-title">Available BDT Balance</span></div>
                    <div class="stat-value"><?php echo $foundBalance; ?> BDT</div>
                </div>
            </section>

            <div class="dashboard-main-grid">
                <section class="panel-box">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-paper-plane"></i> Instant Payout Dispatcher</div>
                    </div>

                    <?php if ($payoutRes !== null): ?>
                        <?php if (isset($payoutRes['code']) && $payoutRes['code'] == 0): ?>
                            <div class="alert-box alert-success">
                                <i class="fa-solid fa-circle-check"></i>
                                <div>
                                    <strong>Payout Request Dispatched Successfully!</strong><br>
                                    <span>Transaction ID: <?php echo htmlspecialchars($payoutRes['data']['transaction_Id'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert-box alert-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <div>
                                    <strong>API Execution Error</strong><br>
                                    <span><?php echo htmlspecialchars($payoutRes['msg'] ?? 'Unknown Error'); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="POST" class="form-layout" id="payoutForm">
                        <div class="form-group">
                            <label>Payment Channel Method</label>
                            <div class="method-selector">
                                <label class="method-card active" onclick="selectMethod(this, 'BKASH', 300, 50000)">
                                    <input type="radio" name="pay_type" value="BKASH" checked>
                                    <i class="fa-solid fa-mobile-screen-button" style="color: #e2136e;"></i>
                                    <span>bKash</span>
                                </label>
                                <label class="method-card" onclick="selectMethod(this, 'NAGAD', 300, 50000)">
                                    <input type="radio" name="pay_type" value="NAGAD">
                                    <i class="fa-solid fa-wallet" style="color: #f7921e;"></i>
                                    <span>Nagad</span>
                                </label>
                                <label class="method-card" onclick="selectMethod(this, 'ROCKET', 100, 25000)">
                                    <input type="radio" name="pay_type" value="ROCKET">
                                    <i class="fa-solid fa-building-columns" style="color: #8c3494;"></i>
                                    <span>Rocket</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="account">Beneficiary Account</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-hashtag"></i>
                                    <input type="text" name="account" id="account" class="custom-input" placeholder="01700000000" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="userName">Beneficiary Name</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" name="userName" id="userName" class="custom-input" value="Zhang San" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="money">
                                <span>Transfer Amount (BDT)</span>
                                <span id="limitNotice" style="color: var(--primary);">Limit: 300 - 50,000 BDT</span>
                            </label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-bangladeshi-taka-sign"></i>
                                <input type="number" name="money" id="money" class="custom-input" min="300" max="50000" placeholder="Enter amount..." required>
                            </div>
                        </div>

                        <button type="submit" name="submit_payout" class="btn-primary">
                            <i class="fa-solid fa-paper-plane"></i> Execute Withdrawal Request
                        </button>
                    </form>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
    let currentMin = 300;
    let currentMax = 50000;

    function selectMethod(element, method, min, max) {
        document.querySelectorAll('.method-card').forEach(card => card.classList.remove('active'));
        element.classList.add('active');
        
        const radio = element.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;

        currentMin = min;
        currentMax = max;

        const moneyInput = document.getElementById('money');
        moneyInput.min = min;
        moneyInput.max = max;

        document.getElementById('limitNotice').innerText = `Limit: ${min} - ${max.toLocaleString()} BDT`;
    }

    document.getElementById('payoutForm').addEventListener('submit', function(e) {
        const amountInput = document.getElementById('money');
        const val = parseInt(amountInput.value);

        if (val < currentMin || val > currentMax) {
            e.preventDefault();
            alert(`Invalid Amount! For selected method, the amount must be between ${currentMin} BDT and ${currentMax.toLocaleString()} BDT.`);
        }
    });
</script>

</body>
</html>