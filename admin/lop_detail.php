<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$lop = db_query_one('SELECT l.*, h.ma_hp, h.ten_hp FROM lop_hocphan l JOIN hocphan h ON h.id=l.hocphan_id WHERE l.id=?', [$id]);
if (!$lop) { set_flash('error', 'Không tìm thấy lớp học phần.'); redirect('/admin/lop.php'); }

// Cập nhật thông tin & điều kiện lớp (sĩ số nhóm, hạn đăng ký nhóm...)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_settings') {
    csrf_check();
    $ten_lop = trim($_POST['ten_lop']);
    $hoc_ky = trim($_POST['hoc_ky']);
    $si_min = (int)$_POST['si_so_nhom_toi_thieu'];
    $si_max = (int)$_POST['si_so_nhom_toi_da'];
    $han_nhom = $_POST['han_dang_ky_nhom'] !== '' ? $_POST['han_dang_ky_nhom'] : null;

    if ($ten_lop === '' || $si_min < 1 || $si_max < $si_min) {
        set_flash('error', 'Vui lòng kiểm tra lại tên lớp và sĩ số nhóm (tối đa phải ≥ tối thiểu).');
        redirect('/admin/lop_detail.php?id=' . $id);
    }

    db_exec('UPDATE lop_hocphan SET ten_lop=?, hoc_ky=?, si_so_nhom_toi_thieu=?, si_so_nhom_toi_da=?, han_dang_ky_nhom=? WHERE id=?',
        [$ten_lop, $hoc_ky, $si_min, $si_max, $han_nhom, $id]);

    set_flash('success', 'Đã cập nhật thông tin lớp học phần.');
    redirect('/admin/lop_detail.php?id=' . $id);
}

// Gán / đổi giảng viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_gv') {
    csrf_check();
    $gv_id = $_POST['giangvien_id'] !== '' ? (int)$_POST['giangvien_id'] : null;
    db_exec('UPDATE lop_hocphan SET giangvien_id=? WHERE id=?', [$gv_id, $id]);
    set_flash('success', 'Đã cập nhật giảng viên phụ trách.');
    redirect('/admin/lop_detail.php?id=' . $id);
}

// Thêm 1 sinh viên vào lớp (chọn từ danh sách có sẵn)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_one') {
    csrf_check();
    $sv_id = (int)$_POST['sinhvien_id'];
    if ($sv_id) {
        try {
            db_exec('INSERT INTO lop_sinhvien (lop_id, sinhvien_id) VALUES (?,?)', [$id, $sv_id]);
            set_flash('success', 'Đã thêm sinh viên vào lớp.');
        } catch (mysqli_sql_exception $e) {
            set_flash('error', 'Sinh viên đã có trong lớp.');
        }
    }
    redirect('/admin/lop_detail.php?id=' . $id);
}

// Thêm nhiều sinh viên theo danh sách mã số / username (mỗi dòng 1 mã)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_list') {
    csrf_check();
    $lines = preg_split('/\r\n|\r|\n/', trim($_POST['ds_ma'] ?? ''));
    $ok = 0; $loi = [];
    foreach ($lines as $ma) {
        $ma = trim($ma);
        if ($ma === '') continue;
        $row = db_query_one("SELECT id FROM users WHERE (username=? OR mssv_mgv=?) AND role='sinhvien'", [$ma, $ma]);
        if (!$row) { $loi[] = $ma; continue; }
        try {
            db_exec('INSERT INTO lop_sinhvien (lop_id, sinhvien_id) VALUES (?,?)', [$id, $row['id']]);
            $ok++;
        } catch (mysqli_sql_exception $e) { /* đã tồn tại trong lớp, bỏ qua */ }
    }
    set_flash($loi ? 'error' : 'success', "Đã thêm {$ok} sinh viên." . ($loi ? ' Không tìm thấy: ' . implode(', ', $loi) : ''));
    redirect('/admin/lop_detail.php?id=' . $id);
}

// Xoá sinh viên khỏi lớp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_sv') {
    csrf_check();
    db_exec('DELETE FROM lop_sinhvien WHERE lop_id=? AND sinhvien_id=?', [$id, (int)$_POST['sinhvien_id']]);
    set_flash('success', 'Đã xoá sinh viên khỏi lớp.');
    redirect('/admin/lop_detail.php?id=' . $id);
}

$giangviens = db_query("SELECT * FROM users WHERE role='giangvien' ORDER BY ho_ten");

