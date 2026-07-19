<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (dang_nhap()) {
    redirect('/' . $_SESSION['role'] . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

$page_title = 'Đăng nhập';
include __DIR__ . '/includes/header.php';
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
      <button type="submit"
        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-lg py-2.5 text-sm transition">
        Đăng nhập
      </button>
      <p class="text-xs text-slate-400 text-center pt-2">
        Tài khoản demo: <b>admin / gv.hoang / sv.an</b> — mật khẩu: <b>123456</b>
      </p>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
