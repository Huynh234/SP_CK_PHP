-- =============================================================
-- CSDL: Hệ thống quản lý nhóm & đăng ký đề tài
-- Import file này vào phpMyAdmin (XAMPP) trước khi chạy dự án
-- =============================================================

CREATE DATABASE IF NOT EXISTS qlnhom_detai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qlnhom_detai;

-- ---------------------------------------------------------
-- 1. Người dùng (Admin / Giảng viên / Sinh viên)
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    ho_ten VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    mssv_mgv VARCHAR(50) COMMENT 'Mã số sinh viên hoặc mã giảng viên',
    role ENUM('admin','giangvien','sinhvien') NOT NULL,
    trang_thai ENUM('active','locked') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 2. Học phần (môn học) - do admin thêm mã học phần
-- ---------------------------------------------------------
CREATE TABLE hocphan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ma_hp VARCHAR(20) NOT NULL UNIQUE,
    ten_hp VARCHAR(200) NOT NULL,
    so_tin_chi INT DEFAULT 3
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 3. Lớp học phần - admin tạo, gán giảng viên phụ trách
-- ---------------------------------------------------------
CREATE TABLE lop_hocphan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ma_lop VARCHAR(30) NOT NULL UNIQUE,
    ten_lop VARCHAR(150) NOT NULL,
    hocphan_id INT NOT NULL,
    giangvien_id INT DEFAULT NULL,
    hoc_ky VARCHAR(20) DEFAULT NULL COMMENT 'VD: HK1 2025-2026',
    si_so_nhom_toi_thieu INT NOT NULL DEFAULT 2,
    si_so_nhom_toi_da INT NOT NULL DEFAULT 5,
    han_dang_ky_nhom DATETIME DEFAULT NULL,
    han_dang_ky_detai DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hocphan_id) REFERENCES hocphan(id) ON DELETE CASCADE,
    FOREIGN KEY (giangvien_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 4. Danh sách sinh viên trong từng lớp học phần
-- ---------------------------------------------------------
CREATE TABLE lop_sinhvien (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lop_id INT NOT NULL,
    sinhvien_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lop_sv (lop_id, sinhvien_id),
    FOREIGN KEY (lop_id) REFERENCES lop_hocphan(id) ON DELETE CASCADE,
    FOREIGN KEY (sinhvien_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 5. Nhóm sinh viên (thuộc 1 lớp học phần)
-- ---------------------------------------------------------
CREATE TABLE nhom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lop_id INT NOT NULL,
    ten_nhom VARCHAR(100) NOT NULL,
    truong_nhom_id INT NOT NULL,
    nguon_tao ENUM('sinhvien','random') NOT NULL DEFAULT 'sinhvien',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lop_id) REFERENCES lop_hocphan(id) ON DELETE CASCADE,
    FOREIGN KEY (truong_nhom_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 6. Thành viên nhóm (bao gồm lời mời chờ chấp nhận)
-- ---------------------------------------------------------
CREATE TABLE thanhvien_nhom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nhom_id INT NOT NULL,
    sinhvien_id INT NOT NULL,
    trang_thai ENUM('cho_xac_nhan','da_xac_nhan','tu_choi') NOT NULL DEFAULT 'cho_xac_nhan',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nhom_sv (nhom_id, sinhvien_id),
    FOREIGN KEY (nhom_id) REFERENCES nhom(id) ON DELETE CASCADE,
    FOREIGN KEY (sinhvien_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 7. Đề tài (thuộc về 1 giảng viên, có thể dùng chung nhiều lớp)
-- ---------------------------------------------------------
CREATE TABLE detai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    giangvien_id INT NOT NULL,
    ten_detai VARCHAR(255) NOT NULL,
    mo_ta TEXT,
    so_nhom_toi_da INT NOT NULL DEFAULT 1 COMMENT 'Số nhóm tối đa được đăng ký đề tài này (tính chung mọi lớp dùng đề tài)',
    nguon ENUM('giangvien','sinhvien') NOT NULL DEFAULT 'giangvien' COMMENT 'giangvien = do GV tạo sẵn, sinhvien = do SV tự đề xuất',
    de_xuat_boi_nhom_id INT DEFAULT NULL COMMENT 'Nếu do SV đề xuất, lưu nhóm đã đề xuất',
    trang_thai ENUM('mo','dong') NOT NULL DEFAULT 'mo' COMMENT 'mo = còn nhận đăng ký, dong = đã đủ/đóng',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (giangvien_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 8. Gán đề tài cho lớp học phần (1 đề tài có thể gán nhiều lớp)
-- ---------------------------------------------------------
CREATE TABLE detai_lop (
    id INT AUTO_INCREMENT PRIMARY KEY,
    detai_id INT NOT NULL,
    lop_id INT NOT NULL,
    UNIQUE KEY uniq_detai_lop (detai_id, lop_id),
    FOREIGN KEY (detai_id) REFERENCES detai(id) ON DELETE CASCADE,
    FOREIGN KEY (lop_id) REFERENCES lop_hocphan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 9. Đăng ký đề tài của nhóm
-- ---------------------------------------------------------
CREATE TABLE dangky_detai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nhom_id INT NOT NULL,
    detai_id INT NOT NULL,
    trang_thai ENUM('cho_duyet','da_duyet','tu_choi','yeu_cau_dieu_chinh') NOT NULL DEFAULT 'cho_duyet',
    la_random TINYINT(1) NOT NULL DEFAULT 0,
    phan_hoi TEXT COMMENT 'Phản hồi / lý do của giảng viên',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nhom_active (nhom_id) COMMENT 'Mỗi nhóm chỉ có 1 bản ghi đăng ký hiện hành (xóa/hủy khi bị từ chối để đăng ký lại)',
    FOREIGN KEY (nhom_id) REFERENCES nhom(id) ON DELETE CASCADE,
    FOREIGN KEY (detai_id) REFERENCES detai(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Dữ liệu mẫu
-- ---------------------------------------------------------
-- Mật khẩu mẫu cho tất cả tài khoản demo bên dưới là: 123456
-- (hash bằng password_hash PHP - bcrypt)
INSERT INTO users (username, password, ho_ten, email, mssv_mgv, role) VALUES
('admin', '$2b$10$ero.ktPYKY5wi4pupK2hveijG.pCBVKmCUD.NpZLsLFPukOq0pUQm', 'Quản trị viên', 'admin@truong.edu.vn', 'AD001', 'admin'),
('gv.hoang', '$2b$10$ero.ktPYKY5wi4pupK2hveijG.pCBVKmCUD.NpZLsLFPukOq0pUQm', 'Nguyễn Văn Hoàng', 'hoang.nv@truong.edu.vn', 'GV001', 'giangvien'),
('sv.an', '$2b$10$ero.ktPYKY5wi4pupK2hveijG.pCBVKmCUD.NpZLsLFPukOq0pUQm', 'Trần Văn An', 'an.tv@truong.edu.vn', 'SV001', 'sinhvien'),
('sv.binh', '$2b$10$ero.ktPYKY5wi4pupK2hveijG.pCBVKmCUD.NpZLsLFPukOq0pUQm', 'Lê Thị Bình', 'binh.lt@truong.edu.vn', 'SV002', 'sinhvien'),
('sv.chi', '$2b$10$ero.ktPYKY5wi4pupK2hveijG.pCBVKmCUD.NpZLsLFPukOq0pUQm', 'Phạm Thị Chi', 'chi.pt@truong.edu.vn', 'SV003', 'sinhvien');

INSERT INTO hocphan (ma_hp, ten_hp, so_tin_chi) VALUES
('CNW101', 'Công nghệ Web', 3);

INSERT INTO lop_hocphan (ma_lop, ten_lop, hocphan_id, giangvien_id, hoc_ky, si_so_nhom_toi_thieu, si_so_nhom_toi_da, han_dang_ky_nhom, han_dang_ky_detai) VALUES
('CNW101.01', 'Công nghệ Web - Nhóm 01', 1, 2, 'HK2 2025-2026', 2, 4,
 DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 14 DAY));

INSERT INTO lop_sinhvien (lop_id, sinhvien_id) VALUES (1,3),(1,4),(1,5);
