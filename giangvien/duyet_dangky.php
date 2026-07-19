<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('giangvien');
$gv_id = $_SESSION['user_id'];

$lop_id = (int)($_GET['lop_id'] ?? 0);
$lop = db_query_one('SELECT * FROM lop_hocphan WHERE id=? AND giangvien_id=?', [$lop_id, $gv_id]);
if (!$lop) { set_flash('error', 'Không tìm thấy lớp.'); redirect('/giangvien/dashboard.php'); }

// Xử lý duyệt / từ chối / yêu cầu điều chỉnh
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $dk_id = (int)$_POST['dangky_id'];
    $action = $_POST['action'];
    $phan_hoi = trim($_POST['phan_hoi'] ?? '');

    // đảm bảo đăng ký này thuộc 1 nhóm trong lớp của giảng viên này
    $dk = db_query_one("
        SELECT dk.*, d.so_nhom_toi_da FROM dangky_detai dk
        JOIN nhom n ON n.id = dk.nhom_id
        JOIN detai d ON d.id = dk.detai_id
        WHERE dk.id=? AND n.lop_id=?
    ", [$dk_id, $lop_id]);

    if ($dk) {
        if ($action === 'duyet') {
            // Đếm số nhóm đã duyệt CÙNG đề tài, TRONG CÙNG ĐỢT (so_nhom_toi_da tính theo từng đợt)
            $count = (int)db_value("SELECT COUNT(*) FROM dangky_detai WHERE detai_id=? AND dot_id=? AND trang_thai='da_duyet'", [$dk['detai_id'], $dk['dot_id']]);
            if ($count >= $dk['so_nhom_toi_da']) {
                set_flash('error', 'Đề tài đã đủ số nhóm đăng ký trong đợt này, không thể duyệt thêm.');
            } else {
                db_exec("UPDATE dangky_detai SET trang_thai='da_duyet', phan_hoi=? WHERE id=?", [$phan_hoi, $dk_id]);
                set_flash('success', 'Đã duyệt đăng ký đề tài.');
            }
        } elseif ($action === 'tu_choi') {
            db_exec("UPDATE dangky_detai SET trang_thai='tu_choi', phan_hoi=? WHERE id=?", [$phan_hoi, $dk_id]);
            set_flash('success', 'Đã từ chối. Nhóm sẽ cần chọn đề tài khác cho đợt này.');
        } elseif ($action === 'dieu_chinh') {
            db_exec("UPDATE dangky_detai SET trang_thai='yeu_cau_dieu_chinh', phan_hoi=? WHERE id=?", [$phan_hoi, $dk_id]);
            set_flash('success', 'Đã yêu cầu nhóm điều chỉnh đề tài.');
        }
    }
    redirect('/giangvien/duyet_dangky.php?lop_id=' . $lop_id);
}

$list = db_query("
    SELECT dk.*, n.ten_nhom, d.ten_detai, d.mo_ta, d.nguon, dd.ten_dot
    FROM dangky_detai dk
    JOIN nhom n ON n.id = dk.nhom_id
    JOIN detai d ON d.id = dk.detai_id
    JOIN dot_dangky dd ON dd.id = dk.dot_id
    WHERE n.lop_id = ?
    ORDER BY FIELD(dk.trang_thai,'cho_duyet','yeu_cau_dieu_chinh','da_duyet','tu_choi'), dk.created_at
", [$lop_id]);

$page_title = 'Duyệt đăng ký đề tài';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/giangvien/lop.php?id=<?= $lop_id ?>" class="text-sm text-brand-600 hover:underline">← <?= e($lop['ma_lop']) ?></a>
<h1 class="text-xl font-bold text-slate-800 mt-2 mb-6">Duyệt đăng ký đề tài</h1>

<div class="grid gap-4">
  <?php foreach ($list as $dk): ?>
  <?php $mau = ['cho_duyet'=>'bg-amber-50 text-amber-700','da_duyet'=>'bg-emerald-50 text-emerald-700','tu_choi'=>'bg-rose-50 text-rose-700','yeu_cau_dieu_chinh'=>'bg-sky-50 text-sky-700'][$dk['trang_thai']]; ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="font-semibold text-slate-800">
          <?= e($dk['ten_nhom']) ?> → <?= e($dk['ten_detai']) ?>
          <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full ml-1"><?= e($dk['ten_dot']) ?></span>
          <?php if ($dk['nguon']==='sinhvien'): ?><span class="text-xs bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full ml-1">SV tự đề xuất</span><?php endif; ?>
          <?php if ($dk['la_random']): ?><span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full ml-1">random</span><?php endif; ?>
        </div>
        <p class="text-sm text-slate-500 mt-1"><?= nl2br(e($dk['mo_ta'])) ?></p>
        <?php if ($dk['phan_hoi']): ?>
          <p class="text-xs text-slate-400 mt-2 italic">Phản hồi trước: "<?= e($dk['phan_hoi']) ?>"</p>
        <?php endif; ?>
      </div>
      <span class="text-xs px-2 py-0.5 rounded-full <?= $mau ?> whitespace-nowrap"><?= e(str_replace('_',' ',$dk['trang_thai'])) ?></span>
    </div>

    <?php if ($dk['trang_thai'] === 'cho_duyet' || $dk['trang_thai'] === 'yeu_cau_dieu_chinh'): ?>
    <form method="post" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
      <?= csrf_field() ?>
      <input type="hidden" name="dangky_id" value="<?= $dk['id'] ?>">
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-slate-600 mb-1">Phản hồi (tuỳ chọn)</label>
        <input name="phan_hoi" class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
      </div>
      <button name="action" value="duyet" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg">✓ Duyệt</button>
      <button name="action" value="dieu_chinh" class="text-xs bg-sky-500 hover:bg-sky-600 text-white px-3 py-2 rounded-lg">✎ Yêu cầu điều chỉnh</button>
      <button name="action" value="tu_choi" class="text-xs bg-rose-500 hover:bg-rose-600 text-white px-3 py-2 rounded-lg">✕ Từ chối</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$list): ?><div class="text-center text-slate-400 py-12">Chưa có đăng ký đề tài nào trong lớp này.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
