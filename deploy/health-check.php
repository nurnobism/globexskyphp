<?php
/**
 * GlobexSky — Deployment Health Check
 *
 * Verifies that all critical components are correctly configured after deployment.
 *
 * ACCESS CONTROL: This script is restricted to localhost and a configurable
 * admin IP. Do NOT leave it publicly accessible in production.
 *
 * Usage (CLI):  php deploy/health-check.php
 * Usage (Web):  https://yourdomain.com/deploy/health-check.php
 *               (blocked from public access — see IP whitelist below)
 */

declare(strict_types=1);

// ── Access control ────────────────────────────────────────────
// Add your admin/office IP here to allow web access.
// Web access from any other IP returns 403.
$ALLOWED_IPS = [
    '127.0.0.1',
    '::1',
    // '203.0.113.10',  // example: your office IP
];

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteIp, $ALLOWED_IPS, true)) {
        http_response_code(403);
        exit('Access denied. Edit ALLOWED_IPS in deploy/health-check.php to grant access.');
    }
}

// ── Load .env ─────────────────────────────────────────────────
$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if ($key !== '' && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────
$results = [];

function addCheck(string $label, bool $pass, string $detail = ''): void
{
    global $results;
    $results[] = ['label' => $label, 'pass' => $pass, 'detail' => $detail];
}

function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ═══════════════════════════════════════════════════════════════
// CHECK 1 — .env file
// ═══════════════════════════════════════════════════════════════
addCheck(
    '.env file exists and is readable',
    file_exists($envFile) && is_readable($envFile),
    $envFile
);

// ═══════════════════════════════════════════════════════════════
// CHECK 2 — APP_ENV / APP_DEBUG
// ═══════════════════════════════════════════════════════════════
$appEnv   = env('APP_ENV', 'unknown');
$appDebug = env('APP_DEBUG', 'true');
addCheck(
    'APP_ENV=production',
    $appEnv === 'production',
    "Current: APP_ENV=$appEnv"
);
addCheck(
    'APP_DEBUG=false',
    strtolower($appDebug) === 'false' || $appDebug === '0',
    "Current: APP_DEBUG=$appDebug"
);

// ═══════════════════════════════════════════════════════════════
// CHECK 3 — Required PHP extensions
// ═══════════════════════════════════════════════════════════════
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'gd', 'curl', 'openssl'];
foreach ($requiredExtensions as $ext) {
    addCheck("PHP extension: $ext", extension_loaded($ext));
}

// ═══════════════════════════════════════════════════════════════
// CHECK 4 — Database connection
// ═══════════════════════════════════════════════════════════════
$dbConnected = false;
$dbDetail    = '';
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', 'localhost'),
        env('DB_PORT', '3306'),
        env('DB_NAME')
    );
    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    $pdo->query('SELECT 1');
    $dbConnected = true;
    $dbDetail    = 'Connected to ' . env('DB_HOST') . '/' . env('DB_NAME');
} catch (PDOException $e) {
    $dbDetail = 'Error: ' . $e->getMessage();
}
addCheck('Database connection', $dbConnected, $dbDetail);

// ═══════════════════════════════════════════════════════════════
// CHECK 5 — Upload directories exist and are writable
// ═══════════════════════════════════════════════════════════════
$uploadDirs = [
    'uploads/kyc',
    'uploads/products',
    'uploads/avatars',
    'storage',
    'storage/logs',
];
foreach ($uploadDirs as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    $exists   = is_dir($fullPath);
    $writable = $exists && is_writable($fullPath);
    addCheck(
        "Directory writable: $dir",
        $writable,
        $exists ? ($writable ? 'OK' : 'Not writable — run chmod 750') : 'Directory missing'
    );
}

// ═══════════════════════════════════════════════════════════════
// CHECK 6 — Required environment variables are set
// ═══════════════════════════════════════════════════════════════
$requiredEnvVars = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD',
    'STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY',
    'JWT_SECRET', 'INTERNAL_API_KEY',
    'ADMIN_EMAIL', 'ADMIN_PASSWORD',
];
foreach ($requiredEnvVars as $var) {
    $val = env($var);
    $set = $val !== '' && !str_contains(strtolower($val), 'fill_in') && !str_contains($val, 'xxx');
    addCheck("ENV var set: $var", $set, $set ? '(set)' : 'EMPTY or placeholder');
}

// ═══════════════════════════════════════════════════════════════
// CHECK 7 — Stripe key format (live keys in production)
// ═══════════════════════════════════════════════════════════════
$stripePublic = env('STRIPE_PUBLISHABLE_KEY');
$stripeSecret = env('STRIPE_SECRET_KEY');
addCheck(
    'Stripe publishable key is live (pk_live_)',
    str_starts_with($stripePublic, 'pk_live_'),
    "Current prefix: " . substr($stripePublic, 0, 8) . '…'
);
addCheck(
    'Stripe secret key is live (sk_live_)',
    str_starts_with($stripeSecret, 'sk_live_'),
    "Current prefix: " . substr($stripeSecret, 0, 8) . '…'
);

