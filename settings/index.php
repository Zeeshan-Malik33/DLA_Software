<?php
require '../config/database.php';
require '../includes/auth_check.php';
require '../includes/functions.php';

$activePage = 'settings';
$pageTitle  = 'Settings';

$userId = $_SESSION['user_id'];

// ---------------------------------------------------------
// Handle submissions (AJAX POST, returns JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // --- Profile info ---
    if ($action === 'profile') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        $errors = [];
        if ($name === '')  $errors['name'] = 'Full name is required.';
        if ($email === '') $errors['email'] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';

        if ($email !== '') {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ?');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetchColumn()) $errors['email'] = 'That email is already in use.';
        }

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
                $filename = 'user_' . $userId . '_' . uniqid() . '.' . $ext;
                $destDir  = '../assets/uploads/users/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                    $photoPath = 'assets/uploads/users/' . $filename;
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
            $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, photo_path=? WHERE user_id=?');
            $stmt->execute([$name, $email, $photoPath, $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name=?, email=? WHERE user_id=?');
            $stmt->execute([$name, $email, $userId]);
        }

        $_SESSION['user_name'] = $name;
        echo json_encode(['success' => true, 'photo_path' => $photoPath]);
        exit;
    }

    // --- Password change ---
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        $errors = [];
        if (!password_verify($current, $hash)) $errors['current_password'] = 'Current password is incorrect.';
        if (strlen($new) < 6) $errors['new_password'] = 'New password must be at least 6 characters.';
        if ($new !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE user_id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ---------------------------------------------------------
// Load current user (GET)
// ---------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

ob_start();
?>

<div class="mb-6">
  <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
  <!-- Profile Information (Left Column) -->
  <div class="lg:col-span-8">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
      <div class="flex items-center justify-between border-b border-gray-100 pb-5 mb-6">
        <h3 class="font-semibold text-gray-900 text-lg">Profile Information</h3>
      </div>

      <form id="profileForm" novalidate>
        <div class="flex flex-col sm:flex-row gap-8 items-center sm:items-start">
          <!-- Avatar section -->
          <div class="flex flex-col items-center shrink-0">
            <button type="button" id="userPhotoTrigger" class="w-32 h-32 rounded-full overflow-hidden bg-gray-50 border-2 border-dashed border-gray-200 flex items-center justify-center hover:border-brand hover:bg-gray-100 transition-all group relative">
              <?php if (!empty($user['photo_path'])): ?>
                <img id="userPhotoPreview" src="../<?= h($user['photo_path']) ?>" class="w-full h-full object-cover" alt="">
                <div class="absolute inset-0 bg-black/40 hidden group-hover:flex items-center justify-center">
                  <i class="ti ti-camera text-white text-2xl"></i>
                </div>
              <?php else: ?>
                <img id="userPhotoPreview" class="hidden w-full h-full object-cover" alt="">
                <span id="userPhotoInitials" class="text-3xl font-bold text-gray-400 group-hover:hidden"><?= h(initials($user['name'])) ?></span>
                <i class="ti ti-camera text-gray-500 text-2xl hidden group-hover:block"></i>
              <?php endif; ?>
            </button>
            <input type="file" name="photo" id="userPhotoInput" accept="image/jpeg,image/png" class="hidden">
            <button type="button" id="changePictureBtn" class="text-sm font-medium text-brand hover:text-brand-light mt-4 transition-colors">Change Picture</button>
            <p id="userPhotoError" class="text-xs text-red-600 mt-2 hidden text-center max-w-[120px]"></p>
          </div>

          <!-- Form Fields -->
          <div class="flex-1 space-y-6 w-full">
            <div>
              <label class="block text-xs font-bold tracking-wider text-gray-500 uppercase mb-2">Full Name</label>
              <input type="text" name="name" value="<?= h($user['name']) ?>" placeholder="Full Name"
                     class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-all bg-gray-50/50 focus:bg-white">
              <p class="field-error text-xs text-red-600 mt-1.5 hidden" data-field="name"></p>
            </div>
            <div>
              <label class="block text-xs font-bold tracking-wider text-gray-500 uppercase mb-2">Email Address</label>
              <input type="email" name="email" value="<?= h($user['email']) ?>" placeholder="Email address"
                     class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-all bg-gray-50/50 focus:bg-white">
              <p class="field-error text-xs text-red-600 mt-1.5 hidden" data-field="email"></p>
            </div>
          </div>
        </div>

        <div id="profileSuccess" class="hidden mt-8 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm px-5 py-4 flex items-center gap-3">
          <i class="ti ti-circle-check-filled text-emerald-500 text-lg"></i>
          <span class="font-medium">Profile updated successfully.</span>
        </div>
        
        <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
          <button type="submit" class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-8 py-3 shadow-md hover:shadow-lg transition-all">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Security (Right Column) -->
  <div class="lg:col-span-4">
    <div id="security-section" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 sticky top-6">
      <div class="flex items-center justify-between border-b border-gray-100 pb-5 mb-6">
        <h3 class="font-semibold text-gray-900 text-lg">Security</h3>
      </div>

      <div class="space-y-4">
        <div class="bg-gray-50/80 rounded-xl p-5 border border-gray-100/80">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
               <i class="ti ti-lock text-lg"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-900">Password</p>
              <p class="text-xs text-gray-500 mt-0.5">Last changed <?= timeAgo($user['password_changed_at'] ?? $user['created_at']) ?></p>
            </div>
          </div>
          <button type="button" id="openPasswordModal" class="w-full rounded-xl border border-gray-200 bg-white text-sm font-medium px-4 py-2.5 text-gray-700 hover:bg-gray-50 hover:border-gray-300 hover:shadow-sm transition-all">
            Update Password
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Change password modal -->
<div id="passwordModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-4">
  <div class="bg-white rounded-xl max-w-sm w-full p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Update Password</h3>
      <button type="button" id="closePasswordModal" class="text-gray-400 hover:text-gray-600"><i class="ti ti-x"></i></button>
    </div>
    <form id="passwordForm" novalidate class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
        <input type="password" name="current_password"
               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="current_password"></p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
        <input type="password" name="new_password"
               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="new_password"></p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
        <input type="password" name="confirm_password"
               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand">
        <p class="field-error text-xs text-red-600 mt-1 hidden" data-field="confirm_password"></p>
      </div>
      <button type="submit" class="w-full rounded-full bg-brand hover:bg-brand-light text-white text-sm font-medium px-5 py-2.5">Update Password</button>
    </form>
  </div>
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