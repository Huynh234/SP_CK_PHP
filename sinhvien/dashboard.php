<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$lops = db_query("
    SELECT l.*, h.ma_hp, h.ten_hp, u.ho_ten AS ten_gv,
      (SELECT n.id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
       WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=l.id LIMIT 1) AS nhom_id
    FROM lop_hocphan l
    JOIN hocphan h ON h.id = l.hocphan_id
    LEFT JOIN users u ON u.id = l.giangvien_id
    JOIN lop_sinhvien ls ON ls.lop_id = l.id AND ls.sinhvien_id = ?
    ORDER BY l.created_at DESC
", [$sv_id, $sv_id]);

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
    echo '<a href="' . BASE_URL . '/sinhvien/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp của tôi</a>';
    echo '<a href="' . BASE_URL . '/sinhvien/loi_moi.php" class="px-3 py-2 rounded hover:bg-brand-600">Lời mời nhóm</a>';
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
    <h1 class="text-xl font-bold text-slate-800 mb-1">Chào, <?php echo $_SESSION['ho_ten'] ?> 👋</h1>
    <p class="text-sm text-slate-500 mb-6">Các lớp học phần bạn đang tham gia.</p>

    <div class="grid md:grid-cols-2 gap-4">
      <?php
      foreach ($lops as $l) {
        echo '<a href="' . BASE_URL . '/sinhvien/lop.php?id=' . $l['id'] . '" 
             class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-brand-400 hover:shadow-sm transition">';

        echo '<div class="flex items-center gap-2 mb-1">';
        echo '<span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded">' . $l['ma_lop'] . '</span>';
        echo '<span class="font-semibold text-slate-800">' . $l['ten_lop'] . '</span>';
        echo '</div>';

        echo '<div class="text-xs text-slate-500">'
          . $l['ma_hp'] . ' — ' . $l['ten_hp']
          . ' · GV: ' . $l['ten_gv'] ?: 'chưa gán'
          . '</div>';

        echo '<div class="mt-3">';
        echo $l['nhom_id']
          ? '<span class="text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">✓ Đã có nhóm</span>'
          : '<span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">Chưa có nhóm</span>';
        echo '</div>';
        echo '</div>';
        echo '</a>';
      } 
      ?>
      <?php
      if (!$lops) {
        echo '<div class="text-slate-400 text-sm">Bạn chưa được thêm vào lớp học phần nào.</div>';
      }
      ?>

    </div>

  </main>
  <footer class="text-center text-xs text-slate-400 py-4">
    sản phẩm cuối kỳ Công nghệ Web
  </footer>
</body>

</html>