# Hệ thống Quản lý Nhóm & Đăng ký Đề tài (v2)

Đồ án cuối kỳ môn Công nghệ Web — PHP thuần (kiến trúc kiểu MVC đơn giản) + **mysqli** + MySQL + XAMPP + Tailwind CSS (CDN).

> **Đây là bản v2**, đã chuyển từ PDO sang **mysqli**, và bổ sung: chia nhóm thủ công theo danh sách (ngoài random), và nhiều **đợt đăng ký đề tài** trong 1 lớp (VD: Giữa kỳ, Cuối kỳ). Nếu bạn đang dùng bản v1 (PDO, 1 lần đăng ký đề tài/lớp), hãy **xoá database cũ và import lại `database/schema.sql`** — cấu trúc bảng đã thay đổi khá nhiều, không tương thích ngược.

## 1. Kiến trúc thư mục

```
qlnhom/
├── config/
│   ├── config.php     # session, BASE_URL
│   └── database.php   # kết nối mysqli + các hàm helper truy vấn (db_query, db_exec, ...)
├── includes/          # auth (quyền), functions (tiện ích), header/footer (View)
├── database/
│   └── schema.sql     # toàn bộ CSDL + dữ liệu mẫu
├── admin/             # các trang cho vai trò Admin
├── giangvien/          # các trang cho vai trò Giảng viên
├── sinhvien/           # các trang cho vai trò Sinh viên
├── index.php           # trang đăng nhập
├── logout.php
└── access_denied.php
```

## 2. Vì sao dùng mysqli thay vì PDO

Dự án dùng **mysqli** (thay vì PDO) theo đúng yêu cầu. Để code gọn và dễ đọc, `config/database.php` định nghĩa sẵn vài hàm helper mỏng bọc quanh mysqli (vẫn 100% dùng mysqli bên dưới, có prepared statement chống SQL Injection đầy đủ):

```php
db_query($sql, $params)      // SELECT nhiều dòng -> mảng kết hợp
db_query_one($sql, $params)  // SELECT 1 dòng -> mảng kết hợp hoặc null
db_value($sql, $params)      // SELECT 1 giá trị đơn, VD COUNT(*)
db_exec($sql, $params)       // INSERT/UPDATE/DELETE -> trả về mysqli_stmt
db_last_id()                 // lấy id vừa insert (AUTO_INCREMENT)
```

Ví dụ 1 câu lệnh trong code:
```php
$u = db_query_one('SELECT * FROM users WHERE username = ?', [$username]);
db_exec('INSERT INTO users (username, ho_ten) VALUES (?,?)', [$username, $ho_ten]);
$id = db_last_id();
```

**Yêu cầu: PHP >= 8.1** (vì dùng `mysqli_stmt::execute($params)` nhận mảng tham số trực tiếp — tính năng có từ PHP 8.1). XAMPP bản mới (8.1 trở lên, ví dụ 8.2.x) đã đáp ứng sẵn. Kiểm tra bằng cách vào `http://localhost/dashboard/phpinfo.php` hoặc chạy `php -v` trong XAMPP Shell.

Lỗi trùng khoá (VD: username trùng) được bắt bằng `catch (mysqli_sql_exception $e)` nhờ đã bật `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`.

## 3. Cài đặt trên XAMPP

1. Cài XAMPP, bật **Apache** và **MySQL** trong XAMPP Control Panel.
2. Copy toàn bộ thư mục `qlnhom` vào `C:\xampp\htdocs\qlnhom` (Windows) hoặc `/Applications/XAMPP/htdocs/qlnhom` (Mac).
3. Mở `http://localhost/phpmyadmin`, tạo CSDL bằng cách **Import** file `database/schema.sql` (file này tự `DROP DATABASE` cũ nếu có rồi tạo lại `qlnhom_detai` từ đầu, kèm dữ liệu mẫu).
4. Kiểm tra `config/database.php`: mặc định user `root`, mật khẩu rỗng — khớp XAMPP mặc định.
5. Kiểm tra `config/config.php`: `BASE_URL` mặc định `/qlnhom`. Đổi nếu bạn đặt tên thư mục khác trong `htdocs`.
6. Truy cập `http://localhost/qlnhom` → ra trang đăng nhập.

## 4. Tài khoản demo (mật khẩu chung: `123456`)

| Vai trò    | Tài khoản  |
|-----------|-----------|
| Admin     | admin     |
| Giảng viên| gv.hoang  |
| Sinh viên | sv.an, sv.binh, sv.chi |

## 5. Điểm mới so với bản v1

### 5.1. Admin & Giảng viên cùng sửa được điều kiện lớp
Cả Admin (`admin/lop_detail.php`) và Giảng viên (`giangvien/lop.php`) đều sửa được: sĩ số nhóm tối thiểu/tối đa, hạn đăng ký nhóm. Admin sửa thêm được tên lớp, học kỳ.

