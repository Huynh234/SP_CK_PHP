<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$nhom = db_query_one("
    SELECT n.*, l.ma_lop, l.ten_lop, l.id AS lop_id, l.si_so_nhom_toi_da, l.han_dang_ky_nhom
    FROM nhom n JOIN lop_hocphan l ON l.id = n.lop_id WHERE n.id=?
", [$id]);
if (!$nhom) { set_flash('error', 'Không tìm thấy nhóm.'); redirect('/sinhvien/dashboard.php'); }

// Xác nhận sinh viên có thuộc nhóm này (đã xác nhận)
$myRow = db_query_one('SELECT * FROM thanhvien_nhom WHERE nhom_id=? AND sinhvien_id=?', [$id, $sv_id]);
if (!$myRow || $myRow['trang_thai'] !== 'da_xac_nhan') {
    set_flash('error', 'Bạn không thuộc nhóm này.');
    redirect('/sinhvien/lop.php?id=' . $nhom['lop_id']);
}
$isLeader = ($nhom['truong_nhom_id'] == $sv_id);

// Mời thành viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'moi') {
    csrf_check();
    if (!$isLeader) { set_flash('error', 'Chỉ trưởng nhóm mới được mời thành viên.'); redirect('/sinhvien/nhom.php?id=' . $id); }
    if (is_qua_han($nhom['han_dang_ky_nhom'])) { set_flash('error', 'Đã hết hạn đăng ký nhóm, không thể mời thêm.'); redirect('/sinhvien/nhom.php?id=' . $id); }

    $svMoiId = (int)$_POST['sinhvien_id'];

    $slot = (int)db_value("SELECT COUNT(*) FROM thanhvien_nhom WHERE nhom_id=? AND trang_thai IN ('da_xac_nhan','cho_xac_nhan')", [$id]);
    if ($slot >= $nhom['si_so_nhom_toi_da']) {
        set_flash('error', 'Nhóm đã đạt số lượng thành viên tối đa (kể cả lời mời đang chờ).');
        redirect('/sinhvien/nhom.php?id=' . $id);
    }
    $daCoNhom = db_query_one("
        SELECT tv.id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
        WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
    ", [$svMoiId, $nhom['lop_id']]);
    if ($daCoNhom) {
        set_flash('error', 'Sinh viên này đã ở trong một nhóm khác của lớp.');
        redirect('/sinhvien/nhom.php?id=' . $id);
    }

    try {
        db_exec("INSERT INTO thanhvien_nhom (nhom_id, sinhvien_id, trang_thai) VALUES (?,?,'cho_xac_nhan')", [$id, $svMoiId]);
        set_flash('success', 'Đã gửi lời mời. Chờ sinh viên chấp nhận.');
    } catch (mysqli_sql_exception $e) {
        set_flash('error', 'Sinh viên này đã được mời trước đó.');
    }
    redirect('/sinhvien/nhom.php?id=' . $id);
}

// Huỷ lời mời (trưởng nhóm)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'huy_moi') {
    csrf_check();
    if ($isLeader) {
        db_exec("DELETE FROM thanhvien_nhom WHERE nhom_id=? AND sinhvien_id=? AND trang_thai='cho_xac_nhan'", [$id, (int)$_POST['sinhvien_id']]);
        set_flash('success', 'Đã huỷ lời mời.');
    }
    redirect('/sinhvien/nhom.php?id=' . $id);
}

// Danh sách thành viên
$thanhvien = db_query('SELECT tv.*, u.ho_ten, u.mssv_mgv FROM thanhvien_nhom tv JOIN users u ON u.id=tv.sinhvien_id WHERE tv.nhom_id=? ORDER BY tv.trang_thai, u.ho_ten', [$id]);

