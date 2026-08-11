<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';
require '../includes/expense_categories.php';

$activePage = 'expenses';
$pageTitle  = 'Add Expense';

// ---------------------------------------------------------
// Handle submission (AJAX POST, returns JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $name        = trim($_POST['name'] ?? '');
    $category    = trim($_POST['category'] ?? 'Other');
    $description = trim($_POST['description'] ?? '');
    $amount      = (float) ($_POST['amount'] ?? 0);
    $date        = trim($_POST['expense_date'] ?? '');

    $errors = [];
    if ($name === '')   $errors['name'] = 'Expense name is required.';
    if ($amount <= 0)   $errors['amount'] = 'Enter an amount greater than 0.';
    if ($date === '')   $errors['expense_date'] = 'Date is required.';
    if (!in_array($category, $EXPENSE_CATEGORIES, true)) $category = 'Other';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO personal_expenses (name, category, description, amount, expense_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$name, $category, $description, $amount, $date, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'expense_id' => $pdo->lastInsertId()]);
    exit;
}

// ---------------------------------------------------------
// Render form (GET)
// ---------------------------------------------------------
ob_start();
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Add Expense</h2>
  <p class="text-sm text-gray-400 mt-1">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
    <span class="mx-1">&gt;</span>
    <a href="listexpense.php" data-spa class="hover:text-brand">Personal Expenses</a>
    <span class="mx-1">&gt;</span> Add Expense
  </p>
</div>

<form id="expenseForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8" novalidate>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Expense Name <span class="text-red-500">*</span></label>
      <input type="text" name="name" placeholder="e.g. Grocery shopping"
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
      <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="name"></p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
      <select name="category" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
        <?php foreach ($EXPENSE_CATEGORIES as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $cat === 'Other' ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
      <input type="number" name="amount" placeholder="0.00" min="0" step="0.01"
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
      <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="amount"></p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
      <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>"
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
      <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="expense_date"></p>
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
      <textarea name="description" rows="3" placeholder="Optional notes..."
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"></textarea>
    </div>

  </div>

  <div id="formGeneralError" class="mt-6 hidden rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2"></div>

  <div class="flex justify-end gap-3 mt-8">
    <a href="listexpense.php" data-spa
       class="rounded-full border border-gray-300 bg-white text-sm font-medium px-5 py-2.5 text-gray-700 hover:bg-gray-50">
      Cancel
    </a>
    <button type="submit"
            class="rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-6 py-2.5">
      Save Expense
    </button>
  </div>

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
