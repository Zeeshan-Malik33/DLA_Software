<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';
require '../includes/locations.php';

$activePage = 'orders';
$pageTitle  = 'Add Order';

// ---------------------------------------------------------
// Handle submission (AJAX POST, returns JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    file_put_contents('debug_order.txt', "POST Data:\n" . print_r($_POST, true) . "\nFILES:\n" . print_r($_FILES, true), FILE_APPEND);

    $customerId   = (int) ($_POST['customer_id'] ?? 0);
    $fullName     = trim($_POST['full_name'] ?? '');
    $instagram    = trim($_POST['instagram_handle'] ?? '');
    $country      = trim($_POST['country'] ?? '');
    $orderDate    = trim($_POST['order_date'] ?? '');
    $expectedDate = trim($_POST['expected_delivery_date'] ?? '') ?: null;
    $shippingCost = (float) ($_POST['shipping_cost'] ?? 0);
    $shippingWeight = isset($_POST['shipping_weight_kg']) && $_POST['shipping_weight_kg'] !== '' ? (float) $_POST['shipping_weight_kg'] : null;
    $manualCostOfGoods = isset($_POST['cost_of_goods']) && $_POST['cost_of_goods'] !== '' ? (float) $_POST['cost_of_goods'] : null;
    $paymentStatus = trim($_POST['payment_status'] ?? 'pending');
    $amountPaidInput = (float) ($_POST['amount_paid'] ?? 0);
    $items = json_decode($_POST['items'] ?? '[]', true) ?: [];

    $errors = [];
    if ($fullName === '' && $customerId === 0) $errors['full_name'] = 'Full name is required.';
    if ($customerId === 0 && $fullName !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE full_name = ?');
        $stmt->execute([$fullName]);
        if ($stmt->fetchColumn() > 0) {
            $errors['full_name'] = 'A customer with this name already exists. Please select them from existing customers.';
        }
    }
    if ($orderDate === '') $errors['order_date'] = 'Order date is required.';
    if (empty($items))     $errors['items'] = 'Add at least one product to the order.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // --- Find or create the customer ---
        if ($customerId === 0) {
            if ($instagram !== '') {
                $stmt = $pdo->prepare('SELECT customer_id FROM customers WHERE instagram_handle = ? AND instagram_handle != "" LIMIT 1');
                $stmt->execute([$instagram]);
                $existing = $stmt->fetch();
            } else {
                $existing = false;
            }

            if ($existing) {
                $customerId = $existing['customer_id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO customers (full_name, instagram_handle, country) VALUES (?, ?, ?)');
                $stmt->execute([$fullName, $instagram, $country]);
                $customerId = $pdo->lastInsertId();
            }
        }

        // --- Price the order from its line items ---
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
                // New free-typed product — save it to the catalog for next time
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

            $lineTotal = $qty * $unitPrice;
            $subtotal += $lineTotal;
            $costOfGoods += $qty * $costPrice;

            $cleanItems[] = compact('productId', 'name', 'sku', 'qty', 'unitPrice');
        }

        if ($manualCostOfGoods !== null) {
            $costOfGoods = $manualCostOfGoods;
        }

        if (empty($cleanItems)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'errors' => ['items' => 'Add at least one valid product to the order.']]);
            exit;
        }

        $grandTotal = round($subtotal + $shippingCost, 2);

        $amountPaid = match ($paymentStatus) {
            'paid'    => $grandTotal,
            'partial' => min($amountPaidInput, $grandTotal),
            default   => 0,
        };

        $productDescription = implode(', ', array_column($cleanItems, 'name'));

        $stmtId = $pdo->query('
            SELECT COALESCE(
                (SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM orders WHERE order_id = 1)),
                (SELECT MIN(o1.order_id + 1)
                 FROM orders o1
                 LEFT JOIN orders o2 ON o1.order_id + 1 = o2.order_id
                 WHERE o2.order_id IS NULL)
            ) AS next_id
        ');
        $nextId = $stmtId->fetchColumn();

        $status = $_POST['status'] ?? 'pending';
        $stmt = $pdo->prepare('
            INSERT INTO orders
                (order_id, customer_id, created_by, order_date, expected_delivery_date, status,
                 product_description, total_amount, currency, amount_paid,
                 cost_of_goods, shipping_cost, shipping_weight_kg)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "PKR", ?, ?, ?, ?)
        ');
        $stmt->execute([
            $nextId, $customerId, $_SESSION['user_id'], $orderDate, $expectedDate, $status,
            $productDescription, $grandTotal, $amountPaid, $costOfGoods, $shippingCost, $shippingWeight
        ]);
        $orderId = $nextId;

        // --- Line items ---
        $stmt = $pdo->prepare('
            INSERT INTO order_items (order_id, product_id, product_name, sku, quantity, unit_price, item_image)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($cleanItems as $idx => $item) {
            $imagePath = null;
            $fileKey = "items_image_$idx";
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$fileKey]['tmp_name'];
                $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                $ext = $ext ? ".$ext" : '';
                $itemNum = $idx + 1;
                $fileName = "ord-{$orderId}-item-{$itemNum}-img" . $ext;
                $uploadDir = '../assets/uploads/order_items/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($tmpName, $uploadDir . $fileName)) {
                    $imagePath = 'assets/uploads/order_items/' . $fileName;
                }
            }
            $stmt->execute([$orderId, $item['productId'], $item['name'], $item['sku'], $item['qty'], $item['unitPrice'], $imagePath]);
        }

        // --- Status history ---
        $stmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, ?)');
        $stmt->execute([$orderId, $status, $_SESSION['user_id']]);

        // --- Initial payment, if any ---
        if ($amountPaid > 0) {
            $stmt = $pdo->prepare('INSERT INTO payments (order_id, amount, payment_date, recorded_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$orderId, $amountPaid, $orderDate, $_SESSION['user_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'order_id' => $orderId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        file_put_contents('debug_order.txt', "PDO Error:\n" . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Could not save the order. Error: ' . $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------
// Render form (GET)
// ---------------------------------------------------------
ob_start();
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Add Order</h2>
  <p class="text-sm text-gray-400 mt-1">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
    <span class="mx-1">&gt;</span>
    <a href="listorder.php" data-spa class="hover:text-brand">Order Management</a>
    <span class="mx-1">&gt;</span> Add Order
  </p>
</div>

<form id="addOrderForm" novalidate>
<input type="hidden" name="customer_id" id="customerIdField" value="">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

  <!-- Left: form -->
  <div class="lg:col-span-2 space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-900">Customer Information</h3>
        <button type="button" id="quickAddToggle" class="inline-flex items-center gap-1 rounded-full border border-blue-200 text-blue-600 text-sm font-medium px-3 py-1.5 hover:bg-blue-50">
          <i class="ti ti-plus"></i> Quick Add
        </button>
      </div>

      <!-- Existing-customer search, hidden until "Quick Add" is clicked -->
      <div id="customerSearchBox" class="hidden relative mb-5">
        <input type="text" id="customerSearchInput" placeholder="Search existing customers by name or Instagram..."
               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <div id="customerSearchResults" class="hidden absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"></div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
          <input type="text" name="full_name" id="fullNameField" placeholder="Enter full name..."
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="full_name"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Instagram Username</label>
          <input type="text" name="instagram_handle" id="instagramField" placeholder="Enter instagram handle..."
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="instagram_handle"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
          <input type="text" name="country" id="countrySelect" list="countryList" placeholder="Select or type country..."
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <datalist id="countryList">
            <?php foreach ($COUNTRIES as $c): ?>
              <option value="<?= h($c) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="country"></p>
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
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
              <th class="pb-2 font-medium">Image</th>
              <th class="pb-2 font-medium">Product Name</th>
              <th class="pb-2 font-medium text-center">Quantity</th>
              <th class="pb-2 font-medium text-right">Unit Price</th>
              <th class="pb-2 font-medium text-right">Total</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody id="productRows" class="divide-y divide-gray-100"></tbody>
        </table>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Order Details</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-8 gap-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Order Date <span class="text-red-500">*</span></label>
          <input type="date" name="order_date" value="<?= date('Y-m-d') ?>"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="order_date"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery</label>
          <input type="date" name="expected_delivery_date"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
          <select name="status" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            <option value="pending" selected>Pending</option>
            <option value="processing">Processing</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
          </select>
        </div>
      </div>
    </div>

  </div>

  <!-- Right: summary -->
  <div class="space-y-4 lg:sticky lg:top-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Order Summary</h3>
      <div class="space-y-3 text-sm">
        <div class="flex justify-between text-gray-600">
          <span>Subtotal</span> <span id="sumSubtotal">PKR 0</span>
        </div>
        <div class="flex justify-between items-center text-gray-600">
          <span>Shipping Weight (kg)</span>
          <input type="number" name="shipping_weight_kg" placeholder="0.00" min="0" step="0.01"
                 class="w-28 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand">
        </div>
        <div class="flex justify-between items-center text-gray-600">
          <span>Cost of Goods</span>
          <input type="number" name="cost_of_goods" placeholder="e.g. 500" min="0" step="0.01"
                 class="w-28 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand">
        </div>
        <div class="flex justify-between items-center text-gray-600">
          <span>Shipping</span>
          <input type="number" name="shipping_cost" id="shippingInput" value="0" min="0" step="0.01"
                 class="w-28 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand">
        </div>
      </div>
      <div class="flex justify-between items-center border-t border-gray-100 mt-4 pt-4">
        <span class="font-semibold text-gray-900">Grand Total</span>
        <span id="sumGrandTotal" class="font-bold text-brand text-lg">PKR 0</span>
      </div>
    </div>


    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-3">Payment Status</h3>
      <div class="space-y-3 text-sm">
        <label class="flex items-center gap-2 text-gray-700">
          <input type="radio" name="payment_status" value="pending" class="accent-brand" checked> Pending
        </label>
        <label class="flex items-center gap-2 text-gray-700">
          <input type="radio" name="payment_status" value="partial" class="accent-brand"> Partially paid
        </label>
        <div id="partialAmountWrap" class="hidden pl-6">
          <input type="number" name="amount_paid" id="amountPaidInput" min="0" step="0.01" placeholder="Amount paid..."
                 class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        </div>
        <label class="flex items-center gap-2 text-gray-700">
          <input type="radio" name="payment_status" value="paid" class="accent-brand"> Paid
        </label>
      </div>
    </div>

    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-5 py-3">
      <i class="ti ti-circle-check"></i> Create Order
    </button>
    <a href="listorder.php" data-spa class="block text-center rounded-full border border-gray-300 bg-white text-sm font-medium px-5 py-3 text-gray-700 hover:bg-gray-50">
      Cancel
    </a>

  </div>

</div>

<div id="formGeneralError" class="mt-6 hidden rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2"></div>

</form>

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
