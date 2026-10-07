<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set("Asia/Dhaka");

/* ===== BALANCE API CONFIG ===== */
$appId     = "6752332636";
$secretKey = "749bbbb46aaa4cb7bd6e6841dcb3512e";
$baseUrl   = "http://150.5.128.21/apid/newbankPay/selUser.do";

$params = ["appId" => $appId, "time" => date("YmdHis")];
ksort($params);
$signStr = "";
foreach ($params as $k => $v) { $signStr .= $k . "=" . $v . "&"; }
$signStr .= "key=" . $secretKey;
$params["sign"] = strtoupper(md5($signStr));

$finalUrl = $baseUrl . "?" . http_build_query($params);
$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => $finalUrl, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$balance  = $result['data']['amt'] ?? $result['data']['balance'] ?? "0.00";
$username = $result['data']['userName'] ?? "Premium Merchant";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animated Payout Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bkash: #d12053;
            --nagad: #f37021;
            --primary: #6366f1;
            --bg: #f0f4f8;
            --card: #ffffff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(209, 32, 83, 0.05) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1000px;
            overflow-x: hidden;
        }

        .app-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        /* Floating Animation for Balance Card */
        .balance-card {
            background: var(--card);
            border-radius: 35px;
            padding: 35px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            border: 1px solid rgba(255,255,255,0.7);
            animation: float 4s ease-in-out infinite, introUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
            position: relative;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        @keyframes introUp {
            from { opacity: 0; transform: translateY(50px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .balance-card h2 {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(90deg, #1e293b, #64748b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 10px 0;
        }

        .merchant-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
        }

        /* Withdrawal Card with Slide-in */
        .withdraw-container {
            background: var(--card);
            border-radius: 40px;
            padding: 35px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            animation: introUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.1s backwards;
        }

        /* Animated Tabs */
        .tabs {
            display: flex;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 25px;
            margin-bottom: 30px;
            position: relative;
        }

        .tab-item {
            flex: 1; padding: 14px; border: none; background: transparent;
            cursor: pointer; font-weight: 800; font-size: 14px; color: #94a3b8;
            z-index: 2; transition: 0.4s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }

        .tab-item.active { color: #1e293b; }
        .tab-item img { height: 20px; transition: 0.4s; filter: grayscale(1); }
        .tab-item.active img { filter: grayscale(0); transform: scale(1.1); }

        .indicator {
            position: absolute; height: calc(100% - 12px); width: calc(50% - 6px);
            background: white; border-radius: 20px; top: 6px; left: 6px;
            transition: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1;
        }

        /* Form Animation */
        .form-panel { display: none; }
        .form-panel.active { display: block; animation: panelIn 0.5s cubic-bezier(0.4, 0, 0.2, 1); }

        @keyframes panelIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label {
            display: block; font-size: 12px; font-weight: 800;
            color: #94a3b8; margin-bottom: 10px; padding-left: 5px; text-transform: uppercase;
        }

        .input-group input {
            width: 100%; padding: 18px 25px; border-radius: 20px;
            border: 2px solid #f1f5f9; background: #f8fafc;
            font-size: 16px; font-weight: 600; outline: none; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-group input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }

        /* Button Pulse */
        .action-btn {
            width: 100%; padding: 20px; border: none; border-radius: 22px;
            color: white; font-size: 17px; font-weight: 800; cursor: pointer;
            transition: 0.4s; display: flex; align-items: center; justify-content: center; gap: 12px;
            position: relative; overflow: hidden;
        }

        .btn-bkash { background: var(--bkash); box-shadow: 0 10px 25px rgba(209, 32, 83, 0.2); }
        .btn-nagad { background: var(--nagad); box-shadow: 0 10px 25px rgba(243, 112, 33, 0.2); }

        .action-btn:hover { transform: scale(1.03); filter: brightness(1.1); }
        .action-btn:active { transform: scale(0.97); }

        /* Loader */
        .ripple {
            width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Status Toast */
        .status-toast {
            margin-top: 20px; padding: 18px; border-radius: 20px;
            text-align: center; font-size: 14px; font-weight: 700; display: none;
            animation: toastIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes toastIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .success { background: #ecfdf5; color: #059669; border: 1px solid #10b98133; }
        .error { background: #fef2f2; color: #dc2626; border: 1px solid #ef444433; }

    </style>
</head>
<body>

<div class="app-wrapper">
    <div class="balance-card">
        <p style="color: #94a3b8; font-weight: 700; font-size: 12px; text-transform: uppercase;">Available Payout</p>
        <h2 id="balance-txt">৳ 0.00</h2>
        <div class="merchant-badge">
            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
            <?php echo htmlspecialchars($username); ?>
        </div>
    </div>

    <div class="withdraw-container">
        <div class="tabs">
            <div class="indicator" id="tab-ind"></div>
            <button class="tab-item active" onclick="changeTab('bkash', 0)">
                <img src="https://ossimg.crhhh.com/bdtgame/payNameIcon/payNameIcon2_202305271852268s4b.png"> bKash
            </button>
            <button class="tab-item" onclick="changeTab('nagad', 1)">
                <img src="https://ossimg.crhhh.com/bdtgame/payNameIcon/payNameIcon2_202305271853108ybn.png"> Nagad
            </button>
        </div>

        <div id="bkash-pnl" class="form-panel active">
            <form onsubmit="fireWithdraw(event, 'wdbkashbd.php')">
                <div class="input-group">
                    <label>Receiver Name</label>
                    <input type="text" name="name" placeholder="Full Name" required>
                </div>
                <div class="input-group">
                    <label>bKash Number</label>
                    <input type="tel" name="number" placeholder="017XXXXXXXX" required pattern="[0-9]{11}">
                </div>
                <div class="input-group">
                    <label>Amount (Min. 100)</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                </div>
                <button type="submit" class="action-btn btn-bkash">
                    <div class="ripple"></div>
                    <span class="bt">Withdraw via bKash</span>
                </button>
                <div class="status-toast"></div>
            </form>
        </div>

        <div id="nagad-pnl" class="form-panel">
            <form onsubmit="fireWithdraw(event, 'wdnagadbd.php')">
                <div class="input-group">
                    <label>Receiver Name</label>
                    <input type="text" name="name" placeholder="Full Name" required>
                </div>
                <div class="input-group">
                    <label>Nagad Number</label>
                    <input type="tel" name="number" placeholder="01XXXXXXXXX" required pattern="[0-9]{11}">
                </div>
                <div class="input-group">
                    <label>Amount (Min. 100)</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                </div>
                <button type="submit" class="action-btn btn-nagad">
                    <div class="ripple"></div>
                    <span class="bt">Withdraw via Nagad</span>
                </button>
                <div class="status-toast"></div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Ultra-Smooth Counter
    const targetVal = parseFloat("<?php echo $balance; ?>");
    const balEl = document.getElementById('balance-txt');
    let startVal = 0;
    const duration = 1500; // 1.5 seconds
    const startTime = performance.now();

    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOutQuad = t => t * (2 - t); // Easing function
        
        const currentVal = startVal + (targetVal - startVal) * easeOutQuad(progress);
        balEl.innerText = "৳ " + currentVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        if (progress < 1) requestAnimationFrame(updateCounter);
    }
    requestAnimationFrame(updateCounter);

    // 2. Tab Switch Animation
    function changeTab(type, pos) {
        document.getElementById('tab-ind').style.transform = `translateX(${pos * 100}%)`;
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-item')[pos].classList.add('active');
        
        document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
        document.getElementById(`${type}-pnl`).classList.add('active');
    }

    // 3. Logic & API Handling
    async function fireWithdraw(e, url) {
        e.preventDefault();
        const f = e.target;
        const b = f.querySelector('button');
        const l = f.querySelector('.ripple');
        const t = f.querySelector('.bt');
        const toast = f.querySelector('.status-toast');
        const amt = parseFloat(f.querySelector('input[name="amount"]').value);

        toast.style.display = "none";

        // Business Logic
        if (amt < 100) { showToast(toast, "Minimum 100 BDT required!", "error"); return; }
        if (targetVal < 110) { showToast(toast, "Insufficient gateway balance (Min 110)", "error"); return; }

        // UI Loading
        b.disabled = true;
        l.style.display = "block";
        t.innerText = "Encrypting...";

        try {
            const res = await fetch(url, { method: 'POST', body: new FormData(f) });
            const result = await res.text();
            
            let finalMsg = result;
            if (result.includes("未加白")) finalMsg = "Security: IP Not Whitelisted.";
            if (result.includes("成功")) finalMsg = "Success! Funds are on the way.";

            if (result.includes("成功") || result.toLowerCase().includes("success")) {
                showToast(toast, finalMsg, "success");
                f.reset();
            } else {
                showToast(toast, finalMsg, "error");
            }
        } catch (e) {
            showToast(toast, "Network error. Try again.", "error");
        } finally {
            b.disabled = false;
            l.style.display = "none";
            t.innerText = "Withdraw Now";
        }
    }

    function showToast(el, m, type) {
        el.innerText = m;
        el.className = `status-toast ${type}`;
        el.style.display = "block";
    }
</script>

</body>
</html>