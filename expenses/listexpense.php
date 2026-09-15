<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';
require '../includes/expense_categories.php';

$activePage = 'expenses';
$pageTitle  = 'Personal Expenses';

// ---------------------------------------------------------
// Delete (AJAX POST from the Actions column)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM personal_expenses WHERE expense_id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

$filterMonth = $_GET['month'] ?? date('m');
$filterYear  = $_GET['year'] ?? date('Y');

$currentY = (int)$filterYear;
$currentM = (int)$filterMonth;

$conditions = ['created_by = ?'];
$params = [$_SESSION['user_id']];

if ($currentM > 0) {
    $conditions[] = 'MONTH(expense_date) = ?';
    $params[] = $currentM;
}
if ($currentY > 0) {
    $conditions[] = 'YEAR(expense_date) = ?';
    $params[] = $currentY;
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$stmt = $pdo->prepare("SELECT * FROM personal_expenses $where ORDER BY expense_date DESC, expense_id DESC LIMIT 100");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// ---------------------------------------------------------
// Stat cards
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM personal_expenses $where");
$stmt->execute($params);
$filteredStats = $stmt->fetch();

// $filteredStats['total'] IS the selected-month total (same $where clause filters by month+year)
$selectedMonthTotal = (float) $filteredStats['total'];
$selectedMonthCount = (int) $filteredStats['cnt'];

// Selected year expenses (based on filter)
$selectedYearStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM personal_expenses WHERE created_by = ? AND YEAR(expense_date) = ?');
$selectedYearStmt->execute([$_SESSION['user_id'], $currentY]);
$selectedYearTotal = (float) $selectedYearStmt->fetchColumn();

// Human-readable labels for the selected period
$monthNames = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
$selectedMonthLabel = ($monthNames[sprintf('%02d', $currentM)] ?? '') . ' ' . $currentY;
$selectedYearLabel  = (string)$currentY;

// Actual Profit Calculation
$stmt = $pdo->query("SELECT COALESCE(SUM(profit), 0) FROM orders");
$totalSystemProfit = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM personal_expenses");
$sysExp = $stmt->fetch();
$totalSystemExpensesCount = (int)$sysExp['cnt'];
$totalSystemExpenses = (float)$sysExp['total'];

$actualProfit = $totalSystemProfit - $totalSystemExpenses;

ob_start();
?>
<span data-spa-title="Personal Expenses — DLA" hidden></span>

<div class="flex flex-col h-full">

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 shrink-0">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Personal Expenses</h2>
    <p class="text-sm text-gray-400 mt-1">
      <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
      <span class="mx-1">&gt;</span> Personal Expenses
    </p>
  </div>
  <form id="expenseRangeForm" method="GET" data-spa-form class="flex flex-wrap items-center gap-2">
    <!-- Monthly: dropdown -->
    <div class="relative">
      <select name="month" onchange="this.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))"
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
      <select name="year" onchange="this.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))"
              class="appearance-none cursor-pointer rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 pr-8 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors">
        <?php for($yr = (int)date('Y'); $yr >= 2020; $yr--): ?>
          <option value="<?= $yr ?>" <?= $currentY == $yr ? 'selected' : '' ?>><?= $yr ?></option>
        <?php endfor; ?>
      </select>
      <i class="ti ti-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
    </div>

    <!-- Export Button -->
    <a href="export_pdf.php?<?= h(http_build_query($_GET)) ?>" target="_blank"
       class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white shadow-sm text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-brand focus:outline-none transition-colors">
      <i class="ti ti-file-type-pdf text-red-500"></i> PDF
    </a>

    <!-- Add Expense -->
    <a href="addexpense.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Expense
    </a>
  </form>
</div>


<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 shrink-0">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="ti ti-calculator text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Total</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($totalSystemExpenses) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= $totalSystemExpensesCount ?> expense<?= $totalSystemExpensesCount == 1 ? '' : 's' ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="ti ti-calendar-month text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Monthly Expenses</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($selectedMonthTotal) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= h($selectedMonthLabel) ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0"><i class="ti ti-calendar text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Yearly Expenses</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($selectedYearTotal) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= h($selectedYearLabel) ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><i class="ti ti-report-money text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Actual Profit</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($actualProfit) ?></p>
      <p class="text-xs text-gray-400 mt-1">System Profit - Expenses</p>
    </div>
  </div>
</div>

<!-- Desktop Table -->
<div class="hidden lg:flex flex-col flex-1 min-h-0 bg-white rounded-xl border border-gray-200 shadow-sm relative mb-6">
  <div class="overflow-x-auto overflow-y-auto flex-1">
  <table class="w-full text-sm min-w-[760px]">
    <thead class="sticky top-0 bg-white z-10 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:border-b after:border-gray-100">
      <tr class="text-center text-xs text-gray-400 uppercase">
        <th class="px-5 py-3 font-medium">#</th>
        <th class="px-5 py-3 font-medium">Date</th>
        <th class="px-5 py-3 font-medium">Name</th>
        <th class="px-5 py-3 font-medium">Category</th>
        <th class="px-5 py-3 font-medium">Description</th>
        <th class="px-5 py-3 font-medium">Amount</th>
        <th class="px-5 py-3 font-medium">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($expenses as $i => $e): ?>
      <tr class="text-center">
        <td class="px-5 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-5 py-4 text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
        <td class="px-5 py-4 font-medium text-gray-800"><?= h($e['name']) ?></td>
        <td class="px-5 py-4">
          <span class="inline-block <?= expenseCategoryColor($e['category']) ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= h($e['category']) ?></span>
        </td>
        <td class="px-5 py-4 text-gray-500 max-w-xs truncate"><?= h($e['description']) ?></td>
        <td class="px-5 py-4 font-medium text-gray-800"><?= formatMoney($e['amount']) ?></td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-center gap-3 text-gray-400">
            <a href="editexpense.php?id=<?= $e['expense_id'] ?>" data-spa title="Edit" class="hover:text-brand"><i class="ti ti-edit"></i></a>
            <button type="button" data-delete-expense="<?= $e['expense_id'] ?>" title="Delete" class="hover:text-red-600"><i class="ti ti-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($expenses)): ?>
      <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No expenses recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Mobile Cards -->
<div class="grid grid-cols-1 gap-4 lg:hidden mb-6">
  <?php foreach ($expenses as $i => $e): ?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 relative">
    <div class="flex justify-between items-start mb-3">
      <div>
        <span class="inline-block <?= expenseCategoryColor($e['category']) ?> text-xs font-medium px-2 py-0.5 rounded-full mb-1">
          <?= h($e['category']) ?>
        </span>
        <h4 class="font-bold text-gray-900"><?= h($e['name']) ?></h4>
      </div>
      <div class="flex items-center gap-3 text-gray-400">
        <a href="editexpense.php?id=<?= $e['expense_id'] ?>" data-spa title="Edit" class="hover:text-brand"><i class="ti ti-edit"></i></a>
        <button type="button" data-delete-expense="<?= $e['expense_id'] ?>" title="Delete" class="hover:text-red-600"><i class="ti ti-trash"></i></button>
      </div>
    </div>
    
    <div class="mb-3">
      <p class="text-xs text-gray-400 mb-0.5">Description</p>
      <p class="text-sm text-gray-600 line-clamp-2"><?= h($e['description'] ?: '—') ?></p>
    </div>
    
    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Date</p>
        <p class="text-sm text-gray-800 font-medium"><?= date('d M Y', strtotime($e['expense_date'])) ?></p>
      </div>
      <div class="text-right">
        <p class="text-xs text-gray-400 mb-0.5">Amount</p>
        <p class="text-gray-900 font-bold text-base"><?= formatMoney($e['amount']) ?></p>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($expenses)): ?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-center text-gray-400 text-sm">
    No expenses recorded yet.
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
