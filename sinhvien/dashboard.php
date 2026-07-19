<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$lops = db_query("
    SELECT l.*, h.ma_hp, h.ten_hp, u.ho_ten AS ten_gv,
      (SELECT n.id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
       WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=l.id LIMIT 1) AS nhom_id
    FROM lop_hocphan l
    JOIN hocphan h ON h.id = l.hocphan_id
    LEFT JOIN users u ON u.id = l.giangvien_id
    JOIN lop_sinhvien ls ON ls.lop_id = l.id AND ls.sinhvien_id = ?
    ORDER BY l.created_at DESC
", [$sv_id, $sv_id]);

$page_title = 'Lớp của tôi';
include __DIR__ . '/../includes/header.php';
?>
<h1 class="text-xl font-bold text-slate-800 mb-1">Chào, <?= e($_SESSION['ho_ten']) ?> 👋</h1>
<p class="text-sm text-slate-500 mb-6">Các lớp học phần bạn đang tham gia.</p>

<div class="grid md:grid-cols-2 gap-4">
  <?php foreach ($lops as $l): ?>
  <a href="<?= BASE_URL ?>/sinhvien/lop.php?id=<?= $l['id'] ?>" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">
    <div class="flex items-center gap-2 mb-1">
      <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?= e($l['ma_lop']) ?></span>
      <span class="font-semibold text-slate-800"><?= e($l['ten_lop']) ?></span>
    </div>
    <div class="text-xs text-slate-500"><?= e($l['ma_hp']) ?> — <?= e($l['ten_hp']) ?> · GV: <?= e($l['ten_gv'] ?: 'chưa gán') ?></div>
    <div class="mt-3">
      <?php if ($l['nhom_id']): ?>
        <span class="text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">✓ Đã có nhóm</span>
      <?php else: ?>
        <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">Chưa có nhóm</span>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
  <?php if (!$lops): ?><div class="text-slate-400 text-sm">Bạn chưa được thêm vào lớp học phần nào.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
