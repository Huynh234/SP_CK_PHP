<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$soGV  = db_value("SELECT COUNT(*) FROM users WHERE role='giangvien'");
$soSV  = db_value("SELECT COUNT(*) FROM users WHERE role='sinhvien'");
$soLop = db_value("SELECT COUNT(*) FROM lop_hocphan");
$soHP  = db_value("SELECT COUNT(*) FROM hocphan");

$page_title = 'Tổng quan';
include __DIR__ . '/../includes/header.php';
?>
<h1 class="text-xl font-bold text-slate-800 mb-1">Chào, <?= e($_SESSION['ho_ten']) ?> 👋</h1>
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
  <a href="<?= BASE_URL ?>/admin/accounts.php" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="text-2xl mb-2">👤</div>
    <div class="font-semibold text-slate-800">Quản lý tài khoản</div>
    <div class="text-xs text-slate-500 mt-1">Thêm tài khoản đơn lẻ hoặc import từ file CSV</div>
  </a>
  <a href="<?= BASE_URL ?>/admin/hocphan.php" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="text-2xl mb-2">📖</div>
    <div class="font-semibold text-slate-800">Quản lý học phần</div>
    <div class="text-xs text-slate-500 mt-1">Thêm mã học phần, tên học phần</div>
  </a>
  <a href="<?= BASE_URL ?>/admin/lop.php" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="text-2xl mb-2">🏫</div>
    <div class="font-semibold text-slate-800">Lớp học phần</div>
    <div class="text-xs text-slate-500 mt-1">Tạo lớp, gán giảng viên, thêm sinh viên vào lớp</div>
  </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
