<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('giangvien');
$gv_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT l.*, h.ma_hp, h.ten_hp FROM lop_hocphan l JOIN hocphan h ON h.id=l.hocphan_id WHERE l.id=? AND l.giangvien_id=?');
$stmt->execute([$id, $gv_id]);
$lop = $stmt->fetch();
if (!$lop) { set_flash('error', 'Không tìm thấy lớp hoặc bạn không phụ trách lớp này.'); redirect('/giangvien/dashboard.php'); }

// Cập nhật điều kiện nhóm & thời hạn (giảng viên được sửa cho lớp mình phụ trách)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_settings') {
    csrf_check();
    $si_min = (int)$_POST['si_so_nhom_toi_thieu'];
    $si_max = (int)$_POST['si_so_nhom_toi_da'];
    $han_nhom = $_POST['han_dang_ky_nhom'] !== '' ? $_POST['han_dang_ky_nhom'] : null;
    $han_detai = $_POST['han_dang_ky_detai'] !== '' ? $_POST['han_dang_ky_detai'] : null;

    if ($si_min < 1 || $si_max < $si_min) {
        set_flash('error', 'Sĩ số nhóm không hợp lệ (tối đa phải ≥ tối thiểu).');
        redirect('/giangvien/lop.php?id=' . $id);
    }

    $pdo->prepare('UPDATE lop_hocphan SET si_so_nhom_toi_thieu=?, si_so_nhom_toi_da=?, han_dang_ky_nhom=?, han_dang_ky_detai=? WHERE id=?')
        ->execute([$si_min, $si_max, $han_nhom, $han_detai, $id]);

    set_flash('success', 'Đã cập nhật điều kiện nhóm và thời hạn của lớp.');
    redirect('/giangvien/lop.php?id=' . $id);
}

// Gán đề tài có sẵn (từ ngân hàng) vào lớp này
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'gan_detai') {
    csrf_check();
    $detai_id = (int)$_POST['detai_id'];
    $chk = $pdo->prepare('SELECT id FROM detai WHERE id=? AND giangvien_id=?');
    $chk->execute([$detai_id, $gv_id]);
    if ($chk->fetch()) {
        try {
            $pdo->prepare('INSERT INTO detai_lop (detai_id, lop_id) VALUES (?,?)')->execute([$detai_id, $id]);
            set_flash('success', 'Đã gán đề tài vào lớp.');
        } catch (PDOException $e) { set_flash('error', 'Đề tài đã được gán cho lớp này.'); }
    }
    redirect('/giangvien/lop.php?id=' . $id);
}

// ====== RANDOM HOÁ NHÓM (khi hết hạn đăng ký nhóm, SV chưa có nhóm) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'random_nhom') {
    csrf_check();
    if (!is_qua_han($lop['han_dang_ky_nhom'])) {
        set_flash('error', 'Chưa đến hạn đăng ký nhóm, chưa thể random.');
        redirect('/giangvien/lop.php?id=' . $id);
    }
    // Sinh viên trong lớp chưa thuộc nhóm nào (đã xác nhận)
    $svChuaCoNhom = $pdo->prepare("
        SELECT u.id FROM lop_sinhvien ls
        JOIN users u ON u.id = ls.sinhvien_id
        WHERE ls.lop_id = ?
        AND u.id NOT IN (
            SELECT tv.sinhvien_id FROM thanhvien_nhom tv
            JOIN nhom n ON n.id = tv.nhom_id
            WHERE n.lop_id = ? AND tv.trang_thai = 'da_xac_nhan'
        )
    ");
    $svChuaCoNhom->execute([$id, $id]);
    $svIds = array_column($svChuaCoNhom->fetchAll(), 'id');
    shuffle($svIds);

    $maxSize = max(1, (int)$lop['si_so_nhom_toi_da']);
    $soNhomTao = 0;
    $insNhom = $pdo->prepare("INSERT INTO nhom (lop_id, ten_nhom, truong_nhom_id, nguon_tao) VALUES (?,?,?,'random')");
    $insTv = $pdo->prepare("INSERT INTO thanhvien_nhom (nhom_id, sinhvien_id, trang_thai) VALUES (?,?,'da_xac_nhan')");

    $chunks = array_chunk($svIds, $maxSize);
    foreach ($chunks as $chunk) {
        if (!$chunk) continue;
        $soNhomHienCo = $pdo->prepare('SELECT COUNT(*) c FROM nhom WHERE lop_id=?');
        $soNhomHienCo->execute([$id]);
        $stt = $soNhomHienCo->fetch()['c'] + 1;
        $insNhom->execute([$id, 'Nhóm random ' . $stt, $chunk[0]]);
        $nhomId = $pdo->lastInsertId();
        foreach ($chunk as $svId) {
            $insTv->execute([$nhomId, $svId]);
        }
        $soNhomTao++;
    }
    set_flash('success', "Đã random tạo {$soNhomTao} nhóm cho " . count($svIds) . " sinh viên chưa có nhóm.");
    redirect('/giangvien/lop.php?id=' . $id);
}

