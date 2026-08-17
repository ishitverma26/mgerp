<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create_invite') {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO cf_partners (invite_token, invited_by) VALUES (:t, :uid)")
            ->execute([':t' => $token, ':uid' => $currentUser['id']]);
        $newId = (int) $pdo->lastInsertId();
        logAudit($pdo, $currentUser['id'], 'create', 'cf_partners', $newId, null, ['status' => 'invited']);
        setFlash('success', 'Invite link created - copy it below and send it to the partner.');
        redirect('/modules/admin/cf-partners.php');
    }

    if ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT * FROM cf_partners WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $partner = $stmt->fetch();

        if (!$partner || $partner['status'] !== 'submitted') {
            setFlash('error', 'This partner is not awaiting approval.');
        } else {
            $pdo->beginTransaction();
            try {
                $cfRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name='C&F'")->fetchColumn();

                $base = strtolower(preg_replace('/[^a-z0-9]/i', '', $partner['firm_name']) ?: 'cfpartner');
                $base = substr($base, 0, 20) ?: 'cfpartner';
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=:u");
                $username = $base;
                $suffix = 1;
                $checkStmt->execute([':u' => $username]);
                while ($checkStmt->fetchColumn() > 0) {
                    $suffix++;
                    $username = $base . $suffix;
                    $checkStmt->execute([':u' => $username]);
                }
                // The applicant set their own password at signup (onboard.php) -
                // reuse that hash rather than generating a new one, so they can
                // log in with the password they actually chose. Only fall back
                // to a generated one for older rows submitted before that field
                // existed.
                $passwordHash = $partner['password_hash'];
                $generatedPassword = null;
                if (!$passwordHash) {
                    $generatedPassword = generateRandomPassword();
                    $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);
                }

                $userStmt = $pdo->prepare(
                    "INSERT INTO users (name, username, password, role_id) VALUES (:name, :username, :password, :role_id)"
                );
                $userStmt->execute([
                    ':name' => $partner['firm_name'], ':username' => $username,
                    ':password' => $passwordHash, ':role_id' => $cfRoleId,
                ]);
                $newUserId = (int) $pdo->lastInsertId();

                $pdo->prepare("UPDATE cf_partners SET status='approved', user_id=:uid, reviewed_by=:rb, reviewed_at=NOW() WHERE id=:id")
                    ->execute([':uid' => $newUserId, ':rb' => $currentUser['id'], ':id' => $id]);

                logAudit($pdo, $currentUser['id'], 'approve', 'cf_partners', $id, ['status' => 'submitted'], ['status' => 'approved', 'username' => $username]);
                $pdo->commit();

                if ($generatedPassword) {
                    setModalPopup(
                        'Partner Approved',
                        'Login for ' . e($partner['firm_name']) . ':<br><br><strong>Username:</strong> ' . e($username) . '<br><strong>Password:</strong> ' . e($generatedPassword)
                        . '<br><br>Share these with the partner now - the password will not be shown again.',
                        'check-square', 'green', true
                    );
                } else {
                    setModalPopup(
                        'Partner Approved',
                        'Login for ' . e($partner['firm_name']) . ':<br><br><strong>Username:</strong> ' . e($username)
                        . '<br><br>They can log in with the password they set when applying.',
                        'check-square', 'green', true
                    );
                }
                setFlash('success', 'Partner approved and account created.');
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('error', 'Could not approve: ' . $e->getMessage());
            }
        }
        redirect('/modules/admin/cf-partners.php');
    }

    if ($action === 'reject') {
        $stmt = $pdo->prepare("SELECT * FROM cf_partners WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $partner = $stmt->fetch();
        if (!$partner || $partner['status'] !== 'submitted') {
            setFlash('error', 'This partner is not awaiting approval.');
        } else {
            $pdo->prepare("UPDATE cf_partners SET status='rejected', reviewed_by=:rb, reviewed_at=NOW() WHERE id=:id")
                ->execute([':rb' => $currentUser['id'], ':id' => $id]);
            logAudit($pdo, $currentUser['id'], 'reject', 'cf_partners', $id, ['status' => 'submitted'], ['status' => 'rejected']);
            setFlash('success', 'Submission rejected.');
        }
        redirect('/modules/admin/cf-partners.php');
    }
}

