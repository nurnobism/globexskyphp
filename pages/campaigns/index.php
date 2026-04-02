<?php
require_once __DIR__ . '/../../includes/middleware.php';
$db = getDB();

$activeTab = get('tab', 'active');
$now = date('Y-m-d H:i:s');

// Fetch all campaigns, classify by status
$stmt = $db->query("SELECT * FROM campaigns ORDER BY start_date DESC");
$allCampaigns = $stmt->fetchAll();

$tabs = [
    'active'   => [],
    'upcoming' => [],
    'past'     => [],
];

foreach ($allCampaigns as $c) {
    $start = $c['start_date'] ?? null;
    $end   = $c['end_date']   ?? null;
    if ($start && $end) {
        if ($now < $start) {
            $tabs['upcoming'][] = $c;
        } elseif ($now > $end) {
            $tabs['past'][] = $c;
        } else {
            $tabs['active'][] = $c;
        }
    } else {
        $tabs['active'][] = $c;
    }
}

$pageTitle = 'Marketing Campaigns';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold mb-2"><i class="bi bi-tag-fill me-2 text-primary"></i>Marketing Campaigns</h1>
        <p class="text-muted lead mb-0">Discover exclusive deals, seasonal sales, and special promotions</p>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs nav-fill mb-4 shadow-sm rounded-3 overflow-hidden border" id="campaignTabs">
        <?php foreach (['active' => ['Active', 'success'], 'upcoming' => ['Upcoming', 'warning'], 'past' => ['Past', 'secondary']] as $tab => [$label, $color]): ?>
        <li class="nav-item">
            <a class="nav-link fw-semibold py-3 <?= $activeTab === $tab ? 'active' : '' ?>"
               href="index.php?tab=<?= $tab ?>">
                <i class="bi bi-circle-fill text-<?= $color ?> me-1" style="font-size:.55rem;vertical-align:middle;"></i>
                <?= $label ?>
                <span class="badge bg-<?= $color ?> ms-2 text-white"><?= count($tabs[$tab]) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Tab Content -->
    <?php
    $currentCampaigns = $tabs[$activeTab] ?? [];
    $tabColors = ['active' => 'success', 'upcoming' => 'warning', 'past' => 'secondary'];
    $tabColor  = $tabColors[$activeTab] ?? 'primary';
    $tabLabels = ['active' => 'Active', 'upcoming' => 'Upcoming', 'past' => 'Past'];
    ?>

    <?php if (empty($currentCampaigns)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-tag display-3 d-block mb-3"></i>
            <h5>No <?= strtolower($tabLabels[$activeTab] ?? '') ?> campaigns</h5>
            <p class="small">Check back soon for new promotions.</p>
        </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($currentCampaigns as $campaign):
            $discountPct = $campaign['discount_percent'] ?? $campaign['discount'] ?? null;
            $type        = $campaign['type'] ?? 'promotional';
            $typeIcons   = ['flash' => 'lightning-charge-fill', 'seasonal' => 'sun-fill', 'clearance' => 'trash3-fill', 'promotional' => 'gift-fill'];
            $typeIcon    = $typeIcons[$type] ?? 'tag-fill';
            $typeColors  = ['flash' => 'danger', 'seasonal' => 'warning', 'clearance' => 'secondary', 'promotional' => 'primary'];
            $typeColor   = $typeColors[$type] ?? 'primary';
        ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-0 hover-shadow">
                <!-- Card Header with type icon -->
                <div class="card-header bg-<?= $typeColor ?> bg-opacity-10 border-0 py-3 px-4 d-flex align-items-center gap-2">
                    <i class="bi bi-<?= $typeIcon ?> text-<?= $typeColor ?> fs-5"></i>
                    <span class="fw-semibold text-<?= $typeColor ?> text-capitalize small"><?= e($type) ?> Campaign</span>
                    <div class="ms-auto">
                        <span class="badge bg-<?= $tabColor ?>">
                            <?= $tabLabels[$activeTab] ?? '' ?>
                        </span>
                    </div>
                </div>

                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold mb-2"><?= e($campaign['title'] ?? $campaign['name'] ?? '') ?></h5>

                    <?php $desc = $campaign['description'] ?? ''; ?>
                    <?php if ($desc): ?>
                    <p class="text-muted small flex-grow-1 mb-3">
                        <?= e(strlen($desc) > 120 ? substr($desc, 0, 120) . '…' : $desc) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Discount Badge -->
                    <?php if ($discountPct): ?>
                    <div class="mb-3">
                        <span class="badge bg-danger fs-6 px-3 py-2">
                            <i class="bi bi-tag me-1"></i><?= (int)$discountPct ?>% OFF
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Dates -->
                    <div class="small text-muted d-flex flex-column gap-1 mb-3">
                        <?php if (!empty($campaign['start_date'])): ?>
                        <span><i class="bi bi-calendar-check me-1 text-success"></i>
                            Starts: <?= date('M j, Y', strtotime($campaign['start_date'])) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($campaign['end_date'])): ?>
                        <span><i class="bi bi-calendar-x me-1 text-danger"></i>
                            Ends: <?= date('M j, Y', strtotime($campaign['end_date'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-footer bg-white border-top-0 px-4 pb-4">
                    <a href="detail.php?id=<?= (int)$campaign['id'] ?>" class="btn btn-outline-<?= $typeColor ?> w-100">
                        View Details <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.hover-shadow { transition: box-shadow .2s; }
.hover-shadow:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12) !important; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
