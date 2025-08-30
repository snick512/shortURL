<?php
// url.php
header('Content-Type: application/json');

// ==== CONFIGURATION ==== //
$API_KEY   = ""; // Set a strong, secret key
$BASE_SHORT_URL = "https://go.tyclifford.com";    // Base domain for your short URLs
$jsonFile  = __DIR__ . '/urls.json';
// ======================= //

// === Helper: generate shortname ===
function generateShort($existing) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxAttempts = 1000;

    for ($i = 0; $i < $maxAttempts; $i++) {
        // Random length 3–5
        $len = random_int(3, 5);

        // Seed randomness with microtime + iteration
        $seed = microtime(true) . $i . random_int(0, PHP_INT_MAX);
        $hash = hash('sha256', $seed);

        // Pick random characters from hash
        $short = '';
        for ($j = 0; $j < $len; $j++) {
            $pos = hexdec(substr($hash, $j * 2, 2)) % strlen($chars);
            $short .= $chars[$pos];
        }

        // Ensure it's unique
        if (!isset($existing[$short])) {
            return $short;
        }
    }
    return null; // fallback if too many attempts
}

// Get parameters
$apiKey = isset($_GET['key'])   ? trim($_GET['key'])   : '';
$url    = isset($_GET['url'])   ? trim($_GET['url'])   : '';
$short  = isset($_GET['short']) ? trim($_GET['short']) : '';

// Check API key
if ($apiKey !== $API_KEY) {
    echo json_encode(["error" => "Invalid API key."]);
    http_response_code(403);
    exit;
}

// Validate URL
if (!$url) {
    echo json_encode(["error" => "Missing 'url' parameter."]);
    http_response_code(400);
    exit;
}

// Load existing JSON or start fresh
if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($data)) $data = [];
} else {
    $data = [];
}

// If no shortname supplied, auto-generate one
if (!$short) {
    $short = generateShort($data);
    if (!$short) {
        echo json_encode(["error" => "Failed to generate unique shortname."]);
        http_response_code(500);
        exit;
    }
}

// Check if short name already exists
if (isset($data[$short])) {
    echo json_encode([
        "success"    => false,
        "message"    => "Short name already exists.",
        "short"      => $short,
        "url"        => $data[$short]['url'],
        "created"    => $data[$short]['created'],
        "shortLink"  => $BASE_SHORT_URL . '/' . $short
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Add the new short URL
$data[$short] = [
    "url"     => $url,
    "created" => gmdate("Y-m-d\TH:i:s\Z")
];

// Save the JSON file
if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
    echo json_encode([
        "success"    => true,
        "short"      => $short,
        "url"        => $url,
        "created"    => $data[$short]['created'],
        "shortLink"  => $BASE_SHORT_URL . '/' . $short
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(["error" => "Failed to write to file."]);
    http_response_code(500);
}
