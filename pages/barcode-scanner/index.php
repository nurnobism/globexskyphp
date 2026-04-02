<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireAuth();

$pageTitle = 'Barcode Scanner';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="h3 mb-1 fw-bold"><i class="bi bi-upc-scan me-2 text-primary"></i>Barcode &amp; QR Code Scanner</h1>
        <p class="text-muted mb-0">Scan product barcodes or QR codes to look up product details instantly.</p>
    </div>

    <div class="row g-4">
        <!-- Camera Feed -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-camera-video me-2 text-primary"></i>Camera Scanner</span>
                    <span id="camera-status" class="badge bg-secondary">Camera Off</span>
                </div>
                <div class="card-body">
                    <div id="camera-container" class="bg-dark rounded-3 overflow-hidden mb-3 position-relative"
                         style="min-height:300px; display:flex; align-items:center; justify-content:center;">
                        <video id="camera-video" class="w-100 rounded-3" autoplay playsinline
                               style="display:none; max-height:360px; object-fit:cover;"></video>
                        <div id="camera-placeholder" class="text-center text-white-50 py-5">
                            <i class="bi bi-camera-video-off display-3 d-block mb-2"></i>
                            <p class="mb-0">Camera is off</p>
                        </div>
                        <!-- Scan overlay -->
                        <div id="scan-overlay" class="position-absolute top-50 start-50 translate-middle"
                             style="display:none; width:220px; height:220px; border:3px solid #0d6efd; border-radius:12px; box-shadow:0 0 0 9999px rgba(0,0,0,.45);">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button id="btn-start-camera" class="btn btn-primary" onclick="startCamera()">
                            <i class="bi bi-camera-video me-1"></i> Start Camera
                        </button>
                        <button id="btn-stop-camera" class="btn btn-outline-danger" onclick="stopCamera()" style="display:none;">
                            <i class="bi bi-camera-video-off me-1"></i> Stop Camera
                        </button>
                    </div>
                    <div class="form-text mt-1">Allow camera access when prompted. Best used on mobile devices.</div>
                </div>
            </div>
        </div>

        <!-- Manual Entry -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold"><i class="bi bi-keyboard me-2 text-secondary"></i>Manual Entry</span>
                </div>
                <div class="card-body">
                    <form id="barcode-form" onsubmit="handleManualSubmit(event)">
                        <label for="barcode-input" class="form-label fw-semibold">Barcode / SKU</label>
                        <div class="input-group mb-3">
                            <input type="text" id="barcode-input" class="form-control"
                                   placeholder="Enter barcode or SKU…" autocomplete="off">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search me-1"></i> Lookup
                            </button>
                        </div>
                        <div class="form-text">Enter a barcode, EAN, UPC, or internal SKU to search.</div>
                    </form>
                </div>
            </div>

            <!-- Result Section -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <span class="fw-semibold"><i class="bi bi-box-seam me-2 text-success"></i>Scan Result</span>
                </div>
                <div class="card-body">
                    <div id="scan-result">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-upc display-4 d-block mb-2 text-muted"></i>
                            <p class="mb-0 small">Scan a barcode or enter one manually to see product details here.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cameraStream = null;

function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showResult('<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Camera API not supported in this browser.</div>');
        return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function (stream) {
            cameraStream = stream;
            const video = document.getElementById('camera-video');
            video.srcObject = stream;
            video.style.display = 'block';
            document.getElementById('camera-placeholder').style.display = 'none';
            document.getElementById('scan-overlay').style.display = 'block';
            document.getElementById('btn-start-camera').style.display = 'none';
            document.getElementById('btn-stop-camera').style.display = 'inline-flex';
            document.getElementById('camera-status').textContent = 'Camera On';
            document.getElementById('camera-status').className = 'badge bg-success';
        })
        .catch(function (err) {
            showResult('<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>Camera access denied: ' + err.message + '</div>');
        });
}

function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(function (track) { track.stop(); });
        cameraStream = null;
    }
    const video = document.getElementById('camera-video');
    video.srcObject = null;
    video.style.display = 'none';
    document.getElementById('camera-placeholder').style.display = 'block';
    document.getElementById('scan-overlay').style.display = 'none';
    document.getElementById('btn-start-camera').style.display = 'inline-flex';
    document.getElementById('btn-stop-camera').style.display = 'none';
    document.getElementById('camera-status').textContent = 'Camera Off';
    document.getElementById('camera-status').className = 'badge bg-secondary';
}

function lookupBarcode(code) {
    code = code.trim();
    if (!code) return;

    showResult('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Looking up <strong>' + escapeHtml(code) + '</strong>…</div>');

    fetch('/api/products.php?action=detail&barcode=' + encodeURIComponent(code))
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.success && data.product) {
                const p = data.product;
                showResult(
                    '<div class="d-flex align-items-start gap-3">' +
                    '<div class="rounded-3 bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:64px;height:64px;">' +
                    '<i class="bi bi-box-seam fs-3 text-secondary"></i></div>' +
                    '<div class="flex-grow-1">' +
                    '<div class="fw-bold mb-1">' + escapeHtml(p.name || '') + '</div>' +
                    '<div class="text-muted small mb-1">SKU: ' + escapeHtml(p.sku || code) + '</div>' +
                    '<div class="text-muted small mb-2">Category: ' + escapeHtml(p.category || '—') + '</div>' +
                    '<div class="fw-semibold text-primary fs-5 mb-2">$' + escapeHtml(String(p.price || '—')) + '</div>' +
                    '<a href="/pages/products/detail.php?id=' + (p.id || '') + '" class="btn btn-sm btn-outline-primary">' +
                    '<i class="bi bi-arrow-right me-1"></i>View Product</a>' +
                    '</div></div>'
                );
            } else {
                showResult('<div class="alert alert-warning mb-0"><i class="bi bi-search me-2"></i>No product found for barcode <strong>' + escapeHtml(code) + '</strong>.</div>');
            }
        })
        .catch(function () {
            showResult('<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>Error looking up barcode. Please try again.</div>');
        });
}

function handleManualSubmit(e) {
    e.preventDefault();
    const code = document.getElementById('barcode-input').value;
    lookupBarcode(code);
}

function showResult(html) {
    document.getElementById('scan-result').innerHTML = html;
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
