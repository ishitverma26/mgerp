<?php
/**
 * Shared helper functions used across every module.
 */

// Escape output safely for HTML
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Clean a raw POST/GET string input
function clean($value) {
    return trim(strip_tags((string) $value));
}

/**
 * Absolute URL (scheme + host + APP_URL + path) - needed anywhere a link
 * has to work outside this app's own pages, e.g. a C&F invite link shared
 * over WhatsApp/SMS to someone opening it on their own phone. Every other
 * internal link in the app just uses the relative APP_URL, which is fine
 * for links clicked from within the app itself.
 */
function fullUrl(string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . APP_URL . $path;
}

/**
 * A random password to hand a newly-approved partner - avoids visually
 * ambiguous characters (0/O, 1/l/I) since this typically gets read aloud or
 * typed off a screenshot rather than copy-pasted.
 */
function generateRandomPassword(int $length = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Icon set - Feather Icons (MIT), inlined so the app has zero external
 * asset dependency and keeps working with no internet on the plant floor.
 * Lives here (not header.php) so pages that don't load the full app shell,
 * like the login page, can still use it.
 */
function tabIcon($name) {
    $icons = [
        'home'          => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'truck'         => '<rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>',
        'refresh'       => '<polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
        'package'       => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'layers'        => '<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline>',
        'chart'         => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
        'plus'          => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>',
        'more'          => '<circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle>',
        'briefcase'     => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
        'archive'       => '<polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line>',
        'tag'           => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line>',
        'square'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>',
        'hash'          => '<line x1="4" y1="9" x2="20" y2="9"></line><line x1="4" y1="15" x2="20" y2="15"></line><line x1="10" y1="3" x2="8" y2="21"></line><line x1="16" y1="3" x2="14" y2="21"></line>',
        'user'          => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'dollar'        => '<text x="12" y="18" font-size="19" font-family="Arial, Helvetica, sans-serif" font-weight="700" text-anchor="middle" fill="currentColor" stroke="none">₹</text>',
        'clipboard'     => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>',
        'clock'         => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'edit'          => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>',
        'alert'         => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'check-square'  => '<polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
        'book'          => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
        'chevron'       => '<polyline points="6 9 12 15 18 9"></polyline>',
        'arrow-left'    => '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
        'arrow-right'   => '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>',
        'rotate-ccw'    => '<polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>',
        'camera'        => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle>',
        'settings'      => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
        'bell'          => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
        'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        'filter'        => '<line x1="21" y1="4" x2="14" y2="4"></line><line x1="10" y1="4" x2="3" y2="4"></line><line x1="21" y1="12" x2="12" y2="12"></line><line x1="8" y1="12" x2="3" y2="12"></line><line x1="21" y1="20" x2="16" y2="20"></line><line x1="12" y1="20" x2="3" y2="20"></line><line x1="14" y1="1" x2="14" y2="7"></line><line x1="8" y1="9" x2="8" y2="15"></line><line x1="16" y1="17" x2="16" y2="23"></line>',
        'eye'           => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
        'tool'          => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}

/**
 * Brand glyphs for the login page's "Follow us" row - separate from
 * tabIcon() because these are self-contained <svg> tags (not fragments),
 * some filled (WhatsApp has no official Feather-style outline glyph) rather
 * than all sharing tabIcon()'s single stroke-only wrapper.
 */
function socialIcon(string $name): string {
    $icons = [
        'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>',
        'facebook'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>',
        'youtube'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg>',
        'whatsapp'  => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"></path><path d="M12 2C6.478 2 2 6.478 2 12c0 1.977.578 3.822 1.578 5.372L2 22l4.762-1.545C8.267 21.43 10.083 22 12 22c5.522 0 10-4.478 10-10S17.522 2 12 2zm0 18c-1.79 0-3.478-.523-4.887-1.428l-.35-.219-3.027.982.99-2.955-.229-.362A7.945 7.945 0 0 1 4 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"></path></svg>',
        'website'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
    ];
    return $icons[$name] ?? '';
}

/**
 * Illustrated (not line-icon) graphics for things like the dashboard Quick
 * Actions tiles - unDraw illustrations (MIT-licensed, free for commercial
 * use, no attribution required: https://undraw.co/license), stored as
 * standalone files in assets/img/illustrations/ and inlined here (rather
 * than loaded via <img>) specifically so their internal
 * var(--primary-svg-color) can be recoloured per tile from our own CSS -
 * that variable can't be reached from outside an externally-referenced image.
 */
function tabIllustration(string $name): string {
    static $cache = [];
    if (!isset($cache[$name])) {
        $path = __DIR__ . '/../assets/img/illustrations/' . basename($name) . '.svg';
        $cache[$name] = is_file($path) ? file_get_contents($path) : '';
    }
    return $cache[$name];
}

function redirect($path) {
    // Preserve embed mode (see header.php's $embedMode) across the
    // post/redirect/get cycle, so a form submitted inside the Settings
    // page's Masters popup stays embedded instead of landing on the full
    // page (with its own sidebar/topbar) inside the small iframe.
    if (!empty($_GET['embed'])) {
        $path .= (strpos($path, '?') === false ? '?' : '&') . 'embed=1';
    }
    header('Location: ' . APP_URL . $path);
    exit;
}

// App-wide settings (company name, logo, ...) - a simple key/value store
// managed from the Admin Settings page, falling back to $default when unset.
function getSetting(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key=:k");
    $stmt->execute([':k' => $key]);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null && $value !== '' ? $value : $default;
}

function setSetting(PDO $pdo, string $key, string $value) {
    $stmt = $pdo->prepare(
        "INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = :v2"
    );
    $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

// One-time flash message stored in session, shown once then cleared
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Renders the flash message box (call this right after including header.php)
function renderFlash() {
    $flash = getFlash();
    if ($flash) {
        $class = $flash['type'] === 'error' ? 'danger' : $flash['type'];
        echo '<div class="alert alert-' . e($class) . ' alert-dismissible fade show" role="alert">'
           . e($flash['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// One-time modal popup - set from anywhere (usually right before a redirect),
// shown once on the next page load, then cleared. Used for things important
// enough to interrupt the user, not just a dismissible flash banner.
/**
 * $messageIsHtml: pass true only when $message was built server-side with
 * every dynamic piece already escaped via e() (e.g. highlighted task-name
 * pills) - default false keeps the plain-text auto-escaped behaviour every
 * existing caller relies on.
 */
function setModalPopup(string $title, string $message, string $icon = 'check-square', string $color = 'green', bool $messageIsHtml = false) {
    $_SESSION['modal_popup'] = ['title' => $title, 'message' => $message, 'icon' => $icon, 'color' => $color, 'html' => $messageIsHtml];
}

function renderModalPopup() {
    if (empty($_SESSION['modal_popup'])) return;
    $m = $_SESSION['modal_popup'];
    unset($_SESSION['modal_popup']);
    ?>
    <div class="modal-overlay" id="appModalPopup">
      <div class="modal-box">
        <div class="modal-box-head">
          <span class="icon-chip icon-chip-lg icon-chip-<?= e($m['color']) ?>"><?= tabIcon($m['icon']) ?></span>
          <h3><?= e($m['title']) ?></h3>
        </div>
        <p><?= !empty($m['html']) ? $m['message'] : e($m['message']) ?></p>
        <button type="button" class="btn btn-accent" onclick="var m=document.getElementById('appModalPopup');m.classList.add('is-closing');setTimeout(function(){m.remove();},200);">OK</button>
      </div>
    </div>
    <?php
}

/**
 * Validates and moves an uploaded photo (from a `<input type="file">` field)
 * into /uploads, returning the stored filename - or null if no file was
 * submitted (photos are always optional). Throws InvalidArgumentException
 * on an invalid file so callers can flash the message and re-show the form.
 */
function handlePhotoUpload(string $fieldName, string $prefix): ?string {
    if (empty($_FILES[$fieldName]['name'])) return null;
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Photo upload failed - try again.');
    }
    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Photo must be under 5MB.');
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array(mime_content_type($_FILES[$fieldName]['tmp_name']), $allowed, true)) {
        throw new InvalidArgumentException('Photo must be a JPG, PNG or WEBP image.');
    }
    $ext = pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = $prefix . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], UPLOAD_PATH . $filename)) {
        throw new InvalidArgumentException('Could not save the uploaded photo.');
    }
    return $filename;
}

/**
 * Same idea as handlePhotoUpload() but for KYC-style documents (GST/Aadhaar/
 * PAN) - also accepts a scanned PDF, not just images, since that's how most
 * of these get uploaded in practice. Pass $pdfOnly for a document that must
 * specifically be a PDF (e.g. a multi-page GST certificate, where a single
 * photo can't represent all the pages).
 */
function handleDocumentUpload(string $fieldName, string $prefix, bool $pdfOnly = false): ?string {
    if (empty($_FILES[$fieldName]['name'])) return null;
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Document upload failed - try again.');
    }
    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Document must be under 5MB.');
    }
    $allowed = $pdfOnly ? ['application/pdf'] : ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if (!in_array(mime_content_type($_FILES[$fieldName]['tmp_name']), $allowed, true)) {
        throw new InvalidArgumentException($pdfOnly ? 'Document must be a PDF file.' : 'Document must be a JPG, PNG, WEBP image or PDF.');
    }
    $ext = pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION) ?: 'pdf';
    $filename = $prefix . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], UPLOAD_PATH . $filename)) {
        throw new InvalidArgumentException('Could not save the uploaded document.');
    }
    return $filename;
}

