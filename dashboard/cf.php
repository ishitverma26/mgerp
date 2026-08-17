<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['C&F']);

$stmt = $pdo->prepare("SELECT * FROM cf_partners WHERE user_id=:uid");
$stmt->execute([':uid' => $currentUser['id']]);
$partner = $stmt->fetch();

$districts = [];
if ($partner) {
    $dStmt = $pdo->prepare("SELECT state_name, district_name, active_dealer_count FROM cf_partner_districts WHERE cf_partner_id=:id ORDER BY state_name, district_name");
    $dStmt->execute([':id' => $partner['id']]);
    $districts = $dStmt->fetchAll();
}
$districtGeoJsonAvailable = file_exists(__DIR__ . '/../assets/data/districts/_index.json');

$pageTitle = 'C&F Dashboard';
$activeMenu = 'dashboard';
$hideTopbar = true;
require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($partner): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>.cf-profile-map { height: 240px; border-radius: 14px; margin-top: 10px; }</style>

<div class="section-head"><h3><span class="icon-chip icon-chip-amber"><?= tabIcon('plus') ?></span>Quick Actions</h3></div>
<div class="quick-actions-grid">
  <a href="<?= APP_URL ?>/modules/cf/new-order.php" class="action-tile action-tile-green">
    <span class="action-tile-icon"><?= tabIllustration('delivery') ?></span>
    <span class="action-tile-bottom"><span class="action-tile-label">New Order</span><?= tabIcon('arrow-right') ?></span>
  </a>
</div>

<div class="section-head"><h3><span class="icon-chip icon-chip-purple"><?= tabIcon('briefcase') ?></span>Profile</h3></div>
<div class="card">
  <h3 class="mt-0"><?= e($partner['firm_name']) ?></h3>
  <p class="help-text" style="margin-top:0;">This is exactly what Admin sees when they review your application.</p>

  <label style="display:block;font-weight:700;">Company / Firm Details</label>
  <div class="row-card-fields" style="margin-top:8px;">
    <div><div class="field-label">Firm Name</div><div class="field-value"><?= e($partner['firm_name']) ?></div></div>
    <div><div class="field-label">Proprietor/Owner Name</div><div class="field-value"><?= e($partner['contact_person']) ?: '-' ?></div></div>
    <div><div class="field-label">WhatsApp No</div><div class="field-value"><?= e($partner['contact_no']) ?: '-' ?></div></div>
    <div><div class="field-label">Email Id</div><div class="field-value"><?= e($partner['email']) ?: '-' ?></div></div>
  </div>

  <label style="display:block;font-weight:700;margin-top:18px;">GST, Aadhaar &amp; PAN</label>
  <div class="row-card-fields" style="margin-top:8px;">
    <div><div class="field-label">GST No</div><div class="field-value"><?= e($partner['gst_no']) ?: '-' ?></div></div>
    <div><div class="field-label">GST Certificate</div><div class="field-value"><?php if ($partner['gst_doc']): ?><a href="<?= UPLOAD_URL ?><?= e($partner['gst_doc']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></div></div>
    <div><div class="field-label">Aadhaar No</div><div class="field-value"><?= e($partner['aadhaar_no']) ?: '-' ?></div></div>
    <div><div class="field-label">Aadhaar - Front</div><div class="field-value"><?php if ($partner['aadhaar_doc']): ?><a href="<?= UPLOAD_URL ?><?= e($partner['aadhaar_doc']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></div></div>
    <div><div class="field-label">Aadhaar - Back</div><div class="field-value"><?php if ($partner['aadhaar_doc_back']): ?><a href="<?= UPLOAD_URL ?><?= e($partner['aadhaar_doc_back']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></div></div>
    <div><div class="field-label">PAN No</div><div class="field-value"><?= e($partner['pan_no']) ?: '-' ?></div></div>
    <div><div class="field-label">PAN Document</div><div class="field-value"><?php if ($partner['pan_doc']): ?><a href="<?= UPLOAD_URL ?><?= e($partner['pan_doc']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></div></div>
  </div>

  <label style="display:block;font-weight:700;margin-top:18px;">Address</label>
  <div class="row-card-fields" style="margin-top:8px;">
    <div><div class="field-label">Country</div><div class="field-value"><?= e($partner['country']) ?></div></div>
    <div><div class="field-label">State</div><div class="field-value"><?= e($partner['state']) ?></div></div>
    <div><div class="field-label">City</div><div class="field-value"><?= e($partner['city']) ?></div></div>
    <?php if ($partner['live_lat'] && $partner['live_lng']): ?>
      <div><div class="field-label">Live Location</div><div class="field-value"><?= e($partner['live_lat']) ?>, <?= e($partner['live_lng']) ?></div></div>
    <?php endif; ?>
  </div>

  <label style="display:block;font-weight:700;margin-top:18px;">Districts You Cover &amp; Active Dealers/Retailers</label>
  <div class="field-value" style="margin-top:8px;"><?php foreach ($districts as $d): ?><span class="pill pill-active"><?= e($d['district_name']) ?> (<?= (int) $d['active_dealer_count'] ?>)</span> <?php endforeach; ?></div>
  <?php if ($districtGeoJsonAvailable && $districts): ?>
    <div class="cf-profile-map" id="cfProfileMap" data-districts='<?= e(json_encode(array_column($districts, 'district_name'))) ?>'></div>
  <?php endif; ?>
</div>

<?php if ($districtGeoJsonAvailable && $districts): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
  var mapDiv = document.getElementById('cfProfileMap');
  if (!mapDiv) return;
  var districts = JSON.parse(mapDiv.getAttribute('data-districts') || '[]');
  if (!districts.length) return;

  function normalizeKey(name) {
    return (name || '').toString().trim().toUpperCase().replace(/[^A-Z]/g, '');
  }

  var map = L.map('cfProfileMap').setView([22.5, 79], 5);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO', maxZoom: 19, subdomains: 'abcd'
  }).addTo(map);
  var layer = L.geoJSON(null, {
    style: { color: '#1f7a3d', weight: 2.5, fillColor: '#4caf50', fillOpacity: 0.35 }
  }).addTo(map);

  var remaining = districts.length;
  var allFeatures = [];
  districts.forEach(function (d) {
    fetch('<?= APP_URL ?>/assets/data/districts/' + normalizeKey(d) + '.json')
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data && data.features) allFeatures = allFeatures.concat(data.features);
      })
      .catch(function () {})
      .finally(function () {
        remaining--;
        if (remaining === 0 && allFeatures.length) {
          layer.addData({ type: 'FeatureCollection', features: allFeatures });
          var bounds = layer.getBounds();
          if (bounds.isValid()) map.fitBounds(bounds, { padding: [20, 20] });
        }
      });
  });
})();
</script>
<?php endif; ?>

<?php else: ?>
<div class="card">
  <p class="row-card-empty">No profile found for this account.</p>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
