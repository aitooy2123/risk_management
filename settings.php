<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

// ===== สร้างโฟลเดอร์ backupSQL =====
$backup_dir = __DIR__ . '/backupSQL';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    file_put_contents($backup_dir . '/.htaccess', 'Deny from all');
    file_put_contents($backup_dir . '/index.html', '');
}

// ===== ดึงค่าการตั้งค่า =====
$settings = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {}

$defaults = [
    'site_name' => 'Risk Management',
    'site_description' => 'ระบบบริหารความเสี่ยง',
    'site_organization' => 'ศูนย์อนามัยที่ 8 อุดรธานี',
    'site_logo' => 'assets/default-logo.png',
    'items_per_page' => '10',
    'session_timeout' => '30',
    'site_url' => 'http://localhost/risk_management/',
    'menu_order' => 'dashboard,risks,risk_form,reports,users,settings,logout',
    'menu_dashboard' => 'ภาพรวมระบบ', 'menu_dashboard_visible' => '1', 'menu_dashboard_url' => 'dashboard.php',
    'menu_risks' => 'รายการความเสี่ยง', 'menu_risks_visible' => '1', 'menu_risks_url' => 'risks.php',
    'menu_risk_form' => 'เพิ่มความเสี่ยง', 'menu_risk_form_visible' => '1', 'menu_risk_form_url' => 'risk_form.php',
    'menu_reports' => 'รายงาน', 'menu_reports_visible' => '1', 'menu_reports_url' => 'reports.php',
    'menu_users' => 'จัดการผู้ใช้', 'menu_users_visible' => '1', 'menu_users_url' => 'users.php',
    'menu_settings' => 'ตั้งค่าระบบ', 'menu_settings_visible' => '1', 'menu_settings_url' => 'settings.php',
    'menu_logout' => 'ออกจากระบบ', 'menu_logout_visible' => '1', 'menu_logout_url' => 'logout.php'
];
foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) $settings[$key] = $value;
}

