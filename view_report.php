<?php

/**
 * ดูสรุปผลการรายงาน - โหมดอ่านอย่างเดียว
 * - User: ดูได้เฉพาะรายงานของตัวเอง
 * - Admin: ดูได้ทั้งหมด
 * - แสดงข้อมูลสรุปผลแบบอ่านอย่างเดียว
 * - ไม่สามารถแก้ไขได้
 * - Responsive Full Screen Design
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

// ใช้ Session Flash Message
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

// ตรวจสอบสิทธิ์: User ดูได้เฉพาะของตัวเอง, Admin ดูได้ทั้งหมด
if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) {
    redirect('risks.php');
}

// ===== ดึงรายงาน =====
$stmt = $pdo->prepare("SELECT * FROM risk_reports WHERE risk_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$risk_id]);
$report = $stmt->fetch();

// ถ้ายังไม่มีรายงาน ให้ redirect ไปหน้าเพิ่มสรุปผล
if (!$report) {
    redirect('report_summary.php?risk_id=' . $risk_id);
    exit;
}

// ===== ฟังก์ชัน helpers =====
function getSeverityFullText($severity)
{
    $severityFullMap = [
        'A' => 'มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
        'B' => 'เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
        'C' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
        'D' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลาง ต้องให้เพื่อนร่วมงานช่วยแก้ไข',
        'E' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูง ต้องแจ้งหัวหน้างานช่วยแก้ไข',
        'F' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุด ไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
    ];
    return $severityFullMap[$severity] ?? 'ไม่ระบุ';
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

function thaiDateTimeView($date)
{
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp) + 543;
    $day = date('d', $timestamp);
    $month = date('n', $timestamp);
    $time = date('H:i', $timestamp);
    $thaiMonths = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.'
    ];
    return $day . ' ' . $thaiMonths[$month] . ' ' . $year . ' เวลา ' . $time . ' น.';
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

$isAdmin = isAdmin();

// Page title
$page_title = 'ดูสรุปผลการรายงาน';
?>
<?php include 'includes/header.php'; ?>

<style>
    /* ===== Layout Override for Full Screen ===== */
    #main-content.flex {
        overflow: hidden;
    }

    .report-layout {
        display: flex;
        height: 100vh;
        width: 100%;
        overflow: hidden;
    }

    .report-main {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }

    /* ===== Page Header ===== */
    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.25rem;
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
        pointer-events: none;
    }

    .page-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 50%;
        pointer-events: none;
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
        margin-top: 0.35rem;
        position: relative;
        z-index: 1;
    }

    /* ===== Cards ===== */
    .card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.2s;
    }

    .card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
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

    /* ===== Info Grid ===== */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .info-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-value {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
    }

    /* ===== Badge ===== */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        border: 1px solid;
    }

    .badge-readonly {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .badge-admin {
        background: #fef3c7;
        color: #92400e;
        border-color: #fde68a;
    }

    /* ===== Severity Display ===== */
    .severity-display {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border-radius: 0.75rem;
        border: 1px solid;
        margin-top: 0.75rem;
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

    /* ===== Report Sections ===== */
    .report-section {
        padding-bottom: 1.25rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .report-section:last-of-type {
        padding-bottom: 0;
        margin-bottom: 0;
        border-bottom: none;
    }

    .report-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .report-content {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.625rem;
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
        color: #334155;
        line-height: 1.7;
        min-height: 50px;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .report-content.empty {
        color: #94a3b8;
        font-style: italic;
    }

    /* ===== File Card ===== */
    .file-card {
        border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem;
        overflow: hidden;
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

    .file-card-preview {
        background: #f8fafc;
        padding: 1rem;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
    }

    .file-card-preview img {
        max-width: 100%;
        max-height: 250px;
        object-fit: contain;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: opacity 0.2s;
    }

    .file-card-preview img:hover {
        opacity: 0.9;
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
        width: 38px;
        height: 38px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
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

    /* ===== Buttons ===== */
    .btn {
        padding: 0.55rem 1.1rem;
        border-radius: 0.6rem;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid;
        transition: all 0.2s;
        font-family: 'Sarabun', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .btn-gray {
        background: #f8fafc;
        color: #475569;
        border-color: #e2e8f0;
    }

    .btn-gray:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .btn-blue {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .btn-blue:hover {
        background: #dbeafe;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.15);
    }

    .btn-emerald {
        background: #f0fdf4;
        color: #059669;
        border-color: #bbf7d0;
    }

    .btn-emerald:hover {
        background: #dcfce7;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.15);
    }

    .btn-purple {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        color: white;
        border-color: #6d28d9;
        box-shadow: 0 2px 8px rgba(124, 58, 237, 0.2);
    }

    .btn-purple:hover {
        box-shadow: 0 4px 16px rgba(124, 58, 237, 0.35);
    }

    /* ===== Responsive ===== */
    @media (max-width: 1024px) {
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .report-layout {
            flex-direction: column;
        }

        .report-main {
            padding: 0.75rem;
        }

        .page-header {
            padding: 1.25rem 1.5rem;
            border-radius: 1rem;
        }

        .page-header h1 {
            font-size: 1.25rem;
        }

        .page-header p {
            font-size: 0.8rem;
        }

        .card {
            padding: 1.15rem;
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

        .btn {
            padding: 0.5rem 0.9rem;
            font-size: 0.78rem;
        }
    }

    @media (max-width: 480px) {
        .report-main {
            padding: 0.5rem;
        }

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

        .report-content {
            font-size: 0.8rem;
            padding: 0.7rem 0.8rem;
        }

        .file-card-preview img {
            max-height: 180px;
        }
    }
</style>

<div class="report-layout">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="report-main" style="background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);">
        <div class="p-4 md:p-6 lg:p-8">
            <div class="max-w-5xl mx-auto">

                <!-- Page Header -->
                <div class="page-header">
                    <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem; position: relative; z-index: 1;">
                        <span class="badge badge-readonly" style="background: rgba(255,255,255,0.15); color: #bfdbfe; border-color: rgba(255,255,255,0.2);">
                            <i class="fas fa-lock"></i> โหมดอ่านอย่างเดียว
                        </span>
                        <?php if ($isAdmin): ?>
                            <span class="badge badge-admin" style="background: rgba(251,191,36,0.2); color: #fbbf24; border-color: rgba(251,191,36,0.3);">
                                <i class="fas fa-crown"></i> Admin
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1>
                        <i class="fas fa-clipboard-check"></i>
                        ดูสรุปผลการรายงาน
                    </h1>
                    <p>
                        <?= $isAdmin ? '👑 Admin - สามารถดูและแก้ไขได้ทุกรายการ' : '👤 ' . htmlspecialchars($_SESSION['username']) . ' - ดูได้เฉพาะรายการของตัวเอง' ?>
                    </p>
                </div>

                <!-- Content Grid -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">

                    <!-- ข้อมูลความเสี่ยง -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                                ข้อมูลความเสี่ยง
                            </div>
                            <a href="risks.php" class="btn btn-gray" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">
                                <i class="fas fa-arrow-left"></i> กลับ
                            </a>
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
                                <span class="info-label">วันที่เกิดเหตุ</span>
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
                                        <i class="fas <?= $statusIcon ?>" style="font-size: 0.65rem;"></i>
                                        <?= htmlspecialchars($currentStatus) ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- ระดับความเสี่ยง -->
                        <div style="margin-top: 1rem;">
                            <span class="info-label" style="margin-bottom: 0.5rem;">
                                <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> ระดับความเสี่ยง
                            </span>
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
                    </div>

                    <!-- สรุปผลการรายงาน -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-clipboard-check" style="color: #059669;"></i>
                                สรุปผลการรายงาน
                            </div>
                            <span style="font-size: 0.75rem; color: #94a3b8;">
                                <i class="far fa-clock"></i> <?= thaiDateTimeView($report['created_at']) ?>
                            </span>
                        </div>

                        <!-- มาตรการแก้ไข -->
                        <div class="report-section">
                            <div class="report-label">
                                <i class="fas fa-tools" style="color: #3b82f6;"></i> มาตรการแก้ไข
                            </div>
                            <div class="report-content <?= empty($report['corrective_action']) ? 'empty' : '' ?>">
                                <?= !empty($report['corrective_action']) ? htmlspecialchars($report['corrective_action']) : 'ไม่ระบุ' ?>
                            </div>
                        </div>

                        <!-- ผู้รับผิดชอบ -->
                        <div class="report-section">
                            <div class="report-label">
                                <i class="fas fa-user-check" style="color: #8b5cf6;"></i> ผู้รับผิดชอบ
                            </div>
                            <div class="report-content <?= empty($report['responsible_person']) ? 'empty' : '' ?>">
                                <?php if (!empty($report['responsible_person'])): ?>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6d28d9); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.85rem;">
                                            <?= mb_substr($report['responsible_person'], 0, 1, 'UTF-8') ?>
                                        </div>
                                        <span style="font-weight: 600;"><?= htmlspecialchars($report['responsible_person']) ?></span>
                                    </div>
                                <?php else: ?>
                                    ไม่ระบุ
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- การติดตามผล -->
                        <div class="report-section">
                            <div class="report-label">
                                <i class="fas fa-search" style="color: #059669;"></i> การติดตามผล
                            </div>
                            <div class="report-content <?= empty($report['follow_up']) ? 'empty' : '' ?>">
                                <?= !empty($report['follow_up']) ? htmlspecialchars($report['follow_up']) : 'ไม่ระบุ' ?>
                            </div>
                        </div>

                        <!-- ผลที่คาดว่าจะได้รับ -->
                        <div class="report-section">
                            <div class="report-label">
                                <i class="fas fa-chart-line" style="color: #f59e0b;"></i> ผลที่คาดว่าจะได้รับ
                            </div>
                            <div class="report-content <?= empty($report['expected_outcome']) ? 'empty' : '' ?>">
                                <?= !empty($report['expected_outcome']) ? htmlspecialchars($report['expected_outcome']) : 'ไม่ระบุ' ?>
                            </div>
                        </div>

                        <!-- ไฟล์แนบ -->
                        <?php if (!empty($report['report_file']) && file_exists($report['report_file'])): ?>
                            <?php
                            $fp = str_replace('\\', '/', $report['report_file']);
                            $fn = basename($fp);
                            $img = isImageFile($fp);
                            $fs = filesize($report['report_file']);
                            ?>
                            <div class="report-section">
                                <div class="report-label">
                                    <i class="fas fa-paperclip" style="color: #6366f1;"></i> ไฟล์แนบ
                                </div>
                                <div class="file-card">
                                    <?php if ($img): ?>
                                        <div class="file-card-preview">
                                            <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($fn) ?>">
                                                <img src="<?= htmlspecialchars($fp) ?>" alt="<?= htmlspecialchars($fn) ?>" onerror="this.style.display='none';">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="file-info">
                                        <div class="file-info-left">
                                            <div class="file-icon" style="<?= $img ? 'background: #f0fdf4;' : 'background: #eff6ff;' ?>">
                                                <i class="fas <?= $img ? 'fa-file-image' : getFileIcon($fp) ?>"
                                                    style="<?= $img ? 'color: #059669;' : 'color: #3b82f6;' ?>"></i>
                                            </div>
                                            <div>
                                                <div class="file-name"><?= htmlspecialchars($fn) ?></div>
                                                <div class="file-meta"><?= strtoupper(pathinfo($fp, PATHINFO_EXTENSION)) ?> · <?= formatFileSize($fs) ?></div>
                                            </div>
                                        </div>
                                        <div class="file-actions">
                                            <a href="<?= htmlspecialchars($fp) ?>" target="_blank" download class="btn btn-blue" style="padding: 0.4rem 0.75rem; font-size: 0.75rem;">
                                                <i class="fas fa-download"></i> ดาวน์โหลด
                                            </a>
                                            <?php if ($img): ?>
                                                <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" class="btn btn-emerald" style="padding: 0.4rem 0.75rem; font-size: 0.75rem;">
                                                    <i class="fas fa-expand"></i> ดูภาพ
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: space-between; align-items: center;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <a href="risks.php" class="btn btn-gray">
                                <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                            </a>
                            <a href="view_risk.php?id=<?= $risk_id ?>" class="btn btn-blue">
                                <i class="fas fa-eye"></i> ดูรายละเอียดความเสี่ยง
                            </a>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <a href="generate_pdf.php?id=<?= $risk_id ?>" target="_blank" class="btn btn-emerald">
                                <i class="fas fa-print"></i> พิมพ์ PDF
                            </a>
                            <?php if ($isAdmin): ?>
                                <a href="report_summary.php?risk_id=<?= $risk_id ?>" class="btn btn-purple">
                                    <i class="fas fa-edit"></i> แก้ไข (Admin)
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fancybox -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
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
</script>

<?php include 'includes/footer.php'; ?>