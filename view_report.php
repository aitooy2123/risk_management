<?php

/**
 * ดูสรุปผลการรายงาน - โหมดอ่านอย่างเดียว
 * - User: ดูได้เฉพาะรายงานของตัวเอง
 * - Admin: ดูได้ทั้งหมด
 * - แสดงข้อมูลสรุปผลแบบอ่านอย่างเดียว
 * - ไม่สามารถแก้ไขได้
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

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'ดำเนินการแล้ว': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'กำลังดำเนินการ': return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'ยุติ': return 'bg-gray-100 text-gray-500 border-gray-200';
        default: return 'bg-slate-100 text-slate-600 border-slate-200';
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
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
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

$currentSeverity = $risk['severity'] ?? 'A';
$severityFullText = getSeverityFullText($currentSeverity);
$severityColor = getSeverityColor($currentSeverity);
$severityBgColor = getSeverityBgColor($currentSeverity);
$severityLabel = getSeverityLabel($currentSeverity);

$currentStatus = $risk['status'] ?: 'ยังไม่ดำเนินการ';
$statusBadgeClass = getStatusBadgeClass($currentStatus);
$statusIcon = getStatusIcon($currentStatus);

$isAdmin = isAdmin();
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

    .card {
        background: white; border-radius: 1rem; border: 1px solid #e2e8f0;
        padding: 1.5rem; margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .card h3 {
        font-size: 1rem; font-weight: 700; color: #1e293b;
        margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
        flex-wrap: wrap;
    }

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
    .severity-label-view {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 2px;
    }
    .severity-description {
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .readonly-badge {
        display: inline-flex; align-items: center; gap: 0.3rem;
        background: #dbeafe; color: #1e40af; font-size: 0.65rem;
        padding: 0.2rem 0.7rem; border-radius: 9999px; font-weight: 600;
    }

    .report-section {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .report-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    .report-label {
        font-size: 0.7rem; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.35rem;
    }
    .report-content {
        font-size: 0.9rem; color: #334155; line-height: 1.7;
        background: #f8fafc; padding: 0.75rem 1rem;
        border-radius: 0.6rem; border: 1px solid #e2e8f0;
        min-height: 50px;
    }
    .report-content.empty {
        color: #94a3b8; font-style: italic;
        display: flex; align-items: center;
    }

    .file-card {
        background: white; border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem; overflow: hidden;
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
    .img-preview-link img { display: block; max-width: 100%; max-height: 300px; object-fit: contain; }
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
    .btn-action.green { background: #f0fdf4; color: #059669; border-color: #bbf7d0; }
    .btn-action.green:hover { background: #dcfce7; }

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
                <h2>👁️ ดูสรุปผลการรายงาน</h2>
                <p>
                    โหมดอ่านอย่างเดียว · 
                    <?= $isAdmin ? '👑 Admin (สามารถแก้ไขได้)' : '👤 ' . htmlspecialchars($_SESSION['username']) ?>
                </p>
            </div>

            <!-- ข้อมูลความเสี่ยง -->
            <div class="card">
                <h3>
                    <i class="fas fa-info-circle text-blue-600"></i> 
                    ข้อมูลความเสี่ยง
                    <span class="readonly-badge"><i class="fas fa-lock"></i> อ่านอย่างเดียว</span>
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
            </div>

            <!-- สรุปผลการรายงาน -->
            <div class="card">
                <h3>
                    <i class="fas fa-clipboard-check text-green-600"></i> 
                    สรุปผลการรายงาน
                    <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 400;">
                        บันทึกเมื่อ <?= thaiDateView($report['created_at']) ?>
                    </span>
                </h3>

                <!-- มาตรการแก้ไข -->
                <div class="report-section">
                    <div class="report-label"><i class="fas fa-tools"></i> มาตรการแก้ไข</div>
                    <div class="report-content <?= empty($report['corrective_action']) ? 'empty' : '' ?>">
                        <?= !empty($report['corrective_action']) ? nl2br(htmlspecialchars($report['corrective_action'])) : 'ไม่ระบุ' ?>
                    </div>
                </div>

                <!-- ผู้รับผิดชอบ -->
                <div class="report-section">
                    <div class="report-label"><i class="fas fa-user-check"></i> ผู้รับผิดชอบ</div>
                    <div class="report-content <?= empty($report['responsible_person']) ? 'empty' : '' ?>">
                        <?= !empty($report['responsible_person']) ? htmlspecialchars($report['responsible_person']) : 'ไม่ระบุ' ?>
                    </div>
                </div>

                <!-- การติดตามผล -->
                <div class="report-section">
                    <div class="report-label"><i class="fas fa-search"></i> การติดตามผล</div>
                    <div class="report-content <?= empty($report['follow_up']) ? 'empty' : '' ?>">
                        <?= !empty($report['follow_up']) ? nl2br(htmlspecialchars($report['follow_up'])) : 'ไม่ระบุ' ?>
                    </div>
                </div>

                <!-- ผลที่คาดว่าจะได้รับ -->
                <div class="report-section">
                    <div class="report-label"><i class="fas fa-chart-line"></i> ผลที่คาดว่าจะได้รับ</div>
                    <div class="report-content <?= empty($report['expected_outcome']) ? 'empty' : '' ?>">
                        <?= !empty($report['expected_outcome']) ? nl2br(htmlspecialchars($report['expected_outcome'])) : 'ไม่ระบุ' ?>
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
                        <div class="report-label"><i class="fas fa-paperclip"></i> ไฟล์แนบ</div>
                        <div class="file-card">
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
                    </div>
                <?php endif; ?>
            </div>

            <!-- ปุ่มกลับ -->
            <div style="display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                <div style="display: flex; gap: 0.5rem;">
                    <a href="risks.php" class="btn-action gray">
                        <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                    </a>
                    <a href="view_risk.php?id=<?= $risk_id ?>" class="btn-action blue">
                        <i class="fas fa-eye"></i> ดูรายละเอียดความเสี่ยง
                    </a>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="generate_pdf.php?id=<?= $risk_id ?>" target="_blank" class="btn-action green">
                        <i class="fas fa-print"></i> พิมพ์ PDF
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="report_summary.php?risk_id=<?= $risk_id ?>" class="btn-action blue">
                            <i class="fas fa-edit"></i> แก้ไข (Admin)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
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
</script>

<?php include 'includes/footer.php'; ?>