<?php
// 13L missing assets downloader
// Use only if the source domain/assets are yours or you have permission to copy them.
// Run: https://your-domain.com/download_missing_assets.php?key=8279&limit=300

set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');

$KEY = '8279';
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $KEY) {
    http_response_code(403);
    echo "Forbidden. Use ?key=8279\n";
    exit;
}

$ROOT = __DIR__; 
$SOURCE_BASE = rtrim($_GET['source'] ?? 'https://dmfirst1.com', '/'); // Updated default source domain based on your log
$LIMIT = max(1, min(2000, (int)($_GET['limit'] ?? 300)));
$ALLOW_EXT = ['png','webp','jpg','jpeg','gif','svg','css','js','json','mp3','wav'];
$SKIP_EXT = ['woff','woff2','ttf','otf','eot']; // font files intentionally skipped

function collect_files(string $dir, array &$files): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $path = $file->getPathname();
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','html','htm','js','css','json','webmanifest','txt'], true)) $files[] = $path;
    }
}

function normalize_asset_url(string $u) { 
    $u = html_entity_decode(trim($u, " \t\n\r\0\x0B'\"()"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $u = preg_replace('/[#?].*$/', '', $u);
    return $u ?: '';
}

function local_path_for(string $url, string $root, string $sourceBase): array {
    $url = normalize_asset_url($url);
    
    // Fixed: Added missing || operators
    if ($url === '' || strncmp($url, 'data:', 5) === 0 || strncmp($url, 'blob:', 5) === 0) {
        return ['', '']; 
    }
    
    if (preg_match('#^https?://#i', $url)) {
        $parts = parse_url($url);
        // Fixed: Added missing || operators
        if (!$parts || empty($parts['host']) || empty($parts['path'])) return ['', '']; 
        $host = strtolower($parts['host']);
        $baseHost = strtolower(parse_url($sourceBase, PHP_URL_HOST) ?: '');
        
        if ($host === $baseHost) {
            $rel = ltrim($parts['path'], '/');
        } elseif ($host === 'pro-img.arsaaspub.com') {
            $rel = 'external-assets/pro-img.arsaaspub.com/' . ltrim($parts['path'], '/');
        } else {
            return ['', ''];
        }
        return [$url, $root . '/' . $rel];
    }
    
    // PHP 7.4 compatible checks for relative/protocol-less paths
    if (strncmp($url, '//', 2) === 0) return ['https:' . $url, ''];
    if (strncmp($url, '/', 1) === 0) return [$sourceBase . $url, $root . $url];
    
    if (preg_match('#^(img|images|assets|css|js|cdn-cgi)/#i', $url)) {
        return [$sourceBase . '/' . $url, $root . '/' . $url];
    }
    return ['', ''];
}

function fetch_url(string $url) { 
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, 
            CURLOPT_FOLLOWLOCATION=>true, 
            CURLOPT_CONNECTTIMEOUT=>10, 
            CURLOPT_TIMEOUT=>30, 
            CURLOPT_USERAGENT=>'Mozilla/5.0 asset-downloader'
        ]);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 300 && $data !== false) ? $data : false;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>30,'header'=>"User-Agent: Mozilla/5.0 asset-downloader\r\n"]]);
    return @file_get_contents($url, false, $ctx);
}

$scan = [];
foreach (['index.html','manifest.webmanifest','themes-dcff9595.json','api','assets','css','js','images','img'] as $item) {
    collect_files($ROOT . '/' . $item, $scan);
}

$urls = [];
$pattern = '#(?:https?:)?//[^\s\"\'<>\\)]+\.(?:png|webp|jpg|jpeg|gif|svg|css|js|json|mp3|wav|woff2?|ttf|otf|eot)(?:\?[^\s\"\'<>\\)]*)?|/?(?:img|images|assets|css|js|cdn-cgi)/[^\s\"\'<>\\)]+\.(?:png|webp|jpg|jpeg|gif|svg|css|js|json|mp3|wav|woff2?|ttf|otf|eot)(?:\?[^\s\"\'<>\\)]*)?#i';

foreach ($scan as $file) {
    $text = @file_get_contents($file);
    if ($text === false) continue;
    if (preg_match_all($pattern, $text, $m)) {
        foreach ($m[0] as $u) $urls[normalize_asset_url($u)] = true;
    }
}

// Optional manual list: one URL/path per line.
$manual = $ROOT . '/missing_urls.txt';
if (is_file($manual)) {
    foreach (file($manual, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $urls[normalize_asset_url($line)] = true;
    }
}

$downloaded=0; $skipped=0; $failed=0; $seen=0;
echo "Source: $SOURCE_BASE\nLimit: $LIMIT\nFound URLs: " . count($urls) . "\n\n";

foreach (array_keys($urls) as $u) {
    $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?: $u, PATHINFO_EXTENSION));
    // Fixed: Added missing || operators
    if (in_array($ext, $SKIP_EXT, true) || !in_array($ext, $ALLOW_EXT, true)) { $skipped++; continue; }
    
    [$src, $dst] = local_path_for($u, $ROOT, $SOURCE_BASE);
    // Fixed: Added missing || operators
    if (!$src || !$dst) { $skipped++; continue; }
    $seen++;
    
    if (is_file($dst) && filesize($dst) > 200) { $skipped++; continue; }
    if ($downloaded >= $LIMIT) { echo "Limit reached. Re-run again to continue.\n"; break; }
    
    @mkdir(dirname($dst), 0755, true);
    $data = fetch_url($src);
    if ($data === false || strlen($data) < 50) { echo "FAIL  $src\n"; $failed++; continue; }
    
    file_put_contents($dst, $data);
    echo "SAVE  " . str_replace($ROOT.'/', '', $dst) . " <= $src (" . strlen($data) . " bytes)\n";
    $downloaded++;
}

echo "\nDone. seen=$seen downloaded=$downloaded skipped=$skipped failed=$failed\n";
echo "Security: download complete hone ke baad is file ko delete kar dena.\n";