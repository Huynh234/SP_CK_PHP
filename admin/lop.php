<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $ma = trim($_POST['ma_lop']);
    $ten = trim($_POST['ten_lop']);
    $hp_id = (int)$_POST['hocphan_id'];
    $gv_id = $_POST['giangvien_id'] !== '' ? (int)$_POST['giangvien_id'] : null;
    $hoc_ky = trim($_POST['hoc_ky']);
    $si_min = (int)$_POST['si_so_nhom_toi_thieu'];
    $si_max = (int)$_POST['si_so_nhom_toi_da'];
    $han_nhom = $_POST['han_dang_ky_nhom'] !== '' ? $_POST['han_dang_ky_nhom'] : null;
    $han_detai = $_POST['han_dang_ky_detai'] !== '' ? $_POST['han_dang_ky_detai'] : null;

    if ($ma === '' || $ten === '' || !$hp_id) {
        set_flash('error', 'Vui lòng nhập đủ thông tin bắt buộc.');
        redirect('/admin/lop.php');
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO lop_hocphan (ma_lop, ten_lop, hocphan_id, giangvien_id, hoc_ky, si_so_nhom_toi_thieu, si_so_nhom_toi_da, han_dang_ky_nhom, han_dang_ky_detai)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$ma, $ten, $hp_id, $gv_id, $hoc_ky, $si_min ?: 2, $si_max ?: 5, $han_nhom, $han_detai]);
        set_flash('success', 'Đã tạo lớp học phần.');
    } catch (PDOException $e) {
        set_flash('error', 'Mã lớp đã tồn tại.');
    }
    redirect('/admin/lop.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $pdo->prepare('DELETE FROM lop_hocphan WHERE id=?')->execute([(int)$_POST['id']]);
    set_flash('success', 'Đã xoá lớp học phần.');
    redirect('/admin/lop.php');
}

$hocphans = $pdo->query('SELECT * FROM hocphan ORDER BY ma_hp')->fetchAll();
$giangviens = $pdo->query("SELECT * FROM users WHERE role='giangvien' ORDER BY ho_ten")->fetchAll();

$list = $pdo->query("
    SELECT l.*, h.ma_hp, h.ten_hp, u.ho_ten AS ten_gv,
        (SELECT COUNT(*) FROM lop_sinhvien ls WHERE ls.lop_id = l.id) AS so_sv
    FROM lop_hocphan l
    JOIN hocphan h ON h.id = l.hocphan_id
    LEFT JOIN users u ON u.id = l.giangvien_id
    ORDER BY l.created_at DESC
")->fetchAll();

$page_title = 'Lớp học phần';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <h1 class="text-xl font-bold text-slate-800">Lớp học phần</h1>
  <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">+ Tạo lớp học phần</button>
</div>

<div class="grid gap-4">
  <?php foreach ($list as $l): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5 flex flex-wrap items-center justify-between gap-3">
    <div>
      <div class="flex items-center gap-2">
        <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?= e($l['ma_lop']) ?></span>
        <span class="font-semibold text-slate-800"><?= e($l['ten_lop']) ?></span>
      </div>
      <div class="text-xs text-slate-500 mt-1">
        <?= e($l['ma_hp']) ?> — <?= e($l['ten_hp']) ?> · <?= e($l['hoc_ky'] ?: 'Chưa đặt học kỳ') ?> ·
        GV: <?= e($l['ten_gv'] ?: 'Chưa gán') ?> · <?= $l['so_sv'] ?> sinh viên
      </div>
      <div class="text-xs text-slate-400 mt-1">
        Hạn ĐK nhóm: <?= format_datetime($l['han_dang_ky_nhom']) ?> · Hạn ĐK đề tài: <?= format_datetime($l['han_dang_ky_detai']) ?>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= BASE_URL ?>/admin/lop_detail.php?id=<?= $l['id'] ?>" class="text-sm text-brand-600 hover:underline">Quản lý →</a>
      <form method="post" onsubmit="return confirm('Xoá lớp này?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $l['id'] ?>">
        <button class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$list): ?><div class="text-center text-slate-400 py-12">Chưa có lớp học phần nào.</div><?php endif; ?>
</div>

<!-- Modal tạo lớp -->
<div id="modalAdd" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
  <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <h2 class="font-bold text-slate-800 mb-4">Tạo lớp học phần mới</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Mã lớp *</label>
          <input name="ma_lop" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="CNW101.01">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Học kỳ</label>
          <input name="hoc_ky" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="HK2 2025-2026">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Tên lớp *</label>
        <input name="ten_lop" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Học phần *</label>
          <select name="hocphan_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="">-- chọn --</option>
            <?php foreach ($hocphans as $hp): ?>
              <option value="<?= $hp['id'] ?>"><?= e($hp['ma_hp']) ?> - <?= e($hp['ten_hp']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Giảng viên phụ trách</label>
          <select name="giangvien_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="">-- chưa gán --</option>
            <?php foreach ($giangviens as $gv): ?>
              <option value="<?= $gv['id'] ?>"><?= e($gv['ho_ten']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối thiểu / nhóm</label>
          <input name="si_so_nhom_toi_thieu" type="number" min="1" value="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối đa / nhóm</label>
          <input name="si_so_nhom_toi_da" type="number" min="1" value="5" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký nhóm</label>
          <input name="han_dang_ky_nhom" type="datetime-local" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký đề tài</label>
          <input name="han_dang_ky_detai" type="datetime-local" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
        <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white">Tạo lớp</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
