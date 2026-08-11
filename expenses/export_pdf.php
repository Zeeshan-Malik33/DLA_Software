<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

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

$stmt = $pdo->prepare("SELECT * FROM personal_expenses $where ORDER BY expense_date DESC");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$total = array_sum(array_column($expenses, 'amount'));

$rangeLabel = 'All Expenses';
if ($dateFrom && $dateTo) $rangeLabel = date('M j, Y', strtotime($dateFrom)) . ' – ' . date('M j, Y', strtotime($dateTo));
elseif ($dateFrom) $rangeLabel = 'From ' . date('M j, Y', strtotime($dateFrom));
elseif ($dateTo) $rangeLabel = 'Through ' . date('M j, Y', strtotime($dateTo));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Personal Expenses Report</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { colors: { brand: { DEFAULT: '#14302A' } } } } };
</script>
<style>@media print { .print\:hidden { display: none !important; } }</style>
</head>
<body class="bg-gray-100 py-10 px-4">
  <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-sm p-8">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-brand">Personal Expenses Report</h1>
        <p class="text-sm text-gray-400"><?= h($rangeLabel) ?><?= $category ? ' · ' . h($category) : '' ?> · Generated <?= date('M j, Y, H:i') ?></p>
      </div>
      <button onclick="window.print()" class="print:hidden rounded-full bg-brand text-white text-sm font-medium px-5 py-2.5">
        Print / Save as PDF
      </button>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-8">
      <div class="border border-gray-100 rounded-lg p-4 text-center">
        <p class="text-xs text-gray-500">Total Expenses</p>
        <p class="text-lg font-bold text-gray-900"><?= count($expenses) ?></p>
      </div>
      <div class="border border-gray-100 rounded-lg p-4 text-center">
        <p class="text-xs text-gray-500">Total Amount</p>
        <p class="text-lg font-bold text-gray-900"><?= formatMoney($total) ?></p>
      </div>
    </div>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-200">
          <th class="py-2">Date</th>
          <th class="py-2">Name</th>
          <th class="py-2">Category</th>
          <th class="py-2 text-right">Amount</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($expenses as $e): ?>
        <tr>
          <td class="py-2"><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
          <td class="py-2"><?= h($e['name']) ?></td>
          <td class="py-2"><?= h($e['category']) ?></td>
          <td class="py-2 text-right"><?= formatMoney($e['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($expenses)): ?>
        <tr><td colspan="4" class="py-6 text-center text-gray-400">No expenses in this selection.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>
</body>
</html>
