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

// ---------------------------------------------------------
// Filters
// ---------------------------------------------------------
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

$conditions = ['created_by = ?'];
$params = [$_SESSION['user_id']];

if ($search !== '')   { $conditions[] = '(name LIKE ? OR description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category !== '') { $conditions[] = 'category = ?'; $params[] = $category; }
if ($dateFrom !== '')  { $conditions[] = 'expense_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '')    { $conditions[] = 'expense_date <= ?'; $params[] = $dateTo; }

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

$thisMonth = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM personal_expenses WHERE created_by = ? AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())');
$thisMonth->execute([$_SESSION['user_id']]);
$thisMonthTotal = (float) $thisMonth->fetchColumn();

$thisYear = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM personal_expenses WHERE created_by = ? AND YEAR(expense_date) = YEAR(CURDATE())');
$thisYear->execute([$_SESSION['user_id']]);
$thisYearTotal = (float) $thisYear->fetchColumn();

$stmt = $pdo->prepare("SELECT category, SUM(amount) AS total FROM personal_expenses $where GROUP BY category ORDER BY total DESC LIMIT 1");
$stmt->execute($params);
$topCategory = $stmt->fetch();

ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-1">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Personal Expenses</h2>
    <p class="text-sm text-gray-400 mt-1">
      <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
      <span class="mx-1">&gt;</span> Personal Expenses
    </p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="addexpense.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Expense
    </a>
    <div class="relative">
      <button type="button" id="exportExpenseToggle"
        class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-download"></i> Export <i class="ti ti-chevron-down text-xs"></i>
      </button>
      <div id="exportExpenseMenu" class="hidden absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-10 overflow-hidden">
        <a href="export_pdf.php?<?= h(http_build_query($_GET)) ?>" target="_blank" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Export as PDF</a>
        <a href="export_excel.php?<?= h(http_build_query($_GET)) ?>" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Export as Excel</a>
      </div>
    </div>
  </div>
</div>

<!-- Quick range presets -->
<div class="flex flex-wrap gap-2 my-4">
  <button type="button" data-expense-preset="today" class="rounded-full border border-gray-300 bg-white text-xs font-medium px-3 py-1.5 text-gray-600 hover:bg-gray-50">Today</button>
  <button type="button" data-expense-preset="week" class="rounded-full border border-gray-300 bg-white text-xs font-medium px-3 py-1.5 text-gray-600 hover:bg-gray-50">This Week</button>
  <button type="button" data-expense-preset="month" class="rounded-full border border-gray-300 bg-white text-xs font-medium px-3 py-1.5 text-gray-600 hover:bg-gray-50">This Month</button>
  <button type="button" data-expense-preset="year" class="rounded-full border border-gray-300 bg-white text-xs font-medium px-3 py-1.5 text-gray-600 hover:bg-gray-50">This Year</button>
  <button type="button" data-expense-preset="all" class="rounded-full border border-gray-300 bg-white text-xs font-medium px-3 py-1.5 text-gray-600 hover:bg-gray-50">All Time</button>
</div>

<!-- Filters -->
<form id="expenseFilterForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Name or description..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Category</label>
      <select name="category" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <option value="">All categories</option>
        <?php foreach ($EXPENSE_CATEGORIES as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Date From</label>
      <input type="date" name="date_from" id="expenseDateFrom" value="<?= h($dateFrom) ?>"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Date To</label>
      <input type="date" name="date_to" id="expenseDateTo" value="<?= h($dateTo) ?>"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div class="flex gap-2">
      <button type="button" id="resetFiltersBtn"
              class="rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">Reset</button>
      <button type="submit"
              class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
        <i class="ti ti-filter"></i> Apply
      </button>
    </div>
  </div>
</form>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto mb-6">
  <table class="w-full text-sm min-w-[760px]">
    <thead>
      <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
        <th class="px-5 py-3 font-medium">#</th>
        <th class="px-5 py-3 font-medium">Date</th>
        <th class="px-5 py-3 font-medium">Name</th>
        <th class="px-5 py-3 font-medium">Category</th>
        <th class="px-5 py-3 font-medium">Description</th>
        <th class="px-5 py-3 font-medium text-right">Amount</th>
        <th class="px-5 py-3 font-medium text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($expenses as $i => $e): ?>
      <tr>
        <td class="px-5 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-5 py-4 text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
        <td class="px-5 py-4 font-medium text-gray-800"><?= h($e['name']) ?></td>
        <td class="px-5 py-4">
          <span class="inline-block <?= expenseCategoryColor($e['category']) ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= h($e['category']) ?></span>
        </td>
        <td class="px-5 py-4 text-gray-500 max-w-xs truncate"><?= h($e['description']) ?></td>
        <td class="px-5 py-4 text-right font-medium text-gray-800"><?= formatMoney($e['amount']) ?></td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-end gap-3 text-gray-400">
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

<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Total (filtered)</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($filteredStats['total']) ?></p>
    <p class="text-xs text-gray-400 mt-2"><?= (int) $filteredStats['cnt'] ?> expense<?= $filteredStats['cnt'] == 1 ? '' : 's' ?></p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">This Month</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($thisMonthTotal) ?></p>
    <p class="text-xs text-gray-400 mt-2"><?= date('F Y') ?></p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">This Year</p>
    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($thisYearTotal) ?></p>
    <p class="text-xs text-gray-400 mt-2"><?= date('Y') ?></p>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <p class="text-sm text-gray-500 mb-2">Top Category</p>
    <p class="text-2xl font-bold text-gray-900"><?= $topCategory ? h($topCategory['category']) : '—' ?></p>
    <p class="text-xs text-gray-400 mt-2"><?= $topCategory ? formatMoney($topCategory['total']) : 'No data' ?></p>
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
