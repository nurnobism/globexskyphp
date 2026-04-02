<?php
/**
 * pages/trade-shows/booth.php — Virtual/Physical Trade Show Booth
 */
require_once __DIR__ . '/../../includes/middleware.php';

$showId  = (int)($_GET['show_id']  ?? 0);
$boothId = (int)($_GET['booth_id'] ?? 0);

$db = getDB();

// Load trade show
$showStmt = $db->prepare('SELECT * FROM trade_shows WHERE id = ? LIMIT 1');
$showStmt->execute([$showId]);
$show = $showStmt->fetch();
if (!$show) {
    header('Location: /pages/trade-shows/index.php');
    exit;
}

// Load booth (specific or first for this show)
if ($boothId) {
    $bStmt = $db->prepare(
        "SELECT b.*, s.company_name, s.company_desc, s.logo, s.website, s.country,
                u.name contact_name, u.email contact_email, u.phone contact_phone
         FROM trade_show_booths b
         LEFT JOIN suppliers s ON s.id = b.supplier_id
         LEFT JOIN users u ON u.id = s.user_id
         WHERE b.id = ? AND b.show_id = ? LIMIT 1"
    );
    $bStmt->execute([$boothId, $showId]);
} else {
    $bStmt = $db->prepare(
        "SELECT b.*, s.company_name, s.company_desc, s.logo, s.website, s.country,
                u.name contact_name, u.email contact_email, u.phone contact_phone
         FROM trade_show_booths b
         LEFT JOIN suppliers s ON s.id = b.supplier_id
         LEFT JOIN users u ON u.id = s.user_id
         WHERE b.show_id = ? ORDER BY b.is_featured DESC, b.id ASC LIMIT 1"
    );
    $bStmt->execute([$showId]);
}
$booth = $bStmt->fetch();

// Load all booths for this show (sidebar list)
$allBoothsStmt = $db->prepare(
    "SELECT b.id, s.company_name, s.logo, b.is_featured
     FROM trade_show_booths b
     LEFT JOIN suppliers s ON s.id = b.supplier_id
     WHERE b.show_id = ? ORDER BY b.is_featured DESC, s.company_name ASC LIMIT 30"
);
$allBoothsStmt->execute([$showId]);
$allBooths = $allBoothsStmt->fetchAll();

// Featured products for this booth
$products = [];
if ($booth) {
    $prodStmt = $db->prepare(
        "SELECT p.id, p.name, p.slug, p.price, p.thumbnail, p.short_desc, p.currency
         FROM trade_show_booth_products bp
         JOIN products p ON p.id = bp.product_id
         WHERE bp.booth_id = ? ORDER BY bp.sort_order ASC LIMIT 12"
    );
    $prodStmt->execute([$booth['id']]);
    $products = $prodStmt->fetchAll();
}

// Handle contact form submission
$contactSuccess = false;
$contactError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    if (!verifyCsrf()) {
        $contactError = 'Invalid security token.';
    } else {
        $senderName  = trim($_POST['sender_name'] ?? '');
        $senderEmail = trim($_POST['sender_email'] ?? '');
        $msgBody     = trim($_POST['message'] ?? '');
        if (!$senderName || !$senderEmail || !$msgBody) {
            $contactError = 'All fields are required.';
        } elseif (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $contactError = 'Please enter a valid email address.';
        } else {
            // Store inquiry
            $db->prepare(
                "INSERT INTO booth_inquiries (booth_id, show_id, sender_name, sender_email, message, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            )->execute([$booth['id'] ?? 0, $showId, $senderName, $senderEmail, $msgBody]);
            $contactSuccess = true;
        }
    }
}

