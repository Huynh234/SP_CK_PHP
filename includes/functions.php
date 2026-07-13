<?php
// ==========================================================
// Hàm tiện ích dùng chung
// ==========================================================

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Lưu thông báo flash (hiển thị 1 lần rồi mất) */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function format_datetime(?string $dt): string
{
    if (!$dt) return '—';
    return date('H:i d/m/Y', strtotime($dt));
}

function is_qua_han(?string $han): bool
{
    if (!$han) return false;
    return strtotime($han) < time();
}

/** Sinh mật khẩu ngẫu nhiên đơn giản, dùng khi admin thêm tài khoản hàng loạt */
function random_password(int $len = 8): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/** Kiểm tra CSRF token đơn giản */
function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function csrf_check(): void
{
    if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        die('Yêu cầu không hợp lệ (CSRF). Hãy quay lại và thử lại.');
    }
}
