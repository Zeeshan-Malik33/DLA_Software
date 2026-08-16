<?php
require 'config/database.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->query('INSERT INTO customers (full_name) VALUES ("Test Customer")');
    $customerId = $pdo->lastInsertId();

    $stmt = $pdo->prepare('
        INSERT INTO orders
            (customer_id, created_by, order_date, expected_delivery_date, status,
             product_description, total_amount, currency, amount_paid,
             cost_of_goods, shipping_cost, shipping_weight_kg)
        VALUES (?, ?, ?, ?, "pending", ?, ?, "PKR", ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $customerId, 1, '2026-08-14', null,
        'Test Item', 1000.00, 0,
        500.00, 100.00, null
    ]);
    
    echo "SUCCESS. Inserted ID: " . $pdo->lastInsertId();
    $pdo->query('DELETE FROM orders WHERE order_id = ' . $pdo->lastInsertId());

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
