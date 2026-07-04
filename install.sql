-- ============================================================
-- install.sql - ฐานข้อมูลระบบบริหารความเสี่ยง (Risk Management)
-- ใช้กับ MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS risk_management
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE risk_management;

-- ============================================================
-- 1. ตารางผู้ใช้ (users)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_code VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL,
    fullname VARCHAR(255) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    department VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ตารางรหัสผ่านลืม (password_resets)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ตารางความเสี่ยง (risks)
-- ============================================================
CREATE TABLE IF NOT EXISTS risks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reporter_code VARCHAR(50) NOT NULL,
    unit VARCHAR(100) NOT NULL,
    unit_other VARCHAR(100) DEFAULT NULL,
    risk_type VARCHAR(50) NOT NULL,
    risk_type_other VARCHAR(100) DEFAULT NULL,
    severity VARCHAR(50) NOT NULL,
    severity_other VARCHAR(100) DEFAULT NULL,
    event_datetime DATETIME NOT NULL,
    report_datetime DATETIME NOT NULL,
    detail TEXT NOT NULL,
    initial_solution TEXT NOT NULL,
    suggestion TEXT NOT NULL,
    status VARCHAR(255) DEFAULT 'ยังไม่ดำเนินการ',
    consent TINYINT(1) DEFAULT 0 COMMENT '0=ยังไม่ยินยอม, 1=ยินยอมแล้ว',
    consent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ตารางสรุปผลการรายงาน (risk_reports)
-- ============================================================
CREATE TABLE IF NOT EXISTS risk_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    risk_id INT NOT NULL,
    corrective_action TEXT DEFAULT NULL COMMENT 'มาตรการแก้ไข',
    responsible_person VARCHAR(255) DEFAULT NULL COMMENT 'ผู้รับผิดชอบ',
    follow_up TEXT DEFAULT NULL COMMENT 'การติดตามผล',
    expected_outcome TEXT DEFAULT NULL COMMENT 'ผลที่คาดว่าจะได้รับ',
    report_file VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์แนบ',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (risk_id) REFERENCES risks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. ข้อมูลเริ่มต้น: ผู้ดูแลระบบ
-- ============================================================
-- รหัสผ่าน: admin123 (เข้ารหัสด้วย bcrypt)
INSERT INTO users (reporter_code, username, fullname, email, phone, department, password, role, avatar) VALUES
('ADMIN001', 'admin', 'ผู้ดูแลระบบ', 'admin@example.com', '080-000-0000', 'กลุ่มผู้บริหาร', '$2y$10$MpLkZ29y4ExpVl.b0nswaunwi5e3W/O2xaSacK3DLmXCpyhjehQZi', 'admin', 'default.png');