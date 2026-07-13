<?php
// ==========================================================
// Kết nối CSDL MySQL bằng PDO
// Sửa các thông tin bên dưới cho khớp với XAMPP của bạn
// ==========================================================
$DB_HOST = 'localhost';
$DB_NAME = 'qlnhom_detai';
$DB_USER = 'root';
$DB_PASS = '';
$DB_PORT = '3307';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage()) .
        '<br>Hãy kiểm tra XAMPP (Apache + MySQL) đã bật và đã import file database/schema.sql chưa.');
}
