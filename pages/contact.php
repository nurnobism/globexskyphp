<?php
require_once __DIR__ . '/../includes/middleware.php';
$pageTitle = 'Contact Us';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-5">
            <h2 class="fw-bold mb-3"><i class="bi bi-envelope-fill text-primary me-2"></i>Contact Us</h2>
            <p class="text-muted">Have a question or need support? We're here to help. Fill out the form and our team will get back to you within 24 hours.</p>

            <div class="d-flex flex-column gap-4 mt-4">
                <?php foreach ([
                    ['bi-geo-alt-fill', 'Office', '123 Trade Street, Global City, GC 10001'],
                    ['bi-envelope-fill', 'Email', 'support@globexsky.com'],
                    ['bi-telephone-fill', 'Phone', '+1 (800) GLOBEX-SKY'],
                    ['bi-clock-fill', 'Hours', 'Monday – Friday: 9am – 6pm UTC'],
                ] as [$icon, $label, $value]): ?>
                <div class="d-flex gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 flex-shrink-0">
                        <i class="bi <?= $icon ?> text-primary"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0"><?= $label ?></h6>
                        <p class="text-muted mb-0"><?= $value ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Send a Message</h5>
                    <form method="POST" action="/api/cms.php?action=contact">
                        <?= csrfField() ?>
                        <input type="hidden" name="_redirect" value="/pages/contact.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name *</label>
                                <input type="text" name="name" class="form-control" required placeholder="John Doe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email *</label>
                                <input type="email" name="email" class="form-control" required placeholder="you@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Subject</label>
                                <select name="subject" class="form-select">
                                    <option>General Inquiry</option>
                                    <option>Product/Supplier Support</option>
                                    <option>Order Issue</option>
                                    <option>Payment Issue</option>
                                    <option>Partnership</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Message *</label>
                                <textarea name="message" class="form-control" rows="5" required placeholder="Describe your question or issue in detail..."></textarea>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="bi bi-send me-1"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
