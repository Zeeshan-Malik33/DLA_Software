<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'payments';
$pageTitle  = 'Payment Management';

// ---------------------------------------------------------
// Delete (AJAX POST from the Actions column)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = (int) ($_GET['id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT order_id, amount, status FROM payments WHERE payment_id = ?');
        $stmt->execute([$id]);
        $payment = $stmt->fetch();

        if ($payment) {
            // Only reverse the order's paid total if this payment had actually counted toward it
            if ($payment['status'] === 'completed') {
                $pdo->prepare('UPDATE orders SET amount_paid = GREATEST(0, amount_paid - ?) WHERE order_id = ?')
                    ->execute([$payment['amount'], $payment['order_id']]);
            }
            $pdo->prepare('DELETE FROM payments WHERE payment_id = ?')->execute([$id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not delete this payment.']);
    }
    exit;
}

// ---------------------------------------------------------
// Filters
// ---------------------------------------------------------
$txn      = trim($_GET['txn'] ?? '');
$name     = trim($_GET['name'] ?? '');
$method   = trim($_GET['method'] ?? '');

$conditions = [];
$params = [];
if ($txn !== '')    { $conditions[] = 'p.transaction_id LIKE ?'; $params[] = "%$txn%"; }
if ($name !== '')   { $conditions[] = 'c.full_name LIKE ?';      $params[] = "%$name%"; }
if ($method !== '') { $conditions[] = 'p.payment_method = ?';    $params[] = $method; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT p.payment_id, p.transaction_id, p.payment_date, p.payment_method, p.amount, p.status,
           p.reference_number, p.order_id, c.full_name
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    JOIN customers c ON c.customer_id = o.customer_id
    $where
    ORDER BY p.created_at DESC
    LIMIT 50
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// ---------------------------------------------------------
// Stat cards
// ---------------------------------------------------------
$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
$revenueThisMonth = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn();
$revenueLastMonth = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND MONTH(payment_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(payment_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)")->fetchColumn();
$revenueTrend = $revenueLastMonth > 0 ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 0;

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

$refundsIssued = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'refunded'")->fetchColumn();
$refundsThisWeek = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'refunded' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$refundsLastWeek = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'refunded' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND payment_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$refundsTrend = $refundsLastWeek > 0 ? round((($refundsThisWeek - $refundsLastWeek) / $refundsLastWeek) * 100, 1) : 0;

$totalPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$completedPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'")->fetchColumn();
$successRate = $totalPayments > 0 ? round(($completedPayments / $totalPayments) * 100, 1) : 0;

$methodIcons = [
    'cash' => 'ti-cash', 'visa' => 'ti-credit-card', 'mastercard' => 'ti-credit-card',
    'bank_transfer' => 'ti-building-bank', 'other' => 'ti-dots',
];
$methodLabels = [
    'cash' => 'Cash', 'visa' => 'Visa', 'mastercard' => 'Mastercard',
    'bank_transfer' => 'Bank Transfer', 'other' => 'Other',
];
$statusStyles = [
    'completed' => 'bg-emerald-50 text-emerald-700',
    'pending'   => 'bg-amber-50 text-amber-700',
    'failed'    => 'bg-red-50 text-red-700',
    'refunded'  => 'bg-gray-100 text-gray-600',
];

ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Payment Management</h2>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="addpayment.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Payment
    </a>
    <a href="export.php?<?= h(http_build_query($_GET)) ?>"
       class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
      <i class="ti ti-download"></i> Export
    </a>
  </div>
</div>

<!-- Filters -->
<form id="paymentFilterForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Transaction ID</label>
      <input type="text" name="txn" value="<?= h($txn) ?>" placeholder="Enter transaction ID..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Customer Name</label>
      <input type="text" name="name" value="<?= h($name) ?>" placeholder="Enter customer name..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Method</label>
      <select name="method" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <option value="">Select method</option>
        <?php foreach ($methodLabels as $key => $label): ?>
          <option value="<?= $key ?>" <?= $method === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex gap-2 lg:justify-end">
      <button type="button" id="resetFiltersBtn"
              class="rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        Reset Filters
      </button>
      <button type="submit"
              class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
        <i class="ti ti-filter"></i> Apply Filters
      </button>
    </div>
  </div>
</form>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto mb-6">
  <table class="w-full text-sm min-w-[880px]">
    <thead>
      <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
        <th class="px-5 py-3 font-medium">#</th>
        <th class="px-5 py-3 font-medium">Transaction ID</th>
        <th class="px-5 py-3 font-medium">Date</th>
        <th class="px-5 py-3 font-medium">Customer Name</th>
        <th class="px-5 py-3 font-medium">Method</th>
        <th class="px-5 py-3 font-medium text-right">Amount</th>
        <th class="px-5 py-3 font-medium">Status</th>
        <th class="px-5 py-3 font-medium text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($payments as $i => $p): ?>
      <tr data-payment='<?= h(json_encode($p)) ?>'>
        <td class="px-5 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-5 py-4 font-semibold text-brand"><?= h($p['transaction_id']) ?></td>
        <td class="px-5 py-4 text-gray-500 whitespace-nowrap"><?= date('M j, Y, H:i', strtotime($p['payment_date'])) ?></td>
        <td class="px-5 py-4 font-medium text-gray-800"><?= h($p['full_name'] ?: 'Unnamed') ?></td>
        <td class="px-5 py-4 text-gray-600">
          <span class="inline-flex items-center gap-1.5"><i class="ti <?= $methodIcons[$p['payment_method']] ?? 'ti-dots' ?>"></i> <?= $methodLabels[$p['payment_method']] ?? ucfirst($p['payment_method']) ?></span>
        </td>
        <td class="px-5 py-4 text-right font-medium text-gray-800"><?= formatMoney($p['amount']) ?></td>
        <td class="px-5 py-4">
          <span class="inline-block <?= $statusStyles[$p['status']] ?? 'bg-gray-100 text-gray-600' ?> text-xs font-medium px-2.5 py-1 rounded-full">
            <?= ucfirst($p['status']) ?>
          </span>
        </td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-end gap-3 text-gray-400">
            <button type="button" data-view-payment title="View" class="hover:text-brand"><i class="ti ti-eye"></i></button>
            <a href="receipt.php?id=<?= $p['payment_id'] ?>" target="_blank" title="Digital Receipt" class="hover:text-brand"><i class="ti ti-file-text"></i></a>
            <button type="button" data-delete-payment="<?= $p['payment_id'] ?>" title="Delete" class="hover:text-red-600"><i class="ti ti-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($payments)): ?>
      <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No payments recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="ti ti-file-invoice text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Total Revenue</p>
      <p class="text-2xl font-bold text-gray-900"><?= formatMoney($totalRevenue) ?></p>
      <p class="text-xs <?= $revenueTrend >= 0 ? 'text-emerald-600' : 'text-red-600' ?> mt-1">
        <i class="ti ti-trending-<?= $revenueTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($revenueTrend) ?>% from last month
      </p>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0"><i class="ti ti-clock-hour-4 text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Pending Payments</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($pendingCount) ?></p>
      <p class="text-xs text-gray-400 mt-1">Waiting for approval</p>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i class="ti ti-arrow-back-up text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Refunds Issued</p>
      <p class="text-2xl font-bold text-gray-900"><?= formatMoney($refundsIssued) ?></p>
      <p class="text-xs text-red-500 mt-1"><i class="ti ti-trending-up"></i> <?= abs($refundsTrend) ?>% this week</p>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><i class="ti ti-circle-check text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Success Rate (%)</p>
      <p class="text-2xl font-bold text-gray-900"><?= $successRate ?>%</p>
      <p class="text-xs text-emerald-600 mt-1">Excellent performance</p>
    </div>
  </div>

</div>

<!-- Feature overview -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
  <div class="flex items-start gap-3">
    <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="ti ti-file-invoice"></i></span>
    <div>
      <p class="font-semibold text-gray-900 text-sm">Record Payments</p>
      <p class="text-xs text-gray-500 mt-1">Manually add offline payments or wire transfers to balance ledger.</p>
    </div>
  </div>
  <div class="flex items-start gap-3">
    <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0"><i class="ti ti-search"></i></span>
    <div>
      <p class="font-semibold text-gray-900 text-sm">Audit Trail</p>
      <p class="text-xs text-gray-500 mt-1">Filter transactions by date, status, or method for fast reconciliation.</p>
    </div>
  </div>
  <div class="flex items-start gap-3">
    <span class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center shrink-0"><i class="ti ti-file-text"></i></span>
    <div>
      <p class="font-semibold text-gray-900 text-sm">Digital Receipts</p>
      <p class="text-xs text-gray-500 mt-1">Generate printable receipts you can save or forward to customers.</p>
    </div>
  </div>
  <div class="flex items-start gap-3">
    <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="ti ti-receipt-refund"></i></span>
    <div>
      <p class="font-semibold text-gray-900 text-sm">Refund Handling</p>
      <p class="text-xs text-gray-500 mt-1">Mark payments as refunded; balances adjust automatically.</p>
    </div>
  </div>
  <div class="flex items-start gap-3">
    <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="ti ti-download"></i></span>
    <div>
      <p class="font-semibold text-gray-900 text-sm">Reporting</p>
      <p class="text-xs text-gray-500 mt-1">Export payment data as CSV for your accounting software.</p>
    </div>
  </div>
</div>

<!-- View payment modal -->
<div id="paymentModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-4">
  <div class="bg-white rounded-xl max-w-sm w-full p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Payment Details</h3>
      <button type="button" id="closePaymentModal" class="text-gray-400 hover:text-gray-600"><i class="ti ti-x"></i></button>
    </div>
    <dl id="paymentModalBody" class="space-y-2 text-sm"></dl>
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
