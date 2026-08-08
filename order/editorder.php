<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'orders';
$pageTitle  = 'Edit Order';

$id = (int) ($_GET['id'] ?? 0);

// ---------------------------------------------------------
// Handle submission (AJAX POST, returns JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id           = (int) ($_POST['order_id'] ?? 0);
    $orderDate    = trim($_POST['order_date'] ?? '');
    $expectedDate = trim($_POST['expected_delivery_date'] ?? '') ?: null;
    $status       = trim($_POST['status'] ?? 'pending');
    $shippingCost = (float) ($_POST['shipping_cost'] ?? 0);
    $amountPaid   = (float) ($_POST['amount_paid'] ?? 0);
    $items        = json_decode($_POST['items'] ?? '[]', true) ?: [];

    $errors = [];
    if ($orderDate === '') $errors['order_date'] = 'Order date is required.';
    if (empty($items))     $errors['items'] = 'Add at least one product to the order.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $subtotal = 0;
        $costOfGoods = 0;
        $cleanItems = [];

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $name = trim($item['product_name'] ?? '');
            if ($name === '') continue;

            $productId = !empty($item['product_id']) ? (int) $item['product_id'] : null;
            $sku = trim($item['sku'] ?? '') ?: null;
            $costPrice = 0;

            if ($productId) {
                $stmt = $pdo->prepare('SELECT cost_price FROM products WHERE product_id = ?');
                $stmt->execute([$productId]);
                $costPrice = (float) $stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare('SELECT product_id FROM products WHERE name = ? LIMIT 1');
                $stmt->execute([$name]);
                $found = $stmt->fetch();
                if ($found) {
                    $productId = $found['product_id'];
                } else {
                    $stmt = $pdo->prepare('INSERT INTO products (sku, name, unit_price, cost_price) VALUES (?, ?, ?, 0)');
                    $stmt->execute([$sku, $name, $unitPrice]);
                    $productId = $pdo->lastInsertId();
                }
            }

            $subtotal += $qty * $unitPrice;
            $costOfGoods += $qty * $costPrice;
            $cleanItems[] = compact('productId', 'name', 'sku', 'qty', 'unitPrice');
        }

        $tax = round($subtotal * 0.10, 2);
        $grandTotal = round($subtotal + $tax + $shippingCost, 2);
        $productDescription = implode(', ', array_column($cleanItems, 'name'));

        // Was the status just changed? log it.
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE order_id = ?');
        $stmt->execute([$id]);
        $previousStatus = $stmt->fetchColumn();

        $stmt = $pdo->prepare('
            UPDATE orders SET
                order_date = ?, expected_delivery_date = ?, status = ?,
                product_description = ?, total_amount = ?, amount_paid = ?,
                cost_of_goods = ?, shipping_cost = ?
            WHERE order_id = ?
        ');
        $stmt->execute([$orderDate, $expectedDate, $status, $productDescription, $grandTotal, $amountPaid, $costOfGoods, $shippingCost, $id]);

        if ($status !== $previousStatus) {
            $stmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, ?)');
            $stmt->execute([$id, $status, $_SESSION['user_id']]);

            if ($status === 'delivered') {
                $pdo->prepare('UPDATE orders SET actual_delivery_date = COALESCE(actual_delivery_date, CURDATE()) WHERE order_id = ?')->execute([$id]);
            }
        }

        // Replace line items with the edited set
        $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($cleanItems as $item) {
            $stmt->execute([$id, $item['productId'], $item['name'], $item['sku'], $item['qty'], $item['unitPrice']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'order_id' => $id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not save changes. Please try again.']);
    }
    exit;
}

// ---------------------------------------------------------
// Load existing order (GET)
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT o.*, c.full_name, c.instagram_handle, c.whatsapp_number FROM orders o JOIN customers c ON c.customer_id = o.customer_id WHERE o.order_id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

$items = [];
if ($order) {
    $stmt = $pdo->prepare('SELECT product_id, product_name, sku, quantity, unit_price FROM order_items WHERE order_id = ?');
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
}

ob_start();

if (!$order):
?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center text-gray-400">
    Order not found. <a href="listorder.php" data-spa class="text-brand hover:underline">Back to list</a>
  </div>
<?php
else:
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Edit Order #ORD-<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></h2>
  <p class="text-sm text-gray-400 mt-1">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
    <span class="mx-1">&gt;</span>
    <a href="listorder.php" data-spa class="hover:text-brand">Order Management</a>
    <span class="mx-1">&gt;</span> Edit Order
  </p>
</div>

<form id="addOrderForm" data-mode="edit" novalidate>
<input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

  <div class="lg:col-span-2 space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Customer</h3>
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-full <?= avatarColor($order['full_name']) ?> text-white text-sm font-semibold flex items-center justify-center">
          <?= h(initials($order['full_name'])) ?>
        </span>
        <div>
          <p class="font-medium text-gray-800"><?= h($order['full_name']) ?></p>
          <p class="text-xs text-gray-400">@<?= h(ltrim($order['instagram_handle'] ?? '', '@')) ?> · <?= h($order['whatsapp_number']) ?></p>
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-3">To change the customer on this order, cancel it and create a new one.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Order Details</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-8 gap-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Order Date <span class="text-red-500">*</span></label>
          <input type="date" name="order_date" value="<?= h($order['order_date']) ?>"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="order_date"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery</label>
          <input type="date" name="expected_delivery_date" value="<?= h($order['expected_delivery_date']) ?>"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select name="status" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-900">Product Selection</h3>
        <button type="button" id="addProductRow" class="inline-flex items-center gap-1 rounded-full border border-blue-200 text-blue-600 text-sm font-medium px-3 py-1.5 hover:bg-blue-50">
          <i class="ti ti-plus"></i> Add Product
        </button>
      </div>
      <p class="field-error text-xs text-red-600 mb-2 hidden" data-field="items"></p>
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
          <thead>
            <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
              <th class="pb-2 font-medium">Product Name</th>
              <th class="pb-2 font-medium">SKU</th>
              <th class="pb-2 font-medium text-center">Quantity</th>
              <th class="pb-2 font-medium text-right">Unit Price</th>
              <th class="pb-2 font-medium text-right">Total</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody id="productRows" data-initial='<?= h(json_encode($items)) ?>' class="divide-y divide-gray-100"></tbody>
        </table>
      </div>
    </div>

  </div>

  <div class="space-y-4 lg:sticky lg:top-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Order Summary</h3>
      <div class="space-y-3 text-sm">
        <div class="flex justify-between text-gray-600"><span>Subtotal</span> <span id="sumSubtotal">PKR 0</span></div>
        <div class="flex justify-between text-gray-600"><span>Tax (10%)</span> <span id="sumTax">PKR 0</span></div>
        <div class="flex justify-between items-center text-gray-600">
          <span>Shipping</span>
          <input type="number" name="shipping_cost" id="shippingInput" value="<?= h($order['shipping_cost']) ?>" min="0" step="0.01"
                 class="w-28 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand">
        </div>
      </div>
      <div class="flex justify-between items-center border-t border-gray-100 mt-4 pt-4">
        <span class="font-semibold text-gray-900">Grand Total</span>
        <span id="sumGrandTotal" class="font-bold text-brand text-lg">PKR 0</span>
      </div>
    </div>

    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-5 py-3">
      <i class="ti ti-circle-check"></i> Save Changes
    </button>
    <a href="vieworder.php?id=<?= $order['order_id'] ?>" data-spa class="block text-center rounded-full border border-gray-300 bg-white text-sm font-medium px-5 py-3 text-gray-700 hover:bg-gray-50">
      Cancel
    </a>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-3">Amount Paid</h3>
      <input type="number" name="amount_paid" value="<?= h($order['amount_paid']) ?>" min="0" step="0.01"
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
      <p class="text-xs text-gray-400 mt-2">Adjust directly. For itemized installment history, use Payment Management.</p>
    </div>

  </div>

</div>

<div id="formGeneralError" class="mt-6 hidden rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2"></div>

</form>

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