$partners = $pdo->query(
    "SELECT c.*, u.name AS invited_by_name
     FROM cf_partners c
     LEFT JOIN users u ON u.id = c.invited_by
     ORDER BY c.id DESC"
)->fetchAll();

$districtStmt = $pdo->prepare("SELECT state_name, district_name, active_dealer_count FROM cf_partner_districts WHERE cf_partner_id=:id ORDER BY state_name, district_name");

$statusPill = ['invited' => 'pending', 'submitted' => 'completed', 'approved' => 'active', 'rejected' => 'garbage'];
$statusLabel = ['invited' => 'Invited', 'submitted' => 'Awaiting Review', 'approved' => 'Approved', 'rejected' => 'Rejected'];

$districtGeoJsonAvailable = file_exists(__DIR__ . '/../../assets/data/districts/_index.json');

$pageTitle = 'C&F Partners';
$activeMenu = 'cf-partners';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>.cf-admin-map { height: 240px; border-radius: 14px; margin-top: 10px; }</style>
<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;">Send New Invite</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <p class="help-text" style="margin-top:0;">Creates a one-time link - send it to a prospective C&amp;F partner. They fill in their own details, then you review and approve it below.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="create_invite">
    <button type="submit" class="btn btn-accent" style="width:100%;">+ Create Invite Link</button>
  </form>
  </div>
</details>

