<?php
/**
 * GlobexSky — Deployment Health Check
 *
 * Verifies that all critical components are correctly configured after deployment.
 *
 * ACCESS CONTROL:
 *   - CLI mode: always allowed
 *   - Web mode with ?key=<HEALTH_CHECK_KEY>: allowed from any IP
 *   - Web mode from whitelisted IPs: allowed
 *   - All other web requests: 403 Forbidden
 *
 * Usage (CLI):      php deploy/health-check.php
 * Usage (Web HTML): https://yourdomain.com/deploy/health-check.php?key=YOUR_SECRET_KEY
 * Usage (Web JSON): https://yourdomain.com/deploy/health-check.php?key=YOUR_SECRET_KEY&format=json
 *
 * Set HEALTH_CHECK_KEY in your .env file to protect web access.
 */

declare(strict_types=1);

// ── Load .env first (needed for HEALTH_CHECK_KEY) ─────────────
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

// ── Access control ────────────────────────────────────────────
// Add your admin/office IP here to allow web access without a key.
$ALLOWED_IPS = [
    '127.0.0.1',
    '::1',
    // '203.0.113.10',  // example: your office IP
];

$isCli    = (php_sapi_name() === 'cli');
$isJson   = !$isCli && (($_GET['format'] ?? '') === 'json');
$givenKey = $_GET['key'] ?? '';
$envKey   = $_ENV['HEALTH_CHECK_KEY'] ?? getenv('HEALTH_CHECK_KEY') ?: '';

if (!$isCli) {
    $remoteIp    = $_SERVER['REMOTE_ADDR'] ?? '';
    $keyMatches  = ($envKey !== '' && hash_equals($envKey, $givenKey));
    $ipAllowed   = in_array($remoteIp, $ALLOWED_IPS, true);

    if (!$keyMatches && !$ipAllowed) {
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access denied. Provide ?key=YOUR_HEALTH_CHECK_KEY'], JSON_THROW_ON_ERROR);
        } else {
            http_response_code(403);
            echo 'Access denied. Add ?key=YOUR_HEALTH_CHECK_KEY or add your IP to ALLOWED_IPS.';
        }
        exit;
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
// CHECK 1 — PHP version
// ═══════════════════════════════════════════════════════════════
$phpVersion    = PHP_VERSION;
$phpVersionOk  = PHP_MAJOR_VERSION >= 8;
addCheck(
    'PHP version >= 8.0',
    $phpVersionOk,
    "Current: PHP $phpVersion"
);

// ═══════════════════════════════════════════════════════════════
// CHECK 2 — Required PHP extensions
// ═══════════════════════════════════════════════════════════════
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'gd', 'openssl', 'fileinfo'];
foreach ($requiredExtensions as $ext) {
    addCheck("PHP extension: $ext", extension_loaded($ext));
}

// ═══════════════════════════════════════════════════════════════
// CHECK 3 — .env file
// ═══════════════════════════════════════════════════════════════
addCheck(
    '.env file exists and is readable',
    file_exists($envFile) && is_readable($envFile),
    $envFile
);

// ═══════════════════════════════════════════════════════════════
// CHECK 4 — APP_ENV / APP_DEBUG
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
// CHECK 5 — Database connection
// ═══════════════════════════════════════════════════════════════
$dbConnected = false;
$dbDetail    = '';
$pdo         = null;
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
// CHECK 6 — Required database tables
// ═══════════════════════════════════════════════════════════════
$requiredTables = [
    'users', 'products', 'categories', 'orders', 'order_items',
    'cart', 'chat_rooms', 'chat_messages', 'notifications',
    'kyc_verifications', 'subscriptions', 'system_settings',
];
if ($pdo !== null) {
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM `$table` LIMIT 0");
            addCheck("Table exists: $table", true);
        } catch (PDOException $e) {
            addCheck("Table exists: $table", false, 'Missing — run database-setup.sh');
        }
    }
} else {
    foreach ($requiredTables as $table) {
        addCheck("Table exists: $table", false, 'Skipped — no DB connection');
    }
}

// ═══════════════════════════════════════════════════════════════
// CHECK 7 — Admin user exists
// ═══════════════════════════════════════════════════════════════
if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE role='admin' AND is_active=1 LIMIT 1");
        $row  = $stmt->fetch();
        $adminCount = (int)($row['cnt'] ?? 0);
        addCheck(
            'Admin user exists (role=admin, is_active=1)',
            $adminCount > 0,
            $adminCount > 0 ? "$adminCount admin user(s) found" : 'No active admin user — run setup.sh or insert manually'
        );
    } catch (PDOException $e) {
        addCheck('Admin user exists', false, 'Error: ' . $e->getMessage());
    }
} else {
    addCheck('Admin user exists', false, 'Skipped — no DB connection');
}