### 5.2. Hai cách tạo nhóm cho Giảng viên
Ở trang chi tiết lớp (`giangvien/lop.php`), ngoài **Random nhóm** (tự động chia ngẫu nhiên sinh viên chưa có nhóm sau khi hết hạn), giảng viên có thêm nút **"✂️ Chia nhóm theo danh sách"**: dán danh sách theo định dạng

```
Nhóm 1: SV001, SV002, SV003
Nhóm 2: SV004, SV005
Nhóm Alpha: sv.an, sv.binh
```

mỗi dòng là 1 nhóm — `Tên nhóm: mã số/username các thành viên, cách nhau bởi dấu phẩy`. Hệ thống tự bỏ qua sinh viên không tìm thấy hoặc đã có nhóm khác, và báo cáo lại kết quả.

### 5.3. Nhiều đợt đăng ký đề tài trong 1 lớp
Trước đây mỗi lớp chỉ có **1 lần** đăng ký đề tài duy nhất. Bản v2 cho phép giảng viên tạo nhiều **đợt đăng ký** trong cùng 1 lớp — mỗi đợt có tên riêng, mục đích riêng (điểm giữa kỳ / điểm cuối kỳ / khác) và **hạn đăng ký riêng**:

```
Đợt 1 - Giữa kỳ   (áp dụng cho điểm giữa kỳ, hạn 14/08)
Đợt 2 - Cuối kỳ   (áp dụng cho điểm cuối kỳ, hạn 20/11)
```

Với mỗi đợt:
- Giảng viên gán các đề tài từ **ngân hàng đề tài** của mình vào đợt đó (1 đề tài có thể dùng cho nhiều đợt/nhiều lớp khác nhau).
- Mỗi nhóm sinh viên đăng ký đề tài **riêng cho từng đợt** (VD: 1 đề tài cho đợt giữa kỳ, 1 đề tài khác cho đợt cuối kỳ) — không giới hạn chỉ 1 đăng ký duy nhất cho cả lớp như trước.
- Giảng viên duyệt/từ chối/yêu cầu điều chỉnh và random hoá đề tài **theo từng đợt** độc lập.
- Số nhóm tối đa của 1 đề tài (`so_nhom_toi_da`) được tính **riêng cho mỗi đợt** đề tài đó tham gia.

## 6. Luồng chức năng đầy đủ

**Admin**
- Thêm tài khoản đơn lẻ / import CSV hàng loạt, cấp vai trò, khoá/mở khoá, xoá tài khoản.
- Quản lý mã học phần.
- Tạo lớp học phần, gán giảng viên, thêm sinh viên vào lớp (chọn từng người hoặc dán danh sách mã số).
- Sửa lại thông tin & điều kiện lớp học phần bất kỳ lúc nào.

**Giảng viên**
- Xem các lớp được phân công.
- Tạo ngân hàng đề tài dùng chung nhiều đợt/nhiều lớp.
- Tạo nhiều đợt đăng ký đề tài trong 1 lớp (giữa kỳ, cuối kỳ...), mỗi đợt hạn riêng.
- Gán đề tài cho từng đợt, duyệt/từ chối/yêu cầu điều chỉnh đăng ký theo từng đợt.
- Tạo nhóm bằng 2 cách: **random hoá** hoặc **chia thủ công theo danh sách**.
- Random hoá đề tài cho nhóm chưa đăng ký, riêng theo từng đợt khi đã hết hạn.
- Sửa điều kiện nhóm & hạn đăng ký nhóm của lớp mình phụ trách.

**Sinh viên**
- Tạo nhóm (trở thành trưởng nhóm) hoặc chờ được mời vào nhóm bạn cùng lớp.
- Trưởng nhóm mời thành viên, nhận/chấp nhận/từ chối lời mời.
- Với mỗi đợt đăng ký đề tài đang mở của lớp: chọn đề tài GV cung cấp (tự động duyệt nếu đủ điều kiện) hoặc tự đề xuất đề tài mới (cần GV duyệt) — độc lập giữa các đợt.
- Xem trạng thái và phản hồi của giảng viên cho từng đợt.

## 7. Ghi chú kỹ thuật

- Không dùng framework/thư viện ngoài — chỉ PHP thuần + **mysqli** (yêu cầu PHP >= 8.1) + Tailwind qua CDN.
- Import Excel được thực hiện qua **CSV** (Excel → Save As → CSV UTF-8) để tránh phải cài thư viện đọc `.xlsx`.
- Chức năng "random hoá" được kích hoạt thủ công bằng nút bấm của giảng viên khi đã quá hạn (XAMPP mặc định không có cron job).
- Mật khẩu được băm bằng `password_hash()` (bcrypt), có CSRF token cơ bản cho các form POST.
