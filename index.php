<?php
// go.php â€” Redirects / tracks visits for a short link, e.g. go.php?short=exampleshort

// ==== CONFIG ==== //
$jsonFile = __DIR__ . '/urls.json';            // Your shortlinks JSON (same used by url.php)
$dbFile   = __DIR__ . '/visits.sqlite';        // SQLite DB path
$geoURL   = 'http://ip-api.com/json/';         // Free geolocation endpoint (rate-limited)
// Optional: limit network call time
$geoTimeoutSeconds = 2;
// ================= //

// Get short
$short = isset($_GET['short']) ? trim($_GET['short']) : '';
if ($short === '') {
    http_response_code(400);
    echo "Missing short parameter.";
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

// Collect request info
$ip       = getClientIp();
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$ts       = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

[$country, $region, $city, $lat, $lon] = geoLookup($ip, $geoURL, $geoTimeoutSeconds);

// Ensure DB / table
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
        CREATE INDEX IF NOT EXISTS idx_visits_country ON visits(country);
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
    // Logging failures shouldn't block the redirect; you could log $e->getMessage() to a file if you want.
}

// Redirect
header("Location: $targetUrl", true, 302);
exit;

// ------------- Helpers ------------- //
function getClientIp(): string {
    // X-Forwarded-For support (first IP)
    $headers = [
        'HTTP_CF_CONNECTING_IP',       // Cloudflare
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
    // Skip private / local IPs
    if (
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
    ) {
        return [null, null, null, null, null];
    }

    $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
    try {
        $resp = @file_get_contents($endpoint . urlencode($ip), false, $ctx);
        if ($resp !== false) {
            $j = json_decode($resp, true);
            if (isset($j['status']) && $j['status'] === 'success') {
                return [
                    $j['country']  ?? null,
                    $j['regionName'] ?? null,
                    $j['city']     ?? null,
                    isset($j['lat']) ? floatval($j['lat']) : null,
                    isset($j['lon']) ? floatval($j['lon']) : null,
                ];
            }
        }
    } catch (Throwable $e) { /* ignore */ }

    return [null, null, null, null, null];
}
