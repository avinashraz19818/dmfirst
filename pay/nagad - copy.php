<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ইউজারের লোকাল ডাটাবেজ ফাইল কানেক্ট করা হলো
include("../serive/samparka.php"); 

if (!isset($_GET['uid']) || !isset($_GET['amount'])) { 
    die("Illegal access: UID or Amount missing"); 
}

$uid     = mysqli_real_escape_string($conn, $_GET['uid']);
$ramt    = floatval($_GET['amount']); 
$payName = 'NAGAD'; // গেটওয়ে ফাইলের কন্ডিশন ম্যাচ করার জন্য বড় হাতের 'BKASH'
$serial  = date("Ymd") . time() . rand(100000, 999999);
$createdate = date("Y-m-d H:i:s");

// ১. ডাটাবেজ থেকে মোবাইল নম্বর রিড করা
$q = mysqli_query($conn, "SELECT mobile FROM shonu_subjects WHERE id='$uid'");
$u = mysqli_fetch_array($q);
$mobile = isset($u['mobile']) ? $u['mobile'] : '01700000000';

// ২. ইউজারের সিস্টেমে পেন্ডিং রেকর্ড ইনসার্ট
$insert_query = "INSERT INTO thevani (payid, balakedara, motta, dharavahi, mula, ullekha, duravani, ekikrtapavati, dinankavannuracisi, madari, pavatiaidi, sthiti) 
                 VALUES ('2', '$uid', '$ramt', '$serial', '$payName', 'N/A', '$mobile', 'BKASH', '$createdate', '1005', '2', '0')";
mysqli_query($conn, $insert_query);

/* ================= 👑 GATEWAY CONFIG & REDIRECT ================= */
// আপনার তৈরি করা মেইন গেটওয়ে (create_order.php) এর লিংক
// নোট: আপনার মেইন ফাইলের নাম crate_oders.php নাকি create_order.php তা ইউআরএলে নিশ্চিত হয়ে নিন
$gatewayUrl = "https://api.zenpay.cc/pay.php"; 

// আপনার গেটওয়ের ডাটাবেজে (merchants টেবিলে) যে appId সেট করা আছে তা এখানে দিন
$my_app_id = "6f0c7f9d02c54746150737f027909b52"; 

// নোটিফাই এবং রিটার্ন ইউআরএল
$notifyURL = "https://dmfirst.club9.eu.cc/pay/Zenpay_notify.php";
$jumpURL   = "https://dmfirst.club9.eu.cc"; 

// ৩. ডাটাবেজ কানেকশন ক্লোজ করা
if(isset($conn)) { mysqli_close($conn); }

// ৪. cURL বাদ দিয়ে সরাসরি HTML Form এর মাধ্যমে ব্রাউজার রিকোয়েস্ট পাঠানো (যাতে Cloudflare বা Firewall ব্লক না করে)
?>
<!DOCTYPE html>
<html>
<head>
    <title>zenpay Payment Gateway</title>
</head>
<body onload="document.forms['gateway_form'].submit();">
    <div style="text-align: center; margin-top: 50px; font-family: Arial, sans-serif;">
    </div>
    
    <form id="gateway_form" action="<?php echo htmlspecialchars($gatewayUrl); ?>" method="POST">
        <input type="hidden" name="appId" value="<?php echo htmlspecialchars($my_app_id); ?>">
        <input type="hidden" name="merOrderNo" value="<?php echo htmlspecialchars($serial); ?>">
        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($ramt); ?>">
        <input type="hidden" name="payMethod" value="<?php echo htmlspecialchars($payName); ?>">
        <input type="hidden" name="notifyUrl" value="<?php echo htmlspecialchars($notifyURL); ?>">
        <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($jumpURL); ?>">
        <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>">
    </form>
</body>
</html>
<?php
ob_end_flush();
exit();
?>