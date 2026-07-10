<?php

/**
 * หน้าตั้งค่าระบบ - UI สวยงาม พร้อม Tab Navigation
 * - เฉพาะ Admin เท่านั้น
 * - จัดการการตั้งค่าทั่วไป, เมนู, ระบบ
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
    'site_url' => 'http://localhost/risk_management/',
    'menu_dashboard' => 'ภาพรวมระบบ',
    'menu_dashboard_visible' => '1',
    'menu_dashboard_url' => 'dashboard.php',
    'menu_risks' => 'รายการความเสี่ยง',
    'menu_risks_visible' => '1',
    'menu_risks_url' => 'risks.php',
    'menu_risk_form' => 'เพิ่มความเสี่ยง',
    'menu_risk_form_visible' => '1',
    'menu_risk_form_url' => 'risk_form.php',
    'menu_reports' => 'รายงาน',
    'menu_reports_visible' => '1',
    'menu_reports_url' => 'reports.php',
    'menu_users' => 'จัดการผู้ใช้',
    'menu_users_visible' => '1',
    'menu_users_url' => 'users.php',
    'menu_settings' => 'ตั้งค่าระบบ',
    'menu_settings_visible' => '1',
    'menu_settings_url' => 'settings.php',
    'menu_logout' => 'ออกจากระบบ',
    'menu_logout_visible' => '1',
    'menu_logout_url' => 'logout.php'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// ===== จัดการ Action =====
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_csrf = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    
    if ($post_csrf !== $session_csrf) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : 'save_settings';
        
        if ($action === 'clear_data') {
            $clear_type = isset($_POST['clear_type']) ? $_POST['clear_type'] : '';
            
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
                        $success_message = 'ล้างข้อมูลผู้ใช้ทั้งหมด (ยกเว้น Admin) สำเร็จ';
                        break;
                    case 'all_except_users':
                        $pdo->exec("DELETE FROM risk_reports");
                        $pdo->exec("DELETE FROM risks");
                        $pdo->exec("DELETE FROM user_tokens");
                        $pdo->exec("DELETE FROM login_attempts");
                        $pdo->exec("DELETE FROM system_settings");
                        $pdo->exec("ALTER TABLE risk_reports AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE risks AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE user_tokens AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE login_attempts AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE system_settings AUTO_INCREMENT = 1");
                        $success_message = 'ล้างข้อมูลทุกตารางสำเร็จ (ยกเว้นข้อมูลผู้ใช้)';
                        break;
                    case 'all':
                        $adminId = $_SESSION['user_id'];
                        $adminUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $adminUser->execute([$adminId]);
                        $admin = $adminUser->fetch();
                        
                        $pdo->exec("DELETE FROM risk_reports");
                        $pdo->exec("DELETE FROM risks");
                        $pdo->exec("DELETE FROM user_tokens");
                        $pdo->exec("DELETE FROM login_attempts");
                        $pdo->exec("DELETE FROM system_settings");
                        $pdo->exec("DELETE FROM users");
                        $pdo->exec("ALTER TABLE risk_reports AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE risks AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE user_tokens AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE login_attempts AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE system_settings AUTO_INCREMENT = 1");
                        $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 1");
                        
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
            $sql_file = __DIR__ . '/install.sql';
            
            if (!file_exists($sql_file)) {
                $error_message = 'ไม่พบไฟล์ install.sql';
            } else {
                try {
                    $pdo->beginTransaction();
                    $sql_content = file_get_contents($sql_file);
                    $queries = array_filter(array_map('trim', explode(';', $sql_content)));
                    
                    $imported = 0;
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            try { $pdo->exec($query); $imported++; } 
                            catch (Exception $e) { continue; }
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
            try {
                $pdo->beginTransaction();
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                $logo_path = isset($_POST['site_logo_url']) ? $_POST['site_logo_url'] : ($settings['site_logo'] ?? '');
                
                if (isset($_FILES['site_logo_file']) && is_array($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'assets/uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    $file_extension = strtolower(pathinfo($_FILES['site_logo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                        $new_filename = 'logo_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $upload_path)) {
                            $logo_path = $upload_path;
                        }
                    }
                }
                
                $settings_to_save = [
                    'site_name' => $_POST['site_name'] ?? 'Risk Management',
                    'site_description' => $_POST['site_description'] ?? '',
                    'site_organization' => $_POST['site_organization'] ?? '',
                    'site_logo' => $logo_path,
                    'site_url' => $_POST['site_url'] ?? '',
                    'items_per_page' => $_POST['items_per_page'] ?? '10',
                    'session_timeout' => $_POST['session_timeout'] ?? '30',
                    'menu_dashboard' => $_POST['menu_dashboard'] ?? 'ภาพรวมระบบ',
                    'menu_dashboard_visible' => isset($_POST['menu_dashboard_visible']) ? '1' : '0',
                    'menu_dashboard_url' => $_POST['menu_dashboard_url'] ?? 'dashboard.php',
                    'menu_risks' => $_POST['menu_risks'] ?? 'รายการความเสี่ยง',
                    'menu_risks_visible' => isset($_POST['menu_risks_visible']) ? '1' : '0',
                    'menu_risks_url' => $_POST['menu_risks_url'] ?? 'risks.php',
                    'menu_risk_form' => $_POST['menu_risk_form'] ?? 'เพิ่มความเสี่ยง',
                    'menu_risk_form_visible' => isset($_POST['menu_risk_form_visible']) ? '1' : '0',
                    'menu_risk_form_url' => $_POST['menu_risk_form_url'] ?? 'risk_form.php',
                    'menu_reports' => $_POST['menu_reports'] ?? 'รายงาน',
                    'menu_reports_visible' => isset($_POST['menu_reports_visible']) ? '1' : '0',
                    'menu_reports_url' => $_POST['menu_reports_url'] ?? 'reports.php',
                    'menu_users' => $_POST['menu_users'] ?? 'จัดการผู้ใช้',
                    'menu_users_visible' => isset($_POST['menu_users_visible']) ? '1' : '0',
                    'menu_users_url' => $_POST['menu_users_url'] ?? 'users.php',
                    'menu_settings' => $_POST['menu_settings'] ?? 'ตั้งค่าระบบ',
                    'menu_settings_visible' => isset($_POST['menu_settings_visible']) ? '1' : '0',
                    'menu_settings_url' => $_POST['menu_settings_url'] ?? 'settings.php',
                    'menu_logout' => $_POST['menu_logout'] ?? 'ออกจากระบบ',
                    'menu_logout_visible' => isset($_POST['menu_logout_visible']) ? '1' : '0',
                    'menu_logout_url' => $_POST['menu_logout_url'] ?? 'logout.php'
                ];
                
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
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

$stats = [];
try {
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['risks'] = $pdo->query("SELECT COUNT(*) FROM risks")->fetchColumn();
    $stats['reports'] = 0;
    try { $stats['reports'] = $pdo->query("SELECT COUNT(*) FROM risk_reports")->fetchColumn(); } catch (Exception $e) {}
    $stats['admin_count'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
} catch (Exception $e) {
    $stats = ['users' => 0, 'risks' => 0, 'reports' => 0, 'admin_count' => 0];
}

$current_url = $settings['site_url'] ?? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
$csrf_token = generateCsrfToken();
$page_title = 'ตั้งค่าระบบ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Risk Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            --border: #e2e8f0;
            --text: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f8fafc; min-height: 100vh; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 20px; }
        .page-container { max-width: 1280px; margin: 0 auto; }

        .page-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4f46e5 60%, #6366f1 100%);
            border-radius: 1.5rem; padding: 2rem 2.5rem; margin-bottom: 1.5rem;
            color: white; position: relative; overflow: hidden; box-shadow: var(--shadow-xl);
        }
        .page-header-content { position: relative; z-index: 1; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; display: flex; align-items: center; gap: 0.875rem; margin-bottom: 1rem; }
        .icon-hex {
            width: 52px; height: 52px; background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .header-meta { display: flex; gap: 1rem; flex-wrap: wrap; }
        .header-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1rem; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 2rem;
            font-size: 0.8rem; backdrop-filter: blur(10px); transition: all 0.3s;
        }
        .header-badge:hover { background: rgba(255,255,255,0.2); transform: translateY(-1px); }
        .header-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: #34d399; animation: pulse-dot 2s ease-in-out infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:0.4} }

        .tabs-nav {
            display: flex; gap: 0.25rem; margin-bottom: 1.5rem;
            background: white; padding: 0.375rem; border-radius: 1rem;
            box-shadow: var(--shadow-sm); border: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
        }
        .tab-btn {
            flex: 1; padding: 0.75rem 1.5rem; border: none; background: transparent;
            border-radius: 0.75rem; cursor: pointer; font-family: 'Sarabun', sans-serif;
            font-weight: 600; font-size: 0.85rem; color: var(--text-muted);
            transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        }
        .tab-btn:hover { color: var(--text-secondary); background: #f8fafc; }
        .tab-btn.active { color: white; background: var(--primary-gradient); box-shadow: 0 4px 15px rgba(99,102,241,0.3); transform: scale(1.03); }
        .tab-btn.danger-tab.active { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .tab-content { display: none; animation: fadeIn 0.4s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

        .card { background: white; border-radius: 1.25rem; border: 1px solid var(--border); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); overflow: hidden; }
        .card-header { padding: 1.25rem 1.75rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 1rem; }
        .card-title { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .icon-box { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .icon-box.indigo { background: #eef2ff; color: #6366f1; }
        .icon-box.cyan { background: #ecfeff; color: #06b6d4; }
        .icon-box.blue { background: #eff6ff; color: #3b82f6; }
        .icon-box.emerald { background: #ecfdf5; color: #10b981; }
        .card-body { padding: 1.75rem; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; }
        .form-input { padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 0.75rem; font-size: 0.875rem; font-family: 'Sarabun', sans-serif; background: #f8fafc; width: 100%; transition: all 0.25s; outline: none; }
        .form-input:focus { border-color: #6366f1; background: white; box-shadow: 0 0 0 4px rgba(99,102,241,0.08); }

        .switch { position: relative; display: inline-block; width: 48px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; transition: 0.3s; border-radius: 28px; }
        .switch-slider:before { content: ""; position: absolute; height: 22px; width: 22px; left: 3px; bottom: 3px; background: white; transition: 0.3s; border-radius: 50%; }
        input:checked + .switch-slider { background: #6366f1; }
        input:checked + .switch-slider:before { transform: translateX(20px); }

        /* ========== MENU ITEM ROW - แสดงชัดเจน ========== */
        .menu-item-row {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem 1.25rem; background: #f8fafc;
            border-radius: 0.75rem; border: 1px solid #e2e8f0;
            margin-bottom: 0.75rem; transition: all 0.2s;
        }
        .menu-item-row:hover { background: white; border-color: #cbd5e1; }
        .menu-item-row.hidden-menu { opacity: 0.4; background: #f1f5f9; }
        
        .menu-item-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        
        .menu-item-details {
            flex: 1; display: flex; gap: 1rem; align-items: center;
        }
        
        .menu-item-name {
            flex: 1; min-width: 0;
        }
        .menu-item-name input {
            width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #e2e8f0;
            border-radius: 0.625rem; font-size: 0.875rem; font-family: 'Sarabun', sans-serif;
            background: white; outline: none; font-weight: 600;
        }
        .menu-item-name input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        
        .menu-item-url-group {
            flex: 1.5; min-width: 0;
            display: flex; align-items: center; gap: 0.5rem;
            background: #f1f5f9; border-radius: 0.625rem;
            padding: 0.5rem 0.75rem; border: 1.5px solid #e2e8f0;
        }
        .menu-item-url-group label {
            font-size: 0.75rem; font-weight: 600; color: var(--text-muted);
            white-space: nowrap; display: flex; align-items: center; gap: 0.25rem;
        }
        .menu-item-url-group label i { font-size: 0.65rem; }
        .menu-item-url-group input {
            flex: 1; border: none; background: transparent;
            font-size: 0.8rem; font-family: 'Courier New', monospace;
            color: #2563eb; outline: none; padding: 0;
        }
        .menu-item-url-group input:focus { outline: none; }
        .menu-item-url-group:hover { border-color: #cbd5e1; background: #eef2ff; }
        .menu-item-url-group:focus-within { border-color: #6366f1; background: white; }
        
        .menu-item-actions {
            display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;
        }
        .drag-handle {
            cursor: grab; color: #94a3b8; padding: 0.3rem;
            font-size: 1rem; flex-shrink: 0;
        }
        .drag-handle:hover { color: #6366f1; background: #eef2ff; border-radius: 0.4rem; }

        /* URL Preview */
        .url-preview {
            font-size: 0.7rem; color: #10b981; margin-top: 0.25rem;
            display: flex; align-items: center; gap: 0.25rem;
        }
        .url-preview i { font-size: 0.6rem; }

        .url-display {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem;
            padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;
        }
        .url-display i { color: #10b981; font-size: 1.2rem; }
        .url-display code { flex: 1; font-size: 0.875rem; color: #065f46; word-break: break-all; font-family: monospace; }
        .url-display .copy-btn { padding: 0.4rem 0.8rem; background: #10b981; color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.75rem; font-weight: 600; }
        .url-display .copy-btn:hover { background: #059669; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        .stat-card { background: white; border-radius: 1.25rem; padding: 1.5rem; border: 1px solid var(--border); transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 1.25rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--text); }
        .stat-label { font-size: 0.78rem; color: var(--text-muted); }

        .danger-zone { border: 2px dashed #fca5a5; border-radius: 1.25rem; padding: 2rem; background: #fef2f2; margin-bottom: 1.5rem; }
        .danger-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
        .danger-btn { padding: 0.875rem 1.25rem; border-radius: 0.75rem; font-weight: 600; cursor: pointer; font-family: 'Sarabun', sans-serif; font-size: 0.85rem; display: flex; align-items: center; gap: 0.625rem; border: 1px solid #fca5a5; background: white; color: #dc2626; transition: all 0.3s; width: 100%; justify-content: center; }
        .danger-btn:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        .danger-btn.warning { border-color: #fcd34d; color: #d97706; }
        .danger-btn.warning:hover { background: #d97706; color: white; }
        .danger-btn.info { border-color: #93c5fd; color: #2563eb; }
        .danger-btn.info:hover { background: #2563eb; color: white; }

        .action-bar { display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1.75rem; border-top: 1px solid #f1f5f9; }
        .btn { padding: 0.75rem 1.75rem; border-radius: 0.75rem; font-weight: 600; cursor: pointer; font-family: 'Sarabun', sans-serif; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border: none; transition: all 0.3s; }
        .btn-primary { background: var(--primary-gradient); color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
        .btn-outline { background: white; color: var(--text-secondary); border: 1.5px solid #e2e8f0; }
        .btn-outline:hover { border-color: #cbd5e1; background: #f8fafc; }
        .btn-ghost { background: transparent; color: var(--text-muted); }
        .btn-ghost:hover { background: #f1f5f9; color: var(--text-secondary); }

        .logo-upload-group { display: flex; align-items: center; gap: 2rem; }
        .logo-preview { width: 88px; height: 88px; border-radius: 50%; object-fit: contain; border: 3px solid #e2e8f0; background: white; padding: 6px; transition: all 0.3s; }
        .logo-preview:hover { border-color: #6366f1; box-shadow: var(--shadow-md); }
        .upload-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; background: #eef2ff; color: #6366f1; border: 1.5px dashed #a5b4fc; border-radius: 0.75rem; cursor: pointer; font-size: 0.825rem; font-weight: 600; transition: all 0.25s; }
        .upload-btn:hover { background: #e0e7ff; border-color: #818cf8; }

        .alert { padding: 1rem 1.25rem; border-radius: 0.875rem; margin-bottom: 1.25rem; font-weight: 500; font-size: 0.875rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .tip-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 1.25rem; padding: 1.5rem 1.75rem; margin-top: 2rem; display: flex; gap: 1.25rem; }
        .tip-icon { width: 44px; height: 44px; border-radius: 12px; background: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #0284c7; box-shadow: var(--shadow-sm); }
        .tip-content h4 { font-size: 0.9rem; font-weight: 700; color: #0c4a6e; margin-bottom: 0.5rem; }
        .tip-content p { font-size: 0.8rem; color: #475569; line-height: 1.7; }
        .last-updated { font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.375rem; margin-top: 0.75rem; }

        @media (max-width: 1024px) {
            .menu-item-details { flex-direction: column; gap: 0.5rem; }
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .danger-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .page-header { padding: 1.5rem; }
            .page-header h1 { font-size: 1.35rem; }
        }
    </style>
</head>
<body>
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        <div class="flex-1 p-4 md:p-6 overflow-y-auto">
            <div class="page-container">

                <!-- HEADER -->
                <div class="page-header">
                    <div class="page-header-content">
                        <h1><span class="icon-hex">⚙️</span> การตั้งค่าระบบ</h1>
                        <div class="header-meta">
                            <span class="header-badge"><span class="dot"></span> ออนไลน์</span>
                            <span class="header-badge"><i class="fas fa-shield-alt"></i> ผู้ดูแลระบบ</span>
                            <span class="header-badge"><i class="fas fa-sync-alt"></i> ผลลัพธ์ทันที</span>
                        </div>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <!-- TABS -->
                <div class="tabs-nav">
                    <button class="tab-btn active" data-tab="general"><i class="fas fa-sliders-h"></i> ทั่วไป</button>
                    <button class="tab-btn" data-tab="menu"><i class="fas fa-bars"></i> เมนู</button>
                    <button class="tab-btn danger-tab" data-tab="system"><i class="fas fa-database"></i> ระบบ</button>
                </div>

                <!-- FORM -->
                <form method="POST" id="settingsForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="save_settings" id="formAction">

                    <!-- TAB: ทั่วไป -->
                    <div class="tab-content active" id="tab-general">
                        <div class="card">
                            <div class="card-header"><div class="icon-box indigo"><i class="fas fa-info-circle"></i></div><div class="card-title">ข้อมูลระบบ</div></div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group full-width"><label class="form-label">ชื่อระบบ</label><input type="text" name="site_name" class="form-input" value="<?= htmlspecialchars($settings['site_name']) ?>" required></div>
                                    <div class="form-group"><label class="form-label">คำอธิบาย</label><input type="text" name="site_description" class="form-input" value="<?= htmlspecialchars($settings['site_description']) ?>"></div>
                                    <div class="form-group"><label class="form-label">หน่วยงาน</label><input type="text" name="site_organization" class="form-input" value="<?= htmlspecialchars($settings['site_organization']) ?>"></div>
                                    <div class="form-group full-width"><label class="form-label"><i class="fas fa-link"></i> URL ของเว็บไซต์</label><input type="text" name="site_url" class="form-input" value="<?= htmlspecialchars($current_url) ?>" placeholder="http://localhost/risk_management/"></div>
                                    <div class="form-group full-width">
                                        <label class="form-label">โลโก้</label>
                                        <div class="logo-upload-group">
                                            <img src="<?= htmlspecialchars($settings['site_logo']) ?>" alt="Logo" class="logo-preview" id="logoPreview" onerror="this.src='assets/default-logo.png'">
                                            <div><label class="upload-btn"><i class="fas fa-cloud-upload-alt"></i> เลือกไฟล์<input type="file" name="site_logo_file" accept="image/*" style="display:none" onchange="previewLogo(this)"></label></div>
                                        </div>
                                        <input type="text" name="site_logo_url" class="form-input" value="<?= htmlspecialchars($settings['site_logo']) ?>" placeholder="หรือวาง URL โลโก้" style="margin-top:1rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><div class="icon-box cyan"><i class="fas fa-cog"></i></div><div class="card-title">ค่าพื้นฐาน</div></div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group"><label class="form-label">รายการต่อหน้า</label><select name="items_per_page" class="form-input"><?php foreach ([5, 10, 25, 50, 100] as $val): ?><option value="<?= $val ?>" <?= ($settings['items_per_page'] ?? '10') == $val ? 'selected' : '' ?>><?= $val ?> รายการ</option><?php endforeach; ?></select></div>
                                    <div class="form-group"><label class="form-label">หมดเวลาเซสชัน (นาที)</label><input type="number" name="session_timeout" class="form-input" value="<?= htmlspecialchars($settings['session_timeout'] ?? '30') ?>" min="5" max="240"></div>
                                </div>
                            </div>
                        </div>
                        <div class="url-display">
                            <i class="fas fa-globe"></i>
                            <code id="displayUrl"><?= htmlspecialchars($current_url) ?></code>
                            <button type="button" class="copy-btn" onclick="copyUrl()"><i class="fas fa-copy"></i> คัดลอก</button>
                        </div>
                    </div>

                    <!-- TAB: เมนู (แสดง URL ชัดเจน) -->
                    <div class="tab-content" id="tab-menu">
                        <div class="card">
                            <div class="card-header"><div class="icon-box blue"><i class="fas fa-edit"></i></div><div class="card-title">จัดการเมนู Sidebar</div></div>
                            <div class="card-body">
                                <p style="color:#475569;font-size:0.875rem;margin-bottom:1.5rem;">
                                    <i class="fas fa-info-circle" style="color:#6366f1;"></i> 
                                    แก้ไข<strong>ชื่อเมนู</strong>, <strong>ลิงก์ URL</strong>, และ<strong>เปิด/ปิด</strong>การแสดงผล
                                </p>
                                
                                <!-- Header -->
                                <div style="display:flex;align-items:center;gap:1rem;padding:0.5rem 1.25rem;margin-bottom:0.5rem;font-size:0.7rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">
                                    <span style="width:28px;"></span>
                                    <span style="width:40px;"></span>
                                    <span style="flex:1;">ชื่อเมนู</span>
                                    <span style="flex:1.5;">ลิงก์ (URL)</span>
                                    <span style="width:80px;text-align:center;">แสดงผล</span>
                                </div>
                                
                                <div id="menuItemsContainer">
                                    <?php
                                    $menuItems = [
                                        ['key' => 'dashboard', 'icon' => 'fa-tachometer-alt', 'color' => '#6366f1', 'bg' => '#eef2ff'],
                                        ['key' => 'risks', 'icon' => 'fa-clipboard-list', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
                                        ['key' => 'risk_form', 'icon' => 'fa-plus-circle', 'color' => '#10b981', 'bg' => '#ecfdf5'],
                                        ['key' => 'reports', 'icon' => 'fa-chart-bar', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
                                        ['key' => 'users', 'icon' => 'fa-users', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
                                        ['key' => 'settings', 'icon' => 'fa-cog', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
                                        ['key' => 'logout', 'icon' => 'fa-sign-out-alt', 'color' => '#ef4444', 'bg' => '#fef2f2']
                                    ];
                                    
                                    foreach ($menuItems as $item):
                                        $nameKey = 'menu_' . $item['key'];
                                        $visibleKey = 'menu_' . $item['key'] . '_visible';
                                        $urlKey = 'menu_' . $item['key'] . '_url';
                                        $name = $settings[$nameKey] ?? '';
                                        $visible = ($settings[$visibleKey] ?? '1') == '1';
                                        $url = $settings[$urlKey] ?? '';
                                    ?>
                                    <div class="menu-item-row <?= !$visible ? 'hidden-menu' : '' ?>" data-menu="<?= $item['key'] ?>">
                                        <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                                        <div class="menu-item-icon" style="background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;">
                                            <i class="fas <?= $item['icon'] ?>"></i>
                                        </div>
                                        <div class="menu-item-details">
                                            <div class="menu-item-name">
                                                <input type="text" name="menu_<?= $item['key'] ?>" value="<?= htmlspecialchars($name) ?>" placeholder="ชื่อเมนู">
                                            </div>
                                            <div class="menu-item-url-group">
                                                <label><i class="fas fa-link"></i> URL</label>
                                                <input type="text" name="menu_<?= $item['key'] ?>_url" value="<?= htmlspecialchars($url) ?>" placeholder="<?= $item['key'] ?>.php">
                                            </div>
                                        </div>
                                        <div class="menu-item-actions">
                                            <label class="switch" style="margin:0;">
                                                <input type="checkbox" name="menu_<?= $item['key'] ?>_visible" value="1" <?= $visible ? 'checked' : '' ?> onchange="toggleMenuRow(this)">
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="action-bar" id="saveButtons">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกการตั้งค่า</button>
                        <!-- <a href="<?= htmlspecialchars($current_url) ?>dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> กลับ</a> -->
                        <button type="button" class="btn btn-ghost" id="resetBtn"><i class="fas fa-undo"></i> คืนค่าเริ่มต้น</button>
                    </div>
                    <div class="last-updated" id="lastSavedInfo"><i class="fas fa-history"></i> อัปเดตล่าสุด: <?= date('d/m/Y H:i') ?></div>
                </form>

                <!-- TAB: ระบบ -->
                <div class="tab-content" id="tab-system">
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-icon-wrapper" style="background:#eef2ff;color:#6366f1;"><i class="fas fa-users"></i></div><div class="stat-value"><?= number_format($stats['users']) ?></div><div class="stat-label">ผู้ใช้</div></div>
                        <div class="stat-card"><div class="stat-icon-wrapper" style="background:#fffbeb;color:#f59e0b;"><i class="fas fa-crown"></i></div><div class="stat-value"><?= number_format($stats['admin_count']) ?></div><div class="stat-label">แอดมิน</div></div>
                        <div class="stat-card"><div class="stat-icon-wrapper" style="background:#ecfdf5;color:#10b981;"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-value"><?= number_format($stats['risks']) ?></div><div class="stat-label">ความเสี่ยง</div></div>
                        <div class="stat-card"><div class="stat-icon-wrapper" style="background:#f5f3ff;color:#8b5cf6;"><i class="fas fa-file-alt"></i></div><div class="stat-value"><?= number_format($stats['reports']) ?></div><div class="stat-label">รายงาน</div></div>
                    </div>
                    <div class="card"><div class="card-header"><div class="icon-box emerald"><i class="fas fa-file-import"></i></div><div class="card-title">นำเข้าข้อมูลทดสอบ</div></div><div class="card-body"><form method="POST" id="importForm"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="import_sample_data"><button type="button" class="btn btn-primary" onclick="confirmImport()"><i class="fas fa-download"></i> นำเข้าเลย</button></form></div></div>
                    <div class="danger-zone">
                        <div style="font-size:1.1rem;font-weight:700;color:#991b1b;margin-bottom:1rem;"><i class="fas fa-radiation-alt"></i> โซนอันตราย</div>
                        <form method="POST" id="clearDataForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="clear_data"><input type="hidden" name="clear_type" id="clearType">
                            <div class="danger-grid">
                                <button type="button" class="danger-btn warning" onclick="confirmClear('risks')"><i class="fas fa-clipboard-list"></i> ล้างความเสี่ยง</button>
                                <button type="button" class="danger-btn warning" onclick="confirmClear('reports')"><i class="fas fa-file-alt"></i> ล้างรายงาน</button>
                                <button type="button" class="danger-btn info" onclick="confirmClear('all_except_users')"><i class="fas fa-broom"></i> ล้างทุกตาราง (ยกเว้นผู้ใช้)</button>
                                <button type="button" class="danger-btn" onclick="confirmClear('users_except_admin')"><i class="fas fa-users-slash"></i> ล้างผู้ใช้ (ยกเว้น Admin)</button>
                                <!-- <button type="button" class="danger-btn" onclick="confirmClear('all')" style="grid-column:span 2;"><i class="fas fa-skull"></i> ล้างทั้งหมด (เหลือ Admin)</button> -->
                            </div>
                        </form>
                    </div>
                </div>

                <div class="tip-box">
                    <div class="tip-icon"><i class="fas fa-lightbulb"></i></div>
                    <div class="tip-content">
                        <h4>เคล็ดลับ</h4>
                        <p>• แก้ไขชื่อเมนูและ URL ได้ตามต้องการ<br>• URL จะใช้ใน Sidebar เป็นลิงก์ไปยังหน้าต่างๆ<br>• ใช้ Switch เปิด/ปิดการแสดงเมนู</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const target = document.getElementById('tab-' + this.dataset.tab);
                if (target) target.classList.add('active');
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

        const urlInput = document.querySelector('[name="site_url"]');
        if (urlInput) urlInput.addEventListener('input', function() { document.getElementById('displayUrl').textContent = this.value; });

        function copyUrl() {
            const url = document.getElementById('displayUrl').textContent;
            navigator.clipboard.writeText(url).then(() => Swal.fire({ icon: 'success', title: 'คัดลอกแล้ว!', timer: 1500, showConfirmButton: false }));
        }

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                if (input.files[0].size > 2*1024*1024) { Swal.fire({ icon: 'warning', title: 'ไฟล์ใหญ่เกินไป', text: 'สูงสุด 2MB' }); input.value = ''; return; }
                const r = new FileReader();
                r.onload = e => document.getElementById('logoPreview').src = e.target.result;
                r.readAsDataURL(input.files[0]);
            }
        }

        function toggleMenuRow(checkbox) {
            const row = checkbox.closest('.menu-item-row');
            if (checkbox.checked) row.classList.remove('hidden-menu');
            else row.classList.add('hidden-menu');
        }

        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            if (document.getElementById('formAction').value !== 'save_settings') return;
            e.preventDefault();
            Swal.fire({ title: 'บันทึกการตั้งค่า?', text: 'การตั้งค่าจะมีผลทันที', icon: 'question', showCancelButton: true, confirmButtonColor: '#6366f1', confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก' })
            .then(r => { if (r.isConfirmed) { Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); this.submit(); } });
        });

        document.getElementById('resetBtn').addEventListener('click', () => {
            Swal.fire({ title: 'คืนค่าเริ่มต้น?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก' })
            .then(r => {
                if (r.isConfirmed) {
                    document.querySelector('[name="site_name"]').value = 'Risk Management';
                    document.querySelector('[name="site_url"]').value = 'http://localhost/risk_management/';
                    document.getElementById('displayUrl').textContent = 'http://localhost/risk_management/';
                    document.querySelectorAll('.menu-item-row input[type="checkbox"]').forEach(cb => { cb.checked = true; cb.closest('.menu-item-row').classList.remove('hidden-menu'); });
                    const defaults = {
                        menu_dashboard: 'ภาพรวมระบบ', menu_dashboard_url: 'dashboard.php',
                        menu_risks: 'รายการความเสี่ยง', menu_risks_url: 'risks.php',
                        menu_risk_form: 'เพิ่มความเสี่ยง', menu_risk_form_url: 'risk_form.php',
                        menu_reports: 'รายงาน', menu_reports_url: 'reports.php',
                        menu_users: 'จัดการผู้ใช้', menu_users_url: 'users.php',
                        menu_settings: 'ตั้งค่าระบบ', menu_settings_url: 'settings.php',
                        menu_logout: 'ออกจากระบบ', menu_logout_url: 'logout.php'
                    };
                    Object.entries(defaults).forEach(([name, value]) => { const el = document.querySelector(`[name="${name}"]`); if (el) el.value = value; });
                    Swal.fire({ icon: 'success', title: 'รีเซ็ตแล้ว', timer: 2000 });
                }
            });
        });

        function confirmClear(type) {
            const c = { risks: ['ล้างความเสี่ยง', false], reports: ['ล้างรายงาน', false], all_except_users: ['ล้างทุกตาราง (ยกเว้นผู้ใช้)', false], users_except_admin: ['ล้างผู้ใช้', true], all: ['ล้างทั้งหมด', true] };
            Swal.fire({ title: c[type][0]+'?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก' })
            .then(r => { if (r.isConfirmed) { if (c[type][1]) { Swal.fire({ title: 'แน่ใจสุดท้าย?', icon: 'error', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'ล้าง!' }).then(r2 => { if (r2.isConfirmed) submitClear(type); }); } else submitClear(type); } });
        }
        function submitClear(type) { document.getElementById('clearType').value = type; Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); document.getElementById('clearDataForm').submit(); }
        function confirmImport() { Swal.fire({ title: 'นำเข้า?', icon: 'question', showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: 'นำเข้า', cancelButtonText: 'ยกเลิก' }).then(r => { if (r.isConfirmed) { Swal.fire({ title: 'กำลังนำเข้า...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); document.getElementById('importForm').submit(); } }); }

        <?php if ($success_message): ?>Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= htmlspecialchars($success_message) ?>', timer: 3000 });<?php endif; ?>
        <?php if ($error_message): ?>Swal.fire({ icon: 'error', title: 'ผิดพลาด!', text: '<?= htmlspecialchars($error_message) ?>' });<?php endif; ?>
    </script>
</body>
</html>