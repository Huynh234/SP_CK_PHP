<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_role('giangvien');
$gv_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$lop = db_query_one('SELECT l.*, h.ma_hp, h.ten_hp FROM lop_hocphan l JOIN hocphan h ON h.id=l.hocphan_id WHERE l.id=? AND l.giangvien_id=?', [$id, $gv_id]);
if (!$lop) {
  set_flash('error', 'Không tìm thấy lớp hoặc bạn không phụ trách lớp này.');
  redirect('/giangvien/dashboard.php');
}

// Cập nhật điều kiện nhóm & hạn đăng ký nhóm
if (isset($_POST['update_settings'])) {
  csrf_check();
  $si_min = (int)$_POST['si_so_nhom_toi_thieu'];
  $si_max = (int)$_POST['si_so_nhom_toi_da'];
  $han_nhom = $_POST['han_dang_ky_nhom'] !== '' ? $_POST['han_dang_ky_nhom'] : null;

  if ($si_min < 1 || $si_max < $si_min) {
    set_flash('error', 'Sĩ số nhóm không hợp lệ (tối đa phải ≥ tối thiểu).');
    redirect('/giangvien/lop.php?id=' . $id);
  }

  db_exec(
    'UPDATE lop_hocphan SET si_so_nhom_toi_thieu=?, si_so_nhom_toi_da=?, han_dang_ky_nhom=? WHERE id=?',
    [$si_min, $si_max, $han_nhom, $id]
  );

  set_flash('success', 'Đã cập nhật điều kiện nhóm và hạn đăng ký nhóm.');
  redirect('/giangvien/lop.php?id=' . $id);
}

// ====== QUẢN LÝ ĐỢT ĐĂNG KÝ ĐỀ TÀI (Giữa kỳ / Cuối kỳ / Khác) ======
if (isset($_POST['add_dot'])) {
  csrf_check();
  $ten_dot = trim($_POST['ten_dot']);
  $muc_dich = $_POST['muc_dich'];
  $han = $_POST['han_dang_ky'] !== '' ? $_POST['han_dang_ky'] : null;
  if ($ten_dot === '' || !in_array($muc_dich, ['giua_ky', 'cuoi_ky', 'khac'], true)) {
    set_flash('error', 'Vui lòng nhập tên đợt và chọn mục đích hợp lệ.');
    redirect('/giangvien/lop.php?id=' . $id);
  }
  db_exec('INSERT INTO dot_dangky (lop_id, ten_dot, muc_dich, han_dang_ky) VALUES (?,?,?,?)', [$id, $ten_dot, $muc_dich, $han]);
  set_flash('success', 'Đã tạo đợt đăng ký đề tài mới.');
  redirect('/giangvien/lop.php?id=' . $id);
}

if (isset($_POST['toggle_dot'])) {
  csrf_check();
  $dot_id = (int)$_POST['dot_id'];
  $chk = db_query_one('SELECT id FROM dot_dangky WHERE id=? AND lop_id=?', [$dot_id, $id]);
  if ($chk) db_exec("UPDATE dot_dangky SET trang_thai = IF(trang_thai='mo','dong','mo') WHERE id=?", [$dot_id]);
  redirect('/giangvien/lop.php?id=' . $id);
}

if (isset($_POST['delete_dot'])) {
  csrf_check();
  $dot_id = (int)$_POST['dot_id'];
  db_exec('DELETE FROM dot_dangky WHERE id=? AND lop_id=?', [$dot_id, $id]);
  set_flash('success', 'Đã xoá đợt đăng ký.');
  redirect('/giangvien/lop.php?id=' . $id);
}

