<?php
require '../config/database.php';
require '../includes/auth_check.php';

$txn    = trim($_GET['txn'] ?? '');
$name   = trim($_GET['name'] ?? '');
$method = trim($_GET['method'] ?? '');

$conditions = [];
$params = [];
if ($txn !== '')    { $conditions[] = 'p.transaction_id LIKE ?'; $params[] = "%$txn%"; }
if ($name !== '')   { $conditions[] = 'c.full_name LIKE ?';      $params[] = "%$name%"; }
if ($method !== '') { $conditions[] = 'p.payment_method = ?';    $params[] = $method; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare("
    SELECT p.transaction_id, p.payment_date, c.full_name, p.payment_method, p.amount, p.status, p.reference_number, p.order_id
    FROM payments p
    JOIN orders o ON o.order_id = p.order_id
    JOIN customers c ON c.customer_id = o.customer_id
    $where
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$rowsData = [];
foreach ($rows as $r) {
    $rowsData[] = [
        'date'     => date('d/m/Y', strtotime($r['payment_date'])),
        'txn'      => $r['transaction_id'] ?: '-',
        'customer' => $r['full_name'],
        'amount'   => 'PKR ' . number_format((float)$r['amount'], 2, '.', ''),
        'method'   => ucfirst($r['payment_method']),
        'ref'      => $r['reference_number'] ?: '-',
        'order'    => 'ORD-' . str_pad($r['order_id'], 5, '0', STR_PAD_LEFT),
        'status'   => ucfirst($r['status']),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exporting Payments Report...</title>
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
</head>
<body style="background: #F9FAFB; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0;">

<div style="text-align: center; background: white; padding: 30px 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
  <div style="font-size: 24px; font-weight: 600; color: #1F2937; margin-bottom: 8px;">Generating Report...</div>
  <p style="font-size: 14px; color: #6B7280; margin: 0;">Your styled Excel payments report is downloading automatically.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const rowsData = <?= json_encode($rowsData) ?>;
  const COLUMNS = ['Date', 'Receipt / Txn No.', 'Party Name', 'Amount', 'Payment Type', 'Description / Reference', 'Order ID', 'Status'];

  const formattedRows = rowsData.map(r => ({
    'Date': r.date,
    'Receipt / Txn No.': r.txn,
    'Party Name': r.customer,
    'Amount': r.amount,
    'Payment Type': r.method,
    'Description / Reference': r.ref,
    'Order ID': r.order,
    'Status': r.status
  }));

  const worksheet = XLSX.utils.json_to_sheet(formattedRows, { header: COLUMNS });

  // Column widths matching optimal layout
  const colWidths = [14, 20, 24, 16, 16, 26, 14, 14];
  worksheet['!cols'] = colWidths.map(w => ({ wch: w }));

  // Header styling matching import orders sample file exactly
  const range = XLSX.utils.decode_range(worksheet['!ref']);

  const headerStyle = {
    fill: { fgColor: { rgb: "2563EB" } }, // Royal Blue header background
    font: { name: "Arial", sz: 11, bold: true, color: { rgb: "FFFFFF" } },
    alignment: { horizontal: "center", vertical: "center", wrapText: true },
    border: {
      top: { style: "medium", color: { rgb: "1D4ED8" } },
      bottom: { style: "medium", color: { rgb: "1D4ED8" } },
      left: { style: "thin", color: { rgb: "60A5FA" } },
      right: { style: "thin", color: { rgb: "60A5FA" } }
    }
  };

  const alignRightCols = new Set(['Amount']);
  const alignCenterCols = new Set(['Date', 'Receipt / Txn No.', 'Payment Type', 'Order ID', 'Status']);

  for (let R = range.s.r; R <= range.e.r; ++R) {
    for (let C = range.s.c; C <= range.e.c; ++C) {
      const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
      if (!worksheet[cellRef]) continue;

      if (R === 0) {
        worksheet[cellRef].s = headerStyle;
      } else {
        const colName = COLUMNS[C];
        let align = 'left';
        if (alignRightCols.has(colName)) align = 'right';
        else if (alignCenterCols.has(colName)) align = 'center';

        worksheet[cellRef].s = {
          font: { name: "Arial", sz: 10, color: { rgb: "1F2937" } },
          alignment: { vertical: "center", horizontal: align },
          border: {
            top: { style: "thin", color: { rgb: "E5E7EB" } },
            bottom: { style: "thin", color: { rgb: "E5E7EB" } },
            left: { style: "thin", color: { rgb: "E5E7EB" } },
            right: { style: "thin", color: { rgb: "E5E7EB" } }
          }
        };
      }
    }
  }

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Payments Record');
  XLSX.writeFile(workbook, 'payments_<?= date('Y-m-d') ?>.xlsx');

  setTimeout(() => { window.close(); }, 1200);
});
</script>
</body>
</html>
<?php
exit;
