<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_role('admin');

// ---- Thêm tài khoản đơn lẻ ----
if (isset($_POST['add_acc'])) {
  csrf_check();
  $username = trim($_POST['username']);
  $ho_ten   = trim($_POST['ho_ten']);
  $email    = trim($_POST['email']);
  $mssv     = trim($_POST['mssv_mgv']);
  $role     = $_POST['role'];
  $password = $_POST['password'] !== '' ? $_POST['password'] : random_password();

  if ($username === '' || $ho_ten === '' || !in_array($role, ['admin', 'giangvien', 'sinhvien'], true)) {
    set_flash('error', 'Vui lòng nhập đầy đủ thông tin bắt buộc.');
    redirect('/admin/accounts.php');
  }

  if (db_query_one('SELECT id FROM users WHERE username = ?', [$username])) {
    set_flash('error', "Tài khoản '{$username}' đã tồn tại.");
    redirect('/admin/accounts.php');
  }

  db_exec(
    'INSERT INTO users (username, password, ho_ten, email, mssv_mgv, role) VALUES (?,?,?,?,?,?)',
    [$username, password_hash($password, PASSWORD_BCRYPT), $ho_ten, $email, $mssv, $role]
  );

  set_flash('success', "Đã tạo tài khoản '{$username}' — mật khẩu tạm: {$password}");
  redirect('/admin/accounts.php');
}

// ---- Khoá / mở khoá ----
if (isset($_POST['toggle_lock'])) {
  csrf_check();
  $id = (int)$_POST['id'];
  db_exec("UPDATE users SET trang_thai = IF(trang_thai='active','locked','active') WHERE id=?", [$id]);
  set_flash('success', 'Đã cập nhật trạng thái tài khoản.');
  redirect('/admin/accounts.php');
}

// ---- Xoá tài khoản ----
if (isset($_POST['delete'])) {
  csrf_check();
  $id = (int)$_POST['id'];
  if ($id === (int)$_SESSION['user_id']) {
    set_flash('error', 'Không thể tự xoá chính mình.');
    redirect('/admin/accounts.php');
  }
  db_exec('DELETE FROM users WHERE id=?', [$id]);
  set_flash('success', 'Đã xoá tài khoản.');
  redirect('/admin/accounts.php');
}

$role_filter = $_GET['role'] ?? '';
$q = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM users WHERE 1=1';
$params = [];
if (in_array($role_filter, ['admin', 'giangvien', 'sinhvien'], true)) {
  $sql .= ' AND role = ?';
  $params[] = $role_filter;
}
if ($q !== '') {
  $sql .= ' AND (username LIKE ? OR ho_ten LIKE ? OR mssv_mgv LIKE ?)';
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
$sql .= ' ORDER BY role, ho_ten';
$users = db_query($sql, $params);

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
      <h1 class="text-xl font-bold text-slate-800">Quản lý tài khoản</h1>
      <div class="flex gap-2">
        <a href="<?php echo BASE_URL; ?>/admin/accounts_import.php" class="bg-white border border-slate-300 hover:border-brand-400 text-slate-700 text-sm px-4 py-2 rounded-lg transition">📥 Import từ CSV</a>
        <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg transition">+ Thêm tài khoản</button>
      </div>
    </div>

    <form method="get" class="flex flex-wrap gap-2 mb-4">
      <input type="text" name="q" value="<?php echo $q; ?>" placeholder="Tìm theo tên, username, mã số..." class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm w-64">
      <select name="role" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="">-- Tất cả vai trò --</option>
        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
        <option value="giangvien" <?php echo $role_filter === 'giangvien' ? 'selected' : ''; ?>>Giảng viên</option>
        <option value="sinhvien" <?php echo $role_filter === 'sinhvien' ? 'selected' : ''; ?>>Sinh viên</option>
      </select>
      <button class="bg-slate-200 hover:bg-slate-300 text-sm px-3 py-1.5 rounded-lg">Lọc</button>
    </form>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
          <tr>
            <th class="text-left px-4 py-2">Họ tên</th>
            <th class="text-left px-4 py-2">Tài khoản</th>
            <th class="text-left px-4 py-2">Mã số</th>
            <th class="text-left px-4 py-2">Vai trò</th>
            <th class="text-left px-4 py-2">Trạng thái</th>
            <th class="text-right px-4 py-2">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php
          foreach ($users as $u) {
            echo '<tr class="hover:bg-slate-50">';
            echo '<td class="px-4 py-2 font-medium text-slate-800">' . $u['ho_ten'] . '</td>';
            echo '<td class="px-4 py-2 text-slate-500">' . $u['username'] . '</td>';
            echo '<td class="px-4 py-2 text-slate-500">' . $u['mssv_mgv'] . '</td>';
            echo '<td class="px-4 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-brand-50 text-brand-700">' . $u['role'] . '</span></td>';

            if ($u['trang_thai'] === 'active') {
              echo '<td class="px-4 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Hoạt động</span></td>';
            } else {
              echo '<td class="px-4 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-rose-50 text-rose-700">Đã khoá</span></td>';
            }

            echo '<td class="px-4 py-2 text-right whitespace-nowrap">';
            echo '<form method="post" class="inline">'
              . csrf_field()
              . '<input type="hidden" name="id" value="' . $u['id'] . '">'
              . '<button name="toggle_lock" class="text-xs text-slate-500 hover:text-brand-600 mr-3">'
              . ($u['trang_thai'] === 'active' ? 'Khoá' : 'Mở khoá')
              . '</button>'
              . '</form>';

            echo '<form method="post" class="inline" onsubmit="return confirm(\'Xoá tài khoản này?\');">'
              . '<input type="hidden" name="id" value="' . $u['id'] . '">'
              . '<button name="delete" class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>'
              . '</form>';

            echo '</td>';
            echo '</tr>';
          }
          ?>

          <?php
          if (!$users) {
            echo '<tr>
            <td colspan="6" class="text-center text-slate-400 py-8">Không có tài khoản nào.</td>
          </tr>';
          }
          ?>

        </tbody>
      </table>
    </div>

    <!-- Modal thêm tài khoản -->
    <div id="modalAdd" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
      <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h2 class="font-bold text-slate-800 mb-4">Thêm tài khoản mới</h2>
        <form method="post" class="space-y-3">
          <?php echo csrf_field(); ?>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Họ tên *</label>
            <input name="ho_ten" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Username *</label>
              <input name="username" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Mã số SV/GV</label>
              <input name="mssv_mgv" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
            <input name="email" type="email" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Vai trò *</label>
              <select name="role" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="sinhvien">Sinh viên</option>
                <option value="giangvien">Giảng viên</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Mật khẩu</label>
              <input name="password" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
            <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white" name="add_acc" type="submit">Tạo tài khoản</button>
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