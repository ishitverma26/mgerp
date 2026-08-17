<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Public, token-gated - no login. Reachable two ways: an Admin-sent invite
// link (already has ?token=...), or the login page's own "C&F Registration"
// button (no token yet) - a fresh no-token GET here just creates a new
// cf_partners row on the spot (invited_by NULL, so Admin's list can tell
// self-registrations apart from ones they actually sent out) and redirects
// to the real token URL, so a refresh/bookmark comes back to the same
// in-progress application instead of starting a new one each time.
$token = clean($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO cf_partners (invite_token) VALUES (:t)")->execute([':t' => $token]);
    header('Location: ' . APP_URL . '/modules/cf/onboard.php?token=' . $token);
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM cf_partners WHERE invite_token=:t");
$stmt->execute([':t' => $token]);
$partner = $stmt->fetch();

$cfTerms = getSetting($pdo, 'cf_terms_conditions', '');

// The worldwide Country/State/City picker needs geo_countries/geo_states/
// geo_cities (populated by a one-off import, not part of the base schema
// every install necessarily has run yet) - fall back to the older
// India-only state dropdown + free-text city if those tables aren't there,
// so the form still works either way instead of breaking.
function tableExists(PDO $pdo, string $table): bool {
    try {
        $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
$worldGeoAvailable = tableExists($pdo, 'geo_countries') && tableExists($pdo, 'geo_states') && tableExists($pdo, 'geo_cities');
$countries = $worldGeoAvailable ? $pdo->query("SELECT id, name FROM geo_countries ORDER BY name")->fetchAll() : [];
$defaultCountryId = 0;
foreach ($countries as $c) {
    if ($c['name'] === 'India') { $defaultCountryId = (int) $c['id']; break; }
}

$error = '';
$justSubmitted = false;

if ($partner && $partner['status'] === 'invited' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $firmName = clean($_POST['firm_name'] ?? '');
    $contactPerson = clean($_POST['contact_person'] ?? '');
    $contactNo = clean($_POST['contact_no'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $gstNo = strtoupper(clean($_POST['gst_no'] ?? ''));
    $aadhaarNo = clean($_POST['aadhaar_no'] ?? '');
    $panNo = strtoupper(clean($_POST['pan_no'] ?? ''));
    $country = clean($_POST['country'] ?? '') ?: 'India';
    $state = clean($_POST['state'] ?? '');
    $city = clean($_POST['city'] ?? '');
    $liveLat = clean($_POST['live_lat'] ?? '');
    $liveLng = clean($_POST['live_lng'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $termsAccepted = !empty($_POST['terms_accepted']);

    // A C&F can cover districts across several states, not just their own
    // address's state - each entry here is "State||District||DealerCount"
    // (built client-side as the coverage picker is used), split back apart
    // below rather than assuming every district belongs to the one address
    // state or has no dealer count attached.
    $districtPairs = [];
    foreach ($_POST['districts'] ?? [] as $raw) {
        $parts = explode('||', clean($raw), 3);
        if (count($parts) === 3 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
            $districtPairs[] = ['state' => trim($parts[0]), 'district' => trim($parts[1]), 'count' => max(0, (int) trim($parts[2]))];
        }
    }

    // Standard India ID formats - PAN: 5 letters + 4 digits + 1 letter;
    // Aadhaar: 12 digits; GST: 15-char GSTIN built around a PAN.
    $panRegex = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';
    $aadhaarRegex = '/^[0-9]{12}$/';
    $gstRegex = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    if ($firmName === '' || $contactNo === '' || $email === '' || $gstNo === '' || $aadhaarNo === '' || $panNo === ''
        || $state === '' || $city === '' || !$districtPairs) {
        $error = 'Fill in all required fields and select at least one district to cover.';
    } elseif (!preg_match($panRegex, $panNo)) {
        $error = 'PAN number format looks wrong - it should look like ABCDE1234F.';
    } elseif (!preg_match($aadhaarRegex, $aadhaarNo)) {
        $error = 'Aadhaar number should be exactly 12 digits.';
    } elseif (!preg_match($gstRegex, $gstNo)) {
        $error = 'GST number format looks wrong - it should be a 15-character GSTIN.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must be at least 8 characters and include both letters and numbers.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirmation do not match.';
    } elseif (!$termsAccepted) {
        $error = 'Please accept the Terms & Conditions to continue.';
    } elseif (empty($_FILES['gst_doc']['name']) || empty($_FILES['aadhaar_doc']['name']) || empty($_FILES['aadhaar_doc_back']['name']) || empty($_FILES['pan_doc']['name'])) {
        $error = 'Please upload all required documents - GST Certificate (PDF), Aadhaar front & back, and PAN Card.';
    } else {
        try {
            $gstDoc = handleDocumentUpload('gst_doc', 'gst', true);
            $aadhaarDocFront = handleDocumentUpload('aadhaar_doc', 'aadhaar-front');
            $aadhaarDocBack = handleDocumentUpload('aadhaar_doc_back', 'aadhaar-back');
            $panDoc = handleDocumentUpload('pan_doc', 'pan');

            $pdo->beginTransaction();
            $pdo->prepare(
                "UPDATE cf_partners SET status='submitted', firm_name=:fn, contact_person=:cp, contact_no=:cn, email=:em,
                    gst_no=:gn, gst_doc=:gd, aadhaar_no=:an, aadhaar_doc=:ad, aadhaar_doc_back=:adb, pan_no=:pn, pan_doc=:pd,
                    country=:co, state=:st, city=:ci, live_lat=:lat, live_lng=:lng,
                    password_hash=:ph, terms_accepted_at=NOW(), submitted_at=NOW()
                 WHERE id=:id"
            )->execute([
                ':fn' => $firmName, ':cp' => $contactPerson, ':cn' => $contactNo, ':em' => $email,
                ':gn' => $gstNo, ':gd' => $gstDoc, ':an' => $aadhaarNo, ':ad' => $aadhaarDocFront, ':adb' => $aadhaarDocBack,
                ':pn' => $panNo, ':pd' => $panDoc, ':co' => $country, ':st' => $state, ':ci' => $city,
                ':lat' => $liveLat !== '' ? $liveLat : null, ':lng' => $liveLng !== '' ? $liveLng : null,
                ':ph' => password_hash($password, PASSWORD_DEFAULT), ':id' => $partner['id'],
            ]);
            $insDistrict = $pdo->prepare(
                "INSERT INTO cf_partner_districts (cf_partner_id, state_name, district_name, active_dealer_count) VALUES (:pid, :sn, :dn, :dc)"
            );
            foreach ($districtPairs as $pair) {
                $insDistrict->execute([':pid' => $partner['id'], ':sn' => $pair['state'], ':dn' => $pair['district'], ':dc' => $pair['count']]);
            }
            $pdo->commit();
            $justSubmitted = true;
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not submit - please try again.';
        }
    }
}

$statesDistrictsPath = __DIR__ . '/../../assets/data/india-states-districts.json';
$statesDistricts = file_exists($statesDistrictsPath) ? (json_decode(file_get_contents($statesDistrictsPath), true) ?: []) : [];
// District boundaries are split into one small file per district (see
// assets/data/districts/_index.json and the one-off split script used to
// build it) rather than one 74MB national file - a district checkbox only
// ever needs to fetch its own ~80KB shape, not the whole country's.
$districtGeoJsonAvailable = file_exists(__DIR__ . '/../../assets/data/districts/_index.json');

$companyName = getSetting($pdo, 'company_name', APP_NAME);
$companyLogo = getSetting($pdo, 'company_logo', null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>C&amp;F Partner Onboarding &middot; <?= e($companyName) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
  body { background: var(--bg); margin: 0; }
  .cf-onboard-wrap { max-width: 480px; margin: 0 auto; padding: 20px 16px 60px; }
  .cf-onboard-brand { display: flex; align-items: center; gap: 10px; padding: 14px 0 20px; }
  .cf-onboard-brand img { width: 34px; height: 34px; border-radius: 9px; object-fit: cover; }
  .cf-onboard-brand-badge { width: 34px; height: 34px; border-radius: 9px; background: var(--primary-soft); color: var(--primary-dark); display: flex; align-items: center; justify-content: center; font-weight: 800; }
  .cf-onboard-brand strong { font-size: 16px; }
  #cfDistrictMap { height: 260px; border-radius: 14px; margin-top: 10px; z-index: 1; }
  .cf-district-list { max-height: 220px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; padding: 8px 10px; margin-top: 6px; }
  .cf-district-list label { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-weight: 400; font-size: 13.5px; }
  .cf-selected-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
  .cf-selected-row:last-child { border-bottom: none; }
  .cf-selected-row span.cf-selected-name { flex: 1; min-width: 0; }
  .cf-selected-row input[type=number] { width: 90px; }
  .cf-selected-row .cf-remove { cursor: pointer; font-weight: 700; color: var(--bad); flex-shrink: 0; }
  .cf-terms-box { max-height: 150px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; margin-top: 6px; font-size: 12.5px; color: var(--ink-soft); }
</style>
</head>
<body>
<div class="cf-onboard-wrap">
  <div class="cf-onboard-brand">
    <?php if ($companyLogo): ?>
      <img src="<?= UPLOAD_URL ?><?= e($companyLogo) ?>" alt="<?= e($companyName) ?>">
    <?php else: ?>
      <span class="cf-onboard-brand-badge"><?= e(substr($companyName, 0, 1)) ?></span>
    <?php endif; ?>
    <strong><?= e($companyName) ?></strong>
  </div>

  <?php if (!$partner): ?>
    <div class="card"><h3 class="mt-0">Link Not Valid</h3><p class="help-text">This invite link isn't valid. Please ask for a fresh link.</p></div>

  <?php elseif ($justSubmitted || $partner['status'] !== 'invited'): ?>
    <div class="card">
      <h3 class="mt-0"><?= $partner['status'] === 'approved' ? 'Already Approved' : ($partner['status'] === 'rejected' ? 'Submission Closed' : 'Thank You') ?></h3>
      <?php if ($justSubmitted || $partner['status'] === 'submitted'): ?>
        <p class="help-text">Your details have been submitted. We'll review them and get back to you with login access soon.</p>
      <?php elseif ($partner['status'] === 'approved'): ?>
        <p class="help-text">This application has already been approved - log in with the password you set when you applied.</p>
      <?php else: ?>
        <p class="help-text">This application is no longer open for submission.</p>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="card">
      <h3 class="mt-0">Distributor Registration</h3>
      <p class="help-text">Fill in your details below - an Admin will review this and activate your account.</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

      <form method="post" action="" enctype="multipart/form-data">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label style="display:block;margin-top:4px;font-weight:700;">Company / Firm Details</label>
        <div class="form-row" style="margin-top:8px;">
          <div><label>Firm / Company Name *</label><input type="text" name="firm_name" required value="<?= e($_POST['firm_name'] ?? '') ?>"></div>
          <div><label>Proprietor / Owner Name</label><input type="text" name="contact_person" value="<?= e($_POST['contact_person'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div><label>Proprietor/Owner WhatsApp No. *</label><input type="tel" name="contact_no" required value="<?= e($_POST['contact_no'] ?? '') ?>"></div>
          <div><label>Proprietor/Owner Email Id *</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        </div>

        <label style="display:block;margin-top:18px;font-weight:700;">GST, Aadhaar &amp; PAN</label>
        <div class="form-row" style="margin-top:8px;">
          <div><label>GST No *</label><input type="text" name="gst_no" required placeholder="e.g. 09ABCDE1234F1Z5" maxlength="15" style="text-transform:uppercase;" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]" title="15-character GSTIN" value="<?= e($_POST['gst_no'] ?? '') ?>"></div>
          <div><label>GST Certificate (3 Page, PDF) *</label><input type="file" name="gst_doc" accept="application/pdf,.pdf" required></div>
        </div>
        <div class="form-row">
          <div><label>Aadhaar No *</label><input type="text" name="aadhaar_no" required placeholder="12-digit number" maxlength="12" inputmode="numeric" pattern="[0-9]{12}" title="12 digits" value="<?= e($_POST['aadhaar_no'] ?? '') ?>"></div>
          <div><label>PAN No *</label><input type="text" name="pan_no" required placeholder="e.g. ABCDE1234F" maxlength="10" style="text-transform:uppercase;" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]" title="Format: ABCDE1234F" value="<?= e($_POST['pan_no'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div><label>Aadhaar Card - Front *</label><input type="file" name="aadhaar_doc" accept="image/*,.pdf" required></div>
          <div><label>Aadhaar Card - Back *</label><input type="file" name="aadhaar_doc_back" accept="image/*,.pdf" required></div>
        </div>
        <div class="form-row">
          <div><label>PAN Card *</label><input type="file" name="pan_doc" accept="image/*,.pdf" required></div>
        </div>

        <label style="display:block;margin-top:18px;font-weight:700;">Address</label>
        <?php if ($worldGeoAvailable): ?>
          <div class="form-row" style="margin-top:8px;">
            <div>
              <label>Country *</label>
              <input type="text" id="cfCountryInput" list="cfCountryList" required autocomplete="off" value="India" data-id="<?= $defaultCountryId ?>">
              <datalist id="cfCountryList">
                <?php foreach ($countries as $c): ?><option value="<?= e($c['name']) ?>" data-id="<?= (int) $c['id'] ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div>
              <label>State *</label>
              <input type="text" id="cfStateInput" name="state" list="cfStateList" required autocomplete="off" placeholder="Start typing..." value="<?= e($_POST['state'] ?? '') ?>">
              <datalist id="cfStateList"></datalist>
            </div>
          </div>
          <input type="hidden" name="country" id="cfCountryHidden" value="India">
          <div class="form-row">
            <div>
              <label>City *</label>
              <input type="text" id="cfCityInput" name="city" list="cfCityList" required autocomplete="off" placeholder="Select a state first" value="<?= e($_POST['city'] ?? '') ?>">
              <datalist id="cfCityList"></datalist>
            </div>
          </div>
          <p class="text-soft" style="font-size:11px;margin-top:4px;">Contains data from Countries States Cities Database (github.com/dr5hn/countries-states-cities-database), ODbL v1.0.</p>
        <?php else: ?>
          <div class="form-row" style="margin-top:8px;">
            <div><label>Country</label><input type="text" name="country" value="India" readonly></div>
            <div>
              <label>State *</label>
              <select name="state" id="cfStateSelect" required>
                <option value="">Select state</option>
                <?php foreach (array_keys($statesDistricts) as $stateName): ?>
                  <option value="<?= e($stateName) ?>"><?= e($stateName) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div><label>City *</label><input type="text" name="city" required value="<?= e($_POST['city'] ?? '') ?>"></div>
          </div>
        <?php endif; ?>

        <label style="display:block;margin-top:18px;font-weight:700;">District of Distribution *</label>
        <p class="help-text" style="margin-top:2px;">Add districts from any state you'll operate in, and how many active dealers/retailers you already have in each.</p>
        <label style="margin-top:8px;">Pick a State to Add Districts From</label>
        <select id="cfCoverageStateSelect">
          <option value="">Select state</option>
          <?php foreach (array_keys($statesDistricts) as $stateName): ?>
            <option value="<?= e($stateName) ?>"><?= e($stateName) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="cf-district-list" id="cfDistrictList">
          <p class="text-soft" style="margin:0;">Select a state above to see its districts.</p>
        </div>

        <label style="display:block;margin-top:14px;">Selected Districts &amp; Active Dealers/Retailers</label>
        <div class="cf-district-list" id="cfSelectedDistricts">
          <p class="text-soft" style="margin:0;">None selected yet.</p>
        </div>
        <div id="cfDistrictsHidden"></div>
        <div id="cfDistrictMap"></div>

        <label style="display:block;margin-top:18px;font-weight:700;">Live Location</label>
        <p class="help-text" style="margin-top:2px;">Optional - helps confirm where you're actually located.</p>
        <button type="button" class="btn btn-outline btn-sm" id="cfLiveLocationBtn"><?= tabIcon('layers') ?> Use My Current Location</button>
        <span class="text-soft" id="cfLiveLocationStatus" style="margin-left:8px;"></span>
        <input type="hidden" name="live_lat" id="cfLiveLat">
        <input type="hidden" name="live_lng" id="cfLiveLng">

        <label style="display:block;margin-top:18px;font-weight:700;">Set Your Password</label>
        <div class="form-row" style="margin-top:8px;">
          <div><label>Password *</label><input type="password" name="password" required minlength="8"></div>
          <div><label>Confirm Password *</label><input type="password" name="confirm_password" required minlength="8"></div>
        </div>
        <p class="help-text" style="margin-top:4px;">At least 8 characters, with a mix of letters and numbers - this is what you'll log in with once approved.</p>

        <label style="display:block;margin-top:18px;font-weight:700;">Terms &amp; Conditions</label>
        <div class="cf-terms-box"><?= $cfTerms !== '' ? nl2br(e($cfTerms)) : '<em>No Terms &amp; Conditions have been set yet - contact Admin.</em>' ?></div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-weight:400;">
          <input type="checkbox" name="terms_accepted" value="1" required style="width:auto;">
          I have read and accept the Terms &amp; Conditions
        </label>

        <button type="submit" class="btn btn-accent" style="margin-top:22px;width:100%;">Submit for Review</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($partner && $partner['status'] === 'invited' && !$justSubmitted): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
  var statesDistricts = <?= json_encode($statesDistricts) ?>;
  var districtGeoJsonAvailable = <?= $districtGeoJsonAvailable ? 'true' : 'false' ?>;
  // A C&F can cover districts across several states - selectedDistricts is
  // keyed by "State||District" so the same district name in two different
  // states never collides, and stays intact as the coverage-state dropdown
  // is switched back and forth. Each entry also carries the applicant's
  // Active Dealers/Retailers count for that specific district.
  var selectedDistricts = {};

  var coverageStateSelect = document.getElementById('cfCoverageStateSelect');
  var districtList = document.getElementById('cfDistrictList');
  var selectedContainer = document.getElementById('cfSelectedDistricts');
  var hiddenContainer = document.getElementById('cfDistrictsHidden');

  var map = L.map('cfDistrictMap').setView([22.5, 79], 5);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO', maxZoom: 19, subdomains: 'abcd'
  }).addTo(map);
  var districtLayer = L.geoJSON(null, {
    style: { color: '#1f7a3d', weight: 2.5, fillColor: '#4caf50', fillOpacity: 0.35 }
  }).addTo(map);

  function normalizeKey(name) {
    return (name || '').toString().trim().toUpperCase().replace(/[^A-Z]/g, '');
  }

  var districtFileCache = {};
  function loadDistrictShape(districtName, callback) {
    if (!districtGeoJsonAvailable) { callback(null); return; }
    var key = normalizeKey(districtName);
    if (districtFileCache[key] !== undefined) { callback(districtFileCache[key]); return; }
    fetch('<?= APP_URL ?>/assets/data/districts/' + key + '.json')
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) { districtFileCache[key] = data; callback(data); })
      .catch(function () { districtFileCache[key] = null; callback(null); });
  }

  function refreshMapHighlight() {
    var pairs = Object.keys(selectedDistricts);
    districtLayer.clearLayers();
    if (!pairs.length) return;
    var remaining = pairs.length;
    var allFeatures = [];
    pairs.forEach(function (key) {
      loadDistrictShape(selectedDistricts[key].district, function (data) {
        if (data && data.features) allFeatures = allFeatures.concat(data.features);
        remaining--;
        if (remaining === 0 && allFeatures.length) {
          districtLayer.addData({ type: 'FeatureCollection', features: allFeatures });
          var bounds = districtLayer.getBounds();
          if (bounds.isValid()) map.fitBounds(bounds, { padding: [20, 20] });
        }
      });
    });
  }

  function renderSelectedList() {
    var keys = Object.keys(selectedDistricts);
    selectedContainer.innerHTML = '';
    if (!keys.length) {
      selectedContainer.innerHTML = '<p class="text-soft" style="margin:0;">None selected yet.</p>';
      return;
    }
    keys.forEach(function (key) {
      var entry = selectedDistricts[key];
      var row = document.createElement('div');
      row.className = 'cf-selected-row';

      var name = document.createElement('span');
      name.className = 'cf-selected-name';
      name.textContent = entry.district + ' (' + entry.state + ')';
      row.appendChild(name);

      var countInput = document.createElement('input');
      countInput.type = 'number';
      countInput.min = '0';
      countInput.placeholder = 'Active dealers';
      countInput.value = entry.count || '';
      countInput.addEventListener('input', function () {
        entry.count = parseInt(countInput.value, 10) || 0;
        renderHiddenInputs();
      });
      row.appendChild(countInput);

      var remove = document.createElement('span');
      remove.className = 'cf-remove';
      remove.textContent = '×';
      remove.addEventListener('click', function () {
        delete selectedDistricts[key];
        renderSelectedList();
        renderHiddenInputs();
        refreshMapHighlight();
        var cb = districtList.querySelector('input[value="' + key.replace(/"/g, '&quot;') + '"]');
        if (cb) cb.checked = false;
      });
      row.appendChild(remove);

      selectedContainer.appendChild(row);
    });
  }

  function renderHiddenInputs() {
    hiddenContainer.innerHTML = '';
    Object.keys(selectedDistricts).forEach(function (key) {
      var entry = selectedDistricts[key];
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'districts[]';
      input.value = entry.state + '||' + entry.district + '||' + (entry.count || 0);
      hiddenContainer.appendChild(input);
    });
  }

  coverageStateSelect.addEventListener('change', function () {
    var stateName = coverageStateSelect.value;
    var districts = statesDistricts[stateName] || [];
    if (!districts.length) {
      districtList.innerHTML = '<p class="text-soft" style="margin:0;">No districts found for this state.</p>';
      return;
    }
    districtList.innerHTML = '';
    districts.forEach(function (d) {
      var pairKey = stateName + '||' + d;
      var label = document.createElement('label');
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = pairKey;
      cb.checked = !!selectedDistricts[pairKey];
      cb.addEventListener('change', function () {
        if (cb.checked) {
          selectedDistricts[pairKey] = { state: stateName, district: d, count: 0 };
        } else {
          delete selectedDistricts[pairKey];
        }
        renderSelectedList();
        renderHiddenInputs();
        refreshMapHighlight();
      });
      label.appendChild(cb);
      label.appendChild(document.createTextNode(d));
      districtList.appendChild(label);
    });
  });

  var liveBtn = document.getElementById('cfLiveLocationBtn');
  var liveStatus = document.getElementById('cfLiveLocationStatus');
  liveBtn.addEventListener('click', function () {
    if (!navigator.geolocation) {
      liveStatus.textContent = 'Not supported on this device/browser.';
      return;
    }
    liveStatus.textContent = 'Fetching location...';
    navigator.geolocation.getCurrentPosition(function (pos) {
      document.getElementById('cfLiveLat').value = pos.coords.latitude;
      document.getElementById('cfLiveLng').value = pos.coords.longitude;
      liveStatus.textContent = 'Captured: ' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5);
    }, function () {
      liveStatus.textContent = 'Could not get your location - check permission and try again.';
    });
  });

  // Worldwide Country -> State -> City cascade (only present if
  // worldGeoAvailable was true server-side - see the PHP if-block around
  // the Address section above).
  var countryInput = document.getElementById('cfCountryInput');
  if (countryInput) {
    var countryHidden = document.getElementById('cfCountryHidden');
    var stateInput = document.getElementById('cfStateInput');
    var stateList = document.getElementById('cfStateList');
    var cityInput = document.getElementById('cfCityInput');
    var cityList = document.getElementById('cfCityList');
    var countryListEl = document.getElementById('cfCountryList');
    var stateNameToId = {};
    var currentCountryId = parseInt(countryInput.getAttribute('data-id'), 10) || 0;

    function findCountryId(name) {
      var opt = countryListEl.querySelector('option[value="' + name.replace(/"/g, '&quot;') + '"]');
      return opt ? parseInt(opt.getAttribute('data-id'), 10) : 0;
    }

    function loadStates(countryId) {
      stateList.innerHTML = '';
      stateNameToId = {};
      if (!countryId) return;
      fetch('<?= APP_URL ?>/modules/cf/geo-states.php?country_id=' + countryId)
        .then(function (r) { return r.json(); })
        .then(function (states) {
          states.forEach(function (s) {
            stateNameToId[s.name] = s.id;
            var opt = document.createElement('option');
            opt.value = s.name;
            stateList.appendChild(opt);
          });
        });
    }

    function loadCities(stateId) {
      cityList.innerHTML = '';
      if (!stateId) return;
      fetch('<?= APP_URL ?>/modules/cf/geo-cities.php?state_id=' + stateId)
        .then(function (r) { return r.json(); })
        .then(function (cities) {
          cities.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.name;
            cityList.appendChild(opt);
          });
        });
    }

    countryInput.addEventListener('input', function () {
      var id = findCountryId(countryInput.value);
      if (id && id !== currentCountryId) {
        currentCountryId = id;
        countryHidden.value = countryInput.value;
        stateInput.value = '';
        cityInput.value = '';
        loadStates(id);
      }
    });

    stateInput.addEventListener('input', function () {
      var id = stateNameToId[stateInput.value];
      if (id) {
        cityInput.value = '';
        loadCities(id);
      }
    });

    // Pre-load India's states on first render since the country defaults to India.
    if (currentCountryId) loadStates(currentCountryId);
  }
})();
</script>
<?php endif; ?>
</body>
</html>
