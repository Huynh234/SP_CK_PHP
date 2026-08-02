<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_role('admin');

if (isset($_POST['add_class'])) {
  csrf_check();
  $ma = trim($_POST['ma_lop']);
  $ten = trim($_POST['ten_lop']);
  $hp_id = (int)$_POST['hocphan_id'];
  $gv_id = $_POST['giangvien_id'] !== '' ? (int)$_POST['giangvien_id'] : null;
  $hoc_ky = trim($_POST['hoc_ky']);
  $si_min = (int)$_POST['si_so_nhom_toi_thieu'];
  $si_max = (int)$_POST['si_so_nhom_toi_da'];
  $han_nhom = $_POST['han_dang_ky_nhom'] !== '' ? $_POST['han_dang_ky_nhom'] : null;
  $sv_ids = $_POST['sv_id'] ?? [];

  if ($ma === '' || $ten === '' || !$hp_id) {
    set_flash('error', 'Vui lòng nhập đủ thông tin bắt buộc.');
    redirect('/admin/lop.php');
  }
  try {
    db_exec(
      'INSERT INTO lop_hocphan (ma_lop, ten_lop, hocphan_id, giangvien_id, hoc_ky, si_so_nhom_toi_thieu, si_so_nhom_toi_da, han_dang_ky_nhom)
            VALUES (?,?,?,?,?,?,?,?)',
      [$ma, $ten, $hp_id, $gv_id, $hoc_ky, $si_min, $si_max, $han_nhom]
    );
    set_flash('success', 'Đã tạo lớp học phần.');
  } catch (mysqli_sql_exception $e) {
    set_flash('error', 'Mã lớp đã tồn tại.');
  }

  if (!empty($sv_ids)) {
    try {
      $lop_id = db_last_id();
      foreach ($sv_ids as $sv_id) {
        db_exec('INSERT IGNORE INTO lop_sinhvien (lop_id, sinhvien_id) VALUES (?,?)', [$lop_id, (int)$sv_id]);
      }
    } catch (mysqli_sql_exception $e) {
      set_flash('error', 'Có lỗi khi thêm sinh viên vào lớp.');
    }
  }

  redirect('/admin/lop.php');
}

if (isset($_POST['delete_class'])) {
  csrf_check();
  db_exec('DELETE FROM lop_hocphan WHERE id=?', [(int)$_POST['id']]);
  set_flash('success', 'Đã xoá lớp học phần.');
  redirect('/admin/lop.php');
}

$hocphans = db_query('SELECT * FROM hocphan ORDER BY ma_hp');
$giangviens = db_query("SELECT * FROM users WHERE role='giangvien' ORDER BY ho_ten");

$list = db_query("
    SELECT l.*, h.ma_hp, h.ten_hp, u.ho_ten AS ten_gv,
        (SELECT COUNT(*) FROM lop_sinhvien ls WHERE ls.lop_id = l.id) AS so_sv
    FROM lop_hocphan l
    JOIN hocphan h ON h.id = l.hocphan_id
    LEFT JOIN users u ON u.id = l.giangvien_id
    ORDER BY l.created_at DESC
");

$page_title = 'Lớp học phần';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo SITE_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#eef2ff',
              100: '#e0e7ff',
              200: '#c7d2fe',
              300: '#a5b4fc',
              400: '#818cf8',
              500: '#6366f1',
              600: '#4f46e5',
              700: '#4338ca',
              800: '#3730a3',
              900: '#312e81'
            }
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Be Vietnam Pro', sans-serif;
    }
  </style>
</head>

