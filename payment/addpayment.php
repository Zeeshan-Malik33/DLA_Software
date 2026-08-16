<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'payments';
$pageTitle  = 'Add Payment';

// ---------------------------------------------------------
// Handle submission (AJAX POST, returns JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $orderId    = (int) ($_POST['order_id'] ?? 0);
    $date       = trim($_POST['payment_date'] ?? '');
    $method     = trim($_POST['payment_method'] ?? 'cash');
    $amount     = (float) ($_POST['amount'] ?? 0);
    $reference  = trim($_POST['reference_number'] ?? '');

    $errors = [];
    if ($orderId === 0)    $errors['order_id'] = 'Select the order this payment is for.';
    if ($date === '')      $errors['payment_date'] = 'Payment date is required.';
    if ($amount <= 0)      $errors['amount'] = 'Enter an amount greater than 0.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // Generate a unique transaction ID, e.g. TRX-84213
    do {
        $transactionId = 'TRX-' . random_int(10000, 99999);
        $stmt = $pdo->prepare('SELECT 1 FROM payments WHERE transaction_id = ?');
        $stmt->execute([$transactionId]);
    } while ($stmt->fetchColumn());

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            INSERT INTO payments (order_id, transaction_id, amount, payment_method, status, reference_number, payment_date, recorded_by)
            VALUES (?, ?, ?, ?, "completed", ?, ?, ?)
        ');
        $stmt->execute([$orderId, $transactionId, $amount, $method, $reference ?: null, $date, $_SESSION['user_id']]);

        // Completed payments count toward the order's paid total
        $stmt = $pdo->prepare('UPDATE orders SET amount_paid = amount_paid + ? WHERE order_id = ?');
        $stmt->execute([$amount, $orderId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'transaction_id' => $transactionId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not record this payment. Please try again.']);
    }
    exit;
}

ob_start();
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Add Payment</h2>
  <p class="text-sm text-gray-400 mt-1">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
    <span class="mx-1">&gt;</span>
    <a href="listpayment.php" data-spa class="hover:text-brand">Payment Management</a>
    <span class="mx-1">&gt;</span> Add Payment
  </p>
</div>

<form id="addPaymentForm" novalidate>
<input type="hidden" name="order_id" id="paymentOrderId" value="">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

  <div class="lg:col-span-2 space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Payment Information</h3>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">
        <div class="relative">
          <label class="block text-sm font-medium text-gray-700 mb-1">Select Transaction/Order <span class="text-red-500">*</span></label>
          <input type="text" id="orderSearchInput" placeholder="Enter transaction ID..." autocomplete="off"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <div id="orderSearchResults" class="hidden absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"></div>
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="order_id"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Customer name</label>
          <input type="text" id="paymentCustomerName" placeholder="Enter customer name..." readonly
                 class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date <span class="text-red-500">*</span></label>
          <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="payment_date"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
          <select name="payment_method" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Amount Details</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount Received <span class="text-red-500">*</span></label>
          <input type="number" name="amount" id="amountReceivedInput" placeholder="0.00" min="0" step="0.01"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
          <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="amount"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
          <input type="text" name="reference_number" placeholder="e.g. Check #, Transaction ID"
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
        </div>
      </div>
    </div>

  </div>

  <div class="space-y-4 lg:sticky lg:top-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Payment Summary</h3>
      <div class="space-y-3 text-sm border-b border-gray-100 pb-4 mb-4">
        <div class="flex justify-between text-gray-600">
          <span>Outstanding Balance</span> <span id="sumOutstanding" class="font-medium text-gray-800">—</span>
        </div>
        <div class="flex justify-between text-gray-600">
          <span>Total Amount to Pay</span> <span id="sumAmountToPay" class="font-medium text-gray-800">—</span>
        </div>
      </div>
      <div class="flex justify-between items-start">
        <span class="font-semibold text-gray-900">Remaining<br>Balance</span>
        <span id="sumRemaining" class="font-bold text-brand text-2xl">—</span>
      </div>
      <p class="text-xs text-gray-400 mt-2">*After payment is recorded</p>
    </div>

    <button type="submit" class="w-full rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-5 py-3">
      Record Payment
    </button>
    <a href="listpayment.php" data-spa class="block text-center rounded-full border border-gray-300 bg-white text-sm font-medium px-5 py-3 text-gray-700 hover:bg-gray-50">
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
