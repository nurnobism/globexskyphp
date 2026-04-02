<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
$db = getDB();

$pageTitle = 'Create Blog Post';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Blog</a></li>
                    <li class="breadcrumb-item active">Create Post</li>
                </ol>
            </nav>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-pencil-square me-2 text-primary"></i>New Blog Post
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/blog.php" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create">

                        <!-- Title -->
                        <div class="mb-4">
                            <label for="title" class="form-label fw-semibold">Post Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control form-control-lg"
                                   placeholder="Enter an engaging post title…" required>
                        </div>

                        <!-- Excerpt -->
                        <div class="mb-4">
                            <label for="excerpt" class="form-label fw-semibold">Excerpt</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" rows="2"
                                      placeholder="A short summary shown in post listings (max 200 characters)…" maxlength="200"></textarea>
                            <div class="form-text">Brief description shown in blog listing cards.</div>
                        </div>

                        <!-- Content -->
                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
                            <textarea id="content" name="content" class="form-control" rows="10"
                                      placeholder="Write your post content here… HTML is supported." required></textarea>
                            <div class="form-text">HTML is accepted. Use headings, paragraphs, lists, and links.</div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Category -->
                            <div class="col-md-4">
                                <label for="category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select category…</option>
                                    <option value="news">News</option>
                                    <option value="guides">Guides</option>
                                    <option value="case-studies">Case Studies</option>
                                    <option value="industry">Industry</option>
                                    <option value="announcements">Announcements</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="draft" selected>Draft</option>
                                    <option value="published">Published</option>
                                </select>
                                <div class="form-text">Drafts are only visible to you.</div>
                            </div>

                            <!-- Tags -->
                            <div class="col-md-4">
                                <label for="tags" class="form-label fw-semibold">Tags</label>
                                <input type="text" id="tags" name="tags" class="form-control"
                                       placeholder="e.g. trade, B2B, export">
                                <div class="form-text">Comma-separated list of tags.</div>
                            </div>
                        </div>

                        <!-- Featured Image (optional) -->
                        <div class="mb-4">
                            <label for="featured_image" class="form-label fw-semibold">Featured Image</label>
                            <input type="file" id="featured_image" name="featured_image" class="form-control"
                                   accept="image/png,image/jpeg,image/webp">
                            <div class="form-text">Optional. Accepted formats: PNG, JPEG, WebP. Max 2 MB.</div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send me-1"></i> Publish Post
                            </button>
                            <button type="submit" name="status" value="draft" class="btn btn-outline-secondary">
                                <i class="bi bi-floppy me-1"></i> Save as Draft
                            </button>
                            <a href="index.php" class="btn btn-link text-muted ms-auto">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live character counter for excerpt
const excerptEl = document.getElementById('excerpt');
const excerptHint = excerptEl.nextElementSibling;
excerptEl.addEventListener('input', function () {
    const remaining = 200 - this.value.length;
    excerptHint.textContent = remaining + ' characters remaining.';
    excerptHint.className = 'form-text ' + (remaining < 20 ? 'text-warning' : '');
});

// Override publish button to set status=published
document.querySelector('form').addEventListener('submit', function (e) {
    const btn = e.submitter;
    if (btn && btn.name === 'status' && btn.value === 'draft') {
        document.getElementById('status').value = 'draft';
    } else {
        // Keep whatever status is in the select
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
