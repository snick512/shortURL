<?php
// index.php — Secure redirect + tracker

// ==== CONFIG ==== //
$jsonFile = __DIR__ . '/urls.json';   // Shortlinks JSON
$dbFile   = __DIR__ . '/visits.sqlite';
$geoURL   = 'http://ip-api.com/json/'; 
$geoTimeoutSeconds = 2;
// ================= //

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header('X-XSS-Protection: 1; mode=block');

// Get short parameter, sanitize
$short = isset($_GET['short']) ? trim($_GET['short']) : '';
if ($short === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $short)) {
    http_response_code(400);
    echo "Invalid short parameter.";
    exit;
}

// Load shortlinks
if (!file_exists($jsonFile)) {
    http_response_code(404);
    echo "Shortlinks store not found.";
    exit;
}
$data = json_decode(file_get_contents($jsonFile), true);
if (!is_array($data) || !isset($data[$short]['url'])) {
    http_response_code(404);
    echo "Short link not found.";
    exit;
}
$targetUrl = $data[$short]['url'];

// Validate target URL
if (!preg_match('#^https?://#i', $targetUrl)) {
    http_response_code(400);
    echo "Invalid target URL.";
    exit;
}

// Collect request info
$ip       = getClientIp();
$ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500); // prevent oversized UA strings
$referrer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);
$ts       = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

[$country, $region, $city, $lat, $lon] = geoLookup($ip, $geoURL, $geoTimeoutSeconds);

// Save visit
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            short TEXT NOT NULL,
            url TEXT NOT NULL,
            ip TEXT,
            ua TEXT,
            referrer TEXT,
            country TEXT,
            region TEXT,
            city TEXT,
            lat REAL,
            lon REAL,
            ts TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_visits_short_ts ON visits(short, ts);
    ");

    $stmt = $pdo->prepare("
        INSERT INTO visits (short, url, ip, ua, referrer, country, region, city, lat, lon, ts)
        VALUES (:short, :url, :ip, :ua, :referrer, :country, :region, :city, :lat, :lon, :ts)
    ");
    $stmt->execute([
        ':short'    => $short,
        ':url'      => $targetUrl,
        ':ip'       => $ip,
        ':ua'       => $ua,
        ':referrer' => $referrer,
        ':country'  => $country,
        ':region'   => $region,
        ':city'     => $city,
        ':lat'      => $lat,
        ':lon'      => $lon,
        ':ts'       => $ts,
    ]);
} catch (Throwable $e) {
    // Fail silently but don’t block redirect
}

// Redirect safely
header("Location: $targetUrl", true, 302);
exit;

// --------- Helpers --------- //
function getClientIp(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $raw = $_SERVER[$h];
            if ($h === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $raw));
                return filter_var($parts[0], FILTER_VALIDATE_IP) ? $parts[0] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            }
            return filter_var($raw, FILTER_VALIDATE_IP) ? $raw : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        }
    }
    return '0.0.0.0';
}

function geoLookup(string $ip, string $endpoint, int $timeout = 2): array {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return [null, null, null, null, null];
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
    try {
        $resp = @file_get_contents($endpoint . urlencode($ip), false, $ctx);
        if ($resp !== false) {
            $j = json_decode($resp, true);
            if (isset($j['status']) && $j['status'] === 'success') {
                return [
                    $j['country'] ?? null,
                    $j['regionName'] ?? null,
                    $j['city'] ?? null,
                    isset($j['lat']) ? floatval($j['lat']) : null,
                    isset($j['lon']) ? floatval($j['lon']) : null,
                ];
            }
        }
    } catch (Throwable $e) { /* ignore */ }
    return [null, null, null, null, null];
}
