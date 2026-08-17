<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

$pageTitle = 'Utilities';
$activeMenu = 'utilities';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="section-head"><h3><span class="icon-chip icon-chip-blue"><?= tabIcon('tool') ?></span>Utilities</h3></div>
<p class="help-text" style="margin-top:-6px;">Day-to-day plant records that aren't tied to a specific batch. Tap a tile to open it right here - no page change.</p>
<div class="quick-actions-grid">
  <button type="button" class="action-tile action-tile-red glass-tile" data-title="Machine Down Time" data-url="<?= APP_URL ?>/modules/utilities/downtime.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Machine Down Time</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-red"><?= tabIcon('alert') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-amber glass-tile" data-title="Electricity" data-url="<?= APP_URL ?>/modules/utilities/electricity.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Electricity</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-amber"><?= tabIcon('chart') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-green glass-tile" data-title="Diesel" data-url="<?= APP_URL ?>/modules/utilities/diesel.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Diesel</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-green"><?= tabIcon('truck') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-purple glass-tile" data-title="Water Tanker" data-url="<?= APP_URL ?>/modules/utilities/water-tanker.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Water Tanker</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-purple"><?= tabIcon('truck') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-green glass-tile" data-title="Labour Cost" data-url="<?= APP_URL ?>/modules/utilities/labour-cost.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Labour Cost</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-green"><?= tabIcon('dollar') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-amber glass-tile" data-title="Empty Jumbo Bags" data-url="<?= APP_URL ?>/modules/utilities/empty-jumbo.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Empty Jumbo Bags</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-amber"><?= tabIcon('archive') ?></span></span>
  </button>
  <?php if ($isAdmin): ?>
  <button type="button" class="action-tile action-tile-blue glass-tile" data-title="C&amp;F Territory Map" data-url="<?= APP_URL ?>/modules/admin/cf-territory-map.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">C&amp;F Territory Map</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-blue"><?= tabIcon('layers') ?></span></span>
  </button>
  <?php endif; ?>
</div>

<div class="masters-modal-overlay" id="mastersModalOverlay">
  <div class="masters-modal masters-modal-lg">
    <div class="masters-modal-head">
      <span id="mastersModalTitle">Utility</span>
      <button type="button" class="masters-modal-close" id="mastersModalClose" aria-label="Close">&times;</button>
    </div>
    <iframe class="masters-modal-frame" id="mastersModalFrame" src="about:blank" title="Utility"></iframe>
  </div>
</div>
<script>
(function () {
  var overlay = document.getElementById('mastersModalOverlay');
  var frame = document.getElementById('mastersModalFrame');
  var title = document.getElementById('mastersModalTitle');
  var closeBtn = document.getElementById('mastersModalClose');
  if (!overlay || !frame) return;

  function openModal(url, label) {
    title.textContent = label;
    frame.src = url;
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
    // Wait for the slide-down transition to finish before blanking the
    // iframe, so the sheet slides away with its content still showing
    // instead of flashing to a blank white page the instant you tap close.
    setTimeout(function () { frame.src = 'about:blank'; }, 400);
  }

  document.querySelectorAll('.glass-tile').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.getAttribute('data-url'), btn.getAttribute('data-title'));
    });
  });
  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
