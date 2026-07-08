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
function getSeverityFullText($severity)
{
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

function getSeverityColor($severity)
{
    $colors = [
        'A' => '#3b82f6',
        'B' => '#22c55e',
        'C' => '#84cc16',
        'D' => '#eab308',
        'E' => '#f97316',
        'F' => '#ef4444'
    ];
    return $colors[$severity] ?? '#6b7280';
}

function getSeverityBgColor($severity)
{
    $colors = [
        'A' => '#eff6ff',
        'B' => '#f0fdf4',
        'C' => '#f7fee7',
        'D' => '#fefce8',
        'E' => '#fff7ed',
        'F' => '#fef2f2'
    ];
    return $colors[$severity] ?? '#f9fafb';
}

function getSeverityLabel($severity)
{
    $labels = [
        'A' => 'ต่ำมาก',
        'B' => 'ต่ำ',
        'C' => 'ปานกลาง',
        'D' => 'สูง',
        'E' => 'สูงมาก',
        'F' => 'สูงสุด'
    ];
    return $labels[$severity] ?? $severity;
}

function getStatusBadgeClass($status)
{
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

function getStatusIcon($status)
{
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

function thaiDateView($date)
{
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp) + 543;
    $day = date('d', $timestamp);
    $month = date('n', $timestamp);
    $thaiMonths = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];
    return $day . ' ' . $thaiMonths[$month] . ' ' . $year;
}

function isImageFile($filename)
{
    if (empty($filename)) return false;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

function getFileIcon($filename)
{
    if (empty($filename)) return 'fa-file';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image',
        'gif' => 'fa-file-image',
        'webp' => 'fa-file-image'
    ];
    return $icons[$ext] ?? 'fa-file';
}

function formatFileSize($bytes)
{
    if ($bytes === false || $bytes === null) return 'ไม่ทราบขนาด';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
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
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary: #2563eb;
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #1e40af 40%, #2563eb 100%);
    }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
    }

    .page-container {
        max-width: 960px;
        margin: 0 auto;
        padding: 0.5rem;
    }

    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem;
        padding: 1.75rem 2.25rem;
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
        width: 350px;
        height: 350px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .page-header h2 {
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
        font-size: 0.9rem;
        margin-top: 0.35rem;
        position: relative;
        z-index: 1;
    }

    .back-link {
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-bottom: 0.5rem;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: white;
    }

    .card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .card h3 {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        border: 1px solid;
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

    .severity-text-content {
        flex: 1;
    }

    .severity-label-view {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }

    .severity-description {
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .status-update-notice {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        border: 1px solid #a7f3d0;
        border-radius: 0.75rem;
        font-size: 0.82rem;
        color: #065f46;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .permission-notice {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border: 1px solid #fde68a;
        border-radius: 0.75rem;
        font-size: 0.82rem;
        color: #92400e;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    /* Form */
    .form-card.disabled {
        opacity: 0.75;
    }

    .form-card.disabled .form-textarea,
    .form-card.disabled .form-input,
    .form-card.disabled .upload-area {
        pointer-events: none;
        background: #f1f5f9;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .form-textarea {
        width: 100%;
        min-height: 110px;
        padding: 0.7rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        background: #fafbfc;
        color: #334155;
        font-size: 0.85rem;
        resize: vertical;
        outline: none;
        font-family: inherit;
        line-height: 1.65;
        transition: all 0.2s;
    }

    .form-textarea:focus {
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    .form-input {
        width: 100%;
        padding: 0.7rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        background: #fafbfc;
        color: #334155;
        font-size: 0.85rem;
        outline: none;
        font-family: inherit;
        transition: all 0.2s;
    }

    .form-input:focus {
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    .btn {
        padding: 0.55rem 1.1rem;
        border-radius: 0.5rem;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid;
        transition: all 0.2s;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-secondary {
        background: #f8fafc;
        color: #475569;
        border-color: #e2e8f0;
    }

    .btn-secondary:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .btn-success {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        border-color: #047857;
    }

    .btn-success:hover {
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-sm {
        padding: 0.35rem 0.7rem;
        font-size: 0.73rem;
        border-radius: 0.4rem;
    }

    .btn-download {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .btn-download:hover {
        background: #dbeafe;
    }

    .btn-view {
        background: #f0fdf4;
        color: #047857;
        border-color: #bbf7d0;
    }

    .btn-view:hover {
        background: #dcfce7;
    }

    .file-card {
        background: white;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem;
        overflow: hidden;
        margin-bottom: 0.75rem;
    }

    .file-card-header {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.6rem 1rem;
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
    }

    .file-card-preview {
        background: #f8fafc;
        padding: 1rem;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
    }

    .img-preview-link {
        display: inline-block;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
    }

    .img-preview-link:hover {
        border-color: #2563eb;
    }

    .img-preview-link img {
        display: block;
        max-width: 100%;
        max-height: 180px;
        object-fit: contain;
    }

    .file-info-row {
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
        gap: 0.7rem;
    }

    .file-icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 0.6rem;
        padding: 2rem;
        text-align: center;
        background: #fafbfc;
        cursor: pointer;
        transition: all 0.2s;
        display: block;
    }

    .upload-area.disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .upload-area:hover:not(.disabled) {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .upload-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #eff6ff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem;
    }

    .upload-icon i {
        font-size: 1.2rem;
        color: #2563eb;
    }

    .file-preview-inline {
        margin-top: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.75rem;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.4rem;
        font-size: 0.8rem;
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

    .hidden {
        display: none !important;
    }

    .form-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid #f1f5f9;
    }

    @media (max-width: 640px) {
        .page-header {
            padding: 1.25rem;
        }

        .card {
            padding: 1.15rem;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .severity-full-display {
            flex-direction: column;
            text-align: center;
        }

        .form-footer {
            flex-direction: column;
        }

        .form-footer .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-6 overflow-y-auto">
        <div class="page-container">

            <!-- Header -->
            <div class="page-header">
                <!-- <a href="risks.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                </a> -->
                <h2>
                    <i class="fas fa-clipboard-list"></i>
                    สรุปผลการรายงาน
                </h2>
                <p>บันทึกมาตรการแก้ไขและการติดตามผล</p>
            </div>

            <!-- ข้อมูลความเสี่ยง -->
            <div class="card">
                <h3>
                    <i class="fas fa-info-circle text-blue-600"></i>
                    ข้อมูลความเสี่ยง
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
                        <span class="info-item-value"><?= thaiDateView($risk['event_datetime']) ?></span>
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

                <!-- ระดับความเสี่ยง -->
                <div style="margin-top: 1rem;">
                    <div style="color: #94a3b8; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> ระดับความเสี่ยง
                    </div>
                    <div class="severity-full-display" style="background: <?= $severityBgColor ?>; border-color: <?= $severityColor ?>33;">
                        <div class="severity-icon-box" style="background: <?= $severityColor ?>;">
                            <?= htmlspecialchars($currentSeverity) ?>
                        </div>
                        <div class="severity-text-content">
                            <div class="severity-label-view" style="color: <?= $severityColor ?>;">
                                ระดับ <?= htmlspecialchars($currentSeverity) ?>
                                <span style="font-weight: 400; font-size: 0.8rem;">(<?= htmlspecialchars($severityLabel) ?>)</span>
                            </div>
                            <div class="severity-description" style="color: #475569;">
                                <?= htmlspecialchars($severityFullText) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($canEdit && $risk['status'] !== 'ดำเนินการแล้ว'): ?>
                    <div class="status-update-notice">
                        <i class="fas fa-info-circle"></i>
                        <span>เมื่อบันทึกสรุปผล <strong>สถานะจะถูกเปลี่ยนเป็น "ดำเนินการแล้ว" โดยอัตโนมัติ</strong></span>
                    </div>
                <?php endif; ?>

                <?php if (!$canEdit): ?>
                    <div class="permission-notice">
                        <i class="fas fa-lock"></i>
                        <span>คุณอยู่ในโหมด <strong>อ่านอย่างเดียว</strong> — คุณไม่ใช่เจ้าของรายการนี้</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ฟอร์มบันทึก -->
            <form method="POST" enctype="multipart/form-data" class="card <?= !$canEdit ? 'form-card disabled' : '' ?>" id="reportForm">
                <h3>
                    <i class="fas fa-edit text-purple-600"></i>
                    <?= $existingReport ? 'แก้ไขสรุปผล' : 'บันทึกสรุปผล' ?>
                    <?php if ($existingReport): ?>
                        <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 400;">
                            บันทึกล่าสุด: <?= date('d/m/Y H:i', strtotime($existingReport['created_at'])) ?>
                        </span>
                    <?php endif; ?>
                </h3>

                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="risk_id" value="<?= $risk_id ?>">

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tools" style="color: #3b82f6;"></i> มาตรการแก้ไข
                    </label>
                    <textarea name="corrective_action" class="form-textarea"
                        placeholder="ระบุมาตรการแก้ไขที่ดำเนินการ..."
                        rows="4" <?= !$canEdit ? 'readonly' : '' ?>><?= htmlspecialchars($existingReport['corrective_action'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user-check" style="color: #8b5cf6;"></i> ผู้รับผิดชอบ
                    </label>
                    <input type="text" name="responsible_person" class="form-input"
                        placeholder="ระบุชื่อผู้รับผิดชอบ..."
                        value="<?= htmlspecialchars($existingReport['responsible_person'] ?? $_SESSION['username'] ?? '') ?>"
                        <?= !$canEdit ? 'readonly' : '' ?>>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-search" style="color: #059669;"></i> การติดตามผล
                    </label>
                    <textarea name="follow_up" class="form-textarea"
                        placeholder="ระบุผลการติดตาม..."
                        rows="4" <?= !$canEdit ? 'readonly' : '' ?>><?= htmlspecialchars($existingReport['follow_up'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-chart-line" style="color: #f59e0b;"></i> ผลที่คาดว่าจะได้รับ
                    </label>
                    <textarea name="expected_outcome" class="form-textarea"
                        placeholder="ระบุผลที่คาดว่าจะได้รับ..."
                        rows="4" <?= !$canEdit ? 'readonly' : '' ?>><?= htmlspecialchars($existingReport['expected_outcome'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-paperclip" style="color: #6366f1;"></i> แนบไฟล์สรุปผล
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
                                <div class="file-card-preview">
                                    <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($fn) ?>" class="img-preview-link">
                                        <img src="<?= htmlspecialchars($fp) ?>" alt="<?= htmlspecialchars($fn) ?>" onerror="this.style.display='none';">
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="file-info-row">
                                <div class="file-info-left">
                                    <div class="file-icon-box" style="<?= $img ? 'background: #f0fdf4;' : 'background: #eff6ff;' ?>">
                                        <i class="fas <?= $img ? 'fa-file-image' : getFileIcon($fp) ?>"
                                            style="<?= $img ? 'color: #059669;' : 'color: #3b82f6;' ?>"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.84rem;"><?= htmlspecialchars($fn) ?></div>
                                        <div style="font-size: 0.72rem; color: #94a3b8;">
                                            <?= strtoupper(pathinfo($fp, PATHINFO_EXTENSION)) ?> · <?= formatFileSize($fs) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:0.4rem;">
                                    <a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="btn btn-sm btn-download" download>
                                        <i class="fas fa-download"></i> ดาวน์โหลด
                                    </a>
                                    <?php if ($img): ?>
                                        <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" class="btn btn-sm btn-view">
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
                        <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p style="font-weight: 600; color: #475569; font-size: 0.9rem;">
                            <?= $canEdit ? 'คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวาง' : 'คุณไม่มีสิทธิ์ในการอัปโหลดไฟล์' ?>
                        </p>
                        <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">
                            PDF, Word, Excel, รูปภาพ (สูงสุด 10MB)
                        </p>
                        <div id="file-preview-area" class="file-preview-inline" style="display:none;">
                            <i class="fas fa-file"></i>
                            <span id="selected-file-name"></span>
                            <span id="selected-file-size" style="color: #94a3b8;"></span>
                            <button type="button" class="btn-remove" onclick="removeSelectedFile(event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </label>
                </div>

                <div class="form-footer">
                    <a href="risks.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> ยกเลิก
                    </a>
                    <?php if ($canEdit): ?>
                        <button type="submit" class="btn btn-success" id="submitBtn">
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

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    // ===== Fancybox =====
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind("[data-fancybox]", {
                Thumbs: {
                    autoStart: true
                },
                Toolbar: {
                    display: ["zoom", "slideshow", "fullscreen", "download", "thumbs", "close"]
                }
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
                customClass: {
                    popup: 'rounded-xl'
                }
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

    // ===== Confirm Submit =====
    const form = document.getElementById('reportForm');
    if (form && !form.classList.contains('disabled')) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const isUpdate = <?= $existingReport ? 'true' : 'false' ?>;

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
                confirmButtonText: '<i class="fas fa-save"></i> ' + (isUpdate ? 'อัปเดต' : 'บันทึก'),
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        html: 'กรุณารอสักครู่',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
                        submitBtn.style.pointerEvents = 'none';
                    }
                    form.submit();
                }
            });
        });
    }
</script>

<?php include 'includes/footer.php'; ?>