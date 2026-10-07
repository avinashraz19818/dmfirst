<?php
// cron_commission.php
// এই ফাইলটি প্রতিদিন রাত ১২:০১ মিনিটে ক্রন জবের মাধ্যমে রান করাবেন।

$servername = "localhost";
$username = "club532583_dmfirst";
$password = "club532583_dmfirst";
$dbname = "club532583_dmfirst";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set("Asia/Kolkata"); // আপনার সঠিক টাইমজোন দিন

// ১. 'to_be_add_comission' টেবিল থেকে সারাদিনের আনপ্রসেসড (status=0) ডেটা ইউজার আইডি অনুযায়ী যোগফল বের করা
$sql = "SELECT user_id, SUM(amount) as total_commission FROM to_be_add_comission WHERE status = 0 GROUP BY user_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $total_commission = floatval($row['total_commission']);
        
        if ($total_commission > 0) {
            // ২. ইউজারের বর্তমান মেইন ব্যালেন্স তুলে আনা
            $balquery = "SELECT motta FROM shonu_kaichila WHERE balakedara = '$user_id'";
            $balresult = $conn->query($balquery);
            
            if ($balresult && $balresult->num_rows > 0) {
                $balarr = mysqli_fetch_array($balresult);
                $current_balance = floatval($balarr['motta']);
                
                // নতুন ব্যালেন্স হিসাব
                $new_balance = round(($current_balance + $total_commission), 2);
                
                // ৩. মেইন ব্যালেন্স আপডেট করা
                $update_bal = "UPDATE shonu_kaichila SET motta = '$new_balance' WHERE balakedara = '$user_id'";
                if ($conn->query($update_bal)) {
                    // ৪. এই ইউজারের আজকের প্রসেসড হওয়া কমিশনের স্ট্যাটাস ১ করে দেওয়া
                    $update_status = "UPDATE to_be_add_comission SET status = 1 WHERE user_id = '$user_id' AND status = 0";
                    $conn->query($update_status);
                }
            }
        }
    }
    echo "সারাদিনের সকল মেম্বারদের বেট কমিশন সফলভাবে রাত ১২টার পর অ্যাকাউন্টে যোগ করা হয়েছে।";
} else {
    echo "আজ যোগ করার মতো কোনো পেন্ডিং কমিশন পাওয়া যায়নি।";
}

$conn->close();
?>