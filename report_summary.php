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
 * - แสดงระดับความเสี่ยงแบบเต็ม (severity + คำอธิบาย)
 * - เมื่อบันทึกรายงาน สถานะจะเปลี่ยนเป็น "ดำเนินการแล้ว" อัตโนมัติ
 * - ใช้ Session Flash Message สำหรับแจ้งเตือน
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

// รับ risk_id จาก URL (ใช้สำหรับฟอร์ม)
$risk_id = isset($_GET['risk_id']) ? (int)$_GET['risk_id'] : 0;

// ถ้าไม่มี risk_id ให้ redirect กลับไปหน้า risks
if (!$risk_id) {
    redirect('risks.php');
}

// ===== ดึงข้อมูลความเสี่ยง (ทุกสถานะ) =====
$stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$risk_id]);
$risk = $stmt->fetch();

if (!$risk) {
    redirect('risks.php');
}

// ตรวจสอบสิทธิ์: User ดูได้เฉพาะของตัวเอง, Admin ดูได้ทั้งหมด
if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) {
    redirect('risks.php');
}

// ===== ดึงรายงานที่มีอยู่ =====
$existingReport = null;
$stmt = $pdo->prepare("SELECT * FROM risk_reports WHERE risk_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$risk_id]);
$existingReport = $stmt->fetch();

// ===== User ที่เป็นเจ้าของสามารถแก้ไขสรุปผลได้ =====
$canEdit = isAdmin() || (isset($risk['user_id']) && $risk['user_id'] == $_SESSION['user_id']);

// ===== จัดการ POST (บันทึก/อัปเดต) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการบันทึกหรือแก้ไขข้อมูล'];
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request (CSRF token ไม่ถูกต้อง)'];
    } else {
        $risk_id_post = (int)($_POST['risk_id'] ?? 0);
        $corrective_action = trim($_POST['corrective_action'] ?? '');
        $responsible_person = trim($_POST['responsible_person'] ?? '');
        $follow_up = trim($_POST['follow_up'] ?? '');
        $expected_outcome = trim($_POST['expected_outcome'] ?? '');

        if (empty($corrective_action) && empty($responsible_person) && empty($follow_up) && empty($expected_outcome)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'กรุณากรอกข้อมูลอย่างน้อย 1 ฟิลด์'];
        } else {
            // อัปโหลดไฟล์
            $uploaded_file = '';
            $upload_error = '';
            
            if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/reports/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_extension = pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    $upload_error = 'ประเภทไฟล์ไม่ถูกต้อง (รองรับ: PDF, Word, Excel, รูปภาพ)';
                } elseif ($_FILES['report_file']['size'] > 10 * 1024 * 1024) {
                    $upload_error = 'ขนาดไฟล์ต้องไม่เกิน 10MB';
                } else {
                    $file_name = 'report_' . $risk_id . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['report_file']['tmp_name'], $file_path)) {
                        $uploaded_file = $file_path;
                    } else {
                        $upload_error = 'ไม่สามารถอัปโหลดไฟล์ได้';
                    }
                }
            }

            if (!empty($upload_error)) {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => $upload_error];
            } else {
                // บันทึก/อัปเดตรายงาน
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
                } else {
                    $stmt = $pdo->prepare("INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, report_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$risk_id, $corrective_action, $responsible_person, $follow_up, $expected_outcome, $uploaded_file, $_SESSION['user_id']]);
                }
                
                // ===== อัปเดตสถานะความเสี่ยงเป็น "ดำเนินการแล้ว" =====
                $updateStatusSql = "UPDATE risks SET status = 'ดำเนินการแล้ว' WHERE id = ?";
                $updateStatusStmt = $pdo->prepare($updateStatusSql);
                $updateStatusStmt->execute([$risk_id]);
                
                // ตั้งค่า flash message สำเร็จ
                if ($existingReport) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'อัปเดตสรุปผลการรายงานเรียบร้อยแล้ว (สถานะ: ดำเนินการแล้ว)'];
                } else {
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'บันทึกสรุปผลการรายงานเรียบร้อยแล้ว (สถานะ: ดำเนินการแล้ว)'];
                }
                
                // Redirect เพื่อป้องกันการ submit ซ้ำ (PRG Pattern)
                redirect('report_summary.php?risk_id=' . $risk_id);
                exit;
            }
        }
    }
    
    // ถ้ามี error ให้ redirect กลับมาแสดง error
    if (isset($_SESSION['flash_message'])) {
        redirect('report_summary.php?risk_id=' . $risk_id);
        exit;
    }
}