<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

  <?php if ($user) {
    echo '<header class="bg-brand-700 text-white sticky top-0 z-30 shadow">';
    echo '<div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">';
    echo '<div class="flex items-center gap-6">';
    echo '<a href="' . BASE_URL . '/' . $user['role'] . '/dashboard.php" class="font-bold text-lg tracking-tight">';
    echo '📚 QL Nhóm & Đề tài';
    echo '</a>';
    echo '<nav class="hidden md:flex items-center gap-1 text-sm">';
    echo '<a href="' . BASE_URL . '/admin/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Tổng quan</a>';
    echo '<a href="' . BASE_URL . '/admin/accounts.php" class="px-3 py-2 rounded hover:bg-brand-600">Tài khoản</a>';
    echo '<a href="' . BASE_URL . '/admin/hocphan.php" class="px-3 py-2 rounded hover:bg-brand-600">Học phần</a>';
    echo '<a href="' . BASE_URL . '/admin/lop.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp học phần</a>';
    echo '</nav>';
    echo '</div>';
    echo '<div class="flex items-center gap-3 text-sm">';
    echo '<span class="hidden sm:inline text-brand-100">' . $user['ho_ten'] . ' · <span class="uppercase text-xs bg-brand-800 px-2 py-0.5 rounded">' . $user['role'] . '</span></span>';
    echo '<a href="' . BASE_URL . '/logout.php" class="bg-brand-800 hover:bg-brand-900 px-3 py-1.5 rounded transition">Đăng xuất</a>';
    echo '</div>';
    echo '</div>';
    echo '</header>';
  } ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
    <?php $flash = get_flash();
    if ($flash) {
      echo '<div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium
      ' . ($flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200') . '">';
      echo $flash['message'] . '</div>';
    }
    ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <h1 class="text-xl font-bold text-slate-800">Lớp học phần</h1>
      <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">+ Tạo lớp học phần</button>
    </div>

    <div class="grid gap-4">
      <?php
      foreach ($list as $l) {
        echo '<div class="bg-white border border-slate-200 rounded-xl p-5 flex flex-wrap items-center justify-between gap-3">';
        echo '<div>';
        echo '<div class="flex items-center gap-2">';
        echo '<span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded">' . $l['ma_lop'] . '</span>';
        echo '<span class="font-semibold text-slate-800">' . $l['ten_lop'] . '</span>';
        echo '</div>';
        echo '<div class="text-xs text-slate-500 mt-1">'
          . $l['ma_hp'] . ' — ' . $l['ten_hp']
          . ' · ' . ($l['hoc_ky'] ?: 'Chưa đặt học kỳ')
          . ' · GV: ' . ($l['ten_gv'] ?: 'Chưa gán')
          . ' · ' . $l['so_sv'] . ' sinh viên'
          . '</div>';
        echo '<div class="text-xs text-slate-400 mt-1">'
          . 'Hạn ĐK nhóm: ' . format_datetime($l['han_dang_ky_nhom'])
          . ' · Sĩ số nhóm: ' . $l['si_so_nhom_toi_thieu'] . '–' . $l['si_so_nhom_toi_da']
          . '</div>';
        echo '</div>';
        echo '<div class="flex items-center gap-2">';
        echo '<form method="post">';
        echo csrf_field();
        echo '<input type="hidden" name="id" value="' . $l['id'] . '">';
        echo '<button name="delete_class" class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
      }
      ?>
    </div>

    <!-- Modal tạo lớp -->
    <div id="modalAdd" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
      <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h2 class="font-bold text-slate-800 mb-4">Tạo lớp học phần mới</h2>
        <form method="post" class="space-y-3">
          <?php echo csrf_field() ?>
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
                <?php
                foreach ($hocphans as $hp) {
                  echo '<option value="' . $hp['id'] . '">'
                    . $hp['ma_hp'] . ' - ' . $hp['ten_hp']
                    . '</option>';
                }
                ?>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Giảng viên phụ trách</label>
              <select name="giangvien_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="">-- chưa gán --</option>
                <?php
                foreach ($giangviens as $gv) {
                  echo '<option value="' . $gv['id'] . '">'
                    . $gv['ho_ten'] . ' (' . $gv['email'] . ')'
                    . '</option>';
                }
                ?>
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
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký nhóm</label>
            <input name="han_dang_ky_nhom" type="datetime-local" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <h2 class="font-semibold text-slate-800 mb-3 text-sm">Thêm 1 sinh viên</h2>
          <select name="sv_id[]" multiple class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="">-- chọn sinh viên --</option>
            <?php
            $svAvailable = db_query("SELECT * FROM users WHERE role='sinhvien' ORDER BY ho_ten");
            foreach ($svAvailable as $sv) {
              echo '<option value="' . $sv['id'] . '">'
                . $sv['ho_ten'] . ' (' . $sv['mssv_mgv'] . ')'
                . '</option>';
            }
            ?>
          </select>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
            <button name="add_class" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white">Tạo lớp</button>
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