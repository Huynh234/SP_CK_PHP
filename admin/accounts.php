<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

// ---- Thêm tài khoản đơn lẻ ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $username = trim($_POST['username']);
    $ho_ten   = trim($_POST['ho_ten']);
    $email    = trim($_POST['email']);
    $mssv     = trim($_POST['mssv_mgv']);
    $role     = $_POST['role'];
    $password = $_POST['password'] !== '' ? $_POST['password'] : random_password();

    if ($username === '' || $ho_ten === '' || !in_array($role, ['admin','giangvien','sinhvien'], true)) {
        set_flash('error', 'Vui lòng nhập đầy đủ thông tin bắt buộc.');
        redirect('/admin/accounts.php');
    }

    if (db_query_one('SELECT id FROM users WHERE username = ?', [$username])) {
        set_flash('error', "Tài khoản '{$username}' đã tồn tại.");
        redirect('/admin/accounts.php');
    }

    db_exec('INSERT INTO users (username, password, ho_ten, email, mssv_mgv, role) VALUES (?,?,?,?,?,?)',
        [$username, password_hash($password, PASSWORD_BCRYPT), $ho_ten, $email, $mssv, $role]);

    set_flash('success', "Đã tạo tài khoản '{$username}' — mật khẩu tạm: {$password}");
    redirect('/admin/accounts.php');
}

// ---- Khoá / mở khoá ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_lock') {
    csrf_check();
    $id = (int)$_POST['id'];
    db_exec("UPDATE users SET trang_thai = IF(trang_thai='active','locked','active') WHERE id=?", [$id]);
    set_flash('success', 'Đã cập nhật trạng thái tài khoản.');
    redirect('/admin/accounts.php');
}

// ---- Xoá tài khoản ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
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
if (in_array($role_filter, ['admin','giangvien','sinhvien'], true)) {
    $sql .= ' AND role = ?';
    $params[] = $role_filter;
}
if ($q !== '') {
    $sql .= ' AND (username LIKE ? OR ho_ten LIKE ? OR mssv_mgv LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$sql .= ' ORDER BY role, ho_ten';
$users = db_query($sql, $params);

$page_title = 'Quản lý tài khoản';
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <h1 class="text-xl font-bold text-slate-800">Quản lý tài khoản</h1>
  <div class="flex gap-2">
    <a href="<?= BASE_URL ?>/admin/accounts_import.php" class="bg-white border border-slate-300 hover:border-brand-400 text-slate-700 text-sm px-4 py-2 rounded-lg transition">📥 Import từ CSV</a>
    <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg transition">+ Thêm tài khoản</button>
  </div>
</div>

<form method="get" class="flex flex-wrap gap-2 mb-4">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên, username, mã số..." class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm w-64">
  <select name="role" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
    <option value="">-- Tất cả vai trò --</option>
    <option value="admin" <?= $role_filter==='admin'?'selected':'' ?>>Admin</option>
    <option value="giangvien" <?= $role_filter==='giangvien'?'selected':'' ?>>Giảng viên</option>
    <option value="sinhvien" <?= $role_filter==='sinhvien'?'selected':'' ?>>Sinh viên</option>
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
      <?php foreach ($users as $u): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-2 font-medium text-slate-800"><?= e($u['ho_ten']) ?></td>
        <td class="px-4 py-2 text-slate-500"><?= e($u['username']) ?></td>
        <td class="px-4 py-2 text-slate-500"><?= e($u['mssv_mgv']) ?></td>
        <td class="px-4 py-2">
          <span class="text-xs px-2 py-0.5 rounded-full bg-brand-50 text-brand-700"><?= e($u['role']) ?></span>
        </td>
        <td class="px-4 py-2">
          <?php if ($u['trang_thai'] === 'active'): ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Hoạt động</span>
          <?php else: ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-rose-50 text-rose-700">Đã khoá</span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-2 text-right whitespace-nowrap">
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_lock">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="text-xs text-slate-500 hover:text-brand-600 mr-3"><?= $u['trang_thai']==='active' ? 'Khoá' : 'Mở khoá' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('Xoá tài khoản này?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="6" class="text-center text-slate-400 py-8">Không có tài khoản nào.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal thêm tài khoản -->
<div id="modalAdd" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
  <div class="bg-white rounded-xl p-6 w-full max-w-md">
    <h2 class="font-bold text-slate-800 mb-4">Thêm tài khoản mới</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Họ tên *</label>
        <input name="ho_ten" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tài khoản (username) *</label>
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
          <label class="block text-xs font-medium text-slate-600 mb-1">Mật khẩu (để trống = tự sinh)</label>
          <input name="password" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
        <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white">Tạo tài khoản</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
