<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
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
include '../includes/header.php';
?>
<h1 class="text-xl font-bold text-slate-800 mb-1">Chào, <?php echo $_SESSION['ho_ten'] ?> 👋</h1>
<p class="text-sm text-slate-500 mb-6">Các lớp học phần bạn đang phụ trách.</p>

<div class="grid md:grid-cols-2 gap-4">
  <?php
  foreach ($lops as $l) {
    echo '<a href="' . BASE_URL . '/giangvien/lop.php?id=' . $l['id'] . '" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">';
    echo '<div class="flex items-center gap-2 mb-1">';
    echo '<span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded">' . $l['ma_lop'] . '</span>';
    echo '<span class="font-semibold text-slate-800">' . $l['ten_lop'] . '</span>';
    echo '</div>';
    echo '<div class="text-xs text-slate-500">' . $l['ma_hp'] . ' — ' . $l['ten_hp'] . ' · ' . $l['hoc_ky'] . '</div>';
    echo '<div class="flex gap-4 text-xs text-slate-500 mt-3">';
    echo '<span>👥 ' . $l['so_sv'] . ' sinh viên</span>';
    echo '<span>🧩 ' . $l['so_nhom'] . ' nhóm</span>';
    echo '</div>';
    echo '<div class="text-xs text-slate-400 mt-2">Hạn ĐK nhóm: ' . format_datetime($l['han_dang_ky_nhom']) . '</div>';
    echo '</a>';
  }
  ?>

  <?php if (!$lops){echo '<div class="text-slate-400 text-sm">Bạn chưa được phân công lớp nào. Liên hệ quản trị viên.</div>'; } ?>
</div>

</main>
<footer class="text-center text-xs text-slate-400 py-4">
  sản phẩm cuối kỳ Công nghệ Web
</footer>
</body>
</html>