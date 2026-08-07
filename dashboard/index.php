<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

// ---------------------------------------------------------
// Date range filter
// ---------------------------------------------------------
$range = $_GET['range'] ?? '30';
$rangeDays = ['7' => 7, '30' => 30, '90' => 90][$range] ?? null;

$where = '';
if ($rangeDays !== null) {
    $where = "WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $rangeDays DAY)";
}

$stats = $pdo->query("
    SELECT
        COALESCE(SUM(total_amount), 0)                  AS total_sales,
        COALESCE(SUM(cost_of_goods + shipping_cost), 0)  AS total_cost,
        COALESCE(SUM(profit), 0)                         AS total_profit,
        COALESCE(SUM(remaining_balance), 0)              AS outstanding,
        COUNT(*)                                         AS order_count
    FROM orders
    $where
")->fetch();

$profitMargin = $stats['total_sales'] > 0
    ? round(($stats['total_profit'] / $stats['total_sales']) * 100, 1)
    : 0;

$monthly = $pdo->query("
    SELECT DATE_FORMAT(order_date, '%b') AS month_label,
           DATE_FORMAT(order_date, '%Y-%m') AS month_key,
           SUM(total_amount) AS revenue,
           SUM(cost_of_goods + shipping_cost) AS cost
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
")->fetchAll();

$statusRows = $pdo->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status")->fetchAll();
$statusTotal = array_sum(array_column($statusRows, 'total'));

$recentOrders = $pdo->query("
    SELECT o.order_id, c.full_name, c.country, o.total_amount, o.currency, o.status
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();

$topCustomers = $pdo->query("
    SELECT c.full_name, SUM(o.total_amount) AS total_spent, COUNT(o.order_id) AS order_count
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    GROUP BY o.customer_id ORDER BY total_spent DESC LIMIT 3
")->fetchAll();

$outstandingOrders = $pdo->query("
    SELECT o.order_id, c.full_name, o.remaining_balance, o.currency
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    WHERE o.remaining_balance > 0 ORDER BY o.remaining_balance DESC LIMIT 3
")->fetchAll();

ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
    <p class="text-sm text-gray-500">Real-time order intelligence and financial performance overview.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <form id="dashboardRangeForm" method="GET" class="flex">
      <select name="range" onchange="this.form.requestSubmit()"
        class="rounded-full border border-gray-300 bg-white text-sm px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand">
        <option value="7"  <?= $range === '7'  ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="30" <?= $range === '30' ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="90" <?= $range === '90' ? 'selected' : '' ?>>Last 90 Days</option>
        <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All Time</option>
      </select>
    </form>
    <a href="../reports/export_pdf.php?range=<?= h($range) ?>"
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-download"></i> Export Report
    </a>
  </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Total Sales</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($stats['total_sales']) ?></p>
    <p class="text-xs text-emerald-600 mt-2 flex items-center gap-1"><i class="ti ti-trending-up"></i> <?= (int) $stats['order_count'] ?> orders in range</p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Total Cost</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($stats['total_cost']) ?></p>
    <p class="text-xs text-gray-500 mt-2 flex items-center gap-1"><i class="ti ti-truck-delivery"></i> Goods + shipping</p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Total Profit</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($stats['total_profit']) ?></p>
    <p class="text-xs text-emerald-600 mt-2 flex items-center gap-1"><i class="ti ti-chart-line"></i> <?= $profitMargin ?>% margin</p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Outstanding Balance</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($stats['outstanding']) ?></p>
    <p class="text-xs text-red-600 mt-2 flex items-center gap-1"><i class="ti ti-alert-circle"></i> Unpaid across all orders</p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
  <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Revenue vs. Cost</h3>
      <div class="flex items-center gap-4 text-xs text-gray-500">
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-indigo-300 inline-block"></span> Revenue</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-indigo-100 inline-block"></span> Cost</span>
      </div>
    </div>
    <div class="h-64">
      <canvas id="revenueChart"
        data-labels='<?= h(json_encode(array_column($monthly, 'month_label'))) ?>'
        data-revenue='<?= h(json_encode(array_map('floatval', array_column($monthly, 'revenue')))) ?>'
        data-cost='<?= h(json_encode(array_map('floatval', array_column($monthly, 'cost')))) ?>'></canvas>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h3 class="font-semibold text-gray-900 mb-4">Order Status</h3>
    <div class="relative h-40 flex items-center justify-center">
      <canvas id="statusChart"
        data-labels='<?= h(json_encode(array_map('ucfirst', array_column($statusRows, 'status')))) ?>'
        data-values='<?= h(json_encode(array_map('intval', array_column($statusRows, 'total')))) ?>'></canvas>
      <div class="absolute text-center">
        <p class="text-xl font-bold text-gray-900"><?= (int) $statusTotal ?></p>
        <p class="text-xs text-gray-500">Total Orders</p>
      </div>
    </div>
    <ul class="mt-4 space-y-2 text-sm">
      <?php foreach ($statusRows as $row):
          $pct = $statusTotal > 0 ? round(($row['total'] / $statusTotal) * 100) : 0;
          $c = statusColor($row['status']);
      ?>
      <li class="flex items-center justify-between">
        <span class="flex items-center gap-2 text-gray-600">
          <span class="w-2.5 h-2.5 rounded-full <?= $c['bg'] ?> inline-block"></span>
          <?= h(statusLabel($row['status'])) ?>
        </span>
        <span class="font-medium text-gray-900"><?= $pct ?>%</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5 overflow-x-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Recent Orders</h3>
      <a href="../order/listorder.php" data-spa data-page="orders" class="text-sm text-brand font-medium hover:underline">View All</a>
    </div>
    <table class="w-full text-sm min-w-[480px]">
      <thead>
        <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
          <th class="pb-2 font-medium">Customer</th>
          <th class="pb-2 font-medium">Country</th>
          <th class="pb-2 font-medium text-right">Total</th>
          <th class="pb-2 font-medium text-right">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($recentOrders as $order): $c = statusColor($order['status']); ?>
        <tr>
          <td class="py-3 font-medium text-gray-800">#<?= (int) $order['order_id'] ?> · <?= h($order['full_name']) ?></td>
          <td class="py-3 text-gray-500"><?= h($order['country']) ?></td>
          <td class="py-3 text-right text-gray-800"><?= formatMoney($order['total_amount'], $order['currency']) ?></td>
          <td class="py-3 text-right">
            <span class="inline-block <?= $c['soft'] ?> <?= $c['text'] ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= h(statusLabel($order['status'])) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentOrders)): ?>
        <tr><td colspan="4" class="py-6 text-center text-gray-400">No orders yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="ti ti-star text-brand"></i> Top Customers</h3>
      <ol class="space-y-3">
        <?php foreach ($topCustomers as $i => $cust): ?>
        <li class="flex items-start gap-3">
          <span class="w-6 h-6 flex items-center justify-center rounded bg-brand/10 text-brand text-xs font-semibold"><?= $i + 1 ?></span>
          <div>
            <p class="text-sm font-medium text-gray-800"><?= h($cust['full_name'] ?: 'Unnamed customer') ?></p>
            <p class="text-xs text-gray-500"><?= formatMoney($cust['total_spent']) ?> · <?= (int) $cust['order_count'] ?> orders</p>
          </div>
        </li>
        <?php endforeach; ?>
        <?php if (empty($topCustomers)): ?>
        <li class="text-sm text-gray-400">No data yet.</li>
        <?php endif; ?>
      </ol>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="ti ti-alert-triangle text-red-500"></i> Outstanding Payments</h3>
      <ul class="space-y-3">
        <?php foreach ($outstandingOrders as $o): ?>
        <li class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-800">#<?= (int) $o['order_id'] ?> · <?= h($o['full_name']) ?></p>
            <p class="text-xs text-gray-500">Balance due</p>
          </div>
          <span class="text-sm font-semibold text-red-600"><?= formatMoney($o['remaining_balance'], $o['currency']) ?></span>
        </li>
        <?php endforeach; ?>
        <?php if (empty($outstandingOrders)): ?>
        <li class="text-sm text-gray-400">Nothing outstanding.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