// ===== จัดการ Action (เหมือนเดิม) =====
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    $session_csrf = $_SESSION['csrf_token'] ?? '';
    
    if ($post_csrf !== $session_csrf) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'backup_database') {
            try {
                $backup_file = backupDatabase($pdo, $backup_dir);
                $success_message = '✅ สำรองฐานข้อมูลสำเร็จ! ' . basename($backup_file);
            } catch (Exception $e) {
                $error_message = '❌ ' . $e->getMessage();
            }
        }
        elseif ($action === 'restore_upload') {
            if (!isset($_FILES['restore_file']) || $_FILES['restore_file']['error'] !== UPLOAD_ERR_OK) {
                $error_message = '❌ กรุณาเลือกไฟล์ SQL';
            } else {
                $file = $_FILES['restore_file'];
                if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
                    $error_message = '❌ รองรับเฉพาะไฟล์ .sql';
                } elseif ($file['size'] > 50 * 1024 * 1024) {
                    $error_message = '❌ ไฟล์ใหญ่เกินไป (สูงสุด 50MB)';
                } else {
                    try {
                        $sql = file_get_contents($file['tmp_name']);
                        $count = restoreDatabase($pdo, $sql);
                        $success_message = "✅ กู้คืนสำเร็จ! ($count คำสั่ง)";
                    } catch (Exception $e) {
                        $error_message = '❌ ' . $e->getMessage();
                    }
                }
            }
        }
        elseif ($action === 'restore_backup') {
            $filename = basename($_POST['backup_file'] ?? '');
            $filepath = $backup_dir . '/' . $filename;
            if (!file_exists($filepath)) {
                $error_message = '❌ ไม่พบไฟล์';
            } else {
                try {
                    $sql = file_get_contents($filepath);
                    $count = restoreDatabase($pdo, $sql);
                    $success_message = "✅ กู้คืนจาก $filename สำเร็จ! ($count คำสั่ง)";
                } catch (Exception $e) {
                    $error_message = '❌ ' . $e->getMessage();
                }
            }
        }
        elseif ($action === 'delete_backup') {
            $filename = basename($_POST['backup_file'] ?? '');
            $filepath = $backup_dir . '/' . $filename;
            if (file_exists($filepath) && unlink($filepath)) {
                $success_message = '✅ ลบไฟล์สำเร็จ';
            } else {
                $error_message = '❌ ไม่สามารถลบไฟล์ได้';
            }
        }
        elseif ($action === 'download_backup') {
            $filename = basename($_POST['backup_file'] ?? '');
            $filepath = $backup_dir . '/' . $filename;
            if (file_exists($filepath)) {
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit;
            }
        }
        elseif ($action === 'clear_data') {
            $clear_type = $_POST['clear_type'] ?? '';
            try {
                $pdo->beginTransaction();
                switch ($clear_type) {
                    case 'risks':
                        $pdo->exec("DELETE FROM risk_reports"); $pdo->exec("DELETE FROM risks");
                        $success_message = '✅ ล้างความเสี่ยงทั้งหมด'; break;
                    case 'reports':
                        $pdo->exec("DELETE FROM risk_reports");
                        $success_message = '✅ ล้างรายงานทั้งหมด'; break;
                    case 'all_except_users':
                        $pdo->exec("DELETE FROM risk_reports"); $pdo->exec("DELETE FROM risks");
                        $pdo->exec("DELETE FROM user_tokens"); $pdo->exec("DELETE FROM login_attempts");
                        $pdo->exec("DELETE FROM system_settings");
                        $success_message = '✅ ล้างทุกตาราง (ยกเว้นผู้ใช้)'; break;
                    case 'users_except_admin':
                        $adminId = $_SESSION['user_id'];
                        $pdo->exec("DELETE FROM risk_reports WHERE risk_id IN (SELECT id FROM risks WHERE user_id != $adminId)");
                        $pdo->exec("DELETE FROM risks WHERE user_id != $adminId");
                        $pdo->exec("DELETE FROM user_tokens WHERE user_id != $adminId");
                        $pdo->prepare("DELETE FROM users WHERE id != ?")->execute([$adminId]);
                        $success_message = '✅ ล้างผู้ใช้ทั้งหมด (ยกเว้น Admin)'; break;
                    default: $error_message = '❌ กรุณาเลือกประเภทข้อมูล';
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = '❌ ' . $e->getMessage();
            }
        }
        elseif ($action === 'import_sample') {
            $sql_file = __DIR__ . '/install.sql';
            if (!file_exists($sql_file)) {
                $error_message = '❌ ไม่พบไฟล์ install.sql';
            } else {
                try {
                    $sql = file_get_contents($sql_file);
                    $queries = array_filter(array_map('trim', explode(';', $sql)));
                    $imported = 0;
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            try { $pdo->exec($query); $imported++; } 
                            catch (Exception $e) { continue; }
                        }
                    }
                    $success_message = "✅ นำเข้าสำเร็จ ($imported คำสั่ง)";
                } catch (Exception $e) {
                    $error_message = '❌ ' . $e->getMessage();
                }
            }
        }
        elseif ($action === 'save_settings') {
            try {
                $pdo->beginTransaction();
                $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");

                $logo_path = $_POST['site_logo_url'] ?? ($settings['site_logo'] ?? '');
                if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'assets/uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['site_logo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                        $new_file = $upload_dir . 'logo_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $new_file)) {
                            $logo_path = $new_file;
                        }
                    }
                }

                $menu_order = $_POST['menu_order'] ?? 'dashboard,risks,risk_form,reports,users,settings,logout';
                $save_data = [
                    'site_name' => $_POST['site_name'] ?? 'Risk Management',
                    'site_description' => $_POST['site_description'] ?? '',
                    'site_organization' => $_POST['site_organization'] ?? '',
                    'site_logo' => $logo_path,
                    'site_url' => $_POST['site_url'] ?? '',
                    'items_per_page' => $_POST['items_per_page'] ?? '10',
                    'session_timeout' => $_POST['session_timeout'] ?? '30',
                    'menu_order' => $menu_order,
                ];
                $menu_keys = ['dashboard', 'risks', 'risk_form', 'reports', 'users', 'settings', 'logout'];
                foreach ($menu_keys as $key) {
                    $save_data['menu_' . $key] = $_POST['menu_' . $key] ?? '';
                    $save_data['menu_' . $key . '_visible'] = isset($_POST['menu_' . $key . '_visible']) ? '1' : '0';
                    $save_data['menu_' . $key . '_url'] = $_POST['menu_' . $key . '_url'] ?? '';
                }

                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                foreach ($save_data as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                $pdo->commit();
                $success_message = '✅ บันทึกการตั้งค่าสำเร็จ';
                $settings = array_merge($settings, $save_data);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = '❌ ' . $e->getMessage();
            }
        }
    }
}

// ===== ฟังก์ชันสำรอง/กู้คืน =====
function backupDatabase($pdo, $backup_dir) {
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . '/' . $filename;
    $output = "-- Risk Management Backup\n-- " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $output .= $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $output .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, array_values($row));
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            $output .= implode(",\n", $values) . ";\n\n";
        }
    }
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($filepath, $output);
    
    foreach (glob($backup_dir . '/backup_*.sql') as $old) {
        if (filemtime($old) < time() - 30*24*60*60) unlink($old);
    }
    return $filepath;
}

function restoreDatabase($pdo, $sql_content) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $lines = explode("\n", $sql_content);
    $query = ''; $queries = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) continue;
        if (strpos($line, '/*') !== false) continue;
        $query .= $line . "\n";
        if (substr(rtrim($line), -1) === ';') {
            $queries[] = trim($query);
            $query = '';
        }
    }
    if (!empty(trim($query))) $queries[] = trim($query);
    
    $count = 0;
    foreach ($queries as $q) {
        if (empty($q)) continue;
        try { $pdo->exec($q); $count++; }
        catch (Exception $e) { error_log("SQL Error: " . $e->getMessage()); }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    return $count;
}

// ===== ดึงรายการ backup =====
$backup_files = [];
foreach (glob($backup_dir . '/backup_*.sql') as $file) {
    $backup_files[] = ['name' => basename($file), 'size' => filesize($file), 'date' => filemtime($file)];
}
usort($backup_files, function($a, $b) { return $b['date'] - $a['date']; });

// ===== สถิติ =====
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'risks' => $pdo->query("SELECT COUNT(*) FROM risks")->fetchColumn(),
    'reports' => 0,
    'admin_count' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'backup_count' => count($backup_files)
];
try { $stats['reports'] = $pdo->query("SELECT COUNT(*) FROM risk_reports")->fetchColumn(); } catch (Exception $e) {}

