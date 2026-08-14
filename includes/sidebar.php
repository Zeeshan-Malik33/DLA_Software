<?php
// Expects $pdo (from config/database.php) and $activePage (string) to be set
// by the including page before this file is required.
$activePage = $activePage ?? '';

$logoStmt = $pdo->query("SELECT photo_path FROM users WHERE photo_path IS NOT NULL AND photo_path != '' LIMIT 1");
$logoPath = $logoStmt->fetchColumn();

$summary = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM customers)                         AS total_customers,
        (SELECT COUNT(*) FROM orders)                             AS total_orders,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders)       AS total_sales,
        (SELECT COALESCE(SUM(profit), 0) FROM orders)             AS total_profit
")->fetch();

function navClass($key, $active) {
    return $key === $active
        ? 'flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white bg-white/15'
        : 'flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white/90 bg-white/5 hover:bg-white/10';
}
?>
<!-- Mobile top bar -->
<div class="md:hidden bg-brand flex items-center justify-between px-4 py-3">
  <?php if (!empty($logoPath)): ?>
    <img src="../<?= h($logoPath) ?>" alt="Logo" class="w-10 h-10 rounded-full object-cover">
  <?php else: ?>
    <span class="text-white text-lg font-semibold">Dashboard</span>
  <?php endif; ?>
  <button id="menuToggle" class="text-white p-1" aria-label="Open menu">
    <i class="ti ti-menu-2 text-2xl"></i>
  </button>
</div>

<aside id="sidebar" class="hidden md:flex md:flex-col w-full md:w-64 bg-brand px-4 py-6 md:min-h-screen md:sticky md:top-0 md:h-screen">
  <?php if (!empty($logoPath)): ?>
    <div class="hidden md:flex flex-col items-center justify-center w-full pt-4 mb-6">
      <img src="../<?= h($logoPath) ?>" alt="Logo" class="w-32 h-32 rounded-full object-cover mx-auto">
    </div>
  <?php else: ?>
    <h1 class="hidden md:block text-white text-2xl font-semibold px-2 mb-6">Dashboard</h1>
  <?php endif; ?>

  <nav id="sidebarNav" class="space-y-2">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="<?= navClass('dashboard', $activePage) ?>">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="../customer/listcustomer.php" data-spa data-page="customers" class="<?= navClass('customers', $activePage) ?>">
      <i class="ti ti-users"></i> Customer Management
    </a>
    <a href="../order/listorder.php" data-spa data-page="orders" class="<?= navClass('orders', $activePage) ?>">
      <i class="ti ti-package"></i> Order Management
    </a>
    <a href="../payment/listpayment.php" data-spa data-page="payments" class="<?= navClass('payments', $activePage) ?>">
      <i class="ti ti-credit-card"></i> Payment Management
    </a>
    <a href="../reports/monthly_report.php" data-spa data-page="reports" class="<?= navClass('reports', $activePage) ?>">
      <i class="ti ti-file-report"></i> Report
    </a>
    <a href="../expenses/listexpense.php" data-spa data-page="expenses" class="<?= navClass('expenses', $activePage) ?>">
      <i class="ti ti-wallet"></i> Personal Expenses
    </a>
    <a href="../settings/index.php" data-spa data-page="settings" class="<?= navClass('settings', $activePage) ?>">
      <i class="ti ti-settings"></i> Setting
    </a>
  </nav>

  <!-- Business summary — computed once per full page load, stays put during in-app navigation
  <div class="mt-6 bg-black/20 rounded-xl p-4">
    <p class="text-xs font-semibold tracking-wide text-white/60 uppercase mb-3">Business Summary</p>
    <ul class="space-y-3">
      <li class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-300 flex items-center justify-center"><i class="ti ti-users"></i></span>
        <div>
          <p class="text-[11px] text-white/50 leading-none mb-1">Total Customers</p>
          <p class="text-sm font-semibold text-white"><?= number_format($summary['total_customers']) ?></p>
        </div>
      </li>
      <li class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg bg-orange-500/20 text-orange-300 flex items-center justify-center"><i class="ti ti-package"></i></span>
        <div>
          <p class="text-[11px] text-white/50 leading-none mb-1">Total Orders</p>
          <p class="text-sm font-semibold text-white"><?= number_format($summary['total_orders']) ?></p>
        </div>
      </li>
      <li class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center"><i class="ti ti-currency-dollar"></i></span>
        <div>
          <p class="text-[11px] text-white/50 leading-none mb-1">Total Sales</p>
          <p class="text-sm font-semibold text-white"><?= formatMoney($summary['total_sales']) ?></p>
        </div>
      </li>
      <li class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg bg-purple-500/20 text-purple-300 flex items-center justify-center"><i class="ti ti-chart-line"></i></span>
        <div>
          <p class="text-[11px] text-white/50 leading-none mb-1">Total Profit</p>
          <p class="text-sm font-semibold text-white"><?= formatMoney($summary['total_profit']) ?></p>
        </div>
      </li>
    </ul>
  </div> -->

  <div class="mt-auto pt-6 hidden md:block">
    <a href="../auth/logout.php" class="flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white/70 hover:text-white hover:bg-white/10">
      <i class="ti ti-logout"></i> Log out
    </a>
  </div>
</aside>
