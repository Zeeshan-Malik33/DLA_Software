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

$isFilterApplied = ($category !== '' || $dateFrom !== '' || $dateTo !== '');

ob_start();
?>

<div class="flex flex-col h-full pb-80 lg:pb-10">

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 shrink-0">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Personal Expenses</h2>
    <p class="text-sm text-gray-400 mt-1">
      <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
      <span class="mx-1">&gt;</span> Personal Expenses
    </p>
  </div>
  <div class="flex flex-wrap gap-2">
    <?php if ($isFilterApplied): ?>
      <a href="listexpense.php" data-spa
         class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-refresh"></i> Reset
      </a>
      <a href="export_pdf.php?<?= h(http_build_query($_GET)) ?>" target="_blank"
         class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-download"></i> Download Report
      </a>
    <?php endif; ?>

    <a href="addexpense.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Expense
    </a>
    <div class="relative">
      <button type="button" id="expenseFilterToggle"
        class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        <i class="ti ti-filter"></i> Filter <i class="ti ti-chevron-down text-xs"></i>
      </button>
      <div id="expenseFilterMenu" class="hidden absolute left-1/2 -translate-x-1/2 sm:translate-x-0 sm:left-auto sm:right-0 mt-1 w-72 sm:w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-20 p-4">
        <form id="expenseFilterForm">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Category</label>
              <select name="category" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
                <option value="">All categories</option>
                <?php foreach ($EXPENSE_CATEGORIES as $cat): ?>
                  <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
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
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" id="resetFiltersBtn"
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
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="ti ti-calculator text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Total (filtered)</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($filteredStats['total']) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= (int) $filteredStats['cnt'] ?> expense<?= $filteredStats['cnt'] == 1 ? '' : 's' ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="ti ti-calendar-month text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">This Month</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($thisMonthTotal) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= date('F Y') ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0"><i class="ti ti-calendar text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">This Year</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= formatMoney($thisYearTotal) ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= date('Y') ?></p>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-start gap-4">
    <span class="w-11 h-11 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><i class="ti ti-chart-pie text-lg"></i></span>
    <div>
      <p class="text-sm text-gray-500">Top Category</p>
      <p class="text-lg sm:text-2xl font-bold text-gray-900 break-all"><?= $topCategory ? h($topCategory['category']) : '—' ?></p>
      <p class="text-xs text-gray-400 mt-1"><?= $topCategory ? formatMoney($topCategory['total']) : 'No data' ?></p>
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
