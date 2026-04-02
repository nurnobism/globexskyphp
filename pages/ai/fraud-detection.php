<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$db = getDB();

// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS fraud_flags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    risk_score      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    risk_level      ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    reasons         JSON         NULL,
    recommended_action VARCHAR(255) NULL,
    ai_raw_response TEXT         NULL,
    status          ENUM('open','reviewed','dismissed','blocked') NOT NULL DEFAULT 'open',
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order  (order_id),
    INDEX idx_level  (risk_level),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Stats
$stats = [
    'total'    => 0, 'high' => 0, 'medium' => 0, 'low' => 0,
    'open'     => 0, 'blocked' => 0,
];
try {
    $statsRow = $db->query(
        "SELECT
            COUNT(*) AS total,
            SUM(risk_level IN ('high','critical')) AS high,
            SUM(risk_level = 'medium') AS medium,
            SUM(risk_level = 'low') AS low,
            SUM(status = 'open') AS open,
            SUM(status = 'blocked') AS blocked
         FROM fraud_flags"
    )->fetch(PDO::FETCH_ASSOC);
    if ($statsRow) $stats = array_map('intval', $statsRow);
} catch (PDOException $e) {}

// Recent flagged orders
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page - 1) * $limit;
$filter  = $_GET['level'] ?? '';
$status  = $_GET['status'] ?? '';

