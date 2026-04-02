<?php
require_once __DIR__ . '/../../includes/middleware.php';
$pageTitle = 'Shipment Services';
include __DIR__ . '/../../includes/header.php';
?>
<style>
.door-hero{min-height:80vh;background:linear-gradient(135deg,#1B2A4A 0%,#2d4a7a 100%);display:flex;align-items:center}
.door-card{border-radius:16px;overflow:hidden;transition:transform .3s,box-shadow .3s;cursor:pointer;min-height:420px;position:relative;background:#fff}
.door-card:hover{transform:scale(1.04);box-shadow:0 20px 60px rgba(0,0,0,.3)}
.door-card .overlay{position:absolute;inset:0;background:rgba(27,42,74,.7);transition:background .3s}
.door-card:hover .overlay{background:rgba(27,42,74,.5)}
.door-card .content{position:relative;z-index:2;padding:2.5rem;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;text-align:center}
.door-card.left{background:linear-gradient(160deg,#FF6B35,#e85d2a)}
.door-card.right{background:linear-gradient(160deg,#1B2A4A,#2d4a7a)}
.door-icon{font-size:4rem;margin-bottom:1rem}
.stat-box{background:rgba(255,255,255,.1);border-radius:12px;padding:1.5rem;text-align:center;color:#fff}
</style>
<section class="door-hero">
  <div class="container py-5">
    <div class="text-center mb-5">
      <h1 class="display-4 fw-bold text-white">Shipment Services</h1>
      <p class="lead text-white-50">Choose how you want to move cargo around the world</p>
    </div>
    <div class="row g-4 justify-content-center mb-5">
      <div class="col-md-5">
        <div class="door-card left">
          <div class="overlay"></div>
          <div class="content">
            <div class="door-icon">✈️</div>
            <h2 class="fw-bold mb-2">Carry Service</h2>
            <p class="mb-4 opacity-75">Travel with packages, earn money on every trip you make worldwide</p>
            <a href="/pages/shipment/carry/dashboard.php" class="btn btn-light btn-lg px-4 fw-semibold">Get Started</a>
          </div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="door-card right">
          <div class="overlay"></div>
          <div class="content">
            <div class="door-icon">📦</div>
            <h2 class="fw-bold mb-2">Send Parcel</h2>
            <p class="mb-4 opacity-75">Fast, reliable shipping worldwide with real-time tracking</p>
            <a href="/pages/shipment/parcel/create.php" class="btn btn-warning btn-lg px-4 fw-semibold text-dark">Ship Now</a>
          </div>
        </div>
      </div>
    </div>
    <div class="row g-3 justify-content-center">
      <?php foreach([['🌍','50+ Countries','Global coverage'],['🕐','24/7 Support','Always here'],['📡','Real-time Tracking','Live updates']] as $s): ?>
      <div class="col-md-3 col-6">
        <div class="stat-box">
          <div style="font-size:2rem"><?= $s[0] ?></div>
          <div class="fw-bold fs-5"><?= $s[1] ?></div>
          <div class="small opacity-75"><?= $s[2] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
