<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_company') {
        $name = clean($_POST['company_name'] ?? '');
        if ($name === '') {
            setFlash('error', 'Company name cannot be empty.');
            redirect('/modules/admin/settings.php');
        }

        try {
            $oldName = getSetting($pdo, 'company_name', APP_NAME);
            setSetting($pdo, 'company_name', $name);

            $logo = handlePhotoUpload('company_logo', 'logo');
            if ($logo) {
                setSetting($pdo, 'company_logo', $logo);
            }

            logAudit($pdo, $currentUser['id'], 'update', 'app_settings', 0, ['company_name' => $oldName], ['company_name' => $name]);
            setFlash('success', 'Company profile updated.');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'save_cf_terms') {
        $terms = clean($_POST['cf_terms'] ?? '');
        setSetting($pdo, 'cf_terms_conditions', $terms);
        logAudit($pdo, $currentUser['id'], 'update', 'app_settings', 0, null, ['cf_terms_conditions' => $terms]);
        setFlash('success', 'C&F Terms & Conditions updated.');
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'save_social') {
        $links = [
            'social_instagram' => clean($_POST['social_instagram'] ?? ''),
            'social_facebook'  => clean($_POST['social_facebook'] ?? ''),
            'social_youtube'   => clean($_POST['social_youtube'] ?? ''),
            'social_whatsapp'  => clean($_POST['social_whatsapp'] ?? ''),
            'social_website'   => clean($_POST['social_website'] ?? ''),
        ];
        foreach ($links as $key => $value) {
            setSetting($pdo, $key, $value);
        }
        logAudit($pdo, $currentUser['id'], 'update', 'app_settings', 0, null, $links);
        setFlash('success', 'Social media links updated.');
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'save_targets') {
        $daily = $_POST['daily_mt_target'] ?? '';
        $monthly = $_POST['monthly_mt_target'] ?? '';
        if (!is_numeric($daily) || $daily < 0 || !is_numeric($monthly) || $monthly < 0) {
            setFlash('error', 'Enter valid daily and monthly MT targets.');
        } else {
            setSetting($pdo, 'daily_mt_target', $daily);
            setSetting($pdo, 'monthly_mt_target', $monthly);
            logAudit($pdo, $currentUser['id'], 'update', 'app_settings', 0, null, [
                'daily_mt_target' => $daily, 'monthly_mt_target' => $monthly,
            ]);
            setFlash('success', 'Production targets updated.');
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=:id");
        $stmt->execute([':id' => $currentUser['id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New password and confirmation do not match.');
        } else {
            $pdo->prepare("UPDATE users SET password=:p WHERE id=:id")
                ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => $currentUser['id']]);
            logAudit($pdo, $currentUser['id'], 'change_password', 'users', $currentUser['id'], null, null);
            setFlash('success', 'Password changed successfully.');
        }
        redirect('/modules/admin/settings.php');
    }
}

$companyName = getSetting($pdo, 'company_name', APP_NAME);
$companyLogo = getSetting($pdo, 'company_logo', null);
$dailyMtTarget = (float) getSetting($pdo, 'daily_mt_target', 0);
$monthlyMtTarget = (float) getSetting($pdo, 'monthly_mt_target', 0);
$socialInstagram = getSetting($pdo, 'social_instagram', '');
$socialFacebook = getSetting($pdo, 'social_facebook', '');
$socialYoutube = getSetting($pdo, 'social_youtube', '');
$socialWhatsapp = getSetting($pdo, 'social_whatsapp', '');
$socialWebsite = getSetting($pdo, 'social_website', '');
$cfTerms = getSetting($pdo, 'cf_terms_conditions', '');

