<?php
require_once './config/config.php';
require_once './config/database.php';
require_once './includes/auth.php';
require_once './includes/functions.php';

if (dang_nhap()) {
  redirect('/' . $_SESSION['role'] . '/dashboard.php');
}

if (isset($_POST['btn_login'])) {
  csrf_check();
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  $u = db_query_one('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);

  if (!$u || !password_verify($password, $u['password'])) {
    set_flash('error', 'Sai tài khoản hoặc mật khẩu.');
    redirect('/index.php');
  }
  if ($u['trang_thai'] === 'locked') {
    set_flash('error', 'Tài khoản của bạn đã bị khoá. Liên hệ quản trị viên.');
    redirect('/index.php');
  }

  $_SESSION['user_id']  = $u['id'];
  $_SESSION['username'] = $u['username'];
  $_SESSION['ho_ten']   = $u['ho_ten'];
  $_SESSION['role']     = $u['role'];

  redirect('/' . $u['role'] . '/dashboard.php');
}
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
              50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81'
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
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
    <?php $flash = get_flash();
    if ($flash) {
      echo '<div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium
      ' . ($flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200') . '">';
      echo $flash['message'] . '</div>';
    }
    ?>
    <div class="min-h-[75vh] flex items-center justify-center -mt-6">
      <div class="w-full max-w-sm">
        <div class="text-center mb-8">
          <div class="text-4xl mb-2">📚</div>
          <h1 class="text-xl font-bold text-slate-800"><?= SITE_NAME ?></h1>
          <p class="text-sm text-slate-500 mt-1">Đăng nhập để tiếp tục</p>
        </div>
        <form method="post" class="bg-white shadow-sm border border-slate-200 rounded-xl p-6 space-y-4">
          <?= csrf_field() ?>
          <div>
            <label class="block text-sm font-medium text-slate-600 mb-1">Tài khoản</label>
            <input type="text" name="username" required autofocus
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-600 mb-1">Mật khẩu</label>
            <input type="password" name="password" required
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
          </div>
          <button type="submit" name="btn_login"
            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-lg py-2.5 text-sm transition">
            Đăng nhập
          </button>
          <p class="text-xs text-slate-400 text-center pt-2">
            Tài khoản demo: <b>admin / gv.hoang / sv.an</b> — mật khẩu: <b>123456</b>
          </p>
        </form>
      </div>
    </div>

  </main>
  <footer class="text-center text-xs text-slate-400 py-4">
    sản phẩm cuối kỳ Công nghệ Web
  </footer>
</body>

</html>