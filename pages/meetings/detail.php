<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$db = getDB();
$meetingId = (int)get('id', 0);

if ($meetingId <= 0) {
    flashMessage('danger', 'Invalid meeting ID.');
    redirect('/pages/meetings/index.php');
}

$stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    flashMessage('danger', 'Meeting not found.');
    redirect('/pages/meetings/index.php');
}

$participants = [];
try {
    $stmt = $db->prepare("
        SELECT mp.*, u.first_name, u.last_name, u.email
        FROM meeting_participants mp
        LEFT JOIN users u ON mp.user_id = u.id
        WHERE mp.meeting_id = ?
        ORDER BY mp.created_at ASC
    ");
    $stmt->execute([$meetingId]);
    $participants = $stmt->fetchAll();
} catch (\Exception $e) {
    $participants = [];
}

$statusMap = [
    'scheduled'   => 'primary',
    'in_progress' => 'success',
    'completed'   => 'secondary',
    'cancelled'   => 'danger',
];
$badgeClass = $statusMap[$meeting['status'] ?? ''] ?? 'secondary';

$pageTitle = e($meeting['title']);
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/pages/meetings/index.php">Meetings</a></li>
                    <li class="breadcrumb-item active"><?= e($meeting['title']) ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-camera-video me-2"></i><?= e($meeting['title']) ?></h1>
        </div>
        <div>
            <?php if (!empty($meeting['meeting_url'])): ?>
                <a href="<?= e($meeting['meeting_url']) ?>" class="btn btn-success me-2" target="_blank" rel="noopener noreferrer"><i class="bi bi-camera-video-fill me-1"></i>Join Meeting</a>
            <?php endif; ?>
            <a href="/pages/meetings/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Meeting Info -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Meeting Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">Status</div>
                        <div class="col-sm-9">
                            <span class="badge bg-<?= $badgeClass ?> fs-6"><?= e(ucfirst(str_replace('_', ' ', $meeting['status'] ?? 'scheduled'))) ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">Start</div>
                        <div class="col-sm-9"><i class="bi bi-calendar3 me-1"></i><?= formatDateTime($meeting['start_time']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">End</div>
                        <div class="col-sm-9"><i class="bi bi-calendar3 me-1"></i><?= formatDateTime($meeting['end_time']) ?></div>
                    </div>
                    <?php if (!empty($meeting['meeting_url'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-3 text-muted">Meeting URL</div>
                            <div class="col-sm-9">
                                <a href="<?= e($meeting['meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-link-45deg me-1"></i><?= e($meeting['meeting_url']) ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($meeting['description'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-3 text-muted">Description</div>
                            <div class="col-sm-9"><?= nl2br(e($meeting['description'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-sm-3 text-muted">Created</div>
                        <div class="col-sm-9"><small class="text-muted"><?= formatDateTime($meeting['created_at']) ?></small></div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="bi bi-journal-text me-2"></i>Meeting Notes</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($meeting['notes'])): ?>
                        <div class="bg-light rounded p-3"><?= nl2br(e($meeting['notes'])) ?></div>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-pencil me-1"></i>No notes recorded for this meeting.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Participants -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Participants (<?= count($participants) ?>)</h5>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($participants)): ?>
                        <li class="list-group-item text-center text-muted py-4">No participants added.</li>
                    <?php else: ?>
                        <?php foreach ($participants as $p):
                            $pStatusMap = [
                                'invited'  => 'warning',
                                'accepted' => 'success',
                                'declined' => 'danger',
                            ];
                            $pBadge = $pStatusMap[$p['status'] ?? ''] ?? 'secondary';
                            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                            if (empty($name)) {
                                $name = $p['email'] ?? 'Unknown';
                            }
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-person-circle me-2 text-muted"></i>
                                    <span class="fw-semibold"><?= e($name) ?></span>
                                    <?php if (!empty($p['email'])): ?>
                                        <br><small class="text-muted ms-4"><?= e($p['email']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?= $pBadge ?>"><?= e(ucfirst($p['status'] ?? 'invited')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
