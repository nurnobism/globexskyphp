<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAdmin();

$pageTitle = 'AI Analytics Dashboard';
include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root { --brand-orange: #FF6B35; --brand-dark: #1B2A4A; }
    .analytics-hero { background: linear-gradient(135deg, var(--brand-dark) 0%, #2d4070 100%); }
    .ask-ai-bar     { border-radius: 50px; border: 2px solid transparent; background: #fff;
                      transition: border-color .25s, box-shadow .25s; }
    .ask-ai-bar:focus-within { border-color: var(--brand-orange); box-shadow: 0 0 0 3px rgba(255,107,53,.15); }
    .ask-ai-bar input { border: none; background: transparent; font-size: .95rem;
                        padding: .65rem 1.25rem; }
    .ask-ai-bar input:focus { outline: none; box-shadow: none; }
    .chart-card     { border-radius: 16px; border: none; }
    .commentary-box { border-radius: 14px; border-left: 4px solid var(--brand-orange);
                      background: linear-gradient(135deg, #fff8f5, #fff); }
    .period-btn.active { background: var(--brand-orange); color: #fff; border-color: var(--brand-orange); }
    .ai-response    { border-radius: 14px; background: rgba(27,42,74,.04);
                      border: 1px solid rgba(27,42,74,.1); }
    .skeleton       { background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);
                      background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:10px; }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
    .chart-explanation { font-size: .83rem; color: #555; }
</style>

<!-- Hero -->
<div class="analytics-hero text-white py-4 pb-5">
    <div class="container">
        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-bar-chart-fill text-warning me-2"></i>AI Analytics Dashboard</h3>
                <p class="mb-0 text-white-75 small">Ask your data anything in plain English · AI explains every chart</p>
            </div>
            <a href="<?= APP_URL ?>/pages/ai/index.php" class="btn btn-outline-light btn-sm rounded-pill ms-auto">
                <i class="bi bi-arrow-left me-1"></i> AI Hub
            </a>
        </div>

        <!-- Ask AI -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="ask-ai-bar d-flex align-items-center pe-2 py-1">
                    <span class="ps-3 text-muted"><i class="bi bi-chat-square-text-fill" style="color:var(--brand-orange);"></i></span>
                    <input type="text" id="aiQuestion" class="form-control flex-grow-1"
                           placeholder='Ask: "What drove revenue growth this month?" or "Which category declined?"'
                           maxlength="500">
                    <button class="btn text-white px-3 ms-1 rounded-pill" id="askAiBtn"
                            style="background:var(--brand-orange);">
                        <i class="bi bi-send-fill me-1"></i> Ask AI
                    </button>
                </div>
            </div>
        </div>

        <!-- Period selector -->
        <div class="d-flex gap-2 justify-content-center mt-3">
            <?php foreach ([7 => '7D', 30 => '30D', 90 => '90D', 365 => '1Y'] as $d => $label): ?>
            <button class="btn btn-outline-light btn-sm rounded-pill period-btn <?= $d === 30 ? 'active' : '' ?>"
                    data-days="<?= $d ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container-fluid py-4" style="max-width:1400px; margin-top:-30px;">

    <!-- AI Commentary Card -->
    <div class="card border-0 shadow commentary-box mb-4" id="commentaryCard">
        <div class="card-body p-4">
            <div class="d-flex gap-3 align-items-start">
                <div style="font-size:2rem;flex-shrink:0;">🤖</div>
                <div class="flex-grow-1">
                    <div class="fw-bold mb-1">
                        <i class="bi bi-cpu-fill text-warning me-1"></i>AI Commentary
                        <span class="badge ms-2 rounded-pill" style="background:rgba(255,107,53,.15);color:var(--brand-orange);font-size:.7rem;">
                            DeepSeek AI
                        </span>
                    </div>
                    <div id="commentaryText" class="text-muted">
                        <div class="d-flex gap-2 align-items-center">
                            <span class="spinner-border spinner-border-sm text-warning"></span>
                            Analysing your data…
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Question Answer -->
    <div class="card border-0 shadow ai-response mb-4 d-none" id="aiAnswerCard">
        <div class="card-body p-4">
            <div class="d-flex gap-3">
                <div style="font-size:1.5rem;flex-shrink:0;">💬</div>
                <div>
                    <div class="fw-bold mb-1 small text-muted" id="aiQuestionDisplay"></div>
                    <div id="aiAnswerText" class="text-dark"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="row g-4" id="chartsGrid">

        <!-- Sales Chart placeholder -->
        <div class="col-lg-8">
            <div class="card chart-card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-graph-up text-warning me-2"></i>Daily Orders & Revenue</h6>
                        <span class="badge rounded-pill bg-light text-dark small" id="salesChartBadge">
                            <span class="spinner-border spinner-border-sm"></span>
                        </span>
                    </div>
                    <canvas id="salesChart" height="80"></canvas>
                    <div class="chart-explanation mt-2 p-2 rounded-3 bg-light d-none" id="salesExplanation"></div>
                </div>
            </div>
        </div>

        <!-- Category Chart -->
        <div class="col-lg-4">
            <div class="card chart-card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Revenue by Category</h6>
                        <span class="badge rounded-pill bg-light text-dark small" id="catChartBadge">
                            <span class="spinner-border spinner-border-sm"></span>
                        </span>
                    </div>
                    <canvas id="categoryChart" height="150"></canvas>
                    <div class="chart-explanation mt-2 p-2 rounded-3 bg-light d-none" id="categoryExplanation"></div>
                </div>
            </div>
        </div>

        <!-- Weekly Bar Chart -->
        <div class="col-lg-6">
            <div class="card chart-card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill text-success me-2"></i>Weekly Order Volume</h6>
                        <span class="badge rounded-pill bg-light text-dark small" id="weeklyChartBadge">
                            <span class="spinner-border spinner-border-sm"></span>
                        </span>
                    </div>
                    <canvas id="weeklyChart" height="100"></canvas>
                    <div class="chart-explanation mt-2 p-2 rounded-3 bg-light d-none" id="weeklyExplanation"></div>
                </div>
            </div>
        </div>

        <!-- Quick Metrics Table -->
        <div class="col-lg-6">
            <div class="card chart-card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-table text-danger me-2"></i>Category Revenue Breakdown</h6>
                    <div class="table-responsive" id="categoryTable">
                        <div class="text-center py-3">
                            <span class="spinner-border spinner-border-sm text-warning"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Error state -->
    <div id="errorState" class="d-none text-center py-5">
        <i class="bi bi-exclamation-circle text-danger" style="font-size:3rem;"></i>
        <h6 class="mt-3" id="errorMsg">Failed to load analytics.</h6>
        <button class="btn btn-outline-primary rounded-pill mt-2" onclick="loadAnalytics(currentPeriod)">Retry</button>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ANALYTICS_URL = '<?= APP_URL ?>/api/ai/analytics.php';
let currentPeriod   = 30;
let salesChartInst  = null, catChartInst = null, weeklyChartInst = null;

const CHART_COLORS = [
    '#FF6B35','#1B2A4A','#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0'
];

function showExplanation(id, text) {
    const el = document.getElementById(id);
    if (!text) return;
    el.classList.remove('d-none');
    el.innerHTML = `<i class="bi bi-cpu-fill text-warning me-1"></i><em>${text}</em>`;
}

function renderSalesChart(chart) {
    if (salesChartInst) salesChartInst.destroy();
    const data = chart.data;
    salesChartInst = new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Revenue ($)',
                    data: data.revenue,
                    borderColor: '#FF6B35',
                    backgroundColor: 'rgba(255,107,53,.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    yAxisID: 'y',
                },
                {
                    label: 'Orders',
                    data: data.orders,
                    borderColor: '#1B2A4A',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5,3],
                    tension: 0.4,
                    pointRadius: 0,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            plugins: { legend: { position: 'top', labels: { font: {size:11} } } },
            scales: {
                x:  { ticks: { maxTicksLimit: 10, font:{size:10} }, grid: { display: false } },
                y:  { ticks: { callback: v => '$'+v.toLocaleString(), font:{size:10} }, position: 'left' },
                y1: { ticks: { font:{size:10} }, position: 'right', grid: { drawOnChartArea: false } },
            }
        }
    });
    document.getElementById('salesChartBadge').textContent = data.labels.length + ' days';
    showExplanation('salesExplanation', chart.ai_explanation);
}

function renderCategoryChart(chart) {
    if (catChartInst) catChartInst.destroy();
    const data = chart.data;
    catChartInst = new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.revenue,
                backgroundColor: CHART_COLORS,
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom', labels: { font:{size:10}, boxWidth:12 } }
            },
            cutout: '60%',
        }
    });
    document.getElementById('catChartBadge').textContent = data.labels.length + ' categories';
    showExplanation('categoryExplanation', chart.ai_explanation);

    // Category table
    const total = data.revenue.reduce((a,b) => a+b, 0) || 1;
    const rows  = data.labels.map((l, i) => `
        <tr>
            <td><span class="me-2" style="color:${CHART_COLORS[i]};font-size:1.1rem;">■</span>${l}</td>
            <td class="fw-bold">$${parseFloat(data.revenue[i]).toLocaleString('en-US',{minimumFractionDigits:0})}</td>
            <td>
                <div class="progress" style="height:6px;border-radius:3px;">
                    <div class="progress-bar" style="width:${(data.revenue[i]/total*100).toFixed(1)}%;background:${CHART_COLORS[i]};"></div>
                </div>
            </td>
        </tr>`).join('');

    document.getElementById('categoryTable').innerHTML = `
        <table class="table table-sm small mb-0">
            <thead><tr><th>Category</th><th>Revenue</th><th>Share</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
}

function renderWeeklyChart(chart) {
    if (weeklyChartInst) weeklyChartInst.destroy();
    const data = chart.data;
    weeklyChartInst = new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Orders',
                data: data.orders,
                backgroundColor: 'rgba(255,107,53,.75)',
                borderColor: '#FF6B35',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font:{size:10} }, grid: { display: false } },
                y: { ticks: { font:{size:10} } }
            }
        }
    });
    document.getElementById('weeklyChartBadge').textContent = data.labels.length + ' weeks';
    showExplanation('weeklyExplanation', chart.ai_explanation);
}

async function loadAnalytics(period, question = '') {
    currentPeriod = period;
    document.querySelectorAll('.period-btn').forEach(b => {
        b.classList.toggle('active', parseInt(b.dataset.days) === period);
    });

    const url  = `${ANALYTICS_URL}?period=${period}` + (question ? `&question=${encodeURIComponent(question)}` : '');

    // Show loading states
    document.getElementById('commentaryText').innerHTML =
        '<div class="d-flex gap-2 align-items-center"><span class="spinner-border spinner-border-sm text-warning"></span> Analysing your data…</div>';
    document.getElementById('errorState').classList.add('d-none');

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'API error');

        // Commentary
        document.getElementById('commentaryText').innerHTML =
            data.commentary.replace(/\n/g, '<br>') || 'No commentary available.';

        // AI answer to question
        if (question && data.commentary) {
            document.getElementById('aiAnswerCard').classList.remove('d-none');
            document.getElementById('aiQuestionDisplay').textContent = '❓ ' + question;
            document.getElementById('aiAnswerText').innerHTML = data.commentary.replace(/\n/g,'<br>');
        }

        // Render charts
        const charts = data.charts || [];
        charts.forEach(chart => {
            if (chart.id === 'salesChart')    renderSalesChart(chart);
            if (chart.id === 'categoryChart') renderCategoryChart(chart);
            if (chart.id === 'weeklyChart')   renderWeeklyChart(chart);
        });

    } catch (err) {
        document.getElementById('commentaryText').innerHTML = '⚠️ Could not load AI commentary.';
        document.getElementById('errorMsg').textContent = err.message;
        document.getElementById('errorState').classList.remove('d-none');
    }
}

// Ask AI
document.getElementById('askAiBtn').addEventListener('click', () => {
    const q = document.getElementById('aiQuestion').value.trim();
    if (!q) { document.getElementById('aiQuestion').focus(); return; }
    loadAnalytics(currentPeriod, q);
});

document.getElementById('aiQuestion').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('askAiBtn').click();
});

// Period buttons
document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', () => loadAnalytics(parseInt(btn.dataset.days)));
});

// Initial load
loadAnalytics(30);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
