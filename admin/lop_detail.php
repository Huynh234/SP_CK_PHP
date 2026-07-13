<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT l.*, h.ma_hp, h.ten_hp FROM lop_hocphan l JOIN hocphan h ON h.id=l.hocphan_id WHERE l.id=?');
$stmt->execute([$id]);
$lop = $stmt->fetch();
if (!$lop) { set_flash('error', 'Không tìm thấy lớp học phần.'); redirect('/admin/lop.php'); }

// Gán / đổi giảng viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_gv') {
    csrf_check();
    $gv_id = $_POST['giangvien_id'] !== '' ? (int)$_POST['giangvien_id'] : null;
    $pdo->prepare('UPDATE lop_hocphan SET giangvien_id=? WHERE id=?')->execute([$gv_id, $id]);
    set_flash('success', 'Đã cập nhật giảng viên phụ trách.');
    redirect('/admin/lop_detail.php?id=' . $id);
}

// Thêm 1 sinh viên vào lớp (chọn từ danh sách có sẵn)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_one') {
    csrf_check();
    $sv_id = (int)$_POST['sinhvien_id'];
    if ($sv_id) {
        try {
            $pdo->prepare('INSERT INTO lop_sinhvien (lop_id, sinhvien_id) VALUES (?,?)')->execute([$id, $sv_id]);
            set_flash('success', 'Đã thêm sinh viên vào lớp.');
        } catch (PDOException $e) {
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
        $u = $pdo->prepare("SELECT id FROM users WHERE (username=? OR mssv_mgv=?) AND role='sinhvien'");
        $u->execute([$ma, $ma]);
        $row = $u->fetch();
        if (!$row) { $loi[] = $ma; continue; }
        try {
            $pdo->prepare('INSERT INTO lop_sinhvien (lop_id, sinhvien_id) VALUES (?,?)')->execute([$id, $row['id']]);
            $ok++;
        } catch (PDOException $e) { /* đã tồn tại trong lớp, bỏ qua */ }
    }
    set_flash($loi ? 'error' : 'success', "Đã thêm {$ok} sinh viên." . ($loi ? ' Không tìm thấy: ' . implode(', ', $loi) : ''));
    redirect('/admin/lop_detail.php?id=' . $id);
}

// Xoá sinh viên khỏi lớp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_sv') {
    csrf_check();
    $pdo->prepare('DELETE FROM lop_sinhvien WHERE lop_id=? AND sinhvien_id=?')->execute([$id, (int)$_POST['sinhvien_id']]);
    set_flash('success', 'Đã xoá sinh viên khỏi lớp.');
    redirect('/admin/lop_detail.php?id=' . $id);
}

$giangviens = $pdo->query("SELECT * FROM users WHERE role='giangvien' ORDER BY ho_ten")->fetchAll();

$svInLop = $pdo->prepare("
    SELECT u.* FROM lop_sinhvien ls JOIN users u ON u.id = ls.sinhvien_id
    WHERE ls.lop_id = ? ORDER BY u.ho_ten
");
$svInLop->execute([$id]);
$svInLop = $svInLop->fetchAll();

$svIds = array_column($svInLop, 'id');
$svAvailable = $pdo->prepare("SELECT * FROM users WHERE role='sinhvien'" . ($svIds ? ' AND id NOT IN (' . implode(',', array_fill(0, count($svIds), '?')) . ')' : '') . ' ORDER BY ho_ten');
$svAvailable->execute($svIds);
$svAvailable = $svAvailable->fetchAll();

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
