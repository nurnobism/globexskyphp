<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$pageTitle = 'Schedule Meeting';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-calendar-plus me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Set up a new meeting with participants</p>
        </div>
        <a href="/pages/meetings/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Meetings</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form id="scheduleMeetingForm" method="post" action="/api/meetings.php?action=create">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold">Meeting Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Weekly Sync, Product Review" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Meeting agenda or notes..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label fw-semibold">Start Date &amp; Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label fw-semibold">End Date &amp; Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="participants" class="form-label fw-semibold">Participants</label>
                            <textarea class="form-control" id="participants" name="participants" rows="4" placeholder="Enter email addresses, one per line&#10;e.g.&#10;alice@example.com&#10;bob@example.com"></textarea>
                            <div class="form-text">One email address per line. Invitations will be sent to each participant.</div>
                        </div>

                        <div class="mb-4">
                            <label for="meeting_url" class="form-label fw-semibold">Meeting URL <span class="text-muted fw-normal">(optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                <input type="url" class="form-control" id="meeting_url" name="meeting_url" placeholder="https://meet.example.com/...">
                            </div>
                            <div class="form-text">Paste a Zoom, Google Meet, or Teams link</div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="/pages/meetings/index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancel</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Schedule Meeting</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
