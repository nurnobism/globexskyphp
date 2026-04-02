<?php
// includes/footer.php — Global footer, chatbot widget, and page-close tags

// Ensure config is available if footer is included standalone
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/app.php';
}

$footerYear = date('Y');
$socialLinks = [
    ['icon' => 'fa-brands fa-facebook-f',  'url' => 'https://facebook.com/globexsky',  'label' => 'Facebook'],
    ['icon' => 'fa-brands fa-x-twitter',   'url' => 'https://twitter.com/globexsky',   'label' => 'X / Twitter'],
    ['icon' => 'fa-brands fa-linkedin-in', 'url' => 'https://linkedin.com/company/globexsky', 'label' => 'LinkedIn'],
    ['icon' => 'fa-brands fa-instagram',   'url' => 'https://instagram.com/globexsky', 'label' => 'Instagram'],
    ['icon' => 'fa-brands fa-youtube',     'url' => 'https://youtube.com/@globexsky',  'label' => 'YouTube'],
    ['icon' => 'fa-brands fa-whatsapp',    'url' => 'https://wa.me/+18009562394',      'label' => 'WhatsApp'],
];

$paymentMethods = [
    ['name' => 'Visa',         'icon' => 'fa-brands fa-cc-visa'],
    ['name' => 'Mastercard',   'icon' => 'fa-brands fa-cc-mastercard'],
    ['name' => 'PayPal',       'icon' => 'fa-brands fa-cc-paypal'],
    ['name' => 'Stripe',       'icon' => 'fa-brands fa-cc-stripe'],
    ['name' => 'bKash',        'icon' => 'fa fa-mobile-alt'],
    ['name' => 'Nagad',        'icon' => 'fa fa-wallet'],
    ['name' => 'Amex',         'icon' => 'fa-brands fa-cc-amex'],
];
?>