/**
 * Where a given role's dashboard lives - a real lookup instead of deriving
 * the path from the role name (lowercase + spaces-to-hyphens), which breaks
 * for a name like "C&F" (the & isn't URL/filename-safe). Used by login.php's
 * post-login redirect and auth-check.php's 403 "back to dashboard" link.
 */
function roleDashboardUrl(string $roleName): string {
    $map = [
        'Admin' => '/dashboard/admin.php',
        'Plant Head' => '/dashboard/plant-head.php',
        'C&F' => '/dashboard/cf.php',
    ];
    return $map[$roleName] ?? '/dashboard/admin.php';
}

/**
 * Builds the batch-group data used by the "All Batches" card list - shared
 * between batch-list.php (the full page) and both dashboards, which embed
 * the exact same cards. Groups every production_batches row by its
 * batch_group_id, computes per-group progress/status, picks a badge colour
 * per token, and attaches the most recent Admin revision (if any) to each
 * item so the cards can show a "Revised" flag.
 *
 * Excludes target-less rows (target_bags IS NULL) - those only ever come
 * from Packing Update's on-the-fly batch-less packing (see the POST
 * handler in packing-update.php), never from Admin's own batch-create/
 * add-SKU flows which both require a target > 0. They're not something
 * Admin created or needs to manage/delete here - they just quietly feed
 * Live Stock (see buildLiveStock()), so they stay out of "All Batches"
 * entirely rather than showing up as a batch with nothing to do with it.
 *
 * Returns ['groups' => [...], 'revisions' => [...]] - pass both straight
 * into includes/partials/batch-cards.php.
 */
