<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'Không có quyền truy cập';
include __DIR__ . '/includes/header.php';
?>
<div class="text-center py-24">
  <div class="text-5xl mb-4">⛔</div>
  <h1 class="text-xl font-bold text-slate-800">Bạn không có quyền truy cập trang này</h1>
  <a href="<?= BASE_URL ?>/<?= $_SESSION['role'] ?>/dashboard.php" class="inline-block mt-4 text-brand-600 hover:underline">← Quay lại trang chính</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
