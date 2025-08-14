<?php
// dashboard.php — Analytics for your short links

// password
$password = "changeme"; // Change this!

session_start();
if (isset($_POST['pass'])) {
    if ($_POST['pass'] === $password) {
        $_SESSION['vt_logged_in'] = true;
    } else {
        $error = "Wrong password";
    }
}
if (!($_SESSION['vt_logged_in'] ?? false)) {
    ?>
    <form method="post" style="margin:50px auto;max-width:300px;font-family:sans-serif;">
        <h2>Visitor Tracker Dashboard</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <input type="password" name="pass" placeholder="Password" style="width:100%;padding:8px;">
        <button style="margin-top:10px;padding:8px;width:100%;">Login</button>
    </form>
    <?php
    exit;
}


// ==== CONFIG ==== //
$dbFile = __DIR__ . '/visits.sqlite';

// Optional: Basic Auth (set both to enable)
$DASH_USER = ''; // e.g., 'admin'
$DASH_PASS = ''; // e.g., 'change-me'
// ================ //

// Basic Auth (optional)
if ($DASH_USER !== '' && $DASH_PASS !== '') {
    if (
        !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) ||
        $_SERVER['PHP_AUTH_USER'] !== $DASH_USER ||
        $_SERVER['PHP_AUTH_PW'] !== $DASH_PASS
    ) {
        header('WWW-Authenticate: Basic realm="Shortener Dashboard"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Unauthorized';
        exit;
    }
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo "SQLite not available or DB missing. Visit a short link first to create the DB.";
    exit;
}

// Totals
$total = (int)$pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn();

// Top shorts
$topShorts = $pdo->query("
    SELECT short, COUNT(*) as c
    FROM visits
    GROUP BY short
    ORDER BY c DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Top countries
$topCountries = $pdo->query("
    SELECT COALESCE(country, 'Unknown') as country, COUNT(*) as c
    FROM visits
    GROUP BY COALESCE(country, 'Unknown')
    ORDER BY c DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Time series (last 30 days)
$timeSeriesStmt = $pdo->prepare("
    SELECT strftime('%Y-%m-%d', ts) as day, COUNT(*) as c
    FROM visits
    WHERE ts >= date('now','-29 day')
    GROUP BY day
    ORDER BY day ASC
");
$timeSeriesStmt->execute();
$timeSeries = $timeSeriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent visits
$recent = $pdo->query("
    SELECT short, url, ip, referrer, country, region, city, lat, lon, ts
    FROM visits
    ORDER BY ts DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

// Map points (limit to keep fast)
$mapPoints = $pdo->query("
    SELECT short, country, city, lat, lon, ts
    FROM visits
    WHERE lat IS NOT NULL AND lon IS NOT NULL
    ORDER BY ts DESC
    LIMIT 1000
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JS
$tsLabels = [];
$tsCounts = [];
$days = new DatePeriod(
    new DateTime('29 days ago'),
    new DateInterval('P1D'),
    (new DateTime('tomorrow'))->setTime(0,0) // inclusive end
);
$countsByDay = [];
foreach ($timeSeries as $row) { $countsByDay[$row['day']] = (int)$row['c']; }
foreach ($days as $d) {
    $k = $d->format('Y-m-d');
    $tsLabels[] = $k;
    $tsCounts[] = $countsByDay[$k] ?? 0;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Shortener Analytics</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 16px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(280px,1fr)); gap: 16px; }
  .card { border: 1px solid #ddd; border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
  h1 { margin: 0 0 12px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th, td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
  #map { height: 420px; border-radius: 12px; }
  .muted { color: #666; }
  .pill { display: inline-block; padding: 2px 8px; border: 1px solid #ddd; border-radius: 999px; font-size: 12px; }
  .kpi { font-size: 28px; font-weight: 700; }
</style>
</head>
<body>
  <h1>Shortener Analytics</h1>

  <div class="grid">
    <div class="card">
      <div class="muted">Total Clicks</div>
      <div class="kpi"><?= number_format($total) ?></div>
    </div>
    <div class="card">
      <div class="muted">Top Links</div>
      <?php foreach ($topShorts as $row): ?>
        <div>
          <span class="pill"><?= h($row['short']) ?></span>
          — <?= number_format($row['c']) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="muted">Top Countries</div>
      <?php foreach ($topCountries as $row): ?>
        <div>
          <span class="pill"><?= h($row['country']) ?></span>
          — <?= number_format($row['c']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="grid" style="margin-top:16px;">
    <div class="card">
      <div class="muted">Clicks (Last 30 Days)</div>
      <canvas id="tsChart"></canvas>
    </div>
    <div class="card">
      <div class="muted">World Map (Recent)</div>
      <div id="map"></div>
    </div>
  </div>

  <div class="card" style="margin-top:16px;">
    <div class="muted">Recent Visits</div>
    <table>
      <thead>
        <tr>
          <th>Time (UTC)</th>
          <th>Short</th>
          <th>Country</th>
          <th>City</th>
          <th>IP</th>
          <th>Referrer</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td class="muted"><?= h($r['ts']) ?></td>
          <td><span class="pill"><?= h($r['short']) ?></span></td>
          <td><?= h($r['country'] ?: '—') ?></td>
          <td><?= h($r['city'] ?: '—') ?></td>
          <td class="muted"><?= h($r['ip']) ?></td>
          <td class="muted" title="<?= h($r['referrer']) ?>"><?= h($r['referrer'] ? (strlen($r['referrer'])>48 ? substr($r['referrer'],0,48).'…' : $r['referrer']) : '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<script>
// Chart.js — time series
const tsCtx = document.getElementById('tsChart').getContext('2d');
new Chart(tsCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($tsLabels) ?>,
    datasets: [{
      label: 'Clicks',
      data: <?= json_encode($tsCounts) ?>,
      tension: 0.25
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
      y: { beginAtZero: true, precision: 0 }
    }
  }
});

// Leaflet map
const map = L.map('map').setView([20, 0], 2);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 6,
  attribution: '&copy; OpenStreetMap'
}).addTo(map);

const points = <?= json_encode($mapPoints) ?>;
points.forEach(p => {
  if (p.lat !== null && p.lon !== null) {
    const m = L.marker([p.lat, p.lon]).addTo(map);
    const city = p.city ? p.city + ', ' : '';
    const country = p.country || 'Unknown';
    m.bindPopup(`<b>${escapeHtml(p.short)}</b><br>${city}${country}<br><small>${p.ts}</small>`);
  }
});
function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }
</script>
</body>
</html>
