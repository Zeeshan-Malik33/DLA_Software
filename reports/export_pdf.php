<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$range = $_GET['range'] ?? '30';
$rangeDays = ['7' => 7, '30' => 30, '90' => 90][$range] ?? null;
$rangeLabel = ['7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days', 'all' => 'All Time'][$range] ?? 'Last 30 Days';
$where = $rangeDays !== null ? "WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $rangeDays DAY)" : '';

$stats = $pdo->query("
    SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS total_sales,
           COALESCE(SUM(cost_of_goods + shipping_cost), 0) AS total_cost,
           COALESCE(SUM(profit), 0) AS total_profit,
           COALESCE(SUM(remaining_balance), 0) AS outstanding
    FROM orders $where
")->fetch();

$orders = $pdo->query("
    SELECT o.order_id, o.order_date, c.full_name, o.status, o.total_amount, o.currency, o.amount_paid, o.remaining_balance
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    $where ORDER BY o.order_date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Report — <?= h($rangeLabel) ?></title>
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
        <h1 class="text-xl font-bold text-brand">Sales Report</h1>
        <p class="text-sm text-gray-400"><?= h($rangeLabel) ?> · Generated <?= date('M j, Y, H:i') ?></p>
      </div>
      <button onclick="window.print()" class="print:hidden rounded-full bg-brand text-white text-sm font-medium px-5 py-2.5">
        Print / Save as PDF
      </button>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-8 text-center">
      <div class="border border-gray-100 rounded-lg p-3">
        <p class="text-xs text-gray-500">Orders</p>
        <p class="text-lg font-bold text-gray-900"><?= number_format($stats['order_count']) ?></p>
      </div>
      <div class="border border-gray-100 rounded-lg p-3">
        <p class="text-xs text-gray-500">Total Sales</p>
        <p class="text-lg font-bold text-gray-900"><?= formatMoney($stats['total_sales']) ?></p>
      </div>
      <div class="border border-gray-100 rounded-lg p-3">
        <p class="text-xs text-gray-500">Total Cost</p>
        <p class="text-lg font-bold text-gray-900"><?= formatMoney($stats['total_cost']) ?></p>
      </div>
      <div class="border border-gray-100 rounded-lg p-3">
        <p class="text-xs text-gray-500">Total Profit</p>
        <p class="text-lg font-bold text-gray-900"><?= formatMoney($stats['total_profit']) ?></p>
      </div>
      <div class="border border-gray-100 rounded-lg p-3">
        <p class="text-xs text-gray-500">Outstanding</p>
        <p class="text-lg font-bold text-red-600"><?= formatMoney($stats['outstanding']) ?></p>
      </div>
    </div>

    <h2 class="font-semibold text-gray-900 mb-3">Order Detail</h2>
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-200">
          <th class="py-2">Order</th>
          <th class="py-2">Date</th>
          <th class="py-2">Customer</th>
          <th class="py-2">Status</th>
          <th class="py-2 text-right">Total</th>
          <th class="py-2 text-right">Paid</th>
          <th class="py-2 text-right">Remaining</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach ($orders as $o): ?>
        <tr>
          <td class="py-2">#ORD-<?= str_pad($o['order_id'], 5, '0', STR_PAD_LEFT) ?></td>
          <td class="py-2"><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
          <td class="py-2"><?= h($o['full_name']) ?></td>
          <td class="py-2"><?= ucfirst($o['status']) ?></td>
          <td class="py-2 text-right"><?= formatMoney($o['total_amount'], $o['currency']) ?></td>
          <td class="py-2 text-right"><?= formatMoney($o['amount_paid'], $o['currency']) ?></td>
          <td class="py-2 text-right"><?= formatMoney($o['remaining_balance'], $o['currency']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
        <tr><td colspan="7" class="py-6 text-center text-gray-400">No orders in this period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>
</body>
</html>