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
    <h2 class="text-2xl font-bold text-gray-900">Order #ORD-<?= (int)$order['order_id'] ?></h2>
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

<div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-6">

  <div class="lg:col-span-2 space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Items</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
              <th class="pb-2 font-medium">Product</th>
              <th class="pb-2 font-medium">Image</th>
              <th class="pb-2 font-medium text-center">Qty</th>
              <th class="pb-2 font-medium text-right">Unit Price</th>
              <th class="pb-2 font-medium text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach ($items as $item): ?>
            <tr>
              <td class="py-3 font-medium text-gray-800"><?= h($item['product_name']) ?></td>
              <td class="py-3 text-gray-500">
                <?php if ($item['item_image']): ?>
                  <button type="button" onclick="openPreviewModal('../<?= h($item['item_image']) ?>')" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-medium"><i class="ti ti-photo"></i> Preview</button>
                <?php else: ?>
                  <span class="text-gray-400">---</span>
                <?php endif; ?>
              </td>
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

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm transition-opacity">
  <div class="relative max-w-4xl w-full bg-white rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-full">
    <div class="flex items-center justify-between px-4 py-3 bg-brand text-white">
      <h3 class="font-semibold text-white">Image Preview</h3>
      <div class="flex gap-2">
        <a href="#" id="previewDownloadBtn" download class="p-2 text-white/80 hover:text-white hover:bg-white/20 rounded-lg transition-colors" title="Download Image">
          <i class="ti ti-download text-lg"></i>
        </a>
        <button type="button" onclick="closePreviewModal()" class="p-2 text-white/80 hover:text-white hover:bg-red-500 rounded-lg transition-colors" title="Close">
          <i class="ti ti-x text-lg"></i>
        </button>
      </div>
    </div>
    <div class="p-4 overflow-auto flex justify-center items-center bg-gray-100/50 flex-1 min-h-[300px]">
      <img id="previewModalImg" src="" alt="Preview" class="max-w-full max-h-[70vh] rounded shadow-sm object-contain">
    </div>
  </div>
</div>

<script>
function openPreviewModal(src) {
    const modal = document.getElementById('imagePreviewModal');
    document.getElementById('previewModalImg').src = src;
    
    const dlBtn = document.getElementById('previewDownloadBtn');
    dlBtn.href = src;
    dlBtn.download = src.split('/').pop();
    
    modal.classList.remove('hidden');
}

function closePreviewModal() {
    document.getElementById('imagePreviewModal').classList.add('hidden');
    document.getElementById('previewModalImg').src = '';
}

document.getElementById('imagePreviewModal').addEventListener('click', function(e) {
    if (e.target === this) closePreviewModal();
});
</script>

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
