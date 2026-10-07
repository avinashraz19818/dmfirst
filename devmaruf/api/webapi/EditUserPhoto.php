<?php // Updated by System - 2026-05-03 21:58:31 ?>
<?php // Updated by System - 2026-05-03 21:58:14 ?>
<?php
// api/edituserphoto.php — Persist user photo choice in DB
include "../../conn.php";
include "../../functions2.php";

	header('Content-Type: application/json; charset=utf-8');
	header('Strict-Transport-Security: max-age=31536000');
	header('Access-Control-Allow-Headers: *');
	header('Access-Control-Allow-Credentials: true');
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	header('Access-Control-Allow-Origin: ' . $origin);
	header('vary: Origin');

date_default_timezone_set("Asia/Kolkata");
$now = date("Y-m-d H:i:s");

// CORS preflight
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['code'=>11,'msg'=>'Method not allowed','msgCode'=>12,'serviceNowTime'=>$now]);
  exit;
}

// Body (JSON or form)
$raw = file_get_contents("php://input");
$in = json_decode($raw, true);
if (!is_array($in) || empty($in)) { $in = $_POST; }
$debug = !empty($in['debug']);

// Require fields (same envelope style)
foreach (['language','random','signature','timestamp','userPhoto'] as $k) {
  if (!array_key_exists($k, $in)) {
    http_response_code(200);
    echo json_encode(['code'=>7,'msg'=>'Param is Invalid','msgCode'=>6,'serviceNowTime'=>$now]);
    exit;
  }
}

$language_raw  = (string)$in['language'];      // e.g., 0 or "en"
$random_raw    = (string)$in['random'];
$photo_raw     = (string)$in['userPhoto'];     // client sends string index or url
$signature_raw = strtoupper((string)$in['signature']);

// ✅ Signature: client signs (no spaces) with userPhoto included
// Primary exact: {"language":0,"random":"<rand>","userPhoto":"<photo>"}
$variants = [];
$variants[] = '{"language":'  . $language_raw          . ',"random":"' . $random_raw . '","userPhoto":"' . $photo_raw . '"}';
// Accept common variants too (quoted language / unquoted userPhoto / order swap):
$variants[] = '{"language":"' . $language_raw . '","random":"' . $random_raw . '","userPhoto":"' . $photo_raw . '"}';
$variants[] = '{"language":'  . $language_raw          . ',"random":"' . $random_raw . '","userPhoto":'  . $photo_raw . '}';
$variants[] = '{"random":"'   . $random_raw  . '","language":' . $language_raw . ',"userPhoto":"' . $photo_raw . '"}';
$variants[] = '{"random":"'   . $random_raw  . '","language":"' . $language_raw . '","userPhoto":"' . $photo_raw . '"}';

// Compare
$ok = false; $dbg = [];
foreach ($variants as $s) {
  $h = strtoupper(md5($s));
  if ($h === $signature_raw) { $ok = true; break; }
  if ($debug) $dbg[] = ['preimage'=>$s,'md5'=>$h];
}
if (!$ok) {
  http_response_code(200);
  $out = ['code'=>5,'msg'=>'Wrong signature','msgCode'=>3,'serviceNowTime'=>$now];
  if ($debug) $out['debug'] = ['clientSig'=>$signature_raw,'tried'=>$dbg];
  echo json_encode($out);
  exit;
}

// Auth header → JWT
$authHeader =
  ($_SERVER['HTTP_AUTHORIZATION'] ?? '') ?:
  ($_SERVER['Authorization'] ?? '') ?:
  ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$parts = explode(' ', trim($authHeader));
$token = $parts[1] ?? '';
if ($token === '') {
  http_response_code(401);
  echo json_encode(['code'=>4,'msg'=>'No operation permission','msgCode'=>2,'serviceNowTime'=>$now]);
  exit;
}

// Validate JWT
$jwt = is_jwt_valid($token);
$auth = json_decode($jwt, true);
if (!is_array($auth) || ($auth['status'] ?? '') !== 'Success') {
  http_response_code(401);
  echo json_encode(['code'=>4,'msg'=>'No operation permission','msgCode'=>2,'serviceNowTime'=>$now]);
  exit;
}
$userId = (int)$auth['payload']['id'];

// Verify session token in DB (akshinak)
$chk = $conn->prepare("SELECT id FROM shonu_subjects WHERE akshinak = ? AND id = ? LIMIT 1");
$chk->bind_param('si', $token, $userId);
$chk->execute();
$r = $chk->get_result();
$chk->close();
if (!$r || $r->num_rows !== 1) {
  http_response_code(401);
  echo json_encode(['code'=>4,'msg'=>'No operation permission','msgCode'=>2,'serviceNowTime'=>$now]);
  exit;
}

// Validate/normalize userPhoto (allow 1..999 or short string/URL up to 255)
$userPhoto = trim($photo_raw);
if ($userPhoto === '') {
  http_response_code(200);
  echo json_encode(['code'=>7,'msg'=>'userPhoto is required','msgCode'=>6,'serviceNowTime'=>$now]);
  exit;
}
if (mb_strlen($userPhoto) > 255) {
  http_response_code(200);
  echo json_encode(['code'=>7,'msg'=>'userPhoto too long (max 255)','msgCode'=>6,'serviceNowTime'=>$now]);
  exit;
}

// Read current value
$sel = $conn->prepare("SELECT COALESCE(user_photo,'1') AS user_photo FROM shonu_subjects WHERE id = ? LIMIT 1");
$sel->bind_param('i', $userId);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
$sel->close();
$oldPhoto = (string)($row['user_photo'] ?? '1');

if ($oldPhoto === $userPhoto) {
  http_response_code(200);
  echo json_encode([
    'code'=>0,'msg'=>'Succeed','msgCode'=>0,'serviceNowTime'=>$now,
    'data'=>['userId'=>$userId,'userPhoto'=>$userPhoto,'changed'=>false]
  ]);
  exit;
}

// ✅ Persist in DB
$upd = $conn->prepare("UPDATE shonu_subjects SET user_photo = ? WHERE id = ?");
$upd->bind_param('si', $userPhoto, $userId);
$upd->execute();
$rows = $upd->affected_rows;
$upd->close();

http_response_code(200);
echo json_encode([
  'code'=>0,'msg'=>'Succeed','msgCode'=>0,'serviceNowTime'=>$now,
  'data'=>['userId'=>$userId,'userPhoto'=>$userPhoto,'changed'=>($rows>0)]
]);