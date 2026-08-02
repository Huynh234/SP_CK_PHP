<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

define('BASE_URL', '/sp_cuoiki/qlnhom'); // sửa tùy theo tên thư mục m nhớ

define('SITE_NAME', 'Hệ thống Quản lý Nhóm & Đăng ký Đề tài');
