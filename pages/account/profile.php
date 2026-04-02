<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$pageTitle = 'My Profile';
$user = getCurrentUser();
include __DIR__ . '/../../includes/header.php';
?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <img src="<?= $user['avatar'] ? e(APP_URL . '/' . $user['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['first_name'] . ' ' . $user['last_name']) . '&size=100&background=0d6efd&color=fff' ?>"
                         class="rounded-circle mb-3" width="80" height="80" alt="Avatar">
                    <h6 class="fw-bold mb-0"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                    <small class="text-muted"><?= e(ucfirst($user['role'])) ?></small>
                    <?php if ($user['is_verified']): ?>
                        <div class="mt-1"><span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span></div>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><a href="/pages/account/profile.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-person"></i> Profile</a></li>
                    <li class="list-group-item"><a href="/pages/account/addresses.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-geo-alt"></i> Addresses</a></li>
                    <li class="list-group-item"><a href="/pages/order/index.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-bag"></i> My Orders</a></li>
                    <li class="list-group-item"><a href="/pages/rfq/index.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-file-text"></i> My RFQs</a></li>
                    <li class="list-group-item"><a href="/pages/account/settings.php" class="text-decoration-none d-flex align-items-center gap-2"><i class="bi bi-gear"></i> Settings</a></li>
                    <li class="list-group-item"><a href="/api/auth.php?action=logout" class="text-decoration-none text-danger d-flex align-items-center gap-2"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-circle text-primary me-2"></i>Edit Profile</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/users.php?action=update_profile" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="_redirect" value="/pages/account/profile.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">First Name *</label>
                                <input type="text" name="first_name" class="form-control" value="<?= e($user['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?= e($user['last_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Company Name</label>
                                <input type="text" name="company_name" class="form-control" value="<?= e($user['company_name'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Bio</label>
                                <textarea name="bio" class="form-control" rows="3" placeholder="Tell us about yourself..."><?= e($user['bio'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Profile Photo</label>
                                <input type="file" name="avatar" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-lock-fill text-primary me-2"></i>Change Password</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/api/users.php?action=change_password">
                        <?= csrfField() ?>
                        <input type="hidden" name="_redirect" value="/pages/account/profile.php">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">New Password</label>
                                <input type="password" name="new_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Confirm New Password</label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-key me-1"></i> Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
