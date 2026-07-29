<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_role('admin');

$soGV  = db_value("SELECT COUNT(*) FROM users WHERE role='giangvien'");
$soSV  = db_value("SELECT COUNT(*) FROM users WHERE role='sinhvien'");
$soLop = db_value("SELECT COUNT(*) FROM lop_hocphan");
$soHP  = db_value("SELECT COUNT(*) FROM hocphan");

$user = current_user();
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
              50: '#eef2ff',
              100: '#e0e7ff',
              200: '#c7d2fe',
              300: '#a5b4fc',
              400: '#818cf8',
              500: '#6366f1',
              600: '#4f46e5',
              700: '#4338ca',
              800: '#3730a3',
              900: '#312e81'
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

  <?php if ($user) {
    echo '<header class="bg-brand-700 text-white sticky top-0 z-30 shadow">';
    echo '<div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">';
    echo '<div class="flex items-center gap-6">';
    echo '<a href="' . BASE_URL . '/' . $user['role'] . '/dashboard.php" class="font-bold text-lg tracking-tight">';
    echo '📚 QL Nhóm & Đề tài';
    echo '</a>';
    echo '<nav class="hidden md:flex items-center gap-1 text-sm">';
    echo '<a href="' . BASE_URL . '/admin/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Tổng quan</a>';
    echo '<a href="' . BASE_URL . '/admin/accounts.php" class="px-3 py-2 rounded hover:bg-brand-600">Tài khoản</a>';
    echo '<a href="' . BASE_URL . '/admin/hocphan.php" class="px-3 py-2 rounded hover:bg-brand-600">Học phần</a>';
    echo '<a href="' . BASE_URL . '/admin/lop.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp học phần</a>';
    echo '</nav>';
    echo '</div>';
    echo '<div class="flex items-center gap-3 text-sm">';
    echo '<span class="hidden sm:inline text-brand-100">' . $user['ho_ten'] . ' · <span class="uppercase text-xs bg-brand-800 px-2 py-0.5 rounded">' . $user['role'] . '</span></span>';
    echo '<a href="' . BASE_URL . '/logout.php" class="bg-brand-800 hover:bg-brand-900 px-3 py-1.5 rounded transition">Đăng xuất</a>';
    echo '</div>';
    echo '</div>';
    echo '</header>';
  } ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
    <?php $flash = get_flash();
    if ($flash) {
      echo '<div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium
      ' . ($flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200') . '">';
      echo $flash['message'] . '</div>';
    }
    ?>
<h1 class="text-xl font-bold text-slate-800 mb-1">Chào, <?php echo $_SESSION['ho_ten']; ?> 👋</h1>
<p class="text-sm text-slate-500 mb-6">Bảng điều khiển quản trị hệ thống.</p>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-2xl font-bold text-brand-700"><?= $soGV ?></div>
    <div class="text-xs text-slate-500 mt-1">Giảng viên</div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-2xl font-bold text-brand-700"><?= $soSV ?></div>
    <div class="text-xs text-slate-500 mt-1">Sinh viên</div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-2xl font-bold text-brand-700"><?= $soHP ?></div>
    <div class="text-xs text-slate-500 mt-1">Học phần</div>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-2xl font-bold text-brand-700"><?= $soLop ?></div>
    <div class="text-xs text-slate-500 mt-1">Lớp học phần</div>
  </div>
</div>

<div class="grid md:grid-cols-3 gap-4">
  <a href="<?php echo BASE_URL; ?>/admin/accounts.php" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="text-2xl mb-2">👤</div>
    <div class="font-semibold text-slate-800">Quản lý tài khoản</div>
    <div class="text-xs text-slate-500 mt-1">Thêm tài khoản đơn lẻ hoặc import từ file CSV</div>
  </a>
  <a href="<?php echo BASE_URL; ?>/admin/hocphan.php" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="text-2xl mb-2">📖</div>
    <div class="font-semibold text-slate-800">Quản lý học phần</div>
    <div class="text-xs text-slate-500 mt-1">Thêm mã học phần, tên học phần</div>
  </a>
  <a href="<?php echo BASE_URL; ?>/admin/lop.php" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="text-2xl mb-2">🏫</div>
    <div class="font-semibold text-slate-800">Lớp học phần</div>
    <div class="text-xs text-slate-500 mt-1">Tạo lớp, gán giảng viên, thêm sinh viên vào lớp</div>
  </a>
</div>

</main>
<footer class="text-center text-xs text-slate-400 py-4">
  sản phẩm cuối kỳ Công nghệ Web
</footer>
</body>
</html>
