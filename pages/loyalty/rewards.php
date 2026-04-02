<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

$pointsRow = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='earned' THEN points ELSE -points END), 0) AS balance FROM loyalty_points WHERE user_id = ?");
$pointsRow->execute([$userId]);
$balance = (int)$pointsRow->fetch()['balance'];

$filterCategory = get('category', '');

$sql = "SELECT * FROM loyalty_rewards WHERE is_active = 1";
$params = [];
if ($filterCategory !== '') {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}
$sql .= " ORDER BY points_required ASC";

$rewards = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rewards = $stmt->fetchAll();
} catch (\Exception $e) {
    $rewards = [];
}

$categories = [];
try {
    $categories = $db->query("SELECT DISTINCT category FROM loyalty_rewards WHERE is_active = 1 AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (\Exception $e) {
    $categories = [];
}

$pageTitle = 'Rewards Catalog';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-gift me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Redeem your points for exclusive rewards</p>
        </div>
        <div>
            <span class="badge bg-primary fs-6 px-3 py-2"><i class="bi bi-gem me-1"></i><?= number_format($balance) ?> Points</span>
        </div>
    </div>

    <!-- Filter by Category -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="d-flex align-items-center gap-3">
                <label class="form-label mb-0 fw-semibold">Filter by Category:</label>
                <select class="form-select" name="category" style="max-width: 250px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filterCategory !== ''): ?>
                    <a href="/pages/loyalty/rewards.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Rewards Grid -->
    <div class="row g-4">
        <?php if (empty($rewards)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-gift display-1 text-muted"></i>
                        <p class="text-muted mt-3 mb-0">No rewards available at the moment. Check back soon!</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rewards as $reward):
                $canRedeem = $balance >= $reward['points_required'];
            ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <!-- Image Placeholder -->
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                            <?php if (!empty($reward['image'])): ?>
                                <img src="<?= e($reward['image']) ?>" alt="<?= e($reward['name']) ?>" class="img-fluid" style="max-height: 160px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-gift display-4 text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <?php if (!empty($reward['category'])): ?>
                                <span class="badge bg-light text-dark mb-2 align-self-start"><?= e($reward['category']) ?></span>
                            <?php endif; ?>
                            <h6 class="card-title"><?= e($reward['name']) ?></h6>
                            <p class="card-text text-muted small flex-grow-1"><?= e($reward['description'] ?? '') ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                                    <i class="bi bi-gem me-1"></i><?= number_format($reward['points_required']) ?> pts
                                </span>
                                <?php if ($canRedeem): ?>
                                    <form method="post" action="/api/loyalty.php?action=redeem" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Redeem this reward for <?= number_format($reward['points_required']) ?> points?')">
                                            <i class="bi bi-check-circle me-1"></i>Redeem
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                        <i class="bi bi-lock me-1"></i>Need <?= number_format($reward['points_required'] - $balance) ?> more
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="text-center mt-4">
        <a href="/pages/loyalty/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Loyalty Dashboard</a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
