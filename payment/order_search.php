<?php
require '../config/database.php';
require '../includes/auth_check.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

// Allow searching by "#ORD-00012", "12", or the customer's name
$numeric = preg_replace('/\D/', '', $q);

$stmt = $pdo->prepare('
    SELECT o.order_id, o.remaining_balance, o.currency, c.full_name
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    WHERE c.full_name LIKE ? OR (? <> "" AND o.order_id = ?)
    ORDER BY o.created_at DESC
    LIMIT 8
');
$stmt->execute(["%$q%", $numeric, $numeric ?: 0]);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
    $r['order_label'] = 'ORD-' . str_pad($r['order_id'], 5, '0', STR_PAD_LEFT);
}

echo json_encode($rows);
