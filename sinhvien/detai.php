<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$lop_id = (int)($_GET['lop_id'] ?? 0);
$lop = $pdo->prepare("
    SELECT l.* FROM lop_hocphan l JOIN lop_sinhvien ls ON ls.lop_id=l.id AND ls.sinhvien_id=?
    WHERE l.id=?
");
$lop->execute([$sv_id, $lop_id]);
$lop = $lop->fetch();
if (!$lop) { set_flash('error', 'Không tìm thấy lớp.'); redirect('/sinhvien/dashboard.php'); }

// Nhóm của tôi trong lớp này
$nhom = $pdo->prepare("
    SELECT n.* FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
    WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
");
$nhom->execute([$sv_id, $lop_id]);
$nhom = $nhom->fetch();
if (!$nhom) { set_flash('error', 'Bạn chưa có nhóm trong lớp này.'); redirect('/sinhvien/lop.php?id=' . $lop_id); }
if ($nhom['truong_nhom_id'] != $sv_id) { set_flash('error', 'Chỉ trưởng nhóm được đăng ký đề tài.'); redirect('/sinhvien/nhom.php?id=' . $nhom['id']); }

$soThanhVien = $pdo->prepare("SELECT COUNT(*) c FROM thanhvien_nhom WHERE nhom_id=? AND trang_thai='da_xac_nhan'");
$soThanhVien->execute([$nhom['id']]);
$soThanhVien = $soThanhVien->fetch()['c'];

// Đăng ký hiện tại của nhóm (nếu có)
$dkHienTai = $pdo->prepare('SELECT * FROM dangky_detai WHERE nhom_id=?');
$dkHienTai->execute([$nhom['id']]);
$dkHienTai = $dkHienTai->fetch();

if ($dkHienTai && in_array($dkHienTai['trang_thai'], ['cho_duyet','da_duyet','yeu_cau_dieu_chinh'], true)) {
    set_flash('error', 'Nhóm bạn đã có đăng ký đề tài đang xử lý. Không thể chọn đề tài khác lúc này.');
    redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

// Đăng ký đề tài có sẵn từ GV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dangky') {
    csrf_check();

    if (is_qua_han($lop['han_dang_ky_detai'])) {
        set_flash('error', 'Đã hết hạn đăng ký đề tài.');
        redirect('/sinhvien/detai.php?lop_id=' . $lop_id);
    }
    if ($soThanhVien < $lop['si_so_nhom_toi_thieu']) {
        set_flash('error', "Nhóm cần tối thiểu {$lop['si_so_nhom_toi_thieu']} thành viên để đăng ký đề tài.");
        redirect('/sinhvien/detai.php?lop_id=' . $lop_id);
    }

    $detai_id = (int)$_POST['detai_id'];
    $dt = $pdo->prepare("
        SELECT d.*, (SELECT COUNT(*) FROM dangky_detai WHERE detai_id=d.id AND trang_thai='da_duyet') AS da_dk
        FROM detai d JOIN detai_lop dl ON dl.detai_id=d.id
        WHERE d.id=? AND dl.lop_id=? AND d.trang_thai='mo'
    ");
    $dt->execute([$detai_id, $lop_id]);
    $dt = $dt->fetch();

    if (!$dt) { set_flash('error', 'Đề tài không hợp lệ.'); redirect('/sinhvien/detai.php?lop_id=' . $lop_id); }
    if ($dt['da_dk'] >= $dt['so_nhom_toi_da']) { set_flash('error', 'Đề tài đã đủ số nhóm đăng ký.'); redirect('/sinhvien/detai.php?lop_id=' . $lop_id); }

    // Xoá bản ghi cũ nếu là tu_choi
    $pdo->prepare('DELETE FROM dangky_detai WHERE nhom_id=?')->execute([$nhom['id']]);
    // Đề tài GV cho + đủ điều kiện => tự động duyệt
    $pdo->prepare("INSERT INTO dangky_detai (nhom_id, detai_id, trang_thai) VALUES (?,?,'da_duyet')")
        ->execute([$nhom['id'], $detai_id]);

    set_flash('success', 'Đăng ký đề tài thành công!');
    redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

// Tự đề xuất đề tài mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'de_xuat') {
    csrf_check();
    if (is_qua_han($lop['han_dang_ky_detai'])) {
        set_flash('error', 'Đã hết hạn đăng ký đề tài.');
        redirect('/sinhvien/detai.php?lop_id=' . $lop_id);
    }
    if ($soThanhVien < $lop['si_so_nhom_toi_thieu']) {
        set_flash('error', "Nhóm cần tối thiểu {$lop['si_so_nhom_toi_thieu']} thành viên để đăng ký đề tài.");
        redirect('/sinhvien/detai.php?lop_id=' . $lop_id);
    }
    $ten = trim($_POST['ten_detai']);
    $mota = trim($_POST['mo_ta']);
    if ($ten === '') { set_flash('error', 'Vui lòng nhập tên đề tài đề xuất.'); redirect('/sinhvien/detai.php?lop_id=' . $lop_id); }

    // Giảng viên phụ trách lớp để gán quyền duyệt
    $gv_id = $lop['giangvien_id'];
    if (!$gv_id) { set_flash('error', 'Lớp chưa có giảng viên phụ trách nên chưa thể đề xuất đề tài.'); redirect('/sinhvien/detai.php?lop_id=' . $lop_id); }

    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO detai (giangvien_id, ten_detai, mo_ta, so_nhom_toi_da, nguon, de_xuat_boi_nhom_id, trang_thai) VALUES (?,?,?,?,'sinhvien',?,'mo')")
        ->execute([$gv_id, $ten, $mota, 1, $nhom['id']]);
    $detai_id = $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO detai_lop (detai_id, lop_id) VALUES (?,?)')->execute([$detai_id, $lop_id]);
    $pdo->prepare('DELETE FROM dangky_detai WHERE nhom_id=?')->execute([$nhom['id']]);
    $pdo->prepare("INSERT INTO dangky_detai (nhom_id, detai_id, trang_thai) VALUES (?,?,'cho_duyet')")
        ->execute([$nhom['id'], $detai_id]);
    $pdo->commit();

    set_flash('success', 'Đã gửi đề xuất đề tài. Chờ giảng viên phê duyệt.');
    redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

$detais = $pdo->prepare("
    SELECT d.*, (SELECT COUNT(*) FROM dangky_detai WHERE detai_id=d.id AND trang_thai='da_duyet') AS da_dk
    FROM detai d JOIN detai_lop dl ON dl.detai_id=d.id
    WHERE dl.lop_id=? AND d.trang_thai='mo' AND d.nguon='giangvien'
    ORDER BY d.ten_detai
");
$detais->execute([$lop_id]);
$detais = $detais->fetchAll();

$page_title = 'Chọn đề tài';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/sinhvien/nhom.php?id=<?= $nhom['id'] ?>" class="text-sm text-brand-600 hover:underline">← <?= e($nhom['ten_nhom']) ?></a>
<h1 class="text-xl font-bold text-slate-800 mt-2 mb-1">Chọn đề tài</h1>
<p class="text-sm text-slate-500 mb-6">
  Hạn đăng ký: <?= format_datetime($lop['han_dang_ky_detai']) ?> ·
  Nhóm cần tối thiểu <?= $lop['si_so_nhom_toi_thieu'] ?> thành viên (hiện có <?= $soThanhVien ?>)
</p>

<?php if ($soThanhVien < $lop['si_so_nhom_toi_thieu']): ?>
  <div class="bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-xl p-4 mb-6">
    Nhóm chưa đủ số lượng thành viên tối thiểu để đăng ký đề tài. Hãy mời thêm thành viên trước.
  </div>
<?php endif; ?>

<h2 class="font-semibold text-slate-800 mb-3">Đề tài do giảng viên cung cấp</h2>
<div class="grid gap-3 mb-8">
  <?php foreach ($detais as $d): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between gap-3">
    <div>
      <div class="font-medium text-slate-800"><?= e($d['ten_detai']) ?></div>
      <p class="text-sm text-slate-500 mt-0.5"><?= nl2br(e($d['mo_ta'])) ?></p>
      <div class="text-xs text-slate-400 mt-1">Đã đăng ký: <?= $d['da_dk'] ?>/<?= $d['so_nhom_toi_da'] ?> nhóm</div>
    </div>
    <?php if ($d['da_dk'] < $d['so_nhom_toi_da'] && !is_qua_han($lop['han_dang_ky_detai']) && $soThanhVien >= $lop['si_so_nhom_toi_thieu']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="dangky"><input type="hidden" name="detai_id" value="<?= $d['id'] ?>">
        <button class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg whitespace-nowrap">Đăng ký</button>
      </form>
    <?php else: ?>
      <span class="text-xs text-slate-400 whitespace-nowrap">Không thể đăng ký</span>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$detais): ?><div class="text-slate-400 text-sm">Giảng viên chưa cung cấp đề tài nào cho lớp này.</div><?php endif; ?>
</div>

<h2 class="font-semibold text-slate-800 mb-3">Hoặc tự đề xuất đề tài mới</h2>
<div class="bg-white border border-slate-200 rounded-xl p-5 max-w-lg">
  <form method="post" class="space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="de_xuat">
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Tên đề tài đề xuất *</label>
      <input name="ten_detai" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Mô tả</label>
      <textarea name="mo_ta" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
    </div>
    <button <?= is_qua_han($lop['han_dang_ky_detai']) || $soThanhVien < $lop['si_so_nhom_toi_thieu'] ? 'disabled' : '' ?>
      class="bg-brand-600 hover:bg-brand-700 disabled:bg-slate-300 text-white text-sm px-4 py-2 rounded-lg">
      Gửi đề xuất (chờ GV duyệt)
    </button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
