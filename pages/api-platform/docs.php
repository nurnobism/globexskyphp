<?php
/**
 * pages/api-platform/docs.php — API Documentation
 */

require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'API Documentation — GlobexSky';
include __DIR__ . '/../../includes/header.php';

$sections = [
    'authentication' => 'Authentication',
    'products'       => 'Products',
    'orders'         => 'Orders',
    'suppliers'      => 'Suppliers',
    'shipments'      => 'Shipments',
    'webhooks'       => 'Webhooks',
    'errors'         => 'Error Codes',
];
$active = get('section', 'authentication');
if (!array_key_exists($active, $sections)) $active = 'authentication';
?>
<div class="container-fluid py-4">
    <div class="row g-0">
        <!-- Sidebar -->
        <div class="col-lg-2 col-md-3" style="position:sticky;top:0;height:100vh;overflow-y:auto;">
            <div class="p-3" style="background:#1B2A4A;min-height:100vh;">
                <div class="text-white fw-bold mb-3"><i class="bi bi-book me-2"></i>API Docs</div>
                <div class="nav flex-column gap-1">
                    <?php foreach ($sections as $key => $label): ?>
                    <a href="?section=<?= $key ?>"
                       class="nav-link rounded px-3 py-2 small <?= $active === $key ? 'text-white fw-semibold' : 'text-white-50' ?>"
                       <?= $active === $key ? 'style="background:#FF6B3540;"' : '' ?>>
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <hr style="border-color:#ffffff30;">
                <a href="/pages/api-platform/keys.php" class="btn btn-sm w-100 text-white" style="background:#FF6B35;">
                    <i class="bi bi-key me-1"></i>My API Keys
                </a>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-lg-10 col-md-9 p-4 p-lg-5">
            <?php if ($active === 'authentication'): ?>
            <h2 class="fw-bold mb-1">Authentication</h2>
            <p class="text-muted mb-4">All API requests require a bearer token in the Authorization header.</p>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold">Base URL</h6>
                    <code>https://api.globexsky.com/v2</code>
                    <h6 class="fw-bold mt-4">Authorization Header</h6>
                    <code>Authorization: Bearer gsk_live_YOUR_API_KEY</code>
                </div>
            </div>
            <h5 class="fw-bold">Code Examples</h5>
            <?php endif; ?>

            <?php if ($active === 'products'): ?>
            <h2 class="fw-bold mb-1">Products</h2>
            <p class="text-muted mb-4">Retrieve and search product listings from GlobexSky suppliers.</p>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <span class="badge bg-success me-2">GET</span><code>/v2/products</code>
                    <p class="mt-2 mb-1 text-muted small">Query params: <code>page</code>, <code>limit</code>, <code>category</code>, <code>q</code></p>
                    <span class="badge bg-success me-2 mt-2">GET</span><code>/v2/products/{id}</code>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($active === 'orders'): ?>
            <h2 class="fw-bold mb-1">Orders</h2>
            <p class="text-muted mb-4">Create and manage orders programmatically.</p>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div><span class="badge bg-success me-2">GET</span><code>/v2/orders</code></div>
                    <div class="mt-2"><span class="badge bg-primary me-2">POST</span><code>/v2/orders</code> — Create order</div>
                    <div class="mt-2"><span class="badge bg-success me-2">GET</span><code>/v2/orders/{id}</code></div>
                    <div class="mt-2"><span class="badge bg-warning text-dark me-2">PATCH</span><code>/v2/orders/{id}/cancel</code></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($active, ['suppliers','shipments','webhooks','errors'])): ?>
            <h2 class="fw-bold mb-1"><?= $sections[$active] ?></h2>
            <p class="text-muted mb-4">Documentation for <?= strtolower($sections[$active]) ?> endpoints and events.</p>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>Full documentation for this section is being expanded. Check back soon.
            </div>
            <?php endif; ?>

            <!-- Tabbed code examples -->
            <ul class="nav nav-tabs" id="codeTabs">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-curl">cURL</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-php">PHP</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-python">Python</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-js">JavaScript</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active p-3 rounded-bottom" id="tab-curl" style="background:#1B2A4A;">
                    <pre class="text-white mb-0" style="font-size:.82rem;">curl -X GET "https://api.globexsky.com/v2/<?= $active === 'authentication' ? 'products' : $active ?>" \
  -H "Authorization: Bearer gsk_live_YOUR_API_KEY" \
  -H "Accept: application/json"</pre>
                </div>
                <div class="tab-pane fade p-3 rounded-bottom" id="tab-php" style="background:#1B2A4A;">
                    <pre class="text-white mb-0" style="font-size:.82rem;">&lt;?php
$client = new \GuzzleHttp\Client(['base_uri' => 'https://api.globexsky.com/v2/']);
$response = $client-&gt;get('<?= $active === 'authentication' ? 'products' : $active ?>', [
    'headers' =&gt; ['Authorization' =&gt; 'Bearer gsk_live_YOUR_API_KEY']
]);
$data = json_decode($response-&gt;getBody(), true);</pre>
                </div>
                <div class="tab-pane fade p-3 rounded-bottom" id="tab-python" style="background:#1B2A4A;">
                    <pre class="text-white mb-0" style="font-size:.82rem;">import requests
headers = {"Authorization": "Bearer gsk_live_YOUR_API_KEY"}
r = requests.get("https://api.globexsky.com/v2/<?= $active === 'authentication' ? 'products' : $active ?>", headers=headers)
data = r.json()</pre>
                </div>
                <div class="tab-pane fade p-3 rounded-bottom" id="tab-js" style="background:#1B2A4A;">
                    <pre class="text-white mb-0" style="font-size:.82rem;">const res = await fetch('https://api.globexsky.com/v2/<?= $active === 'authentication' ? 'products' : $active ?>', {
  headers: { 'Authorization': 'Bearer gsk_live_YOUR_API_KEY' }
});
const data = await res.json();</pre>
                </div>
            </div>

            <!-- Rate limits & errors reference -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold">Response Format</h6>
                    <code>{"data": [...], "total": 120, "page": 1, "pages": 5}</code>
                    <h6 class="fw-bold mt-3">Common HTTP Status Codes</h6>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td><span class="badge bg-success">200</span></td><td>Success</td></tr>
                            <tr><td><span class="badge bg-warning text-dark">400</span></td><td>Bad Request — check parameters</td></tr>
                            <tr><td><span class="badge bg-warning text-dark">401</span></td><td>Unauthorized — invalid or missing API key</td></tr>
                            <tr><td><span class="badge bg-danger">429</span></td><td>Rate Limit Exceeded</td></tr>
                            <tr><td><span class="badge bg-danger">500</span></td><td>Internal Server Error</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
