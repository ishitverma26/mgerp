<?php
/**
 * Renders the notification bottom-sheet (bell icon panel) and its trigger
 * wiring. Expects $notifications (from getRecentNotifications(), which
 * already carries each item's 'is_read' for the current user) to be set
 * by the including page. Binds to a #notifBellBtn that each dashboard's
 * welcome-hero defines itself, since its neighbouring icons differ per
 * role (Admin also has a Settings icon, Plant Head doesn't).
 *
 * Marking read calls modules/notifications/mark.php (notification_states.
 * is_read) - it never touches the underlying audit_logs row other pages
 * rely on.
 */
?>
<div class="masters-modal-overlay" id="notifModalOverlay">
  <div class="masters-modal">
    <div class="masters-modal-head">
      <span>Notifications</span>
      <div style="display:flex;align-items:center;gap:8px;">
        <button type="button" class="btn btn-sm btn-tint-blue" id="notifReadAllBtn">Read All</button>
        <button type="button" class="masters-modal-close" id="notifModalClose" aria-label="Close">&times;</button>
      </div>
    </div>
    <div class="notif-modal-body" id="notifModalBody">
      <?php if (!$notifications): ?>
        <p class="row-card-empty">No activity yet.</p>
      <?php else: ?>
        <?php foreach ($notifications as $n): ?>
          <div class="notif-item<?= $n['is_read'] ? '' : ' notif-item-unread' ?>" data-id="<?= (int) $n['id'] ?>">
            <a href="<?= e($n['url']) ?>" class="notif-item-link">
              <div class="notif-item-title"><?= e($n['summary']) ?></div>
              <div class="notif-item-meta"><?= e($n['module']) ?> &middot; <?= e($n['user_name']) ?> &middot; <?= formatDateTime($n['created_at']) ?></div>
            </a>
            <div class="notif-item-actions">
              <button type="button" class="notif-item-read-btn" data-id="<?= (int) $n['id'] ?>" title="Mark read" aria-label="Mark read"<?= $n['is_read'] ? ' style="display:none;"' : '' ?>><?= tabIcon('check-square') ?></button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Looked up on DOMContentLoaded, not immediately - this partial's own
  // <script> can run before #notifBellBtn exists in the DOM depending on
  // where each page places the bell button relative to where it includes
  // this partial (e.g. the Admin dashboard's bell sits inside "Today's
  // Snapshot", further down the page than this include).
  var btn = document.getElementById('notifBellBtn');
  var overlay = document.getElementById('notifModalOverlay');
  var closeBtn = document.getElementById('notifModalClose');
  var body = document.getElementById('notifModalBody');
  var readAllBtn = document.getElementById('notifReadAllBtn');
  var bellBadge = btn ? btn.querySelector('.notif-badge') : null;
  if (!btn || !overlay) return;

  function post(action, extra) {
    var data = new URLSearchParams(Object.assign({ action: action }, extra || {}));
    return fetch('<?= APP_URL ?>/modules/notifications/mark.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data.toString(),
    });
  }

  function refreshBadge() {
    var unread = body.querySelectorAll('.notif-item-unread').length;
    if (!bellBadge) return;
    if (unread > 0) {
      bellBadge.textContent = unread > 9 ? '9+' : unread;
      bellBadge.style.display = '';
    } else {
      bellBadge.style.display = 'none';
    }
  }

  btn.addEventListener('click', function () { overlay.classList.add('is-open'); });
  closeBtn.addEventListener('click', function () { overlay.classList.remove('is-open'); });
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.classList.remove('is-open'); });

  body.addEventListener('click', function (e) {
    var readBtn = e.target.closest('.notif-item-read-btn');
    if (readBtn) {
      var item = readBtn.closest('.notif-item');
      post('mark_read', { id: readBtn.getAttribute('data-id') });
      item.classList.remove('notif-item-unread');
      readBtn.style.display = 'none';
      refreshBadge();
    }
  });

  if (readAllBtn) {
    readAllBtn.addEventListener('click', function () {
      var ids = Array.prototype.map.call(body.querySelectorAll('.notif-item'), function (el) { return el.getAttribute('data-id'); });
      if (!ids.length) return;
      post('read_all', { ids: JSON.stringify(ids) });
      body.querySelectorAll('.notif-item-unread').forEach(function (el) { el.classList.remove('notif-item-unread'); });
      body.querySelectorAll('.notif-item-read-btn').forEach(function (el) { el.style.display = 'none'; });
      refreshBadge();
    });
  }
});
</script>
