<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'orders';
$pageTitle  = 'Order Details';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT o.*, c.full_name, c.instagram_handle, c.whatsapp_number
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    WHERE o.order_id = ?
');
$stmt->execute([$id]);
$order = $stmt->fetch();

$items = [];
$payments = [];
if ($order) {
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_date DESC');
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll();
}

ob_start();

if (!$order):
?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center text-gray-400">
    Order not found. <a href="listorder.php" data-spa class="text-brand hover:underline">Back to list</a>
  </div>
<?php
else:
    $c = statusColor($order['status']);
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Order #ORD-<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></h2>
    <p class="text-sm text-gray-400 mt-1">
      <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
      <span class="mx-1">&gt;</span>
      <a href="listorder.php" data-spa class="hover:text-brand">Order Management</a>
      <span class="mx-1">&gt;</span> Order Details
    </p>
  </div>
  <div class="flex items-center gap-2">
    <span class="inline-flex items-center gap-1.5 <?= $c['soft'] ?> <?= $c['text'] ?> text-xs font-medium px-3 py-1.5 rounded-full">
      <span class="w-1.5 h-1.5 rounded-full <?= $c['bg'] ?>"></span> <?= h(statusLabel($order['status'])) ?>
    </span>
    <a href="editorder.php?id=<?= $order['order_id'] ?>" data-spa
       class="rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">Edit</a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <div class="lg:col-span-2 space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Items</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[480px]">
          <thead>
            <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
              <th class="pb-2 font-medium">Product</th>
              <th class="pb-2 font-medium">SKU</th>
              <th class="pb-2 font-medium text-center">Qty</th>
              <th class="pb-2 font-medium text-right">Unit Price</th>
              <th class="pb-2 font-medium text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach ($items as $item): ?>
            <tr>
              <td class="py-3 font-medium text-gray-800"><?= h($item['product_name']) ?></td>
              <td class="py-3 text-gray-500"><?= h($item['sku'] ?: '---') ?></td>
              <td class="py-3 text-center text-gray-700"><?= (int) $item['quantity'] ?></td>
              <td class="py-3 text-right text-gray-700"><?= formatMoney($item['unit_price'], $order['currency']) ?></td>
              <td class="py-3 text-right font-medium text-gray-800"><?= formatMoney($item['line_total'], $order['currency']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Payment History</h3>
      <ul class="space-y-3 text-sm">
        <?php foreach ($payments as $p): ?>
        <li class="flex justify-between border-b border-gray-50 pb-2">
          <span class="text-gray-600"><?= date('M j, Y', strtotime($p['payment_date'])) ?></span>
          <span class="font-medium text-gray-800"><?= formatMoney($p['amount'], $order['currency']) ?></span>
        </li>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?>
        <li class="text-gray-400">No payments recorded yet.</li>
        <?php endif; ?>
      </ul>
    </div>

  </div>

  <div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Customer</h3>
      <div class="flex items-center gap-3 mb-4">
        <span class="w-10 h-10 rounded-full <?= avatarColor($order['full_name']) ?> text-white text-sm font-semibold flex items-center justify-center">
          <?= h(initials($order['full_name'])) ?>
        </span>
        <div>
          <p class="font-medium text-gray-800"><?= h($order['full_name']) ?></p>
          <p class="text-xs text-gray-400">@<?= h(ltrim($order['instagram_handle'] ?? '', '@')) ?></p>
        </div>
      </div>
      <p class="text-sm text-gray-600 flex items-center gap-2"><i class="ti ti-brand-whatsapp text-emerald-500"></i> <?= h($order['whatsapp_number']) ?></p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Summary</h3>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between text-gray-600"><span>Order Date</span><span><?= date('M j, Y', strtotime($order['order_date'])) ?></span></div>
        <div class="flex justify-between text-gray-600"><span>Expected Delivery</span><span><?= $order['expected_delivery_date'] ? date('M j, Y', strtotime($order['expected_delivery_date'])) : '—' ?></span></div>
        <div class="flex justify-between text-gray-600"><span>Shipping</span><span><?= formatMoney($order['shipping_cost'], $order['currency']) ?></span></div>
        <div class="flex justify-between text-gray-600"><span>Paid</span><span><?= formatMoney($order['amount_paid'], $order['currency']) ?></span></div>
        <div class="flex justify-between text-red-600 font-medium"><span>Remaining</span><span><?= formatMoney($order['remaining_balance'], $order['currency']) ?></span></div>
        <div class="flex justify-between border-t border-gray-100 pt-2 mt-2 font-semibold text-gray-900">
          <span>Grand Total</span><span><?= formatMoney($order['total_amount'], $order['currency']) ?></span>
        </div>
      </div>
    </div>

  </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
