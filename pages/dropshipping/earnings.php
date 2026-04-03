<?php
/**
 * pages/dropshipping/earnings.php — Earnings Dashboard
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
require_once __DIR__ . '/../../includes/dropshipping.php';
require_once __DIR__ . '/../../includes/dropship-payment.php';

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$period = in_array(get('period', '30days'), ['7days','30days','90days','all']) ? get('period', '30days') : '30days';

// Earnings summary
$earnings = getDropshipperEarnings($userId, $period);
$balance  = getDropshipperBalance($userId);

// Earnings table with filters
$dateFrom = get('date_from', '');
$dateTo   = get('date_to', '');
$statusFilter = get('status', 'all');

$where  = ['de.dropshipper_id = ?'];
$params = [$userId];

if ($statusFilter !== 'all' && in_array($statusFilter, ['pending','available','requested','paid','cancelled'])) {
    $where[]  = 'de.status = ?';
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where[]  = 'de.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[]  = 'de.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(' AND ', $where);
$page    = max(1, (int)get('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$earningRows = [];
$totalRows   = 0;
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM dropship_earnings de WHERE $whereClause");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT de.*, o.order_number
        FROM dropship_earnings de
        LEFT JOIN orders o ON o.id = de.order_id
        WHERE $whereClause
        ORDER BY de.created_at DESC
        LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $earningRows = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$totalPages = (int)ceil($totalRows / $perPage);

// Payout history
$payoutHistory = [];
try {
    $pStmt = $db->prepare("SELECT * FROM payouts WHERE user_id = ? AND type = 'dropship' ORDER BY created_at DESC LIMIT 20");
    $pStmt->execute([$userId]);
    $payoutHistory = $pStmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Chart data
$chartLabels = [];
$chartData   = [];
foreach ($earnings['by_day'] as $d) {
    $chartLabels[] = date('d M', strtotime($d['day']));
    $chartData[]   = (float)$d['earnings'];
}

$pageTitle = 'Dropship Earnings';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4 px-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0 text-primary"><i class="bi bi-cash-coin me-2"></i>Earnings Dashboard</h4>
      <small class="text-muted">Track your dropshipping earnings</small>
    </div>
    <a href="<?= APP_URL ?>/pages/dropshipping/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-primary"><?= formatMoney($earnings['total']) ?></div>
          <div class="small text-muted">Total Earnings</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-warning"><?= formatMoney($earnings['pending']) ?></div>
          <div class="small text-muted">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-success"><?= formatMoney($balance) ?></div>
          <div class="small text-muted">Available</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="fs-3 fw-bold text-info"><?= formatMoney($earnings['paid']) ?></div>
          <div class="small text-muted">Paid Out</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Earnings Chart -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Earnings (Last 30 Days)</h6>
          <div class="btn-group btn-group-sm">
            <?php foreach (['7days'=>'7D','30days'=>'30D','90days'=>'90D','all'=>'All'] as $k => $v): ?>
            <a href="?period=<?= $k ?>" class="btn btn-<?= $period===$k?'primary':'outline-secondary' ?>"><?= $v ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-body">
          <canvas id="earningsChart" height="100"></canvas>
        </div>
      </div>
    </div>

    <!-- Request Payout -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-wallet2 me-2 text-success"></i>Request Payout</h6>
        </div>
        <div class="card-body">
          <div class="text-center mb-3">
            <div class="small text-muted">Available Balance</div>
            <div class="fs-2 fw-bold text-success"><?= formatMoney($balance) ?></div>
          </div>
          <?php if ($balance >= 50): ?>
          <button type="button" class="btn btn-success w-100" id="requestPayoutBtn">
            <i class="bi bi-send me-2"></i>Request Payout
          </button>
          <div class="form-text text-center mt-2">Minimum payout: $50.00</div>
          <?php else: ?>
          <button class="btn btn-secondary w-100" disabled>
            <i class="bi bi-lock me-2"></i>Minimum $50 Required
          </button>
          <div class="form-text text-center mt-2">You need <?= formatMoney(50 - $balance) ?> more to request a payout.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payout History -->
      <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Payout History</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($payoutHistory)): ?>
            <div class="text-center py-3 text-muted small">No payouts yet.</div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($payoutHistory as $po):
                $poStatus = match($po['status'] ?? '') {
                  'paid' => ['success', 'check-circle'], 'pending' => ['warning', 'clock'],
                  default => ['secondary', 'dash-circle']
                };
              ?>
              <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                <div>
                  <div class="fw-semibold small"><?= formatMoney($po['amount']) ?></div>
                  <div class="text-muted" style="font-size:.75rem"><?= formatDate($po['created_at'] ?? '') ?></div>
                </div>
                <span class="badge bg-<?= $poStatus[0] ?>"><i class="bi bi-<?= $poStatus[1] ?> me-1"></i><?= e(ucfirst($po['status'] ?? 'unknown')) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Earnings Table -->
  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h6 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Earnings History</h6>
      <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
        <input type="hidden" name="period" value="<?= e($period) ?>">
        <div>
          <label class="form-label small mb-0">From</label>
          <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm">
        </div>
        <div>
          <label class="form-label small mb-0">To</label>
          <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control form-control-sm">
        </div>
        <div>
          <label class="form-label small mb-0">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="all">All</option>
            <?php foreach (['pending','available','requested','paid','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      </form>
    </div>
    <div class="card-body p-0">
      <?php if (empty($earningRows)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2"></i><p class="mt-2">No earnings yet.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Order #</th>
              <th>Gross</th>
              <th>Platform Fee</th>
              <th>Net</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($earningRows as $er):
            $sc = match($er['status']) {
              'available'=>'success','paid'=>'info','pending'=>'warning','cancelled'=>'danger',default=>'secondary'
            };
          ?>
            <tr>
              <td class="ps-3 fw-semibold small"><?= e($er['order_number'] ?? '#' . $er['order_id']) ?></td>
              <td><?= formatMoney($er['gross_amount']) ?></td>
              <td class="text-danger">-<?= formatMoney($er['platform_fee']) ?></td>
              <td class="fw-bold text-success"><?= formatMoney($er['net_amount']) ?></td>
              <td><span class="badge bg-<?= $sc ?>"><?= e(ucfirst($er['status'])) ?></span></td>
              <td class="text-muted small"><?= formatDate($er['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= min($totalPages, 10); $i++):
        $qs = http_build_query(array_merge(['period'=>$period,'status'=>$statusFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo], ['page' => $i]));
      ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= $qs ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Earnings chart
new Chart(document.getElementById('earningsChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>.length ? <?= json_encode($chartLabels) ?> : ['No data'],
    datasets: [{ label: 'Earnings ($)',
      data: <?= json_encode($chartData) ?>.length ? <?= json_encode($chartData) ?> : [0],
      borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.08)', fill: true, tension: 0.3 }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{callback:v=>'$'+v}}} }
});

// Request payout
const payoutBtn = document.getElementById('requestPayoutBtn');
if (payoutBtn) {
  payoutBtn.addEventListener('click', async () => {
    if (!confirm('Request a payout of your available balance?')) return;
    payoutBtn.disabled = true;
    payoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    try {
      const body = new URLSearchParams({_csrf_token:'<?= e(csrfToken()) ?>', action:'request_payout'});
      const res = await fetch('<?= APP_URL ?>/api/dropshipping.php?action=request_payout', {method:'POST', body});
      const data = await res.json();
      if (data.success) {
        alert('Payout requested successfully!');
        location.reload();
      } else {
        alert(data.error || 'Payout request failed');
      }
    } catch(e) { alert('Error requesting payout'); }
    payoutBtn.disabled = false;
    payoutBtn.innerHTML = '<i class="bi bi-send me-2"></i>Request Payout';
  });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
