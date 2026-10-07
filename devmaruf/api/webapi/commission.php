<?php
// Database connection
$servername = "localhost";
$username = "club532583_dmfirst";
$password = "club532583_dmfirst";
$dbname = "club532583_dmfirst";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

// Commission values from database
$sql = "SELECT level1, level2, level3, level4, level5, level6 FROM web_commission LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
	$row = $result->fetch_assoc();
	$level1commission = $row['level1'];
	$level2commission = $row['level2'];
	$level3commission = $row['level3'];
	$level4commission = $row['level4'];
	$level5commission = $row['level5'];
	$level6commission = $row['level6'];
} else {
	die("No commission data found.");
}

// ভ্যারিয়েবল চেক (যদি মেইন ফাইল থেকে সেট না হয়ে আসে, তবে সেফটি ব্যাকআপ)
$totalamount = isset($totalamount) ? floatval($totalamount) : 0;
$byabaharkarta = isset($byabaharkarta) ? mysqli_real_escape_string($conn, $byabaharkarta) : 0;
if(!isset($shnunc)) {
    date_default_timezone_set("Asia/Kolkata");
    $shnunc = date("Y-m-d H:i:s");
}

// কমিশন পার্সেন্টেজ হিসাব
$level1 = round(($totalamount * floatval($level1commission) / 100), 2);
$level2 = round(($totalamount * floatval($level2commission) / 100), 2);
$level3 = round(($totalamount * floatval($level3commission) / 100), 2);
$level4 = round(($totalamount * floatval($level4commission) / 100), 2);
$level5 = round(($totalamount * floatval($level5commission) / 100), 2);
$level6 = round(($totalamount * floatval($level6commission) / 100), 2);

// ইউজার আপলাইন কোড তুলে আনা
$codesquery = "SELECT code, code1, code2, code3, code4, code5 FROM shonu_subjects WHERE id = '$byabaharkarta'";
$codesresult = $conn->query($codesquery);
$codesarr = mysqli_fetch_array($codesresult);

$levels_config = [
    1 => ['code' => isset($codesarr['code']) ? $codesarr['code'] : '', 'amt' => $level1, 'type' => 'LVLCOMM1'],
    2 => ['code' => isset($codesarr['code1']) ? $codesarr['code1'] : '', 'amt' => $level2, 'type' => 'LVLCOMM2'],
    3 => ['code' => isset($codesarr['code2']) ? $codesarr['code2'] : '', 'amt' => $level3, 'type' => 'LVLCOMM3'],
    4 => ['code' => isset($codesarr['code3']) ? $codesarr['code3'] : '', 'amt' => $level4, 'type' => 'LVLCOMM4'],
    5 => ['code' => isset($codesarr['code4']) ? $codesarr['code4'] : '', 'amt' => $level5, 'type' => 'LVLCOMM5'],
    6 => ['code' => isset($codesarr['code5']) ? $codesarr['code5'] : '', 'amt' => $level6, 'type' => 'LVLCOMM6']
];

// ডেটাবেজ টেবিল স্ট্রাকচার অটো-ফিক্স ফাংশন
function add_to_be($userid, $amount, $amountbet, $level = 1)
{
	global $conn;
	
	// টেবিল ও কলাম ঠিক আছে কিনা তা নিশ্চিত করা (নতুবা তৈরি করবে)
	$conn->query("CREATE TABLE IF NOT EXISTS `to_be_add_comission` (
	    `id` INT AUTO_INCREMENT PRIMARY KEY,
	    `user_id` INT NOT NULL,
	    `type` VARCHAR(50) DEFAULT 'betcommission',
	    `amount` DECIMAL(10,2) NOT NULL,
	    `level` INT NOT NULL,
	    `status` INT DEFAULT 0,
	    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	)");
	
	// status = 0 (রাত ১২টার জমার জন্য পেন্ডিং)
	$query = "INSERT INTO `to_be_add_comission` (user_id, type, amount, level, status)  
	          VALUES ('$userid', 'betcommission', '$amount', '$level', 0)";
	$conn->query($query);
}

// লুপের মাধ্যমে ১ থেকে 6 লেভেলের কমিশন প্রসেস করা
foreach ($levels_config as $lvl => $cfg) {
    if (!empty($cfg['code'])) {
        $codeqr = "SELECT id FROM shonu_subjects WHERE owncode = '" . mysqli_real_escape_string($conn, $cfg['code']) . "'";
        $coders = $conn->query($codeqr);
        if ($coders && mysqli_num_rows($coders) > 0) {
            $codear = mysqli_fetch_array($coders);
            $codeid = $codear['id'];
            
            if ($cfg['amt'] > 0) {
                // ১. পেন্ডিং টেবিলে কমিশন যোগ
                add_to_be($codeid, $cfg['amt'], $totalamount, $lvl);
                
                // ২. ট্রানজেকশন লগ বা হিস্ট্রি রাখা
                mysqli_query($conn, "INSERT INTO `vyavahara` (`balakedara`,`ketebida`,`prakara`,`purba`,`bartaman`,`ayoga`,`koduvavanu`,`tiarikala`) 
                VALUES ('" . $codeid . "','" . $totalamount . "','" . $cfg['type'] . "','0','0','" . $cfg['amt'] . "','" . $byabaharkarta . "','" . $shnunc . "')");
            }
        }
    }
}
?>