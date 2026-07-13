<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $ma = trim($_POST['ma_hp']);
    $ten = trim($_POST['ten_hp']);
    $tc = (int)$_POST['so_tin_chi'];
    if ($ma === '' || $ten === '') {
        set_flash('error', 'Vui lòng nhập đủ mã và tên học phần.');
        redirect('/admin/hocphan.php');
    }
    try {
        $pdo->prepare('INSERT INTO hocphan (ma_hp, ten_hp, so_tin_chi) VALUES (?,?,?)')->execute([$ma, $ten, $tc ?: 3]);
        set_flash('success', 'Đã thêm học phần.');
    } catch (PDOException $e) {
        set_flash('error', 'Mã học phần đã tồn tại.');
    }
    redirect('/admin/hocphan.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $pdo->prepare('DELETE FROM hocphan WHERE id=?')->execute([(int)$_POST['id']]);
    set_flash('success', 'Đã xoá học phần.');
    redirect('/admin/hocphan.php');
}

$list = $pdo->query('SELECT * FROM hocphan ORDER BY ma_hp')->fetchAll();

$page_title = 'Học phần';
include __DIR__ . '/../includes/header.php';
?>
<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-bold text-slate-800">Quản lý học phần</h1>
</div>

<div class="grid md:grid-cols-3 gap-6">
  <div class="md:col-span-2 bg-white border border-slate-200 rounded-xl overflow-hidden h-fit">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr><th class="text-left px-4 py-2">Mã HP</th><th class="text-left px-4 py-2">Tên học phần</th><th class="text-left px-4 py-2">Tín chỉ</th><th class="px-4 py-2"></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($list as $hp): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2 font-mono text-brand-700"><?= e($hp['ma_hp']) ?></td>
          <td class="px-4 py-2"><?= e($hp['ten_hp']) ?></td>
          <td class="px-4 py-2"><?= (int)$hp['so_tin_chi'] ?></td>
          <td class="px-4 py-2 text-right">
            <form method="post" onsubmit="return confirm('Xoá học phần này? (sẽ xoá luôn các lớp thuộc học phần)');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $hp['id'] ?>">
              <button class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="4" class="text-center text-slate-400 py-8">Chưa có học phần nào.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-5 h-fit">
    <h2 class="font-semibold text-slate-800 mb-3 text-sm">+ Thêm học phần mới</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Mã học phần *</label>
        <input name="ma_hp" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="VD: CNW101">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Tên học phần *</label>
        <input name="ten_hp" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="VD: Công nghệ Web">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Số tín chỉ</label>
        <input name="so_tin_chi" type="number" value="3" min="1" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <button class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">Thêm</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
