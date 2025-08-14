<?php
// url.php
header('Content-Type: application/json');

// ==== CONFIGURATION ==== //
$API_KEY   = "CHANGE_THIS_TO_A_RANDOM_KEY"; // Set a strong, secret key here
$jsonFile  = __DIR__ . '/urls.json';
// ======================= //

// Get parameters from query string
$apiKey = isset($_GET['key'])   ? trim($_GET['key'])   : '';
$url    = isset($_GET['url'])   ? trim($_GET['url'])   : '';
$short  = isset($_GET['short']) ? trim($_GET['short']) : '';

// Check API key
if ($apiKey !== $API_KEY) {
    echo json_encode(["error" => "Invalid API key."]);
    http_response_code(403);
    exit;
}

// Validate parameters
if (!$url || !$short) {
    echo json_encode(["error" => "Missing 'url' or 'short' parameter."]);
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

// Add or update the short URL
$data[$short] = [
    "url"     => $url,
    "created" => gmdate("Y-m-d\TH:i:s\Z")
];

// Save the JSON file
if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
    echo json_encode(["success" => true, "short" => $short, "url" => $url]);
} else {
    echo json_encode(["error" => "Failed to write to file."]);
    http_response_code(500);
}
