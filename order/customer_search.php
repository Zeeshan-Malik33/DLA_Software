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
    SELECT customer_id, full_name, instagram_handle, whatsapp_number, country, city, gender
    FROM customers
    WHERE full_name LIKE ? OR instagram_handle LIKE ?
    ORDER BY full_name ASC
    LIMIT 8
');
$stmt->execute(["%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll());
