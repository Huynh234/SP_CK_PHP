    <?php
    $DB_HOST = 'localhost';
    $DB_NAME = 'qlnhom_detai';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_PORT = 3307; // cái này m sửa lại thhanhf 3306 tại tao dùng  3307

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
        $mysqli->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        die('Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage()));
    }

    /**
     * Chuẩn bị + thực thi 1 câu lệnh SQL có tham số dạng "?".
     * Trả về đối tượng mysqli_stmt đã execute (đọc affected_rows/insert_id/get_result() sau đó).
     */
    function db_stmt(string $sql, array $params = []): mysqli_stmt
    {
        try {
            global $mysqli;
            $stmt = $mysqli->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (mysqli_sql_exception $e) {
            die('Lỗi thực thi câu lệnh SQL: ' . htmlspecialchars($e->getMessage()));
        }
    }

    /** SELECT nhiều dòng -> mảng các dòng (mỗi dòng là mảng kết hợp) */
    function db_query(string $sql, array $params = []): array
    {
        try {
            $stmt = db_stmt($sql, $params);
            $result = $stmt->get_result();
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
            return $rows;
        } catch (mysqli_sql_exception $e) {
            die('Lỗi thực thi câu lệnh SQL: ' . htmlspecialchars($e->getMessage()));
        }
    }

    /** SELECT 1 dòng -> mảng kết hợp, hoặc null nếu không có */
    function db_query_one(string $sql, array $params = []): ?array
    {
        try {
            $rows = db_query($sql, $params);
            return $rows[0] ?? null;
        } catch (mysqli_sql_exception $e) {
            die('Lỗi thực thi câu lệnh SQL: ' . htmlspecialchars($e->getMessage()));
        }
    }

    /** SELECT 1 giá trị đơn (VD: SELECT COUNT(*) ...) */
    function db_value(string $sql, array $params = [])
    {
        try {
            $row = db_query_one($sql, $params);
            return $row ? array_values($row)[0] : null;
        } catch (mysqli_sql_exception $e) {
            die('Lỗi thực thi câu lệnh SQL: ' . htmlspecialchars($e->getMessage()));
        }
    }

    /** INSERT/UPDATE/DELETE. Trả về mysqli_stmt (đọc ->affected_rows / ->insert_id trước khi dùng tiếp). */
    function db_exec(string $sql, array $params = []): mysqli_stmt
    {
        try {
            return db_stmt($sql, $params);
        } catch (mysqli_sql_exception $e) {
            die('Lỗi thực thi câu lệnh SQL: ' . htmlspecialchars($e->getMessage()));
        }
    }

    /** Lấy id vừa insert gần nhất trên kết nối hiện tại */
    function db_last_id(): int
    {
        try {
            global $mysqli;
            return (int)$mysqli->insert_id;
        } catch (mysqli_sql_exception $e) {
            die('Lỗi thực thi câu lệnh SQL: ' . htmlspecialchars($e->getMessage()));
        }
    }
