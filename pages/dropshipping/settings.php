<?php
/**
 * pages/dropshipping/settings.php — Dropshipping Settings
 */
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$db     = getDB();
$userId = $_SESSION['user_id'];

// Fetch categories for the table
$catStmt    = $db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name');
$categories = $catStmt->fetchAll();

// Fetch existing markup rules
$rulesStmt = $db->query('
    SELECT mr.*, c.name AS category_name
    FROM dropship_markup_rules mr
    LEFT JOIN categories c ON c.id = mr.category_id
    ORDER BY c.name
');
$markupRules = $rulesStmt->fetchAll();

// Fetch user preferences
$prefStmt = $db->prepare('SELECT * FROM dropship_preferences WHERE user_id = ?');
$prefStmt->execute([$userId]);
$prefs = $prefStmt->fetch() ?: [
    'global_markup_pct' => 50,
    'auto_sync'         => 0,
    'sync_interval_hrs' => 24,
    'notify_stock_out'  => 1,
    'notify_price_chg'  => 1,
];

$pageTitle = 'Dropshipping Settings';
include __DIR__ . '/../../includes/header.php';
?>

<style>
  :root { --ds-primary: #FF6B35; --ds-secondary: #1B2A4A; }
  .settings-nav .nav-link { color: var(--ds-secondary); border-radius: 8px; }
  .settings-nav .nav-link.active { background: var(--ds-primary); color: #fff; }
  .settings-nav .nav-link:hover:not(.active) { background: #f5f5f5; }
  .section-title { border-left: 4px solid var(--ds-primary); padding-left: .75rem; color: var(--ds-secondary); }
  .toggle-label { font-size:.9rem; }
  .tier-row td { vertical-align: middle; }
</style>

<div class="container-fluid py-4 px-4">

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0" style="color:var(--ds-secondary)">
        <i class="bi bi-gear me-2" style="color:var(--ds-primary)"></i>Dropshipping Settings
      </h4>
      <small class="text-muted">Configure your pricing rules, sync preferences, and notifications</small>
    </div>
    <a href="/pages/dropshipping/dashboard.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
  </div>

  <div class="row g-4">

    <!-- Nav Tabs (sidebar on wide screens) -->
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-2">
          <nav class="nav flex-column settings-nav gap-1" id="settingsTabs">
            <a class="nav-link active px-3 py-2" href="#global"    data-tab>
              <i class="bi bi-sliders me-2"></i>Global Markup
            </a>
            <a class="nav-link px-3 py-2" href="#category" data-tab>
              <i class="bi bi-tags me-2"></i>Category Rules
            </a>
            <a class="nav-link px-3 py-2" href="#tiers"    data-tab>
              <i class="bi bi-layers me-2"></i>Price Tiers
            </a>
            <a class="nav-link px-3 py-2" href="#sync"     data-tab>
              <i class="bi bi-arrow-repeat me-2"></i>Auto-Sync
            </a>
          </nav>
        </div>
      </div>
    </div>

    <!-- Settings Panels -->
    <div class="col-lg-9">

      <!-- Alert placeholder -->
      <div id="alertBox" class="d-none mb-3"></div>

      <!-- 1. Global Markup -->
      <div class="card border-0 shadow-sm mb-4" id="panel-global">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-4">
          <h6 class="section-title fw-bold mb-0">Global Markup Percentage</h6>
        </div>
        <div class="card-body px-4">
          <form id="formGlobal">
            <?= csrfField() ?>
            <p class="text-muted small mb-3">
              This markup applies to all imported products unless overridden by a category rule.
            </p>
            <div class="row align-items-end g-3">
              <div class="col-md-5">
                <label class="form-label fw-semibold">Global Markup
                  <span class="text-muted fw-normal" id="globalMarkupDisplay">
                    (<?= $prefs['global_markup_pct'] ?>%)
                  </span>
                </label>
                <input type="range" class="form-range mb-2" min="0" max="200" step="5"
                       name="global_markup_pct" id="globalMarkupSlider"
                       value="<?= (int)$prefs['global_markup_pct'] ?>">
                <div class="input-group input-group-sm">
                  <input type="number" class="form-control" name="global_markup_pct_num"
                         id="globalMarkupNum" min="0" max="1000" step="0.5"
                         value="<?= (float)$prefs['global_markup_pct'] ?>">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-md-7">
                <div class="alert alert-light border mb-0 small">
                  <strong>Example:</strong> Cost $10.00 → Sell at
                  $<span id="examplePrice"><?= number_format(10 * (1 + $prefs['global_markup_pct'] / 100), 2) ?></span>
                  &nbsp;(<span id="exampleMarkup"><?= $prefs['global_markup_pct'] ?></span>% markup)
                </div>
              </div>
            </div>
            <hr>
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch"
                         name="notify_stock_out" id="notifyStock"
                         <?= $prefs['notify_stock_out'] ? 'checked' : '' ?>>
                  <label class="form-check-label toggle-label" for="notifyStock">
                    Notify on stock-out
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch"
                         name="notify_price_chg" id="notifyPrice"
                         <?= $prefs['notify_price_chg'] ? 'checked' : '' ?>>
                  <label class="form-check-label toggle-label" for="notifyPrice">
                    Notify on price change
                  </label>
                </div>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn text-white px-4"
                      style="background:var(--ds-primary)">
                <i class="bi bi-save me-1"></i>Save Preferences
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 2. Category Markup Rules -->
      <div class="card border-0 shadow-sm mb-4" id="panel-category">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-4 d-flex justify-content-between align-items-center">
          <h6 class="section-title fw-bold mb-0">Category Markup Rules</h6>
          <?php if ($isAdmin): ?>
          <button class="btn btn-sm text-white" style="background:var(--ds-primary)"
                  data-bs-toggle="collapse" data-bs-target="#addCategoryRule">
            <i class="bi bi-plus-circle me-1"></i>Add Rule
          </button>
          <?php endif; ?>
        </div>
        <div class="card-body px-4">
          <!-- Add rule form (admin only) -->
          <?php if ($isAdmin): ?>
          <div class="collapse mb-3" id="addCategoryRule">
            <div class="card card-body bg-light border-0">
              <form id="formCategoryRule">
                <?= csrfField() ?>
                <div class="row g-2 align-items-end">
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Category</label>
                    <select name="category_id" class="form-select form-select-sm">
                      <option value="">— All categories —</option>
                      <?php foreach ($categories as $cat): ?>
                      <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small fw-semibold">Markup %</label>
                    <div class="input-group input-group-sm">
                      <input type="number" name="markup_pct" class="form-control" min="0" max="1000"
                             step="0.5" placeholder="50" required>
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small fw-semibold">Min Price</label>
                    <input type="number" name="min_price" class="form-control form-control-sm"
                           min="0" step="0.01" placeholder="0.00">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small fw-semibold">Max Price</label>
                    <input type="number" name="max_price" class="form-control form-control-sm"
                           min="0" step="0.01" placeholder="Any">
                  </div>
                  <div class="col-md-1 text-end">
                    <button type="submit" class="btn btn-sm text-white w-100"
                            style="background:var(--ds-secondary)">
                      Save
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div><!-- /#addCategoryRule -->
          <?php endif; ?>

          <!-- Existing rules table -->
          <?php if (empty($markupRules)): ?>
            <p class="text-muted small text-center py-3">No category rules set. Global markup applies to all products.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Category</th>
                  <th>Markup %</th>
                  <th>Min Price</th>
                  <th>Max Price</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($markupRules as $rule): ?>
                <tr class="tier-row">
                  <td class="fw-semibold"><?= e($rule['category_name'] ?? 'All Categories') ?></td>
                  <td>
                    <span class="badge text-white" style="background:var(--ds-primary)">
                      <?= $rule['markup_pct'] ?>%
                    </span>
                  </td>
                  <td class="text-muted small"><?= $rule['min_price'] > 0 ? formatMoney($rule['min_price']) : '—' ?></td>
                  <td class="text-muted small"><?= $rule['max_price'] ? formatMoney($rule['max_price']) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 3. Price-Range Tiers -->
      <div class="card border-0 shadow-sm mb-4" id="panel-tiers">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-4">
          <h6 class="section-title fw-bold mb-0">Price-Range Markup Tiers</h6>
        </div>
        <div class="card-body px-4">
          <p class="text-muted small mb-3">
            Define different markup percentages based on the supplier cost price range.
            These override category rules when matched.
          </p>
          <form id="formTiers">
            <?= csrfField() ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle" id="tiersTable">
                <thead class="table-light">
                  <tr>
                    <th>Min Cost ($)</th>
                    <th>Max Cost ($)</th>
                    <th>Markup %</th>
                    <th></th>
                  <th class="visually-hidden">Remove</th>
                  </tr>
                </thead>
                <tbody id="tiersBody">
                  <tr class="tier-row">
                    <td><input type="number" class="form-control form-control-sm" name="tier_min[]" placeholder="0.00" min="0" step="0.01"></td>
                    <td><input type="number" class="form-control form-control-sm" name="tier_max[]" placeholder="9.99" min="0" step="0.01"></td>
                    <td><div class="input-group input-group-sm"><input type="number" class="form-control" name="tier_pct[]" placeholder="60" min="0" max="1000" step="0.5"><span class="input-group-text">%</span></div></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-tier"><i class="bi bi-trash"></i></button></td>
                  </tr>
                  <tr class="tier-row">
                    <td><input type="number" class="form-control form-control-sm" name="tier_min[]" placeholder="10.00" min="0" step="0.01"></td>
                    <td><input type="number" class="form-control form-control-sm" name="tier_max[]" placeholder="49.99" min="0" step="0.01"></td>
                    <td><div class="input-group input-group-sm"><input type="number" class="form-control" name="tier_pct[]" placeholder="40" min="0" max="1000" step="0.5"><span class="input-group-text">%</span></div></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-tier"><i class="bi bi-trash"></i></button></td>
                  </tr>
                  <tr class="tier-row">
                    <td><input type="number" class="form-control form-control-sm" name="tier_min[]" placeholder="50.00" min="0" step="0.01"></td>
                    <td><input type="number" class="form-control form-control-sm" name="tier_max[]" placeholder="" min="0" step="0.01"></td>
                    <td><div class="input-group input-group-sm"><input type="number" class="form-control" name="tier_pct[]" placeholder="25" min="0" max="1000" step="0.5"><span class="input-group-text">%</span></div></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-tier"><i class="bi bi-trash"></i></button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="addTierRow">
                <i class="bi bi-plus-circle me-1"></i>Add Tier
              </button>
              <button type="submit" class="btn btn-sm text-white" style="background:var(--ds-primary)">
                <i class="bi bi-save me-1"></i>Save Tiers
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 4. Auto-Sync -->
      <div class="card border-0 shadow-sm mb-4" id="panel-sync">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-4">
          <h6 class="section-title fw-bold mb-0">Auto-Sync Settings</h6>
        </div>
        <div class="card-body px-4">
          <form id="formSync">
            <?= csrfField() ?>
            <div class="row g-4">
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="autoSync"
                         name="auto_sync" <?= $prefs['auto_sync'] ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold toggle-label" for="autoSync">
                    Enable Auto-Sync
                  </label>
                  <div class="text-muted small mt-1">
                    Automatically sync product prices and inventory from suppliers.
                  </div>
                </div>

                <div id="syncOptions" class="<?= $prefs['auto_sync'] ? '' : 'opacity-50 pe-none' ?>">
                  <label class="form-label fw-semibold">Sync Interval</label>
                  <select name="sync_interval_hrs" class="form-select form-select-sm mb-3">
                    <option value="6"  <?= $prefs['sync_interval_hrs'] == 6  ? 'selected' : '' ?>>Every 6 hours</option>
                    <option value="12" <?= $prefs['sync_interval_hrs'] == 12 ? 'selected' : '' ?>>Every 12 hours</option>
                    <option value="24" <?= $prefs['sync_interval_hrs'] == 24 ? 'selected' : '' ?>>Once daily</option>
                    <option value="48" <?= $prefs['sync_interval_hrs'] == 48 ? 'selected' : '' ?>>Every 2 days</option>
                  </select>
                  <label class="form-label fw-semibold">Sync Scope</label>
                  <div class="d-flex flex-column gap-2">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="syncPrices" name="sync_prices" value="1" checked>
                      <label class="form-check-label small" for="syncPrices">Sync supplier prices</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="syncStock" name="sync_stock" value="1" checked>
                      <label class="form-check-label small" for="syncStock">Sync stock levels</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="syncImages" name="sync_images" value="1">
                      <label class="form-check-label small" for="syncImages">Sync product images</label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="card bg-light border-0 h-100 p-3">
                  <h6 class="fw-semibold mb-3">
                    <i class="bi bi-info-circle me-1" style="color:var(--ds-primary)"></i>Sync Status
                  </h6>
                  <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i>Last sync: <strong>Never</strong></li>
                    <li class="mb-2"><i class="bi bi-arrow-repeat me-1" style="color:var(--ds-primary)"></i>Next sync: <strong>—</strong></li>
                    <li><i class="bi bi-cloud me-1 text-muted"></i>Provider: <strong>GlobexSky Sync Engine</strong></li>
                  </ul>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-3" id="triggerSync">
                    <i class="bi bi-arrow-clockwise me-1"></i>Sync Now
                  </button>
                </div>
              </div>
            </div>

            <div class="mt-3">
              <button type="submit" class="btn text-white px-4"
                      style="background:var(--ds-primary)">
                <i class="bi bi-save me-1"></i>Save Sync Settings
              </button>
            </div>
          </form>
        </div>
      </div>

    </div><!-- /col settings panels -->
  </div><!-- /row -->
</div><!-- /container -->

<script>
const CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

// Tab navigation
document.querySelectorAll('[data-tab]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('[data-tab]').forEach(l => l.classList.remove('active'));
    link.classList.add('active');
    const target = link.getAttribute('href').replace('#', '');
    document.querySelectorAll('[id^="panel-"]').forEach(p => {
      p.style.display = p.id === 'panel-' + target ? '' : 'none';
    });
  });
});
// Show only first panel by default
document.querySelectorAll('[id^="panel-"]').forEach((p, i) => { if (i > 0) p.style.display = 'none'; });

// Global markup slider ↔ number input sync
const slider = document.getElementById('globalMarkupSlider');
const numIn  = document.getElementById('globalMarkupNum');
const display = document.getElementById('globalMarkupDisplay');
const exPrice = document.getElementById('examplePrice');
const exMark  = document.getElementById('exampleMarkup');

function updateMarkup(val) {
  const v = parseFloat(val) || 0;
  slider.value  = Math.min(v, 200);
  numIn.value   = v;
  display.textContent = '(' + v + '%)';
  exPrice.textContent = (10 * (1 + v / 100)).toFixed(2);
  exMark.textContent  = v;
}
slider.addEventListener('input', () => updateMarkup(slider.value));
numIn.addEventListener('input',  () => updateMarkup(numIn.value));

// Auto-sync toggle → enable/disable options
document.getElementById('autoSync').addEventListener('change', function() {
  const opts = document.getElementById('syncOptions');
  opts.classList.toggle('opacity-50', !this.checked);
  opts.classList.toggle('pe-none',    !this.checked);
});

// Add tier row
document.getElementById('addTierRow').addEventListener('click', () => {
  const tbody = document.getElementById('tiersBody');
  const row   = tbody.querySelector('.tier-row').cloneNode(true);
  row.querySelectorAll('input').forEach(inp => inp.value = '');
  tbody.appendChild(row);
});

// Remove tier row
document.getElementById('tiersBody').addEventListener('click', e => {
  if (e.target.closest('.remove-tier')) {
    const rows = document.querySelectorAll('.tier-row');
    if (rows.length > 1) e.target.closest('tr').remove();
  }
});

// Generic save handler
async function saveForm(formId, endpoint, successMsg) {
  const form   = document.getElementById(formId);
  const data   = new URLSearchParams(new FormData(form));
  data.set('csrf_token', CSRF);
  const res    = await fetch(endpoint, { method: 'POST', body: data });
  const result = await res.json();
  showAlert(result.success ? 'success' : 'warning', result.message || result.error || successMsg);
}

document.getElementById('formGlobal').addEventListener('submit', async e => {
  e.preventDefault();
  await saveForm('formGlobal', '/api/dropshipping.php?action=save_prefs', 'Preferences saved');
});

document.getElementById('formCategoryRule').addEventListener('submit', async e => {
  e.preventDefault();
  const form   = document.getElementById('formCategoryRule');
  const data   = new URLSearchParams(new FormData(form));
  data.set('csrf_token', CSRF);
  const res    = await fetch('/api/dropshipping.php?action=markup_rules', { method: 'POST', body: data });
  const result = await res.json();
  showAlert(result.success ? 'success' : 'warning', result.message || result.error || 'Rule saved');
  if (result.success) {
    // Reload to reflect the new rule in the table
    setTimeout(() => location.reload(), 800);
  }
});

document.getElementById('formTiers').addEventListener('submit', async e => {
  e.preventDefault();
  await saveForm('formTiers', '/api/dropshipping.php?action=save_prefs', 'Tiers saved');
});

document.getElementById('formSync').addEventListener('submit', async e => {
  e.preventDefault();
  await saveForm('formSync', '/api/dropshipping.php?action=save_prefs', 'Sync settings saved');
});

document.getElementById('triggerSync').addEventListener('click', async () => {
  const btn = document.getElementById('triggerSync');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing…';
  const body = new URLSearchParams({ csrf_token: CSRF });
  try {
    const res  = await fetch('/api/dropshipping.php?action=sync_trigger', { method: 'POST', body });
    const data = await res.json();
    showAlert(data.success ? 'success' : 'warning', data.message || data.error || 'Sync triggered');
  } catch {
    showAlert('warning', 'Network error — please try again');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Sync Now';
  }
});

function showAlert(type, msg) {
  const box = document.getElementById('alertBox');
  box.className = 'alert alert-' + (type === 'success' ? 'success' : 'warning') + ' alert-dismissible fade show mb-3';
  box.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
