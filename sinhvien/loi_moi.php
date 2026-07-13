<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tv_id = (int)$_POST['tv_id'];
    $action = $_POST['action'];

    $row = $pdo->prepare("
        SELECT tv.*, n.lop_id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
        WHERE tv.id=? AND tv.sinhvien_id=? AND tv.trang_thai='cho_xac_nhan'
    ");
    $row->execute([$tv_id, $sv_id]);
    $row = $row->fetch();

    if ($row) {
        if ($action === 'chap_nhan') {
            // Kiểm tra chưa có nhóm đã xác nhận nào khác trong lớp
            $daCo = $pdo->prepare("
                SELECT tv2.id FROM thanhvien_nhom tv2 JOIN nhom n2 ON n2.id=tv2.nhom_id
                WHERE tv2.sinhvien_id=? AND tv2.trang_thai='da_xac_nhan' AND n2.lop_id=?
            ");
            $daCo->execute([$sv_id, $row['lop_id']]);
            if ($daCo->fetch()) {
                set_flash('error', 'Bạn đã có nhóm khác trong lớp này rồi, không thể tham gia thêm.');
            } else {
                $pdo->prepare("UPDATE thanhvien_nhom SET trang_thai='da_xac_nhan' WHERE id=?")->execute([$tv_id]);
                // Tự động huỷ các lời mời khác đang chờ trong cùng lớp
                $pdo->prepare("
                    DELETE tv3 FROM thanhvien_nhom tv3 JOIN nhom n3 ON n3.id=tv3.nhom_id
                    WHERE tv3.sinhvien_id=? AND tv3.trang_thai='cho_xac_nhan' AND n3.lop_id=?
                ")->execute([$sv_id, $row['lop_id']]);
                set_flash('success', 'Đã tham gia nhóm!');
            }
        } elseif ($action === 'tu_choi') {
            $pdo->prepare("DELETE FROM thanhvien_nhom WHERE id=?")->execute([$tv_id]);
            set_flash('success', 'Đã từ chối lời mời.');
        }
    }
    redirect('/sinhvien/loi_moi.php');
}

$list = $pdo->prepare("
    SELECT tv.id AS tv_id, n.id AS nhom_id, n.ten_nhom, l.ma_lop, l.ten_lop, u.ho_ten AS ten_truong_nhom
    FROM thanhvien_nhom tv
    JOIN nhom n ON n.id = tv.nhom_id
    JOIN lop_hocphan l ON l.id = n.lop_id
    JOIN users u ON u.id = n.truong_nhom_id
    WHERE tv.sinhvien_id = ? AND tv.trang_thai = 'cho_xac_nhan'
    ORDER BY tv.created_at DESC
");
$list->execute([$sv_id]);
$list = $list->fetchAll();

$page_title = 'Lời mời nhóm';
include __DIR__ . '/../includes/header.php';
?>
<h1 class="text-xl font-bold text-slate-800 mb-6">Lời mời vào nhóm</h1>

<div class="grid gap-4 max-w-xl">
  <?php foreach ($list as $it): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center justify-between gap-3">
    <div>
      <div class="font-semibold text-slate-800"><?= e($it['ten_nhom']) ?></div>
      <div class="text-xs text-slate-500"><?= e($it['ma_lop']) ?> - <?= e($it['ten_lop']) ?> · Trưởng nhóm: <?= e($it['ten_truong_nhom']) ?></div>
    </div>
    <div class="flex gap-2 shrink-0">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="tv_id" value="<?= $it['tv_id'] ?>"><input type="hidden" name="action" value="chap_nhan">
        <button class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg">Chấp nhận</button>
      </form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="tv_id" value="<?= $it['tv_id'] ?>"><input type="hidden" name="action" value="tu_choi">
        <button class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-lg">Từ chối</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$list): ?><div class="text-center text-slate-400 py-12">Bạn không có lời mời nào đang chờ.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
