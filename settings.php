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
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #eef2ff;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            --surface: #ffffff;
            --surface-secondary: #f8fafc;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --text: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --success: #10b981;
            --success-light: #ecfdf5;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --purple: #8b5cf6;
            --purple-light: #f5f3ff;
            --cyan: #06b6d4;
            --cyan-light: #ecfeff;
            --rose: #f43f5e;
            --rose-light: #fff1f2;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-glow: 0 0 40px -10px rgba(99, 102, 241, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 20px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .page-container {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* ==================== HEADER - MODERN ==================== */
        .page-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4f46e5 60%, #6366f1 100%);
            border-radius: 1.5rem;
            padding: 2rem 2.5rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl), var(--shadow-glow);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: 5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-10px) scale(1.05); }
        }

        .page-header-content {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.875rem;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }

        .icon-hex {
            width: 52px;
            height: 52px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
        }

        .header-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 2rem;
            font-size: 0.8rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .header-badge:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .header-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ==================== TABS - PILL STYLE ==================== */
        .tabs-nav {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.375rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow-x: auto;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .tab-btn {
            flex: 1;
            min-width: max-content;
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            border-radius: 0.75rem;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            white-space: nowrap;
            position: relative;
        }

        .tab-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 0.75rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            background: var(--primary-gradient);
            z-index: -1;
        }

        .tab-btn:hover {
            color: var(--text-secondary);
            background: var(--surface-secondary);
        }

        .tab-btn.active {
            color: white;
            background: var(--primary-gradient);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            transform: scale(1.03);
        }

        .tab-btn.danger-tab.active {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .tab-content {
            display: none;
            animation: fadeSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeSlideIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ==================== CARDS - MINIMALIST ==================== */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            border-color: #cbd5e1;
        }

        .card-header {
            padding: 1.25rem 1.75rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        .icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .card:hover .icon-box {
            transform: rotate(-5deg) scale(1.1);
        }

        .icon-box.indigo { background: #eef2ff; color: #6366f1; }
        .icon-box.amber { background: #fffbeb; color: #f59e0b; }
        .icon-box.emerald { background: #ecfdf5; color: #10b981; }
        .icon-box.violet { background: #f5f3ff; color: #8b5cf6; }
        .icon-box.cyan { background: #ecfeff; color: #06b6d4; }
        .icon-box.rose { background: #fff1f2; color: #f43f5e; }

        .card-body {
            padding: 1.75rem;
        }

        /* ==================== STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: default;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            border-radius: 0 0 0 100%;
            opacity: 0.05;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::after {
            opacity: 0.1;
        }

        .stat-card:nth-child(1)::after { background: #6366f1; }
        .stat-card:nth-child(2)::after { background: #f59e0b; }
        .stat-card:nth-child(3)::after { background: #10b981; }
        .stat-card:nth-child(4)::after { background: #8b5cf6; }

        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ==================== FORM ELEMENTS ==================== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-label i {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            outline: none;
            font-family: 'Sarabun', sans-serif;
            background: #f8fafc;
            color: var(--text);
            width: 100%;
            transition: all 0.25s ease;
        }

        .form-input:hover {
            border-color: #cbd5e1;
            background: white;
        }

        .form-input:focus {
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
        }

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .help-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .help-text::before {
            content: '';
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #cbd5e1;
            flex-shrink: 0;
        }

        /* ==================== TOGGLE SWITCH ==================== */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem;
            border-radius: 0.75rem;
            transition: background 0.2s ease;
        }

        .toggle-row:hover {
            background: #f8fafc;
        }

        .toggle-info {
            flex: 1;
        }

        .toggle-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .toggle-desc {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 28px;
            flex-shrink: 0;
            margin-left: 1.5rem;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #cbd5e1;
            transition: all 0.3s ease;
            border-radius: 28px;
        }

        .switch-slider:before {
            content: "";
            position: absolute;
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: all 0.3s ease;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        input:checked + .switch-slider {
            background: #6366f1;
        }

        input:checked + .switch-slider:before {
            transform: translateX(20px);
        }

        /* ==================== DANGER ZONE ==================== */
        .danger-zone {
            border: 2px dashed #fca5a5;
            border-radius: 1.25rem;
            padding: 2rem;
            background: #fef2f2;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .danger-zone:hover {
            border-color: #f87171;
            background: #fef2f2;
        }

        .danger-zone-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #991b1b;
        }

        .danger-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .danger-btn {
            padding: 0.875rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            border: 1px solid #fca5a5;
            background: white;
            color: #dc2626;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }

        .danger-btn:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .danger-btn.warning {
            border-color: #fcd34d;
            color: #d97706;
        }
        .danger-btn.warning:hover {
            background: #d97706;
            border-color: #d97706;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }

        /* ==================== BUTTONS ==================== */
        .action-bar {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.75rem;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-outline {
            background: white;
            color: var(--text-secondary);
            border: 1.5px solid #e2e8f0;
        }
        .btn-outline:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
        }
        .btn-ghost:hover {
            background: #f1f5f9;
            color: var(--text-secondary);
        }

        /* ==================== LOGO UPLOAD ==================== */
        .logo-upload-group {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .logo-preview {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: contain;
            border: 3px solid #e2e8f0;
            background: white;
            padding: 6px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .logo-preview:hover {
            border-color: #6366f1;
            box-shadow: var(--shadow-md);
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: #eef2ff;
            color: #6366f1;
            border: 1.5px dashed #a5b4fc;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 0.825rem;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .upload-btn:hover {
            background: #e0e7ff;
            border-color: #818cf8;
        }

        /* ==================== ALERTS ==================== */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.875rem;
            margin-bottom: 1.25rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: alertIn 0.4s ease;
        }

        @keyframes alertIn {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* ==================== INFO BOX ==================== */
        .tip-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 1.25rem;
            padding: 1.5rem 1.75rem;
            margin-top: 2rem;
            display: flex;
            gap: 1.25rem;
            transition: all 0.3s ease;
        }

        .tip-box:hover {
            border-color: #7dd3fc;
            box-shadow: var(--shadow-md);
        }

        .tip-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            color: #0284c7;
            box-shadow: var(--shadow-sm);
        }

        .tip-content h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0c4a6e;
            margin-bottom: 0.5rem;
        }

        .tip-content p {
            font-size: 0.8rem;
            color: #475569;
            line-height: 1.7;
        }

        .last-updated {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.375rem;
            margin-top: 0.75rem;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
            .logo-upload-group { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .danger-grid { grid-template-columns: 1fr; }
            .tabs-nav { gap: 0.125rem; }
            .tab-btn { padding: 0.625rem 1rem; font-size: 0.8rem; }
            .page-header { padding: 1.5rem; border-radius: 1rem; }
            .page-header h1 { font-size: 1.35rem; }
            .header-meta { gap: 0.75rem; }
            .action-bar { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .toggle-row { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .switch { margin-left: 0; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        <div class="flex-1 p-4 md:p-6 overflow-y-auto">
            <div class="page-container">

                <!-- ==================== HEADER ==================== -->
                <div class="page-header">
                    <div class="page-header-content">
                        <h1>
                            <span class="icon-hex">⚙️</span> 
                            การตั้งค่าระบบ
                        </h1>
                        <div class="header-meta">
                            <span class="header-badge">
                                <span class="dot"></span> ออนไลน์
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-shield-alt"></i> ผู้ดูแลระบบเท่านั้น
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-sync-alt"></i> ผลลัพธ์ทันที
                            </span>
                        </div>
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
                        <i class="fas fa-sliders-h"></i> ทั่วไป
                    </button>
                    <button class="tab-btn" data-tab="display">
                        <i class="fas fa-palette"></i> แสดงผล
                    </button>
                    <button class="tab-btn" data-tab="notifications">
                        <i class="fas fa-bell"></i> แจ้งเตือน
                    </button>
                    <button class="tab-btn" data-tab="security">
                        <i class="fas fa-lock"></i> ความปลอดภัย
                    </button>
                    <button class="tab-btn danger-tab" data-tab="system">
                        <i class="fas fa-database"></i> ระบบ
                    </button>
                </div>

                <!-- ==================== MAIN FORM ==================== -->
                <form method="POST" id="settingsForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="save_settings" id="formAction">

                    <!-- TAB: ทั่วไป -->
                    <div class="tab-content active" id="tab-general">
                        <div class="card">
                            <div class="card-header">
                                <div class="icon-box indigo"><i class="fas fa-info-circle"></i></div>
                                <div class="card-title">ข้อมูลระบบ</div>
                            </div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label class="form-label"><i class="fas fa-heading"></i> ชื่อระบบ</label>
                                        <input type="text" name="site_name" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-align-left"></i> คำอธิบาย</label>
                                        <input type="text" name="site_description" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_description']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-building"></i> หน่วยงาน</label>
                                        <input type="text" name="site_organization" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_organization']) ?>">
                                    </div>
                                    <div class="form-group full-width">
                                        <label class="form-label"><i class="fas fa-image"></i> โลโก้</label>
                                        <div class="logo-upload-group">
                                            <img src="<?= htmlspecialchars($settings['site_logo']) ?>" 
                                                 alt="Logo" class="logo-preview" id="logoPreview"
                                                 onerror="this.src='assets/default-logo.png'">
                                            <div>
                                                <label class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i> เลือกไฟล์
                                                    <input type="file" name="site_logo_file" accept="image/*" 
                                                           style="display:none" onchange="previewLogo(this)">
                                                </label>
                                                <span class="help-text" style="margin-top:0.5rem;">PNG, JPG, SVG หรือ WebP</span>
                                            </div>
                                        </div>
                                        <input type="text" name="site_logo_url" class="form-input" 
                                               value="<?= htmlspecialchars($settings['site_logo']) ?>" 
                                               placeholder="หรือวาง URL โลโก้" style="margin-top:1rem;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="icon-box cyan"><i class="fas fa-cog"></i></div>
                                <div class="card-title">ค่าพื้นฐาน</div>
                            </div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-list"></i> รายการต่อหน้า</label>
                                        <select name="items_per_page" class="form-input">
                                            <?php foreach ([5, 10, 25, 50, 100] as $val): ?>
                                                <option value="<?= $val ?>" <?= $settings['items_per_page'] == $val ? 'selected' : '' ?>>
                                                    <?= $val ?> รายการ
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-clock"></i> หมดเวลาเซสชัน (นาที)</label>
                                        <input type="number" name="session_timeout" class="form-input" 
                                               value="<?= htmlspecialchars($settings['session_timeout']) ?>" min="5" max="240">
                                        <span class="help-text">5 - 240 นาที</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: แสดงผล -->
                    <div class="tab-content" id="tab-display">
                        <div class="card">
                            <div class="card-header">
                                <div class="icon-box violet"><i class="fas fa-columns"></i></div>
                                <div class="card-title">เมนูนำทาง</div>
                            </div>
                            <div class="card-body">
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-title">📊 แดชบอร์ด</div>
                                        <div class="toggle-desc">แสดงเมนูภาพรวมระบบ</div>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="sidebar_show_dashboard" value="1" 
                                               <?= ($settings['sidebar_show_dashboard'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-title">📈 รายงาน</div>
                                        <div class="toggle-desc">แสดงเมนูรายงานผล</div>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="sidebar_show_reports" value="1" 
                                               <?= ($settings['sidebar_show_reports'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: แจ้งเตือน -->
                    <div class="tab-content" id="tab-notifications">
                        <div class="card">
                            <div class="card-header">
                                <div class="icon-box amber"><i class="fas fa-bell"></i></div>
                                <div class="card-title">ตั้งค่าการแจ้งเตือน</div>
                            </div>
                            <div class="card-body">
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-title">🔔 เปิดการแจ้งเตือน</div>
                                        <div class="toggle-desc">แจ้งเตือนเมื่อสถานะความเสี่ยงเปลี่ยนแปลง</div>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="notification_enabled" value="1" 
                                               <?= $settings['notification_enabled'] == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-title">💾 สำรองข้อมูลอัตโนมัติ</div>
                                        <div class="toggle-desc">สำรองข้อมูลทุกวันเวลา 00:00 น.</div>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="backup_enabled" value="1" 
                                               <?= $settings['backup_enabled'] == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: ความปลอดภัย -->
                    <div class="tab-content" id="tab-security">
                        <div class="card">
                            <div class="card-header">
                                <div class="icon-box emerald"><i class="fas fa-shield-alt"></i></div>
                                <div class="card-title">นโยบายความปลอดภัย</div>
                            </div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-lock"></i> จำนวนเข้าสู่ระบบผิดสูงสุด</label>
                                        <input type="number" name="login_attempts" class="form-input" 
                                               value="<?= htmlspecialchars($settings['login_attempts']) ?>" min="1" max="10">
                                        <span class="help-text">ก่อนล็อคบัญชีชั่วคราว</span>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-key"></i> ความยาวรหัสผ่านขั้นต่ำ</label>
                                        <input type="number" name="password_min_length" class="form-input" 
                                               value="<?= htmlspecialchars($settings['password_min_length']) ?>" min="6" max="20">
                                        <span class="help-text">จำนวนตัวอักษร</span>
                                    </div>
                                </div>
                                <div class="toggle-row">
                                    <div class="toggle-info">
                                        <div class="toggle-title">🔧 โหมดบำรุงรักษา</div>
                                        <div class="toggle-desc">ปิดการเข้าถึงสำหรับผู้ใช้ทั่วไป</div>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="maintenance_mode" value="1" 
                                               <?= $settings['maintenance_mode'] == '1' ? 'checked' : '' ?>>
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="action-bar" id="saveButtons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึกการตั้งค่า
                        </button>
                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                        <button type="button" class="btn btn-ghost" id="resetBtn">
                            <i class="fas fa-undo"></i> คืนค่าเริ่มต้น
                        </button>
                    </div>
                    <div class="last-updated" id="lastSavedInfo">
                        <i class="fas fa-history"></i> อัปเดตล่าสุด: <?= date('d/m/Y H:i') ?>
                    </div>
                </form>

                <!-- TAB: ระบบ -->
                <div class="tab-content" id="tab-system">
                    <!-- Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background:#eef2ff;color:#6366f1;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['users']) ?></div>
                            <div class="stat-label">ผู้ใช้</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background:#fffbeb;color:#f59e0b;">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['admin_count']) ?></div>
                            <div class="stat-label">แอดมิน</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background:#ecfdf5;color:#10b981;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['risks']) ?></div>
                            <div class="stat-label">ความเสี่ยง</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background:#f5f3ff;color:#8b5cf6;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['reports']) ?></div>
                            <div class="stat-label">รายงาน</div>
                        </div>
                    </div>

                    <!-- Import -->
                    <div class="card">
                        <div class="card-header">
                            <div class="icon-box emerald"><i class="fas fa-file-import"></i></div>
                            <div class="card-title">นำเข้าข้อมูล</div>
                        </div>
                        <div class="card-body">
                            <p style="color:#475569;margin-bottom:1rem;font-size:0.875rem;">
                                นำเข้าข้อมูลทดสอบจาก <code style="background:#f1f5f9;padding:0.15rem 0.5rem;border-radius:0.25rem;">install.sql</code>
                            </p>
                            <form method="POST" id="importForm">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="import_sample_data">
                                <button type="button" class="btn btn-primary" onclick="confirmImport()">
                                    <i class="fas fa-download"></i> นำเข้าเลย
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="danger-zone">
                        <div class="danger-zone-header">
                            <i class="fas fa-radiation-alt"></i> โซนอันตราย
                        </div>
                        <p style="color:#991b1b;font-size:0.85rem;margin-bottom:1.5rem;">
                            ⚠️ การล้างข้อมูลไม่สามารถกู้คืนได้
                        </p>
                        <form method="POST" id="clearDataForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="clear_data">
                            <input type="hidden" name="clear_type" id="clearType">
                            <div class="danger-grid">
                                <button type="button" class="danger-btn warning" onclick="confirmClear('risks')">
                                    <i class="fas fa-clipboard-list"></i> ล้างความเสี่ยง
                                </button>
                                <button type="button" class="danger-btn warning" onclick="confirmClear('reports')">
                                    <i class="fas fa-file-alt"></i> ล้างรายงาน
                                </button>
                                <button type="button" class="danger-btn" onclick="confirmClear('users_except_admin')">
                                    <i class="fas fa-users-slash"></i> ล้างผู้ใช้
                                </button>
                                <button type="button" class="danger-btn" onclick="confirmClear('all')">
                                    <i class="fas fa-skull"></i> ล้างทั้งหมด
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tip Box -->
                <div class="tip-box">
                    <div class="tip-icon"><i class="fas fa-lightbulb"></i></div>
                    <div class="tip-content">
                        <h4>เคล็ดลับ</h4>
                        <p>
                            • การตั้งค่ามีผลทันทีหลังบันทึก<br>
                            • ตั้งเวลาหมดเซสชันให้เหมาะสมเพื่อความปลอดภัย<br>
                            • สำรองข้อมูลก่อนล้างหรือนำเข้าข้อมูล
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // ===== TABS =====
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                localStorage.setItem('settingsTab', this.dataset.tab);
                
                const saveBtns = document.getElementById('saveButtons');
                const lastUpd = document.getElementById('lastSavedInfo');
                if (this.dataset.tab === 'system') {
                    if (saveBtns) saveBtns.style.display = 'none';
                    if (lastUpd) lastUpd.style.display = 'none';
                } else {
                    if (saveBtns) saveBtns.style.display = 'flex';
                    if (lastUpd) lastUpd.style.display = 'flex';
                }
            });
        });

        const saved = localStorage.getItem('settingsTab');
        if (saved) document.querySelector(`[data-tab="${saved}"]`)?.click();

        // ===== LOGO =====
        function previewLogo(input) {
            if (input.files?.[0]) {
                if (input.files[0].size > 2 * 1024 * 1024) {
                    Swal.fire({ icon: 'warning', title: 'ไฟล์ใหญ่เกินไป', text: 'สูงสุด 2MB', confirmButtonColor: '#6366f1' });
                    input.value = '';
                    return;
                }
                const r = new FileReader();
                r.onload = e => document.getElementById('logoPreview').src = e.target.result;
                r.readAsDataURL(input.files[0]);
            }
        }

        // ===== SAVE =====
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            if (document.getElementById('formAction').value !== 'save_settings') return;
            e.preventDefault();
            Swal.fire({
                title: 'บันทึก?',
                text: 'การตั้งค่าจะมีผลทันที',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    this.submit();
                }
            });
        });

        // ===== RESET =====
        document.getElementById('resetBtn').addEventListener('click', () => {
            Swal.fire({
                title: 'คืนค่าเริ่มต้น?',
                text: 'คุณยังต้องกดบันทึกเพื่อยืนยัน',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    document.querySelector('[name="site_name"]').value = 'Risk Management';
                    document.querySelector('[name="site_description"]').value = 'ระบบบริหารความเสี่ยง';
                    document.querySelector('[name="site_organization"]').value = 'ศูนย์อนามัยที่ 8 อุดรธานี';
                    document.querySelector('[name="site_logo_url"]').value = '';
                    document.querySelector('[name="items_per_page"]').value = '10';
                    document.querySelector('[name="session_timeout"]').value = '30';
                    document.querySelector('[name="login_attempts"]').value = '5';
                    document.querySelector('[name="password_min_length"]').value = '8';
                    document.querySelectorAll('.switch input').forEach(t => t.checked = true);
                    document.querySelector('[name="maintenance_mode"]').checked = false;
                    Swal.fire({ icon: 'success', title: 'รีเซ็ตแล้ว', timer: 2000, confirmButtonColor: '#6366f1' });
                }
            });
        });

        // ===== CLEAR =====
        function confirmClear(type) {
            const m = {
                risks: ['ล้างความเสี่ยง', 'ข้อมูลความเสี่ยงทั้งหมดจะถูกลบ'],
                reports: ['ล้างรายงาน', 'รายงานทั้งหมดจะถูกลบ'],
                users_except_admin: ['ล้างผู้ใช้', 'ผู้ใช้ทั้งหมด (ยกเว้นคุณ) จะถูกลบ'],
                all: ['ล้างทั้งหมด', 'ทุกอย่างจะถูกลบเหลือแค่ Admin']
            };
            Swal.fire({
                title: m[type][0] + '?',
                text: m[type][1],
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    if (type === 'all') {
                        Swal.fire({
                            title: 'แน่ใจสุดท้าย?',
                            text: 'ไม่มีทางกู้คืน!',
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            confirmButtonText: 'ล้าง!',
                        }).then(r2 => { if (r2.isConfirmed) submitClear(type); });
                    } else {
                        submitClear(type);
                    }
                }
            });
        }

        function submitClear(type) {
            document.getElementById('clearType').value = type;
            Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            document.getElementById('clearDataForm').submit();
        }

        // ===== IMPORT =====
        function confirmImport() {
            Swal.fire({
                title: 'นำเข้าข้อมูลทดสอบ?',
                text: 'ข้อมูลที่มีอยู่จะไม่ถูกเขียนทับ',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'นำเข้า',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    Swal.fire({ title: 'กำลังนำเข้า...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    document.getElementById('importForm').submit();
                }
            });
        }

        // ===== AUTO ALERTS =====
        <?php if ($success_message): ?>
            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: '<?= htmlspecialchars($success_message) ?>', timer: 3000, confirmButtonColor: '#6366f1' });
        <?php endif; ?>
        <?php if ($error_message): ?>
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: '<?= htmlspecialchars($error_message) ?>', confirmButtonColor: '#6366f1' });
        <?php endif; ?>
    </script>
</body>
</html>