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

// ── Phase 4 Shipment System files ───────────────────────────
echo "\nPhase 4 Shipment System files:\n";
$phase4Files = [
    'pages/shipment/parcel/my-shipments.php',
    'pages/shipment/carry/trips.php',
    'pages/shipment/carry/requests.php',
    'pages/shipment/carry/matches.php',
    'pages/order/track.php',
    'pages/admin/logistics/index.php',
    'pages/admin/logistics/parcels.php',
    'pages/admin/logistics/carriers.php',
    'pages/admin/logistics/carry-requests.php',
    'pages/admin/logistics/rates.php',
    'pages/admin/pricing/parcel-rates.php',
];
foreach ($phase4Files as $f) {
    assertFile("$root/$f");
}

// ── PHP syntax checks ────────────────────────────────────────
echo "\nPHP syntax:\n";
$phase4PhpFiles = array_filter($phase4Files, fn($f) => str_ends_with($f, '.php'));
$allPhp = array_merge(
    $newPages, $newApis,
    array_filter($phase1Files, fn($f) => str_ends_with($f, '.php')),
    $phase3Includes, $phase3Apis, $phase3SupplierPages, $phase3AdminPages,
    $phase4PhpFiles
);
foreach ($allPhp as $f) {
    assertSyntax("$root/$f");
}

// ── Phase 2 files ────────────────────────────────────────────
echo "\nPhase 2 files:\n";
$phase2Files = [
    'config/stripe.php',
    'api/checkout.php',
    'api/payments.php',
    'pages/supplier/dashboard.php',
    'pages/supplier/products.php',
    'pages/supplier/product-add.php',
    'pages/supplier/product-edit.php',
    'pages/supplier/products/create.php',
    'pages/supplier/orders.php',
    'pages/order/confirmation.php',
];
foreach ($phase2Files as $f) {
    assertFile("$root/$f");
}
foreach ($phase2Files as $f) {
    if (str_ends_with($f, '.php')) assertSyntax("$root/$f");
}

// ── Phase 5: Real-Time Chat + Notifications + Webmail ────────
echo "\nPhase 5 database:\n";
assertFile("$root/database/schema_v5.sql");

echo "\nPhase 5 Node.js server:\n";
assertFile("$root/nodejs/server.js");
assertFile("$root/nodejs/package.json");
assertFile("$root/nodejs/.env.example");