// ====== RANDOM HOÁ ĐỀ TÀI (khi hết hạn, nhóm chưa có đề tài) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'random_detai') {
    csrf_check();
    if (!is_qua_han($lop['han_dang_ky_detai'])) {
        set_flash('error', 'Chưa đến hạn đăng ký đề tài, chưa thể random.');
        redirect('/giangvien/lop.php?id=' . $id);
    }
    // Nhóm trong lớp chưa có đăng ký đề tài đã duyệt
    $nhomChuaCoDT = $pdo->prepare("
        SELECT n.id FROM nhom n
        WHERE n.lop_id = ?
        AND n.id NOT IN (SELECT nhom_id FROM dangky_detai WHERE trang_thai='da_duyet')
    ");
    $nhomChuaCoDT->execute([$id]);
    $nhomIds = array_column($nhomChuaCoDT->fetchAll(), 'id');

    $soGan = 0;
    foreach ($nhomIds as $nid) {
        // Tìm đề tài còn chỗ, thuộc lớp này, đang mở
        $dt = $pdo->prepare("
            SELECT d.id, d.so_nhom_toi_da,
              (SELECT COUNT(*) FROM dangky_detai dk WHERE dk.detai_id=d.id AND dk.trang_thai='da_duyet') AS da_dk
            FROM detai d
            JOIN detai_lop dl ON dl.detai_id = d.id
            WHERE dl.lop_id = ? AND d.trang_thai='mo'
            HAVING da_dk < so_nhom_toi_da
            ORDER BY RAND() LIMIT 1
        ");
        $dt->execute([$id]);
        $chosen = $dt->fetch();
        if (!$chosen) continue; // hết đề tài trống, GV cần thêm đề tài mới
        // Xoá đăng ký cũ (nếu có, bị từ chối trước đó) rồi tạo bản ghi mới
        $pdo->prepare('DELETE FROM dangky_detai WHERE nhom_id=?')->execute([$nid]);
        $pdo->prepare("INSERT INTO dangky_detai (nhom_id, detai_id, trang_thai, la_random) VALUES (?,?,'da_duyet',1)")
            ->execute([$nid, $chosen['id']]);
        $soGan++;
    }
    set_flash('success', "Đã random gán đề tài cho {$soGan}/" . count($nhomIds) . " nhóm chưa có đề tài.");
    redirect('/giangvien/lop.php?id=' . $id);
}

// Dữ liệu hiển thị
$nhoms = $pdo->prepare("SELECT * FROM nhom WHERE lop_id=? ORDER BY id");
$nhoms->execute([$id]);
$nhoms = $nhoms->fetchAll();

foreach ($nhoms as &$n) {
    $tv = $pdo->prepare("SELECT u.ho_ten, tv.trang_thai FROM thanhvien_nhom tv JOIN users u ON u.id=tv.sinhvien_id WHERE tv.nhom_id=? ORDER BY tv.trang_thai");
    $tv->execute([$n['id']]);
    $n['thanhvien'] = $tv->fetchAll();

    $dk = $pdo->prepare("SELECT dk.*, d.ten_detai FROM dangky_detai dk JOIN detai d ON d.id=dk.detai_id WHERE dk.nhom_id=?");
    $dk->execute([$n['id']]);
    $n['dangky'] = $dk->fetch();
}
unset($n);

$detaiBank = $pdo->prepare("
    SELECT d.* FROM detai d WHERE d.giangvien_id=? AND d.nguon='giangvien'
    AND d.id NOT IN (SELECT detai_id FROM detai_lop WHERE lop_id=?)
");
$detaiBank->execute([$gv_id, $id]);
$detaiBank = $detaiBank->fetchAll();

$svChuaCoNhomCount = $pdo->prepare("
    SELECT COUNT(*) c FROM lop_sinhvien ls
    WHERE ls.lop_id = ?
    AND ls.sinhvien_id NOT IN (
        SELECT tv.sinhvien_id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
        WHERE n.lop_id = ? AND tv.trang_thai='da_xac_nhan'
    )
");
$svChuaCoNhomCount->execute([$id, $id]);
$svChuaCoNhomCount = $svChuaCoNhomCount->fetch()['c'];

$page_title = 'Chi tiết lớp';
include __DIR__ . '/../includes/header.php';
?>
<a href="<?= BASE_URL ?>/giangvien/dashboard.php" class="text-sm text-brand-600 hover:underline">← Danh sách lớp</a>
<div class="flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
  <div class="flex items-center gap-2">
    <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?= e($lop['ma_lop']) ?></span>
    <h1 class="text-xl font-bold text-slate-800"><?= e($lop['ten_lop']) ?></h1>
  </div>
  <div class="flex gap-2">
    <button onclick="document.getElementById('modalSettings').classList.remove('hidden')" class="text-sm bg-white border border-slate-300 hover:border-brand-400 text-slate-700 px-4 py-2 rounded-lg">✎ Sửa điều kiện & thời hạn</button>
    <a href="<?= BASE_URL ?>/giangvien/duyet_dangky.php?lop_id=<?= $id ?>" class="text-sm bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg">🔍 Duyệt đăng ký đề tài</a>
  </div>
</div>

<div class="grid md:grid-cols-3 gap-4 mb-6 text-sm">
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-slate-500 text-xs">Hạn đăng ký nhóm</div>
    <div class="font-medium <?= is_qua_han($lop['han_dang_ky_nhom']) ? 'text-rose-600' : 'text-slate-800' ?>"><?= format_datetime($lop['han_dang_ky_nhom']) ?></div>
    <?php if (is_qua_han($lop['han_dang_ky_nhom']) && $svChuaCoNhomCount > 0): ?>
      <form method="post" class="mt-2" onsubmit="return confirm('Random tạo nhóm cho ' + <?= $svChuaCoNhomCount ?> + ' sinh viên chưa có nhóm?');">
        <?= csrf_field() ?><input type="hidden" name="action" value="random_nhom">
        <button class="w-full text-xs bg-rose-500 hover:bg-rose-600 text-white px-2 py-1.5 rounded">🎲 Random nhóm (<?= $svChuaCoNhomCount ?> SV chưa có nhóm)</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-slate-500 text-xs">Hạn đăng ký đề tài</div>
    <div class="font-medium <?= is_qua_han($lop['han_dang_ky_detai']) ? 'text-rose-600' : 'text-slate-800' ?>"><?= format_datetime($lop['han_dang_ky_detai']) ?></div>
    <?php if (is_qua_han($lop['han_dang_ky_detai'])): ?>
      <form method="post" class="mt-2" onsubmit="return confirm('Random gán đề tài cho các nhóm chưa có đề tài?');">
        <?= csrf_field() ?><input type="hidden" name="action" value="random_detai">
        <button class="w-full text-xs bg-rose-500 hover:bg-rose-600 text-white px-2 py-1.5 rounded">🎲 Random đề tài cho nhóm còn thiếu</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="text-slate-500 text-xs mb-1">Gán thêm đề tài có sẵn vào lớp</div>
    <?php if ($detaiBank): ?>
    <form method="post" class="flex gap-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="gan_detai">
      <select name="detai_id" class="flex-1 border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
        <?php foreach ($detaiBank as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['ten_detai']) ?></option><?php endforeach; ?>
      </select>
      <button class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded">Gán</button>
    </form>
    <?php else: ?>
      <div class="text-xs text-slate-400">Tất cả đề tài trong ngân hàng đã gán cho lớp này.</div>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/giangvien/detai.php" class="text-xs text-brand-600 hover:underline block mt-2">+ Tạo đề tài mới</a>
  </div>
</div>

<h2 class="font-semibold text-slate-800 mb-3">Danh sách nhóm (<?= count($nhoms) ?>)</h2>
<div class="grid gap-4">
  <?php foreach ($nhoms as $n): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="font-semibold text-slate-800"><?= e($n['ten_nhom']) ?> <?= $n['nguon_tao']==='random' ? '<span class="text-xs text-amber-600">(random)</span>' : '' ?></div>
      <?php if ($n['dangky']): ?>
        <?php $trang = $n['dangky']['trang_thai']; $mau = ['cho_duyet'=>'bg-amber-50 text-amber-700','da_duyet'=>'bg-emerald-50 text-emerald-700','tu_choi'=>'bg-rose-50 text-rose-700','yeu_cau_dieu_chinh'=>'bg-sky-50 text-sky-700'][$trang]; ?>
        <span class="text-xs px-2 py-0.5 rounded-full <?= $mau ?>">
          Đề tài: <?= e($n['dangky']['ten_detai']) ?> — <?= e(str_replace('_',' ',$trang)) ?>
        </span>
      <?php else: ?>
        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Chưa đăng ký đề tài</span>
      <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2 mt-2">
      <?php foreach ($n['thanhvien'] as $tv): ?>
        <span class="text-xs px-2 py-1 rounded bg-slate-50 border border-slate-200"><?= e($tv['ho_ten']) ?><?= $tv['trang_thai']!=='da_xac_nhan' ? ' (chờ)' : '' ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$nhoms): ?><div class="text-center text-slate-400 py-12">Lớp chưa có nhóm nào.</div><?php endif; ?>
</div>

<!-- Modal sửa điều kiện & thời hạn -->
<div id="modalSettings" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
  <div class="bg-white rounded-xl p-6 w-full max-w-md">
    <h2 class="font-bold text-slate-800 mb-1">Sửa điều kiện nhóm & thời hạn</h2>
    <p class="text-xs text-slate-500 mb-4">Áp dụng cho lớp <?= e($lop['ma_lop']) ?>. Thay đổi sĩ số sẽ chỉ ảnh hưởng đến các nhóm được tạo/mời thêm sau thời điểm lưu.</p>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_settings">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối thiểu/nhóm</label>
          <input name="si_so_nhom_toi_thieu" type="number" min="1" value="<?= (int)$lop['si_so_nhom_toi_thieu'] ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối đa/nhóm</label>
          <input name="si_so_nhom_toi_da" type="number" min="1" value="<?= (int)$lop['si_so_nhom_toi_da'] ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký nhóm</label>
        <input name="han_dang_ky_nhom" type="datetime-local"
          value="<?= $lop['han_dang_ky_nhom'] ? date('Y-m-d\TH:i', strtotime($lop['han_dang_ky_nhom'])) : '' ?>"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký đề tài</label>
        <input name="han_dang_ky_detai" type="datetime-local"
          value="<?= $lop['han_dang_ky_detai'] ? date('Y-m-d\TH:i', strtotime($lop['han_dang_ky_detai'])) : '' ?>"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modalSettings').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
        <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
