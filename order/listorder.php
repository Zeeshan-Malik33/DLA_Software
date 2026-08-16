<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'orders';
$pageTitle  = 'Order Management';

$status      = $_GET['status'] ?? '';
$customerName = $_GET['customer_name'] ?? '';
$dateFrom    = $_GET['date_from'] ?? '';
$dateTo      = $_GET['date_to'] ?? '';

$conditions = [];
$params = [];

if ($status !== '') { $conditions[] = 'o.status = ?'; $params[] = $status; }
if ($customerName !== '') { $conditions[] = 'c.full_name LIKE ?'; $params[] = "%$customerName%"; }
if ($dateFrom !== '') { $conditions[] = 'o.order_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $conditions[] = 'o.order_date <= ?'; $params[] = $dateTo; }

$where = '';
if (count($conditions) > 0) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

// ---------------------------------------------------------
// Orders list
// ---------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT o.order_id, o.order_date, o.status, o.total_amount, o.currency, o.amount_paid, o.remaining_balance,
           c.full_name,
           (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.order_id) AS item_count
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    $where
    ORDER BY o.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$isFilterApplied = ($status !== '' || $customerName !== '' || $dateFrom !== '' || $dateTo !== '');

// ---------------------------------------------------------
// Stat cards
// ---------------------------------------------------------
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN customers c ON c.customer_id = o.customer_id $where");
$stmtTotal->execute($params);
$totalOrders = (int) $stmtTotal->fetchColumn();

$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN customers c ON c.customer_id = o.customer_id $where " . ($where ? "AND o.status = 'pending'" : "WHERE o.status = 'pending'"));
$stmtPending->execute($params);
$pendingOrders = (int) $stmtPending->fetchColumn();

$stmtShipping = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN customers c ON c.customer_id = o.customer_id $where " . ($where ? "AND o.status IN ('processing', 'shipped')" : "WHERE o.status IN ('processing', 'shipped')"));
$stmtShipping->execute($params);
$shippingOrders = (int) $stmtShipping->fetchColumn();

$stmtDelivered = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN customers c ON c.customer_id = o.customer_id $where " . ($where ? "AND o.status = 'delivered'" : "WHERE o.status = 'delivered'"));
$stmtDelivered->execute($params);
$deliveredOrders = (int) $stmtDelivered->fetchColumn();

ob_start();
?>

<div class="flex flex-col h-full">

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6 shrink-0">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Order Management</h2>
    <p class="text-sm text-gray-500 mt-1">Review, track, and manage customer orders across all channels.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <?php if ($isFilterApplied): ?>
      <a href="listorder.php" data-spa
         class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-refresh"></i> Reset
      </a>
    <?php endif; ?>

    <a href="addorder.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Order
    </a>
    
    <div class="relative">
      <button type="button" id="orderFilterToggle"
              class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-filter"></i> Filter <i class="ti ti-chevron-down text-xs"></i>
      </button>
      <div id="orderFilterMenu" class="hidden absolute left-1/2 -translate-x-1/2 sm:translate-x-0 sm:left-auto sm:right-0 mt-1 w-72 sm:w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-20 p-4">
        <form id="orderFilterForm">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
              <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Customer Name</label>
              <input type="text" name="customer_name" value="<?= h($customerName) ?>" placeholder="Search by name..."
                     class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?= h($dateFrom) ?>"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?= h($dateTo) ?>"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
              </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" id="resetOrderFiltersBtn"
                      class="rounded-md border border-gray-300 bg-white text-xs font-medium px-3 py-1.5 text-gray-700 hover:bg-gray-50">Reset</button>
              <button type="submit"
                      class="inline-flex items-center gap-1 rounded-md bg-brand hover:bg-brand-light text-white text-xs font-medium px-3 py-1.5">
                Apply
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 shrink-0">

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center justify-between">
    <div>
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1">Total Orders</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($totalOrders) ?></p>
    </div>
    <span class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
      <i class="ti ti-shopping-cart text-2xl"></i>
    </span>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center justify-between">
    <div>
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1">Pending Orders</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($pendingOrders) ?></p>
    </div>
    <span class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
      <i class="ti ti-clock-hour-4 text-2xl"></i>
    </span>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center justify-between">
    <div>
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1">Shipping</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($shippingOrders) ?></p>
    </div>
    <span class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center shrink-0">
      <i class="ti ti-truck-delivery text-2xl"></i>
    </span>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center justify-between">
    <div>
      <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase mb-1">Delivered</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($deliveredOrders) ?></p>
    </div>
    <span class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
      <i class="ti ti-circle-check text-2xl"></i>
    </span>
  </div>

</div>

<!-- Desktop Table -->
<div class="hidden lg:flex flex-col flex-1 min-h-0 bg-white rounded-xl border border-gray-200 shadow-sm relative mb-6">
  <div class="overflow-x-auto overflow-y-auto flex-1">
  <table class="w-full text-sm min-w-[820px]">
    <thead class="sticky top-0 bg-white z-10 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:border-b after:border-gray-100">
      <tr class="text-center text-xs text-gray-400 uppercase">
        <th class="px-5 py-3 font-medium">#</th>
        <th class="px-5 py-3 font-medium">Order ID</th>
        <th class="px-5 py-3 font-medium">Date</th>
        <th class="px-5 py-3 font-medium">Customer Name</th>
        <th class="px-5 py-3 font-medium">Items (Qty)</th>
        <th class="px-5 py-3 font-medium">Total Amount</th>
        <th class="px-5 py-3 font-medium">Status</th>
        <th class="px-5 py-3 font-medium">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($orders as $i => $order): $c = statusColor($order['status']); ?>
      <tr class="text-center">
        <td class="px-5 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-5 py-4">
          <a href="vieworder.php?id=<?= $order['order_id'] ?>" data-spa class="font-semibold text-brand hover:underline">
            #ORD-<?= (int)$order['order_id'] ?>
          </a>
        </td>
        <td class="px-5 py-4 text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-center gap-2">
            <span class="w-7 h-7 rounded-full <?= avatarColor($order['full_name']) ?> text-white text-[11px] font-semibold flex items-center justify-center">
              <?= h(initials($order['full_name'])) ?>
            </span>
            <span class="font-medium text-gray-800"><?= h($order['full_name'] ?: 'Unnamed') ?></span>
          </div>
        </td>
        <td class="px-5 py-4 text-gray-600 font-medium">
          <?= (int) $order['item_count'] ?>
        </td>
        <td class="px-5 py-4 text-gray-900 font-semibold">
          <?= formatMoney($order['total_amount'], $order['currency']) ?>
        </td>
        <td class="px-5 py-4">
          <span class="inline-flex items-center justify-center gap-1.5 <?= $c['soft'] ?> <?= $c['text'] ?> text-xs font-medium px-2.5 py-1 rounded-full">
            <span class="w-1.5 h-1.5 rounded-full <?= $c['bg'] ?>"></span> <?= h(statusLabel($order['status'])) ?>
          </span>
        </td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-center gap-3 text-gray-400">
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
</div>

<!-- Mobile Cards -->
<div class="grid grid-cols-1 gap-4 lg:hidden mb-6">
  <?php foreach ($orders as $order): $c = statusColor($order['status']); ?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 relative">
    <div class="flex justify-between items-start mb-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="font-bold text-gray-900">#<?= (int) $order['order_id'] ?></span>
          <span class="inline-flex items-center gap-1.5 <?= $c['soft'] ?> <?= $c['text'] ?> text-xs font-medium px-2 py-0.5 rounded-full">
            <span class="w-1.5 h-1.5 rounded-full <?= $c['bg'] ?>"></span> <?= h(statusLabel($order['status'])) ?>
          </span>
        </div>
        <p class="text-sm text-gray-500 font-medium"><?= h($order['full_name'] ?: 'Unnamed') ?></p>
      </div>
      <div class="flex items-center gap-3 text-gray-400">
        <a href="vieworder.php?id=<?= $order['order_id'] ?>" data-spa title="View" class="hover:text-brand"><i class="ti ti-eye"></i></a>
        <a href="editorder.php?id=<?= $order['order_id'] ?>" data-spa title="Edit" class="hover:text-brand"><i class="ti ti-edit"></i></a>
        <button type="button" data-delete-order="<?= $order['order_id'] ?>" title="Delete" class="hover:text-red-600"><i class="ti ti-trash"></i></button>
      </div>
    </div>
    
    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-sm mb-4">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Date</p>
        <p class="text-gray-800 font-medium"><?= date('d M Y', strtotime($order['order_date'])) ?></p>
      </div>
      <div class="text-right">
        <p class="text-xs text-gray-400 mb-0.5">Total</p>
        <p class="text-gray-900 font-semibold"><?= formatMoney($order['total_amount'], $order['currency']) ?></p>
      </div>
    </div>
    
    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Paid</p>
        <p class="text-gray-900 font-medium"><?= formatMoney($order['amount_paid'], $order['currency']) ?></p>
      </div>
      <div class="text-right">
        <p class="text-xs text-gray-400 mb-0.5">Balance</p>
        <?php if ($order['remaining_balance'] > 0): ?>
          <p class="text-red-600 font-semibold"><?= formatMoney($order['remaining_balance'], $order['currency']) ?></p>
        <?php else: ?>
          <p class="text-green-600 font-semibold">Fully Paid</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($orders)): ?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-center text-gray-400 text-sm">
    No orders yet. Click "Add Order" to create the first one.
  </div>
  <?php endif; ?>
</div>

</div> <!-- End of flex wrapper -->

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