$svInLop = db_query("
    SELECT u.* FROM lop_sinhvien ls JOIN users u ON u.id = ls.sinhvien_id
    WHERE ls.lop_id = ? ORDER BY u.ho_ten
", [$id]);

$svIds = array_column($svInLop, 'id');
if ($svIds) {
    $placeholders = implode(',', array_fill(0, count($svIds), '?'));
    $svAvailable = db_query("SELECT * FROM users WHERE role='sinhvien' AND id NOT IN ($placeholders) ORDER BY ho_ten", $svIds);
} else {
    $svAvailable = db_query("SELECT * FROM users WHERE role='sinhvien' ORDER BY ho_ten");
}

$page_title = 'Chi tiết lớp';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/admin/lop.php" class="text-sm text-brand-600 hover:underline">← Danh sách lớp</a>
<div class="flex items-center gap-2 mt-2 mb-6">
  <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?= e($lop['ma_lop']) ?></span>
  <h1 class="text-xl font-bold text-slate-800"><?= e($lop['ten_lop']) ?></h1>
</div>

<div class="grid md:grid-cols-3 gap-6">
  <div class="md:col-span-2 space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Danh sách sinh viên (<?= count($svInLop) ?>)</h2>
      <table class="w-full text-sm">
        <thead class="text-slate-500 text-xs uppercase"><tr><th class="text-left py-1">Họ tên</th><th class="text-left py-1">Mã SV</th><th></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($svInLop as $sv): ?>
          <tr>
            <td class="py-1.5"><?= e($sv['ho_ten']) ?></td>
            <td class="py-1.5 text-slate-500"><?= e($sv['mssv_mgv']) ?></td>
            <td class="py-1.5 text-right">
              <form method="post" onsubmit="return confirm('Xoá sinh viên khỏi lớp?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_sv">
                <input type="hidden" name="sinhvien_id" value="<?= $sv['id'] ?>">
                <button class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$svInLop): ?><tr><td colspan="3" class="text-center text-slate-400 py-6">Chưa có sinh viên nào trong lớp.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Thông tin & điều kiện lớp</h2>
      <form method="post" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_settings">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tên lớp *</label>
          <input name="ten_lop" required value="<?= e($lop['ten_lop']) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Học kỳ</label>
          <input name="hoc_ky" value="<?= e($lop['hoc_ky']) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối thiểu/nhóm</label>
            <input name="si_so_nhom_toi_thieu" type="number" min="1" value="<?= (int)$lop['si_so_nhom_toi_thieu'] ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối đa/nhóm</label>
            <input name="si_so_nhom_toi_da" type="number" min="1" value="<?= (int)$lop['si_so_nhom_toi_da'] ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký nhóm</label>
          <input name="han_dang_ky_nhom" type="datetime-local"
            value="<?= $lop['han_dang_ky_nhom'] ? date('Y-m-d\TH:i', strtotime($lop['han_dang_ky_nhom'])) : '' ?>"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm px-3 py-2 rounded-lg">Lưu thay đổi</button>
      </form>
      <p class="text-xs text-slate-400 mt-2">Đợt đăng ký đề tài (giữa kỳ/cuối kỳ) do giảng viên phụ trách quản lý riêng tại trang chi tiết lớp bên giao diện Giảng viên.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Giảng viên phụ trách</h2>
      <form method="post" class="flex gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_gv">
        <select name="giangvien_id" class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm">
          <option value="">-- chưa gán --</option>
          <?php foreach ($giangviens as $gv): ?>
            <option value="<?= $gv['id'] ?>" <?= $lop['giangvien_id']==$gv['id']?'selected':'' ?>><?= e($gv['ho_ten']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-3 py-2 rounded-lg">Lưu</button>
      </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Thêm 1 sinh viên</h2>
      <form method="post" class="flex gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_one">
        <select name="sinhvien_id" class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm">
          <option value="">-- chọn sinh viên --</option>
          <?php foreach ($svAvailable as $sv): ?>
            <option value="<?= $sv['id'] ?>"><?= e($sv['ho_ten']) ?> (<?= e($sv['mssv_mgv']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-3 py-2 rounded-lg">Thêm</button>
      </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Thêm theo danh sách (mã SV/username)</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_list">
        <textarea name="ds_ma" rows="5" placeholder="Mỗi dòng 1 mã sinh viên hoặc username&#10;SV004&#10;SV005" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
        <button class="w-full mt-2 bg-brand-600 hover:bg-brand-700 text-white text-sm px-3 py-2 rounded-lg">Thêm danh sách</button>
      </form>
      <p class="text-xs text-slate-400 mt-2">Mẹo: dán trực tiếp 1 cột mã số sinh viên từ file Excel vào đây.</p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
