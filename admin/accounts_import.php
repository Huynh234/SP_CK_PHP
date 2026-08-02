<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_role('admin');

$ket_qua = null; // ['ok'=>n, 'loi'=>[...]]

if (isset($_POST['import'])) {
  csrf_check();

  if (empty($_FILES['file_csv']['tmp_name']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'Vui lòng chọn file CSV hợp lệ.');
    redirect('/admin/accounts_import.php');
  }

  $handle = fopen($_FILES['file_csv']['tmp_name'], 'r');

  // Bỏ qua BOM UTF-8 nếu có
  $bom = fread($handle, 3);
  if ($bom !== "\xEF\xBB\xBF") rewind($handle);

  $so_dong = 0;
  $ok = 0;
  $loi = [];
  $header_skipped = false;

  while (($row = fgetcsv($handle, 0, ',')) !== false) {
    $so_dong++;
    if (count($row) < 2) continue;

    // Dòng đầu có thể là tiêu đề: username,ho_ten,...
    if (!$header_skipped && strtolower(trim($row[0])) === 'username') {
      $header_skipped = true;
      continue;
    }
    $header_skipped = true;

    $username = trim($row[0] ?? '');
    $ho_ten   = trim($row[1] ?? '');
    $email    = trim($row[2] ?? '');
    $mssv     = trim($row[3] ?? '');
    $password = trim($row[4] ?? '');
    $role     = trim($row[5] ?? '');

    if ($username === '' || $ho_ten === '') {
      $loi[] = "Dòng {$so_dong}: thiếu username hoặc họ tên.";
      continue;
    }
    if (db_query_one('SELECT id FROM users WHERE username = ?', [$username])) {
      $loi[] = "Dòng {$so_dong}: tài khoản '{$username}' đã tồn tại, bỏ qua.";
      continue;
    }

    db_exec(
      'INSERT INTO users (username, password, ho_ten, email, mssv_mgv, role) VALUES (?,?,?,?,?,?)',
      [$username, password_hash($password, PASSWORD_BCRYPT), $ho_ten, $email, $mssv, $role]
    );
    $ok++;
  }
  fclose($handle);

  $ket_qua = ['ok' => $ok, 'loi' => $loi];
}

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

    <div class="max-w-2xl">
      <a href="<?php echo BASE_URL; ?>/admin/accounts.php" class="text-sm text-brand-600 hover:underline">← Quay lại danh sách tài khoản</a>
      <h1 class="text-xl font-bold text-slate-800 mt-2 mb-1">Thêm tài khoản</h1>
      <?php
      if ($ket_qua) {
        echo '<div class="bg-white border border-slate-200 rounded-xl p-4 mb-6">';

        echo '<div class="font-semibold text-emerald-700 mb-2">✅ Đã tạo thành công '
          . $ket_qua['ok']
          . ' tài khoản.</div>';

        if ($ket_qua['loi']) {
          echo '<div class="text-rose-600 text-sm font-medium mt-3 mb-1">Một số dòng bị bỏ qua:</div>';
          echo '<ul class="text-xs text-rose-500 list-disc list-inside space-y-0.5">';
          foreach ($ket_qua['loi'] as $l) {
            echo '<li>' . $l . '</li>';
          }
          echo '</ul>';
        }

        echo '<p class="text-xs text-slate-400 mt-3">Vào lại danh sách tài khoản để xem/đặt lại mật khẩu nếu cần.</p>';
        echo '</div>';
      }
      ?>

      <form method="post" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <?php echo csrf_field(); ?>
        <div>
          <label class="block text-sm font-medium text-slate-600 mb-1">File CSV</label>
          <input type="file" name="file_csv" accept=".csv" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <button name="import" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">Import</button>
      </form>
    </div>
  </main>
  <footer class="text-center text-xs text-slate-400 py-4">
    sản phẩm cuối kỳ Công nghệ Web
  </footer>
</body>

</html>