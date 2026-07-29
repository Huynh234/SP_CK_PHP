<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('giangvien');
$gv_id = $_SESSION['user_id'];

// Danh sách đợt đăng ký thuộc các lớp của giảng viên này (dùng để gán đề tài)
function dsDotCuaGiangVien(int $gv_id): array
{
  return db_query("
        SELECT dd.id, dd.ten_dot, l.ma_lop, l.ten_lop
        FROM dot_dangky dd
        JOIN lop_hocphan l ON l.id = dd.lop_id
        WHERE l.giangvien_id = ?
        ORDER BY l.ma_lop, dd.created_at
    ", [$gv_id]);
}

// Thêm đề tài mới
if (isset($_POST['add'])) {
  csrf_check();
  $ten = trim($_POST['ten_detai']);
  $mota = trim($_POST['mo_ta']);
  $sn = max(1, (int)$_POST['so_nhom_toi_da']);
  $dot_ids = $_POST['dot_ids'] ?? [];

  if ($ten === '') {
    set_flash('error', 'Vui lòng nhập tên đề tài.');
    redirect('/giangvien/detai.php');
  }

  db_exec(
    "INSERT INTO detai (giangvien_id, ten_detai, mo_ta, so_nhom_toi_da, nguon, trang_thai) VALUES (?,?,?,?,'giangvien','mo')",
    [$gv_id, $ten, $mota, $sn]
  );
  $detai_id = db_last_id();

  foreach ($dot_ids as $did) {
    $chk = db_query_one("SELECT dd.id FROM dot_dangky dd JOIN lop_hocphan l ON l.id=dd.lop_id WHERE dd.id=? AND l.giangvien_id=?", [(int)$did, $gv_id]);
    if ($chk) db_exec('INSERT INTO detai_dot (detai_id, dot_id) VALUES (?,?)', [$detai_id, (int)$did]);
  }

  set_flash('success', 'Đã tạo đề tài.');
  redirect('/giangvien/detai.php');
}

// Import đề tài từ file CSV
if (isset($_POST['import_csv'])) {
  csrf_check();

  if (empty($_FILES['file_csv']['tmp_name']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'Vui lòng chọn file CSV hợp lệ.');
    redirect('/giangvien/detai.php');
  }

  $dot_ids = $_POST['dot_ids'] ?? [];
  $dot_ids_hople = [];
  foreach ($dot_ids as $did) {
    $chk = db_query_one("SELECT dd.id FROM dot_dangky dd JOIN lop_hocphan l ON l.id=dd.lop_id WHERE dd.id=? AND l.giangvien_id=?", [(int)$did, $gv_id]);
    if ($chk) $dot_ids_hople[] = (int)$did;
  }

  $handle = fopen($_FILES['file_csv']['tmp_name'], 'r');
  $bom = fread($handle, 3);
  if ($bom !== "\xEF\xBB\xBF") rewind($handle);

  $so_tao = 0;
  $so_dong = 0;
  $header_skipped = false;
  while (($row = fgetcsv($handle, 0, ',')) !== false) {
    $so_dong++;
    if (count($row) < 1) continue;

    if (!$header_skipped && strtolower(trim($row[0])) === 'ten_detai') {
      $header_skipped = true;
      continue;
    }
    $header_skipped = true;

    $ten  = trim($row[0] ?? '');
    $mota = trim($row[1] ?? '');
    $sn   = isset($row[2]) && (int)$row[2] > 0 ? (int)$row[2] : 1;
    if ($ten === '') continue;

    db_exec(
      "INSERT INTO detai (giangvien_id, ten_detai, mo_ta, so_nhom_toi_da, nguon, trang_thai) VALUES (?,?,?,?,'giangvien','mo')",
      [$gv_id, $ten, $mota, $sn]
    );
    $detai_id = db_last_id();
    foreach ($dot_ids_hople as $did) {
      db_exec('INSERT INTO detai_dot (detai_id, dot_id) VALUES (?,?)', [$detai_id, $did]);
    }
    $so_tao++;
  }
  fclose($handle);

  set_flash($so_tao ? 'success' : 'error', $so_tao ? "Đã import {$so_tao} đề tài từ CSV." : 'Không tìm thấy dòng hợp lệ nào trong file.');
  redirect('/giangvien/detai.php');
}

// Đóng / mở đề tài
if (isset($_POST['toggle'])) {
  csrf_check();
  $id = (int)$_POST['id'];
  $chk = db_query_one('SELECT id FROM detai WHERE id=? AND giangvien_id=?', [$id, $gv_id]);
  if ($chk) {
    db_exec("UPDATE detai SET trang_thai = IF(trang_thai='mo','dong','mo') WHERE id=?", [$id]);
    set_flash('success', 'Đã cập nhật trạng thái đề tài.');
  }
  redirect('/giangvien/detai.php');
}

// Xoá đề tài
if (isset($_POST['del'])) {
  csrf_check();
  $id = (int)$_POST['id'];
  db_exec('DELETE FROM detai WHERE id=? AND giangvien_id=?', [$id, $gv_id]);
  set_flash('success', 'Đã xoá đề tài.');
  redirect('/giangvien/detai.php');
}

$dots = dsDotCuaGiangVien($gv_id);

$detais = db_query("
    SELECT d.*,
      (SELECT COUNT(*) FROM dangky_detai dk WHERE dk.detai_id=d.id AND dk.trang_thai='da_duyet') AS so_dang_ky,
      GROUP_CONCAT(DISTINCT CONCAT(l.ma_lop, ' / ', dd.ten_dot) SEPARATOR ', ') AS ds_dot
    FROM detai d
    LEFT JOIN detai_dot ddt ON ddt.detai_id = d.id
    LEFT JOIN dot_dangky dd ON dd.id = ddt.dot_id
    LEFT JOIN lop_hocphan l ON l.id = dd.lop_id
    WHERE d.giangvien_id = ? AND d.nguon='giangvien'
    GROUP BY d.id ORDER BY d.created_at DESC
", [$gv_id]);

$page_title = 'Ngân hàng đề tài';
include '../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <h1 class="text-xl font-bold text-slate-800">Ngân hàng đề tài</h1>
  <div class="flex gap-2">
    <button onclick="document.getElementById('modalCsv').classList.remove('hidden')" class="bg-white border border-slate-300 hover:border-brand-400 text-slate-700 text-sm px-4 py-2 rounded-lg">📥 Import CSV</button>
    <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">+ Thêm 1 đề tài</button>
  </div>
</div>

<?php
if (!$dots) {
  echo '<div class="bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-xl p-4 mb-6">';
  echo 'Bạn chưa có đợt đăng ký đề tài nào (VD: "Giữa kỳ", "Cuối kỳ"). ';
  echo 'Vào trang chi tiết 1 lớp để tạo đợt trước khi gán đề tài.';
  echo '</div>';
}
?>


<div class="grid gap-4">
  <?php
  foreach ($detais as $d) {
    echo '<div class="bg-white border border-slate-200 rounded-xl p-5">';
    echo '<div class="flex flex-wrap items-start justify-between gap-3">';
    echo '<div>';
    echo '<div class="flex items-center gap-2">';
    echo '<span class="font-semibold text-slate-800">' . $d['ten_detai'] . '</span>';
    if ($d['trang_thai'] === 'mo') {
      echo '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Đang mở</span>';
    } else {
      echo '<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Đã đóng</span>';
    }
    echo '</div>';
    echo '<p class="text-sm text-slate-500 mt-1">' . nl2br($d['mo_ta']) . '</p>';
    echo '<div class="text-xs text-slate-400 mt-2">';
    echo 'Đã đăng ký: <b>' . $d['so_dang_ky'] . '/' . $d['so_nhom_toi_da'] . '</b> nhóm (mỗi đợt) · ';
    echo 'Dùng cho đợt: ' . ($d['ds_dot'] ? $d['ds_dot'] : 'chưa gán đợt nào');
    echo '</div>';
    echo '</div>';
    echo '<div class="flex gap-2 shrink-0">';
    echo '<form method="post">';
    echo csrf_field();
    echo '<input type="hidden" name="id" value="' . $d['id'] . '">';
    echo '<button class="text-xs text-slate-500 hover:text-brand-600" name="toggle">';
    echo ($d['trang_thai'] === 'mo' ? 'Đóng' : 'Mở lại');
    echo '</button>';
    echo '</form>';
    echo '<form method="post">';
    echo csrf_field();
    echo '<input type="hidden" name="id" value="' . $d['id'] . '">';
    echo '<button class="text-xs text-rose-500 hover:text-rose-700" name="del">Xoá</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
  }
  ?>
  <?php
  if (!$detais) {
    echo '<div class="text-center text-slate-400 py-12">';
    echo 'Chưa có đề tài nào. Bấm "+ Thêm 1 đề tài" để tạo.';
    echo '</div>';
  }
  ?>
</div>

<?php
// Component checkbox danh sách đợt, dùng lại cho cả 3 modal
$dotCheckboxes = function () use ($dots) {
    echo '<div class="border border-slate-300 rounded-lg p-2 max-h-32 overflow-y-auto text-sm space-y-1">';
    if ($dots) {
        foreach ($dots as $d) {
            echo '<label class="flex items-center gap-2">';
            echo '<input type="checkbox" name="dot_ids[]" value="' . $d['id'] . '">';
            echo $d['ma_lop'] . ' — ' . $d['ten_dot'];
            echo '</label>';
        }
    } else {
        echo '<p class="text-slate-400 text-xs">Chưa có đợt đăng ký nào để gán.</p>';
    }
    echo '</div>';
};
?>


<!-- Modal thêm 1 đề tài -->
<div id="modalAdd" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
  <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <h2 class="font-bold text-slate-800 mb-4">Thêm đề tài mới</h2>
    <form method="post" class="space-y-3">
      <?php echo csrf_field() ?>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Tên đề tài *</label>
        <input name="ten_detai" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Mô tả</label>
        <textarea name="mo_ta" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Số nhóm tối đa được đăng ký (mỗi đợt)</label>
        <input name="so_nhom_toi_da" type="number" min="1" value="1" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Gán cho đợt đăng ký (có thể chọn nhiều đợt/nhiều lớp)</label>
        <?php $dotCheckboxes(); ?>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
        <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white" name="add">Tạo đề tài</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal import CSV -->
<div id="modalCsv" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
  <div class="bg-white rounded-xl p-6 w-full max-w-xl max-h-[90vh] overflow-y-auto">
    <h2 class="font-bold text-slate-800 mb-1">Import đề tài từ file CSV</h2>
    <p class="text-xs text-slate-500 mb-4">
      Chuẩn bị file CSV (xuất từ Excel: File → Save As → CSV UTF-8) theo cột:
      <code class="bg-slate-100 px-1 rounded">ten_detai, mo_ta, so_nhom_toi_da</code>.
      Dòng tiêu đề (nếu có) sẽ tự động được bỏ qua.
    </p>
    <form method="post" enctype="multipart/form-data" class="space-y-3">
      <?php echo csrf_field() ?>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">File CSV *</label>
        <input type="file" name="file_csv" accept=".csv" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Gán tất cả cho đợt (tuỳ chọn)</label>
        <?php $dotCheckboxes(); ?>
      </div>
      <div class="text-xs text-slate-400">
        <p class="font-medium mb-1">Ví dụ nội dung file CSV:</p>
        <pre class="bg-slate-100 rounded-lg p-3 overflow-x-auto">ten_detai,mo_ta,so_nhom_toi_da
              Website bán hàng,Xây dựng website TMĐT cơ bản,2
              Ứng dụng quản lý thư viện,Quản lý mượn trả sách,1</pre>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modalCsv').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
        <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white" name="import_csv">Import</button>
      </div>
    </form>
  </div>
</div>

</main>
<footer class="text-center text-xs text-slate-400 py-4">
  sản phẩm cuối kỳ Công nghệ Web
</footer>
</body>
</html>