function buildBatchGroups(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT b.*, g.batch_no, g.created_at AS group_created_at, p.name AS product_name, p.size_kg, t.token_value,
                COALESCE((SELECT total_good_qty FROM packing_production_updates WHERE batch_id=b.id ORDER BY id DESC LIMIT 1), 0) AS completed
         FROM production_batches b
         JOIN batch_groups g ON g.id = b.batch_group_id
         JOIN products p ON p.id = b.product_id
         JOIN tokens t ON t.id = b.token_id
         WHERE b.target_bags IS NOT NULL
         ORDER BY g.id DESC, b.id ASC"
    )->fetchAll();

    $revisionStmt = $pdo->prepare(
        "SELECT old_value, new_value, created_at FROM audit_logs
         WHERE module='production_batches' AND record_id=:id AND action='update'
         ORDER BY id DESC LIMIT 1"
    );
    $revisions = [];
    foreach ($rows as $r) {
        if ($r['updated_at'] === null) continue;
        $revisionStmt->execute([':id' => $r['id']]);
        $log = $revisionStmt->fetch();
        if (!$log) continue;
        $old = json_decode($log['old_value'], true) ?: [];
        $new = json_decode($log['new_value'], true) ?: [];
        $delta = (isset($old['target_bags'], $new['target_bags'])) ? ((int) $new['target_bags'] - (int) $old['target_bags']) : null;
        $revisions[(int) $r['id']] = ['at' => $log['created_at'], 'delta' => $delta];
    }

    $badgeColors = ['amber', 'blue', 'green', 'purple', 'red'];

    // Once every SKU is done, status splits further by where the vehicle
    // dispatch (see batch_vehicle_assignments in includes/partials/
    // batch-cards.php) actually stands - not just "Completed".
    $vehicleCheckStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total, SUM(CASE WHEN status='dispatched' THEN 1 ELSE 0 END) AS dispatched
         FROM batch_vehicle_assignments WHERE batch_group_id = :id"
    );

    $groups = [];
    foreach ($rows as $r) {
        $gid = (int) $r['batch_group_id'];
        if (!isset($groups[$gid])) {
            $groups[$gid] = ['batch_no' => $r['batch_no'], 'created_at' => $r['group_created_at'], 'items' => []];
        }
        $groups[$gid]['items'][] = $r;
    }

    foreach ($groups as $gid => &$group) {
        $totalTarget = 0;
        $totalCompleted = 0;
        $totalMt = 0;
        $anyTarget = false;
        $allCompleted = true;
        $tokenColors = [];
        $colorIdx = 0;
        foreach ($group['items'] as &$item) {
            // Cap what's shown here at the target - anything produced beyond
            // it is surplus, already tracked separately as Live Stock, so it
            // shouldn't inflate this batch's own progress display. Carried-
            // forward bags count toward progress exactly like real packing -
            // they're added in before the cap, not on top of it.
            $item['completed'] = (int) $item['carried_forward_qty'] + (int) $item['completed'];
            if ($item['target_bags'] !== null) {
                $totalTarget += (int) $item['target_bags'];
                $anyTarget = true;
                $item['completed'] = min($item['completed'], (int) $item['target_bags']);
            }
            $totalCompleted += $item['completed'];
            // MT for the collapsed summary - target if it's set, otherwise
            // whatever's actually been produced so far.
            $mtQty = $item['target_bags'] !== null ? (int) $item['target_bags'] : $item['completed'];
            $totalMt += $mtQty * (float) $item['size_kg'] / 1000;
            if ($item['status'] !== 'completed') $allCompleted = false;
            if (!isset($tokenColors[$item['token_value']])) {
                $tokenColors[$item['token_value']] = $badgeColors[$colorIdx % count($badgeColors)];
                $colorIdx++;
            }
        }
        unset($item);
        $group['total_target'] = $anyTarget ? $totalTarget : null;
        $group['total_completed'] = $totalCompleted;
        $group['total_mt'] = $totalMt;
        $group['percent'] = $anyTarget && $totalTarget > 0 ? min(100, round($totalCompleted / $totalTarget * 100)) : 0;
        if (!$allCompleted) {
            $group['status_label'] = 'In Production';
            $group['status_pill'] = 'completed';
        } else {
            $vehicleCheckStmt->execute([':id' => $gid]);
            $vc = $vehicleCheckStmt->fetch();
            $totalVehicles = (int) $vc['total'];
            $dispatchedVehicles = (int) $vc['dispatched'];
            if ($totalVehicles > 0 && $dispatchedVehicles === $totalVehicles) {
                $group['status_label'] = 'Dispatched';
                $group['status_pill'] = 'active';
            } else {
                $group['status_label'] = 'Need Vehicle';
                $group['status_pill'] = 'pending';
            }
        }
        $group['token_colors'] = $tokenColors;
    }
    unset($group);

    return ['groups' => $groups, 'revisions' => $revisions];
}

