<?php
require_once './config/config.php';
require_once './config/database.php';
require_once './config/auth.php';
require_once './config/functions.php';
require_login();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo SITE_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81'
            }
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Be Vietnam Pro', sans-serif;
    }
  </style>
</head>

<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
    <?php $flash = get_flash();
    if ($flash) {
      echo '<div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium
      ' . ($flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200') . '">';
      echo $flash['message'] . '</div>';
    }
    ?>
<div class="text-center py-24">
  <div class="text-5xl mb-4">⛔</div>
  <h1 class="text-xl font-bold text-slate-800">Bạn không có quyền truy cập trang này</h1>
  <a href="<?php echo BASE_URL ?>/<?php echo $_SESSION['role'] ?>/dashboard.php" class="inline-block mt-4 text-brand-600 hover:underline">← Quay lại trang chính</a>
</div>
</main>
<footer class="text-center text-xs text-slate-400 py-4">
  sản phẩm cuối kỳ Công nghệ Web
</footer>
</body>
</html>