echo "\nPhase 5 includes:\n";
$phase5Includes = [
    'includes/notifications.php',
    'includes/mailer.php',
];
foreach ($phase5Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 5 APIs:\n";
$phase5Apis = [
    'api/chat.php',
    'api/notifications.php',
    'api/webmail.php',
];
foreach ($phase5Apis as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 5 chat pages:\n";
$phase5ChatPages = [
    'pages/messages/index.php',
    'pages/messages/conversation.php',
    'pages/messages/compose.php',
];
foreach ($phase5ChatPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 5 notification pages:\n";
$phase5NotifPages = [
    'pages/notifications/index.php',
    'pages/notifications/preferences.php',
];
foreach ($phase5NotifPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 5 webmail pages:\n";
$phase5WebmailPages = [
    'pages/webmail/inbox.php',
    'pages/webmail/compose.php',
    'pages/webmail/sent.php',
    'pages/webmail/drafts.php',
    'pages/webmail/trash.php',
    'pages/webmail/read.php',
    'pages/webmail/labels.php',
];
foreach ($phase5WebmailPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 5 email templates:\n";
$phase5Templates = [
    'templates/emails/base.php',
    'templates/emails/welcome.php',
    'templates/emails/order-confirmation.php',
    'templates/emails/password-reset.php',
    'templates/emails/new-message.php',
];
foreach ($phase5Templates as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 5 JavaScript:\n";
$phase5Js = [
    'assets/js/chat.js',
    'assets/js/socket-client.js',
    'assets/js/notifications.js',
    'assets/js/notification-sounds.js',
];
foreach ($phase5Js as $f) {
    assertFile("$root/$f");
}

// ── Phase 6 Dropshipping Engine ──────────────────────────────
echo "\nPhase 6 Dropshipping Engine:\n";
$phase6Includes = [
    'includes/dropshipping.php',
    'includes/dropship-payment.php',
];
foreach ($phase6Includes as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 6 database:\n";
assertFile("$root/database/schema_v4.sql");

echo "\nPhase 6 dropshipping pages:\n";
$phase6Pages = [
    'pages/dropshipping/index.php',
    'pages/dropshipping/products.php',
    'pages/dropshipping/import.php',
    'pages/dropshipping/my-products.php',
    'pages/dropshipping/orders.php',
    'pages/dropshipping/store.php',
    'pages/dropshipping/store-preview.php',
    'pages/dropshipping/earnings.php',
    'pages/dropshipping/supplier-settings.php',
    'pages/dropshipping/supplier-dropshippers.php',
    'pages/dropshipping/supplier-orders.php',
];
foreach ($phase6Pages as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 6 admin pages:\n";
$phase6AdminPages = [
    'pages/admin/dropshipping.php',
    'pages/admin/dropship-orders.php',
];
foreach ($phase6AdminPages as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 6 APIs:\n";
$phase6Apis = [
    'api/dropshipping.php',
    'api/dropship-external.php',
];
foreach ($phase6Apis as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 6 cron:\n";
assertFile("$root/cron/sync-dropship-products.php");

echo "\nPhase 6 syntax:\n";
$phase6All = array_merge($phase6Includes, $phase6Pages, $phase6AdminPages, $phase6Apis, ['cron/sync-dropship-products.php']);
foreach ($phase6All as $f) {
    assertSyntax("$root/$f");
}

// ── Phase 7: REST API Platform + Live Streaming + Webhooks ───
echo "\nPhase 7 includes:\n";
$phase7Includes = [
    'includes/api-auth.php',
    'includes/api-response.php',
    'includes/webhooks.php',
];
foreach ($phase7Includes as $f) {
    assertFile("$root/$f");
}
foreach ($phase7Includes as $f) {
    assertSyntax("$root/$f");
}

echo "\nPhase 7 database:\n";
assertFile("$root/database/schema_v7.sql");

echo "\nPhase 7 API v1 endpoints:\n";
$phase7ApiV1 = [
    'api/v1/gateway.php',
    'api/v1/products.php',
    'api/v1/orders.php',
    'api/v1/users.php',
    'api/v1/cart.php',
    'api/v1/reviews.php',
    'api/v1/shipping.php',
    'api/v1/dropship.php',
    'api/v1/webhooks.php',
];
foreach ($phase7ApiV1 as $f) {
    assertFile("$root/$f");
}
foreach ($phase7ApiV1 as $f) {
    assertSyntax("$root/$f");
}

echo "\nPhase 7 Live Stream API:\n";
assertFile("$root/api/live.php");
assertSyntax("$root/api/live.php");

echo "\nPhase 7 API developer portal pages:\n";
$phase7ApiPages = [
    'pages/api/index.php',
    'pages/api/keys.php',
    'pages/api/docs.php',
    'pages/api/logs.php',
    'pages/api/usage.php',
    'pages/api/webhooks.php',
];
foreach ($phase7ApiPages as $f) {
    assertFile("$root/$f");
}
foreach ($phase7ApiPages as $f) {
    assertSyntax("$root/$f");
}

echo "\nPhase 7 Live stream pages:\n";
$phase7LivePages = [
    'pages/live/index.php',
    'pages/live/watch.php',
    'pages/live/stream.php',
    'pages/live/schedule.php',
    'pages/live/vod.php',
];
foreach ($phase7LivePages as $f) {
    assertFile("$root/$f");
}
foreach ($phase7LivePages as $f) {
    assertSyntax("$root/$f");
}

echo "\nPhase 7 Admin pages:\n";
assertFile("$root/pages/admin/api-management.php");
assertSyntax("$root/pages/admin/api-management.php");
assertFile("$root/pages/admin/live-streams.php");
assertSyntax("$root/pages/admin/live-streams.php");

echo "\nPhase 7 Cron:\n";
assertFile("$root/cron/process-webhooks.php");
assertSyntax("$root/cron/process-webhooks.php");

echo "\nPhase 7 SDKs:\n";
assertFile("$root/sdk/php/GlobexSkyClient.php");
assertSyntax("$root/sdk/php/GlobexSkyClient.php");
assertFile("$root/sdk/javascript/globexsky.js");
assertFile("$root/sdk/python/globexsky.py");

echo "\nPhase 7 JS & Node.js:\n";
assertFile("$root/assets/js/live-stream.js");
assertFile("$root/assets/js/api-docs.js");
assertFile("$root/nodejs/server.js");

// ── Phase 8: AI Integration (DeepSeek) ───────────────────────
echo "\nPhase 8 database:\n";
assertFile("$root/database/schema_v8.sql");

echo "\nPhase 8 includes:\n";
$phase8Includes = [
    'includes/deepseek.php',
    'includes/ai-recommendations.php',
    'includes/ai-fraud.php',
    'includes/ai-content.php',
    'includes/ai-analytics.php',
];
foreach ($phase8Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 8 AI pages:\n";
$phase8Pages = [
    'pages/ai/index.php',
    'pages/ai/chatbot.php',
    'pages/ai/recommendations.php',
    'pages/ai/fraud-detection.php',
    'pages/ai/search.php',
    'pages/ai/analytics.php',
    'pages/ai/insights.php',
    'pages/ai/content-generator.php',
    'pages/admin/ai-dashboard.php',
];
foreach ($phase8Pages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 8 AI APIs:\n";
$phase8Apis = [
    'api/ai/chatbot.php',
    'api/ai/recommendations.php',
    'api/ai/fraud-detection.php',
    'api/ai/search.php',
    'api/ai/analytics.php',
    'api/ai/insights.php',
    'api/ai/content.php',
    'api/ai/supplier.php',
];
foreach ($phase8Apis as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 8 JavaScript assets:\n";
$phase8Js = [
    'assets/js/ai-chat.js',
    'assets/js/ai-recommendations.js',
    'assets/js/ai-fraud.js',
    'assets/js/ai-content.js',
    'assets/js/ai-analytics.js',
];
foreach ($phase8Js as $f) {
    assertFile("$root/$f");
}

echo "\nPhase 8 cron jobs:\n";
$phase8Cron = [
    'cron/ai-recommendations.php',
    'cron/ai-fraud-scan.php',
];
foreach ($phase8Cron as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── Phase 9: KYC + Advanced Admin ────────────────────────────
echo "\nPhase 9 database:\n";
assertFile("$root/database/schema_v5_kyc.sql");
assertFile("$root/database/schema_v9.sql");

echo "\nPhase 9 includes:\n";
$phase9Includes = [
    'includes/kyc.php',
    'includes/feature_toggles.php',
    'includes/admin_permissions.php',
];
foreach ($phase9Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 9 pages:\n";
$phase9Pages = [
    'pages/supplier/kyc.php',
    'pages/admin/kyc-management.php',
    'pages/admin/advanced-settings.php',
    'pages/admin/user-management.php',
];
foreach ($phase9Pages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 9 APIs:\n";
$phase9Apis = [
    'api/kyc.php',
    'api/admin-kyc.php',
    'api/admin-users.php',
    'api/admin-settings.php',
];
foreach ($phase9Apis as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 9 user pages:\n";
assertFile("$root/pages/account/kyc.php");
assertSyntax("$root/pages/account/kyc.php");

echo "\nPhase 9 admin KYC pages:\n";
$phase9AdminKycPages = [
    'pages/admin/kyc/index.php',
    'pages/admin/kyc/review.php',
];
foreach ($phase9AdminKycPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 9 admin pages:\n";
$phase9AdminPages = [
    'pages/admin/users.php',
    'pages/admin/audit-log.php',
    'pages/admin/settings.php',
];
foreach ($phase9AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── Phase 10: i18n + Currency + PWA ──────────────────────────
echo "\nPhase 10 database:\n";
assertFile("$root/database/schema_v10.sql");

echo "\nPhase 10 includes:\n";
$phase10Includes = [
    'includes/i18n.php',
    'includes/currency.php',
];
foreach ($phase10Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPhase 10 language files:\n";
$langs = ['en','zh','ar','es','fr','de','ja','ko','pt','ru','hi','bn','tr','it','nl','th','vi','id','ms','pl'];
foreach ($langs as $lang) {
    assertFile("$root/lang/$lang.php");
    assertSyntax("$root/lang/$lang.php");
}

echo "\nPhase 10 PWA files:\n";
assertFile("$root/manifest.json");
assertFile("$root/sw.js");
assertFile("$root/assets/js/pwa.js");

// ── PR #3: Product Variations & SKU Matrix ───────────────────
echo "\nPR #3 Variation system:\n";
assertFile("$root/database/schema_v12_variations.sql");
assertFile("$root/includes/variations.php");
assertSyntax("$root/includes/variations.php");
assertFile("$root/pages/supplier/products/variations.php");
assertSyntax("$root/pages/supplier/products/variations.php");
assertFile("$root/api/products.php");
assertSyntax("$root/api/products.php");
assertFile("$root/pages/supplier/product-add.php");
assertSyntax("$root/pages/supplier/product-add.php");
assertFile("$root/pages/supplier/product-edit.php");
assertSyntax("$root/pages/supplier/product-edit.php");
assertFile("$root/pages/product/detail.php");
assertSyntax("$root/pages/product/detail.php");


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