/**
 * "Live Stock" = finished bags physically sitting around that aren't spoken
 * for by any batch's target - i.e. a completed batch's total produced minus
 * what it actually needed. E.g. a batch needing 200 bags that ended up with
 * 700 logged against it leaves 500 bags of surplus stock. Summed per
 * product+token across every completed batch, oldest surplus included.
 * ALSO includes still-open (active/reopened) batches that have no target at
 * all (target_bags IS NULL - see the auto-created batch in packing-update.php
 * for a product/token Admin never set up) - with nothing to net against,
 * everything packed on one of those counts as Live Stock as it's logged,
 * not just once "completed". Whatever a batch has already handed off as
 * carry-forward (live_stock_claimed_qty) is excluded either way, so
 * already-claimed surplus doesn't show as available twice.
 */
/**
 * How much of a target-bearing completed batch item is still "spoken for" -
 * normally just its own target_bags (the original plan Admin set). But once
 * every vehicle assigned to its batch group has actually been through Mark
 * Loaded (or dispatched) - none left sitting at "assigned"/"accepted" - the
 * true spoken-for amount becomes whatever actually got loaded onto those
 * vehicles instead, since that's the real final answer; anything above that
 * is genuine leftover and should show as Live Stock. A group with no
 * vehicles yet, or one still waiting on a pending vehicle that might yet
 * take more, keeps using target_bags unchanged - it can't be "leftover"
 * while something might still claim it. Deliberately not cached (unlike
 * tabIcon()'s static cache, which is safe because that data never changes
 * mid-request) - vehicle statuses are exactly what a caller might just have
 * changed moments earlier in the same request, and stale "still pending"
 * data here would misreport real stock.
 */
function vehicleAwareSpokenFor(PDO $pdo, int $targetBags, int $itemId, int $groupId): int {
    $vStmt = $pdo->prepare("SELECT id, status FROM batch_vehicle_assignments WHERE batch_group_id=:g");
    $vStmt->execute([':g' => $groupId]);
    $vehicles = $vStmt->fetchAll();
    $vehicleIds = array_column($vehicles, 'id');
    $pending = 0;
    foreach ($vehicles as $v) {
        if (in_array($v['status'], ['assigned', 'accepted'], true)) $pending++;
    }
    if (!$vehicleIds || $pending > 0) {
        return $targetBags;
    }
    $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(bags),0) FROM batch_vehicle_load_items WHERE vehicle_assignment_id IN ($placeholders) AND production_batch_id = ?");
    $stmt->execute([...$vehicleIds, $itemId]);
    return (int) $stmt->fetchColumn();
}

function buildLiveStock(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT b.id, b.batch_group_id, b.product_id, b.token_id, p.name AS product_name, p.size_kg, t.token_value, b.target_bags,
                b.carried_forward_qty, b.live_stock_claimed_qty,
                COALESCE((SELECT total_good_qty FROM packing_production_updates WHERE batch_id=b.id ORDER BY id DESC LIMIT 1), 0) AS completed
         FROM production_batches b
         JOIN products p ON p.id = b.product_id
         JOIN tokens t ON t.id = b.token_id
         WHERE (b.status = 'completed' AND b.target_bags IS NOT NULL)
            OR (b.target_bags IS NULL AND b.status IN ('active', 'reopened'))"
    )->fetchAll();

    $stock = [];
    foreach ($rows as $r) {
        $effectiveCompleted = (int) $r['carried_forward_qty'] + (int) $r['completed'];
        if ($r['target_bags'] !== null) {
            $spokenFor = vehicleAwareSpokenFor($pdo, (int) $r['target_bags'], (int) $r['id'], (int) $r['batch_group_id']);
            $surplus = $effectiveCompleted - $spokenFor - (int) $r['live_stock_claimed_qty'];
        } else {
            $surplus = $effectiveCompleted - (int) $r['live_stock_claimed_qty'];
        }
        if ($surplus <= 0) continue;
        $key = $r['product_id'] . '-' . $r['token_id'];
        if (!isset($stock[$key])) {
            $stock[$key] = [
                'product_name' => $r['product_name'],
                'size_kg' => $r['size_kg'],
                'token_value' => $r['token_value'],
                'qty' => 0,
            ];
        }
        $stock[$key]['qty'] += $surplus;
    }
    usort($stock, fn($a, $b) => $b['qty'] <=> $a['qty']);
    return array_values($stock);
}

/**
 * Per-batch breakdown of unclaimed Live Stock for one product+token, oldest
 * completed batch first (FIFO) - the source list claimLiveStock() consumes
 * from. Kept separate from buildLiveStock() because that one only needs to
 * report an aggregate qty per product+token for the Live Stock page, while
 * claiming needs to know exactly which batch(es) the bags come from.
 */
function getLiveStockSources(PDO $pdo, int $productId, int $tokenId): array {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.batch_group_id, g.batch_no, b.target_bags, b.carried_forward_qty, b.live_stock_claimed_qty,
                COALESCE((SELECT total_good_qty FROM packing_production_updates WHERE batch_id=b.id ORDER BY id DESC LIMIT 1), 0) AS completed
         FROM production_batches b
         JOIN batch_groups g ON g.id = b.batch_group_id
         WHERE ((b.status = 'completed' AND b.target_bags IS NOT NULL)
                OR (b.target_bags IS NULL AND b.status IN ('active', 'reopened')))
           AND b.product_id = :p AND b.token_id = :t
         ORDER BY b.id ASC"
    );
    $stmt->execute([':p' => $productId, ':t' => $tokenId]);

    $sources = [];
    foreach ($stmt->fetchAll() as $r) {
        $effectiveCompleted = (int) $r['carried_forward_qty'] + (int) $r['completed'];
        if ($r['target_bags'] !== null) {
            $spokenFor = vehicleAwareSpokenFor($pdo, (int) $r['target_bags'], (int) $r['id'], (int) $r['batch_group_id']);
            $available = $effectiveCompleted - $spokenFor - (int) $r['live_stock_claimed_qty'];
        } else {
            $available = $effectiveCompleted - (int) $r['live_stock_claimed_qty'];
        }
        if ($available > 0) {
            $sources[] = ['batch_id' => (int) $r['id'], 'batch_no' => $r['batch_no'], 'available' => $available];
        }
    }
    return $sources;
}