// ===== ดึง Flash Message =====
$flash = $_SESSION['flash_message'] ?? null;
if ($flash) {
    unset($_SESSION['flash_message']);
}

$csrf_token = generateCsrfToken();
$isAdmin = isAdmin();

// ===== ข้อมูลระดับความเสี่ยงแบบเต็ม =====
function getSeverityFullText($severity) {
    $severityFullMap = [
        'A' => 'มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
        'B' => 'เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
        'C' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
        'D' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลาง ต้องให้เพื่อนร่วมงานช่วยแก้ไข',
        'F' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูง ต้องแจ้งหัวหน้างานช่วยแก้ไข',
        'E' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุด ไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
    ];
    return $severityFullMap[$severity] ?? 'ไม่ระบุ';
}

function getSeverityColor($severity) {
    $colors = [
        'A' => '#3b82f6', 'B' => '#22c55e', 'C' => '#84cc16',
        'D' => '#eab308', 'F' => '#f97316', 'E' => '#ef4444'
    ];
    return $colors[$severity] ?? '#6b7280';
}

function getSeverityBgColor($severity) {
    $colors = [
        'A' => '#eff6ff', 'B' => '#f0fdf4', 'C' => '#f7fee7',
        'D' => '#fefce8', 'F' => '#fff7ed', 'E' => '#fef2f2'
    ];
    return $colors[$severity] ?? '#f9fafb';
}

function getSeverityLabel($severity) {
    $labels = [
        'A' => 'ต่ำมาก', 'B' => 'ต่ำ', 'C' => 'ปานกลาง',
        'D' => 'สูง', 'E' => 'สูงมาก', 'F' => 'สูงสุด'
    ];
    return $labels[$severity] ?? $severity;
}

// ข้อมูลระดับความเสี่ยงปัจจุบัน
$currentSeverity = $risk['severity'] ?? 'A';
$severityFullText = getSeverityFullText($currentSeverity);
$severityColor = getSeverityColor($currentSeverity);
$severityBgColor = getSeverityBgColor($currentSeverity);
$severityLabel = getSeverityLabel($currentSeverity);

// ===== ฟังก์ชัน helpers =====
function isImageFile($filename) {
    if (empty($filename)) return false;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

function getFileIcon($filename) {
    if (empty($filename)) return 'fa-file';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image',
        'gif' => 'fa-file-image', 'webp' => 'fa-file-image'
    ];
    return $icons[$ext] ?? 'fa-file';
}

function formatFileSize($bytes) {
    if ($bytes === false || $bytes === null) return 'ไม่ทราบขนาด';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
}

// ===== ฟังก์ชันแปลงวันที่เป็น พ.ศ. =====
function thaiDate($date) {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp) + 543;
    $day = date('d', $timestamp);
    $month = date('n', $timestamp);
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return $day . ' ' . $thaiMonths[$month] . ' ' . $year;
}

// ===== ฟังก์ชันดึง badge สีตามสถานะ =====
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'ดำเนินการแล้ว':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'กำลังดำเนินการ':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'ยุติ':
            return 'bg-gray-100 text-gray-500 border-gray-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'ดำเนินการแล้ว':
            return 'fa-check-circle';
        case 'กำลังดำเนินการ':
            return 'fa-spinner fa-spin';
        case 'ยุติ':
            return 'fa-stop-circle';
        default:
            return 'fa-clock';
    }
}

