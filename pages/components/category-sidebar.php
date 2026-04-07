<?php
/**
 * pages/components/category-sidebar.php — Category Filter Sidebar
 * PR #4: Reusable collapsible category tree with product counts for filtering.
 *
 * Usage:
 *   $activeCategorySlug = get('category', ''); // current filter slug
 *   include __DIR__ . '/../components/category-sidebar.php';
 *
 * Requires: includes/categories.php to be loaded and DB available.
 */

if (!function_exists('getCategoryTree')) {
    require_once __DIR__ . '/../../includes/categories.php';
}

$_sidebarTree            = getCategoryTree();
$_activeCategorySlug     = $activeCategorySlug ?? get('category', '');
$_activeCat              = $_activeCategorySlug ? getCategoryBySlug($_activeCategorySlug) : null;
$_activeCategoryId       = $_activeCat ? (int)$_activeCat['id'] : 0;
?>
<div class="card border-0 shadow-sm category-sidebar">
    <div class="card-header bg-white fw-bold border-bottom d-flex justify-content-between align-items-center">
        <span><i class="bi bi-funnel-fill text-primary me-2"></i>Categories</span>
        <?php if ($_activeCategorySlug): ?>
        <a href="?" class="btn btn-xs btn-outline-secondary" title="Clear filter">
            <i class="bi bi-x"></i>
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            <!-- All Products -->
            <li class="list-group-item border-0 py-2 <?= !$_activeCategorySlug ? 'active fw-semibold' : '' ?>">
                <a href="?" class="text-decoration-none <?= !$_activeCategorySlug ? 'text-white' : 'text-dark' ?> d-flex justify-content-between">
                    <span><i class="bi bi-grid-fill me-2"></i>All Categories</span>
                </a>
            </li>

            <?php foreach ($_sidebarTree as $l1):
                $l1Active = $_activeCategoryId === (int)$l1['id'];
                $l1HasActiveChild = false;
                foreach ($l1['children'] as $l2) {
                    if ($_activeCategoryId === (int)$l2['id']) { $l1HasActiveChild = true; break; }
                    foreach ($l2['children'] as $l3) {
                        if ($_activeCategoryId === (int)$l3['id']) { $l1HasActiveChild = true; break 2; }
                    }
                }
                $l1Open = $l1Active || $l1HasActiveChild;
            ?>
            <!-- Level 1 -->
            <li class="list-group-item border-0 p-0">
                <div class="d-flex align-items-center px-3 py-2 <?= $l1Active ? 'bg-primary bg-opacity-10' : '' ?>">
                    <?php if (!empty($l1['children'])): ?>
                    <button class="btn btn-link btn-sm p-0 me-1 text-muted cat-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#sidebar-cat-<?= $l1['id'] ?>">
                        <i class="bi bi-chevron-<?= $l1Open ? 'down' : 'right' ?> small"></i>
                    </button>
                    <?php else: ?>
                    <span class="me-3"></span>
                    <?php endif; ?>

                    <?php if ($l1['icon']): ?><i class="bi <?= e($l1['icon']) ?> text-primary me-1 small"></i><?php endif; ?>
                    <a href="?category=<?= urlencode($l1['slug']) ?>"
                       class="text-decoration-none flex-grow-1 fw-semibold <?= $l1Active ? 'text-primary' : 'text-dark' ?>">
                        <?= e($l1['name']) ?>
                    </a>
                </div>

                <?php if (!empty($l1['children'])): ?>
                <div class="collapse <?= $l1Open ? 'show' : '' ?>" id="sidebar-cat-<?= $l1['id'] ?>">
                    <ul class="list-unstyled ps-4 mb-0">
                        <?php foreach ($l1['children'] as $l2):
                            $l2Active = $_activeCategoryId === (int)$l2['id'];
                            $l2HasActiveChild = false;
                            foreach ($l2['children'] as $l3) {
                                if ($_activeCategoryId === (int)$l3['id']) { $l2HasActiveChild = true; break; }
                            }
                            $l2Open = $l2Active || $l2HasActiveChild;
                        ?>
                        <!-- Level 2 -->
                        <li class="py-1 border-bottom border-light">
                            <div class="d-flex align-items-center">
                                <?php if (!empty($l2['children'])): ?>
                                <button class="btn btn-link btn-sm p-0 me-1 text-muted cat-toggle"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#sidebar-cat-<?= $l2['id'] ?>">
                                    <i class="bi bi-chevron-<?= $l2Open ? 'down' : 'right' ?> small"></i>
                                </button>
                                <?php else: ?>
                                <span class="me-3"></span>
                                <?php endif; ?>
                                <a href="?category=<?= urlencode($l2['slug']) ?>"
                                   class="text-decoration-none flex-grow-1 small <?= $l2Active ? 'fw-semibold text-primary' : 'text-dark' ?>">
                                    <?= e($l2['name']) ?>
                                </a>
                            </div>

                            <?php if (!empty($l2['children'])): ?>
                            <div class="collapse <?= $l2Open ? 'show' : '' ?>" id="sidebar-cat-<?= $l2['id'] ?>">
                                <ul class="list-unstyled ps-4 mb-0">
                                    <?php foreach ($l2['children'] as $l3):
                                        $l3Active = $_activeCategoryId === (int)$l3['id'];
                                    ?>
                                    <li class="py-1">
                                        <a href="?category=<?= urlencode($l3['slug']) ?>"
                                           class="text-decoration-none x-small <?= $l3Active ? 'fw-semibold text-primary' : 'text-muted' ?>">
                                            <i class="bi bi-dot"></i><?= e($l3['name']) ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
.category-sidebar .cat-toggle { line-height: 1; }
.category-sidebar .x-small { font-size: .78rem; }
.category-sidebar .btn-xs { padding: .1rem .3rem; font-size: .75rem; }
</style>
