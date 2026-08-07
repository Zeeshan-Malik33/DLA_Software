<?php
require '../config/database.php';
require '../includes/auth_check.php';

$name      = trim($_GET['name'] ?? '');
$instagram = trim($_GET['instagram'] ?? '');
$whatsapp  = trim($_GET['whatsapp'] ?? '');
$country   = trim($_GET['country'] ?? '');
$regFrom   = trim($_GET['registered_from'] ?? '');
$regTo     = trim($_GET['registered_to'] ?? '');

$conditions = [];
$params = [];
if ($name !== '')      { $conditions[] = 'c.full_name LIKE ?';        $params[] = "%$name%"; }
if ($instagram !== '') { $conditions[] = 'c.instagram_handle LIKE ?'; $params[] = "%$instagram%"; }
if ($whatsapp !== '')  { $conditions[] = 'c.whatsapp_number LIKE ?';  $params[] = "%$whatsapp%"; }
if ($country !== '')   { $conditions[] = 'c.country = ?';             $params[] = $country; }
if ($regFrom !== '')   { $conditions[] = 'DATE(c.created_at) >= ?';   $params[] = $regFrom; }
if ($regTo !== '')     { $conditions[] = 'DATE(c.created_at) <= ?';   $params[] = $regTo; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare("
    SELECT c.full_name, c.instagram_handle, c.whatsapp_number, c.country, c.city,
           COUNT(o.order_id) AS total_orders,
           COALESCE(SUM(o.total_amount), 0) AS total_purchase,
           COALESCE(SUM(o.amount_paid), 0) AS total_paid,
           c.created_at
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.customer_id
    $where
    GROUP BY c.customer_id
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Full Name', 'Instagram', 'WhatsApp', 'Country', 'City', 'Total Orders', 'Total Purchase', 'Total Paid', 'Registered On']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['full_name'], $r['instagram_handle'], $r['whatsapp_number'], $r['country'], $r['city'],
        $r['total_orders'], $r['total_purchase'], $r['total_paid'], $r['created_at'],
    ]);
}
fclose($out);
exit;
