<?php
require_once __DIR__ . '/../../../includes/middleware.php';

$pageTitle = 'Shipping Cost Calculator';
include __DIR__ . '/../../../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Shipping Cost Calculator</h5>
                </div>
                <div class="card-body p-4">
                    <form id="calcForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">From Country *</label>
                                <input type="text" id="fromCountry" class="form-control" placeholder="e.g., United States" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">To Country *</label>
                                <input type="text" id="toCountry" class="form-control" placeholder="e.g., United Kingdom" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Weight (kg) *</label>
                                <input type="number" id="weight" class="form-control" min="0.1" step="0.1" placeholder="kg" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Length (cm)</label>
                                <input type="number" id="length" class="form-control" min="0" step="0.1" placeholder="cm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Width (cm)</label>
                                <input type="number" id="width" class="form-control" min="0" step="0.1" placeholder="cm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Height (cm)</label>
                                <input type="number" id="height" class="form-control" min="0" step="0.1" placeholder="cm">
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="calculateCost()">
                            <i class="bi bi-calculator me-1"></i> Calculate Rates
                        </button>
                    </form>

                    <!-- Results -->
                    <div id="results" class="mt-4 d-none">
                        <hr>
                        <h6 class="fw-bold mb-3">Shipping Options</h6>
                        <div class="row g-3" id="rateCards"></div>
                        <div class="mt-3 p-3 bg-light rounded small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Rates are estimates. Final price is confirmed at shipment creation.
                            Billable weight: <strong id="billableWeight"></strong> kg
                            (Volumetric: <strong id="volumetricWeight"></strong> kg)
                        </div>
                    </div>

                    <div id="calcError" class="alert alert-danger mt-3 d-none"></div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-question-circle me-2 text-primary"></i>How It Works</h6>
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <i class="bi bi-box display-6 text-primary mb-2"></i>
                            <h6>Measure Your Package</h6>
                            <p class="text-muted small">Enter the weight and dimensions of your parcel.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-calculator display-6 text-success mb-2"></i>
                            <h6>Get Instant Rates</h6>
                            <p class="text-muted small">We calculate the best rate based on billable weight.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-truck display-6 text-warning mb-2"></i>
                            <h6>Ship &amp; Track</h6>
                            <p class="text-muted small">Create your shipment and track it in real time.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculateCost() {
    const weight  = parseFloat(document.getElementById('weight').value) || 0;
    const length  = parseFloat(document.getElementById('length').value) || 0;
    const width   = parseFloat(document.getElementById('width').value) || 0;
    const height  = parseFloat(document.getElementById('height').value) || 0;
    const from    = document.getElementById('fromCountry').value.trim();
    const to      = document.getElementById('toCountry').value.trim();

    if (!weight || !from || !to) {
        document.getElementById('calcError').textContent = 'Please enter weight, origin and destination.';
        document.getElementById('calcError').classList.remove('d-none');
        return;
    }
    document.getElementById('calcError').classList.add('d-none');

    const params = new URLSearchParams({ weight, length, width, height, from_country: from, to_country: to, speed: 'standard' });
    fetch(`/api/parcels.php?action=calculate&${params}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error);
            document.getElementById('billableWeight').textContent = data.billable_weight;
            document.getElementById('volumetricWeight').textContent = data.volumetric_weight;

            const speeds = [
                { speed: 'standard', label: 'Standard', days: '5-7 days', icon: 'truck', color: 'secondary', multiplier: 1.0 },
                { speed: 'express',  label: 'Express',  days: '2-3 days', icon: 'lightning', color: 'warning', multiplier: 1.6 },
                { speed: 'priority', label: 'Priority', days: '1-2 days', icon: 'rocket', color: 'danger', multiplier: 2.2 },
            ];
            const cards = speeds.map(s => {
                const price = (data.base_price * s.multiplier).toFixed(2);
                return `<div class="col-md-4">
                    <div class="card border-${s.color} h-100">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-${s.icon} text-${s.color} fs-3 mb-2"></i>
                            <h6 class="fw-bold">${s.label}</h6>
                            <p class="text-muted small mb-2">${s.days}</p>
                            <h4 class="fw-bold text-${s.color}">$${price}</h4>
                            <a href="/pages/shipment/parcel/create.php" class="btn btn-${s.color} btn-sm mt-2 w-100">Ship Now</a>
                        </div>
                    </div>
                </div>`;
            });
            document.getElementById('rateCards').innerHTML = cards.join('');
            document.getElementById('results').classList.remove('d-none');
        })
        .catch(err => {
            document.getElementById('calcError').textContent = 'Error: ' + err.message;
            document.getElementById('calcError').classList.remove('d-none');
        });
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
