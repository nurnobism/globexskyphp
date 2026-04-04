<?php // includes/footer.php ?>
<footer class="bg-dark text-white mt-5 py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <h5 class="fw-bold text-primary mb-3"><i class="bi bi-globe2"></i> <?= e(APP_NAME) ?></h5>
                <p class="text-muted small">Your trusted global B2B trade platform connecting buyers and suppliers worldwide.</p>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="btn btn-outline-secondary btn-sm"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-outline-secondary btn-sm"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="btn btn-outline-secondary btn-sm"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="btn btn-outline-secondary btn-sm"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="<?= APP_URL ?>/pages/product/index.php" class="text-muted text-decoration-none">Products</a></li>
                    <li><a href="<?= APP_URL ?>/pages/supplier/index.php" class="text-muted text-decoration-none">Suppliers</a></li>
                    <li><a href="<?= APP_URL ?>/pages/rfq/create.php" class="text-muted text-decoration-none">Request for Quote</a></li>
                    <li><a href="<?= APP_URL ?>/pages/sourcing/index.php" class="text-muted text-decoration-none">Sourcing</a></li>
                    <li><a href="<?= APP_URL ?>/pages/shipment/tracking.php" class="text-muted text-decoration-none">Track Shipment</a></li>
                    <li><a href="<?= APP_URL ?>/pages/flash-sales/index.php" class="text-muted text-decoration-none">Flash Sales</a></li>
                    <li><a href="<?= APP_URL ?>/pages/about.php" class="text-muted text-decoration-none">About Us</a></li>
                    <li><a href="<?= APP_URL ?>/pages/blog/index.php" class="text-muted text-decoration-none">Blog</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Support</h6>
                <ul class="list-unstyled small">
                    <li><a href="<?= APP_URL ?>/pages/support/faq.php" class="text-muted text-decoration-none">FAQ</a></li>
                    <li><a href="<?= APP_URL ?>/pages/support/index.php" class="text-muted text-decoration-none">Support Center</a></li>
                    <li><a href="<?= APP_URL ?>/pages/help.php" class="text-muted text-decoration-none">Help Center</a></li>
                    <li><a href="<?= APP_URL ?>/pages/contact.php" class="text-muted text-decoration-none">Contact Us</a></li>
                    <li><a href="<?= APP_URL ?>/pages/privacy.php" class="text-muted text-decoration-none">Privacy Policy</a></li>
                    <li><a href="<?= APP_URL ?>/pages/terms.php" class="text-muted text-decoration-none">Terms of Service</a></li>
                    <li><a href="<?= APP_URL ?>/pages/cookie-policy.php" class="text-muted text-decoration-none">Cookie Policy</a></li>
                    <li><a href="<?= APP_URL ?>/pages/gdpr/index.php" class="text-muted text-decoration-none">GDPR</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Newsletter</h6>
                <p class="text-muted small">Get the latest trade news and product updates.</p>
                <form action="<?= APP_URL ?>/api/cms.php?action=newsletter_subscribe" method="POST" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="email" name="email" class="form-control form-control-sm" placeholder="Your email">
                    <button type="submit" class="btn btn-primary btn-sm">Go</button>
                </form>
                <div class="mt-3 small text-muted">
                    <i class="bi bi-geo-alt-fill"></i> 123 Trade Street, Global City<br>
                    <i class="bi bi-envelope-fill"></i> support@globexsky.com
                </div>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center small text-muted">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</span>
            <span>Built with PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?> + Bootstrap 5</span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"
        onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js'"></script>
<!-- Custom JS -->
<script src="<?= rtrim(APP_URL, '/') ?>/assets/js/app.js"></script>
<!-- Notification & Sound JS -->
<script src="<?= rtrim(APP_URL, '/') ?>/assets/js/notification-sounds.js"></script>
<script src="<?= rtrim(APP_URL, '/') ?>/assets/js/notifications.js"></script>
<!-- PWA -->
<script src="<?= rtrim(APP_URL, '/') ?>/assets/js/pwa.js"></script>
<?php if (isLoggedIn()): ?>
<script>
GlobexNotifications.init({
    baseUrl: <?= json_encode(APP_URL) ?>,
    csrfToken: <?= json_encode(csrfToken()) ?>
});
</script>
<?php endif; ?>
</body>
</html>