<!-- ══════════════════════ FOOTER ══════════════════════ -->
<footer class="gs-footer mt-auto">

    <!-- Newsletter Strip -->
    <div class="gs-footer-newsletter">
        <div class="container">
            <div class="row align-items-center gy-3">
                <div class="col-lg-5">
                    <h5 class="mb-1 fw-bold text-white">
                        <i class="fa fa-envelope-open-text me-2" style="color:var(--gs-accent)"></i>
                        Stay Ahead of the Market
                    </h5>
                    <p class="mb-0 text-white-50 small">
                        Get trade insights, new supplier alerts, and exclusive deals delivered weekly.
                    </p>
                </div>
                <div class="col-lg-7">
                    <form class="row g-2" id="newsletterForm" novalidate>
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <div class="col-sm-8">
                            <input type="email"
                                   class="form-control gs-footer-input"
                                   name="email"
                                   placeholder="Your business email address"
                                   required
                                   aria-label="Email for newsletter">
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-gs-register w-100 fw-semibold">
                                <i class="fa fa-paper-plane me-1"></i>Subscribe
                            </button>
                        </div>
                        <div class="col-12" id="newsletterMsg" style="display:none"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer Grid -->
    <div class="gs-footer-main">
        <div class="container">
            <div class="row gy-5">

                <!-- Company Info Column -->
                <div class="col-lg-3 col-md-6">
                    <div class="brand-logo mb-3" style="font-size:1.5rem;font-weight:800;background:var(--gs-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">
                        <i class="fa fa-globe-asia me-1"></i>GlobexSky
                    </div>
                    <p class="gs-footer-text mb-4">
                        GlobexSky is a next-generation B2B global marketplace connecting buyers,
                        suppliers, and carriers across 190+ countries. We combine AI-powered sourcing,
                        trusted logistics, and escrow-backed payments for seamless global trade.
                    </p>
                    <!-- Social Links -->
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php foreach ($socialLinks as $s): ?>
                        <a href="<?= htmlspecialchars($s['url']) ?>"
                           class="gs-social-btn"
                           aria-label="<?= htmlspecialchars($s['label']) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <i class="<?= $s['icon'] ?>"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <!-- App Badges -->
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#" class="gs-app-badge">
                            <i class="fa-brands fa-apple me-1"></i>App Store
                        </a>
                        <a href="#" class="gs-app-badge">
                            <i class="fa-brands fa-google-play me-1"></i>Google Play
                        </a>
                    </div>
                </div>

                <!-- Quick Links Column -->
                <div class="col-lg-2 col-md-6 col-6">
                    <h6 class="gs-footer-heading">Company</h6>
                    <ul class="gs-footer-links">
                        <li><a href="<?= APP_URL ?>/pages/about">About Us</a></li>
                        <li><a href="<?= APP_URL ?>/pages/about/team">Our Team</a></li>
                        <li><a href="<?= APP_URL ?>/pages/about/careers">Careers
                            <span class="badge bg-success" style="font-size:.6rem">Hiring</span>
                        </a></li>
                        <li><a href="<?= APP_URL ?>/pages/about/press">Press &amp; Media</a></li>
                        <li><a href="<?= APP_URL ?>/pages/about/investors">Investors</a></li>
                        <li><a href="<?= APP_URL ?>/pages/blog">Blog &amp; News</a></li>
                        <li><a href="<?= APP_URL ?>/pages/support">Help Center</a></li>
                        <li><a href="<?= APP_URL ?>/pages/contact">Contact Us</a></li>
                        <li><a href="<?= APP_URL ?>/pages/support/tickets">Submit a Ticket</a></li>
                        <li><a href="<?= APP_URL ?>/pages/support/faq">FAQ</a></li>
                    </ul>
                </div>

                <!-- Services Column -->
                <div class="col-lg-2 col-md-6 col-6">
                    <h6 class="gs-footer-heading">Services</h6>
                    <ul class="gs-footer-links">
                        <li><a href="<?= APP_URL ?>/pages/product">Product Sourcing</a></li>
                        <li><a href="<?= APP_URL ?>/pages/rfq">Request for Quote</a></li>
                        <li><a href="<?= APP_URL ?>/pages/shipment">Parcel Shipping</a></li>
                        <li><a href="<?= APP_URL ?>/pages/carry">Carry Service</a></li>
                        <li><a href="<?= APP_URL ?>/pages/dropshipping">Dropshipping</a></li>
                        <li><a href="<?= APP_URL ?>/pages/inspection">Quality Inspection</a></li>
                        <li><a href="<?= APP_URL ?>/pages/trade-finance">Trade Finance</a></li>
                        <li><a href="<?= APP_URL ?>/pages/api-platform">API Platform</a></li>
                        <li><a href="<?= APP_URL ?>/pages/livestream">Live Sourcing</a></li>
                        <li><a href="<?= APP_URL ?>/pages/trade-shows">Trade Shows</a></li>
                        <li><a href="<?= APP_URL ?>/pages/ai">AI Assistant</a></li>
                    </ul>
                </div>

                <!-- For Sellers Column -->
                <div class="col-lg-2 col-md-6 col-6">
                    <h6 class="gs-footer-heading">For Sellers</h6>
                    <ul class="gs-footer-links">
                        <li><a href="<?= APP_URL ?>/pages/supplier">Become a Supplier</a></li>
                        <li><a href="<?= APP_URL ?>/pages/supplier/plans">Supplier Plans</a></li>
                        <li><a href="<?= APP_URL ?>/pages/supplier/dashboard">Seller Dashboard</a></li>
                        <li><a href="<?= APP_URL ?>/pages/campaigns">Run Campaigns</a></li>
                        <li><a href="<?= APP_URL ?>/pages/carry/register">Become a Carrier</a></li>
                        <li><a href="<?= APP_URL ?>/pages/carry/earnings">Carrier Earnings</a></li>
                        <li><a href="<?= APP_URL ?>/pages/about/trust-safety">Trust &amp; Safety</a></li>
                    </ul>

                    <h6 class="gs-footer-heading mt-4">Legal</h6>
                    <ul class="gs-footer-links">
                        <li><a href="<?= APP_URL ?>/pages/privacy">Privacy Policy</a></li>
                        <li><a href="<?= APP_URL ?>/pages/terms">Terms of Service</a></li>
                        <li><a href="<?= APP_URL ?>/pages/gdpr">GDPR</a></li>
                        <li><a href="<?= APP_URL ?>/pages/cookies">Cookie Policy</a></li>
                        <li><a href="<?= APP_URL ?>/pages/aml">AML Policy</a></li>
                        <li><a href="<?= APP_URL ?>/pages/sitemap">Sitemap</a></li>
                    </ul>
                </div>

                <!-- Trust & Stats Column -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="gs-footer-heading">Trusted Worldwide</h6>
                    <div class="row g-2 mb-4">
                        <?php
                        $stats = [
                            ['icon' => 'fa-store',       'value' => '50K+',  'label' => 'Verified Suppliers'],
                            ['icon' => 'fa-users',       'value' => '2M+',   'label' => 'Active Buyers'],
                            ['icon' => 'fa-globe',       'value' => '190+',  'label' => 'Countries'],
                            ['icon' => 'fa-boxes',       'value' => '10M+',  'label' => 'Products Listed'],
                        ];
                        foreach ($stats as $stat):
                        ?>
                        <div class="col-6">
                            <div class="gs-stat-card">
                                <i class="fa <?= $stat['icon'] ?> me-2" style="color:var(--gs-accent)"></i>
                                <span class="fw-bold text-white"><?= $stat['value'] ?></span>
                                <div class="small text-white-50"><?= $stat['label'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Certifications / Trust Badges -->
                    <div class="gs-trust-badges d-flex flex-wrap gap-2 mb-4">
                        <span class="badge gs-trust-badge">
                            <i class="fa fa-shield-alt me-1"></i>SSL Secured
                        </span>
                        <span class="badge gs-trust-badge">
                            <i class="fa fa-lock me-1"></i>PCI DSS
                        </span>
                        <span class="badge gs-trust-badge">
                            <i class="fa fa-check-circle me-1"></i>ISO 27001
                        </span>
                        <span class="badge gs-trust-badge">
                            <i class="fa fa-user-shield me-1"></i>GDPR
                        </span>
                    </div>

                    <!-- Contact Info -->
                    <ul class="gs-footer-contact">
                        <li>
                            <i class="fa fa-map-marker-alt"></i>
                            123 Global Trade Centre, New York, NY 10001, USA
                        </li>
                        <li>
                            <i class="fa fa-phone-alt"></i>
                            <a href="tel:+18009562394">+1 (800) GLOBEX-SKY</a>
                        </li>
                        <li>
                            <i class="fa fa-envelope"></i>
                            <a href="mailto:support@globexsky.com">support@globexsky.com</a>
                        </li>
                        <li>
                            <i class="fa fa-clock"></i>
                            24/7 Support Available
                        </li>
                    </ul>
                </div>

            </div><!-- /row -->
        </div><!-- /container -->
    </div><!-- /gs-footer-main -->

    <!-- Payment Methods Bar -->
    <div class="gs-footer-payments">
        <div class="container">
            <div class="row align-items-center gy-2">
                <div class="col-md-4">
                    <span class="text-white-50 small">
                        <i class="fa fa-lock me-1 text-success"></i>
                        Secure payments powered by
                    </span>
                </div>
                <div class="col-md-5">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <?php foreach ($paymentMethods as $pm): ?>
                        <i class="<?= $pm['icon'] ?> fa-2x text-white-50"
                           title="<?= htmlspecialchars($pm['name']) ?>"></i>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <span class="text-white-50 small">
                        <i class="fa fa-shield-halved me-1 text-success"></i>
                        Escrow &amp; Buyer Protection
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Copyright Bar -->
    <div class="gs-footer-copyright">
        <div class="container">
            <div class="row align-items-center gy-2">
                <div class="col-md-7">
                    <p class="mb-0 small text-white-50">
                        &copy; <?= $footerYear ?> GlobexSky Inc. All rights reserved.
                        Registered in Delaware, USA. VAT: US123456789.
                    </p>
                </div>
                <div class="col-md-5 text-md-end">
                    <a href="<?= APP_URL ?>/pages/privacy" class="text-white-50 small me-3">Privacy</a>
                    <a href="<?= APP_URL ?>/pages/terms"   class="text-white-50 small me-3">Terms</a>
                    <a href="<?= APP_URL ?>/pages/cookies" class="text-white-50 small me-3">Cookies</a>
                    <a href="<?= APP_URL ?>/pages/sitemap" class="text-white-50 small">Sitemap</a>
                </div>
            </div>
        </div>
    </div>

</footer>
<!-- ── End Footer ─────────────────────────────────────── -->


<!-- ══════════════════════ AI CHATBOT WIDGET ══════════════════════ -->

<!-- Floating Chat Button -->
<button class="gs-chat-fab" id="chatFab" aria-label="Open AI Chat" title="AI Assistant">
    <i class="fa fa-robot" id="chatFabIcon"></i>
    <span class="gs-chat-pulse"></span>
</button>

<!-- Chatbot Modal -->
<div class="modal fade" id="chatbotModal" tabindex="-1"
     aria-labelledby="chatbotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable gs-chat-dialog">
        <div class="modal-content gs-chat-content">

            <!-- Chat Header -->
            <div class="modal-header gs-chat-header border-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="gs-chat-avatar">
                        <i class="fa fa-robot"></i>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 text-white fw-bold" id="chatbotModalLabel">
                            GlobexSky AI Assistant
                        </h6>
                        <span class="small" style="color:#4ade80">
                            <i class="fa fa-circle" style="font-size:.5rem;vertical-align:middle"></i>
                            Online · Powered by DeepSeek
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm gs-chat-header-btn" id="clearChat"
                            title="Clear conversation">
                        <i class="fa fa-trash-alt"></i>
                    </button>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Chat Body / Messages -->
            <div class="modal-body gs-chat-body p-3" id="chatMessages">
                <!-- Welcome message -->
                <div class="gs-chat-msg gs-chat-msg-bot">
                    <div class="gs-chat-bubble">
                        <p class="mb-1">
                            👋 Hello! I'm your GlobexSky AI assistant, powered by DeepSeek.
                        </p>
                        <p class="mb-2">I can help you with:</p>
                        <ul class="mb-2 ps-3 small">
                            <li>Finding the right products &amp; suppliers</li>
                            <li>Shipping rates &amp; carrier options</li>
                            <li>Dropshipping setup &amp; markup rules</li>
                            <li>Order tracking &amp; disputes</li>
                            <li>Platform features &amp; how-tos</li>
                        </ul>
                        <p class="mb-0">How can I help you today?</p>
                    </div>
                    <div class="gs-chat-time">Just now</div>
                </div>

                <!-- Quick prompts -->
                <div class="gs-quick-prompts" id="quickPrompts">
                    <button class="gs-quick-btn" data-prompt="How do I find verified suppliers?">
                        <i class="fa fa-store me-1"></i>Find Suppliers
                    </button>
                    <button class="gs-quick-btn" data-prompt="What is the Carry delivery service?">
                        <i class="fa fa-plane me-1"></i>Carry Service
                    </button>
                    <button class="gs-quick-btn" data-prompt="How does escrow payment work?">
                        <i class="fa fa-shield-alt me-1"></i>Escrow Payment
                    </button>
                    <button class="gs-quick-btn" data-prompt="How do I set up dropshipping?">
                        <i class="fa fa-store-alt me-1"></i>Dropshipping
                    </button>
                </div>
            </div>

            <!-- Typing Indicator (hidden by default) -->
            <div class="gs-typing-wrap px-3 pb-1" id="typingIndicator" style="display:none">
                <div class="gs-chat-msg gs-chat-msg-bot">
                    <div class="gs-chat-bubble gs-typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>

            <!-- Chat Input -->
            <div class="modal-footer gs-chat-footer border-0 p-2">
                <form class="w-100 d-flex gap-2" id="chatForm" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="text"
                           class="form-control gs-chat-input"
                           id="chatInput"
                           name="message"
                           placeholder="Ask anything about GlobexSky…"
                           maxlength="2000"
                           autocomplete="off"
                           aria-label="Chat message">
                    <button type="submit" class="btn gs-chat-send" id="chatSendBtn"
                            aria-label="Send message">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </form>
                <div class="w-100 text-center mt-1">
                    <small style="color:rgba(255,255,255,.3);font-size:.65rem">
                        AI responses are for guidance only. Verify critical info independently.
                    </small>
                </div>
            </div>

        </div><!-- /modal-content -->
    </div><!-- /modal-dialog -->
</div><!-- /chatbotModal -->

<!-- ── Footer Styles ──────────────────────────────────── -->
<style>
    :root {
        --gs-primary:  #0d6efd;
        --gs-dark:     #0a0f1e;
        --gs-dark-2:   #111827;
        --gs-accent:   #00d4ff;
        --gs-gradient: linear-gradient(135deg, #0d6efd 0%, #00d4ff 100%);
    }

    /* ── Footer Base ──────────────────────────────────── */
    .gs-footer { background: var(--gs-dark); color: rgba(255,255,255,.75); }

    /* Newsletter */
    .gs-footer-newsletter {
        background: linear-gradient(135deg, rgba(13,110,253,.25) 0%, rgba(0,212,255,.15) 100%);
        border-top: 1px solid rgba(13,110,253,.3);
        border-bottom: 1px solid rgba(255,255,255,.07);
        padding: 2.5rem 0;
    }
    .gs-footer-input {
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.15);
        color: #fff;
        border-radius: .5rem;
    }
    .gs-footer-input::placeholder { color: rgba(255,255,255,.4); }
    .gs-footer-input:focus {
        background: rgba(255,255,255,.12);
        border-color: var(--gs-primary);
        box-shadow: none;
        color: #fff;
    }
    .btn-gs-register {
        background: var(--gs-gradient);
        border: none;
        color: #fff;
        border-radius: .4rem;
    }
    .btn-gs-register:hover { opacity: .9; color: #fff; }

    /* Main grid */
    .gs-footer-main { padding: 4rem 0 2rem; }

    .gs-footer-heading {
        color: #fff;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .1em;
        font-weight: 700;
        margin-bottom: 1.2rem;
        padding-bottom: .5rem;
        border-bottom: 1px solid rgba(255,255,255,.08);
    }

    .gs-footer-text { font-size: .85rem; line-height: 1.7; color: rgba(255,255,255,.55); }

    .gs-footer-links {
        list-style: none;
        padding: 0; margin: 0;
    }
    .gs-footer-links li { margin-bottom: .45rem; }
    .gs-footer-links a {
        color: rgba(255,255,255,.55);
        text-decoration: none;
        font-size: .855rem;
        transition: color .15s, padding-left .15s;
    }
    .gs-footer-links a:hover { color: var(--gs-accent); padding-left: 4px; }

    .gs-footer-contact {
        list-style: none;
        padding: 0; margin: 0;
    }
    .gs-footer-contact li {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        color: rgba(255,255,255,.55);
        font-size: .82rem;
        margin-bottom: .6rem;
    }
    .gs-footer-contact li .fa {
        color: var(--gs-accent);
        width: 14px;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .gs-footer-contact a { color: rgba(255,255,255,.55); text-decoration: none; }
    .gs-footer-contact a:hover { color: var(--gs-accent); }

    /* Social */
    .gs-social-btn {
        width: 34px; height: 34px;
        border-radius: .4rem;
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.1);
        color: rgba(255,255,255,.65);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: .875rem;
        transition: background .18s, color .18s, transform .18s;
    }
    .gs-social-btn:hover {
        background: var(--gs-primary);
        color: #fff;
        border-color: var(--gs-primary);
        transform: translateY(-2px);
    }

    /* App badges */
    .gs-app-badge {
        display: inline-flex;
        align-items: center;
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.15);
        color: rgba(255,255,255,.75);
        border-radius: .4rem;
        padding: .3rem .75rem;
        font-size: .78rem;
        text-decoration: none;
        transition: background .18s;
    }
    .gs-app-badge:hover { background: rgba(255,255,255,.13); color: #fff; }

    /* Stats cards */
    .gs-stat-card {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: .5rem;
        padding: .6rem .7rem;
        font-size: .8rem;
        color: rgba(255,255,255,.55);
    }
    .gs-stat-card .fw-bold { font-size: 1rem; }

    /* Trust badges */
    .gs-trust-badge {
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.12);
        color: rgba(255,255,255,.65);
        font-size: .7rem;
        font-weight: 500;
        padding: .3rem .6rem;
    }

    /* Payments bar */
    .gs-footer-payments {
        border-top: 1px solid rgba(255,255,255,.07);
        padding: 1.2rem 0;
    }

    /* Copyright */
    .gs-footer-copyright {
        border-top: 1px solid rgba(255,255,255,.06);
        padding: 1rem 0;
    }
    .gs-footer-copyright a { text-decoration: none; }
    .gs-footer-copyright a:hover { color: var(--gs-accent) !important; }

    /* ── Chat FAB ─────────────────────────────────────── */
    .gs-chat-fab {
        position: fixed;
        bottom: 28px;
        right: 28px;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: var(--gs-gradient);
        border: none;
        color: #fff;
        font-size: 1.3rem;
        box-shadow: 0 6px 24px rgba(13,110,253,.45);
        z-index: 1040;
        cursor: pointer;
        transition: transform .2s, box-shadow .2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .gs-chat-fab:hover {
        transform: scale(1.08);
        box-shadow: 0 8px 32px rgba(13,110,253,.6);
    }
    .gs-chat-pulse {
        position: absolute;
        top: 2px; right: 2px;
        width: 12px; height: 12px;
        background: #4ade80;
        border-radius: 50%;
        border: 2px solid var(--gs-dark);
    }

    /* ── Chat Modal ───────────────────────────────────── */
    .gs-chat-dialog {
        position: fixed !important;
        bottom: 100px;
        right: 28px;
        margin: 0 !important;
        max-width: 380px;
        width: calc(100vw - 56px);
    }
    .gs-chat-content {
        background: #0f172a;
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 1rem;
        max-height: 570px;
        box-shadow: 0 24px 80px rgba(0,0,0,.6);
    }
    .gs-chat-header {
        background: linear-gradient(135deg, #1e293b, #0f1f3d);
        border-radius: 1rem 1rem 0 0;
        padding: .85rem 1rem;
    }
    .gs-chat-avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: var(--gs-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: .95rem;
    }
    .gs-chat-header-btn {
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.1);
        color: rgba(255,255,255,.65);
        border-radius: .35rem;
        padding: .25rem .5rem;
        font-size: .75rem;
    }
    .gs-chat-header-btn:hover { background: rgba(255,0,0,.2); color: #f87171; }

    .gs-chat-body {
        background: #0f172a;
        min-height: 300px;
        max-height: 370px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
    .gs-chat-body::-webkit-scrollbar       { width: 4px; }
    .gs-chat-body::-webkit-scrollbar-track { background: transparent; }
    .gs-chat-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 4px; }

    /* Messages */
    .gs-chat-msg { display: flex; flex-direction: column; margin-bottom: 1rem; }
    .gs-chat-msg-bot  { align-items: flex-start; }
    .gs-chat-msg-user { align-items: flex-end; }

    .gs-chat-bubble {
        max-width: 80%;
        padding: .6rem .85rem;
        border-radius: .9rem;
        font-size: .84rem;
        line-height: 1.55;
        word-break: break-word;
    }
    .gs-chat-msg-bot  .gs-chat-bubble {
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.88);
        border-radius: .2rem .9rem .9rem .9rem;
    }
    .gs-chat-msg-user .gs-chat-bubble {
        background: var(--gs-gradient);
        color: #fff;
        border-radius: .9rem .2rem .9rem .9rem;
    }

    .gs-chat-time {
        font-size: .65rem;
        color: rgba(255,255,255,.3);
        margin-top: .25rem;
        padding: 0 .2rem;
    }

    /* Typing dots */
    .gs-typing { display: flex; gap: 4px; align-items: center; padding: .5rem .8rem; }
    .gs-typing span {
        width: 6px; height: 6px;
        background: rgba(255,255,255,.4);
        border-radius: 50%;
        animation: typingBounce 1.2s infinite;
    }
    .gs-typing span:nth-child(2) { animation-delay: .2s; }
    .gs-typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes typingBounce {
        0%, 80%, 100% { transform: translateY(0); }
        40%            { transform: translateY(-6px); }
    }

    /* Quick prompts */
    .gs-quick-prompts {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .5rem;
        margin-bottom: .25rem;
    }
    .gs-quick-btn {
        background: rgba(13,110,253,.15);
        border: 1px solid rgba(13,110,253,.3);
        color: rgba(255,255,255,.75);
        border-radius: 2rem;
        padding: .3rem .75rem;
        font-size: .75rem;
        cursor: pointer;
        transition: background .15s, color .15s;
        white-space: nowrap;
    }
    .gs-quick-btn:hover {
        background: rgba(13,110,253,.35);
        color: #fff;
        border-color: rgba(13,110,253,.6);
    }

    /* Chat footer */
    .gs-chat-footer { background: #0f172a; border-radius: 0 0 1rem 1rem; }
    .gs-chat-input {
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.12);
        color: #fff;
        border-radius: .6rem;
        font-size: .84rem;
    }
    .gs-chat-input::placeholder { color: rgba(255,255,255,.35); }
    .gs-chat-input:focus {
        background: rgba(255,255,255,.1);
        border-color: var(--gs-primary);
        box-shadow: none;
        color: #fff;
    }
    .gs-chat-send {
        background: var(--gs-gradient);
        border: none;
        color: #fff;
        border-radius: .6rem;
        padding: .5rem .85rem;
        font-size: .9rem;
        flex-shrink: 0;
    }
    .gs-chat-send:hover  { opacity: .9; color: #fff; }
    .gs-chat-send:disabled { opacity: .5; cursor: not-allowed; }

    /* Mobile chat dialog */
    @media (max-width: 479.98px) {
        .gs-chat-dialog { right: 12px; width: calc(100vw - 24px); bottom: 90px; }
        .gs-chat-fab    { bottom: 16px; right: 16px; }
    }
</style>

<!-- ── Chat JavaScript ─────────────────────────────── -->
<script>
(function () {
    'use strict';

    var chatModal      = new bootstrap.Modal(document.getElementById('chatbotModal'));
    var chatFab        = document.getElementById('chatFab');
    var chatFabIcon    = document.getElementById('chatFabIcon');
    var chatMessages   = document.getElementById('chatMessages');
    var chatInput      = document.getElementById('chatInput');
    var chatForm       = document.getElementById('chatForm');
    var chatSendBtn    = document.getElementById('chatSendBtn');
    var typingIndicator= document.getElementById('typingIndicator');
    var quickPrompts   = document.getElementById('quickPrompts');
    var clearChat      = document.getElementById('clearChat');
    var sessionId      = 'chat_' + Date.now();

    // Toggle modal from FAB
    chatFab.addEventListener('click', function () {
        chatModal.toggle();
    });
    document.getElementById('chatbotModal').addEventListener('shown.bs.modal', function () {
        chatFabIcon.className = 'fa fa-times';
        chatInput.focus();
    });
    document.getElementById('chatbotModal').addEventListener('hidden.bs.modal', function () {
        chatFabIcon.className = 'fa fa-robot';
    });

    // Quick prompt buttons
    if (quickPrompts) {
        quickPrompts.addEventListener('click', function (e) {
            var btn = e.target.closest('.gs-quick-btn');
            if (btn) sendMessage(btn.dataset.prompt);
        });
    }

    // Submit form
    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = chatInput.value.trim();
        if (msg) sendMessage(msg);
    });

    // Clear conversation
    clearChat.addEventListener('click', function () {
        if (!confirm('Clear the entire conversation?')) return;
        chatMessages.innerHTML = '';
        sessionId = 'chat_' + Date.now();
        addBotMessage('Conversation cleared. How can I help you?');
    });

    function sendMessage(text) {
        chatInput.value = '';
        chatSendBtn.disabled = true;

        // Hide quick prompts after first interaction
        if (quickPrompts) {
            quickPrompts.style.display = 'none';
        }

        appendMessage('user', escapeHtml(text));
        typingIndicator.style.display = 'block';
        scrollToBottom();

        var csrfToken = chatForm.querySelector('[name="csrf_token"]').value;

        fetch('<?= APP_URL ?>/api/ai/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                message:    text,
                session_id: sessionId,
                context:    'platform_assistant'
            })
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(function (data) {
            typingIndicator.style.display = 'none';
            if (data.reply) {
                addBotMessage(data.reply);
            } else if (data.error) {
                addBotMessage('Sorry, I encountered an error: ' + escapeHtml(data.error));
            }
        })
        .catch(function (err) {
            typingIndicator.style.display = 'none';
            addBotMessage('Sorry, I\'m having trouble connecting right now. Please try again in a moment.');
            console.error('Chat error:', err);
        })
        .finally(function () {
            chatSendBtn.disabled = false;
            chatInput.focus();
        });
    }

    function appendMessage(role, html) {
        var time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        var cls  = role === 'user' ? 'gs-chat-msg-user' : 'gs-chat-msg-bot';
        var div  = document.createElement('div');
        div.className = 'gs-chat-msg ' + cls;
        div.innerHTML =
            '<div class="gs-chat-bubble">' + html + '</div>' +
            '<div class="gs-chat-time">' + escapeHtml(time) + '</div>';
        // Insert before typing indicator
        chatMessages.insertBefore(div, typingIndicator);
        scrollToBottom();
    }

    function addBotMessage(text) {
        // Convert basic markdown: **bold**, `code`, newlines
        var html = escapeHtml(text)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`(.+?)`/g, '<code style="background:rgba(255,255,255,.12);padding:1px 4px;border-radius:3px">$1</code>')
            .replace(/\n/g, '<br>');
        appendMessage('bot', html);
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    // Newsletter form
    var newsletterForm = document.getElementById('newsletterForm');
    var newsletterMsg  = document.getElementById('newsletterMsg');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = newsletterForm.querySelector('[name="email"]').value.trim();
            if (!email) return;

            fetch('<?= APP_URL ?>/api/newsletter/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ email: email, csrf_token: newsletterForm.querySelector('[name="csrf_token"]').value })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                newsletterMsg.style.display = 'block';
                if (d.success) {
                    newsletterMsg.innerHTML = '<div class="alert alert-success py-1 mb-0 small"><i class="fa fa-check-circle me-1"></i>' + escapeHtml(d.message || 'Subscribed successfully!') + '</div>';
                    newsletterForm.reset();
                } else {
                    newsletterMsg.innerHTML = '<div class="alert alert-warning py-1 mb-0 small"><i class="fa fa-exclamation-circle me-1"></i>' + escapeHtml(d.error || 'Please try again.') + '</div>';
                }
            })
            .catch(function () {
                newsletterMsg.style.display = 'block';
                newsletterMsg.innerHTML = '<div class="alert alert-danger py-1 mb-0 small">Connection error. Please try again.</div>';
            });
        });
    }

})();
</script>

</body>
</html>
