<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$ket_qua = null; // ['ok'=>n, 'loi'=>[...]]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_FILES['file_csv']['tmp_name']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Vui lòng chọn file CSV hợp lệ.');
        redirect('/admin/accounts_import.php');
    }

    $default_role = $_POST['default_role'] ?? 'sinhvien';
    $handle = fopen($_FILES['file_csv']['tmp_name'], 'r');

    // Bỏ qua BOM UTF-8 nếu có
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $so_dong = 0;
    $ok = 0;
    $loi = [];
    $header_skipped = false;

    $insert = $pdo->prepare('INSERT INTO users (username, password, ho_ten, email, mssv_mgv, role) VALUES (?,?,?,?,?,?)');
    $check  = $pdo->prepare('SELECT id FROM users WHERE username = ?');

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
        $role     = trim($row[4] ?? '') ?: $default_role;
        if (!in_array($role, ['admin','giangvien','sinhvien'], true)) $role = $default_role;

        if ($username === '' || $ho_ten === '') {
            $loi[] = "Dòng {$so_dong}: thiếu username hoặc họ tên.";
            continue;
        }
        $check->execute([$username]);
        if ($check->fetch()) {
            $loi[] = "Dòng {$so_dong}: tài khoản '{$username}' đã tồn tại, bỏ qua.";
            continue;
        }

        $password = random_password();
        $insert->execute([$username, password_hash($password, PASSWORD_BCRYPT), $ho_ten, $email, $mssv, $role]);
        $ok++;
    }
    fclose($handle);

    $ket_qua = ['ok' => $ok, 'loi' => $loi];
}

$page_title = 'Import tài khoản từ CSV';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl">
  <a href="<?= BASE_URL ?>/admin/accounts.php" class="text-sm text-brand-600 hover:underline">← Quay lại danh sách tài khoản</a>
  <h1 class="text-xl font-bold text-slate-800 mt-2 mb-1">Import tài khoản hàng loạt</h1>
  <p class="text-sm text-slate-500 mb-6">
    Chuẩn bị file CSV (xuất từ Excel: File → Save As → CSV UTF-8) theo cột:
    <code class="bg-slate-100 px-1 rounded">username, ho_ten, email, mssv_mgv, role</code>.
    Cột <code class="bg-slate-100 px-1 rounded">role</code> có thể để trống, hệ thống sẽ dùng vai trò mặc định bên dưới.
    Mật khẩu sẽ được sinh ngẫu nhiên cho từng tài khoản.
  </p>

  <?php if ($ket_qua): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-6">
      <div class="font-semibold text-emerald-700 mb-2">✅ Đã tạo thành công <?= $ket_qua['ok'] ?> tài khoản.</div>
      <?php if ($ket_qua['loi']): ?>
        <div class="text-rose-600 text-sm font-medium mt-3 mb-1">Một số dòng bị bỏ qua:</div>
        <ul class="text-xs text-rose-500 list-disc list-inside space-y-0.5">
          <?php foreach ($ket_qua['loi'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="text-xs text-slate-400 mt-3">Vào lại danh sách tài khoản để xem/đặt lại mật khẩu nếu cần.</p>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium text-slate-600 mb-1">File CSV</label>
      <input type="file" name="file_csv" accept=".csv" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-600 mb-1">Vai trò mặc định (nếu file không có cột role)</label>
      <select name="default_role" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="sinhvien">Sinh viên</option>
        <option value="giangvien">Giảng viên</option>
      </select>
    </div>
    <button class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">Import</button>
  </form>

  <div class="mt-6 text-xs text-slate-400">
    <p class="font-medium mb-1">Ví dụ nội dung file CSV:</p>
    <pre class="bg-slate-100 rounded-lg p-3 overflow-x-auto">username,ho_ten,email,mssv_mgv,role
sv.duong,Nguyễn Văn Dương,duong.nv@truong.edu.vn,SV004,sinhvien
sv.em,Vũ Thị Em,em.vt@truong.edu.vn,SV005,sinhvien</pre>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
