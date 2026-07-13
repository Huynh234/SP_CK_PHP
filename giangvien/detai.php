<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('giangvien');
$gv_id = $_SESSION['user_id'];

// Thêm đề tài mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $ten = trim($_POST['ten_detai']);
    $mota = trim($_POST['mo_ta']);
    $sn = max(1, (int)$_POST['so_nhom_toi_da']);
    $lop_ids = $_POST['lop_ids'] ?? [];

    if ($ten === '') { set_flash('error', 'Vui lòng nhập tên đề tài.'); redirect('/giangvien/detai.php'); }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO detai (giangvien_id, ten_detai, mo_ta, so_nhom_toi_da, nguon, trang_thai) VALUES (?,?,?,?,\'giangvien\',\'mo\')')
        ->execute([$gv_id, $ten, $mota, $sn]);
    $detai_id = $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO detai_lop (detai_id, lop_id) VALUES (?,?)');
    foreach ($lop_ids as $lid) {
        // chỉ gán cho lớp thuộc chính giảng viên này
        $chk = $pdo->prepare('SELECT id FROM lop_hocphan WHERE id=? AND giangvien_id=?');
        $chk->execute([(int)$lid, $gv_id]);
        if ($chk->fetch()) $ins->execute([$detai_id, (int)$lid]);
    }
    $pdo->commit();

    set_flash('success', 'Đã tạo đề tài.');
    redirect('/giangvien/detai.php');
}

// Đóng / mở đề tài
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    csrf_check();
    $id = (int)$_POST['id'];
    $chk = $pdo->prepare('SELECT id FROM detai WHERE id=? AND giangvien_id=?');
    $chk->execute([$id, $gv_id]);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE detai SET trang_thai = IF(trang_thai='mo','dong','mo') WHERE id=?")->execute([$id]);
        set_flash('success', 'Đã cập nhật trạng thái đề tài.');
    }
    redirect('/giangvien/detai.php');
}

// Xoá đề tài
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM detai WHERE id=? AND giangvien_id=?')->execute([$id, $gv_id]);
    set_flash('success', 'Đã xoá đề tài.');
    redirect('/giangvien/detai.php');
}

$lops = $pdo->prepare('SELECT * FROM lop_hocphan WHERE giangvien_id=? ORDER BY ma_lop');
$lops->execute([$gv_id]);
$lops = $lops->fetchAll();

$detais = $pdo->prepare("
    SELECT d.*,
      (SELECT COUNT(*) FROM dangky_detai dk WHERE dk.detai_id=d.id AND dk.trang_thai='da_duyet') AS so_dang_ky,
      GROUP_CONCAT(DISTINCT l.ma_lop SEPARATOR ', ') AS ds_lop
    FROM detai d
    LEFT JOIN detai_lop dl ON dl.detai_id = d.id
    LEFT JOIN lop_hocphan l ON l.id = dl.lop_id
    WHERE d.giangvien_id = ? AND d.nguon='giangvien'
    GROUP BY d.id ORDER BY d.created_at DESC
");
$detais->execute([$gv_id]);
$detais = $detais->fetchAll();

$page_title = 'Ngân hàng đề tài';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <h1 class="text-xl font-bold text-slate-800">Ngân hàng đề tài</h1>
  <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">+ Thêm đề tài</button>
</div>

<div class="grid gap-4">
  <?php foreach ($detais as $d): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="flex items-center gap-2">
          <span class="font-semibold text-slate-800"><?= e($d['ten_detai']) ?></span>
          <?php if ($d['trang_thai']==='mo'): ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Đang mở</span>
          <?php else: ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Đã đóng</span>
          <?php endif; ?>
        </div>
        <p class="text-sm text-slate-500 mt-1"><?= nl2br(e($d['mo_ta'])) ?></p>
        <div class="text-xs text-slate-400 mt-2">
          Đã đăng ký: <b><?= $d['so_dang_ky'] ?>/<?= $d['so_nhom_toi_da'] ?></b> nhóm ·
          Dùng cho lớp: <?= e($d['ds_lop'] ?: 'chưa gán lớp nào') ?>
        </div>
      </div>
      <div class="flex gap-2 shrink-0">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $d['id'] ?>">
          <button class="text-xs text-slate-500 hover:text-brand-600"><?= $d['trang_thai']==='mo'?'Đóng':'Mở lại' ?></button>
        </form>
        <form method="post" onsubmit="return confirm('Xoá đề tài này?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
          <button class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$detais): ?><div class="text-center text-slate-400 py-12">Chưa có đề tài nào. Bấm "+ Thêm đề tài" để tạo.</div><?php endif; ?>
</div>

<!-- Modal thêm đề tài -->
<div id="modalAdd" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
  <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <h2 class="font-bold text-slate-800 mb-4">Thêm đề tài mới</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Tên đề tài *</label>
        <input name="ten_detai" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Mô tả</label>
        <textarea name="mo_ta" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Số nhóm tối đa được đăng ký</label>
        <input name="so_nhom_toi_da" type="number" min="1" value="1" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Gán cho lớp (có thể chọn nhiều lớp dùng chung đề tài)</label>
        <div class="border border-slate-300 rounded-lg p-2 max-h-32 overflow-y-auto text-sm space-y-1">
          <?php foreach ($lops as $l): ?>
            <label class="flex items-center gap-2">
              <input type="checkbox" name="lop_ids[]" value="<?= $l['id'] ?>">
              <?= e($l['ma_lop']) ?> - <?= e($l['ten_lop']) ?>
            </label>
          <?php endforeach; ?>
          <?php if (!$lops): ?><p class="text-slate-400 text-xs">Bạn chưa có lớp nào được phân công.</p><?php endif; ?>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
        <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white">Tạo đề tài</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
