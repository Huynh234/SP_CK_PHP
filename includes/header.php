<?php
/**
 * $page_title: tiêu đề trang (tuỳ chọn)
 * File này cần được include SAU khi đã require config.php, database.php, auth.php, functions.php
 */
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?><?= SITE_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: {
            50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',
            500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81'
          }
        }
      }
    }
  }
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Be Vietnam Pro',sans-serif;}</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

<?php if ($user): ?>
<header class="bg-brand-700 text-white sticky top-0 z-30 shadow">
  <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">
    <div class="flex items-center gap-6">
      <a href="<?= BASE_URL ?>/<?= $user['role'] ?>/dashboard.php" class="font-bold text-lg tracking-tight">
        📚 QL Nhóm & Đề tài
      </a>
      <nav class="hidden md:flex items-center gap-1 text-sm">
        <?php if ($user['role'] === 'admin'): ?>
          <a href="<?= BASE_URL ?>/admin/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Tổng quan</a>
          <a href="<?= BASE_URL ?>/admin/accounts.php" class="px-3 py-2 rounded hover:bg-brand-600">Tài khoản</a>
          <a href="<?= BASE_URL ?>/admin/hocphan.php" class="px-3 py-2 rounded hover:bg-brand-600">Học phần</a>
          <a href="<?= BASE_URL ?>/admin/lop.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp học phần</a>
        <?php elseif ($user['role'] === 'giangvien'): ?>
          <a href="<?= BASE_URL ?>/giangvien/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp của tôi</a>
          <a href="<?= BASE_URL ?>/giangvien/detai.php" class="px-3 py-2 rounded hover:bg-brand-600">Ngân hàng đề tài</a>
        <?php elseif ($user['role'] === 'sinhvien'): ?>
          <a href="<?= BASE_URL ?>/sinhvien/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp của tôi</a>
          <a href="<?= BASE_URL ?>/sinhvien/loi_moi.php" class="px-3 py-2 rounded hover:bg-brand-600">Lời mời nhóm</a>
        <?php endif; ?>
      </nav>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <span class="hidden sm:inline text-brand-100"><?= e($user['ho_ten']) ?> · <span class="uppercase text-xs bg-brand-800 px-2 py-0.5 rounded"><?= e($user['role']) ?></span></span>
      <a href="<?= BASE_URL ?>/logout.php" class="bg-brand-800 hover:bg-brand-900 px-3 py-1.5 rounded transition">Đăng xuất</a>
    </div>
  </div>
  <!-- Nav mobile -->
  <nav class="md:hidden flex overflow-x-auto gap-1 px-4 pb-2 text-sm">
    <?php if ($user['role'] === 'admin'): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Tổng quan</a>
      <a href="<?= BASE_URL ?>/admin/accounts.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Tài khoản</a>
      <a href="<?= BASE_URL ?>/admin/hocphan.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Học phần</a>
      <a href="<?= BASE_URL ?>/admin/lop.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Lớp</a>
    <?php elseif ($user['role'] === 'giangvien'): ?>
      <a href="<?= BASE_URL ?>/giangvien/dashboard.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Lớp của tôi</a>
      <a href="<?= BASE_URL ?>/giangvien/detai.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Ngân hàng đề tài</a>
    <?php elseif ($user['role'] === 'sinhvien'): ?>
      <a href="<?= BASE_URL ?>/sinhvien/dashboard.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Lớp của tôi</a>
      <a href="<?= BASE_URL ?>/sinhvien/loi_moi.php" class="px-3 py-1.5 rounded bg-brand-800 whitespace-nowrap">Lời mời</a>
    <?php endif; ?>
  </nav>
</header>
<?php endif; ?>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
  <?php $flash = get_flash(); if ($flash): ?>
    <div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium
      <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
      <?= e($flash['message']) ?>
    </div>
  <?php endif; ?>
