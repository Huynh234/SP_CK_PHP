<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('giangvien');
$gv_id = $_SESSION['user_id'];

$lop_id = (int)($_GET['id'] ?? 0);
$lop = db_query_one('SELECT * FROM lop_hocphan WHERE id=? AND giangvien_id=?', [$lop_id, $gv_id]);
if (!$lop) {
  set_flash('error', 'Không tìm thấy lớp.');
  redirect('/giangvien/dashboard.php');
}

// Xử lý duyệt / từ chối / yêu cầu điều chỉnh
if (isset($_POST['action'])) {
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
    echo '<a href="' . BASE_URL . '/giangvien/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp của tôi</a>';
    echo '<a href="' . BASE_URL . '/giangvien/detai.php" class="px-3 py-2 rounded hover:bg-brand-600">Ngân hàng đề tài</a>';
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
    <a href="<?php echo BASE_URL ?>/giangvien/lop.php?id=<?php echo $lop_id ?>" class="text-sm text-brand-600 hover:underline">← <?php echo $lop['ma_lop'] ?></a>
    <h1 class="text-xl font-bold text-slate-800 mt-2 mb-6">Duyệt đăng ký đề tài</h1>

    <?php
    echo '<div class="grid gap-4">';

    foreach ($list as $dk) {
      $mau = [
        'cho_duyet' => 'bg-amber-50 text-amber-700',
        'da_duyet' => 'bg-emerald-50 text-emerald-700',
        'tu_choi' => 'bg-rose-50 text-rose-700',
        'yeu_cau_dieu_chinh' => 'bg-sky-50 text-sky-700'
      ][$dk['trang_thai']];

      echo '<div class="bg-white border border-slate-200 rounded-xl p-5">';
      echo '<div class="flex flex-wrap items-start justify-between gap-3">';
      echo '<div>';
      echo '<div class="font-semibold text-slate-800">';
      echo $dk['ten_nhom'] . ' → ' . $dk['ten_detai'];
      echo '<span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full ml-1">' . $dk['ten_dot'] . '</span>';
      if ($dk['nguon'] === 'sinhvien') {
        echo '<span class="text-xs bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full ml-1">SV tự đề xuất</span>';
      }
      if ($dk['la_random']) {
        echo '<span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full ml-1">random</span>';
      }
      echo '</div>';
      echo '<p class="text-sm text-slate-500 mt-1">' . nl2br($dk['mo_ta']) . '</p>';
      if ($dk['phan_hoi']) {
        echo '<p class="text-xs text-slate-400 mt-2 italic">Phản hồi trước: "' . $dk['phan_hoi'] . '"</p>';
      }
      echo '</div>';
      echo '<span class="text-xs px-2 py-0.5 rounded-full ' . $mau . ' whitespace-nowrap">' . str_replace('_', ' ', $dk['trang_thai']) . '</span>';
      echo '</div>';

      if ($dk['trang_thai'] === 'cho_duyet' || $dk['trang_thai'] === 'yeu_cau_dieu_chinh') {
        echo '<form method="post" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">';
        echo csrf_field();
        echo '<input type="hidden" name="dangky_id" value="' . $dk['id'] . '">';
        echo '<div class="flex-1 min-w-[200px]">';
        echo '<label class="block text-xs font-medium text-slate-600 mb-1">Phản hồi (tuỳ chọn)</label>';
        echo '<input name="phan_hoi" class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm">';
        echo '</div>';
        echo '<button name="action" value="duyet" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg">✓ Duyệt</button>';
        echo '<button name="action" value="dieu_chinh" class="text-xs bg-sky-500 hover:bg-sky-600 text-white px-3 py-2 rounded-lg">✎ Yêu cầu điều chỉnh</button>';
        echo '<button name="action" value="tu_choi" class="text-xs bg-rose-500 hover:bg-rose-600 text-white px-3 py-2 rounded-lg">✕ Từ chối</button>';
        echo '</form>';
      }

      echo '</div>';
    }

    if (!$list) {
      echo '<div class="text-center text-slate-400 py-12">Chưa có đăng ký đề tài nào trong lớp này.</div>';
    }

    echo '</div>';
    ?>


  </main>
  <footer class="text-center text-xs text-slate-400 py-4">
    sản phẩm cuối kỳ Công nghệ Web
  </footer>
</body>

</html>