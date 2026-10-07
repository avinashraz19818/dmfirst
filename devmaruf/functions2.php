<?php
// functions2.php (FULL WORKING)

// Secret default (same as yours)
define('JWT_DEFAULT_SECRET', 'bdgshonuuncensored');

function base64url_encode($str) {
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}

function base64url_decode($data) {
    // Add required padding back for base64_decode
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function generate_jwt($headers, $payload, $secret = JWT_DEFAULT_SECRET) {
    $headers_encoded = base64url_encode(json_encode($headers, JSON_UNESCAPED_UNICODE));
    $payload_encoded = base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));

    $signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
    $signature_encoded = base64url_encode($signature);

    return "$headers_encoded.$payload_encoded.$signature_encoded";
}

function is_jwt_valid($jwt, $secret = JWT_DEFAULT_SECRET) {
    $res = [
        'status'  => 'Failed',
        'payload' => ''
    ];

    if (!is_string($jwt) || $jwt === '' || substr_count($jwt, '.') !== 2) {
        return json_encode($res);
    }

    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        return json_encode($res);
    }

    $header_b64  = $tokenParts[0];
    $payload_b64 = $tokenParts[1];
    $sig_provided = $tokenParts[2];

    $header  = base64url_decode($header_b64);
    $payload = base64url_decode($payload_b64);

    if ($header === false || $payload === false) {
        return json_encode($res);
    }

    // Recompute signature
    $signature = hash_hmac('SHA256', $header_b64 . "." . $payload_b64, $secret, true);
    $sig_expected = base64url_encode($signature);

    // timing-safe compare
    if (!hash_equals($sig_expected, $sig_provided)) {
        return json_encode($res);
    }

    $decoded = json_decode($payload, true);
    $res['status'] = 'Success';
    $res['payload'] = is_array($decoded) ? $decoded : '';

    return json_encode($res);
}
?>