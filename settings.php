<?php

/**
 * หน้าตั้งค่าระบบ - UI สวยงาม พร้อม Tab Navigation
 * - เฉพาะ Admin เท่านั้น
 * - จัดการการตั้งค่าทั่วไป, การแสดงผล, การแจ้งเตือน, ความปลอดภัย, ระบบ
 * - บันทึกการตั้งค่าลงฐานข้อมูล
 * - รองรับการอัปโหลดโลโก้
 * - ล้างข้อมูลระบบ
 * - เพิ่มข้อมูลทดสอบจาก install.sql
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือนและการยืนยัน
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

// ===== ดึงค่าการตั้งค่าปัจจุบัน =====
$settings = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // ใช้ค่าเริ่มต้น
}

// ตั้งค่าเริ่มต้น
$defaults = [
    'site_name' => 'Risk Management',
    'site_description' => 'ระบบบริหารความเสี่ยง',
    'site_organization' => 'ศูนย์อนามัยที่ 8 อุดรธานี',
    'site_logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png',
    'items_per_page' => '10',
    'session_timeout' => '30',
    'notification_enabled' => '1',
    'backup_enabled' => '1',
    'maintenance_mode' => '0',
    'login_attempts' => '5',
    'password_min_length' => '8',
    'sidebar_show_dashboard' => '1',
    'sidebar_show_reports' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// ===== จัดการ Action พิเศษ (ล้างข้อมูล / เพิ่มข้อมูลทดสอบ) =====
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? 'save_settings';
        
        if ($action === 'clear_data') {
            // ===== ล้างข้อมูล =====
            $clear_type = $_POST['clear_type'] ?? '';
            
            try {
                $pdo->beginTransaction();
                
                switch ($clear_type) {
                    case 'risks':
                        $pdo->exec("DELETE FROM risk_reports");
                        $pdo->exec("DELETE FROM risks");
                        $success_message = 'ล้างข้อมูลความเสี่ยงทั้งหมดสำเร็จ';
                        break;
                        
                    case 'reports':
                        $pdo->exec("DELETE FROM risk_reports");
                        $success_message = 'ล้างข้อมูลรายงานผลทั้งหมดสำเร็จ';
                        break;
                        
                    case 'users_except_admin':
                        $adminId = $_SESSION['user_id'];
                        $pdo->exec("DELETE FROM risk_reports WHERE risk_id IN (SELECT id FROM risks WHERE user_id != $adminId)");
                        $pdo->exec("DELETE FROM risks WHERE user_id != $adminId");
                        $pdo->exec("DELETE FROM user_tokens WHERE user_id != $adminId");
                        $pdo->prepare("DELETE FROM users WHERE id != ?")->execute([$adminId]);
                        $success_message = 'ล้างข้อมูลผู้ใช้ทั้งหมด (ยกเว้น Admin ปัจจุบัน) สำเร็จ';
                        break;
                        
                    case 'all':
                        $adminId = $_SESSION['user_id'];
                        $adminUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $adminUser->execute([$adminId]);
                        $admin = $adminUser->fetch();
                        
                        // ลบข้อมูลทั้งหมด
                        $pdo->exec("DELETE FROM risk_reports");
                        $pdo->exec("DELETE FROM risks");
                        $pdo->exec("DELETE FROM user_tokens");
                        $pdo->exec("DELETE FROM login_attempts");
                        $pdo->exec("DELETE FROM users");
                        
                        // สร้าง Admin กลับมา
                        if ($admin) {
                            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, role, enabled, created_at) VALUES (?, ?, ?, ?, 'admin', 1, NOW())");
                            $stmt->execute([$admin['username'], $admin['password'], $admin['fullname'] ?? 'Admin', $admin['email'] ?? '']);
                        }
                        
                        $success_message = 'ล้างข้อมูลทั้งหมดสำเร็จ (คงเหลือเฉพาะ Admin)';
                        break;
                        
                    default:
                        $error_message = 'กรุณาเลือกประเภทข้อมูลที่ต้องการล้าง';
                }
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
            
        } elseif ($action === 'import_sample_data') {
            // ===== เพิ่มข้อมูลทดสอบ =====
            $sql_file = __DIR__ . '/install.sql';
            
            if (!file_exists($sql_file)) {
                $error_message = 'ไม่พบไฟล์ install.sql';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $sql_content = file_get_contents($sql_file);
                    
                    // แยกคำสั่ง SQL
                    $queries = array_filter(
                        array_map('trim', 
                            explode(';', $sql_content)
                        )
                    );
                    
                    $imported = 0;
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            try {
                                $pdo->exec($query);
                                $imported++;
                            } catch (Exception $e) {
                                // ข้ามคำสั่งที่ผิดพลาด (เช่น ตารางมีอยู่แล้ว)
                                continue;
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $success_message = "นำเข้าข้อมูลทดสอบสำเร็จ ($imported คำสั่ง)";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = 'เกิดข้อผิดพลาดในการนำเข้า: ' . $e->getMessage();
                }
            }
            
        } else {
            // ===== บันทึกการตั้งค่า =====
            try {
                $pdo->beginTransaction();
                
                // สร้างตารางถ้ายังไม่มี
                $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                // จัดการอัปโหลดโลโก้
                $logo_path = $_POST['site_logo_url'] ?? $settings['site_logo'];
                if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'assets/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['site_logo_file']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'logo_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $upload_path)) {
                            $logo_path = $upload_path;
                        }
                    }
                }
                
                $settings_to_save = [
                    'site_name' => $_POST['site_name'] ?? 'Risk Management',
                    'site_description' => $_POST['site_description'] ?? 'ระบบบริหารความเสี่ยง',
                    'site_organization' => $_POST['site_organization'] ?? 'ศูนย์อนามัยที่ 8 อุดรธานี',
                    'site_logo' => $logo_path,
                    'items_per_page' => $_POST['items_per_page'] ?? '10',
                    'session_timeout' => $_POST['session_timeout'] ?? '30',
                    'notification_enabled' => isset($_POST['notification_enabled']) ? '1' : '0',
                    'backup_enabled' => isset($_POST['backup_enabled']) ? '1' : '0',
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
                    'login_attempts' => $_POST['login_attempts'] ?? '5',
                    'password_min_length' => $_POST['password_min_length'] ?? '8',
                    'sidebar_show_dashboard' => isset($_POST['sidebar_show_dashboard']) ? '1' : '0',
                    'sidebar_show_reports' => isset($_POST['sidebar_show_reports']) ? '1' : '0'
                ];
                
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                      VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                
                foreach ($settings_to_save as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                
                $pdo->commit();
                $success_message = 'บันทึกการตั้งค่าสำเร็จ';
                $settings = $settings_to_save;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}

// ===== นับจำนวนข้อมูลในระบบ =====
$stats = [];
try {
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['risks'] = $pdo->query("SELECT COUNT(*) FROM risks")->fetchColumn();
    $stats['reports'] = 0;
    try {
        $stats['reports'] = $pdo->query("SELECT COUNT(*) FROM risk_reports")->fetchColumn();
    } catch (Exception $e) {}
    $stats['admin_count'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
} catch (Exception $e) {
    $stats = ['users' => 0, 'risks' => 0, 'reports' => 0, 'admin_count' => 0];
}

$csrf_token = generateCsrfToken();
$page_title = 'ตั้งค่าระบบ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Risk Management</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #eff6ff;
            --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #1d4ed8 100%);
            --surface: #ffffff;
            --surface-secondary: #f8fafc;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --text: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --success: #059669;
            --success-light: #ecfdf5;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --purple: #7c3aed;
            --purple-light: #f5f3ff;
            --cyan: #06b6d4;
            --cyan-light: #ecfeff;
            --rose: #e11d48;
            --rose-light: #fff1f2;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
            min-height: 100vh;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ==================== HEADER ==================== */
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
            border-radius: 1.5rem;
            padding: 1.75rem 2.25rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.2);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .page-header h1 .icon-circle {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }

        .header-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .header-stat i {
            font-size: 0.7rem;
            opacity: 0.6;
        }

        /* ==================== TABS ==================== */
        .tabs-nav {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            background: white;
            padding: 0.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border);
            overflow-x: auto;
        }

        .tab-btn {
            flex: 1;
            min-width: max-content;
            padding: 0.75rem 1.25rem;
            border: none;
            background: transparent;
            border-radius: 0.75rem;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: var(--surface-secondary);
            color: var(--text-secondary);
        }

        .tab-btn.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .tab-btn.danger-tab.active {
            background: linear-gradient(135deg, #991b1b 0%, #dc2626 50%, #ef4444 100%);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ==================== CARDS ==================== */
        .settings-card {
            background: var(--surface);
            border-radius: 1rem;
            border: 1px solid var(--border);
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .settings-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .settings-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
        }

        .settings-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .icon-bg {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .icon-bg.blue { background: var(--primary-light); color: var(--primary); border: 1px solid #bfdbfe; }
        .icon-bg.amber { background: var(--warning-light); color: var(--warning); border: 1px solid #fde68a; }
        .icon-bg.green { background: var(--success-light); color: var(--success); border: 1px solid #a7f3d0; }
        .icon-bg.purple { background: var(--purple-light); color: var(--purple); border: 1px solid #ddd6fe; }
        .icon-bg.cyan { background: var(--cyan-light); color: var(--cyan); border: 1px solid #a5f3fc; }
        .icon-bg.rose { background: var(--rose-light); color: var(--rose); border: 1px solid #fecdd3; }
        .icon-bg.red { background: var(--danger-light); color: var(--danger); border: 1px solid #fecaca; }

        .settings-card-body {
            padding: 1.5rem;
        }

        /* ==================== STATS GRID ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.2rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* ==================== FORM ELEMENTS ==================== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .form-label i {
            color: var(--text-muted);
            font-size: 0.7rem;
            width: 16px;
            text-align: center;
        }

        .form-input {
            padding: 0.65rem 0.9rem;
            border: 1.5px solid var(--border);
            border-radius: 0.6rem;
            font-size: 0.85rem;
            outline: none;
            font-family: 'Sarabun', sans-serif;
            background: #fafbfc;
            color: var(--text);
            width: 100%;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.8rem center;
            background-size: 11px;
            padding-right: 2.5rem;
        }

        .help-text {
            font-size: 0.73rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* ==================== DANGER ZONE ==================== */
        .danger-zone {
            border: 2px dashed #fecaca;
            border-radius: 1rem;
            padding: 1.5rem;
            background: #fef2f2;
            margin-bottom: 1.25rem;
        }

        .danger-zone-title {
            font-size: 1rem;
            font-weight: 700;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .danger-zone-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .danger-btn {
            padding: 0.75rem 1.25rem;
            border-radius: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid #fecaca;
            background: white;
            color: #dc2626;
            transition: all 0.25s;
            width: 100%;
            justify-content: center;
        }

        .danger-btn:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .danger-btn.warning {
            border-color: #fde68a;
            color: #d97706;
        }

        .danger-btn.warning:hover {
            background: #d97706;
            color: white;
            border-color: #d97706;
        }

        .danger-btn.success {
            border-color: #a7f3d0;
            color: #059669;
        }

        .danger-btn.success:hover {
            background: #059669;
            color: white;
            border-color: #059669;
        }

        /* ==================== LOGO UPLOAD ==================== */
        .logo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: contain;
            border: 3px solid var(--border);
            background: white;
            padding: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .logo-upload-area {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .file-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px dashed #bfdbfe;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .file-upload-btn:hover {
            background: #dbeafe;
        }

        /* ==================== TOGGLE SWITCH ==================== */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .toggle-row:last-child {
            border-bottom: none;
        }

        .toggle-info { flex: 1; }
        .toggle-label { font-size: 0.9rem; font-weight: 600; color: var(--text); margin-bottom: 0.15rem; }
        .toggle-description { font-size: 0.78rem; color: var(--text-muted); }

        .switch-wrapper {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
            cursor: pointer;
            flex-shrink: 0;
            margin-left: 1.5rem;
        }

        .switch-wrapper:hover { opacity: 0.9; }
        .switch-input { opacity: 0; width: 0; height: 0; }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #e2e8f0;
            transition: all 0.3s ease;
            border-radius: 34px;
            border: 2px solid #e2e8f0;
        }

        .switch-slider:before {
            position: absolute;
            content: "";
            height: 20px; width: 20px;
            left: 2px; bottom: 2px;
            background-color: white;
            transition: all 0.3s ease;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .switch-input:checked + .switch-slider {
            background-color: #10b981;
            border-color: #10b981;
        }

        .switch-input:checked + .switch-slider:before {
            transform: translateX(24px);
        }

        .switch-slider::after {
            content: '✕';
            position: absolute;
            right: 8px; top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: #94a3b8;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .switch-input:checked + .switch-slider::after {
            content: '✓';
            left: 8px; right: auto;
            color: white;
        }

        /* ==================== BUTTONS ==================== */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 2rem;
            border-radius: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--surface-secondary);
            border-color: #cbd5e1;
        }

        .btn-danger {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fee2e2;
        }

        /* ==================== ALERT ==================== */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: var(--success-light); color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: var(--danger-light); color: #991b1b; border: 1px solid #fecaca; }
        .alert i { font-size: 1.2rem; flex-shrink: 0; }

        /* ==================== INFO BOX ==================== */
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .info-box-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            border: 1px solid #bfdbfe;
            color: #2563eb;
        }

        .info-box-content { flex: 1; }
        .info-box-title { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
        .info-box-text { font-size: 0.78rem; color: #475569; line-height: 1.6; }

        .last-saved {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.5rem;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .danger-zone-grid { grid-template-columns: 1fr; }
            .tabs-nav { flex-direction: column; }
            .page-header { padding: 1.25rem 1.5rem; }
            .page-header h1 { font-size: 1.25rem; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .toggle-row { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .switch-wrapper { margin-left: 0; }
            .logo-upload-area { flex-direction: column; align-items: flex-start; }
            .header-stats { flex-direction: column; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        <div class="flex-1 p-4 md:p-5 overflow-y-auto">
            <div class="page-container">

                <!-- ==================== HEADER ==================== -->
                <div class="page-header">
                    <h1><span class="icon-circle">⚙️</span> ตั้งค่าระบบ</h1>
                    <div class="header-stats">
                        <span class="header-stat"><i class="fas fa-check-circle"></i> จัดการการตั้งค่าทั้งหมด</span>
                        <span class="header-stat"><i class="fas fa-shield-alt"></i> เฉพาะ Admin</span>
                        <span class="header-stat"><i class="fas fa-sync-alt"></i> มีผลทันที</span>
                    </div>
                </div>

                <!-- ==================== ALERTS ==================== -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- ==================== TABS ==================== -->
                <div class="tabs-nav">
                    <button class="tab-btn active" data-tab="general">
                        <i class="fas fa-cog"></i> ทั่วไป
                    </button>
                    <button class="tab-btn" data-tab="display">
                        <i class="fas fa-desktop"></i> การแสดงผล
                    </button>
                    <button class="tab-btn" data-tab="notifications">
                        <i class="fas fa-bell"></i> การแจ้งเตือน
                    </button>
                    <button class="tab-btn" data-tab="security">
                        <i class="fas fa-shield-alt"></i> ความปลอดภัย
                    </button>
                    <button class="tab-btn" data-tab="system">
                        <i class="fas fa-server"></i> ระบบฐานข้อมูล
                    </button>
                </div>

                <!-- ==================== FORM ==================== -->
                <form method="POST" id="settingsForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="save_settings" id="formAction">

                    <!-- TAB: ทั่วไป -->
                    <div class="tab-content active" id="tab-general">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div class="settings-card-title">
                                    <div class="icon-bg blue"><i class="fas fa-globe"></i></div>
                                    ข้อมูลระบบ
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label class="form-label" for="site_name"><i class="fas fa-tag"></i> ชื่อระบบ</label>
                                        <input type="text" name="site_name" id="site_name" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="site_description"><i class="fas fa-info-circle"></i> คำอธิบายระบบ</label>
                                        <input type="text" name="site_description" id="site_description" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_description']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="site_organization"><i class="fas fa-building"></i> หน่วยงาน</label>
                                        <input type="text" name="site_organization" id="site_organization" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_organization']) ?>">
                                    </div>
                                    <div class="form-group full-width">
                                        <label class="form-label"><i class="fas fa-image"></i> โลโก้ระบบ</label>
                                        <div class="logo-upload-area">
                                            <img src="<?= htmlspecialchars($settings['site_logo']) ?>" 
                                                 alt="Logo" class="logo-preview" id="logoPreview"
                                                 onerror="this.src='assets/default-logo.png'">
                                            <div>
                                                <label class="file-upload-btn">
                                                    <i class="fas fa-upload"></i> อัปโหลดโลโก้
                                                    <input type="file" name="site_logo_file" accept="image/*" 
                                                           style="display:none" onchange="previewLogo(this)">
                                                </label>
                                                <span class="help-text" style="display:block;margin-top:0.5rem;">หรือระบุ URL ด้านล่าง</span>
                                            </div>
                                        </div>
                                        <input type="text" name="site_logo_url" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_logo']) ?>" 
                                               placeholder="หรือวาง URL โลโก้" style="margin-top:0.75rem;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div class="settings-card-title">
                                    <div class="icon-bg cyan"><i class="fas fa-sliders-h"></i></div>
                                    การตั้งค่าพื้นฐาน
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="items_per_page"><i class="fas fa-list-ol"></i> จำนวนรายการต่อหน้า</label>
                                        <select name="items_per_page" id="items_per_page" class="form-input">
                                            <option value="5" <?= $settings['items_per_page'] == '5' ? 'selected' : '' ?>>5 รายการ</option>
                                            <option value="10" <?= $settings['items_per_page'] == '10' ? 'selected' : '' ?>>10 รายการ</option>
                                            <option value="25" <?= $settings['items_per_page'] == '25' ? 'selected' : '' ?>>25 รายการ</option>
                                            <option value="50" <?= $settings['items_per_page'] == '50' ? 'selected' : '' ?>>50 รายการ</option>
                                            <option value="100" <?= $settings['items_per_page'] == '100' ? 'selected' : '' ?>>100 รายการ</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="session_timeout"><i class="fas fa-clock"></i> หมดเวลาเซสชัน (นาที)</label>
                                        <input type="number" name="session_timeout" id="session_timeout" class="form-input" 
                                               value="<?= htmlspecialchars($settings['session_timeout']) ?>" min="5" max="240" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: การแสดงผล -->
                    <div class="tab-content" id="tab-display">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div class="settings-card-title">
                                    <div class="icon-bg purple"><i class="fas fa-desktop"></i></div>
                                    การแสดงผล Sidebar
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-label">📊 แสดงเมนู Dashboard</div>
                                        <div class="toggle-description">แสดง/ซ่อนเมนูภาพรวมระบบ (เฉพาะ Admin)</div>
                                    </div>
                                    <label class="switch-wrapper">
                                        <input type="checkbox" name="sidebar_show_dashboard" class="switch-input" value="1" 
                                               <?= ($settings['sidebar_show_dashboard'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-label">📈 แสดงเมนูรายงาน</div>
                                        <div class="toggle-description">แสดง/ซ่อนเมนูรายงานใน Sidebar</div>
                                    </div>
                                    <label class="switch-wrapper">
                                        <input type="checkbox" name="sidebar_show_reports" class="switch-input" value="1" 
                                               <?= ($settings['sidebar_show_reports'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: การแจ้งเตือน -->
                    <div class="tab-content" id="tab-notifications">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div class="settings-card-title">
                                    <div class="icon-bg amber"><i class="fas fa-bell"></i></div>
                                    การแจ้งเตือน
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-label">🔔 เปิดการแจ้งเตือน</div>
                                        <div class="toggle-description">ส่งการแจ้งเตือนเมื่อมีการเปลี่ยนแปลงสถานะความเสี่ยง</div>
                                    </div>
                                    <label class="switch-wrapper">
                                        <input type="checkbox" name="notification_enabled" class="switch-input" value="1" 
                                               <?= $settings['notification_enabled'] == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-label">💾 สำรองข้อมูลอัตโนมัติ</div>
                                        <div class="toggle-description">สำรองข้อมูลทุกวันเวลา 00:00 น.</div>
                                    </div>
                                    <label class="switch-wrapper">
                                        <input type="checkbox" name="backup_enabled" class="switch-input" value="1" 
                                               <?= $settings['backup_enabled'] == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: ความปลอดภัย -->
                    <div class="tab-content" id="tab-security">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div class="settings-card-title">
                                    <div class="icon-bg green"><i class="fas fa-shield-alt"></i></div>
                                    ความปลอดภัย
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="login_attempts"><i class="fas fa-lock"></i> จำนวนการเข้าสู่ระบบสูงสุด</label>
                                        <input type="number" name="login_attempts" id="login_attempts" class="form-input" 
                                               value="<?= htmlspecialchars($settings['login_attempts']) ?>" min="1" max="10" required>
                                        <span class="help-text">จำนวนครั้งที่อนุญาตให้เข้าสู่ระบบผิดก่อนล็อคบัญชี</span>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="password_min_length"><i class="fas fa-key"></i> ความยาวรหัสผ่านขั้นต่ำ</label>
                                        <input type="number" name="password_min_length" id="password_min_length" class="form-input" 
                                               value="<?= htmlspecialchars($settings['password_min_length']) ?>" min="6" max="20" required>
                                        <span class="help-text">จำนวนตัวอักษรขั้นต่ำสำหรับรหัสผ่าน</span>
                                    </div>
                                </div>
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-label">🔧 โหมดบำรุงรักษา</div>
                                        <div class="toggle-description">ปิดการเข้าถึงสำหรับผู้ใช้ทั่วไป</div>
                                    </div>
                                    <label class="switch-wrapper">
                                        <input type="checkbox" name="maintenance_mode" class="switch-input" value="1" 
                                               <?= $settings['maintenance_mode'] == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก (แสดงเฉพาะแท็บที่ไม่ใช่ระบบ) -->
                    <div class="action-buttons" id="saveButtons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึกการตั้งค่า
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                        </a>
                        <button type="button" class="btn btn-danger" id="resetBtn">
                            <i class="fas fa-undo"></i> คืนค่าเริ่มต้น
                        </button>
                    </div>
                    <div class="last-saved" id="lastSavedInfo">
                        <i class="fas fa-history"></i> บันทึกล่าสุด: <?= date('d/m/Y H:i:s') ?>
                    </div>
                </form>

                <!-- ==================== TAB: ระบบ (อยู่นอก form หลัก) ==================== -->
                <div class="tab-content" id="tab-system">
                    <!-- สถิติระบบ -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background:#eff6ff;color:#2563eb;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['users']) ?></div>
                            <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:#fef3c7;color:#d97706;">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['admin_count']) ?></div>
                            <div class="stat-label">ผู้ดูแลระบบ</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:#ecfdf5;color:#059669;">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['risks']) ?></div>
                            <div class="stat-label">ความเสี่ยงทั้งหมด</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['reports']) ?></div>
                            <div class="stat-label">รายงานผล</div>
                        </div>
                    </div>

                    <!-- นำเข้าข้อมูลทดสอบ -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-title">
                                <div class="icon-bg green"><i class="fas fa-download"></i></div>
                                นำเข้าข้อมูล
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <p class="text-gray-600 mb-4">นำเข้าข้อมูลทดสอบจากไฟล์ install.sql เพื่อใช้ในการทดสอบระบบ</p>
                            <form method="POST" id="importForm">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="import_sample_data">
                                <button type="button" class="btn btn-primary" onclick="confirmImport()">
                                    <i class="fas fa-file-import"></i> นำเข้าข้อมูลทดสอบ
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- ล้างข้อมูล (Danger Zone) -->
                    <div class="danger-zone">
                        <div class="danger-zone-title">
                            <i class="fas fa-exclamation-triangle"></i> โซนอันตราย - ล้างข้อมูล
                        </div>
                        <p class="text-red-600 text-sm mb-4">
                            <i class="fas fa-info-circle"></i> 
                            การล้างข้อมูลไม่สามารถกู้คืนได้ กรุณาสำรองข้อมูลก่อนดำเนินการ
                        </p>
                        
                        <form method="POST" id="clearDataForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="clear_data">
                            <input type="hidden" name="clear_type" id="clearType" value="">
                            
                            <div class="danger-zone-grid">
                                <button type="button" class="danger-btn warning" onclick="confirmClear('risks')">
                                    <i class="fas fa-clipboard-list"></i> ล้างความเสี่ยงทั้งหมด
                                </button>
                                <button type="button" class="danger-btn warning" onclick="confirmClear('reports')">
                                    <i class="fas fa-file-alt"></i> ล้างรายงานผลทั้งหมด
                                </button>
                                <button type="button" class="danger-btn" onclick="confirmClear('users_except_admin')">
                                    <i class="fas fa-users-slash"></i> ล้างผู้ใช้ทั้งหมด (ยกเว้น Admin)
                                </button>
                                <button type="button" class="danger-btn" onclick="confirmClear('all')">
                                    <i class="fas fa-skull"></i> ล้างทั้งหมด (เหลือเฉพาะ Admin)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <div class="info-box-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="info-box-content">
                        <div class="info-box-title">ข้อมูลการตั้งค่าระบบ</div>
                        <div class="info-box-text">
                            • การตั้งค่าทั้งหมดมีผลทันทีหลังจากบันทึก<br>
                            • การเปลี่ยนแปลงอาจส่งผลต่อการทำงานของระบบ<br>
                            • ควรสำรองข้อมูลก่อนทำการเปลี่ยนแปลงที่สำคัญ<br>
                            • การล้างข้อมูลไม่สามารถกู้คืนได้
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // ========== TAB SWITCHING ==========
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                localStorage.setItem('settingsActiveTab', this.dataset.tab);
                
                // Show/hide save buttons based on tab
                const saveButtons = document.getElementById('saveButtons');
                const lastSaved = document.getElementById('lastSavedInfo');
                if (this.dataset.tab === 'system') {
                    if (saveButtons) saveButtons.style.display = 'none';
                    if (lastSaved) lastSaved.style.display = 'none';
                } else {
                    if (saveButtons) saveButtons.style.display = 'flex';
                    if (lastSaved) lastSaved.style.display = 'flex';
                }
            });
        });

        const savedTab = localStorage.getItem('settingsActiveTab');
        if (savedTab) {
            const tabBtn = document.querySelector(`.tab-btn[data-tab="${savedTab}"]`);
            if (tabBtn) {
                tabBtn.click();
            }
        }

        // ========== LOGO PREVIEW ==========
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('logoPreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ========== SAVE CONFIRMATION ==========
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            if (document.getElementById('formAction').value !== 'save_settings') return;
            e.preventDefault();
            Swal.fire({
                title: 'บันทึกการตั้งค่า?',
                html: '<p>คุณต้องการบันทึกการเปลี่ยนแปลงใช่หรือไม่?</p><p class="text-sm text-gray-500">การตั้งค่าจะมีผลทันที</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    this.submit();
                }
            });
        });

        // ========== RESET ==========
        document.getElementById('resetBtn').addEventListener('click', function() {
            Swal.fire({
                title: 'คืนค่าเริ่มต้น?',
                html: '<p class="text-red-600 font-semibold">⚠️ คำเตือน!</p><p>การตั้งค่าทั้งหมดจะถูกเปลี่ยนกลับเป็นค่าเริ่มต้น</p><p class="text-sm text-gray-500">คุณยังต้องกด "บันทึก" เพื่อยืนยัน</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ยืนยันคืนค่า',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('site_name').value = 'Risk Management';
                    document.getElementById('site_description').value = 'ระบบบริหารความเสี่ยง';
                    document.getElementById('site_organization').value = 'ศูนย์อนามัยที่ 8 อุดรธานี';
                    document.querySelector('input[name="site_logo_url"]').value = '';
                    document.getElementById('items_per_page').value = '10';
                    document.getElementById('session_timeout').value = '30';
                    document.getElementById('login_attempts').value = '5';
                    document.getElementById('password_min_length').value = '8';
                    document.querySelectorAll('.switch-input').forEach(t => t.checked = true);
                    document.querySelector('input[name="maintenance_mode"]').checked = false;
                    
                    Swal.fire({ icon: 'success', title: 'รีเซ็ตฟอร์มแล้ว', text: 'กรุณากด "บันทึก" เพื่อยืนยัน', confirmButtonColor: '#3b82f6', timer: 3000 });
                }
            });
        });

        // ========== CLEAR DATA ==========
        function confirmClear(type) {
            const titles = {
                'risks': 'ล้างความเสี่ยงทั้งหมด',
                'reports': 'ล้างรายงานผลทั้งหมด',
                'users_except_admin': 'ล้างผู้ใช้ทั้งหมด (ยกเว้น Admin)',
                'all': 'ล้างข้อมูลทั้งหมด'
            };
            
            const messages = {
                'risks': 'ข้อมูลความเสี่ยงและรายงานผลทั้งหมดจะถูกลบถาวร!',
                'reports': 'ข้อมูลรายงานผลทั้งหมดจะถูกลบถาวร!',
                'users_except_admin': 'ผู้ใช้ทั้งหมด (ยกเว้นคุณ) จะถูกลบ พร้อมข้อมูลความเสี่ยงของพวกเขา!',
                'all': 'ข้อมูลทั้งหมดจะถูกลบ เหลือเฉพาะบัญชี Admin ของคุณ! การกระทำนี้ไม่สามารถกู้คืนได้!'
            };
            
            Swal.fire({
                title: '⚠️ ยืนยัน: ' + titles[type],
                html: '<p class="text-red-600 font-semibold">' + messages[type] + '</p><p class="text-sm text-gray-500 mt-2">การกระทำนี้ไม่สามารถเรียกคืนได้!</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-trash"></i> ยืนยันการล้าง',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Double confirmation for 'all'
                    if (type === 'all') {
                        Swal.fire({
                            title: '⚠️ ยืนยันครั้งสุดท้าย!',
                            html: '<p class="text-red-600 font-bold text-lg">ล้างข้อมูลทั้งหมดจริงหรือไม่?</p><p>ข้อมูลทั้งหมดจะหายไปถาวร!</p>',
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            cancelButtonColor: '#64748b',
                            confirmButtonText: 'ใช่ ล้างทั้งหมด!',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result2) => {
                            if (result2.isConfirmed) {
                                submitClearForm(type);
                            }
                        });
                    } else {
                        submitClearForm(type);
                    }
                }
            });
        }

        function submitClearForm(type) {
            document.getElementById('clearType').value = type;
            
            Swal.fire({
                title: 'กำลังดำเนินการ...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            document.getElementById('clearDataForm').submit();
        }

        // ========== IMPORT DATA ==========
        function confirmImport() {
            Swal.fire({
                title: 'นำเข้าข้อมูลทดสอบ?',
                html: '<p>ระบบจะนำเข้าข้อมูลทดสอบจากไฟล์ install.sql</p><p class="text-sm text-amber-600 mt-2"><i class="fas fa-info-circle"></i> ข้อมูลที่มีอยู่แล้วจะไม่ถูกเขียนทับ</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-file-import"></i> นำเข้า',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังนำเข้า...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    document.getElementById('importForm').submit();
                }
            });
        }

        // ========== SUCCESS/ERROR ==========
        <?php if ($success_message): ?>
            Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= htmlspecialchars($success_message) ?>', confirmButtonColor: '#3b82f6', timer: 3000 });
        <?php endif; ?>
        <?php if ($error_message): ?>
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: '<?= htmlspecialchars($error_message) ?>', confirmButtonColor: '#3b82f6' });
        <?php endif; ?>

        console.log('✅ Settings page loaded successfully!');
        console.log('📊 System stats:', <?= json_encode($stats) ?>);
    </script>
</body>
</html>