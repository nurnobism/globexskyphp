<?php
/**
 * pages/api/docs.php — Interactive API Documentation
 * Publicly accessible — no auth required to read docs.
 */
require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'API Documentation';

// API endpoints documentation structure
$endpoints = [
    'products' => [
        'label' => 'Products',
        'icon'  => 'bi-box-seam',
        'actions' => [
            ['method' => 'GET',    'action' => 'list',       'summary' => 'List products',        'auth' => false, 'params' => [['name'=>'page','type'=>'int','req'=>false,'desc'=>'Page number'],['name'=>'per_page','type'=>'int','req'=>false,'desc'=>'Items per page (max 100)'],['name'=>'category','type'=>'int','req'=>false,'desc'=>'Category ID'],['name'=>'search','type'=>'string','req'=>false,'desc'=>'Search keyword']]],
            ['method' => 'GET',    'action' => 'detail',     'summary' => 'Get product details',  'auth' => false, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Product ID']]],
            ['method' => 'GET',    'action' => 'search',     'summary' => 'Search products',      'auth' => false, 'params' => [['name'=>'q','type'=>'string','req'=>true,'desc'=>'Search query']]],
            ['method' => 'GET',    'action' => 'categories', 'summary' => 'List categories',      'auth' => false, 'params' => []],
            ['method' => 'POST',   'action' => 'create',     'summary' => 'Create product',       'auth' => true,  'params' => [['name'=>'name','type'=>'string','req'=>true,'desc'=>'Product name'],['name'=>'price','type'=>'float','req'=>true,'desc'=>'Price'],['name'=>'description','type'=>'string','req'=>false,'desc'=>'Description']]],
            ['method' => 'PUT',    'action' => 'update',     'summary' => 'Update product',       'auth' => true,  'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Product ID']]],
            ['method' => 'DELETE', 'action' => 'delete',     'summary' => 'Delete product',       'auth' => true,  'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Product ID']]],
        ],
    ],
    'orders' => [
        'label' => 'Orders',
        'icon'  => 'bi-bag',
        'actions' => [
            ['method' => 'GET',  'action' => 'list',          'summary' => 'List orders',        'auth' => true, 'params' => [['name'=>'status','type'=>'string','req'=>false,'desc'=>'Filter by status'],['name'=>'from','type'=>'date','req'=>false,'desc'=>'From date']]],
            ['method' => 'GET',  'action' => 'detail',        'summary' => 'Order details',      'auth' => true, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Order ID']]],
            ['method' => 'POST', 'action' => 'create',        'summary' => 'Create order',       'auth' => true, 'params' => [['name'=>'items','type'=>'array','req'=>true,'desc'=>'Order items']]],
            ['method' => 'PUT',  'action' => 'update_status', 'summary' => 'Update order status','auth' => true, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Order ID'],['name'=>'status','type'=>'string','req'=>true,'desc'=>'New status']]],
            ['method' => 'POST', 'action' => 'cancel',        'summary' => 'Cancel order',       'auth' => true, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Order ID']]],
            ['method' => 'GET',  'action' => 'tracking',      'summary' => 'Get tracking info',  'auth' => true, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Order ID']]],
        ],
    ],
    'cart' => [
        'label' => 'Cart',
        'icon'  => 'bi-cart',
        'actions' => [
            ['method' => 'GET',    'action' => 'list',    'summary' => 'Get cart items',     'auth' => true, 'params' => []],
            ['method' => 'POST',   'action' => 'add',     'summary' => 'Add item to cart',   'auth' => true, 'params' => [['name'=>'product_id','type'=>'int','req'=>true,'desc'=>'Product ID'],['name'=>'quantity','type'=>'int','req'=>false,'desc'=>'Qty (default 1)']]],
            ['method' => 'PUT',    'action' => 'update',  'summary' => 'Update cart item',   'auth' => true, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Cart item ID'],['name'=>'quantity','type'=>'int','req'=>true,'desc'=>'New quantity']]],
            ['method' => 'DELETE', 'action' => 'remove',  'summary' => 'Remove item',        'auth' => true, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'Cart item ID']]],
            ['method' => 'DELETE', 'action' => 'clear',   'summary' => 'Clear cart',         'auth' => true, 'params' => []],
            ['method' => 'GET',    'action' => 'summary', 'summary' => 'Cart totals',        'auth' => true, 'params' => []],
        ],
    ],
    'users' => [
        'label' => 'Users',
        'icon'  => 'bi-person',
        'actions' => [
            ['method' => 'GET',  'action' => 'profile',        'summary' => 'Get current user profile', 'auth' => true,  'params' => []],
            ['method' => 'PUT',  'action' => 'update_profile', 'summary' => 'Update profile',           'auth' => true,  'params' => [['name'=>'first_name','type'=>'string','req'=>false,'desc'=>'First name'],['name'=>'last_name','type'=>'string','req'=>false,'desc'=>'Last name']]],
            ['method' => 'GET',  'action' => 'public_profile', 'summary' => 'Get public profile by ID', 'auth' => false, 'params' => [['name'=>'id','type'=>'int','req'=>true,'desc'=>'User ID']]],
            ['method' => 'POST', 'action' => 'register',       'summary' => 'Register new user',        'auth' => false, 'params' => [['name'=>'email','type'=>'string','req'=>true,'desc'=>'Email'],['name'=>'password','type'=>'string','req'=>true,'desc'=>'Password (min 8 chars)']]],
            ['method' => 'POST', 'action' => 'login',          'summary' => 'Login & get token',        'auth' => false, 'params' => [['name'=>'email','type'=>'string','req'=>true,'desc'=>'Email'],['name'=>'password','type'=>'string','req'=>true,'desc'=>'Password']]],
        ],
    ],
];

$methodColors = ['GET' => 'success', 'POST' => 'primary', 'PUT' => 'warning', 'DELETE' => 'danger'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Left sidebar nav -->
        <div class="col-lg-2 col-md-3 d-none d-md-block">
            <div class="sticky-top" style="top:1rem">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-dark text-white fw-semibold small">
                        <i class="bi bi-book"></i> API Reference v1
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($endpoints as $resource => $info): ?>
                        <a href="#resource-<?= $resource ?>" class="list-group-item list-group-item-action py-2 small">
                            <i class="bi <?= $info['icon'] ?>"></i> <?= $info['label'] ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-grid">
                    <a href="<?= APP_URL ?>/pages/api/keys.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-key"></i> Get API Key
                    </a>
                </div>
            </div>
        </div>

        <!-- Main documentation -->
        <div class="col-lg-10 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1"><i class="bi bi-book text-primary"></i> API Documentation</h1>
                    <p class="text-muted mb-0">GlobexSky REST API v1 — Base URL: <code><?= APP_URL ?>/api/v1/gateway.php</code></p>
                </div>
                <span class="badge bg-success fs-6">v1.0</span>
            </div>

            <!-- Authentication info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white"><i class="bi bi-shield-lock"></i> Authentication</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6>API Key Header</h6>
                            <pre class="bg-light p-2 rounded small">X-API-Key: gsk_live_xxxxxxxxxxxxxxxxxx</pre>
                        </div>
                        <div class="col-md-6">
                            <h6>Bearer Token</h6>
                            <pre class="bg-light p-2 rounded small">Authorization: Bearer gsk_live_xxxxxxxxxx</pre>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4"><div class="card bg-light border-0 p-3 text-center"><strong>Free</strong><br><span class="text-muted small">100 req/day</span></div></div>
                        <div class="col-md-4"><div class="card bg-primary text-white border-0 p-3 text-center"><strong>Pro</strong><br><span class="small">5,000 req/day</span></div></div>
                        <div class="col-md-4"><div class="card bg-dark text-white border-0 p-3 text-center"><strong>Enterprise</strong><br><span class="small">50,000 req/day</span></div></div>
                    </div>
                </div>
            </div>

            <!-- Endpoints -->
            <?php foreach ($endpoints as $resource => $info): ?>
            <div id="resource-<?= $resource ?>" class="mb-5">
                <h2 class="h4 fw-bold border-bottom pb-2">
                    <i class="bi <?= $info['icon'] ?> text-primary"></i> <?= $info['label'] ?>
                </h2>
                <?php foreach ($info['actions'] as $ep): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $methodColors[$ep['method']] ?? 'secondary' ?> fs-6"><?= $ep['method'] ?></span>
                            <code class="flex-grow-1">/api/v1/gateway.php?resource=<?= $resource ?>&amp;action=<?= $ep['action'] ?></code>
                            <?php if ($ep['auth']): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-lock-fill"></i> Auth required</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="bi bi-unlock-fill"></i> Public</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-1 text-muted small"><?= e($ep['summary']) ?></div>
                    </div>
                    <?php if ($ep['params']): ?>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 small">
                                <thead class="table-light">
                                    <tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ep['params'] as $p): ?>
                                    <tr>
                                        <td><code><?= e($p['name']) ?></code></td>
                                        <td><span class="text-muted"><?= e($p['type']) ?></span></td>
                                        <td><?= $p['req'] ? '<span class="text-danger">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
                                        <td><?= e($p['desc']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Try It -->
                    <div class="card-footer bg-light">
                        <details>
                            <summary class="fw-semibold small" style="cursor:pointer"><i class="bi bi-play-circle"></i> Try It</summary>
                            <div class="mt-3">
                                <div class="input-group mb-2">
                                    <span class="input-group-text small">X-API-Key</span>
                                    <input type="text" class="form-control form-control-sm font-monospace try-api-key"
                                           placeholder="gsk_live_xxxxx"
                                           value="<?= isLoggedIn() ? '' : '' ?>">
                                </div>
                                <button class="btn btn-sm btn-primary"
                                        onclick="tryApiCall('<?= $resource ?>', '<?= $ep['action'] ?>', this)">
                                    <i class="bi bi-send"></i> Send Request
                                </button>
                                <div class="try-response mt-2 d-none">
                                    <pre class="bg-dark text-light p-2 rounded small" style="max-height:200px;overflow:auto"></pre>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/api-docs.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