/**
 * Claims up to $neededQty of available Live Stock for a product+token,
 * FIFO (oldest completed batch's surplus first), marking the claimed
 * amount against each source batch's live_stock_claimed_qty so the same
 * surplus can't be claimed twice - and recording each source/qty pair in
 * live_stock_claims so the claim can be precisely reversed later if
 * $claimingBatchId itself ever gets deleted (see batch-list.php), even
 * when it drew from more than one source batch. Returns how much was
 * actually claimed (may be less than $neededQty if there isn't enough Live
 * Stock) and which batch number(s) it came from, for the "Carry Forward"
 * note. Caller is responsible for wrapping this in the same transaction as
 * whatever creates/updates the destination batch.
 */
function claimLiveStock(PDO $pdo, int $productId, int $tokenId, int $neededQty, int $claimingBatchId): array {
    if ($neededQty <= 0) {
        return ['claimed_qty' => 0, 'source_batch_nos' => []];
    }
    $sources = getLiveStockSources($pdo, $productId, $tokenId);
    $claimed = 0;
    $sourceBatchNos = [];
    $updateStmt = $pdo->prepare("UPDATE production_batches SET live_stock_claimed_qty = live_stock_claimed_qty + :take WHERE id = :id");
    $claimStmt = $pdo->prepare("INSERT INTO live_stock_claims (source_batch_id, claiming_batch_id, qty) VALUES (:s, :c, :q)");
    foreach ($sources as $src) {
        if ($claimed >= $neededQty) break;
        $take = min($src['available'], $neededQty - $claimed);
        if ($take <= 0) continue;
        $updateStmt->execute([':take' => $take, ':id' => $src['batch_id']]);
        $claimStmt->execute([':s' => $src['batch_id'], ':c' => $claimingBatchId, ':q' => $take]);
        $claimed += $take;
        $sourceBatchNos[] = $src['batch_no'];
    }
    return ['claimed_qty' => $claimed, 'source_batch_nos' => $sourceBatchNos];
}

/**
 * Applies a Live Stock claim to a freshly-inserted (or newly-added) SKU row:
 * stamps carried_forward_qty/carried_forward_from_batch_no on it, and if
 * the claim alone already covers the whole target, marks it completed
 * immediately (mirrors recordPackingProduction()'s own auto-complete step,
 * since a raw claim never goes through that function). Audit-logs the
 * claim as its own entry, separate from the SKU's create audit log.
 * Returns the claimed qty (0 if there was nothing to claim).
 */
function applyLiveStockCarryForward(PDO $pdo, int $batchId, int $productId, int $tokenId, int $targetBags, int $userId): int {
    $claim = claimLiveStock($pdo, $productId, $tokenId, $targetBags, $batchId);
    if ($claim['claimed_qty'] <= 0) {
        return 0;
    }
    $sourceLabel = implode(', ', $claim['source_batch_nos']);
    $nowCompleted = $claim['claimed_qty'] >= $targetBags;

    if ($nowCompleted) {
        $pdo->prepare("UPDATE production_batches SET carried_forward_qty=:qty, carried_forward_from_batch_no=:src, status='completed', completed_at=NOW() WHERE id=:id")
            ->execute([':qty' => $claim['claimed_qty'], ':src' => $sourceLabel, ':id' => $batchId]);
    } else {
        $pdo->prepare("UPDATE production_batches SET carried_forward_qty=:qty, carried_forward_from_batch_no=:src WHERE id=:id")
            ->execute([':qty' => $claim['claimed_qty'], ':src' => $sourceLabel, ':id' => $batchId]);
    }

    logAudit($pdo, $userId, 'carry_forward', 'production_batches', $batchId, null, [
        'carried_forward_qty' => $claim['claimed_qty'],
        'carried_forward_from_batch_no' => $sourceLabel,
    ]);
    if ($nowCompleted) {
        logAudit($pdo, $userId, 'auto_complete', 'production_batches', $batchId, ['status' => 'active'], ['status' => 'completed']);
    }

    return $claim['claimed_qty'];
}

/**
 * Undoes whatever Live Stock claim(s) $batchId made via claimLiveStock(),
 * using the exact per-source amounts recorded in live_stock_claims -
 * giving each source batch's live_stock_claimed_qty back so that stock
 * becomes available again. Call this BEFORE deleting a production_batches
 * row (see batch-list.php) so a deleted batch never leaves stock it
 * claimed permanently stuck/unavailable. A batch with no claims (the
 * common case) is a no-op. Caller is responsible for wrapping this in the
 * same transaction as the delete itself.
 */
