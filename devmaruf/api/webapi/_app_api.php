<?php
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/functions2.php';
require_once dirname(__DIR__, 2) . '/app_core.php';

app_install_schema($conn);
app_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, AR-REAL-IP');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Public APIs are first-party. Only echo a browser Origin when it belongs to
// this installation (or was explicitly allowed by APP_ALLOWED_ORIGINS).
$appApiOrigin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($appApiOrigin !== '') {
    $originHost = strtolower((string)parse_url($appApiOrigin, PHP_URL_HOST));
    $requestHost = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $allowed = array_filter(array_map('trim', explode(',', (string)getenv('APP_ALLOWED_ORIGINS'))));
    if (($originHost !== '' && hash_equals($requestHost, $originHost)) || in_array($appApiOrigin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $appApiOrigin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function app_api_input(): array
{
    $data = $_POST;
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }
    return $data;
}

function app_api_send($data = null, int $code = 0, string $msg = 'Succeed', int $http = 200, ?int $msgCode = null): void
{
    http_response_code($http);
    echo json_encode([
        'data' => $data,
        'code' => $code,
        'msg' => $msg,
        'msgCode' => $msgCode ?? ($code === 0 ? 0 : $code),
        'serviceNowTime' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function app_api_bearer(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim((string)$header), $m) ? trim($m[1]) : '';
}

function app_api_user(): array
{
    global $conn;
    $token = app_api_bearer();
    if ($token === '') {
        app_api_send(null, 4, 'No operation permission', 401);
    }
    $verified = json_decode(is_jwt_valid($token), true);
    $id = (int)($verified['payload']['id'] ?? 0);
    if (!is_array($verified) || ($verified['status'] ?? '') !== 'Success' || $id < 1) {
        app_api_send(null, 4, 'No operation permission', 401);
    }
    $stmt = $conn->prepare('SELECT id,mobile,akshinak,status FROM shonu_subjects WHERE id=? AND akshinak=? LIMIT 1');
    if (!$stmt) {
        app_api_send(null, 8, 'Service temporarily unavailable', 503, 8);
    }
    $stmt->bind_param('is', $id, $token);
    $stmt->execute();
    $dbId = null;
    $mobile = null;
    $dbToken = null;
    $status = null;
    $stmt->bind_result($dbId, $mobile, $dbToken, $status);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found || (int)$status !== 1) {
        app_api_send(null, 4, 'No operation permission', 401, 2);
    }
    return ['id' => (int)$dbId, 'mobile' => (string)$mobile, 'akshinak' => (string)$dbToken];
}

/** Match the signer used by the bundled Vue application. */
function app_api_signature_valid(array $body): bool
{
    $provided = strtoupper(trim((string)($body['signature'] ?? '')));
    if ($provided === '' || !preg_match('/^[A-F0-9]{32}$/', $provided)) {
        return false;
    }
    unset($body['signature'], $body['timestamp'], $body['track'], $body['xosoBettingData']);
    foreach ($body as $key => $value) {
        if ($value === null || $value === '') {
            unset($body[$key]);
        }
    }
    ksort($body, SORT_STRING);
    $canonical = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($canonical) && hash_equals(strtoupper(md5($canonical)), $provided);
}

function app_api_require_signature(array $body): void
{
    if (!app_api_signature_valid($body)) {
        app_api_send(null, 5, 'Wrong signature', 200, 3);
    }
}

/** Support the legacy MD5 rows and modern password_hash rows. */
function app_api_password_matches(string $plain, string $stored): bool
{
    return app_password_matches($plain, $stored);
}

/** Use password_hash when the legacy column is wide enough, otherwise retain MD5 compatibility. */
function app_api_password_value(mysqli $db, string $plain): string
{
    return app_password_value($db, $plain, 'shonu_subjects', 'password');
}

function app_api_bank_catalog(): array
{
    return [
        1=>'Axis Bank',2=>'Indian Bank',3=>'State Bank of India',4=>'Kotak Mahindra Bank',
        5=>'Canara Bank',6=>'ICICI Bank',7=>'Punjab National Bank',8=>'Bank of India',
        9=>'IDBI Bank',10=>'Standard Chartered Bank',11=>'Karnataka Bank',12=>'HDFC Bank',
        13=>'Yes Bank',14=>'Central Bank of India',15=>'Union Bank of India',16=>'Bank of Baroda',
        17=>'Federal Bank',18=>'Syndicate Bank',20=>'Bank of Maharashtra',22=>'Citibank India',
        23=>'Indian Overseas Bank',24=>'IDFC First Bank',25=>'Bandhan Bank',26=>'IndusInd Bank',
        27=>'Jammu & Kashmir Bank',28=>'Paytm Payments Bank',29=>'Equitas Bank',30=>'India Post Payments Bank',
        31=>'Corporation Bank',32=>'City Union Bank',33=>'Karur Vysya Bank',34=>'Tamilnad Mercantile Bank',
        35=>'Allahabad Bank',36=>'Varachha Co-operative Bank',37=>'Meghalaya Rural Bank',38=>'AU Small Finance Bank',
        39=>'Lakshmi Vilas Bank',40=>'South Indian Bank',41=>'Bassein Catholic Co-operative Bank',
        42=>'Airtel Payments Bank',43=>'State Bank of Hyderabad',44=>'GP Parsik Bank',45=>'Kerala Gramin Bank',
        46=>'RBL Bank',47=>'Dhanlaxmi Bank',48=>'TJSB Bank',49=>'Punjab & Sind Bank',50=>'Purvanchal Bank',
        51=>'Sarva Haryana Gramin Bank',52=>'Ahmedabad District Co-operative Bank',53=>'Fino Payments Bank',
        54=>'Saraswat Cooperative Bank',55=>'TRC20',57=>'Andhra Pragathi Grameena Bank',
        58=>'Rajasthan Marudhara Gramin Bank',59=>'Abhyudaya Bank',60=>'Ujjivan Small Finance Bank',
        61=>'Pragathi Krishna Gramin Bank',62=>'Dhanlaxmi Bank',63=>'Telangana Grameena Bank',
        64=>'Capital Small Finance Bank',65=>'Mizoram Rural Bank',66=>'Andhra Pradesh Grameena Vikas Bank',
        67=>'Karnataka Vikas Grameena Bank',68=>'Ahmedabad Mercantile Co-operative Bank',
        69=>'Madhya Bihar Gramin Bank',70=>'NSDL Payments Bank',71=>'ESAF Small Finance Bank',
        72=>'Himachal Pradesh State Cooperative Bank',73=>'Maharashtra State Cooperative Bank',
        74=>'Oriental Bank of Commerce',75=>'Nainital Bank',76=>'Telangana Grameena Bank',
        77=>'Jharkhand Rajya Gramin Bank',78=>'Jio Payments Bank',79=>'Maharashtra Gramin Bank',
        80=>'Airtel Payments Bank',81=>'Uttarakhand Gramin Bank',82=>'DBS Bank',
        83=>'Equitas Small Finance Bank',84=>'Himachal Pradesh Gramin Bank',
        85=>'Krishna District Co-operative Central Bank',86=>'Rajkot Nagarik Sahakari Bank',
        87=>'North East Small Finance Bank',88=>'CSB Bank',89=>'Fincare Small Finance Bank',
        90=>'Baroda Uttar Pradesh Gramin Bank',91=>'Dhanalakshmi Bank',92=>'Cosmos Co-operative Bank',
        93=>'Saurashtra Gramin Bank',94=>'Baroda Rajasthan Kshetriya Gramin Bank',95=>'Suco Bank',
        96=>'Jana Small Finance Bank',97=>'Other Bank',98=>'Dena Gujarat Gramin Bank',
        99=>'Chaitanya Godavari Grameena Bank',100=>'SVC Bank',101=>'Bharat Co-operative Bank',
        102=>'Surat District Co-operative Bank',103=>'USDT',104=>'Kalupur Commercial Co-operative Bank',
        105=>'India Post Payments Bank',106=>'Prime Co-operative Bank',107=>'Tripura Gramin Bank',
        108=>'Zila Sahakari Bank Bareilly',109=>'Aryavart Bank',110=>'Development Credit Bank',
        111=>'Ujjivan Small Finance Bank',112=>'Sarva UP Gramin Bank',113=>'New India Co-operative Bank',
        114=>'NKGSB Co-operative Bank',115=>'Vijaya Bank',116=>'United Bank of India',
        117=>'State Bank of Bikaner and Jaipur',118=>'Shri Janata Sahakari Bank',
        119=>'Rajgurunagar Sahakari Bank',120=>'Federal Neo Bank Jupiter',
        121=>'Chhattisgarh Rajya Gramin Bank',122=>'Apna Sahakari Bank',123=>'GS Mahanagar Co-operative Bank',
        124=>'Bangiya Gramin Vikash Bank',125=>'Assam Gramin Vikash Bank',126=>'Saurashtra Gramin Bank',
        127=>'Kangra Central Co-operative Bank',128=>'Punjab Gramin Bank',129=>'Assam Gramin Bikash Bank',
        130=>'Karnataka Gramin Bank',131=>'Suryoday Small Finance Bank',132=>'Utkarsh Small Finance Bank',
        133=>'Meghalaya Co-operative Apex Bank',134=>'Uttar Bihar Gramin Bank',135=>'State Bank of Travancore',
        136=>'Shivalik Small Finance Bank',137=>'Dakshin Bihar Gramin Bank',138=>'DBS Bank',
        139=>'State Bank of Hyderabad',140=>'Manipur Rural Bank',141=>'State Bank of Patiala',
        142=>'Baroda Gujarat Gramin Bank',143=>'Gujarat State Co-operative Bank',
        144=>'Vasai Vikas Sahakari Bank',145=>'Paschim Banga Gramin Bank',
        146=>'Pragathi Krishna Gramin Bank',147=>'Visakhapatnam Co-operative Bank',
        148=>'Samarth Sahakari Bank',149=>'Uttarbanga Kshetriya Gramin Bank',
        150=>'Janata Sahakari Bank',151=>'Prathama UP Gramin Bank',152=>'Gayatri Co-operative Urban Bank',
        153=>'Jupiter Federal Bank',154=>'Abhyudaya Co-operative Bank',155=>'J&K Grameen Bank',
        156=>'Post Office Savings Bank',157=>'SBM Bank India',158=>'Daman and Diu State Co-operative Bank',
        159=>'Jind Central Co-operative Bank',160=>'Jalgaon Peoples Co-operative Bank',
        161=>'Associated Co-operative Bank',162=>'Mizoram Co-operative Apex Bank',
        163=>'Muslim Co-operative Bank',164=>'Prathama Bank',165=>'Kasikornbank',
    ];
}

function app_api_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_AR_REAL_IP'] ?? '',
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return 'UNKNOWN';
}

function app_api_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function app_api_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        app_api_send(null, 11, 'Method not allowed', 405);
    }
}

