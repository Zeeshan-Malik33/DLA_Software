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
        
        // Reset auto increment so IDs are continuous or start from 1 if empty
        $pdo->exec('ALTER TABLE customers AUTO_INCREMENT = 1');
        
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
$hasFilters = !empty($conditions);

$sql = "
    SELECT
        c.customer_id, c.full_name, c.instagram_handle, c.whatsapp_number,
        c.country, c.city, c.photo_path, c.created_at,
        COUNT(o.order_id) AS total_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_purchase,
        COALESCE(SUM(o.total_amount) - SUM(o.amount_paid), 0) AS total_balance
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

<div class="flex flex-col h-full">

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 shrink-0">
  <div>
    <h2 class="text-2xl font-bold text-gray-900">Customer Management</h2>
    <p class="text-sm text-gray-400 mt-1">
      <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
      <span class="mx-1">&gt;</span> Customers
    </p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="addcustomer.php" data-spa
       class="inline-flex items-center gap-2 rounded-full bg-[#173B32] hover:bg-[#173B32]/90 text-white text-sm font-medium px-4 py-2">
      <i class="ti ti-plus"></i> Add Customer
    </a>
    <div class="relative inline-block text-left">
      <button type="button" class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50 action-toggle">
        <i class="ti ti-filter pointer-events-none"></i> Filter
      </button>
      <div class="absolute right-0 top-full mt-2 hidden z-50 w-56 text-left action-dropdown">
        <div class="bg-white rounded-lg shadow-lg border border-gray-100 py-2 relative z-50 flex flex-col">
          <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" class="filter-checkbox accent-brand w-4 h-4" value="filter-name" <?= $name !== '' ? 'checked' : '' ?>> Search by Name
          </label>
          <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" class="filter-checkbox accent-brand w-4 h-4" value="filter-instagram" <?= $instagram !== '' ? 'checked' : '' ?>> Instagram Username
          </label>
          <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" class="filter-checkbox accent-brand w-4 h-4" value="filter-whatsapp" <?= $whatsapp !== '' ? 'checked' : '' ?>> WhatsApp Number
          </label>
          <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" class="filter-checkbox accent-brand w-4 h-4" value="filter-country" <?= $country !== '' ? 'checked' : '' ?>> Country
          </label>
          <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" class="filter-checkbox accent-brand w-4 h-4" value="filter-regfrom" <?= $regFrom !== '' ? 'checked' : '' ?>> Registered From
          </label>
          <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" class="filter-checkbox accent-brand w-4 h-4" value="filter-regto" <?= $regTo !== '' ? 'checked' : '' ?>> Registered To
          </label>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<form id="customerFilterForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 my-5 hidden">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div id="filter-name" class="<?= $name !== '' ? '' : 'hidden' ?>">
      <label class="block text-xs font-semibold text-gray-600 mb-1">Search by Name</label>
      <input type="text" name="name" value="<?= h($name) ?>" placeholder="Enter customer name..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div id="filter-instagram" class="<?= $instagram !== '' ? '' : 'hidden' ?>">
      <label class="block text-xs font-semibold text-gray-600 mb-1">Instagram Username</label>
      <input type="text" name="instagram" value="<?= h($instagram) ?>" placeholder="Enter instagram handle..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div id="filter-whatsapp" class="<?= $whatsapp !== '' ? '' : 'hidden' ?>">
      <label class="block text-xs font-semibold text-gray-600 mb-1">WhatsApp Number</label>
      <input type="text" name="whatsapp" value="<?= h($whatsapp) ?>" placeholder="Enter whatsapp number..."
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div id="filter-country" class="<?= $country !== '' ? '' : 'hidden' ?>">
      <label class="block text-xs font-semibold text-gray-600 mb-1">Country</label>
      <select name="country" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <option value="">Select country</option>
        <?php foreach ($COUNTRIES as $c): ?>
          <option value="<?= h($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="filter-regfrom" class="<?= $regFrom !== '' ? '' : 'hidden' ?>">
      <label class="block text-xs font-semibold text-gray-600 mb-1">Registered From</label>
      <input type="date" name="registered_from" value="<?= h($regFrom) ?>"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
    </div>
    <div id="filter-regto" class="<?= $regTo !== '' ? '' : 'hidden' ?>">
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
              class="inline-flex items-center gap-2 rounded-full bg-[#173B32] hover:bg-[#173B32]/90 text-white text-sm font-medium px-4 py-2">
        <i class="ti ti-filter"></i> Apply Filters
      </button>
    </div>
  </div>
</form>

