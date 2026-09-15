<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

header('Content-Type: application/json');

$rows = json_decode(file_get_contents('php://input'), true);
if (!is_array($rows) || empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'No rows received.']);
    exit;
}

// ---------------------------------------------------------
// Group rows into orders. Rows sharing a non-empty "Order Ref"
// become one order with multiple line items; a blank Order Ref
// means that row is its own single-item order.
// ---------------------------------------------------------
$groups = [];
foreach ($rows as $i => $row) {
    $ref = trim((string) ($row['Order Ref'] ?? ''));
    $key = $ref !== '' ? 'ref:' . $ref : 'row:' . $i;
    $groups[$key][] = $row;
}

$createdCount = 0;
$failed = [];
$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

foreach ($groups as $key => $groupRows) {
    try {
        $pdo->beginTransaction();
        $first = $groupRows[0];

        $customerName = trim((string) ($first['Customer Name'] ?? ''));
        $whatsapp     = trim((string) ($first['WhatsApp Number'] ?? ''));
        $instagram    = trim((string) ($first['Instagram Username'] ?? ''));
        $city         = trim((string) ($first['City'] ?? ''));
        $country      = trim((string) ($first['Country'] ?? ''));
        $orderDate    = trim((string) ($first['Order Date'] ?? ''));
        $expectedDate = trim((string) ($first['Expected Delivery Date'] ?? '')) ?: null;
        $currency     = trim((string) ($first['Currency'] ?? '')) ?: 'PKR';
        $shipping     = (float) ($first['Shipping Cost'] ?? 0);
        $amountPaid   = (float) ($first['Amount Paid'] ?? 0);
        $status       = strtolower(trim((string) ($first['Status'] ?? 'pending'))) ?: 'pending';
        if (!in_array($status, $validStatuses, true)) $status = 'pending';

        $manualCostRaw = $first['Cost of Goods'] ?? '';
        $manualCost = ($manualCostRaw !== '' && $manualCostRaw !== null) ? (float) $manualCostRaw : null;

        if ($customerName === '') throw new Exception('Missing Customer Name.');
        if ($whatsapp === '')      throw new Exception('Missing WhatsApp Number.');
        if ($orderDate === '')     throw new Exception('Missing Order Date.');

        // Normalize date if Excel gave a serial number instead of text
        if (is_numeric($orderDate)) $orderDate = excelSerialToDate((float) $orderDate);
        if ($expectedDate && is_numeric($expectedDate)) $expectedDate = excelSerialToDate((float) $expectedDate);

        // --- Find or create the customer, matched by WhatsApp number ---
        $stmt = $pdo->prepare('SELECT customer_id FROM customers WHERE whatsapp_number = ? LIMIT 1');
        $stmt->execute([$whatsapp]);
        $existing = $stmt->fetch();

        if ($existing) {
            $customerId = $existing['customer_id'];
        } else {
            $newCustId = nextCustomerId($pdo);
            $stmt = $pdo->prepare('INSERT INTO customers (customer_id, full_name, instagram_handle, whatsapp_number, city, country) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$newCustId, $customerName, $instagram, $whatsapp, $city, $country]);
            $customerId = $newCustId;
        }

        // --- Price the order from its line item rows ---
        $subtotal = 0;
        $autoCostOfGoods = 0;
        $cleanItems = [];

        foreach ($groupRows as $idx => $row) {
            if ($idx > 0) {
                // Secondary rows shouldn't have order-level costs
                $extraShipping = trim((string) ($row['Shipping Cost'] ?? ''));
                $extraCost     = trim((string) ($row['Cost of Goods'] ?? ''));
                $extraPaid     = trim((string) ($row['Amount Paid'] ?? ''));
                
                if ($extraShipping !== '' || $extraCost !== '' || $extraPaid !== '') {
                    throw new Exception('Order-level fields (Shipping Cost, Cost of Goods, Amount Paid) must only be entered on the first row of an order. Please leave them blank for subsequent items.');
                }
            }

            $itemName = trim((string) ($row['Item Name'] ?? ''));
            if ($itemName === '') continue;

            $qty = max(1, (int) ($row['Quantity'] ?? 1));
            $unitPrice = (float) ($row['Unit Price'] ?? 0);

            // Reuse a matching product if one exists (for cost lookup); otherwise
            // add it to the catalog, same behavior as the single Add Order form.
            $stmt = $pdo->prepare('SELECT product_id, cost_price FROM products WHERE name = ? LIMIT 1');
            $stmt->execute([$itemName]);
            $product = $stmt->fetch();

            if ($product) {
                $productId = $product['product_id'];
                $autoCostOfGoods += $qty * (float) $product['cost_price'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO products (name, unit_price, cost_price) VALUES (?, ?, 0)');
                $stmt->execute([$itemName, $unitPrice]);
                $productId = $pdo->lastInsertId();
            }

            $subtotal += $qty * $unitPrice;
            $cleanItems[] = ['productId' => $productId, 'name' => $itemName, 'qty' => $qty, 'unitPrice' => $unitPrice];
        }

        if (empty($cleanItems)) throw new Exception('No valid item rows (Item Name / Quantity / Unit Price).');

        $costOfGoods = $manualCost !== null ? $manualCost : $autoCostOfGoods;
        $grandTotal = round($subtotal + $shipping, 2);
        $productDescription = implode(', ', array_column($cleanItems, 'name'));

        // --- Create the order ---
        $stmtId = $pdo->query('
            SELECT COALESCE(
                (SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM orders WHERE order_id = 1)),
                (SELECT MIN(o1.order_id + 1)
                 FROM orders o1
                 LEFT JOIN orders o2 ON o1.order_id + 1 = o2.order_id
                 WHERE o2.order_id IS NULL)
            ) AS next_id
        ');
        $orderId = $stmtId->fetchColumn();

        $stmt = $pdo->prepare('
            INSERT INTO orders
                (order_id, customer_id, created_by, order_date, expected_delivery_date, status,
                 product_description, total_amount, currency, amount_paid, cost_of_goods, shipping_cost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $orderId, $customerId, $_SESSION['user_id'], $orderDate, $expectedDate, $status,
            $productDescription, $grandTotal, $currency, $amountPaid, $costOfGoods, $shipping,
        ]);

        // --- Line items ---
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
        foreach ($cleanItems as $item) {
            $stmt->execute([$orderId, $item['productId'], $item['name'], $item['qty'], $item['unitPrice']]);
        }

        // --- Status history ---
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, ?)')
            ->execute([$orderId, $status, $_SESSION['user_id']]);

        // --- Initial payment, if any ---
        if ($amountPaid > 0) {
            $pdo->prepare('INSERT INTO payments (order_id, amount, payment_date, recorded_by) VALUES (?, ?, ?, ?)')
                ->execute([$orderId, $amountPaid, $orderDate, $_SESSION['user_id']]);
        }

        $pdo->commit();
        $createdCount++;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failed[] = [
            'group' => $key,
            'customer' => $groupRows[0]['Customer Name'] ?? '(blank)',
            'reason' => $e->getMessage(),
        ];
    }
}

echo json_encode(['success' => true, 'created' => $createdCount, 'failed' => $failed]);

// Converts an Excel date serial number (e.g. 45678) to Y-m-d,
// in case a cell was formatted as a date rather than text.
function excelSerialToDate(float $serial): string {
    $unixTimestamp = ($serial - 25569) * 86400;
    return gmdate('Y-m-d', (int) $unixTimestamp);
}