/** Serve a lottery interval list while honoring both settings and game rows. */
function app_api_game_type_list(array $definitions): void
{
    app_api_require_post();
    $body = app_api_input();
    foreach (['language', 'random', 'signature', 'timestamp'] as $field) {
        if (!array_key_exists($field, $body)) {
            app_api_send(null, 7, 'Param is Invalid', 200, 6);
        }
    }
    app_api_require_signature($body);
    app_api_user();

    if (!app_setting_bool('betting_enabled', true)) {
        app_api_send([]);
    }
    $items = [];
    foreach ($definitions as $definition) {
        $feature = (string)($definition['feature'] ?? 'betting_enabled');
        $gameCode = (string)($definition['gameCode'] ?? '');
        $control = $gameCode !== '' ? app_game_control($gameCode) : ['enabled' => 1];
        if (!app_setting_bool($feature, true) || (int)($control['enabled'] ?? 1) !== 1) {
            continue;
        }
        unset($definition['feature'], $definition['gameCode']);
        $items[] = $definition;
    }
    app_api_send($items);
}

/** Load one administrator-managed payment method. */
function app_api_payment_method(string $code, bool $enabledOnly = true): ?array
{
    return app_payment_method_record($code, $enabledOnly);
}

/**
 * The reference client presents several UPI/QR labels as separate tabs. They
 * are all backed here by the same administrator-configured first-party UPI
 * checkout, so they deliberately retain generic payID 1. Special provider IDs
 * (21/26) enter external ARPay/ArUPI protocols and must not be advertised
 * unless those providers are genuinely integrated.
 */
