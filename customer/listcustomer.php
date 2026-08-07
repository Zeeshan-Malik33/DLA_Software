<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';
require '../includes/locations.php';

$activePage = 'customers';
$pageTitle  = 'Customer Management';

// ---------------------------------------------------------
// Handle delete (AJAX POST from the Actions column)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = (int) ($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare('DELETE FROM customers WHERE customer_id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Most likely a foreign key violation because the customer has orders
        echo json_encode(['success' => false, 'message' => 'This customer has existing orders and cannot be deleted.']);
    }
    exit;
}

// ---------------------------------------------------------
// Filters
// ---------------------------------------------------------
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

$sql = "
    SELECT
        c.customer_id, c.full_name, c.instagram_handle, c.whatsapp_number,
        c.country, c.city, c.photo_path, c.created_at,
        COUNT(o.order_id) AS total_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_purchase,
        COALESCE(SUM(o.amount_paid), 0) AS total_paid
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.customer_id
    $where
    GROUP BY c.customer_id
    ORDER BY c.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// ---------------------------------------------------------
// Render (shared by full page load and AJAX partial load)
// ---------------------------------------------------------
ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-1">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Customer Management</h2>
    <p class="text-sm text-gray-400 mt-1">
      <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
      <span class="mx-1">&gt;</span> Customers
    </p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="addcustomer.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Customer
    </a>
    <a href="export.php?<?= h(http_build_query($_GET)) ?>"
       class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
      <i class="ti ti-share"></i> Export CSV
    </a>
  </div>
</div>

<!-- Filters -->
<form id="customerFilterForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 my-5">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Search by Name</label>
      <input type="text" name="name" value="<?= h($name) ?>" placeholder="Enter customer name..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Instagram Username</label>
      <input type="text" name="instagram" value="<?= h($instagram) ?>" placeholder="Enter instagram handle..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">WhatsApp Number</label>
      <input type="text" name="whatsapp" value="<?= h($whatsapp) ?>" placeholder="Enter whatsapp number..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Country</label>
      <select name="country" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <option value="">Select country</option>
        <?php foreach ($COUNTRIES as $c): ?>
          <option value="<?= h($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Registered From</label>
      <input type="date" name="registered_from" value="<?= h($regFrom) ?>"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">Registered To</label>
      <input type="date" name="registered_to" value="<?= h($regTo) ?>"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div class="flex items-end gap-2 lg:col-span-2 lg:justify-end">
      <button type="button" id="resetFiltersBtn"
              class="rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">
        Reset Filters
      </button>
      <button type="submit"
              class="inline-flex items-center gap-2 rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-4 py-2">
        <i class="ti ti-filter"></i> Apply Filters
      </button>
    </div>
  </div>
</form>

<!-- Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
  <table class="w-full text-sm min-w-[900px]">
    <thead>
      <tr class="text-left text-xs text-gray-400 uppercase border-b border-gray-100">
        <th class="px-5 py-3 font-medium">#</th>
        <th class="px-5 py-3 font-medium">Profile</th>
        <th class="px-5 py-3 font-medium">Customer Name</th>
        <th class="px-5 py-3 font-medium">Instagram</th>
        <th class="px-5 py-3 font-medium">WhatsApp</th>
        <th class="px-5 py-3 font-medium">Country</th>
        <th class="px-5 py-3 font-medium">City</th>
        <th class="px-5 py-3 font-medium text-right">Orders</th>
        <th class="px-5 py-3 font-medium text-right">Purchase</th>
        <th class="px-5 py-3 font-medium text-right">Paid</th>
        <th class="px-5 py-3 font-medium text-right">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($customers as $i => $cust): ?>
      <tr>
        <td class="px-5 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-5 py-4">
          <?php if (!empty($cust['photo_path'])): ?>
            <img src="../<?= h($cust['photo_path']) ?>" alt="" class="w-9 h-9 rounded-full object-cover">
          <?php else: ?>
            <span class="w-9 h-9 rounded-full <?= avatarColor($cust['full_name']) ?> text-white text-xs font-semibold flex items-center justify-center">
              <?= h(initials($cust['full_name'])) ?>
            </span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-4 font-medium text-gray-800">
          <a href="viewcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="hover:text-brand">
            <?= h($cust['full_name'] ?: 'Unnamed') ?>
          </a>
        </td>
        <td class="px-5 py-4 text-gray-500">@<?= h(ltrim($cust['instagram_handle'] ?? '', '@')) ?></td>
        <td class="px-5 py-4 text-gray-500"><?= h($cust['whatsapp_number']) ?></td>
        <td class="px-5 py-4 text-gray-600"><?= h($cust['country']) ?></td>
        <td class="px-5 py-4 text-gray-600"><?= h($cust['city']) ?></td>
        <td class="px-5 py-4 text-right text-gray-800"><?= (int) $cust['total_orders'] ?></td>
        <td class="px-5 py-4 text-right text-gray-800"><?= formatMoney($cust['total_purchase']) ?></td>
        <td class="px-5 py-4 text-right text-gray-800"><?= formatMoney($cust['total_paid']) ?></td>
        <td class="px-5 py-4">
          <div class="flex items-center justify-end gap-3 text-gray-400">
            <a href="viewcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa title="View" class="hover:text-brand"><i class="ti ti-eye"></i></a>
            <a href="editcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa title="Edit" class="hover:text-brand"><i class="ti ti-edit"></i></a>
            <button type="button" data-delete-customer="<?= $cust['customer_id'] ?>" title="Delete" class="hover:text-red-600"><i class="ti ti-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($customers)): ?>
      <tr><td colspan="11" class="px-5 py-10 text-center text-gray-400">No customers found. Try adjusting your filters, or add your first customer.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
