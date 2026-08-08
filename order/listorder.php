<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'orders';
$pageTitle  = 'Order Management';

// ---------------------------------------------------------
// Orders list
// ---------------------------------------------------------
$orders = $pdo->query("
    SELECT o.order_id, o.order_date, o.status, o.total_amount, o.currency,
           c.full_name,
           (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.order_id) AS item_count
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    ORDER BY o.created_at DESC
    LIMIT 50
")->fetchAll();

// ---------------------------------------------------------
// Stat cards
// ---------------------------------------------------------
$totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$last30  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$prev30  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND order_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$ordersTrend = $prev30 > 0 ? round((($last30 - $prev30) / $prev30) * 100) : ($last30 > 0 ? 100 : 0);

$pendingShipments = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing','shipped')")->fetchColumn();
$pendingPct = $totalOrders > 0 ? round(($pendingShipments / $totalOrders) * 100) : 0;

$avgOrderValue = (float) $pdo->query("SELECT COALESCE(AVG(total_amount), 0) FROM orders")->fetchColumn();
$avgLast30 = (float) $pdo->query("SELECT COALESCE(AVG(total_amount), 0) FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$avgPrev30 = (float) $pdo->query("SELECT COALESCE(AVG(total_amount), 0) FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND order_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$avgTrend = $avgPrev30 > 0 ? round((($avgLast30 - $avgPrev30) / $avgPrev30) * 100) : 0;

$deliveredToday = $pdo->query("
    SELECT c.full_name FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    WHERE o.status = 'delivered' AND o.actual_delivery_date = CURDATE()
")->fetchAll();
$deliveredTodayCount = count($deliveredToday);

ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Order Management</h2>
    <p class="text-sm text-gray-500 mt-1">Review, track, and manage customer orders across all channels.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="export.php"
       class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
      <i class="ti ti-download"></i> Export
    </a>
    <a href="addorder.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Order
    </a>
  </div>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto mb-6">
  <table class="w-full text-sm min-w-[820px]">
    <thead>
      <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
        <th class="px-5 py-3 font-medium">#</th>
        <th class="px-5 py-3 font-medium">Order ID</th>
        <th class="px-5 py-3 font-medium">Date</th>
        <th class="px-5 py-3 font-medium">Customer Name</th>
        <th class="px-5 py-3 font-medium text-right">Items (Qty)</th>
        <th class="px-5 py-3 font-medium text-right">Total Amount</th>
        <th class="px-5 py-3 font-medium">Status</th>
        <th class="px-5 py-3 font-medium text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($orders as $i => $order): $c = statusColor($order['status']); ?>
      <tr>
        <td class="px-5 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-5 py-4">
          <a href="vieworder.php?id=<?= $order['order_id'] ?>" data-spa class="font-semibold text-brand hover:underline">
            #ORD-<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?>
          </a>
        </td>
        <td class="px-5 py-4 text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
        <td class="px-5 py-4">
          <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-full <?= avatarColor($order['full_name']) ?> text-white text-[11px] font-semibold flex items-center justify-center">
              <?= h(initials($order['full_name'])) ?>
            </span>
            <span class="font-medium text-gray-800"><?= h($order['full_name'] ?: 'Unnamed') ?></span>
          </div>
        </td>
        <td class="px-5 py-4 text-right text-gray-700"><?= (int) $order['item_count'] ?></td>
        <td class="px-5 py-4 text-right font-medium text-gray-800"><?= formatMoney($order['total_amount'], $order['currency']) ?></td>
        <td class="px-5 py-4">
          <span class="inline-flex items-center gap-1.5 <?= $c['soft'] ?> <?= $c['text'] ?> text-xs font-medium px-2.5 py-1 rounded-full">
            <span class="w-1.5 h-1.5 rounded-full <?= $c['bg'] ?>"></span> <?= h(statusLabel($order['status'])) ?>
          </span>
        </td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-end gap-3 text-gray-400">
            <a href="vieworder.php?id=<?= $order['order_id'] ?>" data-spa title="View" class="hover:text-brand"><i class="ti ti-eye"></i></a>
            <a href="editorder.php?id=<?= $order['order_id'] ?>" data-spa title="Edit" class="hover:text-brand"><i class="ti ti-edit"></i></a>
            <button type="button" data-delete-order="<?= $order['order_id'] ?>" title="Delete" class="hover:text-red-600"><i class="ti ti-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
      <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No orders yet. Click "Add Order" to create the first one.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-start justify-between mb-3">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Total Orders</p>
      <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="ti ti-clipboard-list"></i></span>
    </div>
    <div class="flex items-baseline gap-2">
      <p class="text-2xl font-bold text-gray-900"><?= number_format($totalOrders) ?></p>
      <span class="text-xs font-medium <?= $ordersTrend >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
        <i class="ti ti-trending-<?= $ordersTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($ordersTrend) ?>%
      </span>
    </div>
    <p class="text-xs text-gray-400 mt-1">vs. last 30 days</p>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-start justify-between mb-3">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Pending Shipments</p>
      <span class="w-9 h-9 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center"><i class="ti ti-truck-delivery"></i></span>
    </div>
    <div class="flex items-baseline gap-2">
      <p class="text-2xl font-bold text-gray-900"><?= number_format($pendingShipments) ?></p>
      <span class="text-xs font-medium text-red-500"><i class="ti ti-clock"></i> <?= $pendingPct ?>%</span>
    </div>
    <div class="w-full h-1.5 rounded-full bg-gray-100 mt-3 overflow-hidden">
      <div class="h-full bg-orange-400 rounded-full" style="width: <?= $pendingPct ?>%"></div>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-start justify-between mb-3">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Avg. Order Value</p>
      <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="ti ti-currency-dollar"></i></span>
    </div>
    <div class="flex items-baseline gap-2">
      <p class="text-2xl font-bold text-gray-900"><?= formatMoney($avgOrderValue) ?></p>
      <span class="text-xs font-medium <?= $avgTrend >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
        <i class="ti ti-trending-<?= $avgTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($avgTrend) ?>%
      </span>
    </div>
    <p class="text-xs text-gray-400 mt-1">Steady growth this quarter</p>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-start justify-between mb-3">
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Delivered Today</p>
      <span class="w-9 h-9 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center"><i class="ti ti-check"></i></span>
    </div>
    <p class="text-2xl font-bold text-gray-900 mb-2"><?= number_format($deliveredTodayCount) ?></p>
    <?php if ($deliveredTodayCount > 0): ?>
    <div class="flex items-center -space-x-2">
      <?php foreach (array_slice($deliveredToday, 0, 3) as $d): ?>
        <span class="w-7 h-7 rounded-full <?= avatarColor($d['full_name']) ?> text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white">
          <?= h(initials($d['full_name'])) ?>
        </span>
      <?php endforeach; ?>
      <?php if ($deliveredTodayCount > 3): ?>
        <span class="w-7 h-7 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold flex items-center justify-center ring-2 ring-white">
          +<?= $deliveredTodayCount - 3 ?>
        </span>
      <?php endif; ?>
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