// ═══════════════════════════════════════════════════════════════
// CHECK 8 — Upload directories exist and are writable
// ═══════════════════════════════════════════════════════════════
$uploadDirs = [
    'uploads/kyc',
    'uploads/products',
    'uploads/avatars',
    'assets/uploads',
    'storage',
    'storage/logs',
    'storage/cache',
    'logs',
    'cache',
];
foreach ($uploadDirs as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    $exists   = is_dir($fullPath);
    $writable = $exists && is_writable($fullPath);
    addCheck(
        "Directory writable: $dir",
        $writable,
        $exists ? ($writable ? 'OK' : 'Not writable — chmod 755 ' . $dir) : 'Missing — mkdir -p ' . $dir
    );
}

// ═══════════════════════════════════════════════════════════════
// CHECK 9 — File permissions on .env
// ═══════════════════════════════════════════════════════════════
if (file_exists($envFile)) {
    $perms = fileperms($envFile) & 0777;
    // Should be 640 (rw-r-----) or stricter
    $permsOk = $perms <= 0640;
    addCheck(
        '.env file permissions <= 640',
        $permsOk,
        sprintf('Current: %04o — should be 640 (rw-r-----)', $perms)
    );
}

// ═══════════════════════════════════════════════════════════════
// CHECK 10 — Required environment variables are set
// ═══════════════════════════════════════════════════════════════
$requiredEnvVars = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD',
    'STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY',
    'JWT_SECRET', 'INTERNAL_API_KEY',
    'ADMIN_EMAIL', 'ADMIN_PASSWORD',
];
foreach ($requiredEnvVars as $var) {
    $val      = env($var);
    $valLower = strtolower($val);
    $set      = $val !== '' && !str_contains($valLower, 'fill_in') && !str_contains($valLower, 'xxx');
    addCheck("ENV var set: $var", $set, $set ? '(set)' : 'EMPTY or placeholder');
}

// ═══════════════════════════════════════════════════════════════
// CHECK 11 — Stripe key format (live keys in production)
// ═══════════════════════════════════════════════════════════════
$stripePublic = env('STRIPE_PUBLISHABLE_KEY');
$stripeSecret = env('STRIPE_SECRET_KEY');
addCheck(
    'Stripe publishable key is live (pk_live_)',
    str_starts_with($stripePublic, 'pk_live_'),
    'Current prefix: ' . substr($stripePublic, 0, 8) . '…'
);
addCheck(
    'Stripe secret key is live (sk_live_)',
    str_starts_with($stripeSecret, 'sk_live_'),
    'Current prefix: ' . substr($stripeSecret, 0, 8) . '…'
);

// ═══════════════════════════════════════════════════════════════
// CHECK 12 — HTTPS / SSL (web mode only)
// ═══════════════════════════════════════════════════════════════
if (!$isCli) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    addCheck(
        'Request is served over HTTPS',
        $isHttps,
        $isHttps ? 'HTTPS ✔' : 'Not HTTPS — enable in .htaccess and SSL/TLS in cPanel'
    );
} else {
    addCheck('HTTPS check', true, 'Skipped in CLI mode');
}

// ═══════════════════════════════════════════════════════════════
// CHECK 13 — Apache rewrite module (mod_rewrite)
// ═══════════════════════════════════════════════════════════════
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $rewriteOn = in_array('mod_rewrite', $modules, true);
    addCheck('Apache mod_rewrite enabled', $rewriteOn, $rewriteOn ? 'Enabled' : 'Disabled — check cPanel → Apache Modules');
} elseif (!$isCli) {
    // Check via SERVER_SOFTWARE
    $sw = $_SERVER['SERVER_SOFTWARE'] ?? '';
    addCheck('Apache mod_rewrite', true, 'Cannot detect directly — verify .htaccess rewrites work');
} else {
    addCheck('Apache mod_rewrite', true, 'Skipped in CLI mode');
}

// ═══════════════════════════════════════════════════════════════
// CHECK 14 — Disk space (warn if < 100 MB free)
// ═══════════════════════════════════════════════════════════════
$diskFree  = disk_free_space($rootDir);
$diskTotal = disk_total_space($rootDir);
if ($diskFree !== false && $diskTotal !== false) {
    $freeMB    = round($diskFree / 1048576);
    $totalMB   = round($diskTotal / 1048576);
    $diskOk    = $diskFree > 104857600; // > 100 MB
    addCheck(
        'Disk space (>100 MB free)',
        $diskOk,
        "{$freeMB} MB free of {$totalMB} MB total"
    );
} else {
    addCheck('Disk space check', false, 'Could not determine disk space');
}

