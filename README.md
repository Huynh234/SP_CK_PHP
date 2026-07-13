# Hệ thống Quản lý Nhóm & Đăng ký Đề tài

Đồ án cuối kỳ môn Công nghệ Web — PHP thuần (kiến trúc kiểu MVC đơn giản) + MySQL + XAMPP + Tailwind CSS (CDN).

## 1. Kiến trúc thư mục

```
qlnhom/
├── config/           # cấu hình (DB, session, BASE_URL)
├── includes/         # phần dùng chung: auth (quyền), functions (tiện ích), header/footer (View)
├── database/
│   └── schema.sql    # toàn bộ CSDL + dữ liệu mẫu
├── admin/             # các trang (Controller + View gộp) cho vai trò Admin
├── giangvien/          # các trang cho vai trò Giảng viên
├── sinhvien/           # các trang cho vai trò Sinh viên
├── index.php          # trang đăng nhập
├── logout.php
└── access_denied.php
```

Mỗi file .php trong `admin/`, `giangvien/`, `sinhvien/` đóng vai trò **Controller** (xử lý POST, truy vấn CSDL - đóng vai **Model** bằng PDO trực tiếp) và **View** (HTML + Tailwind) trong cùng 1 file để đơn giản hoá, đúng tinh thần "dự án đơn giản, hạn chế thư viện". `includes/header.php` và `includes/footer.php` đóng vai layout dùng chung.

## 2. Cài đặt trên XAMPP

1. Cài XAMPP, bật **Apache** và **MySQL** trong XAMPP Control Panel.
2. Copy toàn bộ thư mục `qlnhom` vào `C:\xampp\htdocs\qlnhom` (Windows) hoặc `/Applications/XAMPP/htdocs/qlnhom` (Mac).
3. Mở trình duyệt vào `http://localhost/phpmyadmin`, tạo CSDL bằng cách **Import** file `database/schema.sql` (nó sẽ tự tạo database `qlnhom_detai` và toàn bộ bảng + dữ liệu mẫu).
4. Kiểm tra `config/database.php`: mặc định user `root`, mật khẩu rỗng — khớp với XAMPP mặc định. Nếu bạn đổi mật khẩu MySQL thì sửa lại ở đây.
5. Kiểm tra `config/config.php`: `BASE_URL` mặc định là `/qlnhom`. Nếu bạn đặt thư mục dự án tên khác trong `htdocs`, đổi `BASE_URL` cho khớp.
6. Truy cập `http://localhost/qlnhom` → sẽ ra trang đăng nhập.

## 3. Tài khoản demo (mật khẩu chung: `123456`)

| Vai trò    | Tài khoản  |
|-----------|-----------|
| Admin     | admin     |
| Giảng viên| gv.hoang  |
| Sinh viên | sv.an, sv.binh, sv.chi |

## 4. Luồng chức năng đã cài đặt

**Admin**
- Thêm tài khoản đơn lẻ hoặc **import hàng loạt từ CSV** (xuất từ Excel), cấp vai trò (admin/giảng viên/sinh viên), khoá/mở khoá, xoá tài khoản.
- Quản lý mã học phần.
- Tạo lớp học phần (mã lớp, sĩ số nhóm tối thiểu/tối đa, hạn đăng ký nhóm, hạn đăng ký đề tài), gán giảng viên phụ trách, thêm sinh viên vào lớp (chọn từng người hoặc dán danh sách mã số).

**Giảng viên**
- Xem các lớp được phân công.
- Tạo **ngân hàng đề tài** (có thể dùng chung cho nhiều lớp/nhiều khoá), giới hạn số nhóm được đăng ký mỗi đề tài.
- Xem danh sách nhóm, trạng thái đăng ký đề tài của từng nhóm trong lớp.
- **Duyệt / từ chối / yêu cầu điều chỉnh** đề tài do sinh viên tự đề xuất (kèm phản hồi).
- **Random hoá nhóm** cho sinh viên chưa có nhóm sau khi hết hạn đăng ký nhóm.
- **Random hoá đề tài** cho nhóm chưa chọn/chưa được duyệt đề tài sau khi hết hạn đăng ký đề tài.

**Sinh viên**
- Tạo nhóm (trở thành trưởng nhóm) hoặc chờ được mời vào nhóm bạn cùng lớp.
- Trưởng nhóm mời thành viên (giới hạn theo sĩ số tối đa của lớp, không mời được người đã có nhóm khác).
- Nhận và chấp nhận/từ chối lời mời vào nhóm (mục "Lời mời nhóm").
- Trưởng nhóm chọn đề tài do giảng viên cung cấp (tự động duyệt nếu đủ điều kiện: đủ sĩ số tối thiểu, còn slot, chưa hết hạn) hoặc **tự đề xuất đề tài mới** (cần giảng viên phê duyệt).
- Xem trạng thái đề tài và phản hồi của giảng viên.

## 5. Ghi chú kỹ thuật

- Không dùng framework/thư viện ngoài — chỉ PHP thuần + PDO (MySQL) + Tailwind qua CDN.
- Import Excel được thực hiện qua **CSV** (Excel → Save As → CSV UTF-8) để tránh phải cài thư viện đọc `.xlsx` như PhpSpreadsheet.
- Chức năng "random hoá" được kích hoạt thủ công bằng nút bấm của giảng viên khi đã quá hạn (XAMPP mặc định không có cron job chạy nền), phù hợp với môi trường demo/đồ án.
- Mật khẩu được băm bằng `password_hash()` (bcrypt) của PHP, có kiểm tra CSRF token cơ bản cho các form POST.