<details class="card" open>
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;">All Partners (<?= count($partners) ?>)</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$partners): ?>
    <p class="row-card-empty">No invites sent yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($partners as $p): ?>
      <?php $districtStmt->execute([':id' => $p['id']]); $districts = $districtStmt->fetchAll(); ?>
      <details class="row-card row-card-<?= $p['status'] === 'rejected' ? 'bad' : ($p['status'] === 'approved' ? 'good' : 'info') ?>">
        <summary>
          <span class="row-card-heading">
            <strong><?= e($p['firm_name'] ?: 'Not filled in yet') ?></strong>
            <span class="text-soft"><?= formatDateTime($p['invited_at']) ?></span>
            <span class="pill pill-<?= $statusPill[$p['status']] ?>"><?= $statusLabel[$p['status']] ?></span>
          </span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <p class="help-text" style="margin-top:0;"><?= $p['invited_by_name'] ? 'Invited by ' . e($p['invited_by_name']) : 'Self-registered from the login page' ?></p>
        <?php if ($p['status'] === 'invited'): ?>
          <p class="help-text" style="margin-top:0;">Not filled in yet. Share this link:</p>
          <div style="display:flex;gap:6px;">
            <input type="text" readonly value="<?= e(fullUrl('/modules/cf/onboard.php?token=' . $p['invite_token'])) ?>" style="width:100%;font-size:12px;" onclick="this.select()" id="inviteLink<?= (int) $p['id'] ?>">
            <button type="button" class="btn btn-sm btn-outline cf-copy-link-btn" data-target="inviteLink<?= (int) $p['id'] ?>" style="flex-shrink:0;">Copy</button>
          </div>
        <?php else: ?>
        <div class="row-card-fields">
          <div><div class="field-label">Proprietor/Owner Name</div><div class="field-value"><?= e($p['contact_person']) ?: '-' ?></div></div>
          <div><div class="field-label">WhatsApp No</div><div class="field-value"><?= e($p['contact_no']) ?: '-' ?></div></div>
          <div><div class="field-label">Email Id</div><div class="field-value"><?= e($p['email']) ?: '-' ?></div></div>
          <div><div class="field-label">GST No</div><div class="field-value"><?= e($p['gst_no']) ?: '-' ?></div></div>
          <div><div class="field-label">Aadhaar No</div><div class="field-value"><?= e($p['aadhaar_no']) ?: '-' ?></div></div>
          <div><div class="field-label">PAN No</div><div class="field-value"><?= e($p['pan_no']) ?: '-' ?></div></div>
          <div><div class="field-label">Location</div><div class="field-value"><?= e($p['city']) ?>, <?= e($p['state']) ?>, <?= e($p['country']) ?></div></div>
          <div>
            <div class="field-label">Districts &amp; Active Dealers/Retailers</div>
            <div class="field-value"><?php foreach ($districts as $d): ?><span class="pill pill-active"><?= e($d['district_name']) ?> (<?= (int) $d['active_dealer_count'] ?>)</span> <?php endforeach; ?></div>
          </div>
          <?php if ($p['gst_doc']): ?><div><div class="field-label">GST Certificate</div><div class="field-value"><a href="<?= UPLOAD_URL ?><?= e($p['gst_doc']) ?>" target="_blank">View</a></div></div><?php endif; ?>
          <?php if ($p['aadhaar_doc']): ?><div><div class="field-label">Aadhaar - Front</div><div class="field-value"><a href="<?= UPLOAD_URL ?><?= e($p['aadhaar_doc']) ?>" target="_blank">View</a></div></div><?php endif; ?>
          <?php if ($p['aadhaar_doc_back']): ?><div><div class="field-label">Aadhaar - Back</div><div class="field-value"><a href="<?= UPLOAD_URL ?><?= e($p['aadhaar_doc_back']) ?>" target="_blank">View</a></div></div><?php endif; ?>
          <?php if ($p['pan_doc']): ?><div><div class="field-label">PAN Document</div><div class="field-value"><a href="<?= UPLOAD_URL ?><?= e($p['pan_doc']) ?>" target="_blank">View</a></div></div><?php endif; ?>
          <?php if ($p['live_lat'] && $p['live_lng']): ?>
            <div><div class="field-label">Live Location</div><div class="field-value"><?= e($p['live_lat']) ?>, <?= e($p['live_lng']) ?></div></div>
          <?php endif; ?>
        </div>
        <?php if ($districtGeoJsonAvailable && $districts): ?>
          <div class="cf-admin-map" id="cfAdminMap<?= (int) $p['id'] ?>" data-districts='<?= e(json_encode(array_column($districts, 'district_name'))) ?>'></div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($p['status'] === 'submitted'): ?>
          <div class="row-card-actions" style="margin-top:12px;">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-accent">Approve</button>
            </form>
            <form method="post" style="display:inline" data-confirm="Reject this submission?">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Reject</button>
            </form>
          </div>
        <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.cf-copy-link-btn');
  if (!btn) return;
  var input = document.getElementById(btn.getAttribute('data-target'));
  if (!input) return;
  navigator.clipboard.writeText(input.value).then(function () {
    var original = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(function () { btn.textContent = original; }, 1500);
  }).catch(function () {
    input.select();
  });
});

// One map per application, but only built the first time its <details>
// is actually opened - dozens of Leaflet maps sitting hidden in collapsed
// cards on page load would be wasteful (and Leaflet doesn't size correctly
// inside a zero-height container anyway).
var cfInitializedMaps = {};
function normalizeDistrictKey(name) {
  return (name || '').toString().trim().toUpperCase().replace(/[^A-Z]/g, '');
}
document.querySelectorAll('.cf-admin-map').forEach(function (mapDiv) {
  var details = mapDiv.closest('details');
  if (!details) return;
  details.addEventListener('toggle', function () {
    if (!details.open || cfInitializedMaps[mapDiv.id]) return;
    cfInitializedMaps[mapDiv.id] = true;

    var districts = JSON.parse(mapDiv.getAttribute('data-districts') || '[]');
    var map = L.map(mapDiv.id).setView([22.5, 79], 5);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap contributors &copy; CARTO', maxZoom: 19, subdomains: 'abcd'
    }).addTo(map);
    var layer = L.geoJSON(null, {
      style: { color: '#1f7a3d', weight: 2.5, fillColor: '#4caf50', fillOpacity: 0.35 }
    }).addTo(map);

    var remaining = districts.length;
    var allFeatures = [];
    if (!remaining) return;
    districts.forEach(function (d) {
      fetch('<?= APP_URL ?>/assets/data/districts/' + normalizeDistrictKey(d) + '.json')
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
  });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
