<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';
require '../includes/locations.php';

$activePage = 'customers';
$pageTitle  = 'Edit Customer';

$id = (int) ($_GET['id'] ?? 0);

// ---------------------------------------------------------
// Handle submission (AJAX POST, returns JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id        = (int) ($_POST['customer_id'] ?? 0);
    $fullName  = trim($_POST['full_name'] ?? '');
    $instagram = trim($_POST['instagram_handle'] ?? '');
    $whatsapp  = trim($_POST['whatsapp_number'] ?? '');
    $country   = trim($_POST['country'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $gender    = trim($_POST['gender'] ?? '');

    $errors = [];
    if ($fullName === '') $errors['full_name'] = 'Full name is required.';
    if ($whatsapp === '') $errors['whatsapp_number'] = 'WhatsApp number is required.';
    if ($country === '')  $errors['country'] = 'Country is required.';

    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'Photo upload failed, please try again.';
        } elseif (!isset($allowed[$file['type']])) {
            $errors['photo'] = 'Only JPEG or PNG images are allowed.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['photo'] = 'Image must be 2MB or smaller.';
        } else {
            $ext = $allowed[$file['type']];
            $filename = 'cust_' . uniqid() . '.' . $ext;
            $destDir  = '../assets/uploads/customers/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                $photoPath = 'assets/uploads/customers/' . $filename;
            } else {
                $errors['photo'] = 'Could not save the uploaded image.';
            }
        }
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    if ($photoPath) {
        $stmt = $pdo->prepare('
            UPDATE customers SET full_name=?, instagram_handle=?, whatsapp_number=?, country=?, city=?, gender=?, photo_path=?
            WHERE customer_id=?
        ');
        $stmt->execute([$fullName, $instagram, $whatsapp, $country, $city, $gender ?: null, $photoPath, $id]);
    } else {
        $stmt = $pdo->prepare('
            UPDATE customers SET full_name=?, instagram_handle=?, whatsapp_number=?, country=?, city=?, gender=?
            WHERE customer_id=?
        ');
        $stmt->execute([$fullName, $instagram, $whatsapp, $country, $city, $gender ?: null, $id]);
    }

    echo json_encode(['success' => true, 'customer_id' => $id]);
    exit;
}

// ---------------------------------------------------------
// Load existing customer (GET)
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

ob_start();

if (!$customer):
?>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center text-gray-400">
    Customer not found. <a href="listcustomer.php" data-spa class="text-brand hover:underline">Back to list</a>
  </div>
<?php
else:
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Edit Customer</h2>
  <p class="text-sm text-gray-400 mt-1">
    <a href="../dashboard/index.php" data-spa data-page="dashboard" class="hover:text-brand">Dashboard</a>
    <span class="mx-1">&gt;</span>
    <a href="listcustomer.php" data-spa class="hover:text-brand">Customer Management</a>
    <span class="mx-1">&gt;</span> Edit Customer
  </p>
</div>

<form id="addCustomerForm" data-mode="edit" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8" novalidate>
  <input type="hidden" name="customer_id" value="<?= (int) $customer['customer_id'] ?>">

  <div class="flex flex-col items-center mb-8">
    <div class="relative">
      <button type="button" id="photoTrigger"
              class="w-24 h-24 rounded-full border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden">
        <?php if (!empty($customer['photo_path'])): ?>
          <img id="photoPreview" src="../<?= h($customer['photo_path']) ?>" class="w-full h-full object-cover" alt="">
          <i id="photoPlaceholderIcon" class="ti ti-camera-plus text-2xl text-gray-400 hidden"></i>
        <?php else: ?>
          <img id="photoPreview" class="hidden w-full h-full object-cover" alt="">
          <i id="photoPlaceholderIcon" class="ti ti-camera-plus text-2xl text-gray-400"></i>
        <?php endif; ?>
      </button>
      <span class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center pointer-events-none">
        <i class="ti ti-pencil text-sm"></i>
      </span>
    </div>
    <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png" class="hidden">
    <p class="text-sm text-gray-700 mt-3">Upload Profile Picture (Optional)</p>
    <p class="text-xs text-gray-400">JPEG or PNG. Max size 2MB.</p>
    <p id="photoError" class="text-xs text-red-600 mt-1 hidden"></p>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
      <input type="text" name="full_name" value="<?= h($customer['full_name']) ?>" placeholder="Enter full name..."
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
      <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="full_name"></p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Instagram Username</label>
      <input type="text" name="instagram_handle" value="<?= h($customer['instagram_handle']) ?>" placeholder="Enter instagram handle..."
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number <span class="text-red-500">*</span></label>
      <input type="text" name="whatsapp_number" value="<?= h($customer['whatsapp_number']) ?>" placeholder="Enter whatsapp number..."
             class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
      <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="whatsapp_number"></p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
      <select name="country" id="countrySelect"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
        <option value="">Country</option>
        <?php foreach ($COUNTRIES as $c): ?>
          <option value="<?= h($c) ?>" <?= $customer['country'] === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="country"></p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
      <select name="city" id="citySelect" data-selected="<?= h($customer['city']) ?>"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand">
        <option value="">City</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
      <div class="flex items-center gap-6 pt-1">
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input type="radio" name="gender" value="male" class="accent-brand" <?= $customer['gender'] === 'male' ? 'checked' : '' ?>> Male
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input type="radio" name="gender" value="female" class="accent-brand" <?= $customer['gender'] === 'female' ? 'checked' : '' ?>> Female
        </label>
      </div>
    </div>

  </div>

  <div id="formGeneralError" class="mt-6 hidden rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2"></div>

  <div class="flex justify-end gap-3 mt-8">
    <a href="listcustomer.php" data-spa
       class="rounded-full border border-gray-300 bg-white text-sm font-medium px-5 py-2.5 text-gray-700 hover:bg-gray-50">
      Cancel
    </a>
    <button type="submit"
            class="rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-6 py-2.5">
      Save Changes
    </button>
  </div>

</form>

<?php endif; ?>

<?php
$content = ob_get_clean();

if (isset($_GET['partial'])) {
    echo $content;
    exit;
}

require '../includes/layout_head.php';
echo $content;
require '../includes/layout_foot.php';
