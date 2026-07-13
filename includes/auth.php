<?php
// ==========================================================
// Xác thực đăng nhập & phân quyền theo vai trò (role)
// ==========================================================

function dang_nhap(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!dang_nhap()) return null;
    return [
        'id'      => $_SESSION['user_id'],
        'username'=> $_SESSION['username'],
        'ho_ten'  => $_SESSION['ho_ten'],
        'role'    => $_SESSION['role'],
    ];
}

/** Bắt buộc đăng nhập, nếu chưa thì đá về trang login */
function require_login(): void
{
    if (!dang_nhap()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/** Bắt buộc đúng vai trò, nếu sai thì đá về trang chủ tương ứng */
function require_role(string $role): void
{
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/access_denied.php');
        exit;
    }
}
