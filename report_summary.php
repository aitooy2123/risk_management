<?php

/**
 * หน้าสรุปผลการรายงาน - ฟอร์มบันทึกข้อมูล
 * - User: สามารถแก้ไขสรุปผลได้ (เจ้าของรายการ)
 * - Admin: เห็นทั้งหมด และแก้ไขได้
 * - เข้าถึงได้ทุกรายการ (ไม่จำกัดเฉพาะ "ดำเนินการแล้ว")
 * - บันทึก: มาตรการแก้ไข, ผู้รับผิดชอบ, การติดตามผล, ผลที่คาดว่าจะได้รับ
 * - แนบไฟล์สรุปผลได้ + Lightbox
 * - ผู้รับผิดชอบแสดงชื่อผู้ใช้ปัจจุบันเป็นค่าเริ่มต้น
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือน
 * - เมื่อบันทึกสรุปผล ระบบจะอัปเดตสถานะเป็น "ดำเนินการแล้ว" โดยอัตโนมัติ
 * - Responsive Full Screen Design พร้อม Sidebar
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

// ใช้ Session Flash Message สำหรับ SweetAlert2
$flash = $_SESSION['flash_message'] ?? null;
if ($flash) {
    unset($_SESSION['flash_message']);
}

// รับ risk_id จาก URL
$risk_id = isset($_GET['risk_id']) ? (int)$_GET['risk_id'] : 0;

if (!$risk_id) {
    redirect('risks.php');
}

// ===== ดึงข้อมูลความเสี่ยง =====
$stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$risk_id]);
$risk = $stmt->fetch();

if (!$risk) {
    redirect('risks.php');
}

// ตรวจสอบสิทธิ์
if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) {
    redirect('risks.php');
}

// ===== ดึงรายงานที่มีอยู่ =====
$existingReport = null;
$stmt = $pdo->prepare("SELECT * FROM risk_reports WHERE risk_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$risk_id]);
$existingReport = $stmt->fetch();

// ===== สิทธิ์แก้ไข =====
$canEdit = isAdmin() || (isset($risk['user_id']) && $risk['user_id'] == $_SESSION['user_id']);

// ===== จัดการ POST =====
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        $error_message = 'คุณไม่มีสิทธิ์ในการบันทึกหรือแก้ไขข้อมูล';
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request (CSRF token ไม่ถูกต้อง)';
    } else {
        $corrective_action = trim($_POST['corrective_action'] ?? '');
        $responsible_person = trim($_POST['responsible_person'] ?? '');
        $follow_up = trim($_POST['follow_up'] ?? '');
        $expected_outcome = trim($_POST['expected_outcome'] ?? '');

        if (empty($corrective_action) && empty($responsible_person) && empty($follow_up) && empty($expected_outcome)) {
            $error_message = 'กรุณากรอกข้อมูลอย่างน้อย 1 ฟิลด์';
        } else {
            // อัปโหลดไฟล์
            $uploaded_file = '';
            if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/reports/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_extension = pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    $error_message = 'ประเภทไฟล์ไม่ถูกต้อง (รองรับ: PDF, Word, Excel, รูปภาพ)';
                } elseif ($_FILES['report_file']['size'] > 10 * 1024 * 1024) {
                    $error_message = 'ขนาดไฟล์ต้องไม่เกิน 10MB';
                } else {
                    $file_name = 'report_' . $risk_id . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['report_file']['tmp_name'], $file_path)) {
                        $uploaded_file = $file_path;
                    } else {
                        $error_message = 'ไม่สามารถอัปโหลดไฟล์ได้';
                    }
                }
            }

            if (empty($error_message)) {
                try {
                    if ($existingReport) {
                        $sql = "UPDATE risk_reports SET corrective_action = ?, responsible_person = ?, follow_up = ?, expected_outcome = ?";
                        $params = [$corrective_action, $responsible_person, $follow_up, $expected_outcome];
                        if ($uploaded_file) {
                            if ($existingReport['report_file'] && file_exists($existingReport['report_file'])) {
                                unlink($existingReport['report_file']);
                            }
                            $sql .= ", report_file = ?";
                            $params[] = $uploaded_file;
                        }
                        $sql .= " WHERE risk_id = ?";
                        $params[] = $risk_id;
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        $pdo->prepare("UPDATE risks SET status = 'ดำเนินการแล้ว', updated_at = NOW() WHERE id = ?")->execute([$risk_id]);
                        $success_message = 'อัปเดตสรุปผลการรายงานเรียบร้อยแล้ว ✅';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, report_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$risk_id, $corrective_action, $responsible_person, $follow_up, $expected_outcome, $uploaded_file, $_SESSION['user_id']]);

                        $pdo->prepare("UPDATE risks SET status = 'ดำเนินการแล้ว', updated_at = NOW() WHERE id = ?")->execute([$risk_id]);
                        $success_message = 'บันทึกสรุปผลการรายงานเรียบร้อยแล้ว ✅';
                    }
                } catch (Exception $e) {
                    $error_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
                }
            }
        }
    }

    if ($success_message) {
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'title' => 'สำเร็จ!',
            'message' => $success_message . '<br><small>สถานะถูกเปลี่ยนเป็น "ดำเนินการแล้ว" โดยอัตโนมัติ</small>'
        ];
    } elseif ($error_message) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'title' => 'เกิดข้อผิดพลาด!',
            'message' => $error_message
        ];
    }

    redirect('report_summary.php?risk_id=' . $risk_id);
    exit;
}

$csrf_token = generateCsrfToken();
$isAdmin = isAdmin();

// ===== ฟังก์ชัน helpers =====
function getSeverityFullText($severity) {
    $map = [
        'A' => 'มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
        'B' => 'เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
        'C' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
        'D' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลาง ต้องให้เพื่อนร่วมงานช่วยแก้ไข',
        'E' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูง ต้องแจ้งหัวหน้างานช่วยแก้ไข',
        'F' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุด ไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
    ];
    return $map[$severity] ?? 'ไม่ระบุ';
}

function getSeverityColor($severity) {
    $colors = ['A' => '#3b82f6', 'B' => '#22c55e', 'C' => '#84cc16', 'D' => '#eab308', 'E' => '#f97316', 'F' => '#ef4444'];
    return $colors[$severity] ?? '#6b7280';
}

function getSeverityBgColor($severity) {
    $colors = ['A' => '#eff6ff', 'B' => '#f0fdf4', 'C' => '#f7fee7', 'D' => '#fefce8', 'E' => '#fff7ed', 'F' => '#fef2f2'];
    return $colors[$severity] ?? '#f9fafb';
}

function getSeverityLabel($severity) {
    $labels = ['A' => 'ต่ำมาก', 'B' => 'ต่ำ', 'C' => 'ปานกลาง', 'D' => 'สูง', 'E' => 'สูงมาก', 'F' => 'สูงสุด'];
    return $labels[$severity] ?? $severity;
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'ดำเนินการแล้ว': return 'badge-emerald';
        case 'กำลังดำเนินการ': return 'badge-sky';
        case 'ยุติ': return 'badge-gray';
        default: return 'badge-slate';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'ดำเนินการแล้ว': return 'fa-check-circle';
        case 'กำลังดำเนินการ': return 'fa-spinner fa-spin';
        case 'ยุติ': return 'fa-stop-circle';
        default: return 'fa-clock';
    }
}

function thaiDateView($date) {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp) + 543;
    $day = date('d', $timestamp);
    $month = date('n', $timestamp);
    $thaiMonths = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $day . ' ' . $thaiMonths[$month] . ' ' . $year;
}

function isImageFile($filename) {
    if (empty($filename)) return false;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

function getFileIcon($filename) {
    if (empty($filename)) return 'fa-file';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = ['pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word', 'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image', 'gif' => 'fa-file-image', 'webp' => 'fa-file-image'];
    return $icons[$ext] ?? 'fa-file';
}

function formatFileSize($bytes) {
    if ($bytes === false || $bytes === null) return 'ไม่ทราบขนาด';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
}

// ===== Sidebar Data =====
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isAdmin();

// ดึงค่าการตั้งค่าระบบ
function getSystemSettings($pdo) {
    $defaults = [
        'site_name' => 'Risk Management',
        'site_description' => 'ระบบบริหารความเสี่ยง',
        'site_organization' => 'ศูนย์อนามัยที่ 8 อุดรธานี',
        'site_logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png',
        'sidebar_show_dashboard' => '1',
        'sidebar_show_reports' => '1'
    ];
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['setting_value'])) {
                    $defaults[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    } catch (Exception $e) {}
    return $defaults;
}

$settings = isset($pdo) ? getSystemSettings($pdo) : [
    'site_name' => 'Risk Management',
    'site_description' => 'ระบบบริหารความเสี่ยง',
    'site_organization' => 'ศูนย์อนามัยที่ 8 อุดรธานี',
    'site_logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png',
    'sidebar_show_dashboard' => '1',
    'sidebar_show_reports' => '1'
];

$site_name = $settings['site_name'];
$site_description = $settings['site_description'];
$site_organization = $settings['site_organization'];
$site_logo = $settings['site_logo'];
$show_dashboard = $settings['sidebar_show_dashboard'] ?? '1';

// นับจำนวนความเสี่ยงที่ยังไม่ดำเนินการ
$pending_risk_count = 0;
if (isset($pdo)) {
    try {
        if ($is_admin) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM risks WHERE status IS NULL OR status = '' OR status = 'ยังไม่ดำเนินการ'");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ? AND (status IS NULL OR status = '' OR status = 'ยังไม่ดำเนินการ')");
            $stmt->execute([$_SESSION['user_id']]);
        }
        $pending_risk_count = $stmt->fetchColumn();
    } catch (Exception $e) {}
}

$user_count = 0;
if ($is_admin && isset($pdo)) {
    try {
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Exception $e) {}
}

function isMenuActive($page, $current_page, $sub_pages = []) {
    if ($current_page == $page) return true;
    foreach ($sub_pages as $sub) {
        if ($current_page == $sub) return true;
    }
    return false;
}

$currentSeverity = $risk['severity'] ?? 'A';
$severityFullText = getSeverityFullText($currentSeverity);
$severityColor = getSeverityColor($currentSeverity);
$severityBgColor = getSeverityBgColor($currentSeverity);
$severityLabel = getSeverityLabel($currentSeverity);
$currentStatus = $risk['status'] ?: 'ยังไม่ดำเนินการ';
$statusBadgeClass = getStatusBadgeClass($currentStatus);
$statusIcon = getStatusIcon($currentStatus);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปผลการรายงาน - <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-width: 240px;
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
            overflow-x: hidden;
        }

        /* ===== Layout ===== */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        /* ===== Sidebar ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(195deg, #0f172a 0%, #1e3a8a 25%, #312e81 60%, #1e1b4b 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -40%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .sidebar::after {
            content: '';
            position: absolute;
            bottom: 10%;
            left: -30%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(167, 139, 250, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        /* Sidebar Logo */
        .sidebar-logo {
            padding: 1.25rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .logo-wrapper {
            position: relative;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .logo-ring {
            position: absolute;
            inset: -2px;
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: ringSpin 10s linear infinite;
        }

        .logo-ring::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            background: #60a5fa;
            border-radius: 50%;
            box-shadow: 0 0 8px #60a5fa;
        }

        @keyframes ringSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo-image {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 2px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .logo-fallback {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .sidebar-logo-text {
            flex: 1;
            min-width: 0;
        }

        .sidebar-logo-text h1 {
            font-size: 0.8125rem;
            font-weight: 700;
            color: white;
            line-height: 1.3;
        }

        .sidebar-logo-text p {
            font-size: 0.625rem;
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.3;
        }

        /* Sidebar Nav */
        .sidebar-nav {
            flex: 1;
            padding: 1rem 0.75rem;
            overflow-y: auto;
            position: relative;
            z-index: 1;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 3px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.06);
            border-radius: 3px;
        }

        .nav-section-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.25);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 0.65rem 0.7rem 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 0.75rem;
            border-radius: 0.65rem;
            color: rgba(255, 255, 255, 0.55);
            font-weight: 500;
            font-size: 0.84rem;
            transition: all 0.25s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 0;
            background: #60a5fa;
            border-radius: 0 3px 3px 0;
            transition: height 0.3s ease;
        }

        .menu-item:hover::before {
            height: 60%;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.85);
        }

        .menu-item.active::before {
            height: 80%;
            background: #3b82f6;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .menu-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.85rem;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .menu-item:hover .menu-icon {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.05);
        }

        .menu-item.active .menu-icon {
            background: rgba(59, 130, 246, 0.25);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        .menu-content {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .menu-text {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .menu-badge {
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            letter-spacing: 0.3px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .menu-badge-admin {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .menu-badge-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
            animation: badgePulse 2s ease-in-out infinite;
        }

        .menu-badge-users {
            background: rgba(167, 139, 250, 0.25);
            color: #c4b5fd;
            border: 1px solid rgba(167, 139, 250, 0.3);
        }

        .menu-badge-settings {
            background: rgba(52, 211, 153, 0.2);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
            animation: badgePulse 2s ease-in-out infinite;
        }

        @keyframes badgePulse {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.02);
            position: relative;
            z-index: 1;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.375rem;
        }

        .user-avatar-sidebar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .user-status-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 8px;
            height: 8px;
            background: #34d399;
            border-radius: 50%;
            border: 2px solid #1e293b;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.625rem;
            color: rgba(255, 255, 255, 0.4);
            line-height: 1.2;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            background: none;
            border: none;
            flex-shrink: 0;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        /* ===== Main Content ===== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        /* Top Bar */
        .top-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #475569;
            font-size: 1.125rem;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .menu-toggle:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #64748b;
        }

        .breadcrumb a {
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #2563eb;
        }

        .breadcrumb .current {
            color: #1e293b;
            font-weight: 600;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #475569;
        }

        /* Page Content */
        .page-content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }

        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
            border-radius: 1rem;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            position: relative;
            z-index: 1;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            border: 1px solid;
        }

        .badge-emerald {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .badge-sky {
            background: #f0f9ff;
            color: #075985;
            border-color: #bae6fd;
        }

        .badge-gray {
            background: #f9fafb;
            color: #4b5563;
            border-color: #e5e7eb;
        }

        .badge-slate {
            background: #f8fafc;
            color: #475569;
            border-color: #e2e8f0;
        }

        /* Severity */
        .severity-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid;
            margin-top: 1rem;
        }

        .severity-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .severity-info {
            flex: 1;
            min-width: 0;
        }

        .severity-level {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 0.125rem;
        }

        .severity-desc {
            font-size: 0.8rem;
            line-height: 1.5;
            color: #475569;
        }

        /* Notice */
        .notice {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.825rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            line-height: 1.5;
        }

        .notice-success {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .notice-warning {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fde68a;
            color: #92400e;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .form-textarea,
        .form-input {
            width: 100%;
            padding: 0.75rem 0.875rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.625rem;
            background: #fafbfc;
            color: #334155;
            font-size: 0.875rem;
            outline: none;
            font-family: inherit;
            line-height: 1.65;
            transition: all 0.2s;
            resize: vertical;
        }

        .form-textarea {
            min-height: 100px;
        }

        .form-textarea:focus,
        .form-input:focus {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .form-textarea:disabled,
        .form-input:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid;
            transition: all 0.2s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f8fafc;
            color: #475569;
            border-color: #e2e8f0;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border-color: #047857;
        }

        .btn-primary:hover:not(:disabled) {
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 0.4rem;
        }

        .btn-outline-blue {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .btn-outline-blue:hover {
            background: #dbeafe;
        }

        .btn-outline-green {
            background: #f0fdf4;
            color: #047857;
            border-color: #bbf7d0;
        }

        .btn-outline-green:hover {
            background: #dcfce7;
        }

        /* Upload */
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            background: #fafbfc;
            cursor: pointer;
            transition: all 0.2s;
            display: block;
        }

        .upload-area:hover:not(.disabled) {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .upload-area.disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .upload-icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
        }

        .upload-icon-circle i {
            font-size: 1.25rem;
            color: #2563eb;
        }

        .upload-text {
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .upload-hint {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* File Card */
        .file-card {
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
            margin-bottom: 0.75rem;
            background: white;
        }

        .file-card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
        }

        .file-preview {
            background: #f8fafc;
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 0.5rem;
            cursor: pointer;
        }

        .file-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .file-info-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .file-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: #1e293b;
        }

        .file-meta {
            font-size: 0.72rem;
            color: #94a3b8;
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* File Preview Inline */
        .file-preview-inline {
            margin-top: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 0.5rem;
            font-size: 0.825rem;
        }

        .btn-remove {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 0.2rem;
            border-radius: 50%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-remove:hover {
            background: #fee2e2;
        }

        /* Form Footer */
        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }

        .hidden {
            display: none !important;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Mobile Bottom Bar */
        .mobile-bottom-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            z-index: 100;
            justify-content: center;
            gap: 0.75rem;
        }

        /* ===== Responsive ===== */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .page-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.25rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: flex;
            }

            .top-bar {
                padding: 0 1rem;
            }

            .page-content {
                padding: 0.75rem;
                padding-bottom: 5rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .card {
                padding: 1rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .severity-display {
                flex-direction: column;
                text-align: center;
            }

            .file-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .file-actions {
                width: 100%;
            }

            .file-actions .btn {
                flex: 1;
                justify-content: center;
            }

            .form-footer {
                flex-direction: column;
            }

            .form-footer .btn {
                width: 100%;
                justify-content: center;
            }

            .mobile-bottom-bar {
                display: flex;
            }

            .top-bar-right span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 1rem;
                border-radius: 0.75rem;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }

            .card {
                padding: 0.875rem;
                border-radius: 0.75rem;
            }

            .form-textarea,
            .form-input {
                font-size: 0.8rem;
                padding: 0.625rem 0.75rem;
            }

            .form-textarea {
                min-height: 80px;
            }

            .upload-area {
                padding: 1.25rem;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Logo -->
            <div class="sidebar-logo">
                <div class="logo-wrapper">
                    <div class="logo-ring"></div>
                    <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="logo-image"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="logo-fallback" style="display:none;">
                        <i class="fas fa-hospital-alt" style="font-size:1rem;"></i>
                    </div>
                </div>
                <div class="sidebar-logo-text">
                    <h1><?= htmlspecialchars($site_name) ?></h1>
                    <p><?= htmlspecialchars($site_organization) ?></p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <?php if ($is_admin && $show_dashboard == '1'): ?>
                    <div class="nav-section-label">
                        <i class="fas fa-crown" style="color:#fbbf24;font-size:0.5rem;"></i> ผู้ดูแลระบบ
                    </div>
                    <a href="dashboard.php" class="menu-item <?= isMenuActive('dashboard.php', $current_page) ? 'active' : '' ?>">
                        <div class="menu-icon"><i class="fas fa-tachometer-alt"></i></div>
                        <div class="menu-content">
                            <span class="menu-text">ภาพรวมระบบ</span>
                            <span class="menu-badge menu-badge-admin">Admin</span>
                        </div>
                    </a>
                <?php endif; ?>

                <div class="nav-section-label">
                    <i class="fas fa-th-large" style="color:#60a5fa;font-size:0.5rem;"></i> เมนูหลัก
                </div>
                <a href="risks.php" class="menu-item <?= isMenuActive('risks.php', $current_page, ['view_risk.php', 'edit_risk.php', 'report_summary.php']) ? 'active' : '' ?>">
                    <div class="menu-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="menu-content">
                        <span class="menu-text">รายการความเสี่ยง</span>
                        <?php if ($pending_risk_count > 0): ?>
                            <span class="menu-badge menu-badge-pending"><?= number_format($pending_risk_count) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <a href="risk_form.php" class="menu-item <?= isMenuActive('risk_form.php', $current_page) ? 'active' : '' ?>">
                    <div class="menu-icon"><i class="fas fa-plus-circle"></i></div>
                    <div class="menu-content">
                        <span class="menu-text">เพิ่มความเสี่ยง</span>
                    </div>
                </a>

                <?php if ($is_admin): ?>
                    <div class="nav-section-label" style="margin-top:0.5rem;">
                        <i class="fas fa-cog" style="color:#94a3b8;font-size:0.5rem;"></i> จัดการระบบ
                    </div>
                    <a href="users.php" class="menu-item <?= isMenuActive('users.php', $current_page, ['user_form.php', 'view_user.php']) ? 'active' : '' ?>">
                        <div class="menu-icon"><i class="fas fa-users-cog"></i></div>
                        <div class="menu-content">
                            <span class="menu-text">จัดการผู้ใช้</span>
                            <?php if ($user_count > 0): ?>
                                <span class="menu-badge menu-badge-users"><?= number_format($user_count) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <a href="settings.php" class="menu-item <?= isMenuActive('settings.php', $current_page, ['system_config.php', 'backup.php']) ? 'active' : '' ?>">
                        <div class="menu-icon"><i class="fas fa-sliders-h"></i></div>
                        <div class="menu-content">
                            <span class="menu-text">ตั้งค่าระบบ</span>
                            <span class="menu-badge menu-badge-settings">ใหม่</span>
                        </div>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- User Footer -->
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div style="position:relative;flex-shrink:0;">
                        <img src="avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>"
                            alt="avatar" class="user-avatar-sidebar"
                            onerror="this.src='avatars/default.png'">
                        <div class="user-status-dot"></div>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></div>
                        <div class="sidebar-user-role"><?= $is_admin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน' ?></div>
                    </div>
                    <button class="logout-btn" id="logoutBtn" title="ออกจากระบบ">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <a href="index.php"><i class="fas fa-home"></i></a>
                        <span style="color:#cbd5e1;">›</span>
                        <a href="risks.php">รายการความเสี่ยง</a>
                        <span style="color:#cbd5e1;">›</span>
                        <span class="current">สรุปผลการรายงาน</span>
                    </div>
                </div>
                <div class="top-bar-right">
                    <i class="fas fa-user-circle" style="color:#94a3b8;"></i>
                    <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                </div>
            </div>

            <!-- Page Content -->
            <main class="page-content">
                <div class="content-container">
                    <!-- Header -->
                    <div class="page-header">
                        <h1>
                            <i class="fas fa-clipboard-list"></i>
                            สรุปผลการรายงาน
                        </h1>
                        <p>บันทึกมาตรการแก้ไขและการติดตามผล</p>
                    </div>

                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- ข้อมูลความเสี่ยง -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-info-circle" style="color:#2563eb;"></i>
                                    ข้อมูลความเสี่ยง
                                </div>
                            </div>

                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">ประเภท</span>
                                    <span class="info-value"><?= htmlspecialchars($risk['risk_type'] ?? '-') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">กลุ่มงาน</span>
                                    <span class="info-value"><?= htmlspecialchars($risk['unit'] ?? '-') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">วันที่</span>
                                    <span class="info-value"><?= thaiDateView($risk['event_datetime']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">ผู้รายงาน</span>
                                    <span class="info-value"><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">สถานะ</span>
                                    <span>
                                        <span class="badge <?= $statusBadgeClass ?>">
                                            <i class="fas <?= $statusIcon ?>" style="font-size:0.7rem;"></i>
                                            <?= htmlspecialchars($currentStatus) ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <!-- ระดับความเสี่ยง -->
                            <div style="margin-top: 1rem;">
                                <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">
                                    <i class="fas fa-exclamation-triangle"></i> ระดับความเสี่ยง
                                </div>
                                <div class="severity-display" style="background: <?= $severityBgColor ?>; border-color: <?= $severityColor ?>33;">
                                    <div class="severity-icon" style="background: <?= $severityColor ?>;">
                                        <?= htmlspecialchars($currentSeverity) ?>
                                    </div>
                                    <div class="severity-info">
                                        <div class="severity-level" style="color: <?= $severityColor ?>;">
                                            ระดับ <?= htmlspecialchars($currentSeverity) ?>
                                            <span style="font-weight: 400; font-size: 0.8rem;">(<?= htmlspecialchars($severityLabel) ?>)</span>
                                        </div>
                                        <div class="severity-desc">
                                            <?= htmlspecialchars($severityFullText) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($canEdit && $risk['status'] !== 'ดำเนินการแล้ว'): ?>
                                <div class="notice notice-success">
                                    <i class="fas fa-info-circle"></i>
                                    <span>เมื่อบันทึกสรุปผล <strong>สถานะจะถูกเปลี่ยนเป็น "ดำเนินการแล้ว" โดยอัตโนมัติ</strong></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!$canEdit): ?>
                                <div class="notice notice-warning">
                                    <i class="fas fa-lock"></i>
                                    <span>คุณอยู่ในโหมด <strong>อ่านอย่างเดียว</strong> — คุณไม่ใช่เจ้าของรายการนี้</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ฟอร์มบันทึก -->
                        <div class="card">
                            <form method="POST" enctype="multipart/form-data" id="reportForm">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="fas fa-edit" style="color:#7c3aed;"></i>
                                        <?= $existingReport ? 'แก้ไขสรุปผล' : 'บันทึกสรุปผล' ?>
                                    </div>
                                    <?php if ($existingReport): ?>
                                        <span style="font-size:0.75rem;color:#94a3b8;">
                                            บันทึกล่าสุด: <?= date('d/m/Y H:i', strtotime($existingReport['created_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="risk_id" value="<?= $risk_id ?>">

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-tools" style="color:#3b82f6;"></i> มาตรการแก้ไข
                                    </label>
                                    <textarea name="corrective_action" class="form-textarea"
                                        placeholder="ระบุมาตรการแก้ไขที่ดำเนินการ..."
                                        rows="4" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars($existingReport['corrective_action'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user-check" style="color:#8b5cf6;"></i> ผู้รับผิดชอบ
                                    </label>
                                    <input type="text" name="responsible_person" class="form-input"
                                        placeholder="ระบุชื่อผู้รับผิดชอบ..."
                                        value="<?= htmlspecialchars($existingReport['responsible_person'] ?? $_SESSION['username'] ?? '') ?>"
                                        <?= !$canEdit ? 'disabled' : '' ?>>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-search" style="color:#059669;"></i> การติดตามผล
                                    </label>
                                    <textarea name="follow_up" class="form-textarea"
                                        placeholder="ระบุผลการติดตาม..."
                                        rows="4" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars($existingReport['follow_up'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-chart-line" style="color:#f59e0b;"></i> ผลที่คาดว่าจะได้รับ
                                    </label>
                                    <textarea name="expected_outcome" class="form-textarea"
                                        placeholder="ระบุผลที่คาดว่าจะได้รับ..."
                                        rows="4" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars($existingReport['expected_outcome'] ?? '') ?></textarea>
                                </div>

                                <!-- แนบไฟล์ -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-paperclip" style="color:#6366f1;"></i> แนบไฟล์สรุปผล
                                    </label>

                                    <?php if (!empty($existingReport['report_file']) && file_exists($existingReport['report_file'])): ?>
                                        <?php
                                        $fp = str_replace('\\', '/', $existingReport['report_file']);
                                        $fn = basename($fp);
                                        $img = isImageFile($fp);
                                        $fs = filesize($existingReport['report_file']);
                                        ?>
                                        <div class="file-card">
                                            <div class="file-card-header">
                                                <i class="fas fa-paperclip"></i> ไฟล์ปัจจุบัน
                                            </div>
                                            <?php if ($img): ?>
                                                <div class="file-preview">
                                                    <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($fn) ?>">
                                                        <img src="<?= htmlspecialchars($fp) ?>" alt="<?= htmlspecialchars($fn) ?>" onerror="this.style.display='none';">
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <div class="file-info">
                                                <div class="file-info-left">
                                                    <div class="file-icon" style="<?= $img ? 'background:#f0fdf4;' : 'background:#eff6ff;' ?>">
                                                        <i class="fas <?= $img ? 'fa-file-image' : getFileIcon($fp) ?>"
                                                            style="<?= $img ? 'color:#059669;' : 'color:#3b82f6;' ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="file-name"><?= htmlspecialchars($fn) ?></div>
                                                        <div class="file-meta">
                                                            <?= strtoupper(pathinfo($fp, PATHINFO_EXTENSION)) ?> · <?= formatFileSize($fs) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="file-actions">
                                                    <a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="btn btn-sm btn-outline-blue" download>
                                                        <i class="fas fa-download"></i> ดาวน์โหลด
                                                    </a>
                                                    <?php if ($img): ?>
                                                        <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" class="btn btn-sm btn-outline-green">
                                                            <i class="fas fa-expand"></i> ดูภาพ
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <label class="upload-area <?= !$canEdit ? 'disabled' : '' ?>" for="report_file">
                                        <input type="file" id="report_file" name="report_file" class="hidden"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                            onchange="handleFileSelect(this)" <?= !$canEdit ? 'disabled' : '' ?>>
                                        <div class="upload-icon-circle">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <div class="upload-text">
                                            <?= $canEdit ? 'คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวาง' : 'คุณไม่มีสิทธิ์ในการอัปโหลดไฟล์' ?>
                                        </div>
                                        <div class="upload-hint">PDF, Word, Excel, รูปภาพ (สูงสุด 10MB)</div>
                                        <div id="file-preview-area" class="file-preview-inline" style="display:none;">
                                            <i class="fas fa-file"></i>
                                            <span id="selected-file-name"></span>
                                            <span id="selected-file-size" style="color:#94a3b8;"></span>
                                            <button type="button" class="btn-remove" onclick="removeSelectedFile(event)" aria-label="Remove file">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </label>
                                </div>

                                <!-- Form Footer (Desktop) -->
                                <div class="form-footer" id="formFooterDesktop">
                                    <a href="risks.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> กลับ
                                    </a>
                                    <?php if ($canEdit): ?>
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i class="fas fa-save"></i> <?= $existingReport ? 'อัปเดตข้อมูล' : 'บันทึกข้อมูล' ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled>
                                            <i class="fas fa-lock"></i> ไม่มีสิทธิ์แก้ไข
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Bar -->
    <div class="mobile-bottom-bar" id="mobileBottomBar">
        <a href="risks.php" class="btn btn-secondary" style="flex:1;justify-content:center;">
            <i class="fas fa-arrow-left"></i> กลับ
        </a>
        <?php if ($canEdit): ?>
            <button type="button" class="btn btn-primary" style="flex:1;justify-content:center;" id="mobileSubmitBtn">
                <i class="fas fa-save"></i> <?= $existingReport ? 'อัปเดต' : 'บันทึก' ?>
            </button>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <script>
        // ===== Sidebar Toggle =====
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        menuToggle.addEventListener('click', () => {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });

        sidebarOverlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        // ===== Logout =====
        document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ออกจากระบบ?',
                html: '<p style="color:#475569;">คุณต้องการออกจากระบบใช่หรือไม่</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-sign-out-alt" style="margin-right:0.25rem;"></i> ออกจากระบบ',
                cancelButtonText: '<i class="fas fa-times" style="margin-right:0.25rem;"></i> ยกเลิก',
                reverseButtons: true,
                customClass: { popup: 'rounded-xl' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังออกจากระบบ...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    setTimeout(() => { window.location.href = 'logout.php'; }, 600);
                }
            });
        });

        // ===== Fancybox =====
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Fancybox !== 'undefined') {
                Fancybox.bind("[data-fancybox]", {
                    Thumbs: { autoStart: true },
                    Toolbar: { display: ["zoom", "slideshow", "fullscreen", "download", "thumbs", "close"] }
                });
            }
        });

        // ===== SweetAlert2 Flash Message =====
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($flash): ?>
                Swal.fire({
                    icon: '<?= $flash['type'] ?? 'info' ?>',
                    title: '<?= addslashes($flash['title'] ?? '') ?>',
                    html: '<?= addslashes($flash['message'] ?? '') ?>',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'ตกลง',
                    <?php if (($flash['type'] ?? '') === 'success'): ?>
                        timer: 3000,
                        timerProgressBar: true,
                    <?php endif; ?>
                    customClass: { popup: 'rounded-xl' }
                });
            <?php endif; ?>
        });

        // ===== File Upload =====
        function handleFileSelect(input) {
            const pa = document.getElementById('file-preview-area'),
                fn = document.getElementById('selected-file-name'),
                fs = document.getElementById('selected-file-size');
            if (input.files && input.files[0]) {
                const f = input.files[0];
                if (pa) pa.style.display = 'inline-flex';
                if (fn) fn.textContent = f.name;
                if (fs) {
                    let s = f.size;
                    if (s < 1024) fs.textContent = ' (' + s + ' B)';
                    else if (s < 1048576) fs.textContent = ' (' + (s / 1024).toFixed(1) + ' KB)';
                    else fs.textContent = ' (' + (s / 1048576).toFixed(1) + ' MB)';
                }
            }
        }

        function removeSelectedFile(e) {
            e.stopPropagation();
            e.preventDefault();
            const fi = document.getElementById('report_file'),
                pa = document.getElementById('file-preview-area');
            if (fi) fi.value = '';
            if (pa) pa.style.display = 'none';
        }

        // ===== Drag & Drop =====
        const uploadArea = document.querySelector('.upload-area:not(.disabled)');
        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#2563eb';
                this.style.background = '#eff6ff';
            });
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '#cbd5e1';
                this.style.background = '#fafbfc';
            });
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#cbd5e1';
                this.style.background = '#fafbfc';
                const files = e.dataTransfer.files,
                    fi = document.getElementById('report_file');
                if (files.length > 0 && fi) {
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    fi.files = dt.files;
                    handleFileSelect(fi);
                }
            });
        }

        // ===== Submit Handler =====
        function handleSubmit() {
            const isUpdate = <?= $existingReport ? 'true' : 'false' ?>;
            const canEdit = <?= $canEdit ? 'true' : 'false' ?>;

            if (!canEdit) return;

            Swal.fire({
                title: isUpdate ? 'ยืนยันการอัปเดต?' : 'ยืนยันการบันทึก?',
                html: (isUpdate ?
                        'คุณต้องการอัปเดตสรุปผลการรายงานนี้ใช่หรือไม่?' :
                        'คุณต้องการบันทึกสรุปผลการรายงานนี้ใช่หรือไม่?') +
                    '<br><small>สถานะจะถูกเปลี่ยนเป็น "ดำเนินการแล้ว" โดยอัตโนมัติ</small>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-save" style="margin-right:0.25rem;"></i> ' + (isUpdate ? 'อัปเดต' : 'บันทึก'),
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                customClass: { popup: 'rounded-xl' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        html: 'กรุณารอสักครู่',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => Swal.showLoading()
                    });

                    const submitBtn = document.getElementById('submitBtn');
                    const mobileBtn = document.getElementById('mobileSubmitBtn');

                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
                        submitBtn.disabled = true;
                    }
                    if (mobileBtn) {
                        mobileBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
                        mobileBtn.disabled = true;
                    }

                    document.getElementById('reportForm').submit();
                }
            });
        }

        // Desktop submit
        document.getElementById('reportForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            handleSubmit();
        });

        // Mobile submit
        document.getElementById('mobileSubmitBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            handleSubmit();
        });

        // ===== Mobile Bottom Bar Scroll =====
        let lastScrollY = window.scrollY;
        const mobileBottomBar = document.getElementById('mobileBottomBar');

        if (mobileBottomBar && window.innerWidth <= 768) {
            window.addEventListener('scroll', function() {
                const currentScrollY = window.scrollY;
                if (currentScrollY > lastScrollY && currentScrollY > 100) {
                    mobileBottomBar.style.transform = 'translateY(100%)';
                    mobileBottomBar.style.transition = 'transform 0.3s ease';
                } else {
                    mobileBottomBar.style.transform = 'translateY(0)';
                }
                lastScrollY = currentScrollY;
            });
        }
    </script>
</body>
</html>