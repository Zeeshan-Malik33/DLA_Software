<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

// Load system logo (same source as sidebar)
$logoStmt = $pdo->query("SELECT photo_path FROM users WHERE photo_path IS NOT NULL AND photo_path != '' LIMIT 1");
$logoPath = $logoStmt ? $logoStmt->fetchColumn() : '';
// Convert to absolute URL for embedding
$logoUrl  = $logoPath ? '../' . $logoPath : '';

// -------------------------------------------------------
// Read filter params (same logic as dashboard/index.php)
// -------------------------------------------------------
$filterType  = $_GET['type'] ?? 'monthly';
$filterMonth = $_GET['month'] ?? date('m');
$filterYear  = $_GET['year'] ?? date('Y');

$where  = '';
$label  = 'All Time';

if ($filterType === 'monthly') {
    $y = (int)$filterYear;
    $m = (int)$filterMonth;
    $where = "WHERE YEAR(o.order_date) = $y AND MONTH(o.order_date) = $m";
    $label = date('F Y', mktime(0, 0, 0, $m, 1, $y));
} elseif ($filterType === 'yearly') {
    $y = (int)$filterYear;
    $where = "WHERE YEAR(o.order_date) = $y";
    $label = (string)$y;
}

// -------------------------------------------------------
// Query orders
// -------------------------------------------------------
$stmt = $pdo->query("
    SELECT
        o.order_id,
        o.order_date,
        o.status,
        o.total_amount,
        o.cost_of_goods,
        o.shipping_cost,
        o.profit,
        o.amount_paid,
        o.remaining_balance,
        o.currency,
        o.product_description,
        c.full_name
    FROM orders o
    JOIN customers c ON c.customer_id = o.customer_id
    $where
    ORDER BY o.order_date ASC, o.order_id ASC
");
$orders = $stmt->fetchAll();

// -------------------------------------------------------
// Totals
// -------------------------------------------------------
$totalSales   = array_sum(array_column($orders, 'total_amount'));
$totalCost    = array_sum(array_column($orders, 'cost_of_goods'));
$totalShip    = array_sum(array_column($orders, 'shipping_cost'));
$totalProfit  = array_sum(array_column($orders, 'profit'));
$totalPaid    = array_sum(array_column($orders, 'amount_paid'));
$totalBalance = array_sum(array_column($orders, 'remaining_balance'));
$orderCount   = count($orders);

$profitMargin = $totalSales > 0 ? round(($totalProfit / $totalSales) * 100, 1) : 0;

function fmtPKR($v) {
    return 'Rs. ' . number_format((float)$v, 0, '.', ',');
}

$statusColors = [
    'pending'    => '#F59E0B',
    'processing' => '#3B82F6',
    'shipped'    => '#6366F1',
    'delivered'  => '#10B981',
    'cancelled'  => '#EF4444',
    'refunded'   => '#8B5CF6',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Report — <?= h($label) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 11px;
    color: #1f2937;
    background: #fff;
  }

  /* ---- Suppress browser print chrome (URL, date, title line) ---- */
  @page {
    size: A4 landscape;
    margin: 0;          /* zero margin removes Chrome's URL/date header+footer */
  }

  /* Remove browser-injected header/footer text across engines */
  html {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  @media print {
    body  { padding: 14mm 12mm; }   /* restore whitespace with body padding */
    .page { padding: 0; }
    .no-print { display: none !important; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr    { page-break-inside: avoid; }
  }
  /* ---- Page layout ---- */
  .page { padding: 28px 32px; max-width: 1100px; margin: 0 auto; }

  /* ---- Header ---- */
  .report-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 2px solid #14302A;
    padding-bottom: 14px;
    margin-bottom: 20px;
  }
  .brand-name {
    font-size: 22px;
    font-weight: 700;
    color: #14302A;
    letter-spacing: -0.5px;
  }
  .report-meta { text-align: right; color: #6b7280; line-height: 1.7; }
  .report-meta strong { color: #1f2937; font-size: 13px; }

  /* ---- Summary cards ---- */
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 22px;
  }
  .summary-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 12px;
  }
  .summary-card .label {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
    margin-bottom: 4px;
  }
  .summary-card .value {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
  }
  .summary-card.green  { border-left: 3px solid #10B981; }
  .summary-card.blue   { border-left: 3px solid #3B82F6; }
  .summary-card.orange { border-left: 3px solid #F59E0B; }
  .summary-card.red    { border-left: 3px solid #EF4444; }
  .summary-card.indigo { border-left: 3px solid #6366F1; }
  .summary-card.gray   { border-left: 3px solid #9CA3AF; }

  /* ---- Section heading ---- */
  .section-title {
    font-size: 13px;
    font-weight: 700;
    color: #14302A;
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid #e5e7eb;
  }

  /* ---- Orders table ---- */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10.5px;
  }
  thead tr {
    background: #14302A;
    color: #fff;
  }
  thead th {
    padding: 7px 10px;
    text-align: left;
    font-weight: 600;
    white-space: nowrap;
  }
  thead th.right { text-align: right; }
  thead th.center { text-align: center; }

  tbody tr:nth-child(even) { background: #f9fafb; }
  tbody tr:hover { background: #f0fdf4; }
  tbody td {
    padding: 6px 10px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
  }
  tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
  tbody td.center { text-align: center; }

  .order-num { font-weight: 700; color: #14302A; }
  .customer  { color: #374151; }
  .desc      { color: #6b7280; font-size: 9.5px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  .badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 600;
    text-transform: capitalize;
  }
  .profit-pos { color: #059669; font-weight: 700; }
  .profit-neg { color: #DC2626; font-weight: 700; }

  /* ---- Totals row ---- */
  tfoot tr {
    background: #f0fdf4;
    font-weight: 700;
  }
  tfoot td {
    padding: 8px 10px;
    border-top: 2px solid #14302A;
    font-size: 11px;
  }
  tfoot td.right { text-align: right; }

  /* ---- Footer ---- */
  .report-footer {
    margin-top: 24px;
    padding-top: 10px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    color: #9ca3af;
    font-size: 9px;
  }

  /* ---- Print tweaks ---- */
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    .page { padding: 0; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr { page-break-inside: avoid; }
  }


</style>
</head>
<body>


<div class="page">

  <!-- Report Header -->
  <div class="report-header">
    <div style="display:flex; align-items:center; gap:14px;">
      <?php if ($logoUrl): ?>
        <img src="<?= h($logoUrl) ?>" alt="Logo"
             style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #14302A;">
      <?php else: ?>
        <div style="width:56px; height:56px; border-radius:50%; background:#14302A; display:flex; align-items:center; justify-content:center;">
          <span style="color:#fff; font-size:22px; font-weight:700;">D</span>
        </div>
      <?php endif; ?>
      <div>
        <div class="brand-name">DLA Business</div>
        <div style="color:#6b7280; margin-top:2px; font-size:11px;">Order Report</div>
      </div>
    </div>
    <div class="report-meta">
      <strong><?= h($label) ?></strong><br>
      Period type: <?= ucfirst(h($filterType)) ?><br>
      Generated: <?= date('d M Y, h:i A') ?><br>
      Total Orders: <?= $orderCount ?>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="summary-grid">
    <div class="summary-card blue">
      <div class="label">Total Sales</div>
      <div class="value"><?= fmtPKR($totalSales) ?></div>
    </div>
    <div class="summary-card orange">
      <div class="label">Cost of Goods</div>
      <div class="value"><?= fmtPKR($totalCost) ?></div>
    </div>
    <div class="summary-card indigo">
      <div class="label">Shipping</div>
      <div class="value"><?= fmtPKR($totalShip) ?></div>
    </div>
    <div class="summary-card green">
      <div class="label">Net Profit</div>
      <div class="value"><?= fmtPKR($totalProfit) ?></div>
    </div>
    <div class="summary-card gray">
      <div class="label">Amount Collected</div>
      <div class="value"><?= fmtPKR($totalPaid) ?></div>
    </div>
    <div class="summary-card red">
      <div class="label">Outstanding</div>
      <div class="value"><?= fmtPKR($totalBalance) ?></div>
    </div>
  </div>

  <!-- Orders Table -->
  <div class="section-title">Order Details (<?= $orderCount ?> orders)</div>

  <?php if (empty($orders)): ?>
    <p style="text-align:center; color:#9ca3af; padding: 40px 0;">No orders found for this period.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Products</th>
        <th class="center">Status</th>
        <th class="right">Total (Rs.)</th>
        <th class="right">Cost (Rs.)</th>
        <th class="right">Shipping</th>
        <th class="right">Profit (Rs.)</th>
        <th class="right">Paid (Rs.)</th>
        <th class="right">Balance (Rs.)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <?php
        $sc = $statusColors[$o['status']] ?? '#9CA3AF';
        $profitClass = ((float)$o['profit'] >= 0) ? 'profit-pos' : 'profit-neg';
      ?>
      <tr>
        <td class="order-num"><?= (int)$o['order_id'] ?></td>
        <td><?= date('d M Y', strtotime($o['order_date'])) ?></td>
        <td class="customer"><?= h($o['full_name']) ?></td>
        <td><span class="desc" title="<?= h($o['product_description']) ?>"><?= h($o['product_description']) ?></span></td>
        <td class="center">
          <span class="badge" style="background:<?= $sc ?>22; color:<?= $sc ?>;">
            <?= h(ucfirst($o['status'])) ?>
          </span>
        </td>
        <td class="right"><?= number_format((float)$o['total_amount'],  0) ?></td>
        <td class="right"><?= number_format((float)$o['cost_of_goods'], 0) ?></td>
        <td class="right"><?= number_format((float)$o['shipping_cost'], 0) ?></td>
        <td class="right <?= $profitClass ?>"><?= number_format((float)$o['profit'], 0) ?></td>
        <td class="right"><?= number_format((float)$o['amount_paid'], 0) ?></td>
        <td class="right <?= (float)$o['remaining_balance'] > 0 ? 'profit-neg' : '' ?>">
          <?= number_format((float)$o['remaining_balance'], 0) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5">TOTALS (<?= $orderCount ?> orders)</td>
        <td class="right"><?= number_format($totalSales,   0) ?></td>
        <td class="right"><?= number_format($totalCost,    0) ?></td>
        <td class="right"><?= number_format($totalShip,    0) ?></td>
        <td class="right <?= $totalProfit >= 0 ? 'profit-pos' : 'profit-neg' ?>">
          <?= number_format($totalProfit, 0) ?>
        </td>
        <td class="right"><?= number_format($totalPaid,    0) ?></td>
        <td class="right <?= $totalBalance > 0 ? 'profit-neg' : '' ?>">
          <?= number_format($totalBalance, 0) ?>
        </td>
      </tr>
    </tfoot>
  </table>

  <!-- Profit margin callout -->
  <div style="margin-top:14px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; display:inline-block;">
    <span style="font-weight:700; color:#065f46; font-size:12px;">
      Profit Margin: <?= $profitMargin ?>%
    </span>
    <span style="color:#6b7280; margin-left:16px;">
      Net Profit <?= fmtPKR($totalProfit) ?> on Sales <?= fmtPKR($totalSales) ?>
    </span>
  </div>

  <?php endif; ?>


</div><!-- .page -->

<script>
  // Signal parent that the report is ready to print.
  // If loaded directly (not in iframe), print self.
  window.addEventListener('load', function () {
    if (window.parent && window.parent !== window) {
      // Running inside a hidden iframe — tell the parent we are ready
      window.parent.postMessage('dla-report-ready', '*');
    } else {
      // Opened directly in a tab — print after styles settle
      setTimeout(function () { window.print(); }, 400);
    }
  });
</script>

</body>
</html>
