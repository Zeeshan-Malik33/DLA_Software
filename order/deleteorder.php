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
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM payments WHERE order_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$id]);
    
    $stmt = $pdo->prepare('DELETE FROM orders WHERE order_id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Could not delete this order. ' . $e->getMessage()]);
}
