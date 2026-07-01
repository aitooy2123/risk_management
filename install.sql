-- ============================================================
-- install.sql - ฐานข้อมูลระบบบริหารความเสี่ยง (Risk Management)
-- ใช้กับ MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS risk_management
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE risk_management;

-- 1. ตารางผู้ใช้
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_code VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตาราง token สำหรับ Remember Me
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตารางรีเซ็ตรหัสผ่าน
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ตารางข้อมูลความเสี่ยง
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

-- 5. ตารางบันทึก Cookie Consent
CREATE TABLE IF NOT EXISTS cookie_consent_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    consent TINYINT(1) DEFAULT 0,
    preferences JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลเริ่มต้น: ผู้ดูแลระบบ (รหัสผ่าน: password)
INSERT INTO users (reporter_code, username, password, role) VALUES
('ADMIN001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');