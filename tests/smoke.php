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
foreach (['middleware.php', 'header.php', 'footer.php', 'auth.php', 'functions.php', 'auth_guard.php', 'cart.php', 'wishlist.php'] as $f) {
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

// ── PR #6: Checkout & Stripe Payment ─────────────────────────
echo "\nPR #6 Checkout & Stripe files:\n";
$pr6Files = [
    'includes/checkout.php',
    'includes/stripe-handler.php',
    'api/stripe-webhook.php',
    'pages/checkout/index.php',
    'pages/checkout/confirmation.php',
    'pages/checkout/payment-success.php',
    'pages/checkout/payment-failed.php',
    'database/schema_v14_checkout.sql',
];
foreach ($pr6Files as $f) {
    assertFile("$root/$f");
}
foreach ($pr6Files as $f) {
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


// ── PR #4: 3-Level Hierarchical Category System ──────────────
echo "\nPR #4 Category system database:\n";
assertFile("$root/database/schema_v13_categories.sql");
assertFile("$root/database/seed_categories.sql");

echo "\nPR #4 Category includes:\n";
assertFile("$root/includes/categories.php");
assertSyntax("$root/includes/categories.php");

echo "\nPR #4 Category API:\n";
assertFile("$root/api/categories.php");
assertSyntax("$root/api/categories.php");

echo "\nPR #4 Admin category pages:\n";
$pr4AdminPages = [
    'pages/admin/categories/index.php',
    'pages/admin/categories/add.php',
    'pages/admin/categories/edit.php',
];
foreach ($pr4AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #4 Category components:\n";
$pr4Components = [
    'pages/components/category-nav.php',
    'pages/components/category-sidebar.php',
];
foreach ($pr4Components as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── PR #7: Order Management ──────────────────────────────────
echo "\nPR #7 Order Management database:\n";
assertFile("$root/database/schema_v14_orders.sql");

echo "\nPR #7 Order Management includes:\n";
assertFile("$root/includes/orders.php");
assertSyntax("$root/includes/orders.php");

echo "\nPR #7 Order Management API:\n";
assertFile("$root/api/orders.php");
assertSyntax("$root/api/orders.php");

echo "\nPR #7 Buyer order pages:\n";
$pr7BuyerPages = [
    'pages/account/orders/index.php',
    'pages/account/orders/detail.php',
];
foreach ($pr7BuyerPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #7 Supplier order pages:\n";
$pr7SupplierPages = [
    'pages/supplier/orders/index.php',
    'pages/supplier/orders/detail.php',
];
foreach ($pr7SupplierPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #7 Admin order pages:\n";
$pr7AdminPages = [
    'pages/admin/orders/index.php',
    'pages/admin/orders/detail.php',
];
foreach ($pr7AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── PR #8: Commission Engine ──────────────────────────────────
echo "\nPR #8 Commission Engine database:\n";
assertFile("$root/database/schema_v15_commission.sql");

echo "\nPR #8 Commission Engine includes:\n";
assertFile("$root/includes/commission.php");
assertSyntax("$root/includes/commission.php");

echo "\nPR #8 Commission API:\n";
assertFile("$root/api/commission.php");
assertSyntax("$root/api/commission.php");

echo "\nPR #8 Admin commission pages:\n";
$pr8AdminPages = [
    'pages/admin/commission/index.php',
    'pages/admin/commission/tiers.php',
    'pages/admin/commission/categories.php',
];
foreach ($pr8AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #8 Supplier commission page:\n";
assertFile("$root/pages/supplier/earnings/commission.php");
assertSyntax("$root/pages/supplier/earnings/commission.php");

// ── PR #9: Supplier Plans — Free / Pro / Enterprise ──────────
echo "\nPR #9 Plans database:\n";
assertFile("$root/database/schema_v15_plans.sql");

echo "\nPR #9 Plans includes:\n";
$pr9Includes = [
    'includes/plans.php',
];
foreach ($pr9Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #9 Plans API:\n";
assertFile("$root/api/plans.php");
assertSyntax("$root/api/plans.php");

echo "\nPR #9 Supplier plan pages:\n";
$pr9SupplierPages = [
    'pages/supplier/plans/index.php',
    'pages/supplier/plans/billing.php',
    'pages/supplier/plans/upgrade.php',
];
foreach ($pr9SupplierPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #9 Admin plan pages:\n";
assertFile("$root/pages/admin/plans/index.php");
assertSyntax("$root/pages/admin/plans/index.php");

echo "\nPR #9 Plan limit enforcement:\n";
assertFile("$root/pages/supplier/product-add.php");
assertSyntax("$root/pages/supplier/product-add.php");

// ── PR #10: Add-On Purchases & Invoice System ────────────────
echo "\nPR #10 Add-On & Invoice database:\n";
assertFile("$root/database/schema_v15_addons.sql");

echo "\nPR #10 includes:\n";
$pr10Includes = [
    'includes/addons.php',
    'includes/invoices.php',
];
foreach ($pr10Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #10 APIs:\n";
$pr10Apis = [
    'api/addons.php',
    'api/invoices.php',
];
foreach ($pr10Apis as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #10 Supplier billing pages:\n";
$pr10SupplierPages = [
    'pages/supplier/billing/addons.php',
    'pages/supplier/billing/invoices.php',
    'pages/supplier/billing/invoice-detail.php',
];
foreach ($pr10SupplierPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #10 Admin billing pages:\n";
$pr10AdminPages = [
    'pages/admin/billing/addons.php',
    'pages/admin/billing/invoices.php',
];
foreach ($pr10AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── PR #11: Payout System — Supplier Withdrawals ─────────────
echo "\nPR #11 Payout System database:\n";
assertFile("$root/database/schema_v16_payouts.sql");

echo "\nPR #11 Payout includes:\n";
assertFile("$root/includes/payouts.php");
assertSyntax("$root/includes/payouts.php");

echo "\nPR #11 Payout API:\n";
assertFile("$root/api/payouts.php");
assertSyntax("$root/api/payouts.php");

echo "\nPR #11 Supplier earnings pages:\n";
$pr11SupplierPages = [
    'pages/supplier/earnings/index.php',
    'pages/supplier/earnings/withdraw.php',
    'pages/supplier/earnings/history.php',
    'pages/supplier/earnings/methods.php',
];
foreach ($pr11SupplierPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #11 Admin payout pages:\n";
$pr11AdminPages = [
    'pages/admin/finance/payouts.php',
    'pages/admin/finance/payout-detail.php',
    'pages/admin/finance/index.php',
];
foreach ($pr11AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── PR #12: Tax Calculation Engine ───────────────────────────
echo "\nPR #12 Tax Engine database:\n";
assertFile("$root/database/schema_v16_tax.sql");

echo "\nPR #12 Tax Engine includes:\n";
$pr12Includes = [
    'includes/tax_engine.php',
    'includes/countries.php',
];
foreach ($pr12Includes as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #12 Tax API:\n";
assertFile("$root/api/tax.php");
assertSyntax("$root/api/tax.php");

echo "\nPR #12 Admin tax pages:\n";
$pr12AdminPages = [
    'pages/admin/tax/index.php',
    'pages/admin/tax/rates.php',
    'pages/admin/tax/report.php',
];
foreach ($pr12AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #12 Checkout & product integration:\n";
assertSyntax("$root/pages/checkout/index.php");
assertSyntax("$root/pages/product/detail.php");

// ── PR #13: Coupon & Promotion System ────────────────────────
echo "\nPR #13 Coupon database:\n";
assertFile("$root/database/schema_v16_coupons.sql");

echo "\nPR #13 Coupon includes:\n";
assertFile("$root/includes/coupons.php");
assertSyntax("$root/includes/coupons.php");

echo "\nPR #13 Coupon API:\n";
assertFile("$root/api/coupons.php");
assertSyntax("$root/api/coupons.php");

echo "\nPR #13 Promotions API:\n";
assertFile("$root/api/promotions.php");
assertSyntax("$root/api/promotions.php");

echo "\nPR #13 Supplier coupon pages:\n";
$pr13SupplierPages = [
    'pages/supplier/marketing/coupons.php',
    'pages/supplier/marketing/coupon-form.php',
];
foreach ($pr13SupplierPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #13 Admin marketing pages:\n";
$pr13AdminPages = [
    'pages/admin/marketing/coupons.php',
    'pages/admin/marketing/promotions.php',
    'pages/admin/marketing/coupon-analytics.php',
];
foreach ($pr13AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #13 Promotion banner component:\n";
assertFile("$root/pages/components/promotion-banner.php");
assertSyntax("$root/pages/components/promotion-banner.php");

// ── PR #14: Shipping Calculator — Zone-Based Rates ───────────
echo "\nPR #14 Shipping database:\n";
assertFile("$root/database/schema_v17_shipping.sql");

echo "\nPR #14 Shipping includes:\n";
assertFile("$root/includes/shipping.php");
assertSyntax("$root/includes/shipping.php");

echo "\nPR #14 Shipping API:\n";
assertFile("$root/api/shipping.php");
assertSyntax("$root/api/shipping.php");

echo "\nPR #14 Admin shipping pages:\n";
$pr14AdminPages = [
    'pages/admin/shipping/zones.php',
    'pages/admin/shipping/methods.php',
    'pages/admin/shipping/settings.php',
];
foreach ($pr14AdminPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #14 Supplier shipping pages:\n";
assertFile("$root/pages/supplier/shipping/index.php");
assertSyntax("$root/pages/supplier/shipping/index.php");

echo "\nPR #14 Checkout & product integration:\n";
assertSyntax("$root/pages/checkout/index.php");
assertSyntax("$root/pages/product/detail.php");
assertSyntax("$root/includes/checkout.php");

// ── PR #15: Parcel Tracking Integration ──────────────────────
echo "\nPR #15 Tracking database:\n";
assertFile("$root/database/schema_v17_tracking.sql");

echo "\nPR #15 Tracking includes:\n";
assertFile("$root/includes/tracking.php");
assertSyntax("$root/includes/tracking.php");

echo "\nPR #15 Tracking API:\n";
assertFile("$root/api/tracking.php");
assertSyntax("$root/api/tracking.php");

echo "\nPR #15 Buyer tracking page:\n";
assertFile("$root/pages/account/orders/tracking.php");
assertSyntax("$root/pages/account/orders/tracking.php");

echo "\nPR #15 Supplier ship form:\n";
assertFile("$root/pages/supplier/orders/ship.php");
assertSyntax("$root/pages/supplier/orders/ship.php");

echo "\nPR #15 Admin tracking dashboard:\n";
assertFile("$root/pages/admin/logistics/tracking.php");
assertSyntax("$root/pages/admin/logistics/tracking.php");

// ── PR #16: Carry Service ─────────────────────────────────────
echo "\nPR #16 Carry Service database:\n";
assertFile("$root/database/schema_v17_carry.sql");

echo "\nPR #16 Carry Service includes:\n";
assertFile("$root/includes/carry.php");
assertSyntax("$root/includes/carry.php");

echo "\nPR #16 Carry Service API:\n";
assertFile("$root/api/carry.php");
assertSyntax("$root/api/carry.php");

echo "\nPR #16 Carry browse/buyer pages:\n";
$pr16CarryPages = [
    'pages/carry/index.php',
    'pages/carry/trip-detail.php',
    'pages/carry/my-requests.php',
];
foreach ($pr16CarryPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #16 Carrier dashboard & management pages:\n";
$pr16CarrierPages = [
    'pages/carrier/dashboard.php',
    'pages/carrier/trips/index.php',
    'pages/carrier/requests/index.php',
];
foreach ($pr16CarrierPages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #16 Admin carry management:\n";
assertFile("$root/pages/admin/logistics/carry.php");
assertSyntax("$root/pages/admin/logistics/carry.php");

// ── PR #17: Address Management ────────────────────────────────
echo "\nPR #17 Address database schema:\n";
assertFile("$root/database/schema_v17_addresses.sql");

echo "\nPR #17 Address includes:\n";
assertFile("$root/includes/countries_data.php");
assertSyntax("$root/includes/countries_data.php");
assertFile("$root/includes/addresses.php");
assertSyntax("$root/includes/addresses.php");

echo "\nPR #17 Address API:\n";
assertFile("$root/api/addresses.php");
assertSyntax("$root/api/addresses.php");

echo "\nPR #17 Address pages:\n";
$pr17Pages = [
    'pages/account/addresses/index.php',
    'pages/account/addresses/form.php',
];
foreach ($pr17Pages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

// ── PR #21: Notification Engine ───────────────────────────────
echo "\nPR #21 Notification database schema:\n";
assertFile("$root/database/schema_v21_notifications.sql");

echo "\nPR #21 Notification engine includes:\n";
assertFile("$root/includes/notifications.php");
assertSyntax("$root/includes/notifications.php");

echo "\nPR #21 Notification API:\n";
assertFile("$root/api/notifications.php");
assertSyntax("$root/api/notifications.php");

echo "\nPR #21 Notification pages:\n";
$pr21Pages = [
    'pages/notifications/index.php',
    'pages/notifications/settings.php',
    'pages/notifications/preferences.php',
];
foreach ($pr21Pages as $f) {
    assertFile("$root/$f");
    assertSyntax("$root/$f");
}

echo "\nPR #21 Notification engine functions:\n";
$notifSource = file_get_contents("$root/includes/notifications.php");
$requiredFunctions = [
    'notify',
    'getNotifications',
    'getUnreadCount',
    'markAsRead',
    'markAllAsRead',
    'deleteNotification',
    'clearAll',
    'groupNotifications',
    'getNotificationEventTypes',
    'getNotificationIcon',
    'formatTimeAgo',
    'createNotification',
    'getUserNotifications',
    'createBulkNotification',
];
foreach ($requiredFunctions as $fn) {
    if (strpos($notifSource, "function $fn(") !== false) {
        ok("notifications.php defines function $fn()");
    } else {
        fail("notifications.php missing function $fn()");
    }
}

echo "\nPR #21 Notification API actions:\n";
$apiSource = file_get_contents("$root/api/notifications.php");
$requiredActions = ['list','unread_count','mark_read','mark_all_read','delete','clear_all','preferences','update_preferences'];
foreach ($requiredActions as $action) {
    if (strpos($apiSource, "case '$action':") !== false) {
        ok("api/notifications.php handles action '$action'");
    } else {
        fail("api/notifications.php missing action '$action'");
    }
}

echo "\nPR #21 Notification event types (50+):\n";
preg_match_all("/'([a-z0-9_.]+)'\s*=>/", $notifSource, $m);
$eventTypes = array_unique(array_filter($m[1], fn($t) => str_contains($t, '.') || in_array($t, [
    'order_placed','order_shipped','order_delivered','order_cancelled','order_refunded',
    'payment_received','payment_failed','message_received','product_approved','product_rejected',
    'low_stock','account_verified','password_changed','promo','system',
])));
$evtCount = count($eventTypes);
if ($evtCount >= 50) {
    ok("notifications.php defines $evtCount event types (>= 50 required)");
} else {
    fail("notifications.php only defines $evtCount event types (need >= 50)");
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
