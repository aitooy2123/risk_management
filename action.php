<?php

/**
 * ตัวจัดการ API (Backend) – อัปเดตสมบูรณ์
 * 
 * - save_risk: รองรับ reporter_code, status
 * - check_duplicate: ตรวจสอบ reporter_code + เงื่อนไขอื่น
 * - delete_risks: ลบรายการ (Admin)
 * - save_user: รองรับ reporter_code, ตรวจสอบซ้ำ
 * - delete_user: ลบผู้ใช้ (Admin)
 * - save_cookie_consent: บันทึก Consent
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบว่าเรียกผ่าน AJAX หรือมี CSRF token
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && empty($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? '';

// ============================================
// 1. save_risk
// ============================================
if ($action == 'save_risk') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $id = $_POST['id'] ?? null;
    $reporter_code = trim($_POST['reporter_code'] ?? '');
    $unit = $_POST['unit'] ?? '';
    $unit_other = trim($_POST['unit_other'] ?? '');
    $risk_type = $_POST['risk_type'] ?? '';
    $risk_type_other = trim($_POST['risk_type_other'] ?? '');
    $severity = $_POST['severity'] ?? '';
    $severity_other = trim($_POST['severity_other'] ?? '');
    $event_datetime = $_POST['event_datetime'] ?? '';
    $report_datetime = $_POST['report_datetime'] ?? '';
    $detail = trim($_POST['detail'] ?? '');
    $initial_solution = trim($_POST['initial_solution'] ?? '');
    $suggestion = trim($_POST['suggestion'] ?? '');
    $consent = isset($_POST['consent']) ? 1 : 0;
    $status = $_POST['status'] ?? 'ยังไม่ดำเนินการ';

    // ตรวจสอบฟิลด์ที่จำเป็น
    if (empty($reporter_code) || empty($unit) || empty($event_datetime) || empty($report_datetime) || empty($detail) || empty($initial_solution) || empty($suggestion)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    // แทน "อื่นๆ" ด้วยค่าที่กรอก
    if ($unit == 'อื่นๆ') $unit = $unit_other;
    if ($risk_type == 'อื่นๆ') $risk_type = $risk_type_other;
    if ($severity == 'อื่นๆ') $severity = $severity_other;

    // ตรวจสอบข้อมูลซ้ำ (เฉพาะเพิ่มใหม่)
    if (empty($id)) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE unit = ? AND risk_type = ? AND event_datetime = ? AND reporter_code = ?");
        $checkStmt->execute([$unit, $risk_type, $event_datetime, $reporter_code]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'มีรายงานความเสี่ยงนี้อยู่แล้ว (ข้อมูลซ้ำ)']);
            exit;
        }
    }

    $consent_at = $consent ? date('Y-m-d H:i:s') : null;

    try {
        if ($id) {
            // แก้ไข
            $isAdmin = isAdmin() ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE risks SET 
                reporter_code = ?, unit = ?, unit_other = ?, risk_type = ?, risk_type_other = ?,
                severity = ?, severity_other = ?, event_datetime = ?, report_datetime = ?,
                detail = ?, initial_solution = ?, suggestion = ?, consent = ?, consent_at = ?,
                status = ?
                WHERE id = ? AND (user_id = ? OR ?)");
            $stmt->execute([
                $reporter_code, $unit, $unit_other, $risk_type, $risk_type_other,
                $severity, $severity_other, $event_datetime, $report_datetime,
                $detail, $initial_solution, $suggestion, $consent, $consent_at,
                $status,
                $id, $_SESSION['user_id'], $isAdmin
            ]);
            if ($stmt->rowCount() == 0) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลหรือไม่มีสิทธิ์แก้ไข']);
                exit;
            }
        } else {
            // เพิ่มใหม่
            $stmt = $pdo->prepare("INSERT INTO risks 
                (user_id, reporter_code, unit, unit_other, risk_type, risk_type_other, severity, severity_other,
                 event_datetime, report_datetime, detail, initial_solution, suggestion, consent, consent_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'], $reporter_code, $unit, $unit_other, $risk_type, $risk_type_other,
                $severity, $severity_other, $event_datetime, $report_datetime, $detail, $initial_solution, $suggestion,
                $consent, $consent_at, $status
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// 2. check_duplicate
// ============================================
if ($action == 'check_duplicate') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }

    $id = $_POST['id'] ?? null;
    $unit = $_POST['unit'] ?? '';
    $unit_other = trim($_POST['unit_other'] ?? '');
    $risk_type = $_POST['risk_type'] ?? '';
    $risk_type_other = trim($_POST['risk_type_other'] ?? '');
    $event_datetime = $_POST['event_datetime'] ?? '';
    $reporter_code = trim($_POST['reporter_code'] ?? '');

    if ($unit == 'อื่นๆ') $unit = $unit_other;
    if ($risk_type == 'อื่นๆ') $risk_type = $risk_type_other;

    if (!empty($id)) {
        echo json_encode(['duplicate' => false]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE unit = ? AND risk_type = ? AND event_datetime = ? AND reporter_code = ?");
    $stmt->execute([$unit, $risk_type, $event_datetime, $reporter_code]);
    $count = $stmt->fetchColumn();

    echo json_encode([
        'duplicate' => ($count > 0),
        'message' => 'มีรายงานความเสี่ยงนี้อยู่แล้ว'
    ]);
    exit;
}

// ============================================
// 3. delete_risks
// ============================================
if ($action == 'delete_risks') {
    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $ids = $input['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรายการที่เลือก']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM risks WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo json_encode(['success' => true, 'message' => 'ลบข้อมูล ' . $stmt->rowCount() . ' รายการสำเร็จ']);
    exit;
}

// ============================================
// 4. save_user (อัปเดตรองรับ reporter_code)
// ============================================
if ($action == 'save_user') {
    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $id = $_POST['id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $reporter_code = trim($_POST['reporter_code'] ?? '');

    if (empty($username) || empty($reporter_code)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผู้รายงาน']);
        exit;
    }

    // ตรวจสอบ username ซ้ำ
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id ?? 0]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่แล้ว']);
        exit;
    }

    // ตรวจสอบ reporter_code ซ้ำ
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reporter_code = ? AND id != ?");
    $stmt->execute([$reporter_code, $id ?? 0]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'รหัสผู้รายงานนี้มีอยู่แล้ว']);
        exit;
    }

    if (!empty($password) || !empty($confirm)) {
        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านไม่ตรงกัน']);
            exit;
        }
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร']);
            exit;
        }
    }

    $avatar = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($ext), $allowed)) {
            echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ .jpg, .png, .gif']);
            exit;
        }
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 2MB)']);
            exit;
        }
        $newName = time() . '_' . uniqid() . '.' . $ext;
        $target = __DIR__ . '/avatars/' . $newName;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
            $avatar = $newName;
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปโหลดรูปได้']);
            exit;
        }
    }

    try {
        if ($id) {
            // แก้ไขผู้ใช้
            $sql = "UPDATE users SET username = ?, role = ?, reporter_code = ?";
            $params = [$username, $role, $reporter_code];
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed;
            }
            if ($avatar) {
                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists(__DIR__ . '/avatars/' . $old) && $old !== 'default.png') {
                    unlink(__DIR__ . '/avatars/' . $old);
                }
                $sql .= ", avatar = ?";
                $params[] = $avatar;
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // เพิ่มใหม่
            if (empty($password)) {
                echo json_encode(['success' => false, 'message' => 'กรุณาระบุรหัสผ่าน']);
                exit;
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $avatar = $avatar ?? 'default.png';
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, reporter_code, avatar) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed, $role, $reporter_code, $avatar]);
        }
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลผู้ใช้สำเร็จ']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// 5. delete_user
// ============================================
if ($action == 'delete_user') {
    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $id = $input['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ผู้ใช้']);
        exit;
    }

    if ($id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบตัวเองได้']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user && $user['role'] == 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != $id");
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบ Admin คนสุดท้ายได้']);
            exit;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'ลบผู้ใช้สำเร็จ']);
    exit;
}

// ============================================
// 6. save_cookie_consent
// ============================================
if ($action == 'save_cookie_consent') {
    $input = json_decode(file_get_contents('php://input'), true);
    $consent = $input['consent'] ?? 0;
    $preferences = $input['preferences'] ?? [];
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cookie_consent_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            consent TINYINT(1) DEFAULT 0,
            preferences JSON NULL,
            ip VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("INSERT INTO cookie_consent_log (user_id, consent, preferences, ip, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $consent, json_encode($preferences), $ip, $user_agent]);
        echo json_encode(['success' => true, 'message' => 'บันทึก Consent เรียบร้อย']);
    } catch (PDOException $e) {
        error_log('Cookie Consent Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึก Consent ได้']);
    }
    exit;
}