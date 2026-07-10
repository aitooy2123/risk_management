-- ============================================================
-- install.sql - ฐานข้อมูลระบบบริหารความเสี่ยง (Risk Management)
-- ใช้กับ MySQL / MariaDB
-- พัฒนาสำหรับ: ศูนย์อนามัยที่ 8 อุดรธานี
-- Version: 2.2 (ปรับปรุง reporter_code เป็นรูปแบบ R10001 และ severity แสดงแบบเต็ม)
-- ============================================================

-- สร้างฐานข้อมูล
CREATE DATABASE IF NOT EXISTS risk_management
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE risk_management;

-- ============================================================
-- 1. ตารางผู้ใช้ (users)
-- ============================================================
DROP TABLE IF EXISTS user_tokens;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS risk_reports;
DROP TABLE IF EXISTS risks;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_code VARCHAR(255) NOT NULL COMMENT 'รหัสผู้รายงาน เช่น R10001',
    username VARCHAR(50) NOT NULL,
    fullname VARCHAR(255) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    department VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0=ถูกระงับ, 1=ใช้งานได้',
    avatar VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    UNIQUE KEY reporter_code (reporter_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ตาราง Remember Me Tokens (user_tokens)
-- ============================================================
CREATE TABLE user_tokens (
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
CREATE TABLE login_attempts (
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
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_token (token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. ตารางความเสี่ยง (risks)
-- ============================================================
CREATE TABLE risks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reporter_code VARCHAR(50) NOT NULL COMMENT 'รหัสผู้รายงาน',
    unit VARCHAR(100) NOT NULL COMMENT 'กลุ่มงาน/หน่วยงาน',
    unit_other VARCHAR(100) DEFAULT NULL COMMENT 'หน่วยงานอื่นๆ (กรณีเลือกอื่นๆ)',
    risk_type VARCHAR(100) NOT NULL COMMENT 'ประเภทความเสี่ยง',
    risk_type_other VARCHAR(100) DEFAULT NULL COMMENT 'ประเภทความเสี่ยงอื่นๆ',
    severity VARCHAR(50) NOT NULL COMMENT 'ระดับความรุนแรง (A-F)',
    severity_other VARCHAR(100) DEFAULT NULL COMMENT 'ระดับความรุนแรงอื่นๆ',
    event_datetime DATETIME NOT NULL COMMENT 'วันที่เกิดเหตุการณ์',
    report_datetime DATETIME NOT NULL COMMENT 'วันที่รายงาน',
    detail TEXT NOT NULL COMMENT 'รายละเอียดเหตุการณ์',
    initial_solution TEXT NOT NULL COMMENT 'แนวทางแก้ไขเบื้องต้น',
    suggestion TEXT NOT NULL COMMENT 'ข้อเสนอแนะ',
    status VARCHAR(255) DEFAULT 'ยังไม่ดำเนินการ' COMMENT 'สถานะการดำเนินการ',
    consent TINYINT(1) DEFAULT 0 COMMENT '0=ยังไม่ยินยอม, 1=ยินยอมแล้ว',
    consent_at DATETIME DEFAULT NULL COMMENT 'วันที่ให้ความยินยอม',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_status (status),
    KEY idx_risk_type (risk_type),
    KEY idx_severity (severity),
    KEY idx_unit (unit),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. ตารางสรุปผลการรายงาน (risk_reports)
-- ============================================================
CREATE TABLE risk_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    risk_id INT NOT NULL,
    corrective_action TEXT DEFAULT NULL COMMENT 'มาตรการแก้ไข',
    responsible_person VARCHAR(255) DEFAULT NULL COMMENT 'ผู้รับผิดชอบ',
    follow_up TEXT DEFAULT NULL COMMENT 'การติดตามผล',
    expected_outcome TEXT DEFAULT NULL COMMENT 'ผลที่คาดว่าจะได้รับ',
    report_file VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์แนบ',
    created_by INT NOT NULL COMMENT 'ผู้สร้างรายงาน',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_risk_id (risk_id),
    KEY idx_created_by (created_by),
    FOREIGN KEY (risk_id) REFERENCES risks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. ข้อมูลเริ่มต้น: ผู้ใช้ระบบ
-- ============================================================

-- ผู้ดูแลระบบ (รหัสผ่าน: admin123)
INSERT INTO users (reporter_code, username, fullname, email, phone, department, password, role, enabled, avatar) VALUES
('R10001', 'admin', 'ผู้ดูแลระบบ', 'admin@health8.go.th', '080-000-0000', 'กลุ่มอำนวยการ', '$2y$10$MpLkZ29y4ExpVl.b0nswaunwi5e3W/O2xaSacK3DLmXCpyhjehQZi', 'admin', 1, 'default.png');

-- root user (รหัสผ่าน: root1234)
INSERT INTO users (reporter_code, username, fullname, email, phone, department, password, role, enabled, avatar) VALUES
('R10002', 'root', 'ผู้ดูแลระบบสูงสุด', 'root@health8.go.th', '080-111-1111', 'กลุ่มอำนวยการ', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'default.png');

-- ผู้ใช้ทั่วไป ตามกลุ่มงานจริงของ ศูนย์อนามัยที่ 8
INSERT INTO users (reporter_code, username, fullname, email, phone, department, password, role, enabled, avatar) VALUES
('R10003', 'somchai', 'สมชาย ใจดี', 'somchai@health8.go.th', '081-111-1111', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'user', 1, 'default.png'),
('R10004', 'somsri', 'สมศรี รักงาน', 'somsri@health8.go.th', '082-222-2222', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'user', 1, 'default.png'),
('R10005', 'prasert', 'ประเสริฐ มั่นคง', 'prasert@health8.go.th', '083-333-3333', 'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'user', 1, 'default.png'),
('R10006', 'wilai', 'วิไล พัฒนา', 'wilai@health8.go.th', '084-444-4444', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'user', 1, 'default.png'),
('R10007', 'sompong', 'สมพงษ์ ก้าวหน้า', 'sompong@health8.go.th', '085-555-5555', 'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'user', 1, 'default.png');

-- ============================================================
-- 8. ข้อมูลตัวอย่าง: ความเสี่ยง 30 รายการ
-- อ้างอิงตามข้อมูลจริงจากตารางรายการความเสี่ยง
-- severity: A, B, C, D, E, F
-- ============================================================

-- ============================================================
-- กลุ่มที่ 1: ความเสี่ยงทางด้านกลยุทธ์ (Strategic Risk) - 5 รายการ
-- ============================================================

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(2, 'R10002', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน', 'ความเสี่ยงทางด้านกลยุทธ์', 'E', '2026-06-01 09:00:00', '2026-06-01 14:00:00', 
'แผนยุทธศาสตร์การพัฒนาสุขภาพวัยทำงาน 5 ปี ไม่สอดคล้องกับนโยบายกระทรวงสาธารณสุขที่ปรับเปลี่ยนใหม่ ทำให้ไม่สามารถขออนุมัติงบประมาณได้', 
'ประชุมคณะกรรมการทบทวนแผนยุทธศาสตร์เร่งด่วน และปรับแผนให้สอดคล้องกับนโยบายใหม่ภายใน 2 สัปดาห์', 
'ควรมีระบบติดตามและวิเคราะห์นโยบายกระทรวงแบบ Real-time และปรับแผนยุทธศาสตร์ทุก 6 เดือน', 'ยุติ', 1, '2026-06-01 15:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(3, 'R10003', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', 'ความเสี่ยงทางด้านกลยุทธ์', 'D', '2026-06-10 10:00:00', '2026-06-10 11:30:00',
'โครงการส่งเสริมสุขภาพวัยเรียนปี 2569 ไม่ได้รับการจัดสรรงบประมาณ เนื่องจากแผนงานไม่ชัดเจนและขาดตัวชี้วัดที่เป็นรูปธรรม', 
'ปรับแผนโครงการใหม่โดยเพิ่ม KPI ที่วัดผลได้ชัดเจน และเสนอขออนุมัติงบประมาณรอบพิเศษ', 
'ควรจัดทำแผนงานล่วงหน้า 1 ปีพร้อมตัวชี้วัดที่ชัดเจน และนำเสนอคณะกรรมการพิจารณางบประมาณก่อนกำหนด', 'กำลังดำเนินการ', 1, '2026-06-10 13:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน', 'ความเสี่ยงทางด้านกลยุทธ์', 'A', '2026-06-30 08:00:00', '2026-06-30 09:00:00',
'ผลการประเมินความพึงพอใจของบุคลากรต่อแผนพัฒนากำลังคนต่ำกว่าเกณฑ์ที่กำหนด (60%) เนื่องจากขาดการมีส่วนร่วมในการจัดทำแผน', 
'จัดประชุม Focus Group รับฟังความคิดเห็นบุคลากรทุกระดับ และปรับแผนพัฒนากำลังคนให้ตรงตามความต้องการ', 
'ควรจัดทำแบบสำรวจความต้องการพัฒนาตนเองประจำปี และให้บุคลากรมีส่วนร่วมในการออกแบบหลักสูตรอบรม', 'ดำเนินการแล้ว', 1, '2026-07-01 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(5, 'R10005', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ', 'ความเสี่ยงทางด้านกลยุทธ์', 'C', '2026-07-05 13:00:00', '2026-07-05 14:30:00',
'แผนพัฒนาระบบดูแลผู้สูงอายุระยะยาว (Long Term Care) ไม่สามารถดำเนินการได้ตามเป้าหมาย เนื่องจากขาดความร่วมมือจากองค์กรปกครองส่วนท้องถิ่น', 
'จัดประชุมชี้แจงและสร้างความร่วมมือกับ อบต./เทศบาล 10 แห่ง พร้อมลงนาม MOU ความร่วมมือ', 
'ควรจัดทำแผนปฏิบัติการร่วมกับ อปท. ล่วงหน้า และมีระบบติดตามประเมินผลทุกไตรมาส', 'อยู่ระหว่างดำเนินการ', 0, NULL);

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(1, 'R10001', 'กลุ่มอำนวยการ', 'ความเสี่ยงทางด้านกลยุทธ์', 'B', '2026-06-15 10:00:00', '2026-06-15 11:00:00',
'การถ่ายทอดตัวชี้วัดจากระดับกรมลงสู่ระดับศูนย์ไม่ชัดเจน ทำให้เกิดความสับสนในการรายงานผล', 
'จัดประชุมชี้แจงตัวชี้วัดกับทุกกลุ่มงาน และจัดทำคู่มือการรายงานผลที่เป็นมาตรฐานเดียวกัน', 
'ควรมีระบบการสื่อสารตัวชี้วัดแบบ Cascade จากบนลงล่าง พร้อมปฏิทินการรายงานที่ชัดเจน', 'ดำเนินการแล้ว', 1, '2026-06-16 08:00:00');

-- ============================================================
-- กลุ่มที่ 2: ความเสี่ยงทางด้านการปฏิบัติงาน (Operational Risk) - 5 รายการ
-- ============================================================

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(6, 'R10006', 'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม', 'ความเสี่ยงทางด้านการปฏิบัติงาน', 'D', '2026-05-10 08:30:00', '2026-05-10 10:00:00',
'เจ้าหน้าที่ขาดทักษะการใช้โปรแกรมวิเคราะห์ข้อมูลสุขภาพ SPSS ทำให้การวิเคราะห์ข้อมูลคลาดเคลื่อนและรายงานผิดพลาด', 
'ส่งเจ้าหน้าที่อบรมการใช้ SPSS แบบเร่งด่วน 5 วัน และจัดให้มีพี่เลี้ยงดูแล', 
'จัดอบรมการใช้โปรแกรมวิเคราะห์ข้อมูลเป็นประจำทุกปี และมีระบบตรวจสอบคุณภาพข้อมูลก่อนออกรายงาน', 'ดำเนินการแล้ว', 1, '2026-05-11 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน', 'ความเสี่ยงทางด้านการปฏิบัติงาน', 'B', '2026-07-07 09:00:00', '2026-07-07 10:00:00',
'ระบบสารบรรณอิเล็กทรอนิกส์ล่ม ทำให้ไม่สามารถส่งหนังสือราชการได้ทันตามกำหนด กระทบ 3 หน่วยงาน', 
'ใช้ระบบ Manual ชั่วคราวและประสานงานทางโทรศัพท์แทน แจ้ง IT ดำเนินการแก้ไขด่วน', 
'จัดทำระบบสำรองฉุกเฉินและแผนรองรับเมื่อระบบขัดข้อง (BCP) สำหรับงานสารบรรณ', 'ดำเนินการแล้ว', 1, '2026-07-07 14:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(2, 'R10002', 'กลุ่มอำนวยการ', 'ความเสี่ยงทางด้านการปฏิบัติงาน', 'F', '2026-07-28 11:00:00', '2026-07-28 12:00:00',
'เจ้าหน้าที่การเงินลาออกกะทันหันโดยไม่ส่งมอบงาน ทำให้ไม่สามารถเบิกจ่ายงบประมาณได้ กระทบ 15 โครงการ', 
'แต่งตั้งคณะทำงานเฉพาะกิจเข้ามาดำเนินการแทนชั่วคราว และเร่งสรรหาเจ้าหน้าที่ใหม่', 
'จัดทำแผนสืบทอดตำแหน่ง (Succession Plan) ทุกตำแหน่งสำคัญ และมีระบบส่งมอบงานที่เป็นมาตรฐาน', 'ดำเนินการแล้ว', 1, '2026-07-28 15:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(5, 'R10005', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ', 'ความเสี่ยงทางด้านการปฏิบัติงาน', 'C', '2026-06-20 09:30:00', '2026-06-20 10:30:00',
'การลงพื้นที่เยี่ยมบ้านผู้สูงอายุติดเตียงไม่เป็นไปตามแผน เนื่องจากพาหนะไม่เพียงพอและเส้นทางบางพื้นที่เข้าถึงยาก', 
'ปรับตารางการลงพื้นที่ใหม่โดยจัดกลุ่มตามเส้นทาง และขอความร่วมมือจาก อสม. ในพื้นที่ช่วยสนับสนุน', 
'จัดหาพาหนะเพิ่มเติม 2 คันสำหรับงานชุมชน และทำแผนที่เส้นทางการลงพื้นที่อย่างเป็นระบบ', 'อยู่ระหว่างดำเนินการ', 1, '2026-06-21 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(3, 'R10003', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', 'ความเสี่ยงทางด้านการปฏิบัติงาน', 'A', '2026-06-15 14:00:00', '2026-06-15 15:00:00',
'อุปกรณ์ตรวจสุขภาพนักเรียน (เครื่องวัดสายตา, เครื่องชั่งน้ำหนัก) ชำรุด 3 เครื่อง ทำให้ตรวจคัดกรองล่าช้า', 
'ยืมอุปกรณ์จากกลุ่มงานอื่นชั่วคราว และส่งเครื่องที่ชำรุดเข้าซ่อมด่วน', 
'จัดทำแผนบำรุงรักษาเชิงป้องกัน (PM) ทุก 6 เดือน และจัดหาอุปกรณ์สำรองอย่างน้อย 1 ชุด', 'ดำเนินการแล้ว', 1, '2026-06-16 08:00:00');

-- ============================================================
-- กลุ่มที่ 3: ความเสี่ยงทางด้านการเงิน (Financial Risk) - 5 รายการ
-- อ้างอิงจากตาราง: รายการที่ 11-15
-- ============================================================

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(3, 'R10003', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน', 'ความเสี่ยงทางด้านการเงิน', 'D', '2026-07-07 08:00:00', '2026-07-07 09:00:00',
'งบประมาณโครงการส่งเสริมสุขภาพวัยทำงานถูกตัดลด 30% จากงบกลางปี ทำให้กิจกรรมที่วางแผนไว้ 5 กิจกรรมไม่สามารถดำเนินการได้', 
'ประชุมคณะทำงานเพื่อปรับแผนการใช้จ่ายใหม่ จัดลำดับความสำคัญกิจกรรมที่จำเป็นเร่งด่วนก่อน', 
'ควรมีการบริหารความเสี่ยงด้านงบประมาณโดยจัดทำแผนสำรองและหาแหล่งทุนสนับสนุนภายนอก', 'ยังไม่ดำเนินการ', 0, NULL);

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(1, 'R10001', 'กลุ่มอำนวยการ', 'ความเสี่ยงทางด้านการเงิน', 'E', '2026-05-25 10:00:00', '2026-05-25 11:00:00',
'การเบิกจ่ายงบประมาณไตรมาส 2 ต่ำกว่าเป้าหมาย 40% เสี่ยงถูกตัดงบประมาณไตรมาส 3-4', 
'เร่งรัดทุกกลุ่มงานให้เบิกจ่ายตามแผนที่กำหนด จัดทำปฏิทินเร่งรัดการเบิกจ่ายรายสัปดาห์', 
'จัดอบรมระเบียบพัสดุและการเงินแก่ผู้ปฏิบัติงาน และมีระบบติดตามการเบิกจ่ายแบบ Real-time', 'ดำเนินการแล้ว', 1, '2026-05-26 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน', 'ความเสี่ยงทางด้านการเงิน', 'C', '2026-06-25 15:00:00', '2026-06-25 16:00:00',
'พัสดุครุภัณฑ์ที่จัดซื้อมาไม่ตรงตามสเปคที่กำหนด (Specification) ทำให้สูญเสียงบประมาณ 150,000 บาท', 
'ส่งเรื่องคืนพัสดุและขอเปลี่ยนเป็นรุ่นที่ถูกต้องตามสเปค ดำเนินการตามระเบียบพัสดุ', 
'แต่งตั้งคณะกรรมการตรวจรับพัสดุที่มีความเชี่ยวชาญเฉพาะ และตรวจสอบสเปคให้ละเอียดก่อนลงนาม', 'ดำเนินการแล้ว', 1, '2026-06-26 08:30:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(2, 'R10002', 'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม', 'ความเสี่ยงทางด้านการเงิน', 'B', '2026-06-10 11:00:00', '2026-06-10 12:00:00',
'ค่าใช้จ่ายในการเดินทางไปราชการเกินวงเงินที่กำหนด เนื่องจากราคาน้ำมันและที่พักปรับตัวสูงขึ้น', 
'ขออนุมัติขยายวงเงินค่าใช้จ่ายในการเดินทางชั่วคราว และปรับแผนการเดินทางให้ใช้พื้นที่ใกล้เคียงก่อน', 
'ควรปรับปรุงระเบียบการเบิกจ่ายให้สอดคล้องกับสถานการณ์ปัจจุบัน และใช้ระบบ Video Conference ทดแทนการเดินทาง', 'ดำเนินการแล้ว', 1, '2026-06-11 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', 'ความเสี่ยงทางด้านการเงิน', 'A', '2026-07-01 09:00:00', '2026-07-01 10:00:00',
'ใบเสร็จรับเงินค่าใช้จ่ายในการจัดอบรมสูญหาย 1 ฉบับ มูลค่า 5,000 บาท ทำให้เบิกจ่ายไม่ได้', 
'จัดทำใบแทนใบเสร็จรับเงินตามระเบียบราชการ และให้ผู้เกี่ยวข้องลงนามรับรอง', 
'ควรใช้ระบบ e-Receipt และจัดเก็บเอกสารการเงินในระบบ Digital ป้องกันการสูญหาย', 'ดำเนินการแล้ว', 1, '2026-07-02 08:00:00');

-- ============================================================
-- กลุ่มที่ 4: ความเสี่ยงทางด้านกฎหมาย (Compliance Risk) - 5 รายการ
-- อ้างอิงจากตาราง: รายการที่ 16-20
-- ============================================================

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(1, 'R10001', 'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม', 'ความเสี่ยงทางด้านกฎหมาย', 'E', '2026-07-07 08:30:00', '2026-07-07 09:30:00',
'ไม่ปฏิบัติตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA) เนื่องจากไม่มีระบบจัดเก็บและทำลายข้อมูลผู้รับบริการ', 
'แต่งตั้ง DPO (Data Protection Officer) และจัดทำนโยบาย PDPA ของหน่วยงาน พร้อมอบรมบุคลากร', 
'จัดทำระบบสารสนเทศที่รองรับ PDPA อย่างสมบูรณ์และตรวจสอบ Compliance ทุก 6 เดือน', 'ยุติ', 1, '2026-07-07 14:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน', 'ความเสี่ยงทางด้านกฎหมาย', 'D', '2026-06-05 10:00:00', '2026-06-05 11:00:00',
'การจัดซื้อจัดจ้างไม่เป็นไปตาม พ.ร.บ. จัดซื้อจัดจ้างฯ เนื่องจากแบ่งซื้อของเพื่อหลีกเลี่ยงการจัดซื้อด้วยวิธี e-Bidding', 
'ยกเลิกการจัดซื้อครั้งนี้และดำเนินการใหม่ให้ถูกต้องตามระเบียบ สอบสวนผู้เกี่ยวข้อง', 
'อบรมระเบียบจัดซื้อจัดจ้างแก่ผู้ปฏิบัติงานทุกคน และมีระบบตรวจสอบภายในที่เข้มแข็ง', 'ดำเนินการแล้ว', 1, '2026-06-06 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(3, 'R10003', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน', 'ความเสี่ยงทางด้านกฎหมาย', 'C', '2026-06-20 14:00:00', '2026-06-20 15:00:00',
'ใบอนุญาตประกอบวิชาชีพของนักกายภาพบำบัดหมดอายุ 2 รายโดยไม่มีการต่ออายุ ทำให้เสี่ยงผิดกฎหมายวิชาชีพ', 
'แจ้งให้ผู้เกี่ยวข้องดำเนินการต่ออายุใบอนุญาตทันที และงดปฏิบัติงานชั่วคราวจนกว่าจะต่ออายุแล้วเสร็จ', 
'จัดทำระบบติดตามวันหมดอายุใบอนุญาตประกอบวิชาชีพของบุคลากรทุกคน และแจ้งเตือนล่วงหน้า 3 เดือน', 'ดำเนินการแล้ว', 1, '2026-06-21 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(5, 'R10005', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ', 'ความเสี่ยงทางด้านกฎหมาย', 'B', '2026-07-10 09:00:00', '2026-07-10 10:00:00',
'แบบฟอร์มยินยอมให้ข้อมูลสุขภาพ (Consent Form) ไม่ครอบคลุมตามข้อกำหนด PDPA ฉบับใหม่', 
'ปรับปรุงแบบฟอร์ม Consent Form ให้เป็นไปตามกฎหมายใหม่ และให้ผู้รับบริการลงนามใหม่ทุกราย', 
'ทบทวนและปรับปรุงแบบฟอร์มกฎหมายทุกฉบับให้เป็นปัจจุบันทุก 6 เดือน', 'กำลังดำเนินการ', 1, '2026-07-11 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(2, 'R10002', 'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม', 'ความเสี่ยงทางด้านกฎหมาย', 'A', '2026-06-15 13:00:00', '2026-06-15 14:00:00',
'ป้ายประชาสัมพันธ์โครงการไม่เป็นไปตามมาตรฐานที่กำหนดตามระเบียบสำนักนายกรัฐมนตรี', 
'แก้ไขป้ายประชาสัมพันธ์ให้ถูกต้องตามระเบียบ และตรวจสอบป้ายอื่นๆ ทั้งหมด', 
'จัดทำ Template ป้ายประชาสัมพันธ์ที่ได้มาตรฐานและผ่านการตรวจสอบจากนิติกรก่อนใช้งาน', 'ดำเนินการแล้ว', 1, '2026-06-16 08:00:00');

-- ============================================================
-- กลุ่มที่ 5: ความเสี่ยงด้านสิ่งแวดล้อม (Environmental Risk) - 5 รายการ
-- ============================================================

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', 'ความเสี่ยงด้านสิ่งแวดล้อม', 'C', '2026-07-07 10:00:00', '2026-07-07 11:00:00',
'น้ำประปาในโรงเรียนไม่สะอาด มีตะกอนและกลิ่นคลอรีนสูง เสี่ยงกระทบสุขภาพนักเรียน 500 คน', 
'ประสานงานการประปาส่วนภูมิภาคตรวจสอบคุณภาพน้ำ และจัดหาน้ำดื่มสะอาดให้นักเรียนชั่วคราว', 
'ติดตั้งระบบกรองน้ำในโรงเรียนและตรวจสอบคุณภาพน้ำทุก 3 เดือน', 'ยังไม่ดำเนินการ', 0, NULL);

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(2, 'R10002', 'กลุ่มอำนวยการ', 'ความเสี่ยงด้านสิ่งแวดล้อม', 'C', '2026-06-30 08:00:00', '2026-06-30 09:00:00',
'เครื่องปรับอากาศในห้องประชุมใหญ่เสีย 3 เครื่องจาก 5 เครื่อง ประชุมนาน 4 ชั่วโมง อุณหภูมิห้อง 34°C', 
'ย้ายการประชุมไปห้องประชุมเล็ก 2 ห้อง ใช้พัดลมช่วยระบายอากาศ และเรียกช่างซ่อมด่วน', 
'จัดทำแผนเปลี่ยนเครื่องปรับอากาศที่อายุเกิน 10 ปี และเพิ่มระบบระบายอากาศสำรอง', 'ดำเนินการแล้ว', 1, '2026-07-01 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(1, 'R10001', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', 'ความเสี่ยงด้านสิ่งแวดล้อม', 'E', '2026-07-07 14:00:00', '2026-07-07 15:00:00',
'พบสารตะกั่วปนเปื้อนในสนามเด็กเล่นโรงเรียนอนุบาลในเขตพื้นที่รับผิดชอบ เกินมาตรฐาน 3 เท่า', 
'ปิดพื้นที่สนามเด็กเล่นทันที แจ้งผู้บริหารโรงเรียนและหน่วยงานที่เกี่ยวข้อง เก็บตัวอย่างตรวจซ้ำ', 
'จัดทำแผนฟื้นฟูสภาพแวดล้อมในโรงเรียนและสุ่มตรวจสารปนเปื้อนในโรงเรียนทุกแห่งในพื้นที่', 'กำลังดำเนินการ', 1, '2026-07-08 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(3, 'R10003', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน', 'ความเสี่ยงด้านสิ่งแวดล้อม', 'B', '2026-06-25 10:30:00', '2026-06-25 11:30:00',
'ที่จอดรถไม่เพียงพอสำหรับเจ้าหน้าที่และผู้มาติดต่อ 200 คันต่อวัน ทำให้จอดรถซ้อนคันและกีดขวางทางเข้าออก', 
'ปรับปรุงพื้นที่ว่างเปล่าเป็นลานจอดรถชั่วคราวเพิ่ม 50 คัน และจัดระบบจราจรในหน่วยงาน', 
'เสนอของบประมาณสร้างอาคารจอดรถ 3 ชั้น และส่งเสริมการใช้รถร่วมกัน (Car Pool)', 'อยู่ระหว่างดำเนินการ', 0, NULL);

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน', 'ความเสี่ยงด้านสิ่งแวดล้อม', 'D', '2026-07-05 07:00:00', '2026-07-05 08:00:00',
'หม้อแปลงไฟฟ้าภายในหน่วยงานระเบิดเสียงดัง ทำให้ไฟฟ้าดับทั้งอาคาร 8 ชั่วโมง อุปกรณ์การแพทย์เสียหาย', 
'ประสานการไฟฟ้าส่วนภูมิภาคซ่อมแซมเร่งด่วน ใช้เครื่องสำรองไฟ (UPS) สำหรับอุปกรณ์ที่จำเป็น', 
'ติดตั้งเครื่องสำรองไฟอัตโนมัติ (Generator) สำหรับทั้งอาคาร และบำรุงรักษาหม้อแปลงไฟฟ้าทุกปี', 'ดำเนินการแล้ว', 1, '2026-07-06 08:00:00');

-- ============================================================
-- กลุ่มที่ 6: ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข - 5 รายการ
-- ============================================================

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(5, 'R10005', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ', 'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข', 'F', '2026-07-07 07:30:00', '2026-07-07 08:30:00',
'ระบบนัดหมายออนไลน์สำหรับผู้สูงอายุใช้งานยาก เนื่องจาก UI/UX ไม่เป็นมิตรกับผู้สูงอายุ ทำให้ผู้สูงอายุ 60% ไม่สามารถใช้งานได้', 
'เปิดช่องทางนัดหมายทางโทรศัพท์เพิ่ม 2 คู่สาย และมีเจ้าหน้าที่ช่วยเหลือการนัดหมายหน้างาน', 
'พัฒนา Mobile App ที่มีฟังก์ชั่นตัวอักษรใหญ่และเสียงอ่านภาษาไทย พร้อมอบรม อสม. สอนใช้แอพ', 'ดำเนินการแล้ว', 1, '2026-07-07 14:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(1, 'R10001', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ', 'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข', 'D', '2026-06-30 14:00:00', '2026-06-30 15:00:00',
'ระบบส่งต่อผู้ป่วยระหว่าง รพ.สต. กับโรงพยาบาลแม่ข่ายไม่มีประสิทธิภาพ ข้อมูลสูญหายระหว่างทาง 20%', 
'ใช้ระบบ LINE Official ในการสื่อสารข้อมูลเบื้องต้นระหว่างหน่วยบริการ และส่งเอกสารตามไปภายหลัง', 
'พัฒนา Health Information Exchange (HIE) ที่เชื่อมโยงข้อมูลระหว่างหน่วยบริการทุกระดับ', 'ดำเนินการแล้ว', 1, '2026-07-01 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(4, 'R10004', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน', 'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข', 'C', '2026-06-20 09:00:00', '2026-06-20 10:00:00',
'เครื่องวัดความดันโลหิตอัตโนมัติให้ค่าที่คลาดเคลื่อนเกิน ±10 mmHg เมื่อเทียบกับเครื่องวัดแบบปรอท', 
'ปรับเทียบ (Calibrate) เครื่องวัดใหม่ทั้งหมด 15 เครื่อง และทดสอบความแม่นยำกับค่ามาตรฐาน', 
'จัดทำแผนสอบเทียบเครื่องมือแพทย์ทุก 6 เดือน และเปลี่ยนเครื่องที่อายุเกิน 5 ปี', 'ดำเนินการแล้ว', 1, '2026-06-21 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(6, 'R10006', 'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม', 'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข', 'B', '2026-07-01 11:00:00', '2026-07-01 12:00:00',
'ถังขยะติดเชื้อในชุมชนไม่มีการแยกประเภทอย่างถูกต้อง ประชาชนทิ้งขยะอันตรายปะปนกับขยะทั่วไป', 
'จัดอบรม อสม. และผู้นำชุมชนเรื่องการคัดแยกขยะติดเชื้อ และจัดหาถังขยะติดเชื้อให้ชุมชน 50 ใบ', 
'รณรงค์ให้ความรู้ผ่านหอกระจายข่าวทุกสัปดาห์ และตั้งจุดรวบรวมขยะอันตรายในชุมชนทุก 1 กม.', 'ดำเนินการแล้ว', 1, '2026-07-02 08:00:00');

INSERT INTO risks (user_id, reporter_code, unit, risk_type, severity, event_datetime, report_datetime, detail, initial_solution, suggestion, status, consent, consent_at) VALUES
(3, 'R10003', 'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน', 'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข', 'A', '2026-07-10 15:00:00', '2026-07-10 16:00:00',
'โปรแกรมคำนวณดัชนีมวลกาย (BMI) ในระบบรายงานสุขภาพไม่รองรับหน่วยวัดแบบไทย (ซม./กก.)', 
'ปรับสูตรคำนวณในโปรแกรมให้รองรับหน่วยวัดมาตรฐานไทย และทดสอบความถูกต้องของผลลัพธ์', 
'พัฒนาโปรแกรมให้รองรับหลายหน่วยวัดและมีฟังก์ชั่นแปลงหน่วยอัตโนมัติ', 'กำลังดำเนินการ', 0, NULL);

-- ============================================================
-- 9. ข้อมูลตัวอย่าง: Risk Reports 10 รายการ
-- สอดคล้องกับรายการที่มีการรายงานผลแล้ว
-- ============================================================

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(11, 'ประชุมคณะทำงานเพื่อปรับแผนการใช้จ่ายใหม่ จัดลำดับความสำคัญกิจกรรมที่จำเป็นเร่งด่วนก่อน', 'นายสมชาย ใจดี', 'รอการอนุมัติแผนการใช้จ่ายใหม่จากคณะกรรมการ', 'สามารถดำเนินกิจกรรมสำคัญได้ 70% ภายในปีงบประมาณ', 3);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(12, 'เร่งรัดทุกกลุ่มงานให้เบิกจ่ายตามแผนที่กำหนด จัดทำปฏิทินเร่งรัดการเบิกจ่ายรายสัปดาห์', 'นางวิไล พัฒนา', 'เบิกจ่ายเพิ่มขึ้นจาก 60% เป็น 85% ภายใน 1 เดือน', 'เบิกจ่ายได้ตามเป้าหมาย 95% ภายในสิ้นไตรมาส', 1);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(13, 'ส่งเรื่องคืนพัสดุและขอเปลี่ยนเป็นรุ่นที่ถูกต้องตามสเปค ดำเนินการตามระเบียบพัสดุ', 'นายประเสริฐ มั่นคง', 'ได้รับพัสดุใหม่ที่ถูกต้องตามสเปคภายใน 2 สัปดาห์', 'การจัดซื้อจัดจ้างเป็นไปตามระเบียบ 100%', 4);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(16, 'จัดทำนโยบาย PDPA อบรมบุคลากร 200 คน และจัดทำระบบขอความยินยอมออนไลน์', 'นายประเสริฐ มั่นคง', 'ผ่านการตรวจสอบจาก สคส. เรียบร้อย ไม่พบการละเมิดข้อมูล', 'หน่วยงานปฏิบัติตาม PDPA ได้ 100% ภายใน 6 เดือน', 1);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(17, 'อบรมระเบียบพัสดุจัดซื้อจัดจ้าง จัดทำคู่มือการจัดซื้อที่ถูกต้อง และมีระบบตรวจสอบภายใน', 'นายประเสริฐ มั่นคง', 'ผ่านการตรวจสอบจาก สตง. ไม่พบข้อทักท้วงในรอบปี', 'การจัดซื้อจัดจ้างถูกต้องตามระเบียบ 100%', 4);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(22, 'ซ่อมแซมเครื่องปรับอากาศและจัดทำแผนเปลี่ยนเครื่องปรับอากาศที่อายุเกิน 10 ปี', 'นางสมศรี รักงาน', 'ซ่อมแล้วเสร็จ 3 เครื่อง อยู่ระหว่างของบประมาณเปลี่ยน 5 เครื่อง', 'ระบบปรับอากาศใช้งานได้ 100% ภายใน 2 เดือน', 3);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(26, 'ปรับปรุงระบบนัดหมายโดยเพิ่มฟังก์ชั่นตัวอักษรใหญ่และเสียงภาษาไทย อบรม อสม. 50 คน', 'นายสมพงษ์ ก้าวหน้า', 'ผู้สูงอายุใช้งานเพิ่มขึ้นจาก 40% เป็น 75% ภายใน 3 เดือน', 'ผู้สูงอายุใช้งานระบบออนไลน์ได้ 80% ภายใน 6 เดือน', 5);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(8, 'สรรหาเจ้าหน้าที่การเงินใหม่และจัดทำระบบส่งมอบงาน (Turnover Checklist) ที่เป็นมาตรฐาน', 'นายประเสริฐ มั่นคง', 'ได้เจ้าหน้าที่ใหม่ภายใน 2 สัปดาห์ ระบบส่งมอบงานแล้วเสร็จ', 'การส่งมอบงานมีประสิทธิภาพ ลดความเสี่ยงการหยุดชะงัก', 4);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(23, 'ปิดพื้นที่สนามเด็กเล่นและฟื้นฟูสภาพแวดล้อม เก็บตัวอย่างตรวจซ้ำ 5 จุด', 'นายสมชาย ใจดี', 'ผลตรวจซ้ำอยู่ในเกณฑ์ปลอดภัย เปิดใช้สนามเด็กเล่นได้ตามปกติ', 'สารตะกั่วในดินไม่เกินมาตรฐาน ภายใน 2 เดือน', 1);

INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, created_by) VALUES
(25, 'ติดตั้งเครื่องสำรองไฟฉุกเฉิน (Generator) ขนาด 500 kVA และบำรุงรักษาหม้อแปลงไฟฟ้า', 'นางสมศรี รักงาน', 'ติดตั้งแล้วเสร็จ ทดสอบระบบทำงานได้ตามมาตรฐาน', 'ไฟฟ้าสำรองพร้อมใช้งานภายใน 30 วินาที เมื่อไฟฟ้าดับ', 4);

-- ============================================================
-- สรุปข้อมูลที่นำเข้า
-- ============================================================
-- Users: 7 คน (admin 2, user 5)
-- Risks: 30 รายการ
--   - ความเสี่ยงทางด้านกลยุทธ์: 5 รายการ
--   - ความเสี่ยงทางด้านการปฏิบัติงาน: 5 รายการ
--   - ความเสี่ยงทางด้านการเงิน: 5 รายการ
--   - ความเสี่ยงทางด้านกฎหมาย: 5 รายการ
--   - ความเสี่ยงด้านสิ่งแวดล้อม: 5 รายการ
--   - ปัญหาและข้อเสนอแนะ: 5 รายการ
-- Risk Reports: 10 รายการ
-- ============================================================

-- ============================================================
-- หมายเหตุการใช้งาน
-- ============================================================
-- 1. ประเภทความเสี่ยง (risk_type) ที่ใช้ในระบบ:
--    - ความเสี่ยงทางด้านกลยุทธ์ (Strategic Risk)
--    - ความเสี่ยงทางด้านการปฏิบัติงาน (Operational Risk)
--    - ความเสี่ยงทางด้านการเงิน (Financial Risk)
--    - ความเสี่ยงทางด้านกฎหมาย (Compliance Risk)
--    - ความเสี่ยงด้านสิ่งแวดล้อม (Environmental Risk)
--    - ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข (Issues & Suggestions)
--
-- 2. ระดับความรุนแรง (severity):
--    A - ต่ำมาก, B - ต่ำ, C - ปานกลาง, D - สูง, E - สูงมาก, F - สูงสุด
--
-- 3. สถานะการดำเนินการ (status):
--    - ดำเนินการแล้ว
--    - กำลังดำเนินการ / อยู่ระหว่างดำเนินการ
--    - ยังไม่ดำเนินการ
--    - ยุติ
--
-- 4. รหัสผ่านตัวอย่าง:
--    admin / admin123
--    root / root1234
--    user ทั้งหมด / user1234
--
-- 5. การแมปข้อมูลผู้ใช้กับตาราง:
--    R10001 - admin (กลุ่มอำนวยการ)
--    R10002 - root (กลุ่มอำนวยการ)
--    R10003 - somchai (กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน)
--    R10004 - somsri (กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน)
--    R10005 - prasert (กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน)
--    R10006 - wilai (กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ)
--    R10007 - sompong (กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม)
-- ============================================================ 