<!-- Desktop Table -->
<div class="hidden lg:flex flex-col flex-1 min-h-0 bg-white rounded-xl border border-gray-200 shadow-sm relative mb-6">
  <div class="overflow-x-auto overflow-y-auto flex-1">
  <table class="w-full text-sm">
    <thead class="sticky top-0 bg-white z-10 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:border-b after:border-gray-100">
      <tr class="text-center text-xs text-gray-400 uppercase">
        <th class="px-4 py-3 font-medium">#</th>
        <th class="px-4 py-3 font-medium">Profile</th>
        <th class="px-4 py-3 font-medium">Customer Name</th>
        <th class="px-4 py-3 font-medium">Instagram</th>
        <th class="px-4 py-3 font-medium">WhatsApp</th>
        <th class="px-4 py-3 font-medium">Country</th>
        <th class="px-4 py-3 font-medium">Orders</th>
        <th class="px-4 py-3 font-medium">Purchase</th>
        <th class="px-4 py-3 font-medium">Balance</th>
        <th class="px-4 py-3 font-medium">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php foreach ($customers as $i => $cust): ?>
      <tr class="text-center">
        <td class="px-4 py-4 text-gray-400"><?= $i + 1 ?></td>
        <td class="px-4 py-4">
          <div class="flex justify-center">
            <?php if (!empty($cust['photo_path'])): ?>
              <img src="../<?= h($cust['photo_path']) ?>" alt="" class="w-9 h-9 rounded-full object-cover">
            <?php else: ?>
              <span class="w-9 h-9 rounded-full <?= avatarColor($cust['full_name']) ?> text-white text-xs font-semibold flex items-center justify-center">
                <?= h(initials($cust['full_name'])) ?>
              </span>
            <?php endif; ?>
          </div>
        </td>
        <td class="px-4 py-4 font-medium text-gray-800">
          <a href="viewcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="hover:text-brand">
            <?= h($cust['full_name'] ?: 'Unnamed') ?>
          </a>
        </td>
        <td class="px-4 py-4 text-gray-500"><?= h(ltrim($cust['instagram_handle'] ?? '', '@')) ?></td>
        <td class="px-4 py-4 text-gray-500"><?= h($cust['whatsapp_number']) ?></td>
        <td class="px-4 py-4 text-gray-600"><?= h($cust['country']) ?></td>
        <td class="px-4 py-4 text-gray-800"><?= (int) $cust['total_orders'] ?></td>
        <td class="px-4 py-4 text-gray-800"><?= formatMoney($cust['total_purchase']) ?></td>
        <td class="px-4 py-4 text-gray-800 font-semibold <?= $cust['total_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney($cust['total_balance']) ?></td>
        <td class="px-4 py-4">
          <div class="relative flex justify-center">
            <button type="button" class="text-gray-400 hover:text-gray-600 p-1 action-toggle">
              <i class="ti ti-dots-vertical text-lg pointer-events-none"></i>
            </button>
            <div class="absolute -right-2 top-full hidden pt-1 z-50 w-32 text-left action-dropdown">
              <div class="bg-white rounded-lg shadow-lg border border-gray-100 py-1 relative z-50">
                <a href="viewcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-[#173B32]">
                  <i class="ti ti-eye"></i> View
                </a>
                <a href="editcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-[#173B32]">
                  <i class="ti ti-edit"></i> Edit
                </a>
                <button type="button" data-delete-customer="<?= $cust['customer_id'] ?>" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-gray-50">
                  <i class="ti ti-trash"></i> Delete
                </button>
              </div>
            </div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($customers)): ?>
      <tr><td colspan="10" class="px-5 py-10 text-center text-gray-400">No customers found. Try adjusting your filters, or add your first customer.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Mobile Cards -->
<div class="grid grid-cols-1 gap-4 lg:hidden mb-6">
  <?php foreach ($customers as $cust): ?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 relative">
    <div class="flex justify-between items-start mb-4">
      <div class="flex items-center gap-3">
        <?php if (!empty($cust['photo_path'])): ?>
          <img src="../<?= h($cust['photo_path']) ?>" alt="" class="w-10 h-10 rounded-full object-cover">
        <?php else: ?>
          <span class="w-10 h-10 rounded-full <?= avatarColor($cust['full_name']) ?> text-white text-sm font-semibold flex items-center justify-center">
            <?= h(initials($cust['full_name'])) ?>
          </span>
        <?php endif; ?>
        <div>
          <a href="viewcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="font-medium text-gray-900 hover:text-brand block">
            <?= h($cust['full_name'] ?: 'Unnamed') ?>
          </a>
          <p class="text-xs text-gray-500 mt-0.5"><?= h($cust['country']) ?></p>
        </div>
      </div>
      <div class="relative">
        <button type="button" class="text-gray-400 hover:text-gray-600 p-1 action-toggle">
          <i class="ti ti-dots-vertical text-lg pointer-events-none"></i>
        </button>
        <div class="absolute right-0 top-full hidden pt-1 z-50 w-32 text-left action-dropdown">
          <div class="bg-white rounded-lg shadow-lg border border-gray-100 py-1 relative z-50">
            <a href="viewcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-[#173B32]">
              <i class="ti ti-eye"></i> View
            </a>
            <a href="editcustomer.php?id=<?= $cust['customer_id'] ?>" data-spa class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-[#173B32]">
              <i class="ti ti-edit"></i> Edit
            </a>
            <button type="button" data-delete-customer="<?= $cust['customer_id'] ?>" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-gray-50">
              <i class="ti ti-trash"></i> Delete
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-sm mb-4">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Instagram</p>
        <p class="text-gray-800 font-medium truncate"><?= h(ltrim($cust['instagram_handle'] ?? '—', '@')) ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">WhatsApp</p>
        <p class="text-gray-800 font-medium truncate"><?= h($cust['whatsapp_number'] ?: '—') ?></p>
      </div>
    </div>
    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Total Orders</p>
        <p class="text-gray-900 font-semibold"><?= (int) $cust['total_orders'] ?></p>
      </div>
      <div class="text-right">
        <p class="text-xs text-gray-400 mb-0.5">Balance</p>
        <p class="font-bold text-base <?= $cust['total_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney($cust['total_balance']) ?></p>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($customers)): ?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-center text-gray-400 text-sm">
    No customers found. Try adjusting your filters, or add your first customer.
  </div>
  <?php endif; ?>
</div>

</div> <!-- End of flex wrapper -->

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