function reverseLiveStockClaims(PDO $pdo, int $batchId): void {
    $stmt = $pdo->prepare("SELECT source_batch_id, qty FROM live_stock_claims WHERE claiming_batch_id = :id");
    $stmt->execute([':id' => $batchId]);
    $giveBackStmt = $pdo->prepare(
        "UPDATE production_batches SET live_stock_claimed_qty = GREATEST(0, live_stock_claimed_qty - :qty) WHERE id = :id"
    );
    foreach ($stmt->fetchAll() as $claim) {
        $giveBackStmt->execute([':qty' => (int) $claim['qty'], ':id' => (int) $claim['source_batch_id']]);
    }
    $pdo->prepare("DELETE FROM live_stock_claims WHERE claiming_batch_id = :id")->execute([':id' => $batchId]);
}

/**
 * How many bags of each SKU in a batch group are actually available to put
 * on a vehicle right now: what's ready (carried-forward + real packing,
 * capped at target) minus whatever's already committed to OTHER vehicle
 * assignments for that same SKU (any status - even just "assigned" already
 * spoken for). Pass $excludeVehicleId when editing an existing assignment
 * so its own current load doesn't count against itself. Returns
 * [production_batch_id => ['size_kg'=>, 'available'=>, 'label'=>]].
 */
function batchSkuAvailability(PDO $pdo, int $groupId, int $excludeVehicleId = 0): array {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.target_bags, b.carried_forward_qty, p.name AS product_name, p.size_kg, t.token_value,
                COALESCE((SELECT total_good_qty FROM packing_production_updates WHERE batch_id=b.id ORDER BY id DESC LIMIT 1), 0) AS packed,
                COALESCE((SELECT SUM(bags) FROM batch_vehicle_load_items WHERE production_batch_id=b.id AND vehicle_assignment_id != :ex), 0) AS already_assigned
         FROM production_batches b
         JOIN products p ON p.id = b.product_id
         JOIN tokens t ON t.id = b.token_id
         WHERE b.batch_group_id = :g"
    );
    $stmt->execute([':ex' => $excludeVehicleId, ':g' => $groupId]);
    $skus = [];
    foreach ($stmt->fetchAll() as $row) {
        $completed = (int) $row['carried_forward_qty'] + (int) $row['packed'];
        if ($row['target_bags'] !== null) $completed = min($completed, (int) $row['target_bags']);
        $skus[(int) $row['id']] = [
            'size_kg' => (float) $row['size_kg'],
            'available' => max(0, $completed - (int) $row['already_assigned']),
            'label' => $row['product_name'] . ' ' . $row['size_kg'] . 'KG (Token ' . $row['token_value'] . ')',
        ];
    }
    return $skus;
}

/**
 * Removes a SKU/batch item on Admin's request - except when it has real
 * completed production (packing_production_updates and/or carry-forward),
 * which the FK on packing_production_updates.batch_id would refuse to
 * hard-delete anyway, and which shouldn't just vanish either way. In that
 * case, releases it instead: strips target_bags to NULL and resets status
 * to 'active' - the same target-less "everything is surplus" state
 * batch-less packing already uses (see buildLiveStock()) - so its packed
 * stock becomes available Live Stock instead of being lost, and it drops
 * out of "All Batches" (buildBatchGroups() only shows target_bags IS NOT
 * NULL rows). Returns true if released, false if actually deleted. Caller
 * should call reverseLiveStockClaims() on this same id first and wrap both
 * in one transaction.
 */
function releaseOrDeleteBatchItem(PDO $pdo, array $item): bool {
    $completedStmt = $pdo->prepare("SELECT total_good_qty FROM packing_production_updates WHERE batch_id=:id ORDER BY id DESC LIMIT 1");
    $completedStmt->execute([':id' => $item['id']]);
    $completed = (int) ($completedStmt->fetchColumn() ?: 0) + (int) $item['carried_forward_qty'];

    if ($completed > 0) {
        $pdo->prepare("UPDATE production_batches SET target_bags=NULL, status='active', completed_at=NULL, updated_at=NOW() WHERE id=:id")
            ->execute([':id' => $item['id']]);
        return true;
    }
    $pdo->prepare("DELETE FROM production_batches WHERE id=:id")->execute([':id' => $item['id']]);
    return false;
}

/**
 * One consolidated record for every kind of "damage" in the app - packed
 * (finished) cement bags damaged during packing/loading, AND empty jumbo
 * bags damaged in Utilities > Empty Jumbo Bags. Different tables, different
 * workflows, merged here into one sorted list (each row tagged with where
 * it came from) so both the Plant Head-facing Damage Record and Admin's
 * Damage Report show the exact same thing instead of drifting apart.
 * There's no separate "log damage" form anymore - every row here was
 * created at the point damage was actually found (Damage Entry's own form
 * for line damage, or the Damaged field on a vehicle's Mark Loading step),
 * this is read-only.
 */
