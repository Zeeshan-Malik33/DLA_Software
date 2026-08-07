<?php
require '../config/database.php';
require '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? skip straight to the dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: ../dashboard/index.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    } elseif ($action === 'forgot_password') {
        $email = trim($_POST['forgot_email'] ?? '');
        $name = trim($_POST['forgot_name'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        if ($email === '' || $name === '' || $newPassword === '') {
            $error = 'Please fill all fields to reset password.';
        } else {
            $stmt = $pdo->prepare('SELECT user_id, name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['name'] === $name) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $user['user_id']]);
                $success = 'Password reset successfully. You can now log in with your new password.';
            } else {
                $error = 'No user found with that exact email and username combination (remember, capitalization matters!).';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Business Management System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: {
            DEFAULT: '#14302A',
            light: '#1E4038',
            dark: '#0E241F',
          },
        },
      },
    },
  };
</script>
</head>
<body class="min-h-screen bg-white">

  <div class="min-h-screen flex flex-col md:flex-row">

    <!-- Brand panel: full-width bar on mobile, side panel on desktop -->
    <div class="bg-brand h-28 md:h-auto md:w-1/2 flex items-center justify-center">
      <span class="text-white text-2xl md:text-3xl font-semibold tracking-tight">Business Manager</span>
    </div>

    <!-- Form panel -->
    <div class="flex-1 flex items-center justify-center px-6 py-10 md:py-0">
      <div class="w-full max-w-sm">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Welcome Back</h1>
        <p class="text-sm text-gray-500 mt-1 mb-6">Sign in to your account</p>

        <?php if ($error): ?>
          <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2">
            <?= h($error) ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2">
            <?= h($success) ?>
          </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="login.php" class="space-y-4 <?= isset($_POST['action']) && $_POST['action'] === 'forgot_password' && !$success ? 'hidden' : '' ?>" id="loginForm">
          <input type="hidden" name="action" value="login">
          <div>
            <input
              type="email"
              name="email"
              placeholder="Enter your email"
              required
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            >
          </div>
          <div>
            <input
              type="password"
              name="password"
              placeholder="Enter your password"
              required
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            >
          </div>

          <div class="text-right">
            <button type="button" onclick="document.getElementById('loginForm').classList.add('hidden'); document.getElementById('forgotForm').classList.remove('hidden');" class="text-xs text-gray-500 hover:text-brand">Forgot password?</button>
          </div>

          <button
            type="submit"
            class="w-full bg-brand hover:bg-brand-light text-white text-sm font-medium rounded-lg py-2.5 transition-colors"
          >
            Log in
          </button>
        </form>

        <!-- Forgot Password Form -->
        <form method="POST" action="login.php" class="space-y-4 <?= isset($_POST['action']) && $_POST['action'] === 'forgot_password' && !$success ? '' : 'hidden' ?>" id="forgotForm">
          <input type="hidden" name="action" value="forgot_password">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Registered Gmail</label>
            <input
              type="email"
              name="forgot_email"
              placeholder="Enter your registered gmail"
              required
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            >
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Username</label>
            <input
              type="text"
              name="forgot_name"
              placeholder="Enter your username"
              required
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            >
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">New Password</label>
            <input
              type="password"
              name="new_password"
              placeholder="Enter new password"
              required
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            >
          </div>

          <div class="flex items-center justify-between">
            <button type="button" onclick="document.getElementById('forgotForm').classList.add('hidden'); document.getElementById('loginForm').classList.remove('hidden');" class="text-xs text-gray-500 hover:text-brand">&larr; Back to login</button>
            <button
              type="submit"
              class="bg-brand hover:bg-brand-light text-white text-sm font-medium rounded-lg px-6 py-2.5 transition-colors"
            >
              Change Password
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>

</body>
</html>
