<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

// ---------------------------------------------------------
// Date filter
// ---------------------------------------------------------
$filterType = $_GET['type'] ?? 'monthly';
$filterD = $_GET['d'] ?? date('Y-m-d');
$filterM = $_GET['m'] ?? date('Y-m');
$filterY = $_GET['y'] ?? date('Y');

$where = '';
$whereAlias = '';

if ($filterType === 'daily') {
    $where = "WHERE DATE(order_date) = " . $pdo->quote($filterD);
    $whereAlias = "WHERE DATE(o.order_date) = " . $pdo->quote($filterD);
} elseif ($filterType === 'monthly') {
    $parts = explode('-', $filterM);
    if (count($parts) == 2) {
        $y = (int)$parts[0];
        $m = (int)$parts[1];
        $where = "WHERE YEAR(order_date) = $y AND MONTH(order_date) = $m";
        $whereAlias = "WHERE YEAR(o.order_date) = $y AND MONTH(o.order_date) = $m";
    }
} elseif ($filterType === 'yearly') {
    $y = (int)$filterY;
    $where = "WHERE YEAR(order_date) = $y";
    $whereAlias = "WHERE YEAR(o.order_date) = $y";
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

$statusWhere = $whereAlias ? str_replace("o.order_date", "order_date", $whereAlias) : '';
$statusRows = $pdo->query("SELECT status, COUNT(*) AS total FROM orders $statusWhere GROUP BY status")->fetchAll();
$statusTotal = array_sum(array_column($statusRows, 'total'));

$recentOrders = $pdo->query("
    SELECT o.order_id, c.full_name, c.country, o.total_amount, o.currency, o.status
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();

ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center gap-6 mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
  </div>
  
  <form id="dashboardRangeForm" method="GET" class="flex items-center gap-3">
    <div class="relative">
      <select name="type" onchange="toggleFilterInput(); navigateTo('index.php?' + new URLSearchParams(new FormData(this.form)).toString(), true);"
        class="appearance-none cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 pr-8 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors">
        <option value="daily" <?= $filterType === 'daily' ? 'selected' : '' ?>>Daily</option>
        <option value="monthly" <?= $filterType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
        <option value="yearly" <?= $filterType === 'yearly' ? 'selected' : '' ?>>Yearly</option>
        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Time</option>
      </select>
      <i class="ti ti-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
    </div>
    
    <div class="relative <?= $filterType === 'daily' ? '' : 'hidden' ?>" id="filter_daily_wrapper">
      <input type="date" name="d" id="filter_daily" value="<?= h($filterD) ?>" 
             class="cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
             onchange="navigateTo('index.php?' + new URLSearchParams(new FormData(this.form)).toString(), true)">
    </div>
           
    <div class="relative <?= $filterType === 'monthly' ? '' : 'hidden' ?>" id="filter_monthly_wrapper">
      <input type="month" name="m" id="filter_monthly" value="<?= h($filterM) ?>" 
             class="cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
             onchange="navigateTo('index.php?' + new URLSearchParams(new FormData(this.form)).toString(), true)">
    </div>
           
    <div class="relative <?= $filterType === 'yearly' ? '' : 'hidden' ?>" id="filter_yearly_wrapper">
      <select name="y" id="filter_yearly" onchange="navigateTo('index.php?' + new URLSearchParams(new FormData(this.form)).toString(), true)"
              class="appearance-none cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 pr-8 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors">
          <?php for($yr = (int)date('Y'); $yr >= 2020; $yr--): ?>
             <option value="<?= $yr ?>" <?= $filterY == $yr ? 'selected' : '' ?>><?= $yr ?></option>
          <?php endfor; ?>
      </select>
      <i class="ti ti-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
    </div>
    
    <script>
      function toggleFilterInput() {
         const type = document.querySelector('select[name="type"]').value;
         document.getElementById('filter_daily_wrapper').classList.toggle('hidden', type !== 'daily');
         document.getElementById('filter_monthly_wrapper').classList.toggle('hidden', type !== 'monthly');
         document.getElementById('filter_yearly_wrapper').classList.toggle('hidden', type !== 'yearly');
      }
    </script>
  </form>
</div>

<?php
$fmtSales = formatMoney($stats['total_sales']);
$fmtCost = formatMoney($stats['total_cost']);
$fmtProfit = formatMoney($stats['total_profit']);
$fmtOut = formatMoney($stats['outstanding']);

function fitText($str) {
    $l = strlen($str);
    if ($l > 18) return 'text-base';
    if ($l > 14) return 'text-lg';
    if ($l > 11) return 'text-xl';
    return 'text-2xl';
}
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 overflow-hidden">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
      <i class="ti ti-currency-dollar"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Total Sales</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtSales) ?>" title="<?= $fmtSales ?>"><?= $fmtSales ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl">
      <i class="ti ti-shopping-cart"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Cost of Goods</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtCost) ?>" title="<?= $fmtCost ?>"><?= $fmtCost ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
      <i class="ti ti-chart-line"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Total Profit</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtProfit) ?>" title="<?= $fmtProfit ?>"><?= $fmtProfit ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl">
      <i class="ti ti-wallet"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Remaining</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtOut) ?>" title="<?= $fmtOut ?>"><?= $fmtOut ?></p>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
  <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5 overflow-x-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">5 Recent Orders</h3>
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

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
