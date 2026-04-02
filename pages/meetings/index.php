<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$userId = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

$upcoming = [];
$past = [];
try {
    $stmt = $db->prepare("
        SELECT m.*, (SELECT COUNT(*) FROM meeting_participants WHERE meeting_id = m.id) AS participant_count
        FROM meetings m
        WHERE m.organizer_id = ? AND m.start_time >= ?
        ORDER BY m.start_time ASC
    ");
    $stmt->execute([$userId, $now]);
    $upcoming = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT m.*, (SELECT COUNT(*) FROM meeting_participants WHERE meeting_id = m.id) AS participant_count
        FROM meetings m
        WHERE m.organizer_id = ? AND m.start_time < ?
        ORDER BY m.start_time DESC
        LIMIT 20
    ");
    $stmt->execute([$userId, $now]);
    $past = $stmt->fetchAll();
} catch (\Exception $e) {
    $upcoming = [];
    $past = [];
}

$pageTitle = 'Meetings';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-camera-video me-2"></i><?= e($pageTitle) ?></h1>
            <p class="text-muted mb-0">Manage your meetings and video calls</p>
        </div>
        <a href="/pages/meetings/schedule.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Schedule Meeting</a>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#upcoming" type="button">
                <i class="bi bi-calendar-event me-1"></i>Upcoming
                <?php if (count($upcoming) > 0): ?>
                    <span class="badge bg-primary ms-1"><?= count($upcoming) ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#past" type="button">
                <i class="bi bi-calendar-check me-1"></i>Past
                <?php if (count($past) > 0): ?>
                    <span class="badge bg-secondary ms-1"><?= count($past) ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Upcoming Meetings -->
        <div class="tab-pane fade show active" id="upcoming">
            <?php if (empty($upcoming)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-calendar-plus display-1 text-muted"></i>
                        <p class="text-muted mt-3">No upcoming meetings.</p>
                        <a href="/pages/meetings/schedule.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Schedule a Meeting</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($upcoming as $meeting):
                        $statusMap = [
                            'scheduled'  => 'primary',
                            'in_progress' => 'success',
                            'completed'  => 'secondary',
                            'cancelled'  => 'danger',
                        ];
                        $badgeClass = $statusMap[$meeting['status'] ?? ''] ?? 'secondary';
                    ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?= e($meeting['title']) ?></h6>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= e(ucfirst(str_replace('_', ' ', $meeting['status'] ?? 'scheduled'))) ?></span>
                                    </div>
                                    <div class="text-muted small mb-3">
                                        <div><i class="bi bi-calendar3 me-1"></i><?= formatDateTime($meeting['start_time']) ?></div>
                                        <div><i class="bi bi-people me-1"></i><?= (int)$meeting['participant_count'] ?> participant(s)</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="/pages/meetings/detail.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Details</a>
                                        <?php if (!empty($meeting['meeting_url'])): ?>
                                            <a href="<?= e($meeting['meeting_url']) ?>" class="btn btn-sm btn-success" target="_blank" rel="noopener noreferrer"><i class="bi bi-camera-video me-1"></i>Join</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Meetings -->
        <div class="tab-pane fade" id="past">
            <?php if (empty($past)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-calendar-check display-1 text-muted"></i>
                        <p class="text-muted mt-3">No past meetings.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($past as $meeting):
                        $statusMap = [
                            'scheduled'  => 'primary',
                            'in_progress' => 'success',
                            'completed'  => 'secondary',
                            'cancelled'  => 'danger',
                        ];
                        $badgeClass = $statusMap[$meeting['status'] ?? ''] ?? 'secondary';
                    ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?= e($meeting['title']) ?></h6>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= e(ucfirst(str_replace('_', ' ', $meeting['status'] ?? 'completed'))) ?></span>
                                    </div>
                                    <div class="text-muted small mb-3">
                                        <div><i class="bi bi-calendar3 me-1"></i><?= formatDateTime($meeting['start_time']) ?></div>
                                        <div><i class="bi bi-people me-1"></i><?= (int)$meeting['participant_count'] ?> participant(s)</div>
                                    </div>
                                    <a href="/pages/meetings/detail.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
