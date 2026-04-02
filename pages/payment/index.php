<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->execute([$userId]);
$methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = 'Payment Methods';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-credit-card me-2"></i>Payment Methods</h1>
        <a href="add-method.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Method
        </a>
    </div>

    <?php if (empty($methods)): ?>
        <div class="text-center py-5">
            <i class="bi bi-wallet2 display-1 text-muted"></i>
            <h5 class="mt-3">No Payment Methods</h5>
            <p class="text-muted">Add a payment method to get started.</p>
            <a href="add-method.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Payment Method
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($methods as $method): ?>
                <?php
                $type = $method['type'] ?? 'card';
                $typeIcons = [
                    'credit_card' => 'bi-credit-card',
                    'card'        => 'bi-credit-card',
                    'bank'        => 'bi-bank',
                    'wallet'      => 'bi-wallet2',
                ];
                $typeIcon = $typeIcons[$type] ?? 'bi-credit-card';
                $isDefault = !empty($method['is_default']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100 <?= $isDefault ? 'border-primary' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?= $typeIcon ?> fs-3 text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0"><?= e($method['provider'] ?? 'Unknown') ?></h6>
                                        <span class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $type))) ?></span>
                                    </div>
                                </div>
                                <?php if ($isDefault): ?>
                                    <span class="badge bg-primary">Default</span>
                                <?php endif; ?>
                            </div>
                            <p class="fs-5 mb-3 font-monospace text-muted">
                                &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;
                                <?= e($method['last_four'] ?? '****') ?>
                            </p>
                            <div class="d-flex gap-2">
                                <?php if (!$isDefault): ?>
                                    <form method="post" action="../../api/payments.php?action=set_default">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="method_id" value="<?= (int) $method['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Set Default</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="../../api/payments.php?action=remove_method"
                                      onsubmit="return confirm('Remove this payment method?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="method_id" value="<?= (int) $method['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