// ====== TẠO NHÓM: RANDOM HOÁ (khi hết hạn đăng ký nhóm, SV chưa có nhóm) ======
if (isset($_POST['random_nhom'])) {
  csrf_check();

  // Bước 1: Giải tán các nhóm CHƯA ĐỦ sĩ số tối thiểu (kể cả nhóm 1 người
  // do sinh viên tự tạo nhưng chưa mời được ai) — để gộp chung vào diện random.
  $siToiThieu = (int)$lop['si_so_nhom_toi_thieu'];
  $nhomChuaDu = db_query("
        SELECT n.id FROM nhom n
        WHERE n.lop_id = ?
        AND (SELECT COUNT(*) FROM thanhvien_nhom tv WHERE tv.nhom_id = n.id AND tv.trang_thai='da_xac_nhan') < ?
    ", [$id, $siToiThieu]);
  foreach ($nhomChuaDu as $nc) {
    // Xoá nhóm này -> nhờ ON DELETE CASCADE, thanhvien_nhom của nhóm cũng bị xoá theo,
    // giải phóng các thành viên để random lại.
    db_exec('DELETE FROM nhom WHERE id=?', [$nc['id']]);
  }

  // Bước 2: Lấy toàn bộ sinh viên trong lớp hiện chưa thuộc nhóm nào (sau khi đã giải tán ở bước 1)
  $svChuaCoNhom = db_query("
        SELECT u.id FROM lop_sinhvien ls
        JOIN users u ON u.id = ls.sinhvien_id
        WHERE ls.lop_id = ?
        AND u.id NOT IN (
            SELECT tv.sinhvien_id FROM thanhvien_nhom tv
            JOIN nhom n ON n.id = tv.nhom_id
            WHERE n.lop_id = ? AND tv.trang_thai = 'da_xac_nhan'
        )
    ", [$id, $id]);
  $svIds = array_column($svChuaCoNhom, 'id');
  shuffle($svIds);

  $maxSize = max(1, (int)$lop['si_so_nhom_toi_da']);
  $soNhomTao = 0;
  $chunks = array_chunk($svIds, $maxSize);
  foreach ($chunks as $chunk) {
    if (!$chunk) continue;
    $stt = (int)db_value('SELECT COUNT(*) FROM nhom WHERE lop_id=?', [$id]) + 1;
    db_exec("INSERT INTO nhom (lop_id, ten_nhom, truong_nhom_id, nguon_tao) VALUES (?,?,?,'random')", [$id, 'Nhóm random ' . $stt, $chunk[0]]);
    $nhomId = db_last_id();
    foreach ($chunk as $svId) {
      db_exec("INSERT INTO thanhvien_nhom (nhom_id, sinhvien_id, trang_thai) VALUES (?,?,'da_xac_nhan')", [$nhomId, $svId]);
    }
    $soNhomTao++;
  }
  set_flash('success', "Đã giải tán " . count($nhomChuaDu) . " nhóm chưa đủ sĩ số và random tạo {$soNhomTao} nhóm mới cho " . count($svIds) . " sinh viên.");
  redirect('/giangvien/lop.php?id=' . $id);
}

// ====== TẠO NHÓM: CHIA THỦ CÔNG THEO DANH SÁCH ======
if (isset($_POST['chia_nhom_thucong'])) {
  csrf_check();
  $lines = preg_split('/\r\n|\r|\n/', trim($_POST['ds_chia_nhom'] ?? ''));
  $soNhomTao = 0;
  $loi = [];

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;
    // Định dạng: "Tên nhóm: mã1, mã2, mã3"
    $pos = strpos($line, ':');
    if ($pos === false) {
      $loi[] = "Bỏ qua dòng (thiếu dấu ':'): {$line}";
      continue;
    }
    $ten_nhom = trim(substr($line, 0, $pos));
    $ds_ma = array_map('trim', explode(',', substr($line, $pos + 1)));

    $svIds = [];
    foreach ($ds_ma as $ma) {
      if ($ma === '') continue;
      $u = db_query_one("SELECT u.id FROM users u JOIN lop_sinhvien ls ON ls.sinhvien_id=u.id AND ls.lop_id=? WHERE (u.username=? OR u.mssv_mgv=?)", [$id, $ma, $ma]);
      if (!$u) {
        $loi[] = "Không tìm thấy sinh viên '{$ma}' trong lớp (nhóm \"{$ten_nhom}\").";
        continue;
      }
      $daCoNhom = db_query_one("
                SELECT tv.id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
                WHERE tv.sinhvien_id=? AND tv.trang_thai='da_xac_nhan' AND n.lop_id=?
            ", [$u['id'], $id]);
      if ($daCoNhom) {
        $loi[] = "Sinh viên '{$ma}' đã có nhóm khác trong lớp, bỏ qua khỏi nhóm \"{$ten_nhom}\".";
        continue;
      }
      $svIds[] = $u['id'];
    }
    $svIds = array_unique($svIds);
    if (!$svIds) {
      $loi[] = "Nhóm \"{$ten_nhom}\" không có thành viên hợp lệ, bỏ qua.";
      continue;
    }

    if ($ten_nhom === '') $ten_nhom = 'Nhóm GV chia ' . (($soNhomTao) + 1);
    db_exec("INSERT INTO nhom (lop_id, ten_nhom, truong_nhom_id, nguon_tao) VALUES (?,?,?,'giangvien')", [$id, $ten_nhom, $svIds[0]]);
    $nhomId = db_last_id();
    foreach ($svIds as $svId) {
      db_exec("INSERT INTO thanhvien_nhom (nhom_id, sinhvien_id, trang_thai) VALUES (?,?,'da_xac_nhan')", [$nhomId, $svId]);
    }
    $soNhomTao++;
  }

  set_flash($loi ? 'error' : 'success', "Đã tạo {$soNhomTao} nhóm theo danh sách." . ($loi ? ' Một số vấn đề: ' . implode(' | ', $loi) : ''));
  redirect('/giangvien/lop.php?id=' . $id);
}

// ====== RANDOM HOÁ ĐỀ TÀI theo 1 đợt cụ thể ======
if (isset($_POST['random_detai'])) {
  csrf_check();
  $dot_id = (int)$_POST['dot_id'];
  $dot = db_query_one('SELECT * FROM dot_dangky WHERE id=? AND lop_id=?', [$dot_id, $id]);
  if (!$dot) {
    set_flash('error', 'Không tìm thấy đợt đăng ký.');
    redirect('/giangvien/lop.php?id=' . $id);
  }
  if (!is_qua_han($dot['han_dang_ky'])) {
    set_flash('error', 'Chưa đến hạn đăng ký của đợt này, chưa thể random.');
    redirect('/giangvien/lop.php?id=' . $id);
  }

  $nhomChuaCoDT = db_query("
        SELECT n.id FROM nhom n
        WHERE n.lop_id = ?
        AND n.id NOT IN (SELECT nhom_id FROM dangky_detai WHERE dot_id=? AND trang_thai='da_duyet')
    ", [$id, $dot_id]);
  $nhomIds = array_column($nhomChuaCoDT, 'id');

  $soGan = 0;
  foreach ($nhomIds as $nid) {
    $chosen = db_query_one("
            SELECT d.id, d.so_nhom_toi_da,
              (SELECT COUNT(*) FROM dangky_detai dk WHERE dk.detai_id=d.id AND dk.dot_id=? AND dk.trang_thai='da_duyet') AS da_dk
            FROM detai d
            JOIN detai_dot ddt ON ddt.detai_id = d.id
            WHERE ddt.dot_id = ? AND d.trang_thai='mo'
            HAVING da_dk < so_nhom_toi_da
            ORDER BY RAND() LIMIT 1
        ", [$dot_id, $dot_id]);
    if (!$chosen) continue;
    db_exec('DELETE FROM dangky_detai WHERE nhom_id=? AND dot_id=?', [$nid, $dot_id]);
    db_exec("INSERT INTO dangky_detai (nhom_id, detai_id, dot_id, trang_thai, la_random) VALUES (?,?,?,'da_duyet',1)", [$nid, $chosen['id'], $dot_id]);
    $soGan++;
  }
  set_flash('success', "Đã random gán đề tài cho {$soGan}/" . count($nhomIds) . " nhóm chưa có đề tài trong đợt \"{$dot['ten_dot']}\".");
  redirect('/giangvien/lop.php?id=' . $id);
}

// Dữ liệu hiển thị
$dots = db_query('SELECT * FROM dot_dangky WHERE lop_id=? ORDER BY created_at', [$id]);

$nhoms = db_query('SELECT * FROM nhom WHERE lop_id=? ORDER BY id', [$id]);
foreach ($nhoms as &$n) {
  $n['thanhvien'] = db_query("SELECT u.ho_ten, tv.trang_thai FROM thanhvien_nhom tv JOIN users u ON u.id=tv.sinhvien_id WHERE tv.nhom_id=? ORDER BY tv.trang_thai", [$n['id']]);

  $n['dangky_theo_dot'] = [];
  foreach ($dots as $dot) {
    $dk = db_query_one("SELECT dk.*, d.ten_detai FROM dangky_detai dk JOIN detai d ON d.id=dk.detai_id WHERE dk.nhom_id=? AND dk.dot_id=?", [$n['id'], $dot['id']]);
    $n['dangky_theo_dot'][$dot['id']] = $dk;
  }
}
unset($n);

$svChuaCoNhomCount = (int)db_value("
    SELECT COUNT(*) FROM lop_sinhvien ls
    WHERE ls.lop_id = ?
    AND ls.sinhvien_id NOT IN (
        SELECT tv.sinhvien_id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
        WHERE n.lop_id = ? AND tv.trang_thai='da_xac_nhan'
    )
", [$id, $id]);

$mucDichLabel = ['giua_ky' => 'Giữa kỳ', 'cuoi_ky' => 'Cuối kỳ', 'khac' => 'Khác'];
$trangThaiLabel = ['cho_duyet' => 'chờ duyệt', 'da_duyet' => 'đã duyệt', 'tu_choi' => 'từ chối', 'yeu_cau_dieu_chinh' => 'yêu cầu điều chỉnh'];
$trangThaiMau = ['cho_duyet' => 'bg-amber-50 text-amber-700', 'da_duyet' => 'bg-emerald-50 text-emerald-700', 'tu_choi' => 'bg-rose-50 text-rose-700', 'yeu_cau_dieu_chinh' => 'bg-sky-50 text-sky-700'];

$user = current_user();
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
              50: '#eef2ff',
              100: '#e0e7ff',
              200: '#c7d2fe',
              300: '#a5b4fc',
              400: '#818cf8',
              500: '#6366f1',
              600: '#4f46e5',
              700: '#4338ca',
              800: '#3730a3',
              900: '#312e81'
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

  <?php if ($user) {
    echo '<header class="bg-brand-700 text-white sticky top-0 z-30 shadow">';
    echo '<div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">';
    echo '<div class="flex items-center gap-6">';
    echo '<a href="' . BASE_URL . '/' . $user['role'] . '/dashboard.php" class="font-bold text-lg tracking-tight">';
    echo '📚 QL Nhóm & Đề tài';
    echo '</a>';
    echo '<nav class="hidden md:flex items-center gap-1 text-sm">';
    echo '<a href="' . BASE_URL . '/giangvien/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp của tôi</a>';
    echo '<a href="' . BASE_URL . '/giangvien/detai.php" class="px-3 py-2 rounded hover:bg-brand-600">Ngân hàng đề tài</a>';
    echo '</nav>';
    echo '</div>';
    echo '<div class="flex items-center gap-3 text-sm">';
    echo '<span class="hidden sm:inline text-brand-100">' . $user['ho_ten'] . ' · <span class="uppercase text-xs bg-brand-800 px-2 py-0.5 rounded">' . $user['role'] . '</span></span>';
    echo '<a href="' . BASE_URL . '/logout.php" class="bg-brand-800 hover:bg-brand-900 px-3 py-1.5 rounded transition">Đăng xuất</a>';
    echo '</div>';
    echo '</div>';
    echo '</header>';
  } ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
    <?php $flash = get_flash();
    if ($flash) {
      echo '<div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium
      ' . ($flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200') . '">';
      echo $flash['message'] . '</div>';
    }
    ?>
<a href="<?php echo BASE_URL ?>/giangvien/dashboard.php" class="text-sm text-brand-600 hover:underline">← Danh sách lớp</a>
<div class="flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
  <div class="flex items-center gap-2">
    <span class="font-mono text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded"><?php echo $lop['ma_lop'] ?></span>
    <h1 class="text-xl font-bold text-slate-800"><?php echo $lop['ten_lop'] ?></h1>
  </div>
  <div class="flex gap-2">
    <button onclick="document.getElementById('modalSettings').classList.remove('hidden')" class="text-sm bg-white border border-slate-300 hover:border-brand-400 text-slate-700 px-4 py-2 rounded-lg">✎ Sửa điều kiện & hạn ĐK nhóm</button>
    <a href="<?php echo BASE_URL ?>/giangvien/duyet_dangky.php?id=<?php echo $id ?>" class="text-sm bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg">🔍 Duyệt đăng ký đề tài</a>
  </div>
</div>

<!-- Hạn đăng ký nhóm + tạo nhóm -->
<div class="grid md:grid-cols-2 gap-4 mb-6">
  <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm">
    <div class="text-slate-500 text-xs">Hạn đăng ký nhóm</div>
    <div class="font-medium <?php echo is_qua_han($lop['han_dang_ky_nhom']) ? 'text-rose-600' : 'text-slate-800' ?>"><?php echo format_datetime($lop['han_dang_ky_nhom']) ?></div>
    <div class="text-xs text-slate-400 mt-1">Sĩ số nhóm: <?php echo $lop['si_so_nhom_toi_thieu'] ?>–<?php echo $lop['si_so_nhom_toi_da'] ?> · <?php echo $svChuaCoNhomCount ?> SV chưa có nhóm</div>
    <div class="flex gap-2 mt-3">
      <?php
      // if (is_qua_han($lop['han_dang_ky_nhom']) && $svChuaCoNhomCount > 0) {
      echo '<form method="post" class="flex-1" ';
      echo 'onsubmit="return confirm(\'Random tạo nhóm cho ' . $svChuaCoNhomCount . ' sinh viên chưa có nhóm?\');">';
      echo csrf_field();
      echo '<button class="w-full text-xs bg-rose-500 hover:bg-rose-600 text-white px-2 py-1.5 rounded" name="random_nhom">';
      echo '🎲 Random nhóm</button>';
      echo '</form>';
      // }
      ?>

      <button onclick="document.getElementById('modalChiaNhom').classList.remove('hidden')" class="flex-1 text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1.5 rounded">✂️ Chia nhóm theo danh sách</button>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-4 text-sm">
    <div class="text-slate-500 text-xs mb-1">Đợt đăng ký đề tài (<?php echo count($dots) ?>)</div>
    <?php
    foreach ($dots as $dot) {
      echo '<div class="flex items-center justify-between py-1 text-xs border-b border-slate-100 last:border-0">';
      echo '<div>';
      echo '<span class="font-medium text-slate-700">' . $dot['ten_dot'] . '</span> ';
      echo '<span class="text-slate-400">(' . $mucDichLabel[$dot['muc_dich']] . ')</span> — ';
      echo '<span class="' . (is_qua_han($dot['han_dang_ky']) ? 'text-rose-500' : 'text-slate-500') . '">';
      echo format_datetime($dot['han_dang_ky']);
      echo '</span>';
      echo '</div>';

      echo '<div class="flex items-center gap-2">';
      echo '<form method="post">';
      echo csrf_field();
      echo '<input type="hidden" name="dot_id" value="' . $dot['id'] . '">';
      echo '<button class="text-slate-400 hover:text-brand-600" name="toggle_dot">';
      echo ($dot['trang_thai'] === 'mo' ? 'đóng' : 'mở');
      echo '</button>';
      echo '</form>';

      echo '<form method="post" onsubmit="return confirm(\'Xoá đợt này? (sẽ xoá luôn đăng ký đề tài thuộc đợt)\');">';
      echo csrf_field();
      echo '<input type="hidden" name="dot_id" value="' . $dot['id'] . '">';
      echo '<button class="text-rose-400 hover:text-rose-600" name="delete_dot">xoá</button>';
      echo '</form>';
      echo '</div>';

      echo '</div>';
    }
    ?>
    <button onclick="document.getElementById('modalAddDot').classList.remove('hidden')" class="w-full mt-2 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1.5 rounded">+ Tạo đợt đăng ký mới</button>
    <a href="<?php echo BASE_URL ?>/giangvien/detai.php" class="block text-center text-xs text-brand-600 hover:underline mt-2">Quản lý ngân hàng đề tài →</a>
  </div>
</div>

<h2 class="font-semibold text-slate-800 mb-3">Danh sách nhóm (<?php echo count($nhoms) ?>)</h2>
<div class="grid gap-4 mb-6">
  <<?php
    foreach ($nhoms as $n) {
      echo '<div class="bg-white border border-slate-200 rounded-xl p-5">';
      echo '<div class="flex flex-wrap items-center justify-between gap-2 mb-2">';
      echo '<div class="font-semibold text-slate-800">';
      echo $n['ten_nhom'];
      if ($n['nguon_tao'] === 'random') {
        echo '<span class="text-xs text-amber-600">(random)</span>';
      }
      if ($n['nguon_tao'] === 'giangvien') {
        echo '<span class="text-xs text-violet-600">(GV chia)</span>';
      }
      echo '</div></div>';

      echo '<div class="flex flex-wrap gap-2 mb-3">';
      foreach ($n['thanhvien'] as $tv) {
        echo '<span class="text-xs px-2 py-1 rounded bg-slate-50 border border-slate-200">';
        echo $tv['ho_ten'];
        echo $tv['trang_thai'] !== 'da_xac_nhan' ? ' (chờ)' : '';
        echo '</span>';
      }
      echo '</div>';

      if ($dots) {
        echo '<div class="border-t border-slate-100 pt-2 space-y-1">';
        foreach ($dots as $dot) {
          $dk = $n['dangky_theo_dot'][$dot['id']];
          echo '<div class="flex items-center justify-between text-xs">';
          echo '<span class="text-slate-500">' . $dot['ten_dot'] . ':</span>';
          if ($dk) {
            echo '<span class="px-2 py-0.5 rounded-full ' . $trangThaiMau[$dk['trang_thai']] . '">';
            echo $dk['ten_detai'] . ' — ' . $trangThaiLabel[$dk['trang_thai']];
            echo '</span>';
          } else {
            echo '<span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">chưa đăng ký</span>';
          }
          echo '</div>';
        }
        echo '</div>';
      }

      echo '</div>';
    }

    if (!$nhoms) {
      echo '<div class="text-center text-slate-400 py-12">Lớp chưa có nhóm nào.</div>';
    }
    ?>

    </div>
    <?php
    if ($dots) {
      echo '<h2 class="font-semibold text-slate-800 mb-3">Random đề tài theo đợt (khi hết hạn)</h2>';
      echo '<div class="grid md:grid-cols-2 gap-4">';
      foreach ($dots as $dot) {
        echo '<div class="bg-white border border-slate-200 rounded-xl p-4 text-sm">';
        echo '<div class="font-medium text-slate-700">' . $dot['ten_dot'] .
          ' <span class="text-xs text-slate-400">(' . $mucDichLabel[$dot['muc_dich']] . ')</span></div>';
        echo '<div class="text-xs text-slate-500 mt-1">Hạn: ' . format_datetime($dot['han_dang_ky']) . '</div>';

        if (is_qua_han($dot['han_dang_ky'])) {
          echo '<form method="post" class="mt-2" onsubmit="return confirm(\'Random gán đề tài cho các nhóm chưa đăng ký trong đợt này?\');">';
          echo csrf_field();
          echo '<input type="hidden" name="dot_id" value="' . $dot['id'] . '">';
          echo '<button name="random_detai" class="w-full text-xs bg-rose-500 hover:bg-rose-600 text-white px-2 py-1.5 rounded">';
          echo '🎲 Random đề tài cho đợt này</button>';
          echo '</form>';
        } else {
          echo '<div class="text-xs text-slate-400 mt-2">Chưa đến hạn.</div>';
        }

        echo '</div>';
      }
      echo '</div>';
    }
    ?>


    <!-- Modal sửa điều kiện & hạn -->
    <div id="modalSettings" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
      <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h2 class="font-bold text-slate-800 mb-1">Sửa điều kiện nhóm & hạn đăng ký nhóm</h2>
        <p class="text-xs text-slate-500 mb-4">Áp dụng cho lớp <?php echo $lop['ma_lop'] ?>.</p>
        <form method="post" class="space-y-3">
          <?php echo csrf_field() ?>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối thiểu/nhóm</label>
              <input name="si_so_nhom_toi_thieu" type="number" min="1" value="<?php echo (int)$lop['si_so_nhom_toi_thieu'] ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Số TV tối đa/nhóm</label>
              <input name="si_so_nhom_toi_da" type="number" min="1" value="<?php echo (int)$lop['si_so_nhom_toi_da'] ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký nhóm</label>
            <input name="han_dang_ky_nhom" type="datetime-local"
              value="<?php echo $lop['han_dang_ky_nhom'] ? date('Y-m-d\TH:i', strtotime($lop['han_dang_ky_nhom'])) : '' ?>"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalSettings').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
            <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white" name="update_settings">Lưu thay đổi</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal tạo đợt đăng ký mới -->
    <div id="modalAddDot" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
      <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h2 class="font-bold text-slate-800 mb-1">Tạo đợt đăng ký đề tài</h2>
        <p class="text-xs text-slate-500 mb-4">VD: "Đợt 1 - Giữa kỳ", "Đợt 2 - Cuối kỳ". Mỗi đợt có hạn đăng ký và danh sách đề tài riêng.</p>
        <form method="post" class="space-y-3">
          <?php echo csrf_field() ?>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tên đợt *</label>
            <input name="ten_dot" required placeholder="Đợt 1 - Giữa kỳ" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Mục đích / loại điểm áp dụng *</label>
            <select name="muc_dich" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
              <option value="giua_ky">Điểm giữa kỳ</option>
              <option value="cuoi_ky">Điểm cuối kỳ</option>
              <option value="khac">Khác</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Hạn đăng ký của đợt này</label>
            <input name="han_dang_ky" type="datetime-local" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalAddDot').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
            <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white" name="add_dot">Tạo đợt</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal chia nhóm theo danh sách -->
    <div id="modalChiaNhom" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
      <div class="bg-white rounded-xl p-6 w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <h2 class="font-bold text-slate-800 mb-1">Chia nhóm theo danh sách</h2>
        <p class="text-xs text-slate-500 mb-4">
          Mỗi dòng là 1 nhóm, theo định dạng:
          <code class="bg-slate-100 px-1 rounded">Tên nhóm: mã SV 1, mã SV 2, mã SV 3</code>
          (mã SV có thể là username hoặc mã số sinh viên). Sinh viên đã có nhóm khác trong lớp sẽ tự động bị bỏ qua.
        </p>
        <form method="post" class="space-y-3">
          <?php echo csrf_field() ?>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Danh sách chia nhóm *</label>
            <textarea name="ds_chia_nhom" rows="8" required
              placeholder="Nhóm 1: SV001, SV002, SV003&#10;Nhóm 2: SV004, SV005&#10;Nhóm Alpha: sv.an, sv.binh"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalChiaNhom').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg text-slate-500 hover:bg-slate-100">Huỷ</button>
            <button class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white" name="chia_nhom_thucong">Tạo các nhóm</button>
          </div>
        </form>
      </div>
    </div>

</main>
<footer class="text-center text-xs text-slate-400 py-4">
  sản phẩm cuối kỳ Công nghệ Web
</footer>
</body>
</html>