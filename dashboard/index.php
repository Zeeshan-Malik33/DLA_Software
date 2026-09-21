<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

// ---------------------------------------------------------
// Date filter (kept for PDF download compatibility)
// ---------------------------------------------------------
$filterMonth = $_GET['month'] ?? date('m');
$filterYear  = $_GET['year'] ?? date('Y');

$currentY = (int)$filterYear;
$currentM = (int)$filterMonth;

// ---------------------------------------------------------
// Stats (Static: All Time vs Selected Month)
// ---------------------------------------------------------
$totalStats = $pdo->query("
    SELECT
        COALESCE(SUM(total_amount), 0)                  AS total_sales,
        COALESCE(SUM(cost_of_goods + shipping_cost), 0)  AS total_cost,
        COALESCE(SUM(profit), 0)                         AS total_profit
    FROM orders
")->fetch();

$monthlyStats = $pdo->query("
    SELECT
        COALESCE(SUM(total_amount), 0)                  AS total_sales,
        COALESCE(SUM(cost_of_goods + shipping_cost), 0)  AS total_cost,
        COALESCE(SUM(profit), 0)                         AS total_profit,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) AS delivered_amount
    FROM orders
    WHERE YEAR(order_date) = $currentY AND MONTH(order_date) = $currentM
")->fetch();

$pendingOrders = $pdo->query("
    SELECT o.order_id, c.full_name, c.country, o.total_amount, o.currency, o.status, o.order_date
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    WHERE YEAR(o.order_date) = $currentY AND MONTH(o.order_date) = $currentM AND o.status = 'pending'
    ORDER BY o.created_at DESC
")->fetchAll();

$recentOrders = $pdo->query("
    SELECT o.order_id, c.full_name, c.country, o.total_amount, o.currency, o.status
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();

ob_start();
?>
<span data-spa-title="Dashboard — DLA" hidden></span>

<div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
  <div class="flex-1">
    <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
  </div>

  <form id="dashboardRangeForm" method="GET" data-spa-form class="flex flex-wrap items-center gap-3">
    <input type="hidden" name="type" value="monthly">
    
    <!-- Monthly: dropdown -->
    <div class="relative">
      <select name="month" id="dashFilterMonth" onchange="this.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))"
              class="appearance-none cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 pr-8 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors">
        <?php
        $months = ['01'=>'January', '02'=>'February', '03'=>'March', '04'=>'April', '05'=>'May', '06'=>'June', '07'=>'July', '08'=>'August', '09'=>'September', '10'=>'October', '11'=>'November', '12'=>'December'];
        foreach($months as $mVal => $mName): ?>
          <option value="<?= $mVal ?>" <?= sprintf('%02d', $currentM) === $mVal ? 'selected' : '' ?>><?= $mName ?></option>
        <?php endforeach; ?>
      </select>
      <i class="ti ti-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
    </div>

    <!-- Yearly: dropdown -->
    <div class="relative">
      <select name="year" id="dashFilterYear" onchange="this.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))"
              class="appearance-none cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 pr-8 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors">
        <?php for($yr = (int)date('Y'); $yr >= 2020; $yr--): ?>
          <option value="<?= $yr ?>" <?= $currentY == $yr ? 'selected' : '' ?>><?= $yr ?></option>
        <?php endfor; ?>
      </select>
      <i class="ti ti-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
    </div>

    <!-- Download PDF button -->
    <a id="dashDownloadBtn"
       href="export_pdf.php?type=monthly&month=<?= urlencode(sprintf('%02d', $currentM)) ?>&year=<?= urlencode($currentY) ?>"
       class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-brand focus:outline-none transition-colors">
      <i class="ti ti-file-type-pdf text-red-500"></i> Download PDF
    </a>

  </form>
</div>


<?php
$fmtTotalSales = formatMoney($totalStats['total_sales']);
$fmtTotalCost = formatMoney($totalStats['total_cost']);
$fmtTotalProfit = formatMoney($totalStats['total_profit']);

$fmtMonthlySales = formatMoney($monthlyStats['total_sales']);
$fmtMonthlyCost = formatMoney($monthlyStats['total_cost']);
$fmtMonthlyProfit = formatMoney($monthlyStats['total_profit']);

$fmtMonthlyDeliveredCount = (int)$monthlyStats['delivered_count'];
$fmtMonthlyDeliveredAmount = formatMoney($monthlyStats['delivered_amount']);

function fitText($str) {
    $l = strlen($str);
    if ($l > 18) return 'text-base';
    if ($l > 14) return 'text-lg';
    if ($l > 11) return 'text-xl';
    return 'text-2xl';
}
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6 overflow-hidden">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
      <i class="ti ti-currency-rupee"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Total Sales</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtTotalSales) ?>" title="<?= $fmtTotalSales ?>"><?= $fmtTotalSales ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl">
      <i class="ti ti-shopping-cart"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Cost of Goods</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtTotalCost) ?>" title="<?= $fmtTotalCost ?>"><?= $fmtTotalCost ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
      <i class="ti ti-chart-line"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Total Profit</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtTotalProfit) ?>" title="<?= $fmtTotalProfit ?>"><?= $fmtTotalProfit ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
      <i class="ti ti-calendar-stats"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Monthly Sales</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtMonthlySales) ?>" title="<?= $fmtMonthlySales ?>"><?= $fmtMonthlySales ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl">
      <i class="ti ti-shopping-cart-discount"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Monthly Cost</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtMonthlyCost) ?>" title="<?= $fmtMonthlyCost ?>"><?= $fmtMonthlyCost ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
      <i class="ti ti-report-money"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Monthly Profit</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtMonthlyProfit) ?>" title="<?= $fmtMonthlyProfit ?>"><?= $fmtMonthlyProfit ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4 min-w-0">
    <div class="w-12 h-12 shrink-0 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
      <i class="ti ti-truck-delivery"></i>
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1 truncate">Monthly Delivered</p>
      <p class="font-bold text-gray-900 truncate <?= fitText($fmtMonthlyDeliveredAmount) ?>" title="<?= $fmtMonthlyDeliveredAmount ?> (<?= $fmtMonthlyDeliveredCount ?> Orders)"><?= $fmtMonthlyDeliveredAmount ?></p>
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

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col h-full">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Pending Orders (This Month)</h3>
    </div>
    <div class="overflow-x-auto flex-1">
      <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-100">
          <?php foreach (array_slice($pendingOrders, 0, 7) as $order): ?>
          <tr>
            <td class="py-3 font-medium text-gray-800">#<?= (int) $order['order_id'] ?> · <?= h($order['full_name']) ?></td>
            <td class="py-3 text-right text-gray-800"><?= formatMoney($order['total_amount'], $order['currency']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($pendingOrders)): ?>
          <tr><td colspan="2" class="py-6 text-center text-gray-400">No pending orders this month.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (count($pendingOrders) > 7): ?>
    <div class="mt-4 text-center pt-2 border-t border-gray-100">
      <a href="../order/listorder.php?status=pending" data-spa data-page="orders" class="text-sm text-brand font-medium hover:underline">View All Pending</a>
    </div>
    <?php endif; ?>
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

