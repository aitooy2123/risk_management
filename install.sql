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
    enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0=ถูกระงับ, 1=ใช้งานได้',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    UNIQUE KEY reporter_code (reporter_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ตาราง Remember Me Tokens (user_tokens)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_token (token),
    KEY idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ตารางบันทึกการพยายามเข้าสู่ระบบ (login_attempts)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_username (username),
    KEY idx_ip_address (ip_address),
    KEY idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ตารางรหัสผ่านลืม (password_resets)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. ตารางความเสี่ยง (risks)
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
    KEY idx_user_id (user_id),
    KEY idx_status (status),
    KEY idx_risk_type (risk_type),
    KEY idx_severity (severity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. ตารางสรุปผลการรายงาน (risk_reports)
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
    KEY idx_risk_id (risk_id),
    KEY idx_created_by (created_by),
    FOREIGN KEY (risk_id) REFERENCES risks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. ข้อมูลเริ่มต้น: ผู้ดูแลระบบ
-- ============================================================
-- รหัสผ่าน: admin123 (เข้ารหัสด้วย bcrypt)
INSERT INTO users (reporter_code, username, fullname, email, phone, department, password, role, enabled, avatar) VALUES
('ADMIN001', 'admin', 'ผู้ดูแลระบบ', 'admin@risk8.go.th', '080-000-0000', 'กลุ่มผู้บริหาร', '$2y$10$MpLkZ29y4ExpVl.b0nswaunwi5e3W/O2xaSacK3DLmXCpyhjehQZi', 'admin', 1, 'default.png')
ON DUPLICATE KEY UPDATE username=username;

-- ============================================================
-- ข้อมูลตัวอย่าง: ผู้ใช้ทั่วไป (ถ้าต้องการ)
-- ============================================================
-- รหัสผ่าน: user1234
-- INSERT INTO users (reporter_code, username, fullname, email, phone, department, password, role, enabled, avatar) VALUES
-- ('USER001', 'user1', 'สมชาย ใจดี', 'user1@risk8.go.th', '081-111-1111', 'กลุ่มงานเวชกรรมสังคม', '$2y$10$YourHashedPasswordHere', 'user', 1, 'default.png')
-- ON DUPLICATE KEY UPDATE username=username;