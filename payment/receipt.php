<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT p.*, o.order_id, c.full_name, c.whatsapp_number
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    JOIN customers c ON c.customer_id = o.customer_id
    WHERE p.payment_id = ?
');
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?= h($payment['transaction_id']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { colors: { brand: { DEFAULT: '#14302A' } } } } };
</script>
</head>
<body class="bg-gray-100 py-10 px-4">
  <div class="max-w-md mx-auto bg-white rounded-xl shadow-sm p-8">
    <div class="text-center mb-6">
      <h1 class="text-xl font-bold text-brand">Payment Receipt</h1>
      <p class="text-sm text-gray-400 mt-1"><?= h($payment['transaction_id']) ?></p>
    </div>

    <dl class="space-y-3 text-sm border-t border-b border-gray-100 py-4">
      <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd class="text-gray-800"><?= date('M j, Y, H:i', strtotime($payment['payment_date'])) ?></dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Customer</dt><dd class="text-gray-800"><?= h($payment['full_name']) ?></dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Order</dt><dd class="text-gray-800">#ORD-<?= str_pad($payment['order_id'], 5, '0', STR_PAD_LEFT) ?></dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Method</dt><dd class="text-gray-800"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></dd></div>
      <?php if ($payment['reference_number']): ?>
      <div class="flex justify-between"><dt class="text-gray-500">Reference</dt><dd class="text-gray-800"><?= h($payment['reference_number']) ?></dd></div>
      <?php endif; ?>
      <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd class="text-gray-800"><?= ucfirst($payment['status']) ?></dd></div>
    </dl>

    <div class="flex justify-between items-center mt-4">
      <span class="font-semibold text-gray-900">Amount</span>
      <span class="font-bold text-brand text-2xl"><?= formatMoney($payment['amount']) ?></span>
    </div>

    <button onclick="window.print()" class="w-full mt-8 rounded-full bg-brand text-white text-sm font-medium px-5 py-3 print:hidden">
      Print / Save as PDF
    </button>
  </div>
</body>
</html>