function formatSizeDisplay($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

$csrf_token = generateCsrfToken();
$page_title = 'ตั้งค่าระบบ';
$menu_order = explode(',', $settings['menu_order'] ?? 'dashboard,risks,risk_form,reports,users,settings,logout');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> • Risk Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 20px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        /* ===== HEADER ===== */
        .header {
            position: relative;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 30%, #312e81 60%, #4f46e5 100%);
            border-radius: 2rem;
            padding: 2.5rem 3rem;
            margin-bottom: 2rem;
            color: white;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.4);
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.3), transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.2), transparent 70%);
            border-radius: 50%;
            animation: float 10s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -20px) scale(1.1); }
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .header-title-group {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        
        .header-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s;
        }
        
        .header-icon:hover {
            transform: rotate(15deg) scale(1.1);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .header p {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        
        .header-badge {
            padding: 0.5rem 1.25rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 3rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .header-badge .dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ===== TABS ===== */
        .tabs-wrapper {
            background: white;
            padding: 0.5rem;
            border-radius: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            position: sticky;
            top: 1rem;
            z-index: 20;
            backdrop-filter: blur(10px);
        }
        
        .tabs-nav {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
        }
        
        .tab-btn {
            padding: 0.875rem 1.5rem;
            border: none;
            background: transparent;
            border-radius: 1rem;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            position: relative;
            overflow: hidden;
        }
        
        .tab-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tab-btn:hover {
            color: var(--text);
            background: #f8fafc;
            transform: translateY(-2px);
        }
        
        .tab-btn.active {
            color: white;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }
        
        .tab-btn.active::before {
            opacity: 1;
        }
        
        .tab-btn span {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-btn.danger.active::before {
            background: linear-gradient(135deg, #dc2626, #ef4444);
        }
        
        .tab-badge {
            background: rgba(255, 255, 255, 0.3);
            padding: 0.15rem 0.6rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* ===== CONTENT ===== */
        .tab-content {
            display: none;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .card:hover {
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f8fafc;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .card-icon.purple { background: linear-gradient(135deg, #eef2ff, #e0e7ff); color: #6366f1; }
        .card-icon.cyan { background: linear-gradient(135deg, #ecfeff, #cffafe); color: #06b6d4; }
        .card-icon.blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #3b82f6; }
        .card-icon.green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #10b981; }
        .card-icon.amber { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #f59e0b; }
        .card-icon.rose { background: linear-gradient(135deg, #fff1f2, #ffe4e6); color: #e11d48; }
        
        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .card-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.125rem;
        }
        
        .card-body {
            padding: 2rem;
        }

        /* ===== FORM ELEMENTS ===== */
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
        
        .form-group.full {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .form-input, select, textarea {
            padding: 0.875rem 1.125rem;
            border: 2px solid var(--border);
            border-radius: 0.875rem;
            font-size: 0.9rem;
            font-family: 'Sarabun', sans-serif;
            background: #f8fafc;
            width: 100%;
            transition: all 0.3s;
            outline: none;
            color: var(--text);
        }
        
        .form-input:hover, select:hover {
            border-color: #cbd5e1;
            background: white;
        }
        
        .form-input:focus, select:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .form-input::placeholder {
            color: #94a3b8;
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 0.875rem 2rem;
            border-radius: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s;
        }
        
        .btn:hover::after {
            transform: translateX(100%);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
        }
        
        .btn-outline {
            background: white;
            color: var(--text);
            border: 2px solid var(--border);
        }
        .btn-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.5rem 1.25rem;
            font-size: 0.8rem;
            border-radius: 0.625rem;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        .btn-icon.green { background: #ecfdf5; color: #10b981; }
        .btn-icon.green:hover { background: #10b981; color: white; }
        .btn-icon.blue { background: #eff6ff; color: #3b82f6; }
        .btn-icon.blue:hover { background: #3b82f6; color: white; }
        .btn-icon.red { background: #fef2f2; color: #ef4444; }
        .btn-icon.red:hover { background: #ef4444; color: white; }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            font-weight: 500;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        /* ===== SWITCH ===== */
        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 30px;
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
            transition: 0.3s;
            border-radius: 30px;
        }
        
        .switch-slider:before {
            content: "";
            position: absolute;
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        input:checked + .switch-slider {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        
        input:checked + .switch-slider:before {
            transform: translateX(22px);
        }

        /* ===== MENU ITEMS ===== */
        .menu-item-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #f8fafc;
            border-radius: 1rem;
            border: 2px solid transparent;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
            cursor: grab;
        }
        
        .menu-item-row:hover {
            background: white;
            border-color: #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transform: translateX(5px);
        }
        
        .menu-item-row:active {
            cursor: grabbing;
            box-shadow: 0 8px 25px rgba(99,102,241,0.2);
            border-color: var(--primary);
        }
        
        .menu-item-row.hidden-menu {
            opacity: 0.4;
            background: #f1f5f9;
        }
        
        .menu-item-icon {
            width: 44px;
            height: 44px;
            border-radius: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .menu-item-details {
            flex: 1;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .menu-item-name {
            flex: 1;
        }
        
        .menu-item-name input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.625rem;
            font-size: 0.875rem;
            font-family: 'Sarabun', sans-serif;
            background: white;
            outline: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .menu-item-name input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        
        .menu-item-url {
            flex: 1.5;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            border-radius: 0.625rem;
            padding: 0.625rem 0.875rem;
            border: 2px solid #e2e8f0;
        }
        
        .menu-item-url input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.8rem;
            font-family: 'Courier New', monospace;
            color: #2563eb;
            outline: none;
        }
        
        .menu-item-url:focus-within {
            border-color: var(--primary);
            background: white;
        }
        
        .drag-handle {
            cursor: grab;
            color: #94a3b8;
            padding: 0.3rem;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .drag-handle:hover {
            color: var(--primary);
            transform: scale(1.2);
        }

        /* ===== DANGER ZONE ===== */
        .danger-zone {
            border: 2px dashed #fca5a5;
            border-radius: 1.5rem;
            padding: 2rem;
            background: linear-gradient(135deg, #fef2f2, #fff5f5);
            margin-bottom: 1.5rem;
        }
        
        .danger-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.875rem;
        }
        
        .danger-btn {
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 2px solid #fca5a5;
            background: white;
            color: #dc2626;
            transition: all 0.3s;
            width: 100%;
            justify-content: center;
        }
        
        .danger-btn:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.4);
        }
        
        .danger-btn.warning {
            border-color: #fcd34d;
            color: #d97706;
        }
        
        .danger-btn.warning:hover {
            background: #d97706;
            color: white;
            border-color: #d97706;
        }
        
        .danger-btn.info {
            border-color: #93c5fd;
            color: #2563eb;
        }
        
        .danger-btn.info:hover {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* ===== TABLE ===== */
        .backup-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .backup-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .backup-table td {
            padding: 1rem 1.5rem;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .backup-table tbody tr {
            transition: all 0.3s;
        }
        
        .backup-table tbody tr:hover {
            background: #f8fafc;
        }

        /* ===== UPLOAD ZONE ===== */
        .upload-zone {
            border: 2px dashed #a7f3d0;
            border-radius: 1.25rem;
            padding: 2.5rem;
            text-align: center;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-zone:hover {
            border-color: #10b981;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            transform: scale(1.01);
        }
        
        .upload-zone.has-file {
            border-style: solid;
            border-color: #10b981;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        }

        /* ===== LOGO UPLOAD ===== */
        .logo-upload {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .logo-preview {
            width: 100px;
            height: 100px;
            border-radius: 1.25rem;
            object-fit: contain;
            border: 3px solid #e2e8f0;
            background: white;
            padding: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: all 0.3s;
        }
        
        .logo-preview:hover {
            transform: scale(1.05);
            border-color: var(--primary);
        }
        
        .upload-btn-label {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            color: var(--primary);
            border: 2px dashed #a5b4fc;
            border-radius: 0.875rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .upload-btn-label:hover {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border-style: solid;
            transform: translateY(-2px);
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 1.25rem 1.75rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.875rem;
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            color: #065f46;
            border: 2px solid #a7f3d0;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            color: #991b1b;
            border: 2px solid #fecaca;
        }

        /* ===== ACTION BAR ===== */
        .action-bar {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f1f5f9;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: block;
            opacity: 0.5;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .tabs-nav {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1.5rem;
                border-radius: 1.5rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .danger-grid {
                grid-template-columns: 1fr;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .menu-item-details {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .logo-upload {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-body {
                padding: 1.25rem;
            }
            
            .tabs-nav {
                grid-template-columns: 1fr 1fr;
                gap: 0.25rem;
            }
            
            .tab-btn {
                padding: 0.75rem 1rem;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .tabs-nav {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        <div class="flex-1 overflow-y-auto">
            <div class="main-container">
                
                <!-- HEADER -->
                <div class="header">
                    <div class="header-content">
                        <div class="header-title-group">
                            <div class="header-icon">⚙️</div>
                            <div>
                                <h1><?= htmlspecialchars($page_title) ?></h1>
                                <p>จัดการการตั้งค่า เมนู สำรองข้อมูล และบำรุงรักษาระบบ</p>
                            </div>
                        </div>
                        <div class="header-badge">
                            <span class="dot"></span>
                            Admin Mode
                        </div>
                    </div>
                </div>

                <!-- ALERTS -->
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size:1.25rem;"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size:1.25rem;"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>

                <!-- TABS -->
                <div class="tabs-wrapper">
                    <div class="tabs-nav" id="tabNavigation">
                        <button type="button" class="tab-btn active" onclick="switchTab('general')" data-tab="general">
                            <span><i class="fas fa-sliders-h"></i> ทั่วไป</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('menu')" data-tab="menu">
                            <span><i class="fas fa-bars"></i> เมนู</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('backup')" data-tab="backup">
                            <span><i class="fas fa-database"></i> สำรองข้อมูล <span class="tab-badge"><?= count($backup_files) ?></span></span>
                        </button>
                        <button type="button" class="tab-btn danger" onclick="switchTab('system')" data-tab="system">
                            <span><i class="fas fa-tools"></i> ระบบ</span>
                        </button>
                    </div>
                </div>

                <!-- ===== FORM: ทั่วไป + เมนู ===== -->
                <form method="POST" id="settingsForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="menu_order" id="menuOrderInput" value="<?= htmlspecialchars(implode(',', $menu_order)) ?>">

                    <!-- TAB: ทั่วไป -->
                    <div class="tab-content active" id="tab-general">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon purple"><i class="fas fa-info-circle"></i></div>
                                <div>
                                    <div class="card-title">ข้อมูลระบบ</div>
                                    <div class="card-subtitle">กำหนดค่าพื้นฐานของระบบ</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label class="form-label"><i class="fas fa-heading"></i> ชื่อระบบ</label>
                                        <input type="text" name="site_name" class="form-input" value="<?= htmlspecialchars($settings['site_name']) ?>" placeholder="เช่น Risk Management" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-align-left"></i> คำอธิบาย</label>
                                        <input type="text" name="site_description" class="form-input" value="<?= htmlspecialchars($settings['site_description']) ?>" placeholder="คำอธิบายสั้นๆ">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-building"></i> หน่วยงาน</label>
                                        <input type="text" name="site_organization" class="form-input" value="<?= htmlspecialchars($settings['site_organization']) ?>" placeholder="ชื่อหน่วยงาน">
                                    </div>
                                    <div class="form-group full">
                                        <label class="form-label"><i class="fas fa-link"></i> URL เว็บไซต์</label>
                                        <input type="text" name="site_url" class="form-input" value="<?= htmlspecialchars($settings['site_url']) ?>" placeholder="https://example.com/">
                                    </div>
                                    <div class="form-group full">
                                        <label class="form-label"><i class="fas fa-image"></i> โลโก้</label>
                                        <div class="logo-upload">
                                            <img src="<?= htmlspecialchars($settings['site_logo']) ?>" alt="Logo" class="logo-preview" id="logoPreview" onerror="this.src='assets/default-logo.png'">
                                            <div>
                                                <label class="upload-btn-label">
                                                    <i class="fas fa-cloud-upload-alt"></i> เลือกไฟล์
                                                    <input type="file" name="site_logo_file" accept="image/*" style="display:none" onchange="previewLogo(this)">
                                                </label>
                                                <p style="font-size:0.75rem;color:#94a3b8;margin-top:0.5rem;">PNG, JPG, SVG (สูงสุด 2MB)</p>
                                            </div>
                                        </div>
                                        <input type="text" name="site_logo_url" class="form-input" value="<?= htmlspecialchars($settings['site_logo']) ?>" placeholder="หรือวาง URL โลโก้" style="margin-top:1rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon cyan"><i class="fas fa-cog"></i></div>
                                <div>
                                    <div class="card-title">ค่าพื้นฐาน</div>
                                    <div class="card-subtitle">การแสดงผลและความปลอดภัย</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-list-ol"></i> รายการต่อหน้า</label>
                                        <select name="items_per_page" class="form-input">
                                            <option value="5" <?= ($settings['items_per_page'] ?? '10') == '5' ? 'selected' : '' ?>>5 รายการ</option>
                                            <option value="10" <?= ($settings['items_per_page'] ?? '10') == '10' ? 'selected' : '' ?>>10 รายการ</option>
                                            <option value="25" <?= ($settings['items_per_page'] ?? '10') == '25' ? 'selected' : '' ?>>25 รายการ</option>
                                            <option value="50" <?= ($settings['items_per_page'] ?? '10') == '50' ? 'selected' : '' ?>>50 รายการ</option>
                                            <option value="100" <?= ($settings['items_per_page'] ?? '10') == '100' ? 'selected' : '' ?>>100 รายการ</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-clock"></i> หมดเวลาเซสชัน (นาที)</label>
                                        <input type="number" name="session_timeout" class="form-input" value="<?= htmlspecialchars($settings['session_timeout'] ?? '30') ?>" min="5" max="240">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-bar">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกการตั้งค่า
                            </button>
                            <button type="reset" class="btn btn-outline">
                                <i class="fas fa-undo"></i> รีเซ็ต
                            </button>
                        </div>
                    </div>

                    <!-- TAB: เมนู -->
                    <div class="tab-content" id="tab-menu">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon blue"><i class="fas fa-edit"></i></div>
                                <div>
                                    <div class="card-title">จัดการเมนู Sidebar</div>
                                    <div class="card-subtitle">ลากเพื่อเรียงลำดับ • เปลี่ยนชื่อ • URL • แสดง/ซ่อน</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="menuItemsContainer">
                                    <?php
                                    $allMenuItems = [
                                        'dashboard' => ['icon' => 'fa-tachometer-alt', 'color' => '#6366f1', 'bg' => '#eef2ff'],
                                        'risks' => ['icon' => 'fa-clipboard-list', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
                                        'risk_form' => ['icon' => 'fa-plus-circle', 'color' => '#10b981', 'bg' => '#ecfdf5'],
                                        'reports' => ['icon' => 'fa-chart-bar', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
                                        'users' => ['icon' => 'fa-users', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
                                        'settings' => ['icon' => 'fa-cog', 'color' => '#ec4899', 'bg' => '#fdf2f8'],
                                        'logout' => ['icon' => 'fa-sign-out-alt', 'color' => '#ef4444', 'bg' => '#fef2f2']
                                    ];
                                    
                                    foreach ($menu_order as $key):
                                        if (!isset($allMenuItems[$key])) continue;
                                        $item = $allMenuItems[$key];
                                        $name = $settings['menu_' . $key] ?? '';
                                        $visible = ($settings['menu_' . $key . '_visible'] ?? '1') == '1';
                                        $url = $settings['menu_' . $key . '_url'] ?? '';
                                    ?>
                                    <div class="menu-item-row <?= !$visible ? 'hidden-menu' : '' ?>" data-menu="<?= $key ?>" draggable="true">
                                        <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                                        <div class="menu-item-icon" style="background:<?= $item['bg'] ?>;color:<?= $item['color'] ?>;">
                                            <i class="fas <?= $item['icon'] ?>"></i>
                                        </div>
                                        <div class="menu-item-details">
                                            <div class="menu-item-name">
                                                <input type="text" name="menu_<?= $key ?>" value="<?= htmlspecialchars($name) ?>" placeholder="ชื่อเมนู">
                                            </div>
                                            <div class="menu-item-url">
                                                <span style="color:#94a3b8;font-size:0.8rem;">🔗</span>
                                                <input type="text" name="menu_<?= $key ?>_url" value="<?= htmlspecialchars($url) ?>" placeholder="<?= $key ?>.php">
                                            </div>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="menu_<?= $key ?>_visible" value="1" <?= $visible ? 'checked' : '' ?> onchange="toggleMenuRow(this)">
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-bar">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกเมนู
                            </button>
                            <button type="reset" class="btn btn-outline">
                                <i class="fas fa-undo"></i> รีเซ็ต
                            </button>
                        </div>
                    </div>
                </form>

                <!-- TAB: สำรองข้อมูล -->
                <div class="tab-content" id="tab-backup">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon amber"><i class="fas fa-database"></i></div>
                            <div>
                                <div class="card-title">สำรองฐานข้อมูล</div>
                                <div class="card-subtitle">สร้างไฟล์สำรองข้อมูลทั้งหมด เก็บใน folder <code>backupSQL/</code></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="backupForm">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="backup_database">
                                <button type="button" class="btn btn-success" onclick="confirmBackup()">
                                    <i class="fas fa-save"></i> สร้าง Backup ใหม่
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green"><i class="fas fa-upload"></i></div>
                            <div>
                                <div class="card-title">กู้คืนฐานข้อมูล</div>
                                <div class="card-subtitle">อัปโหลดไฟล์ SQL เพื่อกู้คืนข้อมูล</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="background: #fef2f2; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #fecaca;">
                                <p style="color: #dc2626; font-size: 0.85rem;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>คำเตือน:</strong> การกู้คืนจะเขียนทับข้อมูลปัจจุบันทั้งหมด! กรุณาสำรองข้อมูลก่อน
                                </p>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="restoreUploadForm">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="restore_upload">
                                
                                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('restoreFileInput').click()">
                                    <input type="file" name="restore_file" id="restoreFileInput" accept=".sql" style="display:none" onchange="handleFileSelect(this)">
                                    <div id="uploadPlaceholder">
                                        <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:#10b981;"></i>
                                        <p style="margin-top:0.75rem;font-weight:600;font-size:1.1rem;">คลิกเพื่อเลือกไฟล์ SQL</p>
                                        <p style="font-size:0.8rem;color:#64748b;">หรือลากไฟล์มาวางที่นี่ (สูงสุด 50MB)</p>
                                    </div>
                                    <div id="uploadFileInfo" style="display:none;">
                                        <i class="fas fa-file-code" style="font-size:2.5rem;color:#10b981;"></i>
                                        <p style="margin-top:0.75rem;font-weight:600;font-size:1.1rem;" id="fileNameDisplay"></p>
                                        <p style="font-size:0.8rem;color:#64748b;" id="fileSizeDisplay"></p>
                                    </div>
                                </div>
                                
                                <div style="margin-top:1.25rem;">
                                    <button type="button" class="btn btn-warning" onclick="confirmRestoreUpload()">
                                        <i class="fas fa-undo"></i> กู้คืนจากไฟล์นี้
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon blue"><i class="fas fa-list"></i></div>
                            <div>
                                <div class="card-title">ไฟล์สำรองข้อมูล</div>
                                <div class="card-subtitle"><?= count($backup_files) ?> ไฟล์ • เรียงจากใหม่ไปเก่า</div>
                            </div>
                        </div>
                        <div class="card-body" style="overflow-x:auto;">
                            <?php if (empty($backup_files)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p style="font-size:1.1rem;font-weight:600;">ยังไม่มีไฟล์สำรองข้อมูล</p>
                                <p style="font-size:0.85rem;">คลิก "สร้าง Backup ใหม่" เพื่อเริ่มต้น</p>
                            </div>
                            <?php else: ?>
                            <table class="backup-table">
                                <thead>
                                    <tr>
                                        <th>ชื่อไฟล์</th>
                                        <th>ขนาด</th>
                                        <th>วันที่</th>
                                        <th style="text-align:center;width:160px;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $file): 
                                        $fid = md5($file['name']);
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-code" style="color:#6366f1;margin-right:0.5rem;"></i>
                                            <strong><?= htmlspecialchars($file['name']) ?></strong>
                                        </td>
                                        <td><span style="background:#f1f5f9;padding:0.25rem 0.75rem;border-radius:1rem;font-weight:600;"><?= formatSizeDisplay($file['size']) ?></span></td>
                                        <td><?= date('d/m/Y H:i', $file['date']) ?></td>
                                        <td style="text-align:center;">
                                            <div style="display:flex;gap:0.375rem;justify-content:center;">
                                                <form method="POST" id="restore_<?= $fid ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="restore_backup">
                                                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file['name']) ?>">
                                                    <button type="button" class="btn-icon green" title="กู้คืน" onclick="confirmRestoreBackup('<?= htmlspecialchars($file['name']) ?>', '<?= $fid ?>')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" id="download_<?= $fid ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="download_backup">
                                                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file['name']) ?>">
                                                    <button type="submit" class="btn-icon blue" title="ดาวน์โหลด">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" id="delete_<?= $fid ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="delete_backup">
                                                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file['name']) ?>">
                                                    <button type="button" class="btn-icon red" title="ลบ" onclick="confirmDeleteBackup('<?= htmlspecialchars($file['name']) ?>', '<?= $fid ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB: ระบบ -->
                <div class="tab-content" id="tab-system">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-value"><?= number_format($stats['users']) ?></div>
                            <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">👑</div>
                            <div class="stat-value"><?= number_format($stats['admin_count']) ?></div>
                            <div class="stat-label">แอดมิน</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-value"><?= number_format($stats['risks']) ?></div>
                            <div class="stat-label">ความเสี่ยง</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📊</div>
                            <div class="stat-value"><?= number_format($stats['reports']) ?></div>
                            <div class="stat-label">รายงาน</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">💾</div>
                            <div class="stat-value"><?= number_format($stats['backup_count']) ?></div>
                            <div class="stat-label">Backup</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green"><i class="fas fa-file-import"></i></div>
                            <div>
                                <div class="card-title">นำเข้าข้อมูลทดสอบ</div>
                                <div class="card-subtitle">เพิ่มข้อมูลตัวอย่างจาก install.sql</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="importForm">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="import_sample">
                                <button type="button" class="btn btn-success" onclick="confirmImport()">
                                    <i class="fas fa-download"></i> นำเข้าข้อมูลทดสอบ
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="danger-zone">
                        <div style="font-size:1.2rem;font-weight:700;color:#991b1b;margin-bottom:0.75rem;">
                            <i class="fas fa-radiation-alt"></i> โซนอันตราย
                        </div>
                        <p style="color:#dc2626;font-size:0.85rem;margin-bottom:1.5rem;font-weight:500;">
                            <i class="fas fa-exclamation-triangle"></i> การล้างข้อมูลไม่สามารถกู้คืนได้! กรุณาสำรองข้อมูลก่อนทุกครั้ง
                        </p>
                        <form method="POST" id="clearDataForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="clear_data">
                            <input type="hidden" name="clear_type" id="clearType">
                            <div class="danger-grid">
                                <button type="button" class="danger-btn warning" onclick="confirmClear('risks')">
                                    <i class="fas fa-clipboard-list"></i> ล้างความเสี่ยงทั้งหมด
                                </button>
                                <button type="button" class="danger-btn warning" onclick="confirmClear('reports')">
                                    <i class="fas fa-file-alt"></i> ล้างรายงานทั้งหมด
                                </button>
                                <button type="button" class="danger-btn info" onclick="confirmClear('all_except_users')">
                                    <i class="fas fa-broom"></i> ล้างทุกตาราง (ยกเว้นผู้ใช้)
                                </button>
                                <button type="button" class="danger-btn" onclick="confirmClear('users_except_admin')">
                                    <i class="fas fa-users-slash"></i> ล้างผู้ใช้ทั้งหมด (ยกเว้น Admin)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // ===== TAB SWITCHING =====
        function switchTab(tabName) {
            document.querySelectorAll('#tabNavigation .tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            const tabBtn = document.querySelector(`#tabNavigation .tab-btn[data-tab="${tabName}"]`);
            const tabContent = document.getElementById('tab-' + tabName);
            
            if (tabBtn) tabBtn.classList.add('active');
            if (tabContent) tabContent.classList.add('active');
            
            localStorage.setItem('settingsActiveTab', tabName);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('settingsActiveTab');
            if (savedTab) switchTab(savedTab);
        });

        // ===== LOGO PREVIEW =====
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                if (input.files[0].size > 2 * 1024 * 1024) {
                    Swal.fire({ icon: 'warning', title: 'ไฟล์ใหญ่เกินไป', text: 'สูงสุด 2MB' });
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => document.getElementById('logoPreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ===== MENU =====
        function toggleMenuRow(cb) {
            const row = cb.closest('.menu-item-row');
            row.classList.toggle('hidden-menu', !cb.checked);
        }

        // ===== DRAG & DROP =====
        const container = document.getElementById('menuItemsContainer');
        let draggedItem = null;

        container.addEventListener('dragstart', e => {
            draggedItem = e.target.closest('.menu-item-row');
            if (draggedItem) draggedItem.style.opacity = '0.5';
        });

        container.addEventListener('dragend', e => {
            if (draggedItem) draggedItem.style.opacity = '1';
            draggedItem = null;
            updateMenuOrder();
        });

        container.addEventListener('dragover', e => e.preventDefault());

        container.addEventListener('drop', e => {
            e.preventDefault();
            const target = e.target.closest('.menu-item-row');
            if (!target || target === draggedItem) return;
            
            const rect = target.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                container.insertBefore(draggedItem, target);
            } else {
                container.insertBefore(draggedItem, target.nextSibling);
            }
            updateMenuOrder();
        });

        function updateMenuOrder() {
            const order = Array.from(container.querySelectorAll('.menu-item-row')).map(item => item.dataset.menu).join(',');
            document.getElementById('menuOrderInput').value = order;
        }

        // ===== SAVE SETTINGS =====
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            updateMenuOrder();
            e.preventDefault();
            
            Swal.fire({
                title: 'บันทึกการตั้งค่า?',
                text: 'การเปลี่ยนแปลงทั้งหมดจะถูกบันทึก',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    this.submit();
                }
            });
        });

        // ===== FILE UPLOAD =====
        function handleFileSelect(input) {
            const placeholder = document.getElementById('uploadPlaceholder');
            const fileInfo = document.getElementById('uploadFileInfo');
            
            if (input.files?.[0]) {
                const file = input.files[0];
                if (!file.name.toLowerCase().endsWith('.sql')) {
                    Swal.fire({ icon: 'error', title: 'ไฟล์ไม่ถูกต้อง', text: 'กรุณาเลือกไฟล์ .sql' });
                    input.value = '';
                    return;
                }
                if (file.size > 50 * 1024 * 1024) {
                    Swal.fire({ icon: 'error', title: 'ไฟล์ใหญ่เกินไป', text: 'สูงสุด 50MB' });
                    input.value = '';
                    return;
                }
                placeholder.style.display = 'none';
                fileInfo.style.display = 'block';
                document.getElementById('fileNameDisplay').textContent = file.name;
                document.getElementById('fileSizeDisplay').textContent = formatSize(file.size);
                document.getElementById('uploadZone').classList.add('has-file');
            }
        }

        function formatSize(bytes) {
            if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' bytes';
        }

        // ===== BACKUP/RESTORE =====
        function confirmBackup() {
            Swal.fire({
                title: 'สร้าง Backup?',
                html: 'ระบบจะสร้างไฟล์สำรองข้อมูลทั้งหมด<br><small>ไฟล์จะถูกเก็บใน <code>backupSQL/</code></small>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: '<i class="fas fa-save"></i> สร้าง',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    Swal.fire({ title: 'กำลังสร้าง...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    document.getElementById('backupForm').submit();
                }
            });
        }

        function confirmRestoreUpload() {
            if (!document.getElementById('restoreFileInput').files?.length) {
                Swal.fire({ icon: 'warning', title: 'กรุณาเลือกไฟล์' });
                return;
            }
            Swal.fire({
                title: '⚠️ กู้คืนฐานข้อมูล?',
                html: '<p style="color:#dc2626;font-weight:bold;">ข้อมูลปัจจุบันทั้งหมดจะถูกเขียนทับ!</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: '<i class="fas fa-undo"></i> ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    Swal.fire({ title: 'กำลังกู้คืน...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    document.getElementById('restoreUploadForm').submit();
                }
            });
        }

        function confirmRestoreBackup(filename, fid) {
            Swal.fire({
                title: '⚠️ กู้คืนจาก ' + filename + '?',
                text: 'ข้อมูลปัจจุบันทั้งหมดจะถูกเขียนทับ!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    Swal.fire({ title: 'กำลังกู้คืน...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    document.getElementById('restore_' + fid).submit();
                }
            });
        }

        function confirmDeleteBackup(filename, fid) {
            Swal.fire({
                title: 'ลบไฟล์ Backup?',
                text: filename,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(r => { if (r.isConfirmed) document.getElementById('delete_' + fid).submit(); });
        }

        function confirmClear(type) {
            const m = {
                risks: 'ล้างความเสี่ยงทั้งหมด?',
                reports: 'ล้างรายงานทั้งหมด?',
                all_except_users: 'ล้างทุกตาราง (ยกเว้นผู้ใช้)?',
                users_except_admin: 'ล้างผู้ใช้ทั้งหมด (ยกเว้น Admin)?'
            };
            Swal.fire({
                title: m[type],
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    if (type === 'users_except_admin') {
                        Swal.fire({
                            title: 'แน่ใจที่สุด?',
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            confirmButtonText: 'ล้าง!'
                        }).then(r2 => { if (r2.isConfirmed) submitClear(type); });
                    } else submitClear(type);
                }
            });
        }

        function submitClear(type) {
            document.getElementById('clearType').value = type;
            Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            document.getElementById('clearDataForm').submit();
        }

        function confirmImport() {
            Swal.fire({
                title: 'นำเข้าข้อมูลทดสอบ?',
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

        // ===== ALERTS =====
        <?php if ($success_message): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= addslashes(strip_tags($success_message)) ?>', timer: 3000 });
        <?php endif; ?>
        <?php if ($error_message): ?>
        Swal.fire({ icon: 'error', title: 'ผิดพลาด!', text: '<?= addslashes(strip_tags($error_message)) ?>' });
        <?php endif; ?>
    </script>
</body>
</html>