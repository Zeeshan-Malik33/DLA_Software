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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        <form method="POST" action="login.php" class="space-y-4">
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
            <a href="#" class="text-xs text-gray-500 hover:text-brand">Forgot password?</a>
          </div>

          <button
            type="submit"
            class="w-full bg-brand hover:bg-brand-light text-white text-sm font-medium rounded-lg py-2.5 transition-colors"
          >
            Log in
          </button>
        </form>
      </div>
    </div>

  </div>

</body>
</html>
