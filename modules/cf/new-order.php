<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['C&F']);

// Placeholder - the actual order-placing workflow (what a C&F can order,
// against which SKUs/stock, how Admin sees and fulfils it) hasn't been
// defined yet. This exists so the dashboard's "New Order" tile has
// somewhere real to go instead of a dead link, until that's built out.
$pageTitle = 'New Order';
$activeMenu = 'new-order';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0">New Order</h3>
  <p class="row-card-empty">Coming soon - this is where you'll be able to place a new order.</p>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
