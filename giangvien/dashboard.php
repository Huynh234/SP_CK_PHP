<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('giangvien');

$gv_id = $_SESSION['user_id'];
$lops = db_query("
    SELECT l.*, h.ma_hp, h.ten_hp,
        (SELECT COUNT(*) FROM lop_sinhvien ls WHERE ls.lop_id=l.id) AS so_sv,
        (SELECT COUNT(*) FROM nhom n WHERE n.lop_id=l.id) AS so_nhom
    FROM lop_hocphan l
    JOIN hocphan h ON h.id = l.hocphan_id
    WHERE l.giangvien_id = ?
    ORDER BY l.created_at DESC
", [$gv_id]);

$page_title = 'Lớp của tôi';
include __DIR__ . '/../includes/header.php';
?>
<h1 class="text-xl font-bold text-slate-800 mb-1">Chào, <?= e($_SESSION['ho_ten']) ?> 👋</h1>
<p class="text-sm text-slate-500 mb-6">Các lớp học phần bạn đang phụ trách.</p>

<div class="grid md:grid-cols-2 gap-4">
  <?php foreach ($lops as $l): ?>
  <a href="<?= BASE_URL ?>/giangvien/lop.php?id=<?= $l['id'] ?>" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="flex items-center gap-2 mb-1">
      <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?= e($l['ma_lop']) ?></span>
      <span class="font-semibold text-slate-800"><?= e($l['ten_lop']) ?></span>
    </div>
    <div class="text-xs text-slate-500"><?= e($l['ma_hp']) ?> — <?= e($l['ten_hp']) ?> · <?= e($l['hoc_ky']) ?></div>
    <div class="flex gap-4 text-xs text-slate-500 mt-3">
      <span>👥 <?= $l['so_sv'] ?> sinh viên</span>
      <span>🧩 <?= $l['so_nhom'] ?> nhóm</span>
    </div>
    <div class="text-xs text-slate-400 mt-2">
      Hạn ĐK nhóm: <?= format_datetime($l['han_dang_ky_nhom']) ?>
    </div>
  </a>
  <?php endforeach; ?>
  <?php if (!$lops): ?><div class="text-slate-400 text-sm">Bạn chưa được phân công lớp nào. Liên hệ quản trị viên.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