function buildDamageRecord(PDO $pdo): array {
    $packingDamage = $pdo->query(
        "SELECT d.*, g.batch_no, p.name AS product_name, p.size_kg, t.token_value,
            (SELECT reprocessing_qty FROM reprocessing_entries WHERE damage_id=d.id ORDER BY id DESC LIMIT 1) AS reprocess_qty,
            (SELECT garbage_qty FROM garbage_entries WHERE damage_id=d.id ORDER BY id DESC LIMIT 1) AS garbage_qty
         FROM damage_entries d
         JOIN production_batches b ON b.id=d.batch_id
         JOIN batch_groups g ON g.id=b.batch_group_id
         JOIN products p ON p.id=b.product_id
         JOIN tokens t ON t.id=b.token_id
         ORDER BY d.id DESC"
    )->fetchAll();

    $jumboDamage = $pdo->query(
        "SELECT e.*, u.name AS user_name
         FROM empty_jumbo_transactions e
         JOIN users u ON u.id = e.created_by
         WHERE e.transaction_type = 'damaged'
         ORDER BY e.id DESC"
    )->fetchAll();

    $rows = [];
    foreach ($packingDamage as $r) {
        $rows[] = [
            'source' => 'packing',
            'sort_key' => $r['damage_date'] . ' ' . str_pad($r['id'], 10, '0', STR_PAD_LEFT),
            'date' => $r['damage_date'],
            'heading' => $r['batch_no'],
            'sub' => $r['product_name'] . ' - ' . (int) $r['damage_qty'] . ' damaged',
            'pill_label' => ucfirst($r['action_status']),
            'pill_class' => $r['action_status'] === 'garbage' ? 'garbage' : ($r['action_status'] === 'pending' ? 'pending' : 'active'),
            'fields' => [
                'Bag Size' => $r['size_kg'] . ' KG',
                'Token' => $r['token_value'],
                'Reason' => $r['reason'] ?: '-',
                'Reprocessed' => $r['reprocess_qty'] !== null ? (int) $r['reprocess_qty'] : '-',
                'Garbage' => $r['garbage_qty'] !== null ? (int) $r['garbage_qty'] : '-',
            ],
        ];
    }
    foreach ($jumboDamage as $r) {
        $rows[] = [
            'source' => 'jumbo',
            'sort_key' => $r['transaction_date'] . ' ' . str_pad($r['id'], 10, '0', STR_PAD_LEFT),
            'date' => $r['transaction_date'],
            'heading' => (int) $r['bags'] . ' bags',
            'sub' => 'Empty Jumbo Bags',
            'pill_label' => 'Damaged',
            'pill_class' => 'garbage',
            'fields' => [
                'Bags' => (int) $r['bags'],
                'Remarks' => $r['remarks'] ?: '-',
                'Logged By' => $r['user_name'],
            ],
        ];
    }
    usort($rows, fn($a, $b) => strcmp($b['sort_key'], $a['sort_key']));
    return $rows;
}

/**
 * MT recovered from damaged packed bags, counted as Processing output (not
 * Packing) - a damaged bag's cement doesn't consume any fresh raw material,
 * so it can't become a real processing_requests row (that table requires a
 * specific raw_material_id and real lot consumption). Computed on the fly
 * from reprocessing_entries instead of stored anywhere, and added into the
 * "MT Processed" figure on both dashboards wherever that's shown.
 */
function getRecoveredDamageMt(PDO $pdo): float {
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(r.reprocessing_qty * p.size_kg / 1000), 0)
         FROM reprocessing_entries r
         JOIN damage_entries d ON d.id = r.damage_id
         JOIN production_batches b ON b.id = d.batch_id
         JOIN products p ON p.id = b.product_id"
    )->fetchColumn();
}

/**
 * Generates the next sequential Lot Number, e.g. LOT-001, LOT-002 ...
 * Locks against the existing max so two simultaneous inward entries
 * never collide (call this inside a transaction).
 */
