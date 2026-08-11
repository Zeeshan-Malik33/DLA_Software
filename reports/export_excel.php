<?php
require '../config/database.php';
require '../includes/auth_check.php';

$range = $_GET['range'] ?? '30';
$rangeDays = ['7' => 7, '30' => 30, '90' => 90][$range] ?? null;
$where = $rangeDays !== null ? "WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $rangeDays DAY)" : '';

$orders = $pdo->query("
    SELECT o.order_id, o.order_date, c.full_name, o.status, o.total_amount, o.currency,
           o.amount_paid, o.remaining_balance, o.cost_of_goods, o.shipping_cost, o.profit
    FROM orders o JOIN customers c ON c.customer_id = o.customer_id
    $where ORDER BY o.order_date DESC
")->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Order', 'Date', 'Customer', 'Status', 'Total', 'Currency', 'Paid', 'Remaining', 'Cost of Goods', 'Shipping', 'Profit']);
foreach ($orders as $o) {
    fputcsv($out, [
        'ORD-' . str_pad($o['order_id'], 5, '0', STR_PAD_LEFT),
        $o['order_date'], $o['full_name'], $o['status'],
        $o['total_amount'], $o['currency'], $o['amount_paid'], $o['remaining_balance'],
        $o['cost_of_goods'], $o['shipping_cost'], $o['profit'],
    ]);
}
fclose($out);
exit;