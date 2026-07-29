<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('sinhvien');
$sv_id = $_SESSION['user_id'];

if (isset($_POST['update_'])) {
    csrf_check();
    $tv_id = (int)$_POST['tv_id'];
    $action = $_POST['action'];

    $row = db_query_one("
        SELECT tv.*, n.lop_id FROM thanhvien_nhom tv JOIN nhom n ON n.id=tv.nhom_id
        WHERE tv.id=? AND tv.sinhvien_id=? AND tv.trang_thai='cho_xac_nhan'
    ", [$tv_id, $sv_id]);

    if ($row) {
        if ($action === 'chap_nhan') {
            $daCo = db_query_one("
                SELECT tv2.id FROM thanhvien_nhom tv2 JOIN nhom n2 ON n2.id=tv2.nhom_id
                WHERE tv2.sinhvien_id=? AND tv2.trang_thai='da_xac_nhan' AND n2.lop_id=?
            ", [$sv_id, $row['lop_id']]);
            if ($daCo) {
                set_flash('error', 'Bạn đã có nhóm khác trong lớp này rồi, không thể tham gia thêm.');
            } else {
                db_exec("UPDATE thanhvien_nhom SET trang_thai='da_xac_nhan' WHERE id=?", [$tv_id]);
                // Tự động huỷ các lời mời khác đang chờ trong cùng lớp
                db_exec("
                    DELETE tv3 FROM thanhvien_nhom tv3 JOIN nhom n3 ON n3.id=tv3.nhom_id
                    WHERE tv3.sinhvien_id=? AND tv3.trang_thai='cho_xac_nhan' AND n3.lop_id=?
                ", [$sv_id, $row['lop_id']]);
                set_flash('success', 'Đã tham gia nhóm!');
            }
        } elseif ($action === 'tu_choi') {
            db_exec("DELETE FROM thanhvien_nhom WHERE id=?", [$tv_id]);
            set_flash('success', 'Đã từ chối lời mời.');
        }
    }
    redirect('/sinhvien/loi_moi.php');
}

$list = db_query("
    SELECT tv.id AS tv_id, n.id AS nhom_id, n.ten_nhom, l.ma_lop, l.ten_lop, u.ho_ten AS ten_truong_nhom
    FROM thanhvien_nhom tv
    JOIN nhom n ON n.id = tv.nhom_id
    JOIN lop_hocphan l ON l.id = n.lop_id
    JOIN users u ON u.id = n.truong_nhom_id
    WHERE tv.sinhvien_id = ? AND tv.trang_thai = 'cho_xac_nhan'
    ORDER BY tv.created_at DESC
", [$sv_id]);

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
        echo '<a href="' . BASE_URL . '/sinhvien/dashboard.php" class="px-3 py-2 rounded hover:bg-brand-600">Lớp của tôi</a>';
        echo '<a href="' . BASE_URL . '/sinhvien/loi_moi.php" class="px-3 py-2 rounded hover:bg-brand-600">Lời mời nhóm</a>';
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
        <h1 class="text-xl font-bold text-slate-800 mb-6">Lời mời vào nhóm</h1>

        <div class="grid gap-4 max-w-xl">
            <?php
            foreach ($list as $it) {
                echo '<div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center justify-between gap-3">';

                echo '<div>';
                echo '<div class="font-semibold text-slate-800">' . $it['ten_nhom'] . '</div>';
                echo '<div class="text-xs text-slate-500">'
                    . $it['ma_lop'] . ' - ' . $it['ten_lop']
                    . ' · Trưởng nhóm: ' . $it['ten_truong_nhom']
                    . '</div>';
                echo '</div>';

                echo '<div class="flex gap-2 shrink-0">';

                // Form chấp nhận
                echo '<form method="post">' . csrf_field()
                    . '<input type="hidden" name="tv_id" value="' . $it['tv_id'] . '">'
                    . '<input type="hidden" name="action" value="chap_nhan">'
                    . '<button name="update_" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg">Chấp nhận</button>'
                    . '</form>';

                // Form từ chối
                echo '<form method="post">' . csrf_field()
                    . '<input type="hidden" name="tv_id" value="' . $it['tv_id'] . '">'
                    . '<input type="hidden" name="action" value="tu_choi">'
                    . '<button name="update_" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-lg">Từ chối</button>'
                    . '</form>';

                echo '</div>'; // end flex
                echo '</div>'; // end card
            }
            ?>

            <?php
            if (!$list) {
                echo '<div class="text-center text-slate-400 py-12">Bạn không có lời mời nào đang chờ.</div>';
            }
            ?>

        </div>

    </main>
    <footer class="text-center text-xs text-slate-400 py-4">
        sản phẩm cuối kỳ Công nghệ Web
    </footer>
</body>

</html>