function generateLotNo(PDO $pdo) {
    $stmt = $pdo->query("SELECT lot_no FROM raw_material_inward ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last) {
        $next = (int) substr($last, 4) + 1;
    }
    return 'LOT-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

/**
 * Generates the next sequential Batch Number - a plain number continuing
 * from the plant's existing tracking (starts at 1375). A batch is a
 * container that can hold several SKUs (see batch_groups table).
 */
function generateBatchNo(PDO $pdo) {
    $stmt = $pdo->query("SELECT batch_no FROM batch_groups ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last !== false && ctype_digit((string) $last)) {
        return (string) ((int) $last + 1);
    }
    return '1375';
}

/**
 * Finds the oldest still-open (active/reopened) production_batches row for
 * a given product+token combo - used both by Packing Update's product/token
 * selector and by FIFO rollover after a batch completes, so batches with
 * the same product+token fill up in creation order rather than the newest
 * always winning while an older one sits unfulfilled. Never creates one -
 * batches only ever come from Admin's Batch Management screen.
 */
function findActiveBatch(PDO $pdo, int $productId, int $tokenId): int {
    $stmt = $pdo->prepare(
        "SELECT id FROM production_batches WHERE product_id=:p AND token_id=:t AND status IN ('active','reopened') ORDER BY id ASC LIMIT 1"
    );
    $stmt->execute([':p' => $productId, ':t' => $tokenId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Records one cumulative Left/Right nozzle reading for a batch line item -
 * the single source of truth for saving packing production, used by both
 * the full Packing Update entry form and the quick-log widget on the batch
 * list card. Inserts the snapshot, logs the stock movement, auto-completes
 * the batch if its target is now met, and reports which batch to land on
 * next (the same one, or the next FIFO batch for the same product+token if
 * this one just completed).
 *
 * $batch must be a full production_batches row (id, batch_no, product_id,
 * token_id, target_bags, status). Throws InvalidArgumentException if the
 * totals don't make sense; callers should catch and flash the message.
 */
function recordPackingProduction(PDO $pdo, array $batch, int $left, int $right, int $damage, string $updateDatetime, int $userId, ?string $photo = null): array {
    $batchId = (int) $batch['id'];

    // Packing bags cement ground from raw material - it can't start before
    // any raw material has actually been processed at all.
    $hasProcessing = (int) $pdo->query("SELECT COUNT(*) FROM processing_requests")->fetchColumn() > 0;
    if (!$hasProcessing) {
        setModalPopup(
            'Cannot Save Entry',
            "No raw material has been processed yet - how are you packing this? Process it first via New Processing, then come back to log packing.",
            'alert', 'red'
        );
        throw new InvalidArgumentException('Process raw material first via New Processing before logging packing production.');
    }

    if ($left < 0 || $right < 0 || $updateDatetime === '') {
        throw new InvalidArgumentException('Enter valid Left/Right nozzle totals and the update time.');
    }

    $lastStmt = $pdo->prepare("SELECT * FROM packing_production_updates WHERE batch_id=:id ORDER BY id DESC LIMIT 1");
    $lastStmt->execute([':id' => $batchId]);
    $last = $lastStmt->fetch();
    $lastTotal = $last ? (int) $last['total_good_qty'] : 0;
    $totalGood = $left + $right;

    if ($totalGood < $lastTotal) {
        throw new InvalidArgumentException("Cumulative total ($totalGood) cannot be lower than the last recorded total ($lastTotal). Enter the current running total, not an increment.");
    }

    $increase = $totalGood - $lastTotal;
    $hasTarget = $batch['target_bags'] !== null;
    $carriedForward = (int) ($batch['carried_forward_qty'] ?? 0);
    // Carried-forward bags already count toward the target (see
    // buildBatchGroups()), so what's still needed from real packing is the
    // target minus whatever carry-forward already covered.
    $remainingTarget = $hasTarget ? max(0, (int) $batch['target_bags'] - $carriedForward - $totalGood) : 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO packing_production_updates
                (batch_id, update_datetime, left_nozzle_qty, right_nozzle_qty, total_good_qty,
                 increase_since_last, damage_qty_cumulative, remaining_target, photo, user_id)
             VALUES (:batch_id, :dt, :left, :right, :total, :increase, :damage, :remaining, :photo, :user_id)"
        );
        $stmt->execute([
            ':batch_id' => $batchId, ':dt' => $updateDatetime, ':left' => $left, ':right' => $right,
            ':total' => $totalGood, ':increase' => $increase, ':damage' => $damage,
            ':remaining' => $remainingTarget, ':photo' => $photo, ':user_id' => $userId,
        ]);
        $updateId = (int) $pdo->lastInsertId();

        if ($increase > 0) {
            logStockMovement(
                $pdo, 'packing_finish', 'packing_production_updates', $updateId, 'finished',
                $increase, 'Bags', $lastTotal, $totalGood, $userId,
                'Batch ' . $batch['batch_no'] . ' packing update'
            );
        }

        $batchCompleted = false;
        if ($hasTarget && ($carriedForward + $totalGood) >= $batch['target_bags'] && $batch['status'] !== 'completed') {
            $pdo->prepare("UPDATE production_batches SET status='completed', completed_at=NOW() WHERE id=:id")
                ->execute([':id' => $batchId]);
            logAudit($pdo, $userId, 'auto_complete', 'production_batches', $batchId,
                ['status' => $batch['status']], ['status' => 'completed']);
            $batchCompleted = true;
            setModalPopup(
                'Batch ' . $batch['batch_no'] . ' Complete',
                'Please contact Admin for the transport.',
                'check-square', 'green'
            );
        }

        logAudit($pdo, $userId, 'create', 'packing_production_updates', $updateId, null, [
            'batch_id' => $batchId, 'left' => $left, 'right' => $right, 'total_good' => $totalGood,
        ]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $nextBatchId = $batchId;
    $rolledOver = false;
    if ($batchCompleted) {
        $next = findActiveBatch($pdo, (int) $batch['product_id'], (int) $batch['token_id']);
        if ($next > 0) {
            $nextBatchId = $next;
            $rolledOver = true;
        }
    }

    return [
        'completed' => $batchCompleted,
        'rolled_over' => $rolledOver,
        'next_batch_id' => $nextBatchId,
        'increase' => $increase,
        'total' => $totalGood,
    ];
}

/**
 * Total finished-goods Metric Tons packed between two dates (inclusive),
 * summed across every product/token line - each production update's
 * increase_since_last converted via that update's own product size_kg, so
 * a mixed day of e.g. 50KG and 25KG bags still adds up correctly. Backs the
 * Daily/Monthly Target progress bars on both dashboards.
 */
function getPackedMt(PDO $pdo, string $from, string $to): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(pu.increase_since_last * p.size_kg), 0) / 1000
         FROM packing_production_updates pu
         JOIN production_batches b ON b.id = pu.batch_id
         JOIN products p ON p.id = b.product_id
         WHERE DATE(pu.update_datetime) BETWEEN :from AND :to"
    );
    $stmt->execute([':from' => $from, ':to' => $to]);
    return (float) $stmt->fetchColumn();
}

function formatDateTime($value) {
    if (!$value) return '-';
    return date('d-M-Y h:i A', strtotime($value));
}

function formatDate($value) {
    if (!$value) return '-';
    return date('d-M-Y', strtotime($value));
}

/** Pill colour class (suffix of .pill-*) for a freight/material payment status - pending/partial/paid. */
function paymentStatusPillClass(string $status): string {
    $map = ['pending' => 'pending', 'partial' => 'completed', 'paid' => 'active'];
    return $map[$status] ?? 'pending';
}

function paymentStatusLabel(string $status): string {
    return ucfirst($status);
}