$pageTitle = 'Settings';
$activeMenu = 'settings';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="section-head"><h3><span class="icon-chip icon-chip-blue"><?= tabIcon('layers') ?></span>Masters</h3></div>
<p class="help-text" style="margin-top:-6px;">Manage the reference data used across the app. Tap a tile to open it right here - no page change.</p>
<div class="quick-actions-grid">
  <button type="button" class="action-tile action-tile-green glass-tile" data-title="Vendors" data-url="<?= APP_URL ?>/modules/admin/vendors.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Vendors</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-green"><?= tabIcon('briefcase') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-blue glass-tile" data-title="Raw Materials" data-url="<?= APP_URL ?>/modules/admin/raw-materials.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Raw Materials</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-blue"><?= tabIcon('archive') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-amber glass-tile" data-title="Products" data-url="<?= APP_URL ?>/modules/admin/products.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Products</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-amber"><?= tabIcon('tag') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-purple glass-tile" data-title="Tokens" data-url="<?= APP_URL ?>/modules/admin/tokens.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Tokens</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-purple"><?= tabIcon('hash') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-red glass-tile" data-title="Users" data-url="<?= APP_URL ?>/modules/admin/users.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Users</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-red"><?= tabIcon('user') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-blue glass-tile" data-title="Tasks" data-url="<?= APP_URL ?>/modules/admin/tasks.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Tasks</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-blue"><?= tabIcon('check-square') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-green glass-tile" data-title="Labour" data-url="<?= APP_URL ?>/modules/admin/labour.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">Labour</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-green"><?= tabIcon('user') ?></span></span>
  </button>
  <button type="button" class="action-tile action-tile-purple glass-tile" data-title="C&amp;F Partners" data-url="<?= APP_URL ?>/modules/admin/cf-partners.php?embed=1">
    <span class="action-tile-bottom"><span class="action-tile-label">C&amp;F Partners</span><?= tabIcon('arrow-right') ?></span>
    <span class="action-tile-icon"><span class="icon-chip icon-chip-lg icon-chip-purple"><?= tabIcon('briefcase') ?></span></span>
  </button>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-amber" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('tag') ?></span>Company Profile</h3>
  <p class="help-text">The name and logo shown across the app's sidebar and page titles.</p>
  <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_company">
    <div class="form-row">
      <div>
        <label>Company Name *</label>
        <input type="text" name="company_name" required value="<?= e($companyName) ?>">
      </div>
      <div>
        <label>Logo</label>
        <input type="file" name="company_logo" accept="image/*">
        <?php if ($companyLogo): ?>
          <p class="help-text">Current: <img src="<?= UPLOAD_URL ?><?= e($companyLogo) ?>" alt="Logo" style="height:26px;vertical-align:middle;border-radius:6px;margin-left:4px;"></p>
        <?php endif; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Save</button>
  </form>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('layers') ?></span>Social Media Links</h3>
  <p class="help-text">Shown as a "Follow us" row on the login screen. Leave a field blank to hide that icon - only the ones filled in show up.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save_social">
    <div class="social-field-list">
      <div class="social-field">
        <label class="social-field-label"><span class="social-field-icon social-field-icon-instagram"><?= socialIcon('instagram') ?></span>Instagram</label>
        <input type="url" name="social_instagram" placeholder="https://instagram.com/..." value="<?= e($socialInstagram) ?>">
      </div>
      <div class="social-field">
        <label class="social-field-label"><span class="social-field-icon social-field-icon-facebook"><?= socialIcon('facebook') ?></span>Facebook</label>
        <input type="url" name="social_facebook" placeholder="https://facebook.com/..." value="<?= e($socialFacebook) ?>">
      </div>
      <div class="social-field">
        <label class="social-field-label"><span class="social-field-icon social-field-icon-youtube"><?= socialIcon('youtube') ?></span>YouTube</label>
        <input type="url" name="social_youtube" placeholder="https://youtube.com/..." value="<?= e($socialYoutube) ?>">
      </div>
      <div class="social-field">
        <label class="social-field-label"><span class="social-field-icon social-field-icon-whatsapp"><?= socialIcon('whatsapp') ?></span>WhatsApp</label>
        <input type="url" name="social_whatsapp" placeholder="https://wa.me/91..." value="<?= e($socialWhatsapp) ?>">
      </div>
      <div class="social-field">
        <label class="social-field-label"><span class="social-field-icon social-field-icon-website"><?= socialIcon('website') ?></span>Website</label>
        <input type="url" name="social_website" placeholder="https://..." value="<?= e($socialWebsite) ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Save Social Links</button>
  </form>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('clipboard') ?></span>C&amp;F Terms &amp; Conditions</h3>
  <p class="help-text">Shown to every applicant on the C&amp;F onboarding form, with a required checkbox to accept before they can submit.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save_cf_terms">
    <textarea name="cf_terms" rows="8" placeholder="Enter the Terms &amp; Conditions text a C&amp;F applicant must accept..."><?= e($cfTerms) ?></textarea>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Save Terms &amp; Conditions</button>
  </form>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-green" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('chart') ?></span>Production Targets</h3>
  <p class="help-text">Daily and monthly Metric Ton targets - shown as a progress bar on both dashboards as packing production comes in.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save_targets">
    <div class="form-row">
      <div><label>Daily Target (MT)</label><input type="number" step="0.01" min="0" name="daily_mt_target" value="<?= e($dailyMtTarget) ?>"></div>
      <div><label>Monthly Target (MT)</label><input type="number" step="0.01" min="0" name="monthly_mt_target" value="<?= e($monthlyMtTarget) ?>"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Save Targets</button>
  </form>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('settings') ?></span>Change Password</h3>
  <p class="help-text">Update your own login password.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="change_password">
    <div class="form-row">
      <div><label>Current Password *</label><input type="password" name="current_password" required></div>
      <div><label>New Password *</label><input type="password" name="new_password" required minlength="6"></div>
      <div><label>Confirm New Password *</label><input type="password" name="confirm_password" required minlength="6"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Change Password</button>
  </form>
</div>

<div class="masters-modal-overlay" id="mastersModalOverlay">
  <div class="masters-modal">
    <div class="masters-modal-head">
      <span id="mastersModalTitle">Master</span>
      <button type="button" class="masters-modal-close" id="mastersModalClose" aria-label="Close">&times;</button>
    </div>
    <iframe class="masters-modal-frame" id="mastersModalFrame" src="about:blank" title="Master data"></iframe>
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
