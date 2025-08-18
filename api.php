<?php
// api.php — Lightweight analytics API

header('Content-Type: application/json');

// === CONFIG ===
$API_KEY = "CHANGE_THIS_KEY"; // strong random string
$dbFile  = __DIR__ . '/visits.sqlite';
// ==============

// API key check
$key = $_GET['key'] ?? '';
if ($key !== $API_KEY) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid API key"]);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB not available"]);
    exit;
}

// Build summary
$total = (int)$pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn();

$topShorts = $pdo->query("
    SELECT short, COUNT(*) as c
    FROM visits
    GROUP BY short
    ORDER BY c DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$topCountries = $pdo->query("
    SELECT COALESCE(country, 'Unknown') as country, COUNT(*) as c
    FROM visits
    GROUP BY COALESCE(country, 'Unknown')
    ORDER BY c DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recent = $pdo->query("
    SELECT short, country, ip, ts
    FROM visits
    ORDER BY ts DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Output JSON
echo json_encode([
    "success" => true,
    "total" => $total,
    "topShorts" => $topShorts,
    "topCountries" => $topCountries,
    "recent" => $recent
], JSON_PRETTY_PRINT);
