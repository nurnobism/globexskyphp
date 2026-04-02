<?php
require_once __DIR__ . '/../../includes/middleware.php';

$db = getDB();
$userId = isLoggedIn() ? $_SESSION['user_id'] : null;

$myApplications = [];
if ($userId) {
    $stmt = $db->prepare("SELECT * FROM trade_finance_applications WHERE applicant_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $myApplications = $stmt->fetchAll();
}

$pageTitle = 'Trade Finance';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <!-- Hero -->
    <div class="row align-items-center mb-5">
        <div class="col-lg-7">
            <h1 class="fw-bold">Trade Finance Solutions</h1>
            <p class="lead text-muted">Access flexible financing options to grow your international trade business.
               Letters of Credit, Trade Insurance, and more.</p>
            <div class="d-flex gap-3 flex-wrap">
                <a href="/pages/trade-finance/credit-application.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-bank me-1"></i> Apply for Trade Credit
                </a>
                <a href="/pages/trade-finance/letter-of-credit.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-file-earmark-text me-1"></i> Letter of Credit
                </a>
            </div>
        </div>
        <div class="col-lg-5 text-center d-none d-lg-block">
            <i class="bi bi-bank2 text-primary" style="font-size:8rem;opacity:.15"></i>
        </div>
    </div>

    <!-- Services Grid -->
    <div class="row g-4 mb-5">
        <?php $services = [
            ['/pages/trade-finance/letter-of-credit.php', 'file-earmark-lock', 'primary', 'Letter of Credit (LC)', 'Secure payment guarantee for international transactions. Protect both buyers and sellers.'],
            ['/pages/trade-finance/payment-terms.php',    'calendar-check',    'success', 'Payment Terms',         'Flexible Net 30/60/90 and deferred payment arrangements for verified businesses.'],
            ['/pages/trade-finance/credit-application.php','bank',             'warning', 'Trade Credit',          'Apply for trade credit lines to finance your purchases and manage cash flow.'],
            ['/pages/trade-finance/insurance.php',         'shield-check',     'info',    'Trade Insurance',       'Protect your shipments and transactions against non-payment and supply risks.'],
        ]; ?>
        <?php foreach ($services as [$url, $icon, $color, $title, $desc]): ?>
        <div class="col-md-6 col-lg-3">
            <a href="<?= $url ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-3 d-inline-flex mb-3">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark"><?= $title ?></h6>
                    <p class="text-muted small mb-0"><?= $desc ?></p>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- My Applications -->
    <?php if ($userId && !empty($myApplications)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <h6 class="mb-0 fw-bold">My Recent Applications</h6>
            <a href="/pages/trade-finance/credit-application.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($myApplications as $a): ?>
                <?php $colors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','under_review'=>'info']; ?>
                <tr>
                    <td><?= e(ucwords(str_replace('_',' ',$a['finance_type']))) ?></td>
                    <td><?= formatMoney($a['requested_amount']) ?></td>
                    <td><span class="badge bg-<?= $colors[$a['status']]??'secondary' ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                    <td><?= formatDate($a['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
