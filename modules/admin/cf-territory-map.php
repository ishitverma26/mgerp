<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin']);

// One map showing every approved C&F partner's covered districts at once,
// each partner in its own colour, so Admin can see who's covering what
// (and spot overlaps - nothing in the onboarding flow currently stops two
// partners from both picking the same district) without opening each
// application individually.
$rows = $pdo->query(
    "SELECT p.id AS partner_id, p.firm_name, d.state_name, d.district_name
     FROM cf_partners p
     JOIN cf_partner_districts d ON d.cf_partner_id = p.id
     WHERE p.status = 'approved'
     ORDER BY p.firm_name, d.state_name, d.district_name"
)->fetchAll();

$partners = [];
foreach ($rows as $r) {
    $pid = (int) $r['partner_id'];
    if (!isset($partners[$pid])) {
        $partners[$pid] = ['firm_name' => $r['firm_name'], 'districts' => []];
    }
    $partners[$pid]['districts'][] = $r['district_name'];
}

$districtGeoJsonAvailable = file_exists(__DIR__ . '/../../assets/data/districts/_index.json');

$pageTitle = 'C&F Territory Map';
$activeMenu = 'utilities';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
  #cfTerritoryMap { height: 55vh; min-height: 320px; border-radius: 14px; margin-top: 10px; }
  .cf-legend-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
  .cf-legend-swatch { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; }
</style>
<div class="card">
  <h3 class="mt-0">C&amp;F Territory Map</h3>
  <p class="help-text" style="margin-top:0;">Every approved C&amp;F partner's covered districts, one colour each. Tap a district on the map for its name.</p>

  <?php if (!$districtGeoJsonAvailable): ?>
    <p class="row-card-empty">District boundary data isn't available yet.</p>
  <?php elseif (!$partners): ?>
    <p class="row-card-empty">No approved C&amp;F partners yet.</p>
  <?php else: ?>
    <div id="cfTerritoryMap" data-partners='<?= e(json_encode(array_values($partners))) ?>'></div>
    <label style="display:block;margin-top:16px;font-weight:700;">Legend</label>
    <div id="cfTerritoryLegend" style="margin-top:6px;"></div>
  <?php endif; ?>
</div>

<?php if ($districtGeoJsonAvailable && $partners): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
  var mapDiv = document.getElementById('cfTerritoryMap');
  var partners = JSON.parse(mapDiv.getAttribute('data-partners') || '[]');
  var legend = document.getElementById('cfTerritoryLegend');
  // Distinct, readable colours, cycling if there are more partners than colours.
  var palette = ['#1f7a3d', '#1d4ed8', '#b91c1c', '#a16207', '#7c3aed', '#0f766e', '#c2410c', '#be185d', '#0369a1', '#4d7c0f'];

  function normalizeKey(name) {
    return (name || '').toString().trim().toUpperCase().replace(/[^A-Z]/g, '');
  }

  var map = L.map('cfTerritoryMap').setView([22.5, 79], 5);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO', maxZoom: 19, subdomains: 'abcd'
  }).addTo(map);

  var allLayers = [];
  partners.forEach(function (partner, idx) {
    var color = palette[idx % palette.length];

    var legendRow = document.createElement('div');
    legendRow.className = 'cf-legend-row';
    var swatch = document.createElement('span');
    swatch.className = 'cf-legend-swatch';
    swatch.style.background = color;
    legendRow.appendChild(swatch);
    legendRow.appendChild(document.createTextNode(partner.firm_name + ' (' + partner.districts.length + ' district' + (partner.districts.length === 1 ? '' : 's') + ')'));
    legend.appendChild(legendRow);

    var layer = L.geoJSON(null, {
      style: { color: color, weight: 2, fillColor: color, fillOpacity: 0.35 },
      onEachFeature: function (feature, lyr) {
        var props = feature.properties || {};
        lyr.bindPopup('<strong>' + (props.district || 'District') + '</strong><br>' + partner.firm_name);
      }
    }).addTo(map);
    allLayers.push(layer);

    var remaining = partner.districts.length;
    partner.districts.forEach(function (d) {
      fetch('<?= APP_URL ?>/assets/data/districts/' + normalizeKey(d) + '.json')
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (data && data.features) layer.addData(data);
        })
        .catch(function () {})
        .finally(function () {
          remaining--;
          if (remaining === 0) fitAllIfDone();
        });
    });
  });

  var totalRemaining = partners.reduce(function (sum, p) { return sum + p.districts.length; }, 0);
  function fitAllIfDone() {
    // Refit bounds every time a partner's shapes finish loading, so the
    // view keeps expanding to fit everyone rather than only the first
    // partner's territory.
    var group = L.featureGroup(allLayers);
    var bounds = group.getBounds();
    if (bounds.isValid()) map.fitBounds(bounds, { padding: [20, 20] });
  }
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
