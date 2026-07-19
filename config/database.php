<?php
// ==========================================================
// Kết nối CSDL MySQL bằng mysqli
// Sửa các thông tin bên dưới cho khớp với XAMPP của bạn
// Yêu cầu PHP >= 8.1 (mysqli_stmt::execute() nhận mảng tham số trực tiếp)
// ==========================================================
$DB_HOST = 'localhost';
$DB_NAME = 'qlnhom_detai';
$DB_USER = 'root';
$DB_PASS = '';

// Bật chế độ báo lỗi bằng exception (mysqli_sql_exception) thay vì
// phải tự kiểm tra return value false ở mọi câu lệnh.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $mysqli->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage()) .
        '<br>Hãy kiểm tra XAMPP (Apache + MySQL) đã bật, đã import file database/schema.sql, và PHP đang dùng là bản >= 8.1.');
}

/**
 * Chuẩn bị + thực thi 1 câu lệnh SQL có tham số dạng "?".
 * Trả về đối tượng mysqli_stmt đã execute (đọc affected_rows/insert_id/get_result() sau đó).
 */
function db_stmt(string $sql, array $params = []): mysqli_stmt
{
    global $mysqli;
    $stmt = $mysqli->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** SELECT nhiều dòng -> mảng các dòng (mỗi dòng là mảng kết hợp) */
function db_query(string $sql, array $params = []): array
{
    $stmt = db_stmt($sql, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** SELECT 1 dòng -> mảng kết hợp, hoặc null nếu không có */
function db_query_one(string $sql, array $params = []): ?array
{
    $rows = db_query($sql, $params);
    return $rows[0] ?? null;
}

/** SELECT 1 giá trị đơn (VD: SELECT COUNT(*) ...) */
function db_value(string $sql, array $params = [])
{
    $row = db_query_one($sql, $params);
    return $row ? array_values($row)[0] : null;
}

/** INSERT/UPDATE/DELETE. Trả về mysqli_stmt (đọc ->affected_rows / ->insert_id trước khi dùng tiếp). */
function db_exec(string $sql, array $params = []): mysqli_stmt
{
    return db_stmt($sql, $params);
}

/** Lấy id vừa insert gần nhất trên kết nối hiện tại */
function db_last_id(): int
{
    global $mysqli;
    return (int)$mysqli->insert_id;
}