$currentStatus = $risk['status'] ?: 'ยังไม่ดำเนินการ';
$statusBadgeClass = getStatusBadgeClass($currentStatus);
$statusIcon = getStatusIcon($currentStatus);
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary: #2563eb;
        --primary-light: #eff6ff;
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #1e40af 40%, #2563eb 100%);
    }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
    }

    .page-container { max-width: 900px; margin: 0 auto; }

    .page-header {
        background: var(--primary-gradient);
        border-radius: 1.25rem;
        padding: 1.75rem 2.25rem;
        margin-bottom: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(37,99,235,0.25);
    }
    .page-header::before {
        content: '';
        position: absolute;
        top: -40%; right: -8%;
        width: 250px; height: 250px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .page-header h2 {
        font-size: 1.5rem; font-weight: 700;
        display: flex; align-items: center; gap: 0.75rem;
        position: relative; z-index: 1;
    }
    .page-header p {
        color: rgba(255,255,255,0.85); font-size: 0.9rem;
        margin-top: 0.35rem; position: relative; z-index: 1;
    }
    .back-link {
        color: rgba(255,255,255,0.85); text-decoration: none;
        font-size: 0.85rem; display: inline-flex; align-items: center;
        gap: 0.3rem; margin-bottom: 0.5rem; position: relative; z-index: 1;
        transition: color 0.2s;
    }
    .back-link:hover { color: white; }

    .info-detail-card {
        background: white; border-radius: 1rem; border: 1px solid #e2e8f0;
        padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .info-detail-card h3 {
        font-size: 1rem; font-weight: 700; color: #1e293b;
        margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;
    }
    .admin-badge {
        background: #dbeafe; color: #1e40af; font-size: 0.6rem;
        padding: 0.15rem 0.6rem; border-radius: 9999px; font-weight: 600;
    }
    .user-badge {
        background: #dbeafe; color: #2563eb; font-size: 0.6rem;
        padding: 0.15rem 0.6rem; border-radius: 9999px; font-weight: 600;
    }

    .severity-full-display {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        border: 1px solid;
        margin-top: 8px;
    }
    .severity-icon-box {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 48px;
        height: 48px;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
        border-radius: 10px;
        flex-shrink: 0;
    }
    .severity-text-content { flex: 1; }
    .severity-label {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }
    .severity-description {
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .form-card {
        background: white; border-radius: 1rem; border: 1px solid #e2e8f0;
        padding: 1.5rem; margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .form-card.disabled { opacity: 0.7; background: #f8fafc; }
    .form-card.disabled .form-textarea,
    .form-card.disabled .form-input,
    .form-card.disabled .upload-area {
        cursor: not-allowed; pointer-events: none; background: #f1f5f9;
    }

    .form-group { margin-bottom: 1.25rem; }
    .form-label {
        font-size: 0.7rem; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.35rem;
    }
    .form-textarea {
        width: 100%; min-height: 110px; padding: 0.65rem 0.85rem;
        border: 1.5px solid #e2e8f0; border-radius: 0.6rem;
        background: #fafbfc; color: #334155; font-size: 0.85rem;
        resize: vertical; outline: none; font-family: 'Sarabun', sans-serif;
        line-height: 1.6; transition: all 0.2s;
    }
    .form-textarea:focus { border-color: #2563eb; background: white; box-shadow: 0 0 0 3px rgba(37,99,235,0.08); }
    .form-input {
        width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem; background: #fafbfc; color: #334155;
        font-size: 0.85rem; outline: none; font-family: 'Sarabun', sans-serif;
        transition: all 0.2s;
    }
    .form-input:focus { border-color: #2563eb; background: white; box-shadow: 0 0 0 3px rgba(37,99,235,0.08); }

    .btn-action {
        padding: 0.55rem 1.1rem; border-radius: 0.6rem;
        font-size: 0.82rem; font-weight: 600; cursor: pointer;
        border: 1px solid; transition: all 0.2s;
        font-family: 'Sarabun', sans-serif; display: inline-flex;
        align-items: center; gap: 0.4rem; text-decoration: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .btn-action.blue { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .btn-action.blue:hover { background: #dbeafe; }
    .btn-action.gray { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
    .btn-action.gray:hover { background: #e2e8f0; }

    .file-card {
        background: white; border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem; overflow: hidden; margin-bottom: 0.75rem;
    }
    .file-card-header {
        display: flex; align-items: center; gap: 0.4rem;
        padding: 0.6rem 1rem; background: #fafbfc;
        border-bottom: 1px solid #e2e8f0; font-size: 0.8rem;
        font-weight: 600; color: #64748b;
    }
    .file-card-preview {
        background: #f8fafc; padding: 1rem; text-align: center;
        border-bottom: 1px solid #e2e8f0;
    }
    .img-preview-link {
        display: inline-block; border-radius: 0.5rem; overflow: hidden;
        border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.2s;
    }
    .img-preview-link:hover { border-color: #2563eb; }
    .img-preview-link img { display: block; max-width: 100%; max-height: 200px; object-fit: contain; }
    .file-info-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.75rem 1rem; gap: 1rem; flex-wrap: wrap;
    }
    .file-info-left { display: flex; align-items: center; gap: 0.75rem; }
    .file-icon-box {
        width: 38px; height: 38px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center; font-size: 1rem;
    }
    .btn-sm {
        padding: 0.4rem 0.7rem; border-radius: 0.4rem;
        font-size: 0.75rem; font-weight: 600; text-decoration: none;
        transition: all 0.2s; border: 1px solid;
        display: inline-flex; align-items: center; gap: 0.3rem;
    }
    .btn-sm.download { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .btn-sm.download:hover { background: #dbeafe; }
    .btn-sm.view { background: #f0fdf4; color: #059669; border-color: #bbf7d0; }
    .btn-sm.view:hover { background: #dcfce7; }

    .upload-area {
        border: 2px dashed #cbd5e1; border-radius: 0.75rem;
        padding: 2rem; text-align: center; background: #fafbfc;
        cursor: pointer; transition: all 0.2s; display: block;
    }
    .upload-area.disabled { cursor: not-allowed; opacity: 0.6; }
    .upload-area:hover:not(.disabled) { border-color: #2563eb; background: #eff6ff; }
    .upload-icon {
        width: 48px; height: 48px; border-radius: 50%;
        background: #eff6ff; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 0.75rem;
    }
    .upload-icon i { font-size: 1.2rem; color: #2563eb; }
    .file-preview-inline {
        margin-top: 0.75rem; display: inline-flex; align-items: center;
        gap: 0.4rem; padding: 0.4rem 0.7rem; background: #eff6ff;
        border: 1px solid #bfdbfe; border-radius: 0.4rem; font-size: 0.8rem;
    }
    .btn-remove {
        background: none; border: none; color: #ef4444;
        cursor: pointer; padding: 0.2rem; border-radius: 50%;
        transition: all 0.2s; display: flex; align-items: center; justify-content: center;
    }
    .btn-remove:hover { background: #fee2e2; }
    .hidden { display: none !important; }

    .badge {
        display: inline-flex; align-items: center; gap: 0.2rem;
        padding: 0.2rem 0.6rem; border-radius: 9999px;
        font-size: 0.7rem; font-weight: 600; white-space: nowrap; border: 1px solid;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        font-size: 0.85rem;
    }
    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .info-item-label {
        color: #94a3b8;
        min-width: 80px;
        flex-shrink: 0;
    }
    .info-item-value {
        font-weight: 600;
        color: #1e293b;
    }

    @media (max-width: 640px) {
        .info-grid { grid-template-columns: 1fr; }
        .severity-full-display { flex-direction: column; text-align: center; }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-6 overflow-y-auto">
        <div class="page-container">

            <!-- Header -->
            <div class="page-header">
                <div style="margin-bottom: 0.5rem;">
                    <a href="risks.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                    </a>
                </div>
                <h2>📝 สรุปผลการรายงาน</h2>
                <p>บันทึกมาตรการแก้ไขและการติดตามผล</p>
            </div>

            <!-- ข้อมูลความเสี่ยง -->
            <div class="info-detail-card">
                <h3>
                    <i class="fas fa-info-circle text-blue-600"></i> 
                    ข้อมูลความเสี่ยง
                    <?php if ($isAdmin): ?>
                        <span class="admin-badge"><i class="fas fa-user-shield"></i> Admin</span>
                    <?php elseif ($canEdit): ?>
                        <span class="user-badge"><i class="fas fa-user-edit"></i> เจ้าของรายการ</span>
                    <?php endif; ?>
                </h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-item-label">ประเภท:</span>
                        <span class="info-item-value"><?= htmlspecialchars($risk['risk_type'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-item-label">กลุ่มงาน:</span>
                        <span class="info-item-value"><?= htmlspecialchars($risk['unit'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-item-label">วันที่:</span>
                        <span class="info-item-value"><?= thaiDate($risk['event_datetime']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-item-label">ผู้รายงาน:</span>
                        <span class="info-item-value"><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-item-label">สถานะ:</span>
                        <span>
                            <span class="badge <?= $statusBadgeClass ?>">
                                <i class="fas <?= $statusIcon ?> text-xs"></i> 
                                <?= htmlspecialchars($currentStatus) ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- ระดับความเสี่ยงแบบเต็ม -->
                <div style="margin-top: 1rem;">
                    <div style="color: #94a3b8; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> ระดับความเสี่ยง
                    </div>
                    <div class="severity-full-display" style="background: <?= $severityBgColor ?>; border-color: <?= $severityColor ?>33;">
                        <div class="severity-icon-box" style="background: <?= $severityColor ?>;">
                            <?= htmlspecialchars($currentSeverity) ?>
                        </div>
                        <div class="severity-text-content">
                            <div class="severity-label" style="color: <?= $severityColor ?>;">
                                ระดับ <?= htmlspecialchars($currentSeverity) ?> 
                                <span style="font-weight: 400; font-size: 0.8rem;">(<?= htmlspecialchars($severityLabel) ?>)</span>
                            </div>
                            <div class="severity-description" style="color: #475569;">
                                <?= htmlspecialchars($severityFullText) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$canEdit): ?>
                    <div style="margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #fef3c7; border: 1px solid #fde68a; color: #92400e; display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                        <i class="fas fa-info-circle"></i>
                        <span>คุณอยู่ในโหมด <strong>อ่านอย่างเดียว</strong> คุณไม่ใช่เจ้าของรายการนี้</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" class="form-card <?= !$canEdit ? 'disabled' : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="risk_id" value="<?= $risk_id ?>">

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tools"></i> มาตรการแก้ไข</label>
                    <textarea name="corrective_action" class="form-textarea" placeholder="ระบุมาตรการแก้ไขที่ดำเนินการ..." rows="4" <?= !$canEdit ? 'readonly' : '' ?>><?= htmlspecialchars($existingReport['corrective_action'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user-check"></i> ผู้รับผิดชอบ</label>
                    <input type="text" name="responsible_person" class="form-input" placeholder="ระบุชื่อผู้รับผิดชอบ..." value="<?= htmlspecialchars($existingReport['responsible_person'] ?? $_SESSION['username'] ?? '') ?>" <?= !$canEdit ? 'readonly' : '' ?>>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-search"></i> การติดตามผล</label>
                    <textarea name="follow_up" class="form-textarea" placeholder="ระบุผลการติดตาม..." rows="4" <?= !$canEdit ? 'readonly' : '' ?>><?= htmlspecialchars($existingReport['follow_up'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-chart-line"></i> ผลที่คาดว่าจะได้รับ</label>
                    <textarea name="expected_outcome" class="form-textarea" placeholder="ระบุผลที่คาดว่าจะได้รับ..." rows="4" <?= !$canEdit ? 'readonly' : '' ?>><?= htmlspecialchars($existingReport['expected_outcome'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> แนบไฟล์สรุปผล</label>

                    <?php if (!empty($existingReport['report_file']) && file_exists($existingReport['report_file'])): ?>
                        <?php 
                            $fp = str_replace('\\', '/', $existingReport['report_file']); 
                            $fn = basename($fp); 
                            $img = isImageFile($fp); 
                            $fs = filesize($existingReport['report_file']); 
                        ?>
                        <div class="file-card">
                            <div class="file-card-header">
                                <i class="fas fa-paperclip text-blue-600"></i> ไฟล์ปัจจุบัน
                            </div>
                            <?php if ($img): ?>
                                <div class="file-card-preview">
                                    <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($fn) ?>" class="img-preview-link">
                                        <img src="<?= htmlspecialchars($fp) ?>" alt="<?= htmlspecialchars($fn) ?>" onerror="this.style.display='none';">
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="file-info-row">
                                <div class="file-info-left">
                                    <div class="file-icon-box" style="<?= $img ? 'background: #f0fdf4;' : 'background: #eff6ff;' ?>">
                                        <i class="fas <?= $img ? 'fa-file-image' : getFileIcon($fp) ?>" style="<?= $img ? 'color: #059669;' : 'color: #3b82f6;' ?>"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($fn) ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;"><?= strtoupper(pathinfo($fp, PATHINFO_EXTENSION)) ?> · <?= formatFileSize($fs) ?></div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:0.4rem;">
                                    <a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="btn-sm download" download>
                                        <i class="fas fa-download"></i> ดาวน์โหลด
                                    </a>
                                    <?php if ($img): ?>
                                        <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" class="btn-sm view">
                                            <i class="fas fa-expand"></i> ดู
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <label class="upload-area <?= !$canEdit ? 'disabled' : '' ?>" for="report_file">
                        <input type="file" id="report_file" name="report_file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" onchange="handleFileSelect(this)" <?= !$canEdit ? 'disabled' : '' ?>>
                        <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p style="font-weight: 600; color: #475569; font-size: 0.9rem;">
                            <?= $canEdit ? 'คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวาง' : 'คุณไม่มีสิทธิ์ในการอัปโหลดไฟล์' ?>
                        </p>
                        <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">PDF, Word, Excel, รูปภาพ (สูงสุด 10MB)</p>
                        <div id="file-preview-area" class="file-preview-inline" style="display:none;">
                            <i class="fas fa-file text-blue-600"></i>
                            <span id="selected-file-name"></span>
                            <span id="selected-file-size" style="color: #94a3b8;"></span>
                            <button type="button" class="btn-remove" onclick="removeSelectedFile(event)"><i class="fas fa-times"></i></button>
                        </div>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9;">
                    <a href="risks.php" class="btn-action gray"><i class="fas fa-times"></i> กลับ</a>
                    <?php if ($canEdit): ?>
                        <button type="submit" class="btn-action blue"><i class="fas fa-save"></i> <?= $existingReport ? 'อัปเดต' : 'บันทึก' ?></button>
                    <?php else: ?>
                        <button type="button" class="btn-action gray" disabled><i class="fas fa-lock"></i> ไม่มีสิทธิ์แก้ไข</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js"></script>
<script>
    // ===== Fancybox v4 =====
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind('[data-fancybox]', {
                Toolbar: {
                    display: ["zoom", "slideshow", "fullscreen", "download", "thumbs", "close"]
                },
                Thumbs: {
                    autoStart: true
                }
            });
        }
    });

    // ===== Handle File Select =====
    function handleFileSelect(input) {
        const pa = document.getElementById('file-preview-area'),
              fn = document.getElementById('selected-file-name'),
              fsEl = document.getElementById('selected-file-size');
        if (input.files && input.files[0]) {
            const f = input.files[0];
            if (pa) pa.style.display = 'inline-flex';
            if (fn) fn.textContent = f.name;
            if (fsEl) {
                let s = f.size;
                if (s < 1024) fsEl.textContent = ' (' + s + ' B)';
                else if (s < 1048576) fsEl.textContent = ' (' + (s / 1024).toFixed(1) + ' KB)';
                else fsEl.textContent = ' (' + (s / 1048576).toFixed(1) + ' MB)';
            }
        }
    }

    // ===== Remove Selected File =====
    function removeSelectedFile(e) {
        e.stopPropagation(); e.preventDefault();
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
            const files = e.dataTransfer.files;
            const fi = document.getElementById('report_file');
            if (files.length > 0 && fi) {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fi.files = dt.files;
                handleFileSelect(fi);
            }
        });
    }

    // ===== Submit Loading =====
    const form = document.querySelector('form:not(.disabled)');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
                submitBtn.style.pointerEvents = 'none';
                submitBtn.style.opacity = '0.8';
            }
        });
    }

    // ===== SweetAlert2 Flash Message =====
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($flash): ?>
            <?php if ($flash['type'] === 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ!',
                html: '<p style="font-size: 0.95rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($flash['message']) ?></p>' +
                      '<p style="font-size: 0.8rem; color: #64748b;">' +
                      '<i class="fas fa-check-circle text-green-500"></i> สถานะถูกเปลี่ยนเป็น <strong>"ดำเนินการแล้ว"</strong> โดยอัตโนมัติ' +
                      '</p>',
                confirmButtonColor: '#2563eb',
                confirmButtonText: '<i class="fas fa-check"></i> ตกลง',
                timer: 4000,
                timerProgressBar: true,
                showCloseButton: true,
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-bold'
                }
            });
            <?php else: ?>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด!',
                text: '<?= htmlspecialchars($flash['message']) ?>',
                confirmButtonColor: '#dc2626',
                confirmButtonText: '<i class="fas fa-times"></i> ตกลง',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-bold'
                }
            });
            <?php endif; ?>
        <?php endif; ?>
    });
</script>

<?php include 'includes/footer.php'; ?>