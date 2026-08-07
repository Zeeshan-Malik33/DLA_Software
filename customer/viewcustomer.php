<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'customers';
$pageTitle  = 'Customer Profile';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

$orders = [];
$summary = ['total_orders' => 0, 'total_purchase' => 0, 'total_paid' => 0, 'total_remaining' => 0];

if ($customer) {
    $stmt = $pdo->prepare('
        SELECT order_id, order_date, status, product_description, total_amount, currency, amount_paid, remaining_balance
        FROM orders WHERE customer_id = ? ORDER BY order_date DESC
    ');
    $stmt->execute([$id]);
    $orders = $stmt->fetchAll();

    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS total_orders,
               COALESCE(SUM(total_amount), 0) AS total_purchase,
               COALESCE(SUM(amount_paid), 0) AS total_paid,
               COALESCE(SUM(remaining_balance), 0) AS total_remaining
        FROM orders WHERE customer_id = ?
    ');
    $stmt->execute([$id]);
    $summary = $stmt->fetch();
}

ob_start();

if (!$customer):
?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center text-gray-400">
    Customer not found. <a href="listcustomer.php" data-spa class="text-brand hover:underline">Back to list</a>
  </div>
<?php
else:
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Customer Profile</h2>
  <p class="text-sm text-gray-400 mt-1">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
    <span class="mx-1">&gt;</span>
    <a href="listcustomer.php" data-spa class="hover:text-brand">Customer Management</a>
    <span class="mx-1">&gt;</span> <?= h($customer['full_name']) ?>
  </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Profile card -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-center">
    <?php if (!empty($customer['photo_path'])): ?>
      <img src="../<?= h($customer['photo_path']) ?>" class="w-20 h-20 rounded-full object-cover mx-auto" alt="">
    <?php else: ?>
      <span class="w-20 h-20 rounded-full <?= avatarColor($customer['full_name']) ?> text-white text-xl font-semibold flex items-center justify-center mx-auto">
        <?= h(initials($customer['full_name'])) ?>
      </span>
    <?php endif; ?>
    <h3 class="text-lg font-semibold text-gray-900 mt-4"><?= h($customer['full_name'] ?: 'Unnamed') ?></h3>
    <p class="text-sm text-gray-400">@<?= h(ltrim($customer['instagram_handle'] ?? '', '@')) ?></p>

    <div class="text-left mt-6 space-y-3 text-sm">
      <p class="flex items-center gap-2 text-gray-600"><i class="ti ti-brand-whatsapp text-emerald-500"></i> <?= h($customer['whatsapp_number']) ?></p>
      <p class="flex items-center gap-2 text-gray-600"><i class="ti ti-map-pin text-gray-400"></i> <?= h($customer['city']) ?><?= $customer['city'] && $customer['country'] ? ', ' : '' ?><?= h($customer['country']) ?></p>
      <?php if (!empty($customer['gender'])): ?>
      <p class="flex items-center gap-2 text-gray-600"><i class="ti ti-user text-gray-400"></i> <?= ucfirst(h($customer['gender'])) ?></p>
      <?php endif; ?>
      <p class="flex items-center gap-2 text-gray-600"><i class="ti ti-calendar text-gray-400"></i> Registered <?= date('M j, Y', strtotime($customer['created_at'])) ?></p>
    </div>

    <div class="flex gap-2 mt-6">
      <a href="editcustomer.php?id=<?= $customer['customer_id'] ?>" data-spa
         class="flex-1 rounded-full bg-[#173B32] hover:bg-[#173B32]/90 text-white text-sm font-medium px-4 py-2 text-center">Edit</a>
      <button type="button" data-delete-customer="<?= $customer['customer_id'] ?>"
              class="flex-1 rounded-full border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2">Delete</button>
    </div>
  </div>

  <!-- Account summary + order history -->
  <div class="lg:col-span-2 space-y-6">

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Total Orders</p>
        <p class="text-xl font-bold text-gray-900"><?= (int) $summary['total_orders'] ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Total Purchase</p>
        <p class="text-xl font-bold text-gray-900"><?= formatMoney($summary['total_purchase']) ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Total Paid</p>
        <p class="text-xl font-bold text-gray-900"><?= formatMoney($summary['total_paid']) ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Remaining</p>
        <p class="text-xl font-bold text-red-600"><?= formatMoney($summary['total_remaining']) ?></p>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 overflow-x-auto">
      <h3 class="font-semibold text-gray-900 mb-4">Order History</h3>
      <table class="w-full text-sm min-w-[500px]">
        <thead>
          <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
            <th class="py-2 font-medium">Order</th>
            <th class="py-2 font-medium">Date</th>
            <th class="py-2 font-medium text-right">Total</th>
            <th class="py-2 font-medium text-right">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($orders as $order): $c = statusColor($order['status']); ?>
          <tr>
            <td class="py-3 font-medium text-gray-800">#<?= (int) $order['order_id'] ?> · <?= h($order['product_description']) ?></td>
            <td class="py-3 text-gray-500"><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
            <td class="py-3 text-right text-gray-800"><?= formatMoney($order['total_amount'], $order['currency']) ?></td>
            <td class="py-3 text-right">
              <span class="inline-block <?= $c['soft'] ?> <?= $c['text'] ?> text-xs font-medium px-2.5 py-1 rounded-full">
                <?= h(statusLabel($order['status'])) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
          <tr><td colspan="4" class="py-6 text-center text-gray-400">No orders yet for this customer.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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
