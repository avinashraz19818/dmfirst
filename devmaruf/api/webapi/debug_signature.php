<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
// Test data from the actual request
$testData = [
    'smsCode' => '',
    'ifsccode' => 'BARB0VJKHAS',
    'bankid' => 15,
    'beneficiaryname' => 'SAHIL',
    'accountno' => '12365489545',
    'email' => '',
    'mobileno' => '911234567890',
    'bankcitycode' => '',
    'bankprovincecode' => '',
    'bankbranchaddress' => '',
    'type' => '',
    'codeType' => 6,
    'language' => 0,
    'random' => '7cb1583ee22d47f5a678e523f67cd6f7',
    'timestamp' => 1747507387
];

$output = "Debug Results:\n";
$output .= "-------------\n\n";

// Test 1: Original order from request
$output .= "Test 1 - Original order from request:\n";
$jsonStr1 = json_encode($testData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sig1 = strtoupper(md5($jsonStr1));
$output .= "JSON string:\n" . $jsonStr1 . "\n\n";
$output .= "Calculated signature: " . $sig1 . "\n\n";

// Test 2: Ordered data (as in SetWithdrawalBankCard.php)
$orderedData = [
    'accountno' => $testData['accountno'],
    'bankbranchaddress' => $testData['bankbranchaddress'],
    'bankcitycode' => $testData['bankcitycode'],
    'bankid' => intval($testData['bankid']),
    'bankprovincecode' => $testData['bankprovincecode'],
    'beneficiaryname' => $testData['beneficiaryname'],
    'codeType' => intval($testData['codeType']),
    'email' => $testData['email'],
    'ifsccode' => $testData['ifsccode'],
    'language' => intval($testData['language']),
    'mobileno' => $testData['mobileno'],
    'random' => $testData['random'],
    'smsCode' => $testData['smsCode'],
    'timestamp' => intval($testData['timestamp']),
    'type' => $testData['type']
];

$jsonStr2 = json_encode($orderedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sig2 = strtoupper(md5($jsonStr2));
$output .= "Test 2 - Ordered data (as in SetWithdrawalBankCard.php):\n";
$output .= "JSON string:\n" . $jsonStr2 . "\n\n";
$output .= "Calculated signature: " . $sig2 . "\n\n";

// Expected signature from request
$output .= "Expected signature from request: 997379E71822FE3839EDBC0DAA0CFCE8\n\n";

// Compare strings character by character
$output .= "Character comparison of JSON strings:\n";
if ($jsonStr1 !== $jsonStr2) {
    $output .= "JSON strings are different!\n";
    $output .= "String 1 length: " . strlen($jsonStr1) . "\n";
    $output .= "String 2 length: " . strlen($jsonStr2) . "\n";
    for ($i = 0; $i < max(strlen($jsonStr1), strlen($jsonStr2)); $i++) {
        if (!isset($jsonStr1[$i]) || !isset($jsonStr2[$i]) || $jsonStr1[$i] !== $jsonStr2[$i]) {
            $output .= sprintf(
                "Difference at position %d: [%s] vs [%s]\n",
                $i,
                isset($jsonStr1[$i]) ? $jsonStr1[$i] : 'NULL',
                isset($jsonStr2[$i]) ? $jsonStr2[$i] : 'NULL'
            );
        }
    }
}

// Write output to file
file_put_contents('debug_results.txt', $output);
echo "Debug results have been written to debug_results.txt\n";
?>