function app_api_upi_public_profiles(): array
{
    return [
        [
            'payID' => 1,
            'payTypeID' => 1011,
            'payName' => 'UPI-QR',
            'paySysName' => 'ArUpiPay',
            'payNameUrl' => '/assets/payment-icons/upi_qr.svg',
            'payNameUrl2' => '/assets/payment-icons/upi_qr.svg',
            'typeName' => 'ArUpi Pay',
            'typeNameCode' => 9225,
            'sort' => 100,
        ],
        [
            'payID' => 1,
            'payTypeID' => 1012,
            'payName' => 'Innate UPI-QR',
            'paySysName' => 'Online Pay',
            'payNameUrl' => '/assets/payment-icons/innate_upi_qr.svg',
            'payNameUrl2' => '/assets/payment-icons/innate_upi_qr.svg',
            'typeName' => 'Innate UPI-QR',
            'typeNameCode' => 0,
            'sort' => 80,
        ],
        [
            'payID' => 1,
            'payTypeID' => 1013,
            'payName' => 'PAYTM-QR',
            'paySysName' => 'Online Pay',
            'payNameUrl' => '/assets/payment-icons/paytm_qr.svg',
            'payNameUrl2' => '/assets/payment-icons/paytm_qr.svg',
            'typeName' => 'PAYTM-QR',
            'typeNameCode' => 0,
            'sort' => 50,
        ],
        [
            'payID' => 1,
            'payTypeID' => 1014,
            'payName' => 'UPI-QR PAY',
            'paySysName' => 'QR Pay',
            'payNameUrl' => '/assets/payment-icons/upi_qr_pay.svg',
            'payNameUrl2' => '/assets/payment-icons/upi_qr_pay.svg',
            'typeName' => 'UPI-QR PAY',
            'typeNameCode' => 0,
            'sort' => 6,
        ],
    ];
}

