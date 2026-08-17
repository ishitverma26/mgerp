<?php
/**
 * Shared page header / sidebar.
 * Every page must set $pageTitle (and optionally $activeMenu) BEFORE
 * including this file. Must be included AFTER auth-check.php so
 * $currentUser is already available.
 */
$isAdmin = $currentUser['role_name'] === 'Admin';
$isCf = $currentUser['role_name'] === 'C&F';
$roleClass = $isAdmin ? 'admin' : ($isCf ? 'cf' : 'plant');
$activeMenu = $activeMenu ?? '';
// Embed mode strips the sidebar/bottom-tabbar/page-header chrome down to
// just the page's own content, for loading a page inside an iframe (e.g.
// the Settings page's Masters popups) without a nested duplicate app shell.
$embedMode = !empty($_GET['embed']);
$companyName = getSetting($pdo, 'company_name', APP_NAME);
$companyLogo = getSetting($pdo, 'company_logo', null);

function navLink($url, $label, $key, $activeMenu, $icon = null) {
    $cls = $activeMenu === $key ? 'nav-link active' : 'nav-link';
    $iconHtml = $icon ? tabIcon($icon) : '';
    echo '<a class="' . $cls . '" href="' . APP_URL . $url . '">' . $iconHtml . '<span>' . e($label) . '</span></a>';
}
function tabLink($url, $label, $icon, $isActive) {
    $cls = $isActive ? 'tab-link active' : 'tab-link';
    echo '<a class="' . $cls . '" href="' . APP_URL . $url . '">' . tabIcon($icon) . '<span>' . e($label) . '</span></a>';
}

