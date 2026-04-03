<?php
/**
 * tests/smoke.php — Basic smoke tests for CI
 *
 * Verifies that key PHP files parse correctly and that required
 * files and directories exist. Runs without a web server.
 *
 * Exit code 0 = all tests passed, 1 = failure.
 */

$errors = [];
$passed = 0;

function ok(string $label): void
{
    global $passed;
    $passed++;
    echo "  [PASS] $label\n";
}

function fail(string $label, string $detail = ''): void
{
    global $errors;
    $errors[] = $label . ($detail ? ": $detail" : '');
    echo "  [FAIL] $label" . ($detail ? " — $detail" : '') . "\n";
}

function assertFile(string $path): void
{
    $rel = ltrim(str_replace(__DIR__ . '/..', '', realpath($path) ?: $path), '/');
    if (is_file($path)) {
        ok("File exists: $rel");
    } else {
        fail("File missing", $rel);
    }
}

function assertDir(string $path): void
{
    $rel = ltrim(str_replace(__DIR__ . '/..', '', realpath($path) ?: $path), '/');
    if (is_dir($path)) {
        ok("Directory exists: $rel");
    } else {
        fail("Directory missing", $rel);
    }
}

function assertSyntax(string $path): void
{
    $rel = ltrim(str_replace(__DIR__ . '/..', '', realpath($path) ?: $path), '/');
    ob_start();
    $output = shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1');
    ob_end_clean();
    if (strpos((string)$output, 'No syntax errors') !== false) {
        ok("Syntax OK: $rel");
    } else {
        fail("Syntax error in $rel", trim((string)$output));
    }
}

$root = dirname(__DIR__);

echo "\n=== GlobexSky PHP Smoke Tests ===\n\n";

// ── Core files ──────────────────────────────────────────────
echo "Core files:\n";
assertFile("$root/index.php");
assertFile("$root/composer.json");
assertFile("$root/.env.example");
assertFile("$root/.htaccess");

// ── Includes ────────────────────────────────────────────────
echo "\nIncludes:\n";
foreach (['middleware.php', 'header.php', 'footer.php', 'auth.php', 'functions.php', 'auth_guard.php'] as $f) {
    assertFile("$root/includes/$f");
}

// ── Database files ───────────────────────────────────────────
echo "\nDatabase:\n";
assertFile("$root/database/schema.sql");
assertFile("$root/database/seed.sql");
assertFile("$root/database/schema_v2.sql");
assertFile("$root/database/install.sql");

// ── New page directories ─────────────────────────────────────
echo "\nNew page directories:\n";
foreach (['reviews', 'disputes', 'cms', 'wishlist', 'returns'] as $dir) {
    assertDir("$root/pages/$dir");
}

// ── New page files ───────────────────────────────────────────
echo "\nNew page files:\n";
$newPages = [
    'pages/reviews/index.php',
    'pages/reviews/create.php',
    'pages/disputes/index.php',
    'pages/disputes/create.php',
    'pages/disputes/detail.php',
    'pages/cms/index.php',
    'pages/cms/edit.php',
    'pages/wishlist/index.php',
    'pages/returns/index.php',
    'pages/returns/create.php',
    'pages/returns/detail.php',
];
foreach ($newPages as $page) {
    assertFile("$root/$page");
}

// ── New API files ────────────────────────────────────────────
echo "\nNew API files:\n";
$newApis = [
    'api/wishlist.php',
    'api/returns.php',
    'api/vr-showroom.php',
    'api/barcode-scanner.php',
    'api/trade-shows.php',
];
foreach ($newApis as $api) {
    assertFile("$root/$api");
}

// ── DevOps files ─────────────────────────────────────────────
echo "\nDevOps files:\n";
assertFile("$root/Dockerfile");
assertFile("$root/docker-compose.yml");
assertFile("$root/.github/workflows/ci.yml");

// ── Phase 1 Foundation files ─────────────────────────────────
echo "\nPhase 1 Foundation files:\n";
$phase1Files = [
    'includes/auth_guard.php',
    'database/install.sql',
    'pages/admin/login.php',
    'pages/admin/index.php',
    'api/auth.php',
];
foreach ($phase1Files as $f) {
    assertFile("$root/$f");
}

// ── Phase 3: Commission Engine + Plans + Payouts ──────────────
echo "\nPhase 3 includes:\n";
$phase3Includes = [
    'includes/commission.php',
    'includes/plan_limits.php',
    'includes/coupon_engine.php',
    'includes/tax_engine.php',
    'includes/price_engine.php',
];
foreach ($phase3Includes as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 3 APIs:\n";
$phase3Apis = [
    'api/commissions.php',
    'api/plans.php',
    'api/payouts.php',
];
foreach ($phase3Apis as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 3 supplier pages:\n";
$phase3SupplierPages = [
    'pages/supplier/plans.php',
    'pages/supplier/plan-upgrade.php',
    'pages/supplier/billing.php',
    'pages/supplier/earnings.php',
    'pages/supplier/payouts.php',
];
foreach ($phase3SupplierPages as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 3 admin finance pages:\n";
$phase3AdminPages = [
    'pages/admin/finance/index.php',
    'pages/admin/finance/commissions.php',
    'pages/admin/finance/payouts.php',
    'pages/admin/finance/invoices.php',
];
foreach ($phase3AdminPages as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 3 database:\n";
assertFile("$root/database/schema_v3.sql");

// ── PHP syntax checks ────────────────────────────────────────
echo "\nPHP syntax:\n";
$allPhp = array_merge(
    $newPages, $newApis,
    array_filter($phase1Files, fn($f) => str_ends_with($f, '.php')),
    $phase3Includes, $phase3Apis, $phase3SupplierPages, $phase3AdminPages
);
foreach ($allPhp as $f) {
    assertSyntax("$root/$f");
}

// ── Summary ──────────────────────────────────────────────────
$total = $passed + count($errors);
echo "\n=== Results: $passed/$total passed";
if ($errors) {
    echo ", " . count($errors) . " failed ===\n\nFailed tests:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}
echo " ===\n\nAll smoke tests passed! ✓\n";
exit(0);