// Bạn cùng lớp có thể mời
$idsHienCo = array_column($thanhvien, 'sinhvien_id');
if ($idsHienCo) {
    $ph = implode(',', array_fill(0, count($idsHienCo), '?'));
    $avail = db_query("
        SELECT u.id, u.ho_ten, u.mssv_mgv FROM users u
        JOIN lop_sinhvien ls ON ls.sinhvien_id = u.id AND ls.lop_id = ?
        WHERE u.id NOT IN ($ph)
        AND u.id NOT IN (
            SELECT tv2.sinhvien_id FROM thanhvien_nhom tv2 JOIN nhom n2 ON n2.id=tv2.nhom_id
            WHERE n2.lop_id = ? AND tv2.trang_thai='da_xac_nhan'
        )
        ORDER BY u.ho_ten
    ", array_merge([$nhom['lop_id']], $idsHienCo, [$nhom['lop_id']]));
} else {
    $avail = db_query("
        SELECT u.id, u.ho_ten, u.mssv_mgv FROM users u
        JOIN lop_sinhvien ls ON ls.sinhvien_id = u.id AND ls.lop_id = ?
        ORDER BY u.ho_ten
    ", [$nhom['lop_id']]);
}

// Các đợt đăng ký đề tài của lớp + đăng ký hiện tại của nhóm cho mỗi đợt
$dots = db_query('SELECT * FROM dot_dangky WHERE lop_id=? ORDER BY created_at', [$nhom['lop_id']]);
$mucDichLabel = ['giua_ky' => 'Giữa kỳ', 'cuoi_ky' => 'Cuối kỳ', 'khac' => 'Khác'];
$trangThaiLabel = ['cho_duyet'=>'chờ duyệt','da_duyet'=>'đã duyệt','tu_choi'=>'từ chối','yeu_cau_dieu_chinh'=>'yêu cầu điều chỉnh'];
$trangThaiMau = ['cho_duyet'=>'bg-amber-50 text-amber-700','da_duyet'=>'bg-emerald-50 text-emerald-700','tu_choi'=>'bg-rose-50 text-rose-700','yeu_cau_dieu_chinh'=>'bg-sky-50 text-sky-700'];
foreach ($dots as &$dot) {
    $dot['dangky'] = db_query_one('SELECT dk.*, d.ten_detai, d.mo_ta FROM dangky_detai dk JOIN detai d ON d.id=dk.detai_id WHERE dk.nhom_id=? AND dk.dot_id=?', [$id, $dot['id']]);
}
unset($dot);

$page_title = 'Nhóm của tôi';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/sinhvien/dashboard.php" class="text-sm text-brand-600 hover:underline">← <?= e($nhom['ma_lop']) ?></a>
<h1 class="text-xl font-bold text-slate-800 mt-2 mb-6"><?= e($nhom['ten_nhom']) ?> <?= $isLeader ? '<span class="text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded-full align-middle">Trưởng nhóm</span>' : '' ?></h1>

<div class="grid md:grid-cols-2 gap-6">
  <div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Thành viên (<?= count(array_filter($thanhvien, fn($t)=>$t['trang_thai']==='da_xac_nhan')) ?>/<?= $nhom['si_so_nhom_toi_da'] ?>)</h2>
      <div class="space-y-2">
        <?php foreach ($thanhvien as $t): ?>
        <div class="flex items-center justify-between text-sm">
          <div>
            <?= e($t['ho_ten']) ?> <span class="text-xs text-slate-400">(<?= e($t['mssv_mgv']) ?>)</span>
            <?= $t['sinhvien_id']==$nhom['truong_nhom_id'] ? '<span class="text-xs text-brand-600">· trưởng nhóm</span>' : '' ?>
          </div>
          <?php if ($t['trang_thai']==='cho_xac_nhan'): ?>
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">chờ chấp nhận</span>
              <?php if ($isLeader): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="huy_moi"><input type="hidden" name="sinhvien_id" value="<?= $t['sinhvien_id'] ?>">
                <button class="text-xs text-rose-500 hover:text-rose-700">huỷ</button>
              </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($isLeader && !is_qua_han($nhom['han_dang_ky_nhom'])): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-800 mb-3 text-sm">Mời thành viên</h2>
      <?php if ($avail): ?>
      <form method="post" class="flex gap-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="moi">
        <select name="sinhvien_id" class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm">
          <?php foreach ($avail as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['ho_ten']) ?> (<?= e($a['mssv_mgv']) ?>)</option><?php endforeach; ?>
        </select>
        <button class="bg-brand-600 hover:bg-brand-700 text-white text-sm px-3 py-2 rounded-lg">Mời</button>
      </form>
      <?php else: ?>
        <p class="text-xs text-slate-400">Không còn bạn cùng lớp nào để mời (hoặc nhóm đã đủ số lượng).</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="space-y-4">
    <?php if (!$dots): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-5 text-sm text-slate-500">
        Giảng viên chưa mở đợt đăng ký đề tài nào cho lớp này.
      </div>
    <?php endif; ?>
    <?php foreach ($dots as $dot): $dk = $dot['dangky']; ?>
    <div class="bg-white border border-slate-200 rounded-xl p-5">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-semibold text-slate-800 text-sm"><?= e($dot['ten_dot']) ?></h2>
        <span class="text-xs text-slate-400"><?= $mucDichLabel[$dot['muc_dich']] ?> · hạn <?= format_datetime($dot['han_dang_ky']) ?></span>
      </div>
      <?php if ($dk): ?>
        <div class="font-medium text-slate-800 text-sm"><?= e($dk['ten_detai']) ?></div>
        <p class="text-sm text-slate-500 mt-1"><?= nl2br(e($dk['mo_ta'])) ?></p>
        <span class="inline-block text-xs px-2 py-0.5 rounded-full <?= $trangThaiMau[$dk['trang_thai']] ?> mt-2"><?= $trangThaiLabel[$dk['trang_thai']] ?></span>
        <?php if ($dk['phan_hoi']): ?>
          <p class="text-xs text-slate-500 mt-2 italic">Phản hồi GV: "<?= e($dk['phan_hoi']) ?>"</p>
        <?php endif; ?>
        <?php if (in_array($dk['trang_thai'], ['tu_choi','yeu_cau_dieu_chinh'], true) && $isLeader): ?>
          <a href="<?= BASE_URL ?>/sinhvien/detai.php?dot_id=<?= $dot['id'] ?>" class="inline-block mt-3 text-xs text-brand-600 hover:underline">→ Chọn/đề xuất đề tài khác cho đợt này</a>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-sm text-slate-500 mb-2">Nhóm chưa đăng ký đề tài cho đợt này.</p>
        <?php if ($isLeader): ?>
          <a href="<?= BASE_URL ?>/sinhvien/detai.php?dot_id=<?= $dot['id'] ?>" class="inline-block bg-brand-600 hover:bg-brand-700 text-white text-xs px-3 py-1.5 rounded-lg">Chọn đề tài →</a>
        <?php else: ?>
          <p class="text-xs text-slate-400">Chỉ trưởng nhóm được đăng ký đề tài.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
