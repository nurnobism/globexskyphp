<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$pageTitle = 'Invite Team Members';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="bi bi-person-plus me-2"></i>Invite Team Members</h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="post" action="../../api/teams.php?action=invite_member">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="emails" class="form-label">Email Addresses <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="emails" name="emails" rows="5" required
                                      placeholder="Enter email addresses, one per line&#10;&#10;e.g.&#10;john@example.com&#10;jane@example.com"></textarea>
                            <div class="form-text">Enter one email address per line.</div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select role...</option>
                                <option value="member">Member — Can view and create orders</option>
                                <option value="editor">Editor — Can edit products and content</option>
                                <option value="admin">Admin — Full access except team ownership</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label">Personal Message</label>
                            <textarea class="form-control" id="message" name="message" rows="3"
                                      placeholder="Add a personal note to the invitation email (optional)"></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send me-1"></i>Send Invitations
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>About Roles</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="badge bg-primary me-1">Member</span>
                            Can view dashboard, create orders, and manage their own profile.
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-info me-1">Editor</span>
                            Everything a Member can do, plus edit products, content, and manage inventory.
                        </li>
                        <li>
                            <span class="badge bg-warning text-dark me-1">Admin</span>
                            Full access to all features except transferring ownership and deleting the team.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
