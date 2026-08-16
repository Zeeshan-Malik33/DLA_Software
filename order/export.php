<?php
require '../config/database.php';
require '../includes/auth_check.php';

$rows = $pdo->query("
    SELECT o.order_id, o.order_date, c.full_name, o.status, o.total_amount, o.currency, o.amount_paid, o.remaining_balance
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    ORDER BY o.created_at DESC
")->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Order ID', 'Date', 'Customer', 'Status', 'Total', 'Currency', 'Paid', 'Remaining']);
foreach ($rows as $r) {
    fputcsv($out, [
        'ORD-' . (int)$r['order_id'],
        $r['order_date'], $r['full_name'], $r['status'],
        $r['total_amount'], $r['currency'], $r['amount_paid'], $r['remaining_balance'],
    ]);
}
fclose($out);
exit;
