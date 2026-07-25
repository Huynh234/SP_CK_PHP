<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
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

$page_title = 'Import tài khoản từ CSV';
include '../includes/header.php';
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

<?php include '../includes/footer.php'; ?>