if ($isAdmin) {
    $bottomTabs = [
        ['url' => '/dashboard/admin.php',              'label' => 'Dashboard',  'icon' => 'home',    'match' => ['dashboard']],
        ['url' => '/modules/admin/payments.php',        'label' => 'Payments',   'icon' => 'dollar',  'match' => ['payments']],
        ['url' => '/modules/packing/batch-list.php',    'label' => 'All Batches','icon' => 'package', 'match' => ['batch-list']],
        ['url' => '/modules/reports/packing-report.php','label' => 'Production','icon' => 'chart',   'match' => ['r-packing']],
        ['url' => '/modules/utilities/index.php',       'label' => 'Utilities',  'icon' => 'tool',    'match' => ['utilities', 'downtime', 'electricity', 'diesel', 'water-tanker', 'labour-cost', 'empty-jumbo']],
    ];
} elseif ($isCf) {
    // C&F's own dashboard content/tools are still mostly to be defined -
    // just Dashboard + New Order for now so login goes somewhere real
    // instead of a broken nav built for a totally different role.
    $bottomTabs = [
        ['url' => '/dashboard/cf.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => ['dashboard']],
        ['url' => '/modules/cf/new-order.php', 'label' => 'New Order', 'icon' => 'plus', 'match' => ['new-order']],
    ];
} else {
    $bottomTabs = [
        ['url' => '/dashboard/plant-head.php',           'label' => 'Dashboard',   'icon' => 'home',    'match' => ['dashboard']],
        ['url' => '/modules/inward/inward-entry.php',     'label' => 'Inward',      'icon' => 'truck',   'match' => ['inward-entry', 'inward-list', 'stock-list']],
        ['url' => '/modules/processing/processing-entry.php','label' => 'Processing','icon' => 'refresh', 'match' => ['processing-entry', 'processing-list']],
        ['url' => '/modules/packing/packing-update.php',  'label' => 'Packing',     'icon' => 'package', 'match' => ['batch-list', 'packing-update']],
    ];
}
/**
 * Every card heading on a page gets the same icon/colour as that page's
 * own nav link (set by app.js on load), so headings stay iconified and
 * on-theme without every module page having to pick its own icon.
 */
$headingIconMap = [
    'dashboard'        => ['home', 'purple'],
    'm-vendors'        => ['briefcase', 'green'],
    'm-raw-materials'  => ['archive', 'blue'],
    'm-products'       => ['tag', 'amber'],
    'm-tokens'         => ['hash', 'blue'],
    'm-users'          => ['user', 'green'],
    'm-tasks'          => ['check-square', 'blue'],
    'task-list'        => ['check-square', 'red'],
    'task-status'      => ['check-square', 'blue'],
    'm-labour'         => ['user', 'green'],
    'labour-attendance' => ['user', 'green'],
    'labour-status'    => ['user', 'blue'],
    'payments'         => ['dollar', 'green'],
    'cf-partners'      => ['briefcase', 'purple'],
    'batch-create'     => ['plus', 'amber'],
    'audit-log'        => ['clipboard', 'purple'],
    'r-vendor'         => ['briefcase', 'green'],
    'r-stock'          => ['archive', 'blue'],
    'r-processing'     => ['refresh', 'blue'],
    'r-batch'          => ['package', 'amber'],
    'r-packing'        => ['chart', 'purple'],
    'r-damage'         => ['alert', 'red'],
    'inward-entry'     => ['truck', 'blue'],
    'inward-list'      => ['clock', 'amber'],
    'stock-list'       => ['archive', 'blue'],
    'processing-entry' => ['refresh', 'blue'],
    'processing-list'  => ['clock', 'amber'],
    'batch-list'       => ['package', 'amber'],
    'packing-update'   => ['edit', 'purple'],
    'damage-entry'     => ['alert', 'red'],
    'ledger-view'      => ['book', 'blue'],
    'live-stock'       => ['layers', 'purple'],
    'settings'         => ['settings', 'blue'],
    'utilities'        => ['tool', 'blue'],
    'downtime'         => ['alert', 'red'],
    'electricity'      => ['chart', 'amber'],
    'diesel'           => ['truck', 'green'],
    'water-tanker'     => ['truck', 'purple'],
    'labour-cost'      => ['dollar', 'green'],
    'empty-jumbo'      => ['archive', 'amber'],
    'new-order'        => ['plus', 'green'],
];
$headingIcon = $headingIconMap[$activeMenu] ?? ['layers', 'purple'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $companyName) ?> · <?= e($companyName) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>window.__pageHeadingIcon = { svg: <?= json_encode(tabIcon($headingIcon[0])) ?>, color: <?= json_encode($headingIcon[1]) ?> };</script>
</head>
<body class="role-<?= $roleClass ?><?= $embedMode ? ' embed-mode' : '' ?>">
<?php if ($embedMode): ?>
    <div class="content embed-content">
      <?php renderFlash(); ?>
      <?php renderModalPopup(); ?>
<?php else: ?>
<div class="app-shell">

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <nav class="bottom-tabbar">
    <?php foreach ($bottomTabs as $t): ?>
      <?php tabLink($t['url'], $t['label'], $t['icon'], in_array($activeMenu, $t['match'], true)); ?>
    <?php endforeach; ?>
  </nav>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <?php if ($companyLogo): ?>
        <img src="<?= UPLOAD_URL ?><?= e($companyLogo) ?>" alt="<?= e($companyName) ?>" class="brand-logo">
      <?php else: ?>
        <span class="brand-badge"><?= e(substr($companyName, 0, 1)) ?></span>
      <?php endif; ?>
      <span class="sidebar-brand-text"><?= e($companyName) ?><small>Production Tracking</small></span>
    </div>

    <div class="sidebar-group-label">General</div>
    <?php navLink(roleDashboardUrl($currentUser['role_name']), 'Dashboard', 'dashboard', $activeMenu, 'home'); ?>

    <?php if ($isAdmin): ?>
      <div class="sidebar-group-label">Masters</div>
      <?php navLink('/modules/admin/vendors.php', 'Vendors', 'm-vendors', $activeMenu, 'briefcase'); ?>

      <div class="sidebar-group-label">Payments</div>
      <?php navLink('/modules/admin/payments.php', 'Vendor Payments', 'payments', $activeMenu, 'dollar'); ?>

      <div class="sidebar-group-label">C&amp;F</div>
      <?php navLink('/modules/admin/cf-partners.php', 'C&F Partners', 'cf-partners', $activeMenu, 'briefcase'); ?>

      <div class="sidebar-group-label">Packing</div>
      <?php navLink('/modules/packing/batch-create.php', 'New Production Batch (Set Target)', 'batch-create', $activeMenu, 'plus'); ?>
      <?php navLink('/modules/packing/batch-list.php', 'All Batches', 'batch-list', $activeMenu, 'package'); ?>

      <div class="sidebar-group-label">Records</div>
      <?php navLink('/modules/audit/audit-log.php', 'Audit Log', 'audit-log', $activeMenu, 'clipboard'); ?>
      <?php navLink('/modules/reports/damage-report.php', 'Damage Report', 'r-damage', $activeMenu, 'alert'); ?>
      <?php navLink('/modules/reports/stock-report.php', 'Stock Report', 'r-stock', $activeMenu, 'archive'); ?>
      <?php navLink('/modules/reports/vendor-report.php', 'Vendor Report', 'r-vendor', $activeMenu, 'briefcase'); ?>
      <?php navLink('/modules/reports/batch-report.php', 'Batch Report', 'r-batch', $activeMenu, 'package'); ?>
      <?php navLink('/modules/reports/packing-report.php', 'Packing Production', 'r-packing', $activeMenu, 'chart'); ?>
      <?php navLink('/modules/reports/processing-report.php', 'Processing Report', 'r-processing', $activeMenu, 'refresh'); ?>

      <div class="sidebar-group-label">Utilities</div>
      <?php navLink('/modules/utilities/downtime.php', 'Machine Down Time', 'downtime', $activeMenu, 'alert'); ?>
      <?php navLink('/modules/utilities/electricity.php', 'Electricity', 'electricity', $activeMenu, 'chart'); ?>
      <?php navLink('/modules/utilities/diesel.php', 'Diesel', 'diesel', $activeMenu, 'truck'); ?>
      <?php navLink('/modules/utilities/water-tanker.php', 'Water Tanker', 'water-tanker', $activeMenu, 'truck'); ?>
      <?php navLink('/modules/utilities/labour-cost.php', 'Labour Cost', 'labour-cost', $activeMenu, 'dollar'); ?>
      <?php navLink('/modules/utilities/empty-jumbo.php', 'Empty Jumbo Bags', 'empty-jumbo', $activeMenu, 'archive'); ?>
    <?php elseif ($isCf): ?>
      <div class="sidebar-group-label">Orders</div>
      <?php navLink('/modules/cf/new-order.php', 'New Order', 'new-order', $activeMenu, 'plus'); ?>
    <?php else: ?>
      <div class="sidebar-group-label">Raw Material</div>
      <?php navLink('/modules/inward/inward-entry.php', 'New Inward Entry', 'inward-entry', $activeMenu, 'truck'); ?>
      <?php navLink('/modules/stock/stock-list.php', 'Raw Material Stock', 'stock-list', $activeMenu, 'archive'); ?>

      <div class="sidebar-group-label">Processing</div>
      <?php navLink('/modules/processing/processing-entry.php', 'New Processing', 'processing-entry', $activeMenu, 'refresh'); ?>

      <div class="sidebar-group-label">Packing</div>
      <?php navLink('/modules/packing/batch-list.php', 'All Batches', 'batch-list', $activeMenu, 'package'); ?>
      <?php navLink('/modules/damage/damage-entry.php', 'Damage Record', 'damage-entry', $activeMenu, 'alert'); ?>

      <div class="sidebar-group-label">Records</div>
      <?php navLink('/modules/reports/packing-report.php', 'Packing Production', 'r-packing', $activeMenu, 'chart'); ?>
      <?php navLink('/modules/ledger/ledger-view.php', 'Stock Ledger', 'ledger-view', $activeMenu, 'book'); ?>

      <div class="sidebar-group-label">Utilities</div>
      <?php navLink('/modules/utilities/downtime.php', 'Machine Down Time', 'downtime', $activeMenu, 'alert'); ?>
      <?php navLink('/modules/utilities/electricity.php', 'Electricity', 'electricity', $activeMenu, 'chart'); ?>
      <?php navLink('/modules/utilities/diesel.php', 'Diesel', 'diesel', $activeMenu, 'truck'); ?>
      <?php navLink('/modules/utilities/water-tanker.php', 'Water Tanker', 'water-tanker', $activeMenu, 'truck'); ?>
      <?php navLink('/modules/utilities/labour-cost.php', 'Labour Cost', 'labour-cost', $activeMenu, 'dollar'); ?>
      <?php navLink('/modules/utilities/empty-jumbo.php', 'Empty Jumbo Bags', 'empty-jumbo', $activeMenu, 'archive'); ?>
    <?php endif; ?>
  </aside>

  <div class="main">
    <div class="brand-topbar">
      <?php if ($companyLogo): ?>
        <img src="<?= UPLOAD_URL ?><?= e($companyLogo) ?>" alt="<?= e($companyName) ?>" class="brand-topbar-logo">
      <?php else: ?>
        <span class="brand-topbar-badge"><?= e(substr($companyName, 0, 1)) ?></span>
      <?php endif; ?>
      <span class="brand-topbar-name"><?= e($companyName) ?></span>
      <a href="<?= APP_URL ?>/modules/auth/logout.php" class="brand-topbar-logout" title="Logout"><?= tabIcon('logout') ?></a>
    </div>
    <?php if (empty($hideTopbar)): ?>
    <div class="topbar">
      <div class="topbar-title"><?= e($pageTitle ?? '') ?></div>
      <div class="topbar-user">
        <?php if ($isAdmin): ?>
          <a href="<?= APP_URL ?>/modules/admin/settings.php" class="topbar-icon-btn" title="Settings"><?= tabIcon('settings') ?></a>
        <?php endif; ?>
        <span class="role-badge role-<?= $roleClass ?>"><?= e($currentUser['role_name']) ?></span>
        <span class="user-avatar"><?= e(strtoupper(substr($currentUser['name'], 0, 1))) ?></span>
        <strong><?= e($currentUser['name']) ?></strong>
        <a href="<?= APP_URL ?>/modules/auth/logout.php">Logout</a>
      </div>
    </div>
    <?php endif; ?>
    <div class="content">
      <?php if (!empty($hideTopbar) && empty($customHero)): ?>
      <div class="page-header page-header-<?= e($headingIcon[1]) ?>">
        <span class="icon-chip icon-chip-lg icon-chip-<?= e($headingIcon[1]) ?>"><?= tabIcon($headingIcon[0]) ?></span>
        <div class="page-header-title"><?= e($pageTitle ?? '') ?></div>
        <?php if ($isAdmin): ?>
          <a href="<?= APP_URL ?>/modules/admin/settings.php" class="page-header-settings" title="Settings"><?= tabIcon('settings') ?></a>
        <?php endif; ?>
        <?php if (!empty($pageHasFilter)): ?>
          <!-- Logout already lives in the sticky brand-topbar on every page
               (see the top of this file) - a second one here was redundant,
               so this slot is repurposed per-page instead: the filter
               trigger if the page has one, or a page-specific extra icon
               (see $pageHeaderExtraIcon) if it set one. -->
          <button type="button" class="page-header-settings" id="filterTriggerBtn" title="Filters"><?= tabIcon('filter') ?></button>
        <?php elseif (!empty($pageHeaderExtraIcon)): ?>
          <a href="<?= APP_URL . $pageHeaderExtraIcon['url'] ?>" class="page-header-settings" title="<?= e($pageHeaderExtraIcon['title']) ?>"><?= tabIcon($pageHeaderExtraIcon['icon']) ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php renderFlash(); ?>
      <?php renderModalPopup(); ?>
<?php endif; ?>
