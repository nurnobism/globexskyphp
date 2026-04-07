<?php
/**
 * pages/components/category-nav.php — Category Mega-Menu Navigation
 * PR #4: Reusable horizontal mega-menu showing 3-level category hierarchy.
 *
 * Usage: include this file in includes/header.php or any page that needs
 * the category nav bar.
 *
 * Requires: includes/categories.php to be loaded and DB available.
 */

if (!function_exists('getCategoryTree')) {
    require_once __DIR__ . '/../../includes/categories.php';
}

$_navTree = getCategoryTree();
?>
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm category-nav py-0" aria-label="Category navigation">
    <div class="container-fluid">
        <button class="navbar-toggler border-0 py-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#categoryOffcanvas"
                aria-controls="categoryOffcanvas">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i> Categories
        </button>

        <!-- Desktop mega-menu (lg+) -->
        <div class="d-none d-lg-flex align-items-stretch w-100">
            <ul class="navbar-nav gap-0">
                <?php foreach ($_navTree as $l1): ?>
                <li class="nav-item dropdown mega-item position-static">
                    <a class="nav-link px-3 py-3 d-flex align-items-center gap-1 fw-medium cat-l1-link"
                       href="/pages/product/index.php?category=<?= urlencode($l1['slug']) ?>"
                       data-bs-toggle="dropdown" data-bs-auto-close="outside"
                       aria-expanded="false">
                        <?php if ($l1['icon']): ?>
                        <i class="bi <?= e($l1['icon']) ?> text-primary"></i>
                        <?php endif; ?>
                        <span class="text-truncate" style="max-width:120px"><?= e($l1['name']) ?></span>
                        <?php if (!empty($l1['children'])): ?>
                        <i class="bi bi-chevron-down small ms-auto"></i>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($l1['children'])): ?>
                    <div class="dropdown-menu mega-menu p-3 border-0 shadow rounded-3">
                        <div class="row g-3">
                            <?php foreach ($l1['children'] as $l2): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="fw-semibold mb-1">
                                    <?php if ($l2['icon']): ?>
                                    <i class="bi <?= e($l2['icon']) ?> text-info me-1"></i>
                                    <?php endif; ?>
                                    <a href="/pages/product/index.php?category=<?= urlencode($l2['slug']) ?>"
                                       class="text-dark text-decoration-none">
                                        <?= e($l2['name']) ?>
                                    </a>
                                </div>
                                <?php if (!empty($l2['children'])): ?>
                                <ul class="list-unstyled ps-2 mb-0">
                                    <?php foreach ($l2['children'] as $l3): ?>
                                    <li>
                                        <a href="/pages/product/index.php?category=<?= urlencode($l3['slug']) ?>"
                                           class="text-muted small text-decoration-none cat-l3-link">
                                            <i class="bi bi-chevron-right small me-1"></i><?= e($l3['name']) ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2 pt-2 border-top">
                            <a href="/pages/product/index.php?category=<?= urlencode($l1['slug']) ?>"
                               class="text-decoration-none text-primary small">
                                See all in <?= e($l1['name']) ?> <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Mobile: Offcanvas category tree -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="categoryOffcanvas" aria-labelledby="categoryOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="categoryOffcanvasLabel">
            <i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Categories
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="accordion accordion-flush" id="mobileCatAccordion">
            <?php foreach ($_navTree as $l1): ?>
            <div class="accordion-item">
                <?php if (!empty($l1['children'])): ?>
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#mob-cat-<?= $l1['id'] ?>">
                        <?php if ($l1['icon']): ?><i class="bi <?= e($l1['icon']) ?> me-2 text-primary"></i><?php endif; ?>
                        <?= e($l1['name']) ?>
                    </button>
                </h2>
                <div id="mob-cat-<?= $l1['id'] ?>" class="accordion-collapse collapse">
                    <div class="accordion-body ps-4 py-1">
                        <?php foreach ($l1['children'] as $l2): ?>
                        <div class="mb-2">
                            <a href="/pages/product/index.php?category=<?= urlencode($l2['slug']) ?>"
                               class="fw-medium text-dark text-decoration-none">
                                <?php if ($l2['icon']): ?><i class="bi <?= e($l2['icon']) ?> me-1 text-info"></i><?php endif; ?>
                                <?= e($l2['name']) ?>
                            </a>
                            <?php if (!empty($l2['children'])): ?>
                            <ul class="list-unstyled ps-3 mt-1 mb-0">
                                <?php foreach ($l2['children'] as $l3): ?>
                                <li>
                                    <a href="/pages/product/index.php?category=<?= urlencode($l3['slug']) ?>"
                                       class="text-muted small text-decoration-none">
                                        <i class="bi bi-dot"></i><?= e($l3['name']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <a href="/pages/product/index.php?category=<?= urlencode($l1['slug']) ?>"
                           class="text-primary small">See all <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                <?php else: ?>
                <div class="accordion-item border-0 py-2 px-3">
                    <a href="/pages/product/index.php?category=<?= urlencode($l1['slug']) ?>"
                       class="text-dark text-decoration-none fw-semibold">
                        <?php if ($l1['icon']): ?><i class="bi <?= e($l1['icon']) ?> me-2 text-primary"></i><?php endif; ?>
                        <?= e($l1['name']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.category-nav { min-height: 46px; }
.mega-menu {
    min-width: 640px;
    max-width: 900px;
    left: 0 !important;
    right: auto !important;
}
.cat-l1-link { font-size: .9rem; white-space: nowrap; }
.cat-l3-link:hover { color: var(--bs-primary) !important; }
</style>