$where  = [];
$params = [];
if (in_array($filter, ['low','medium','high','critical'])) {
    $where[]  = 'ff.risk_level = ?';
    $params[] = $filter;
}
if (in_array($status, ['open','reviewed','dismissed','blocked'])) {
    $where[]  = 'ff.status = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM fraud_flags ff $whereSql");
    $totalStmt->execute($params);
    $totalFlags = (int)$totalStmt->fetchColumn();

    $flagsStmt = $db->prepare(
        "SELECT ff.*, o.total AS order_total, o.placed_at, o.payment_method,
                u.email, u.first_name, u.last_name
         FROM fraud_flags ff
         JOIN orders o ON o.id = ff.order_id
         JOIN users  u ON u.id = o.buyer_id
         $whereSql
         ORDER BY ff.risk_score DESC, ff.created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $flagsStmt->execute($params);
    $flags = $flagsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $flags = [];
    $totalFlags = 0;
}

// Recent orders without analysis (for quick analysis)
try {
    $pendingOrders = $db->query(
        "SELECT o.id, o.total, o.placed_at, u.email, u.first_name, u.last_name
         FROM orders o
         JOIN users u ON u.id = o.buyer_id
         WHERE o.id NOT IN (SELECT order_id FROM fraud_flags)
         ORDER BY o.placed_at DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $pendingOrders = []; }

$totalPages = max(1, (int)ceil($totalFlags / $limit));
$pageTitle  = 'AI Fraud Detection';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --brand-orange: #FF6B35; }
    .page-header { background: linear-gradient(135deg, #1B2A4A 0%, #2d4070 100%); }
    .stat-card   { border-radius: 14px; border: none; }
    .stat-icon   { width: 50px; height: 50px; border-radius: 12px;
                   display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
    .risk-badge  { font-size: .72rem; padding: .3em .7em; border-radius: 20px; font-weight: 600; }
    .risk-low      { background: #d1fae5; color: #065f46; }
    .risk-medium   { background: #fef3c7; color: #92400e; }
    .risk-high     { background: #fee2e2; color: #991b1b; }
    .risk-critical { background: #7f1d1d; color: #fff; }
    .score-bar   { height: 6px; border-radius: 3px; background: #f0f0f0; }
    .score-fill  { height: 100%; border-radius: 3px; }
    .table th    { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
    .table td    { font-size: .88rem; vertical-align: middle; }
</style>

<!-- Page Header -->
<div class="page-header text-white py-4">
    <div class="container">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-shield-shaded me-2 text-warning"></i>AI Fraud Detection</h3>
                <p class="mb-0 text-white-75 small">Real-time transaction risk scoring powered by DeepSeek AI</p>
            </div>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <a href="<?= APP_URL ?>/pages/ai/index.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> AI Hub
                </a>
                <button class="btn btn-warning btn-sm fw-bold rounded-pill" id="analyzeAllBtn">
                    <i class="bi bi-play-fill me-1"></i> Analyze Unreviewed
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid py-4" style="max-width:1400px;">

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['Total Flagged',   $stats['total'],   'flag-fill',         'rgba(27,42,74,.1)',    '#1B2A4A'],
            ['High / Critical', $stats['high'],    'exclamation-triangle-fill','rgba(220,53,69,.1)','#dc3545'],
            ['Medium Risk',     $stats['medium'],  'dash-circle-fill',  'rgba(255,193,7,.15)',   '#d49e00'],
            ['Open Alerts',     $stats['open'],    'bell-fill',         'rgba(255,107,53,.12)',  '#FF6B35'],
            ['Blocked Orders',  $stats['blocked'], 'slash-circle-fill', 'rgba(220,53,69,.15)',   '#dc3545'],
        ];
        foreach ($statCards as [$label, $value, $icon, $bg, $color]):
        ?>
        <div class="col-6 col-lg">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon" style="background:<?= $bg ?>; color:<?= $color ?>;">
                        <i class="bi bi-<?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1"><?= number_format($value) ?></div>
                        <div class="text-muted" style="font-size:.78rem;"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">

        <!-- Main Table -->
        <div class="col-xl-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3 pb-0 px-4 rounded-top-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0">Flagged Transactions</h6>
                        <!-- Filters -->
                        <form class="d-flex gap-2 align-items-center" method="GET">
                            <select name="level" class="form-select form-select-sm rounded-pill" style="width:auto;">
                                <option value="">All Levels</option>
                                <?php foreach (['low','medium','high','critical'] as $l): ?>
                                <option value="<?= $l ?>" <?= $filter === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="form-select form-select-sm rounded-pill" style="width:auto;">
                                <option value="">All Status</option>
                                <?php foreach (['open','reviewed','dismissed','blocked'] as $s): ?>
                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary rounded-pill">Filter</button>
                            <?php if ($filter || $status): ?>
                            <a href="?" class="btn btn-sm btn-outline-secondary rounded-pill">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($flags)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-check text-success" style="font-size:3rem;"></i>
                        <h6 class="mt-3 text-muted">No flagged transactions found</h6>
                        <?php if ($filter || $status): ?>
                        <a href="?" class="btn btn-outline-secondary btn-sm rounded-pill mt-2">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Order</th>
                                    <th>Buyer</th>
                                    <th>Amount</th>
                                    <th>Risk Score</th>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($flags as $flag):
                                $reasons = json_decode($flag['reasons'] ?? '[]', true);
                                $scoreColor = match($flag['risk_level']) {
                                    'critical' => '#7f1d1d',
                                    'high'     => '#dc3545',
                                    'medium'   => '#d49e00',
                                    default    => '#198754',
                                };
                                $statusBadge = match($flag['status']) {
                                    'open'      => 'bg-warning text-dark',
                                    'reviewed'  => 'bg-info text-dark',
                                    'dismissed' => 'bg-secondary',
                                    'blocked'   => 'bg-danger',
                                    default     => 'bg-secondary',
                                };
                            ?>
                            <tr data-flag-id="<?= $flag['id'] ?>">
                                <td class="ps-4">
                                    <a href="<?= APP_URL ?>/pages/order/detail.php?id=<?= $flag['order_id'] ?>"
                                       class="fw-bold text-decoration-none">#<?= $flag['order_id'] ?></a>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($flag['first_name'] . ' ' . $flag['last_name']) ?></div>
                                    <small class="text-muted"><?= e($flag['email']) ?></small>
                                </td>
                                <td class="fw-bold"><?= formatMoney((float)$flag['order_total']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold" style="color:<?= $scoreColor ?>; min-width:32px;">
                                            <?= $flag['risk_score'] ?>
                                        </span>
                                        <div class="score-bar flex-grow-1" style="min-width:60px;">
                                            <div class="score-fill" style="width:<?= $flag['risk_score'] ?>%;background:<?= $scoreColor ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="risk-badge risk-<?= $flag['risk_level'] ?>">
                                        <?= strtoupper($flag['risk_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBadge ?> rounded-pill">
                                        <?= ucfirst($flag['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= formatDate($flag['created_at']) ?></small>
                                </td>
                                <td class="pe-4">
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary rounded-pill"
                                                data-bs-toggle="modal" data-bs-target="#detailModal"
                                                onclick="showDetail(<?= htmlspecialchars(json_encode($flag), ENT_QUOTES) ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($flag['status'] === 'open'): ?>
                                        <button class="btn btn-sm btn-outline-success rounded-pill update-status"
                                                data-flag="<?= $flag['id'] ?>" data-status="reviewed"
                                                title="Mark Reviewed">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill update-status"
                                                data-flag="<?= $flag['id'] ?>" data-status="blocked"
                                                title="Block Order">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center gap-2 p-3">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&level=<?= e($filter) ?>&status=<?= e($status) ?>"
                           class="btn btn-sm <?= $i === $page ? 'btn-warning' : 'btn-outline-secondary' ?> rounded-pill">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar: Quick Analyze -->
        <div class="col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-white border-0 pt-3 pb-0 px-3 rounded-top-4">
                    <h6 class="fw-bold mb-0"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Analyze</h6>
                    <small class="text-muted">Orders not yet scanned</small>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($pendingOrders)): ?>
                    <p class="text-muted small text-center py-2">All recent orders have been analyzed.</p>
                    <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($pendingOrders as $o): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded-3">
                            <div>
                                <div class="fw-semibold small">#<?= $o['id'] ?> — <?= formatMoney((float)$o['total']) ?></div>
                                <small class="text-muted"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary rounded-pill analyze-btn"
                                    data-order="<?= $o['id'] ?>">
                                <i class="bi bi-cpu-fill"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Risk Distribution -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3 pb-0 px-3 rounded-top-4">
                    <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-warning me-2"></i>Risk Distribution</h6>
                </div>
                <div class="card-body p-3">
                    <?php
                    $total = max(1, $stats['total']);
                    $dist  = [
                        ['Low',      $stats['low'],    '#198754'],
                        ['Medium',   $stats['medium'], '#ffc107'],
                        ['High',     $stats['high'],   '#dc3545'],
                    ];
                    foreach ($dist as [$label, $count, $color]):
                        $pct = round($count / $total * 100);
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold"><?= $label ?></span>
                            <span class="text-muted"><?= $count ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress" style="height:8px;border-radius:4px;">
                            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>;" role="progressbar"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#1B2A4A,#2d4070);">
                <h6 class="modal-title text-white fw-bold">
                    <i class="bi bi-shield-shaded me-2 text-warning"></i>Fraud Analysis Detail
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailContent"></div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-danger btn-sm rounded-pill d-none" id="modalBlockBtn">
                    <i class="bi bi-slash-circle me-1"></i> Block Order
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const FRAUD_URL  = '<?= APP_URL ?>/api/ai/fraud-detection.php';
const CSRF_TOKEN = '<?= csrfToken() ?>';

function showDetail(flag) {
    const reasons  = flag.reasons ? JSON.parse(flag.reasons) : [];
    const scoreColor = {'low':'#198754','medium':'#d49e00','high':'#dc3545','critical':'#7f1d1d'}[flag.risk_level] || '#6c757d';

    document.getElementById('detailContent').innerHTML = `
        <div class="text-center mb-3">
            <div class="fw-bold fs-2" style="color:${scoreColor};">${flag.risk_score}<small class="fs-6 text-muted">/100</small></div>
            <span class="badge" style="background:${scoreColor};font-size:.85rem;">${flag.risk_level.toUpperCase()} RISK</span>
        </div>
        <dl class="row small mb-3">
            <dt class="col-5 text-muted">Order ID</dt><dd class="col-7">#${flag.order_id}</dd>
            <dt class="col-5 text-muted">Amount</dt><dd class="col-7">$${parseFloat(flag.order_total||0).toFixed(2)}</dd>
            <dt class="col-5 text-muted">Status</dt><dd class="col-7">${flag.status}</dd>
            <dt class="col-5 text-muted">Analyzed</dt><dd class="col-7">${flag.created_at}</dd>
        </dl>
        ${reasons.length > 0 ? `
        <div class="mb-3">
            <div class="fw-semibold small mb-2">Risk Factors:</div>
            <ul class="small mb-0">${reasons.map(r => `<li>${r}</li>`).join('')}</ul>
        </div>` : ''}
        ${flag.recommended_action ? `
        <div class="p-3 rounded-3" style="background:rgba(255,107,53,.08);border:1px solid rgba(255,107,53,.2);">
            <div class="fw-semibold small mb-1"><i class="bi bi-lightbulb text-warning me-1"></i>Recommended Action</div>
            <div class="small">${flag.recommended_action}</div>
        </div>` : ''}`;

    const blockBtn = document.getElementById('modalBlockBtn');
    if (flag.status === 'open') {
        blockBtn.classList.remove('d-none');
        blockBtn.onclick = () => updateFlagStatus(flag.id, 'blocked');
    } else {
        blockBtn.classList.add('d-none');
    }
}

async function updateFlagStatus(flagId, status) {
    if (!confirm(`Mark this flag as "${status}"?`)) return;
    try {
        const body = new URLSearchParams({flag_id: flagId, status, _csrf_token: CSRF_TOKEN});
        const res  = await fetch(FRAUD_URL + '?action=update', {method:'POST', body});
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Update failed');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function analyzeOrder(orderId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const body = new URLSearchParams({order_id: orderId, _csrf_token: CSRF_TOKEN});
        const res  = await fetch(FRAUD_URL + '?action=analyze', {method:'POST', body});
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Analysis failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cpu-fill"></i>';
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cpu-fill"></i>';
    }
}

// Update status buttons
document.querySelectorAll('.update-status').forEach(btn => {
    btn.addEventListener('click', () => updateFlagStatus(btn.dataset.flag, btn.dataset.status));
});

// Quick analyze buttons
document.querySelectorAll('.analyze-btn').forEach(btn => {
    btn.addEventListener('click', () => analyzeOrder(btn.dataset.order, btn));
});

// Analyze all unreviewed
document.getElementById('analyzeAllBtn').addEventListener('click', async function() {
    const btns = document.querySelectorAll('.analyze-btn');
    if (btns.length === 0) { alert('No new orders to analyze.'); return; }
    if (!confirm(`Analyze ${btns.length} order(s)?`)) return;
    for (const btn of btns) { await analyzeOrder(btn.dataset.order, btn); }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
