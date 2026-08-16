<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Business Manager') ?> — Business Management System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: { DEFAULT: '#14302A', light: '#1E4038', dark: '#0E241F' },
        },
      },
    },
  };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
</head>
<body class="bg-gray-50 text-gray-900 h-screen overflow-hidden">

<div class="h-screen flex flex-col md:flex-row">
  <?php require __DIR__ . '/sidebar.php'; ?>

  <main id="page-content" class="flex-1 px-4 sm:px-6 py-6 max-w-full overflow-y-auto flex flex-col">
