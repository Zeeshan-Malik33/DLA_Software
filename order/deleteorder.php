<?php
require '../config/database.php';
require '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

try {
    // order_items, payments, and order_status_history all cascade-delete
    // via their foreign keys, so this one statement cleans up everything.
    $stmt = $pdo->prepare('DELETE FROM orders WHERE order_id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Could not delete this order.']);
}