function app_api_payment_public_profile(array $method, int $requestedTypeId = 0): array
{
    $code = strtolower(trim((string)($method['code'] ?? '')));
    $configuredName = trim((string)($method['name'] ?? ''));

    if ($code === 'usdt_demo') {
        return [
            'payID' => 11,
            'payTypeID' => 2123,
            'payName' => $configuredName !== '' ? $configuredName : 'USDT',
            'paySysName' => 'USDT',
            'payNameUrl' => '/assets/payment-icons/usdt_demo.svg',
            'payNameUrl2' => '/assets/payment-icons/usdt_demo.svg',
            'typeName' => 'USDT',
            'typeNameCode' => 9205,
            'sort' => 5,
        ];
    }

    $profiles = app_api_upi_public_profiles();
    foreach ($profiles as $profile) {
        if ($requestedTypeId > 0 && (int)$profile['payTypeID'] === $requestedTypeId) {
            return $profile;
        }
    }
    return $profiles[0];
}

/** Return only quick amounts that fit the administrator-configured limits. */
function app_api_payment_quick_amounts(array $method, bool $isUsdt = false): array
{
    $values = $isUsdt
        ? [10, 20, 50, 100, 200, 500, 1000]
        : [100, 200, 500, 1000, 2000, 3000, 5000, 10000, 20000, 30000, 50000];
    $minimum = max(0.01, (float)($method['min_amount'] ?? 0));
    $maximum = max($minimum, (float)($method['max_amount'] ?? $minimum));
    $quick = [];
    foreach ($values as $value) {
        if ($value >= $minimum && $value <= $maximum) {
            $quick[] = (float)$value;
        }
    }
    return $quick ?: [$minimum];
}
