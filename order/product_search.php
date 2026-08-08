<?php
require '../config/database.php';
require '../includes/auth_check.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare('
    SELECT product_id, name, sku, unit_price
    FROM products
    WHERE name LIKE ? OR sku LIKE ?
    ORDER BY name ASC
    LIMIT 8
');
$stmt->execute(["%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll());
