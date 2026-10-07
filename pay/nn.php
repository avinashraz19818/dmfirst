<?php

// ১. আপনার ট্রানজেকশন তথ্য
$currency = "BDT";
$transactionId = "S54425EAE1ABEE12F955372D4A4407C04A12279D232D9"; // আপনার URL এর শেষের অংশ

// ২. ডায়নামিক API URL তৈরি
$apiUrl = "https://cashier.wpay.life/Cashier/Api/TransactionStatus/" . $currency . "/" . $transactionId;

// ৩. cURL দিয়ে সরাসরি API-তে কল করা
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json, text/javascript, */*; q=0.01',
    'X-Requested-With: XMLHttpRequest',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);

$response = curl_exec($ch);
curl_close($ch);

// ৪. JSON রেসপন্স পার্স করা
if ($response) {
    $data = json_decode($response, true);

    // API থেকে জেনুইন URL চেক করা
    if (isset($data['code']) && ($data['code'] === 1 || $data['code'] === 4) && !empty($data['data'])) {
        $finalPayUrl = $data['data'];
        
        // কাঙ্ক্ষিত মূল URL প্রিন্ট করা
        echo "Payment URL: " . $finalPayUrl;
    } elseif (isset($data['code']) && $data['code'] === 2) {
        // যদি কাস্টম বিডিটি পেজ হয়
        echo "BDT Page Data: " . print_r($data['data'], true);
    } else {
        echo "API Response: অর্ডার প্রসেসিং হচ্ছে অথবা স্ট্যাটাস জিরো (Code: " . ($data['code'] ?? 'Unknown') . ")";
    }
} else {
    echo "API এর সাথে কানেক্ট করা সম্ভব হয়নি।";
}

?>