// ═══════════════════════════════════════════════════════════════
// CHECK 15 — Node.js server reachable (optional)
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
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $nodeReachable = ($code === 200);
    $nodeDetail    = $nodeReachable
        ? "HTTP $code — " . substr((string)$resp, 0, 60)
        : "HTTP $code — server may not be running (see deploy/nodejs-setup.md)";
}
addCheck('Node.js server reachable (port 3001)', $nodeReachable, $nodeDetail);

// ═══════════════════════════════════════════════════════════════
// CHECK 16 — SMTP reachability (optional)
// ═══════════════════════════════════════════════════════════════
$smtpHost   = env('MAIL_HOST');
$smtpPort   = (int)env('MAIL_PORT', '587');
$smtpOk     = false;
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
// CHECK 17 — Security headers (web mode only)
// ═══════════════════════════════════════════════════════════════
if (!$isCli && extension_loaded('curl')) {
    $appUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
    $ch     = curl_init($appUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $headerRaw    = curl_exec($ch);
    curl_close($ch);
    $headersLower = strtolower((string)$headerRaw);
    $secHeaders   = [
        'x-content-type-options',
        'x-frame-options',
        'referrer-policy',
        'strict-transport-security',
    ];
    foreach ($secHeaders as $hdr) {
        addCheck(
            "Security header: $hdr",
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

// ── JSON output ───────────────────────────────────────────────
if ($isJson) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'     => $totalFail === 0 ? 'healthy' : 'unhealthy',
        'passed'     => $totalPass,
        'failed'     => $totalFail,
        'total'      => count($results),
        'timestamp'  => date('c'),
        'php_version'=> PHP_VERSION,
        'checks'     => $results,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    exit($totalFail > 0 ? 1 : 0);
}

// ── CLI output ────────────────────────────────────────────────
if ($isCli) {
    $reset = "\033[0m"; $green = "\033[32m"; $red = "\033[31m"; $bold = "\033[1m"; $yellow = "\033[33m";
    echo "\n{$bold}GlobexSky — Deployment Health Check{$reset}\n";
    echo str_repeat('─', 65) . "\n";
    foreach ($results as $r) {
        $icon   = $r['pass'] ? "{$green}✔{$reset}" : "{$red}✘{$reset}";
        $detail = $r['detail'] ? "  ({$r['detail']})" : '';
        echo "  $icon  {$r['label']}$detail\n";
    }
    echo str_repeat('─', 65) . "\n";
    echo "  {$green}Passed: $totalPass{$reset}  {$red}Failed: $totalFail{$reset}\n\n";
    if ($totalFail > 0) {
        echo "{$yellow}  Fix the issues above before going live.{$reset}\n\n";
    } else {
        echo "{$green}  All checks passed — deployment looks healthy! 🎉{$reset}\n\n";
    }
    exit($totalFail > 0 ? 1 : 0);
}

// ── Web HTML output ───────────────────────────────────────────
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
  .card{background:#1e293b;border-radius:0.75rem;padding:1.5rem;max-width:800px;margin:auto}
  table{width:100%;border-collapse:collapse;margin-top:1rem}
  th,td{padding:0.5rem 0.75rem;text-align:left;border-bottom:1px solid #334155}
  th{color:#94a3b8;font-size:0.8rem;text-transform:uppercase}
  .pass{color:#4ade80}
  .fail{color:#f87171}
  .detail{font-size:0.8rem;color:#94a3b8}
  .summary{margin-top:1.5rem;padding:1rem;border-radius:0.5rem;text-align:center;font-weight:bold}
  .all-pass{background:#14532d;color:#4ade80}
  .has-fail{background:#7f1d1d;color:#f87171}
  .meta{font-size:0.75rem;color:#64748b;margin-top:1rem;text-align:right}
</style>
</head>
<body>
<div class="card">
  <h1>🛡 GlobexSky Health Check</h1>
  <p class="sub">Deployment verification — <?= htmlspecialchars(date('Y-m-d H:i:s T')) ?></p>
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
        ? "✅ All $totalPass checks passed — deployment looks healthy! 🎉"
        : "❌ $totalFail check(s) failed — fix the issues above before going live" ?>
  </div>
  <p class="meta">PHP <?= PHP_VERSION ?> · <?= htmlspecialchars(date('c')) ?> · Passed: <?= $totalPass ?> / <?= count($results) ?></p>
  <p class="meta">JSON API: <code>?key=YOUR_KEY&amp;format=json</code></p>
</div>
</body>
</html>
