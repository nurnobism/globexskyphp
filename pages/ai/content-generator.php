<?php
/**
 * pages/ai/content-generator.php — AI Content Generator (Phase 8)
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-file-earmark-magic fs-2 text-success me-3"></i>
        <div>
            <h1 class="h3 mb-0">AI Content Generator</h1>
            <p class="text-muted mb-0">Generate product descriptions, SEO content, ad copy &amp; more</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Generator Panel -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" id="content-tabs">
                        <li class="nav-item"><a class="nav-link active" href="#" data-type="product_description">Description</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-type="seo_title">SEO Title</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-type="ad_copy">Ad Copy</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-type="translation">Translation</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-type="improve">Improve Text</a></li>
                    </ul>

                    <!-- Input -->
                    <div class="mb-3">
                        <label class="form-label">Product / Text Input</label>
                        <textarea class="form-control" id="content-input" rows="5" placeholder="Enter product details, text to translate, or content to improve..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small">Style / Tone</label>
                            <select class="form-select form-select-sm" id="style-select">
                                <option value="professional">Professional</option>
                                <option value="casual">Casual</option>
                                <option value="luxury">Luxury</option>
                                <option value="technical">Technical</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Language</label>
                            <select class="form-select form-select-sm" id="language-select">
                                <option value="en">English</option>
                                <option value="zh">Chinese</option>
                                <option value="es">Spanish</option>
                                <option value="fr">French</option>
                                <option value="ar">Arabic</option>
                                <option value="de">German</option>
                                <option value="ja">Japanese</option>
                                <option value="ko">Korean</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-success w-100" id="generate-btn">
                                <i class="bi bi-stars me-2"></i>Generate
                            </button>
                        </div>
                    </div>

                    <!-- Output -->
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">Generated Content</label>
                        <button class="btn btn-outline-secondary btn-sm d-none" id="copy-btn"><i class="bi bi-clipboard me-1"></i>Copy</button>
                    </div>
                    <div class="border rounded p-3 bg-light" id="content-output" style="min-height:100px;">
                        <span class="text-muted">Your AI-generated content will appear here...</span>
                    </div>
                    <div class="mt-2 d-none" id="content-actions">
                        <button class="btn btn-primary btn-sm" id="approve-btn"><i class="bi bi-check2 me-1"></i>Approve &amp; Save</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="mb-0">Generation History</h6>
                </div>
                <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                    <div id="history-list">
                        <div class="text-center py-3 text-muted">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/ai-content.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
