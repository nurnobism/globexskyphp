<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$pageTitle = 'AI Business Insights';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --brand-orange: #FF6B35; --brand-dark: #1B2A4A; }
    .insights-hero { background: linear-gradient(135deg, var(--brand-dark) 0%, #2d4070 100%); }
    .insight-card  { border-radius: 16px; border: none; transition: transform .2s, box-shadow .2s; }
    .insight-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
    .rec-card      { border-radius: 12px; border-left: 4px solid var(--brand-orange);
                     background: #fff8f5; }
    .growth-card   { border-radius: 12px; border-left: 4px solid #1B2A4A; background: #f0f3fa; }
    .stat-pill     { background: rgba(255,107,53,.1); color: var(--brand-orange);
                     border-radius: 20px; padding: .35rem 1rem; font-size: .82rem; font-weight: 600; }
    .trend-up   { color: #198754; }
    .trend-down { color: #dc3545; }
    .trend-stable { color: #6c757d; }
    .period-btn.active { background: var(--brand-orange); color: #fff; border-color: var(--brand-orange); }
    .skeleton { background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);
                background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:10px; }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
</style>

<!-- Hero -->
<div class="insights-hero text-white py-4">
    <div class="container">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-lightbulb-fill text-warning me-2"></i>AI Business Insights</h3>
                <p class="mb-0 text-white-75 small">Sales trends, revenue predictions & strategic recommendations</p>
            </div>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <a href="<?= APP_URL ?>/pages/ai/index.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> AI Hub
                </a>
                <a href="<?= APP_URL ?>/pages/ai/analytics.php" class="btn btn-warning btn-sm fw-bold rounded-pill">
                    <i class="bi bi-bar-chart me-1"></i> Full Analytics
                </a>
            </div>
        </div>

        <!-- Period Selector -->
        <div class="d-flex gap-2 mt-3">
            <?php foreach ([7 => '7 Days', 30 => '30 Days', 90 => '90 Days', 365 => '1 Year'] as $d => $label): ?>
            <button class="btn btn-outline-light btn-sm rounded-pill period-btn <?= $d === 30 ? 'active' : '' ?>"
                    data-days="<?= $d ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container-fluid py-4" style="max-width:1400px;">

    <!-- Loading skeleton -->
    <div id="loadingState">
        <div class="row g-4 mb-4">
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="skeleton" style="height:120px;"></div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="row g-4">
            <div class="col-lg-7"><div class="skeleton" style="height:320px;"></div></div>
            <div class="col-lg-5"><div class="skeleton" style="height:320px;"></div></div>
        </div>
    </div>

    <!-- Content (hidden until loaded) -->
    <div id="insightsContent" class="d-none">

        <!-- Summary KPIs -->
        <div class="row g-3 mb-4" id="kpiRow"></div>

        <!-- AI Summary Banner -->
        <div class="card border-0 rounded-4 mb-4" id="salesTrendCard" style="background:linear-gradient(135deg,rgba(255,107,53,.08),rgba(27,42,74,.05));">
            <div class="card-body p-4">
                <div class="d-flex gap-3 align-items-start">
                    <div style="font-size:2.5rem;">📊</div>
                    <div>
                        <h6 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow text-warning me-1"></i>Sales Trend</h6>
                        <p class="mb-0 text-muted" id="salesTrendText">Loading…</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Prediction -->
        <div class="row g-4 mb-4">
            <div class="col-lg-5">
                <div class="card insight-card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-currency-dollar text-success me-2"></i>Revenue Prediction
                        </h6>
                        <div class="text-center py-3">
                            <div class="text-muted small mb-1">Next Period Estimate</div>
                            <div class="display-6 fw-bold" style="color:var(--brand-orange);" id="revPrediction">
                                <span class="spinner-border spinner-border-sm"></span>
                            </div>
                        </div>
                        <hr>
                        <div class="row g-2 text-center" id="comparisonStats">
                            <!-- Filled by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card insight-card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-steps text-primary me-2"></i>Period Overview</h6>
                        <canvas id="miniRevenueChart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations & Growth -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card insight-card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-check2-circle text-warning me-2"></i>Actionable Recommendations
                        </h6>
                        <div id="recommendationsList" class="d-flex flex-column gap-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card insight-card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-rocket-takeoff-fill text-primary me-2"></i>Growth Suggestions
                        </h6>
                        <div id="growthList" class="d-flex flex-column gap-3"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Error state -->
    <div id="errorState" class="d-none text-center py-5">
        <i class="bi bi-exclamation-circle text-danger" style="font-size:3rem;"></i>
        <h6 class="mt-3 text-muted" id="errorMsg">Unable to load insights.</h6>
        <button class="btn btn-outline-primary rounded-pill mt-2" onclick="loadInsights(currentDays)">Retry</button>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const INSIGHTS_URL = '<?= APP_URL ?>/api/ai/insights.php';
let currentDays = 30, miniChart = null;

function formatMoney(n) {
    return '$' + parseFloat(n).toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2});
}

function renderKPIs(summary) {
    const change = summary.revenue_change_pct;
    const changeHtml = change > 0
        ? `<span class="trend-up small"><i class="bi bi-arrow-up-right"></i> ${change}%</span>`
        : change < 0
        ? `<span class="trend-down small"><i class="bi bi-arrow-down-left"></i> ${Math.abs(change)}%</span>`
        : `<span class="trend-stable small">→ Stable</span>`;

    const kpis = [
        {label:'Revenue', value: formatMoney(summary.current_revenue), sub: changeHtml, icon:'currency-dollar', color:'#FF6B35', bg:'rgba(255,107,53,.1)'},
        {label:'Orders',  value: summary.current_orders, sub:`${summary.previous_orders} prev`, icon:'bag-fill', color:'#1B2A4A', bg:'rgba(27,42,74,.1)'},
        {label:'New Users', value: summary.new_users, sub:'in period', icon:'people-fill', color:'#0d6efd', bg:'rgba(13,110,253,.1)'},
        {label:'Conversion', value: summary.conversion_rate+'%', sub:'orders/users', icon:'graph-up-arrow', color:'#198754', bg:'rgba(25,135,84,.1)'},
    ];

    document.getElementById('kpiRow').innerHTML = kpis.map(k => `
        <div class="col-sm-6 col-xl-3">
            <div class="card insight-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:${k.bg};color:${k.color};
                                display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
                        <i class="bi bi-${k.icon}"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1">${k.value}</div>
                        <div class="text-muted" style="font-size:.77rem;">${k.label}</div>
                        <div>${k.sub}</div>
                    </div>
                </div>
            </div>
        </div>`).join('');
}

function renderRecommendations(recs, el) {
    el.innerHTML = recs.map((r, i) => `
        <div class="rec-card p-3 d-flex gap-3 align-items-start">
            <span class="fw-bold" style="color:var(--brand-orange);font-size:1.1rem;">${i+1}.</span>
            <span class="small">${r}</span>
        </div>`).join('') || '<p class="text-muted small">No recommendations available.</p>';
}

function renderGrowth(suggestions, el) {
    el.innerHTML = suggestions.map((s, i) => `
        <div class="growth-card p-3 d-flex gap-3 align-items-start">
            <i class="bi bi-arrow-right-circle-fill mt-1" style="color:#1B2A4A;flex-shrink:0;"></i>
            <span class="small">${s}</span>
        </div>`).join('') || '<p class="text-muted small">No suggestions available.</p>';
}

function renderMiniChart(dailyRevenue) {
    const labels  = dailyRevenue.map(d => d.day);
    const revenue = dailyRevenue.map(d => parseFloat(d.revenue));

    if (miniChart) miniChart.destroy();
    miniChart = new Chart(document.getElementById('miniRevenueChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Revenue',
                data: revenue,
                borderColor: '#FF6B35',
                backgroundColor: 'rgba(255,107,53,.12)',
                borderWidth: 2,
                pointRadius: 0,
                fill: true,
                tension: 0.4,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 7, font: {size:10} }, grid: { display: false } },
                y: { ticks: { callback: v => '$'+v.toLocaleString(), font:{size:10} } }
            }
        }
    });
}

async function loadInsights(days) {
    currentDays = days;
    document.querySelectorAll('.period-btn').forEach(b => {
        b.classList.toggle('active', parseInt(b.dataset.days) === days);
    });
    document.getElementById('loadingState').classList.remove('d-none');
    document.getElementById('insightsContent').classList.add('d-none');
    document.getElementById('errorState').classList.add('d-none');

    try {
        const res  = await fetch(`${INSIGHTS_URL}?days=${days}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed');

        const { summary, insights } = data;

        renderKPIs(summary);
        document.getElementById('salesTrendText').textContent = insights.sales_trend;
        document.getElementById('revPrediction').textContent  = insights.revenue_prediction;

        document.getElementById('comparisonStats').innerHTML = `
            <div class="col-6">
                <div class="text-muted small">Current</div>
                <div class="fw-bold">${formatMoney(summary.current_revenue)}</div>
            </div>
            <div class="col-6">
                <div class="text-muted small">Previous</div>
                <div class="fw-bold">${formatMoney(summary.previous_revenue)}</div>
            </div>`;

        renderRecommendations(insights.recommendations, document.getElementById('recommendationsList'));
        renderGrowth(insights.growth_suggestions, document.getElementById('growthList'));
        renderMiniChart(summary.daily_revenue || []);

        document.getElementById('loadingState').classList.add('d-none');
        document.getElementById('insightsContent').classList.remove('d-none');

    } catch (err) {
        document.getElementById('loadingState').classList.add('d-none');
        document.getElementById('errorMsg').textContent = err.message;
        document.getElementById('errorState').classList.remove('d-none');
    }
}

document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', () => loadInsights(parseInt(btn.dataset.days)));
});

loadInsights(30);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
