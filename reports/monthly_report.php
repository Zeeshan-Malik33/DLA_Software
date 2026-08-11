<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'reports';
$pageTitle  = 'Sales Reports';

$range = $_GET['range'] ?? '30';
$rangeDays = ['7' => 7, '30' => 30, '90' => 90][$range] ?? null;
$rangeLabel = ['7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days', 'all' => 'All Time'][$range] ?? 'Last 30 Days';

$where = $rangeDays !== null ? "WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $rangeDays DAY)" : '';
$prevWhere = $rangeDays !== null
    ? "WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays * 2) . " DAY) AND order_date < DATE_SUB(CURDATE(), INTERVAL $rangeDays DAY)"
    : "WHERE 1=0";

// ---------------------------------------------------------
// KPI cards
// ---------------------------------------------------------
$orderStats = $pdo->query("
    SELECT COUNT(*) AS total, SUM(status = 'delivered') AS delivered,
           AVG(CASE WHEN status = 'delivered' AND actual_delivery_date IS NOT NULL THEN DATEDIFF(actual_delivery_date, order_date) END) AS avg_fulfillment_days
    FROM orders $where
")->fetch();

$fulfillmentRate = $orderStats['total'] > 0 ? round(($orderStats['delivered'] / $orderStats['total']) * 100, 1) : 0;
$avgFulfillmentDays = $orderStats['avg_fulfillment_days'] !== null ? round($orderStats['avg_fulfillment_days'], 1) : null;

$repeatNow = $pdo->query("
    SELECT COUNT(*) AS total_customers, SUM(order_count > 1) AS repeat_customers FROM (
        SELECT customer_id, COUNT(*) AS order_count FROM orders $where GROUP BY customer_id
    ) t
")->fetch();
$repeatRate = $repeatNow['total_customers'] > 0 ? round(($repeatNow['repeat_customers'] / $repeatNow['total_customers']) * 100, 1) : 0;

$repeatPrev = $pdo->query("
    SELECT COUNT(*) AS total_customers, SUM(order_count > 1) AS repeat_customers FROM (
        SELECT customer_id, COUNT(*) AS order_count FROM orders $prevWhere GROUP BY customer_id
    ) t
")->fetch();
$repeatRatePrev = $repeatPrev['total_customers'] > 0 ? round(($repeatPrev['repeat_customers'] / $repeatPrev['total_customers']) * 100, 1) : 0;
$repeatTrend = round($repeatRate - $repeatRatePrev, 1);

// ---------------------------------------------------------
// Revenue over time (last 6 months, independent of range filter)
// ---------------------------------------------------------
$monthly = $pdo->query("
    SELECT DATE_FORMAT(order_date, '%b') AS month_label, DATE_FORMAT(order_date, '%Y-%m') AS month_key,
           SUM(total_amount) AS revenue
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
")->fetchAll();

// ---------------------------------------------------------
// Sales vs Refunds trend (last 6 months)
// ---------------------------------------------------------
$salesByMonth = $pdo->query("
    SELECT DATE_FORMAT(order_date, '%Y-%m') AS month_key, SUM(total_amount) AS total
    FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key
")->fetchAll(PDO::FETCH_KEY_PAIR);

$refundsByMonth = $pdo->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_key, SUM(amount) AS total
    FROM payments WHERE status = 'refunded' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key
")->fetchAll(PDO::FETCH_KEY_PAIR);

$trendLabels = array_column($monthly, 'month_label');
$trendKeys   = array_column($monthly, 'month_key');
$salesSeries = array_map(fn($k) => (float) ($salesByMonth[$k] ?? 0), $trendKeys);
$refundSeries = array_map(fn($k) => (float) ($refundsByMonth[$k] ?? 0), $trendKeys);

// ---------------------------------------------------------
// Top performing products
// ---------------------------------------------------------
$topProducts = $pdo->query("
    SELECT oi.product_name,
           SUM(oi.quantity) AS units_sold,
           SUM(oi.line_total) AS revenue
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    $where
    GROUP BY oi.product_name
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();

foreach ($topProducts as &$p) {
    $prevSql = "SELECT COALESCE(SUM(oi.line_total), 0) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id $prevWhere AND oi.product_name = ?";
    $stmt = $pdo->prepare($prevSql);
    $stmt->execute([$p['product_name']]);
    $prevRevenue = (float) $stmt->fetchColumn();
    $p['growth'] = $prevRevenue > 0 ? round((($p['revenue'] - $prevRevenue) / $prevRevenue) * 100, 1) : ($p['revenue'] > 0 ? 100 : 0);
}
unset($p);

// ---------------------------------------------------------
// Outstanding balance aging
// ---------------------------------------------------------
$agingRows = $pdo->query("
    SELECT
      SUM(CASE WHEN DATEDIFF(CURDATE(), order_date) <= 30 THEN remaining_balance ELSE 0 END) AS b0_30,
      SUM(CASE WHEN DATEDIFF(CURDATE(), order_date) BETWEEN 31 AND 60 THEN remaining_balance ELSE 0 END) AS b31_60,
      SUM(CASE WHEN DATEDIFF(CURDATE(), order_date) BETWEEN 61 AND 90 THEN remaining_balance ELSE 0 END) AS b61_90,
      SUM(CASE WHEN DATEDIFF(CURDATE(), order_date) > 90 THEN remaining_balance ELSE 0 END) AS b90_plus
    FROM orders WHERE remaining_balance > 0
")->fetch();

$agingTotal = array_sum(array_map('floatval', $agingRows));
$agingBuckets = [
    ['label' => '0-30 Days',  'value' => $agingRows['b0_30'],   'color' => 'bg-brand'],
    ['label' => '31-60 Days', 'value' => $agingRows['b31_60'],  'color' => 'bg-blue-600'],
    ['label' => '61-90 Days', 'value' => $agingRows['b61_90'],  'color' => 'bg-amber-500'],
    ['label' => '90+ Days',   'value' => $agingRows['b90_plus'],'color' => 'bg-red-500'],
];

// ---------------------------------------------------------
// Orders nearing their delivery deadline
// ---------------------------------------------------------
$dueSoon = $pdo->query("
    SELECT o.order_id, c.full_name, o.expected_delivery_date,
           DATEDIFF(o.expected_delivery_date, CURDATE()) AS days_left
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    WHERE o.status NOT IN ('delivered', 'cancelled')
      AND o.expected_delivery_date IS NOT NULL
      AND o.expected_delivery_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY o.expected_delivery_date ASC
    LIMIT 5
")->fetchAll();

ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Sales Reports</h2>
    <p class="text-sm text-gray-500 mt-1">Deep dive into your sales and order performance metrics.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <form id="reportsRangeForm" method="GET" class="flex">
      <select name="range" onchange="this.form.requestSubmit()"
        class="rounded-full border border-gray-300 bg-white text-sm px-4 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand">
        <option value="7"  <?= $range === '7'  ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="30" <?= $range === '30' ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="90" <?= $range === '90' ? 'selected' : '' ?>>Last 90 Days</option>
        <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All Time</option>
      </select>
    </form>
    <div class="relative">
      <button type="button" id="exportReportToggle"
        class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-download"></i> Export Report <i class="ti ti-chevron-down text-xs"></i>
      </button>
      <div id="exportReportMenu" class="hidden absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-10 overflow-hidden">
        <a href="export_pdf.php?range=<?= h($range) ?>" target="_blank" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Export as PDF</a>
        <a href="export_excel.php?range=<?= h($range) ?>" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Export as Excel</a>
      </div>
    </div>
  </div>
</div>

<!-- KPI cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-sm text-gray-500">Order Fulfillment Rate</p>
      <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full <?= $fulfillmentRate >= 80 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
        <i class="ti ti-circle-check"></i> <?= $fulfillmentRate >= 80 ? 'Healthy' : 'Needs Attention' ?>
      </span>
    </div>
    <p class="text-3xl font-bold text-gray-900"><?= $fulfillmentRate ?>%</p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Avg. Fulfillment Time</p>
    <p class="text-3xl font-bold text-gray-900"><?= $avgFulfillmentDays !== null ? $avgFulfillmentDays . ' days' : '—' ?></p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-sm text-gray-500">Repeat Customer Rate</p>
      <span class="text-xs font-medium <?= $repeatTrend >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
        <i class="ti ti-trending-<?= $repeatTrend >= 0 ? 'up' : 'down' ?>"></i> <?= $repeatTrend >= 0 ? '+' : '' ?><?= $repeatTrend ?>pt vs last period
      </span>
    </div>
    <p class="text-3xl font-bold text-gray-900"><?= $repeatRate ?>%</p>
  </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h3 class="font-semibold text-gray-900 mb-4">Revenue Over Time</h3>
    <div class="h-56">
      <canvas id="revenueTrendChart"
        data-labels='<?= h(json_encode($trendLabels)) ?>'
        data-revenue='<?= h(json_encode($salesSeries)) ?>'></canvas>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Sales vs. Refunds Trends</h3>
      <div class="flex items-center gap-4 text-xs text-gray-500">
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-brand inline-block"></span> Sales</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-indigo-200 inline-block"></span> Refunds</span>
      </div>
    </div>
    <div class="h-56">
      <canvas id="refundTrendChart"
        data-labels='<?= h(json_encode($trendLabels)) ?>'
        data-sales='<?= h(json_encode($salesSeries)) ?>'
        data-refunds='<?= h(json_encode($refundSeries)) ?>'></canvas>
    </div>
  </div>
</div>

<!-- Top performing products -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto mb-6">
  <div class="flex items-center justify-between px-5 pt-5">
    <h3 class="font-semibold text-gray-900">Top Performing Products</h3>
    <a href="../order/listorder.php" data-spa data-page="orders" class="text-sm text-brand font-medium hover:underline">View All</a>
  </div>
  <table class="w-full text-sm min-w-[620px] mt-3">
    <thead>
      <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100 bg-gray-50">
        <th class="px-5 py-3 font-medium">Product</th>
        <th class="px-5 py-3 font-medium text-right">Units Sold</th>
        <th class="px-5 py-3 font-medium text-right">Revenue</th>
        <th class="px-5 py-3 font-medium text-right">Growth %</th>
        <th class="px-5 py-3 font-medium">Demand</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($topProducts as $p):
          $demand = $p['growth'] > 5 ? ['Trending', 'bg-emerald-50 text-emerald-700']
                  : ($p['growth'] < -5 ? ['Slowing', 'bg-red-50 text-red-700']
                  : ['Steady', 'bg-gray-100 text-gray-600']);
      ?>
      <tr>
        <td class="px-5 py-4 font-medium text-gray-800"><?= h($p['product_name']) ?></td>
        <td class="px-5 py-4 text-right text-gray-700"><?= number_format($p['units_sold']) ?></td>
        <td class="px-5 py-4 text-right text-gray-800"><?= formatMoney($p['revenue']) ?></td>
        <td class="px-5 py-4 text-right <?= $p['growth'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
          <i class="ti ti-trending-<?= $p['growth'] >= 0 ? 'up' : 'down' ?>"></i> <?= abs($p['growth']) ?>%
        </td>
        <td class="px-5 py-4"><span class="inline-block <?= $demand[1] ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= $demand[0] ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($topProducts)): ?>
      <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No product sales in this period yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Aging + due-soon -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h3 class="font-semibold text-gray-900 mb-5">Outstanding Balance Aging</h3>
    <div class="space-y-4">
      <?php foreach ($agingBuckets as $bucket):
          $pct = $agingTotal > 0 ? round(($bucket['value'] / $agingTotal) * 100) : 0;
      ?>
      <div class="flex items-center gap-3 text-sm">
        <span class="w-20 text-gray-600 shrink-0"><?= $bucket['label'] ?></span>
        <div class="flex-1 h-2.5 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full <?= $bucket['color'] ?> rounded-full" style="width: <?= $pct ?>%"></div>
        </div>
        <span class="w-10 text-right text-gray-700 shrink-0"><?= $pct ?>%</span>
      </div>
      <?php endforeach; ?>
      <?php if ($agingTotal == 0): ?>
        <p class="text-sm text-gray-400">No outstanding balances right now.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <div class="flex items-start gap-2 mb-1">
      <i class="ti ti-alert-triangle text-red-500 mt-0.5"></i>
      <h3 class="font-semibold text-gray-900">Orders Due Soon</h3>
    </div>
    <p class="text-xs text-gray-500 mb-4">Active orders with an expected delivery date within 7 days.</p>
    <div class="space-y-3">
      <?php foreach ($dueSoon as $o): ?>
      <div class="flex items-center justify-between border border-gray-100 rounded-lg px-4 py-3">
        <div>
          <p class="text-sm font-medium text-gray-800"><?= h($o['full_name']) ?></p>
          <p class="text-xs <?= $o['days_left'] < 0 ? 'text-red-600' : 'text-amber-600' ?>">
            <?= $o['days_left'] < 0 ? 'Overdue by ' . abs($o['days_left']) . ' day' . (abs($o['days_left']) == 1 ? '' : 's') : 'Est. ' . $o['days_left'] . ' day' . ($o['days_left'] == 1 ? '' : 's') . ' remaining' ?>
          </p>
        </div>
        <a href="../order/vieworder.php?id=<?= $o['order_id'] ?>" data-spa data-page="orders"
           class="text-xs font-medium text-brand hover:underline">Follow Up</a>
      </div>
      <?php endforeach; ?>
      <?php if (empty($dueSoon)): ?>
        <p class="text-sm text-gray-400">Nothing due in the next 7 days.</p>
      <?php endif; ?>
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