<?php
require '../config/database.php';
require '../includes/auth_check.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

// Allow searching by order number (e.g. "1", "12") or customer name
$numeric = preg_replace('/\D/', '', $q);

$stmt = $pdo->prepare('
    SELECT o.order_id, o.remaining_balance, o.total_amount, o.currency, o.order_date, c.full_name
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    WHERE c.full_name LIKE ? OR (? <> "" AND CAST(o.order_id AS CHAR) LIKE ?)
    ORDER BY o.order_id ASC
    LIMIT 10
');
$stmt->execute(["%$q%", $numeric, $numeric . '%']);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
    $r['order_label'] = (string)(int)$r['order_id']; // plain number: 1, 2, 3...
}

echo json_encode($rows);
