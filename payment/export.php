<?php
require '../config/database.php';
require '../includes/auth_check.php';

$txn    = trim($_GET['txn'] ?? '');
$name   = trim($_GET['name'] ?? '');
$method = trim($_GET['method'] ?? '');

$conditions = [];
$params = [];
if ($txn !== '')    { $conditions[] = 'p.transaction_id LIKE ?'; $params[] = "%$txn%"; }
if ($name !== '')   { $conditions[] = 'c.full_name LIKE ?';      $params[] = "%$name%"; }
if ($method !== '') { $conditions[] = 'p.payment_method = ?';    $params[] = $method; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare("
    SELECT p.transaction_id, p.payment_date, c.full_name, p.payment_method, p.amount, p.status, p.reference_number, p.order_id
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    JOIN customers c ON c.customer_id = o.customer_id
    $where
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Transaction ID', 'Date', 'Customer', 'Method', 'Amount', 'Status', 'Reference', 'Order']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['transaction_id'], $r['payment_date'], $r['full_name'], $r['payment_method'],
        $r['amount'], $r['status'], $r['reference_number'], 'ORD-' . str_pad($r['order_id'], 5, '0', STR_PAD_LEFT),
    ]);
}
fclose($out);
exit;