// ═══════════════════════════════════════════════════════════════
// CHECK 8 — Node.js server reachable (optional)
// ═══════════════════════════════════════════════════════════════
$nodeReachable = false;
$nodeDetail    = 'Skipped (curl not available)';
if (extension_loaded('curl')) {
    $ch = curl_init('http://127.0.0.1:3001/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $nodeReachable = ($code === 200);
    $nodeDetail    = $nodeReachable
        ? "HTTP $code — " . substr((string)$resp, 0, 60)
        : "HTTP $code — server may not be running";
}
addCheck('Node.js server reachable (port 3001)', $nodeReachable, $nodeDetail);

// ═══════════════════════════════════════════════════════════════
// CHECK 9 — SMTP configuration (optional test)
// ═══════════════════════════════════════════════════════════════
$smtpHost = env('MAIL_HOST');
$smtpPort = (int)env('MAIL_PORT', '587');
$smtpOk   = false;
$smtpDetail = 'Skipped';
if ($smtpHost !== '' && !str_contains($smtpHost, 'fill_in') && !str_contains($smtpHost, 'yourdomain')) {
    $conn = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 5);
    if ($conn) {
        fclose($conn);
        $smtpOk     = true;
        $smtpDetail = "Connected to $smtpHost:$smtpPort";
    } else {
        $smtpDetail = "Cannot connect to $smtpHost:$smtpPort — $errstr";
    }
} else {
    $smtpDetail = 'MAIL_HOST not configured';
}
addCheck("SMTP reachable ($smtpHost:$smtpPort)", $smtpOk, $smtpDetail);

// ═══════════════════════════════════════════════════════════════
// CHECK 10 — Security headers (requires web request, not CLI)
// ═══════════════════════════════════════════════════════════════
if (!$isCli && extension_loaded('curl')) {
    $appUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
    $ch = curl_init($appUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $headerRaw = curl_exec($ch);
    curl_close($ch);
    $headersLower = strtolower((string)$headerRaw);
    $requiredHeaders = [
        'x-content-type-options',
        'x-frame-options',
        'referrer-policy',
    ];
    foreach ($requiredHeaders as $hdr) {
        addCheck(
            "Security header present: $hdr",
            str_contains($headersLower, $hdr . ':'),
            str_contains($headersLower, $hdr . ':') ? 'Present' : 'MISSING — check .htaccess'
        );
    }
} else {
    addCheck('Security headers (web-only check)', true, 'Skipped in CLI mode');
}

// ═══════════════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════════════
$totalPass = count(array_filter($results, fn($r) => $r['pass']));
$totalFail = count($results) - $totalPass;

if ($isCli) {
    // CLI output
    $reset = "\033[0m"; $green = "\033[32m"; $red = "\033[31m"; $bold = "\033[1m";
    echo "\n{$bold}GlobexSky — Deployment Health Check{$reset}\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($results as $r) {
        $icon = $r['pass'] ? "{$green}✔{$reset}" : "{$red}✘{$reset}";
        $detail = $r['detail'] ? "  ({$r['detail']})" : '';
        echo "  $icon  {$r['label']}$detail\n";
    }
    echo str_repeat('─', 60) . "\n";
    echo "  {$green}Passed: $totalPass{$reset}  {$red}Failed: $totalFail{$reset}\n\n";
    exit($totalFail > 0 ? 1 : 0);
}

// Web HTML output
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GlobexSky — Health Check</title>
<style>
  body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:2rem}
  h1{color:#38bdf8;margin-bottom:0.25rem}
  p.sub{color:#94a3b8;margin-top:0}
  .card{background:#1e293b;border-radius:0.75rem;padding:1.5rem;max-width:720px;margin:auto}
  table{width:100%;border-collapse:collapse;margin-top:1rem}
  th,td{padding:0.5rem 0.75rem;text-align:left;border-bottom:1px solid #334155}
  th{color:#94a3b8;font-size:0.8rem;text-transform:uppercase}
  .pass{color:#4ade80}
  .fail{color:#f87171}
  .detail{font-size:0.8rem;color:#94a3b8}
  .summary{margin-top:1.5rem;padding:1rem;border-radius:0.5rem;text-align:center;font-weight:bold}
  .all-pass{background:#14532d;color:#4ade80}
  .has-fail{background:#7f1d1d;color:#f87171}
</style>
</head>
<body>
<div class="card">
  <h1>🛡 GlobexSky Health Check</h1>
  <p class="sub">Deployment verification — <?= date('Y-m-d H:i:s T') ?></p>
  <table>
    <thead><tr><th>Status</th><th>Check</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($results as $r): ?>
      <tr>
        <td class="<?= $r['pass'] ? 'pass' : 'fail' ?>"><?= $r['pass'] ? '✅' : '❌' ?></td>
        <td><?= htmlspecialchars($r['label']) ?></td>
        <td class="detail"><?= htmlspecialchars($r['detail']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="summary <?= $totalFail === 0 ? 'all-pass' : 'has-fail' ?>">
    <?= $totalFail === 0
        ? "✅ All $totalPass checks passed — deployment looks healthy!"
        : "❌ $totalFail check(s) failed — fix issues above before going live" ?>
  </div>
</div>
</body>
</html>
