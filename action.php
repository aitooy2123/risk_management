<?php

/**
 * ตัวจัดการ API (Backend) – ไม่มี Cookie Consent
 * - รองรับ CSRF token จากทั้ง POST form และ JSON body
 * - แก้ไขปัญหา Forbidden เมื่อใช้ fetch แบบ JSON
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบ CSRF token จากหลายช่องทาง
$csrf_token = $_POST['csrf_token'] ?? '';

if (empty($csrf_token)) {
    // ลองอ่านจาก JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $csrf_token = $input['csrf_token'] ?? '';
}

// ถ้าไม่มี CSRF token และไม่ใช่ AJAX → Forbidden
if (empty($csrf_token) && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
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

    if (empty($reporter_code) || empty($unit) || empty($event_datetime) || empty($report_datetime) || empty($detail) || empty($initial_solution) || empty($suggestion)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    if ($unit == 'อื่นๆ') $unit = $unit_other;
    if ($risk_type == 'อื่นๆ') $risk_type = $risk_type_other;
    if ($severity == 'อื่นๆ') $severity = $severity_other;

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
// 4. save_user (รับ reporter_code และ avatar)
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
    $is_edit = isset($_POST['is_edit']) ? (int)$_POST['is_edit'] : 0;
    
    // รับค่าจากฟอร์ม
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $reporter_code = trim($_POST['reporter_code'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    // ถ้าไม่มี department และมี department_select ให้ใช้ department_select
    if (empty($department) && isset($_POST['department_select'])) {
        $department = trim($_POST['department_select']);
        // ถ้าเลือก "อื่นๆ" ให้ใช้ค่าจาก department_other (แต่ตอนนี้ส่งมาเป็น department แล้ว)
        if ($department === 'อื่นๆ' && !empty($_POST['department_other'])) {
            $department = trim($_POST['department_other']);
        }
    }

    // ตรวจสอบข้อมูลบังคับ
    if (empty($username) || empty($reporter_code)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผู้รายงาน']);
        exit;
    }

    // ตรวจสอบ username ซ้ำ (ยกเว้นตัวเอง)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id ?? 0]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่แล้ว']);
        exit;
    }

    // ตรวจสอบ reporter_code ซ้ำ (ยกเว้นตัวเอง)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reporter_code = ? AND id != ?");
    $stmt->execute([$reporter_code, $id ?? 0]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'รหัสผู้รายงานนี้มีอยู่แล้ว']);
        exit;
    }

    // ตรวจสอบรหัสผ่าน
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

    // จัดการ Avatar
    $avatar = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) {
            echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ .jpg, .png, .gif, .webp']);
            exit;
        }
        if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)']);
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
        if ($id && $is_edit) {
            // โหมดแก้ไข - ไม่แก้ไข username และ reporter_code
            $sql = "UPDATE users SET 
                    fullname = ?,
                    email = ?,
                    phone = ?,
                    department = ?,
                    role = ?";
            $params = [$fullname, $email, $phone, $department, $role];
            
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed;
            }
            
            if ($avatar) {
                // ลบรูปเก่า
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
            // โหมดเพิ่มใหม่
            if (empty($password)) {
                echo json_encode(['success' => false, 'message' => 'กรุณาระบุรหัสผ่าน']);
                exit;
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $avatar = $avatar ?? 'default.png';
            
            $stmt = $pdo->prepare("INSERT INTO users 
                (username, password, role, reporter_code, fullname, email, phone, department, avatar) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $username, $hashed, $role, $reporter_code, 
                $fullname, $email, $phone, $department, $avatar
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลผู้ใช้สำเร็จ']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// 5. delete_user (ลบทีละคน)
// ============================================
if ($action == 'delete_user') {
    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $csrf_token = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
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
// 6. delete_users (ลบหลายคน)
// ============================================
if ($action == 'delete_users') {
    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $csrf_token = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $ids = $input['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบผู้ใช้ที่เลือก']);
        exit;
    }

    if (in_array($_SESSION['user_id'], $ids)) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบตัวเองได้']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id IN ($placeholders)");
    $stmt->execute($ids);
    if ($stmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id NOT IN ($placeholders)");
        $stmt->execute($ids);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบ Admin คนสุดท้ายได้']);
            exit;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo json_encode(['success' => true, 'message' => 'ลบผู้ใช้ ' . $stmt->rowCount() . ' คนสำเร็จ']);
    exit;
}

// ============================================
// 7. update_profile (อัปเดตโปรไฟล์ส่วนตัว)
// ============================================
if ($action == 'update_profile') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');

    try {
        $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, department = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullname, $email, $phone, $department, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'อัปเดตโปรไฟล์สำเร็จ']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// 8. update_avatar (อัปเดตรูปโปรไฟล์)
// ============================================
if ($action == 'update_avatar') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเลือกไฟล์รูปภาพ']);
        exit;
    }

    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($ext), $allowed)) {
        echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ .jpg, .png, .gif, .webp']);
        exit;
    }

    if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)']);
        exit;
    }

    $newName = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $target = __DIR__ . '/avatars/' . $newName;

    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปโหลดไฟล์ได้']);
        exit;
    }

    try {
        // ลบรูปเก่า
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists(__DIR__ . '/avatars/' . $old) && $old !== 'default.png') {
            unlink(__DIR__ . '/avatars/' . $old);
        }

        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$newName, $user_id]);
        $_SESSION['avatar'] = $newName;

        echo json_encode(['success' => true, 'message' => 'อัปเดตรูปโปรไฟล์สำเร็จ']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// 9. change_password (เปลี่ยนรหัสผ่าน)
// ============================================
if ($action == 'change_password') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่ไม่ตรงกัน']);
        exit;
    }

    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร']);
        exit;
    }

    // ตรวจสอบรหัสผ่านเก่า
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($old_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านเก่าไม่ถูกต้อง']);
        exit;
    }

    try {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);

        echo json_encode(['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// Default
// ============================================
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;