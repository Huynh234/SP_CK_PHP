<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$dot_id = (int)($_GET['dot_id'] ?? 0);
$dot = db_query_one('SELECT dd.*, l.*, dd.id AS dot_id FROM dot_dangky dd JOIN lop_hocphan l ON l.id = dd.lop_id WHERE dd.id=?', [$dot_id]);
if (!$dot) {
  set_flash('error', 'Không tìm thấy đợt đăng ký.');
  redirect('/sinhvien/dashboard.php');
}
$lop_id = $dot['lop_id'];

// Xác nhận sinh viên thuộc lớp
$thuocLop = db_query_one('SELECT id FROM lop_sinhvien WHERE lop_id=? AND sinhvien_id=?', [$lop_id, $sv_id]);
if (!$thuocLop) {
  set_flash('error', 'Bạn không thuộc lớp này.');
  redirect('/sinhvien/dashboard.php');
}

// Nhóm của tôi trong lớp này
$nhom = db_query_one("
    SELECT n.* FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
    WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
", [$sv_id, $lop_id]);
if (!$nhom) {
  set_flash('error', 'Bạn chưa có nhóm trong lớp này.');
  redirect('/sinhvien/lop.php?id=' . $lop_id);
}
if ($nhom['truong_nhom_id'] != $sv_id) {
  set_flash('error', 'Chỉ trưởng nhóm được đăng ký đề tài.');
  redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

$soThanhVien = (int)db_value("SELECT COUNT(*) FROM thanhvien_nhom WHERE nhom_id=? AND trang_thai='da_xac_nhan'", [$nhom['id']]);

// Đăng ký hiện tại của nhóm cho đợt này (nếu có)
$dkHienTai = db_query_one('SELECT * FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nhom['id'], $dot_id]);

// Đã duyệt rồi -> khoá hẳn, không cho chọn/sửa lại nữa
if ($dkHienTai && $dkHienTai['trang_thai'] === 'da_duyet') {
  set_flash('error', 'Đề tài của nhóm đã được duyệt cho đợt này. Không thể chọn đề tài khác.');
  redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

// Bị từ chối -> xoá đăng ký cũ, coi như chưa đăng ký, bắt đầu lại từ đầu (form trống)
if ($dkHienTai && $dkHienTai['trang_thai'] === 'tu_choi') {
  db_exec('DELETE FROM dangky_detai WHERE id=?', [$dkHienTai['id']]);
  $dkHienTai = null;
}

// Nếu còn đăng ký (chỉ còn khả năng: cho_duyet hoặc yeu_cau_dieu_chinh) và đó là đề tài
// do chính nhóm này tự đề xuất -> cho phép SỬA LẠI (update) thay vì tạo đề tài mới
$detaiHienTai = null;
$dangSuaDeXuat = false;
if ($dkHienTai) {
  $detaiHienTai = db_query_one('SELECT * FROM detai WHERE id=?', [$dkHienTai['detai_id']]);
  if ($detaiHienTai && $detaiHienTai['nguon'] === 'sinhvien' && (int)$detaiHienTai['de_xuat_boi_nhom_id'] === (int)$nhom['id']) {
    $dangSuaDeXuat = true;
  }
}

// Đăng ký đề tài có sẵn từ GV
if (isset($_POST['dangky'])) {
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

  if (!$dt) {
    set_flash('error', 'Đề tài không hợp lệ.');
    redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
  }
  if ($dt['da_dk'] >= $dt['so_nhom_toi_da']) {
    set_flash('error', 'Đề tài đã đủ số nhóm đăng ký cho đợt này.');
    redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
  }

  db_exec('DELETE FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nhom['id'], $dot_id]);
  db_exec("INSERT INTO dangky_detai (nhom_id, detai_id, dot_id, trang_thai) VALUES (?,?,?,'da_duyet')", [$nhom['id'], $detai_id, $dot_id]);

  set_flash('success', 'Đăng ký đề tài thành công!');
  redirect('/sinhvien/nhom.php?id=' . $nhom['id']);
}

// Gửi đề xuất đề tài — TẠO MỚI (insert) nếu chưa có, hoặc SỬA LẠI (update) nếu đang có
// đề xuất cũ bị yêu cầu điều chỉnh / đang chờ duyệt của chính nhóm này.
if (isset($_POST['de_xuat'])) {
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
  if ($ten === '') {
    set_flash('error', 'Vui lòng nhập tên đề tài đề xuất.');
    redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
  }

  $gv_id = $dot['giangvien_id'];
  if (!$gv_id) {
    set_flash('error', 'Lớp chưa có giảng viên phụ trách nên chưa thể đề xuất đề tài.');
    redirect('/sinhvien/detai.php?dot_id=' . $dot_id);
  }

  if ($dangSuaDeXuat) {
    // UPDATE: sửa lại chính đề xuất cũ, gửi lại cho giảng viên duyệt
    db_exec('UPDATE detai SET ten_detai=?, mo_ta=? WHERE id=?', [$ten, $mota, $detaiHienTai['id']]);
    db_exec("UPDATE dangky_detai SET trang_thai='cho_duyet', phan_hoi=NULL WHERE id=?", [$dkHienTai['id']]);
    set_flash('success', 'Đã cập nhật đề xuất và gửi lại cho giảng viên duyệt.');
  } else {
    // INSERT: đề xuất hoàn toàn mới
    db_exec(
      "INSERT INTO detai (giangvien_id, ten_detai, mo_ta, so_nhom_toi_da, nguon, de_xuat_boi_nhom_id, trang_thai) VALUES (?,?,?,?,'sinhvien',?,'mo')",
      [$gv_id, $ten, $mota, 1, $nhom['id']]
    );
    $detai_id = db_last_id();
    db_exec('INSERT INTO detai_dot (detai_id, dot_id) VALUES (?,?)', [$detai_id, $dot_id]);
    db_exec('DELETE FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nhom['id'], $dot_id]);
    db_exec("INSERT INTO dangky_detai (nhom_id, detai_id, dot_id, trang_thai) VALUES (?,?,?,'cho_duyet')", [$nhom['id'], $detai_id, $dot_id]);
    set_flash('success', 'Đã gửi đề xuất đề tài. Chờ giảng viên phê duyệt.');
  }
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
include '../includes/header.php';
?>
<a href="<?php echo BASE_URL; ?>/sinhvien/nhom.php?id=<?php echo $nhom['id']; ?>" class="text-sm text-brand-600 hover:underline">← <?php echo e($nhom['ten_nhom']); ?></a>
<h1 class="text-xl font-bold text-slate-800 mt-2 mb-1">Chọn đề tài — <?php echo e($dot['ten_dot']) ?></h1>
<p class="text-sm text-slate-500 mb-6">
  <?php echo $mucDichLabel[$dot['muc_dich']] ?> · Hạn đăng ký: <?php echo format_datetime($dot['han_dang_ky']) ?> ·
  Nhóm cần tối thiểu <?php echo $dot['si_so_nhom_toi_thieu'] ?> thành viên (hiện có <?php echo $soThanhVien ?>)
</p>

<?php
if ($soThanhVien < $dot['si_so_nhom_toi_thieu']) {
  echo '<div class="bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-xl p-4 mb-6">
            Nhóm chưa đủ số lượng thành viên tối thiểu để đăng ký đề tài. Hãy mời thêm thành viên trước.
          </div>';
}

if ($dangSuaDeXuat) {
  $dangCho = $dkHienTai['trang_thai'] === 'cho_duyet';
  echo '<div class="border rounded-xl p-4 mb-6 '
    . ($dangCho ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-sky-50 border-sky-200 text-sky-700')
    . '">';

  echo '<div class="text-xs font-semibold mb-1">'
    . ($dangCho ? 'Đề xuất đang chờ giảng viên duyệt' : 'Giảng viên yêu cầu điều chỉnh đề xuất này')
    . '</div>';

  if (!empty($dkHienTai['phan_hoi'])) {
    echo '<p class="text-xs mt-1 italic">Phản hồi của giảng viên: "'
      . e($dkHienTai['phan_hoi'])
      . '"</p>';
  }

  echo '<p class="text-xs mt-2 opacity-80">
            Nội dung đề xuất cũ đã được điền sẵn ở form bên dưới — sửa lại rồi gửi để giảng viên duyệt lại.
          </p>';

  echo '</div>';
}
?>


<h2 class="font-semibold text-slate-800 mb-3">Đề tài do giảng viên cung cấp</h2>
<div class="grid gap-3 mb-8">
  <?php
  foreach ($detais as $d) {
    echo '<div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between gap-3">';

    echo '<div>';
    echo '<div class="font-medium text-slate-800">' . e($d['ten_detai']) . '</div>';
    echo '<p class="text-sm text-slate-500 mt-0.5">' . nl2br(e($d['mo_ta'])) . '</p>';
    echo '<div class="text-xs text-slate-400 mt-1">Đã đăng ký: '
      . $d['da_dk'] . '/' . $d['so_nhom_toi_da']
      . ' nhóm (trong đợt này)</div>';
    echo '</div>';

    if ($d['da_dk'] < $d['so_nhom_toi_da'] && !is_qua_han($dot['han_dang_ky']) && $soThanhVien >= $dot['si_so_nhom_toi_thieu']) {
      echo '<form method="post">' . csrf_field()
        . '<input type="hidden" name="detai_id" value="' . $d['id'] . '">';
      echo '<button name="dangky" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg whitespace-nowrap">Đăng ký</button>';
      echo '</form>';
    } else {
      echo '<span class="text-xs text-slate-400 whitespace-nowrap">Không thể đăng ký</span>';
    }

    echo '</div>';
  }
  ?>

  <?php
  if (!$detais) {
    echo '<div class="text-slate-400 text-sm">Giảng viên chưa cung cấp đề tài nào cho đợt này.</div>';
  }
  ?>

</div>

<h2 class="font-semibold text-slate-800 mb-3">
  <?php echo $dangSuaDeXuat ? 'Sửa lại đề xuất của nhóm' : 'Hoặc tự đề xuất đề tài mới cho đợt này' ?>
</h2>
<div class="bg-white border border-slate-200 rounded-xl p-5 max-w-lg">
  <form method="post" class="space-y-3">
    <?php echo csrf_field() ?>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Tên đề tài đề xuất *</label>
      <input name="ten_detai" required value="<?php echo e($detaiHienTai['ten_detai'] ?? '') ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Mô tả</label>
      <textarea name="mo_ta" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><?php echo e($detaiHienTai['mo_ta'] ?? '') ?></textarea>
    </div>
    <button name="de_xuat" <?php echo is_qua_han($dot['han_dang_ky']) || $soThanhVien < $dot['si_so_nhom_toi_thieu'] ? 'disabled' : '' ?>
      class="bg-brand-600 hover:bg-brand-700 disabled:bg-slate-300 text-white text-sm px-4 py-2 rounded-lg">
      <?php echo $dangSuaDeXuat ? 'Cập nhật đề xuất (gửi lại chờ duyệt)' : 'Gửi đề xuất (chờ GV duyệt)' ?>
    </button>
  </form>
</div>

<?php include '../includes/footer.php'; ?>