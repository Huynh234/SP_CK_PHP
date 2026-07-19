<?php
// ==========================================================
// Cấu hình chung của hệ thống
// ==========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Đường dẫn gốc của dự án (thư mục con trong htdocs).
// Nếu bạn đặt dự án ở thư mục khác trong htdocs, đổi lại BASE_URL cho khớp.
define('BASE_URL', '/sp_cuoiki/qlnhom'); // sửa tùy theo tên thư mục m nhớ

define('SITE_NAME', 'Hệ thống Quản lý Nhóm & Đăng ký Đề tài');
