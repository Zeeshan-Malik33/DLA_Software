<?php
require '../config/database.php';
require '../includes/auth_check.php';

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

$conditions = ['created_by = ?'];
$params = [$_SESSION['user_id']];
if ($search !== '')   { $conditions[] = '(name LIKE ? OR description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category !== '') { $conditions[] = 'category = ?'; $params[] = $category; }
if ($dateFrom !== '')  { $conditions[] = 'expense_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '')    { $conditions[] = 'expense_date <= ?'; $params[] = $dateTo; }
$where = 'WHERE ' . implode(' AND ', $conditions);

$stmt = $pdo->prepare("SELECT * FROM personal_expenses $where ORDER BY expense_date DESC");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="personal_expenses_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Name', 'Category', 'Description', 'Amount']);
foreach ($expenses as $e) {
    fputcsv($out, [$e['expense_date'], $e['name'], $e['category'], $e['description'], $e['amount']]);
}
fclose($out);
exit;
