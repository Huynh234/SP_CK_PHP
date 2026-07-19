<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$dot_id = (int)($_GET['dot_id'] ?? 0);
$dot = db_query_one('SELECT dd.*, l.* , dd.id AS dot_id FROM dot_dangky dd JOIN lop_hocphan l ON l.id = dd.lop_id WHERE dd.id=?', [$dot_id]);
if (!$dot) { set_flash('error', 'Không tìm thấy đợt đăng ký.'); redirect('/sinhvien/dashboard.php'); }
$lop_id = $dot['lop_id'];

// Xác nhận sinh viên thuộc lớp
$thuocLop = db_query_one('SELECT id FROM lop_sinhvien WHERE lop_id=? AND sinhvien_id=?', [$lop_id, $sv_id]);
if (!$thuocLop) { set_flash('error', 'Bạn không thuộc lớp này.'); redirect('/sinhvien/dashboard.php'); }

// Nhóm của tôi trong lớp này
$nhom = db_query_one("
    SELECT n.* FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
    WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
", [$sv_id, $lop_id]);
if (!$nhom) { set_flash('error', 'Bạn chưa có nhóm trong lớp này.'); redirect('/sinhvien/lop.php?id=' . $lop_id); }
if ($nhom['truong_nhom_id'] != $sv_id) { set_flash('error', 'Chỉ trưởng nhóm được đăng ký đề tài.'); redirect('/sinhvien/nhom.php?id=' . $nhom['id']); }

$soThanhVien = (int)db_value("SELECT COUNT(*) FROM thanhvien_nhom WHERE nhom_id=? AND trang_thai='da_xac_nhan'", [$nhom['id']]);

// Đăng ký hiện tại của nhóm cho đợt này (nếu có)
$dkHienTai = db_query_one('SELECT * FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nhom['id'], $dot_id]);

if ($dkHienTai && in_array($dkHienTai['trang_thai'], ['cho_duyet','da_duyet','yeu_cau_dieu_chinh'], true)) {
    set_flash('error', 'Nhóm bạn đã có đăng ký đề tài đang xử lý cho đợt này. Không thể chọn đề tài khác lúc này.');
    redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

// Đăng ký đề tài có sẵn từ GV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dangky') {
    csrf_check();

    if (is_qua_han($dot['han_dang_ky'])) {
        set_flash('error', 'Đã hết hạn đăng ký của đợt này.');
        redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
    }
    if ($soThanhVien < $dot['si_so_nhom_toi_thieu']) {
        set_flash('error', "Nhóm cần tối thiểu {$dot['si_so_nhom_toi_thieu']} thành viên để đăng ký đề tài.");
        redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
    }

    $detai_id = (int)$_POST['detai_id'];
    $dt = db_query_one("
        SELECT d.*, (SELECT COUNT(*) FROM dangky_detai WHERE detai_id=d.id AND dot_id=? AND trang_thai='da_duyet') AS da_dk
        FROM detai d JOIN detai_dot ddt ON ddt.detai_id=d.id
        WHERE d.id=? AND ddt.dot_id=? AND d.trang_thai='mo'
    ", [$dot_id, $detai_id, $dot_id]);

    if (!$dt) { set_flash('error', 'Đề tài không hợp lệ.'); redirect('/sinhvien/detai.php?dot_id=' . $dot_id); }
    if ($dt['da_dk'] >= $dt['so_nhom_toi_da']) { set_flash('error', 'Đề tài đã đủ số nhóm đăng ký cho đợt này.'); redirect('/sinhvien/detai.php?dot_id=' . $dot_id); }

    db_exec('DELETE FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nhom['id'], $dot_id]);
    db_exec("INSERT INTO dangky_detai (nhom_id, detai_id, dot_id, trang_thai) VALUES (?,?,?,'da_duyet')", [$nhom['id'], $detai_id, $dot_id]);

    set_flash('success', 'Đăng ký đề tài thành công!');
    redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

// Tự đề xuất đề tài mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'de_xuat') {
    csrf_check();
    if (is_qua_han($dot['han_dang_ky'])) {
        set_flash('error', 'Đã hết hạn đăng ký của đợt này.');
        redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
    }
    if ($soThanhVien < $dot['si_so_nhom_toi_thieu']) {
        set_flash('error', "Nhóm cần tối thiểu {$dot['si_so_nhom_toi_thieu']} thành viên để đăng ký đề tài.");
        redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
    }
    $ten = trim($_POST['ten_detai']);
    $mota = trim($_POST['mo_ta']);
    if ($ten === '') { set_flash('error', 'Vui lòng nhập tên đề tài đề xuất.'); redirect('/sinhvien/detai.php?dot_id=' . $dot_id); }

    $gv_id = $dot['giangvien_id'];
    if (!$gv_id) { set_flash('error', 'Lớp chưa có giảng viên phụ trách nên chưa thể đề xuất đề tài.'); redirect('/sinhvien/detai.php?dot_id=' . $dot_id); }

    db_exec("INSERT INTO detai (giangvien_id, ten_detai, mo_ta, so_nhom_toi_da, nguon, de_xuat_boi_nhom_id, trang_thai) VALUES (?,?,?,?,'sinhvien',?,'mo')",
        [$gv_id, $ten, $mota, 1, $nhom['id']]);
    $detai_id = db_last_id();
    db_exec('INSERT INTO detai_dot (detai_id, dot_id) VALUES (?,?)', [$detai_id, $dot_id]);
    db_exec('DELETE FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nhom['id'], $dot_id]);
    db_exec("INSERT INTO dangky_detai (nhom_id, detai_id, dot_id, trang_thai) VALUES (?,?,?,'cho_duyet')", [$nhom['id'], $detai_id, $dot_id]);

    set_flash('success', 'Đã gửi đề xuất đề tài. Chờ giảng viên phê duyệt.');
    redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

$detais = db_query("
    SELECT d.*, (SELECT COUNT(*) FROM dangky_detai WHERE detai_id=d.id AND dot_id=? AND trang_thai='da_duyet') AS da_dk
    FROM detai d JOIN detai_dot ddt ON ddt.detai_id=d.id
    WHERE ddt.dot_id=? AND d.trang_thai='mo' AND d.nguon='giangvien'
    ORDER BY d.ten_detai
", [$dot_id, $dot_id]);

$mucDichLabel = ['giua_ky' => 'Giữa kỳ', 'cuoi_ky' => 'Cuối kỳ', 'khac' => 'Khác'];

$page_title = 'Chọn đề tài';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/sinhvien/nhom.php?id=<?= $nhom['id'] ?>" class="text-sm text-brand-600 hover:underline">← <?= e($nhom['ten_nhom']) ?></a>
<h1 class="text-xl font-bold text-slate-800 mt-2 mb-1">Chọn đề tài — <?= e($dot['ten_dot']) ?></h1>
<p class="text-sm text-slate-500 mb-6">
  <?= $mucDichLabel[$dot['muc_dich']] ?> · Hạn đăng ký: <?= format_datetime($dot['han_dang_ky']) ?> ·
  Nhóm cần tối thiểu <?= $dot['si_so_nhom_toi_thieu'] ?> thành viên (hiện có <?= $soThanhVien ?>)
</p>

<?php if ($soThanhVien < $dot['si_so_nhom_toi_thieu']): ?>
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
      <div class="text-xs text-slate-400 mt-1">Đã đăng ký: <?= $d['da_dk'] ?>/<?= $d['so_nhom_toi_da'] ?> nhóm (trong đợt này)</div>
    </div>
    <?php if ($d['da_dk'] < $d['so_nhom_toi_da'] && !is_qua_han($dot['han_dang_ky']) && $soThanhVien >= $dot['si_so_nhom_toi_thieu']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="dangky"><input type="hidden" name="detai_id" value="<?= $d['id'] ?>">
        <button class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg whitespace-nowrap">Đăng ký</button>
      </form>
    <?php else: ?>
      <span class="text-xs text-slate-400 whitespace-nowrap">Không thể đăng ký</span>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$detais): ?><div class="text-slate-400 text-sm">Giảng viên chưa cung cấp đề tài nào cho đợt này.</div><?php endif; ?>
</div>

<h2 class="font-semibold text-slate-800 mb-3">Hoặc tự đề xuất đề tài mới cho đợt này</h2>
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
    <button <?= is_qua_han($dot['han_dang_ky']) || $soThanhVien < $dot['si_so_nhom_toi_thieu'] ? 'disabled' : '' ?>
      class="bg-brand-600 hover:bg-brand-700 disabled:bg-slate-300 text-white text-sm px-4 py-2 rounded-lg">
      Gửi đề xuất (chờ GV duyệt)
    </button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
