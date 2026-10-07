<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load Database Connection and Configurations
require_once("../serive/samparka.php");
require_once("zenpayconfig.php");

// Input Validation
if (!isset($_GET['uid']) || !isset($_GET['amount'])) { 
    die("Illegal access: UID or Amount missing"); 
}

$uid        = mysqli_real_escape_string($conn, $_GET['uid']);
$ramt       = floatval($_GET['amount']); 
$payName    = 'Bkash';
$serial     = date("Ymd") . time() . rand(100000, 999999);
$createdate = date("Y-m-d H:i:s");

// Fetch User Mobile Number
$query  = mysqli_query($conn, "SELECT mobile FROM shonu_subjects WHERE id='$uid'");
$user   = mysqli_fetch_array($query);
$mobile = isset($user['mobile']) ? $user['mobile'] : '01700000000';

// Insert Pending Deposit Record
$insert_query = "INSERT INTO thevani (payid, balakedara, motta, dharavahi, mula, ullekha, duravani, ekikrtapavati, dinankavannuracisi, madari, pavatiaidi, sthiti) 
                 VALUES ('2', '$uid', '$ramt', '$serial', '$payName', 'N/A', '$mobile', 'BKASH', '$createdate', '1005', '2', '0')";
mysqli_query($conn, $insert_query);

// Close Database Connection
if (isset($conn)) { 
    mysqli_close($conn); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connecting to Payment Gateway...</title>
</head>
<body onload="document.forms['gateway_form'].submit();">
    <div style="text-align: center; margin-top: 50px; font-family: Arial, sans-serif;">
        <p>Connecting to secure payment gateway, please wait...</p>
    </div>
    
    <form id="gateway_form" action="<?php echo htmlspecialchars(ZENPAY_GATEWAY_URL); ?>" method="POST">
        <input type="hidden" name="appId" value="<?php echo htmlspecialchars(ZENPAY_APP_ID); ?>">
        <input type="hidden" name="merOrderNo" value="<?php echo htmlspecialchars($serial); ?>">
        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($ramt); ?>">
        <input type="hidden" name="payMethod" value="<?php echo htmlspecialchars($payName); ?>">
        <input type="hidden" name="notifyUrl" value="<?php echo htmlspecialchars(ZENPAY_NOTIFY_URL); ?>">
        <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars(ZENPAY_RETURN_URL); ?>">
        <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>">
    </form>
</body>
</html>
<?php
ob_end_flush();
exit();
?>