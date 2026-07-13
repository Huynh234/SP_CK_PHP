<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT l.*, h.ma_hp, h.ten_hp FROM lop_hocphan l
    JOIN hocphan h ON h.id=l.hocphan_id
    JOIN lop_sinhvien ls ON ls.lop_id=l.id AND ls.sinhvien_id=?
    WHERE l.id=?
");
$stmt->execute([$sv_id, $id]);
$lop = $stmt->fetch();
if (!$lop) { set_flash('error', 'Không tìm thấy lớp hoặc bạn không thuộc lớp này.'); redirect('/sinhvien/dashboard.php'); }

// Tạo nhóm mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tao_nhom') {
    csrf_check();

    if (is_qua_han($lop['han_dang_ky_nhom'])) {
        set_flash('error', 'Đã hết hạn đăng ký nhóm.');
        redirect('/sinhvien/lop.php?id=' . $id);
    }
    // Kiểm tra đã có nhóm trong lớp chưa
    $chk = $pdo->prepare("
        SELECT n.id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
        WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
    ");
    $chk->execute([$sv_id, $id]);
    if ($chk->fetch()) {
        set_flash('error', 'Bạn đã ở trong một nhóm của lớp này rồi.');
        redirect('/sinhvien/lop.php?id=' . $id);
    }

    $ten = trim($_POST['ten_nhom']) ?: ($_SESSION['ho_ten'] . "'s Group");
    $pdo->prepare("INSERT INTO nhom (lop_id, ten_nhom, truong_nhom_id, nguon_tao) VALUES (?,?,?,'sinhvien')")
        ->execute([$id, $ten, $sv_id]);
    $nhomId = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO thanhvien_nhom (nhom_id, sinhvien_id, trang_thai) VALUES (?,?,'da_xac_nhan')")
        ->execute([$nhomId, $sv_id]);

    set_flash('success', 'Đã tạo nhóm. Hãy mời thêm thành viên!');
    redirect('/sinhvien/nhom.php?id=' . $nhomId);
}

// Nhóm hiện tại của sinh viên trong lớp này (nếu có)
$myGroup = $pdo->prepare("
    SELECT n.* FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
    WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
");
$myGroup->execute([$sv_id, $id]);
$myGroup = $myGroup->fetch();

if ($myGroup) {
    redirect('/sinhvien/nhom.php?id=' . $myGroup['id']);
}

$page_title = 'Chi tiết lớp';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/sinhvien/dashboard.php" class="text-sm text-brand-600 hover:underline">← Lớp của tôi</a>
<div class="flex items-center gap-2 mt-2 mb-6">
  <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?= e($lop['ma_lop']) ?></span>
  <h1 class="text-xl font-bold text-slate-800"><?= e($lop['ten_lop']) ?></h1>
</div>

<div class="max-w-md">
  <?php if (is_qua_han($lop['han_dang_ky_nhom'])): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl p-4">
      Đã hết hạn đăng ký nhóm (<?= format_datetime($lop['han_dang_ky_nhom']) ?>) và bạn chưa có nhóm.
      Hệ thống sẽ tự động xếp bạn vào một nhóm khi giảng viên chạy random.
    </div>
  <?php else: ?>
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-1">Bạn chưa có nhóm trong lớp này</h2>
      <p class="text-xs text-slate-500 mb-4">Hạn đăng ký nhóm: <?= format_datetime($lop['han_dang_ky_nhom']) ?></p>
      <form method="post" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="tao_nhom">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tên nhóm</label>
          <input name="ten_nhom" placeholder="VD: Nhóm Web Warriors" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm px-4 py-2 rounded-lg">Tạo nhóm & làm trưởng nhóm</button>
      </form>
      <p class="text-xs text-slate-400 mt-3">Hoặc chờ bạn cùng lớp mời bạn vào nhóm của họ — kiểm tra ở mục "Lời mời nhóm" trên thanh menu.</p>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
