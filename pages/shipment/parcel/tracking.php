<?php
require_once __DIR__ . '/../../../includes/middleware.php';

$tracking = trim($_GET['tracking'] ?? '');

$pageTitle = 'Track Parcel';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Track Your Parcel</h5>
                </div>
                <div class="card-body p-4">
                    <form id="trackForm" class="d-flex gap-2">
                        <input type="text" id="trackingInput" class="form-control form-control-lg"
                               placeholder="Enter tracking number (e.g., GS260401ABCDEF)"
                               value="<?= e($tracking) ?>" required>
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-search me-1"></i> Track
                        </button>
                    </form>
                </div>
            </div>

            <div id="trackResult" class="d-none">
                <!-- Parcel Info -->
                <div class="card border-0 shadow-sm mb-3" id="parcelInfo"></div>

                <!-- Timeline -->
                <div class="card border-0 shadow-sm" id="timelineCard">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Tracking Timeline</h6>
                    </div>
                    <div class="card-body p-4" id="timeline"></div>
                </div>
            </div>

            <div id="trackError" class="alert alert-danger d-none"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('trackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const tracking = document.getElementById('trackingInput').value.trim();
    if (!tracking) return;
    trackParcel(tracking);
});

<?php if ($tracking): ?>
window.addEventListener('DOMContentLoaded', () => trackParcel(<?= json_encode($tracking) ?>));
<?php endif; ?>

function trackParcel(tracking) {
    document.getElementById('trackError').classList.add('d-none');
    document.getElementById('trackResult').classList.add('d-none');

    fetch(`/api/parcels.php?action=track&tracking=${encodeURIComponent(tracking)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Parcel not found');

            const p = data.parcel;
            const statusColors = { pending: 'warning', processing: 'info', in_transit: 'primary', delivered: 'success', cancelled: 'danger' };
            const color = statusColors[p.status] || 'secondary';

            document.getElementById('parcelInfo').innerHTML = `
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="text-muted small mb-1">TRACKING NUMBER</h6>
                            <h5 class="fw-bold font-monospace">${p.tracking_number}</h5>
                        </div>
                        <div class="col-md-3 text-center">
                            <span class="badge bg-${color} fs-6 px-3 py-2">${p.status.replace('_',' ').toUpperCase()}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <small class="text-muted">${p.speed?.toUpperCase() ?? ''} SHIPPING</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="mb-0 text-muted small">FROM</p>
                            <strong>${p.from_city ?? '—'}, ${p.from_country ?? ''}</strong>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-arrow-right fs-4 text-primary"></i>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="mb-0 text-muted small">TO</p>
                            <strong>${p.to_city ?? '—'}, ${p.to_country ?? ''}</strong>
                        </div>
                    </div>
                    <div class="row g-3 mt-2 text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Weight</small>
                            <strong>${p.weight} kg</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Insurance</small>
                            <strong>${p.insured ? '✅ Yes' : 'No'}</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Created</small>
                            <strong>${new Date(p.created_at).toLocaleDateString()}</strong>
                        </div>
                    </div>
                </div>`;

            const events = data.timeline || [];
            if (events.length > 0) {
                const evHtml = events.map((ev, i) => `
                    <div class="d-flex gap-3 ${i < events.length-1 ? 'mb-3' : ''}">
                        <div class="d-flex flex-column align-items-center">
                            <div class="rounded-circle bg-primary" style="width:12px;height:12px;margin-top:4px;flex-shrink:0"></div>
                            ${i < events.length-1 ? '<div class="flex-grow-1" style="width:2px;background:#dee2e6;margin:2px auto"></div>' : ''}
                        </div>
                        <div class="flex-grow-1 pb-2">
                            <div class="fw-semibold">${ev.event_type ?? ev.status ?? 'Update'}</div>
                            <div class="text-muted small">${ev.description ?? ''}</div>
                            <div class="text-muted small">${ev.location ?? ''} · ${new Date(ev.created_at).toLocaleString()}</div>
                        </div>
                    </div>`).join('');
                document.getElementById('timeline').innerHTML = evHtml;
            } else {
                document.getElementById('timeline').innerHTML = '<p class="text-muted mb-0">No tracking events yet. Check back soon.</p>';
            }

            document.getElementById('trackResult').classList.remove('d-none');
        })
        .catch(err => {
            document.getElementById('trackError').textContent = err.message;
            document.getElementById('trackError').classList.remove('d-none');
        });
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