$pageTitle = ($booth['company_name'] ?? $show['name']) . ' — Booth';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .booth-hero { background:linear-gradient(135deg,#1B2A4A 0%,#2d4a7a 100%); min-height:220px; }
    .product-card:hover { transform:translateY(-3px); box-shadow:0 6px 18px rgba(255,107,53,.2)!important; }
    .sidebar-booth:hover { background:#fff5f0!important; }
</style>

<div class="container-fluid px-0">

    <!-- Booth Hero Banner -->
    <div class="booth-hero d-flex align-items-center text-white px-4 px-md-5 py-5">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <?php if (!empty($booth['logo'])): ?>
                <img src="<?= e($booth['logo']) ?>" alt="Logo" class="rounded-circle bg-white p-1"
                     style="width:90px;height:90px;object-fit:contain">
            <?php else: ?>
                <div class="rounded-circle bg-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:90px;height:90px">
                    <i class="bi bi-building" style="font-size:2.5rem;color:#1B2A4A"></i>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="fw-bold mb-1"><?= e($booth['company_name'] ?? 'Exhibitor') ?></h2>
                <p class="mb-1 opacity-75">
                    <i class="bi bi-geo-alt me-1"></i><?= e($booth['country'] ?? 'Global') ?>
                    <?php if (!empty($booth['website'])): ?>
                        &nbsp;·&nbsp;<a href="<?= e($booth['website']) ?>" class="text-white" target="_blank" rel="noopener">
                            <i class="bi bi-globe2 me-1"></i>Website
                        </a>
                    <?php endif; ?>
                </p>
                <p class="mb-0 opacity-75 small">
                    <i class="bi bi-calendar3 me-1"></i><?= e($show['name']) ?>
                    &nbsp;·&nbsp;<?= date('M j, Y', strtotime($show['start_date'])) ?>
                    <?php if (($show['type'] ?? '') === 'virtual'): ?>
                        &nbsp;<span class="badge" style="background:#0ea5e9">Virtual</span>
                    <?php else: ?>
                        &nbsp;<span class="badge" style="background:#10b981">Physical</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row g-4">

            <!-- ── Main Content ──────────────────────────────────────────────── -->
            <div class="col-lg-8">

                <!-- About Company -->
                <?php if (!empty($booth['company_desc'])): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                        <i class="bi bi-info-circle me-2"></i>About the Company
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?= nl2br(e($booth['company_desc'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Video / Brochure -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                        <i class="bi bi-play-circle me-2"></i>Videos &amp; Brochures
                    </div>
                    <div class="card-body">
                        <?php if (!empty($booth['video_url'])): ?>
                            <div class="ratio ratio-16x9 mb-3">
                                <iframe src="<?= e($booth['video_url']) ?>" allowfullscreen
                                        class="rounded" style="border:0"></iframe>
                            </div>
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                 style="height:180px">
                                <div class="text-center text-muted">
                                    <i class="bi bi-play-btn display-4"></i>
                                    <p class="small mt-2 mb-0">No video uploaded yet.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booth['brochure_url'])): ?>
                            <a href="<?= e($booth['brochure_url']) ?>" class="btn btn-sm btn-outline-secondary mt-2"
                               target="_blank" rel="noopener">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Download Brochure
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Featured Products -->
                <?php if ($products): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                        <i class="bi bi-bag-heart me-2"></i>Featured Products
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($products as $p): ?>
                            <div class="col-6 col-md-4">
                                <div class="card border-0 shadow-sm h-100 product-card">
                                    <div class="bg-light" style="height:130px;overflow:hidden">
                                        <?php if (!empty($p['thumbnail'])): ?>
                                            <img src="<?= e($p['thumbnail']) ?>" class="w-100 h-100 object-fit-cover" alt="">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                                                <i class="bi bi-box-seam text-muted" style="font-size:2rem"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body p-2">
                                        <p class="small fw-semibold mb-1 text-truncate"><?= e($p['name']) ?></p>
                                        <p class="small fw-bold mb-2" style="color:#FF6B35">
                                            <?= formatMoney((float)$p['price'], $p['currency'] ?? 'USD') ?>
                                        </p>
                                        <a href="/pages/product/detail.php?slug=<?= e($p['slug']) ?>"
                                           class="btn btn-sm w-100 text-white" style="background:#1B2A4A">
                                            View
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                        <i class="bi bi-envelope me-2"></i>Contact this Exhibitor
                    </div>
                    <div class="card-body">
                        <?php if ($contactSuccess): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i>Your message has been sent!
                            </div>
                        <?php endif; ?>
                        <?php if ($contactError): ?>
                            <div class="alert alert-danger"><?= e($contactError) ?></div>
                        <?php endif; ?>
                        <?php if (!$contactSuccess): ?>
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Your Name</label>
                                    <input type="text" name="sender_name" class="form-control form-control-sm"
                                           value="<?= e($_POST['sender_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Email</label>
                                    <input type="email" name="sender_email" class="form-control form-control-sm"
                                           value="<?= e($_POST['sender_email'] ?? (isLoggedIn() ? getCurrentUser()['email'] : '')) ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Message</label>
                                <textarea name="message" class="form-control form-control-sm" rows="4" required
                                          placeholder="I'm interested in learning more about your products…"><?= e($_POST['message'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="contact_submit" class="btn btn-sm text-white fw-semibold px-4"
                                    style="background:#FF6B35;border-color:#FF6B35">
                                <i class="bi bi-send me-2"></i>Send Message
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Sidebar ────────────────────────────────────────────────────── -->
            <div class="col-lg-4">
                <!-- Contact Info -->
                <?php if (!empty($booth['contact_name'])): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3" style="color:#1B2A4A">
                            <i class="bi bi-person-badge me-2" style="color:#FF6B35"></i>Contact Person
                        </h6>
                        <p class="mb-1 small"><strong><?= e($booth['contact_name']) ?></strong></p>
                        <?php if (!empty($booth['contact_email'])): ?>
                            <p class="mb-1 small text-muted">
                                <i class="bi bi-envelope me-2"></i>
                                <a href="mailto:<?= e($booth['contact_email']) ?>"><?= e($booth['contact_email']) ?></a>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($booth['contact_phone'])): ?>
                            <p class="mb-0 small text-muted">
                                <i class="bi bi-telephone me-2"></i><?= e($booth['contact_phone']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Other Booths -->
                <?php if (count($allBooths) > 1): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header fw-semibold" style="background:#1B2A4A;color:#fff">
                        <i class="bi bi-grid me-2"></i>Other Exhibitors
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($allBooths as $ab): ?>
                        <a href="?show_id=<?= (int)$showId ?>&booth_id=<?= (int)$ab['id'] ?>"
                           class="d-flex align-items-center gap-2 p-3 text-decoration-none text-dark border-bottom sidebar-booth">
                            <?php if (!empty($ab['logo'])): ?>
                                <img src="<?= e($ab['logo']) ?>" class="rounded-circle border" style="width:36px;height:36px;object-fit:contain" alt="">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:36px;height:36px">
                                    <i class="bi bi-building text-white small"></i>
                                </div>
                            <?php endif; ?>
                            <span class="small fw-semibold text-truncate"><?= e($ab['company_name']) ?></span>
                            <?php if ($ab['is_featured']): ?>
                                <span class="badge ms-auto" style="background:#FF6B35;font-size:.65rem">Featured</span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
