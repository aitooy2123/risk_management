<?php
/**
 * generate_pdf.php – สร้าง PDF รายงานความเสี่ยง (ภาษาไทยสมบูรณ์)
 * - ใช้ฟอนต์ Sarabun จากเครื่อง
 * - รองรับภาษาไทย 100%
 * - ใช้ลิงก์รูปภาพจาก URL
 * - แนบไฟล์สรุปผล (risk_reports)
 * - แสดงผลสรุปในหน้าเดียว
 * - ไม่มี badge และ pill
 * - จัดรูปแบบสวยงาม อ่านง่าย
 * - ไม่มีสถานะการยินยอม (Consent)
 * - แสดงหมายเลข ID ของความเสี่ยง
 * - แก้ไขปัญหาไม้เอกทับกัน (ใช้ line-height และ spacing)
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) die('กรุณาเข้าสู่ระบบ');

// ---------- รับพารามิเตอร์ ----------
$id  = $_GET['id'] ?? null;
$ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
if ($id) $ids = [$id];
if (empty($ids)) die('ไม่พบข้อมูลที่ต้องการพิมพ์');

// ---------- ดึงข้อมูลตามสิทธิ์ ----------
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT r.*, u.username
        FROM risks r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.id IN ($placeholders)";
if (!isAdmin()) {
    $sql .= " AND r.user_id = ?";
    $params = array_merge($ids, [$_SESSION['user_id']]);
} else {
    $params = $ids;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$risks = $stmt->fetchAll();
if (empty($risks)) die('ไม่พบข้อมูลหรือไม่มีสิทธิ์');

// ---------- ดึงข้อมูลสรุปผล (risk_reports) ----------
$reportData = [];
foreach ($risks as $risk) {
    $stmt = $pdo->prepare("SELECT * FROM risk_reports WHERE risk_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$risk['id']]);
    $report = $stmt->fetch();
    if ($report) {
        $reportData[$risk['id']] = $report;
    }
}

// ---------- โหลด Dompdf ----------
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// ---------- URL โลโก้ ----------
$logoUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png';

// ---------- ตรวจสอบฟังก์ชัน getThaiDate() ----------
if (!function_exists('getThaiDate')) {
    function getThaiDate($datetime) {
        if (empty($datetime)) return '-';
        $thai_months = [
            'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
        ];
        $ts = strtotime($datetime);
        $year = date('Y', $ts) + 543;
        $month = (int)date('m', $ts) - 1;
        $day = date('j', $ts);
        $hour = date('H', $ts);
        $min = date('i', $ts);
        return "{$day} {$thai_months[$month]} {$year} เวลา {$hour}:{$min} น.";
    }
}

// ---------- สร้าง HTML (สรุปในหน้าเดียว จัดสวย) ----------
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
    @font-face {
        font-family: "Sarabun";
        src: url("' . __DIR__ . '/fonts/Sarabun-Regular.ttf' . '") format("truetype");
        font-weight: normal;
        font-style: normal;
    }
    @font-face {
        font-family: "Sarabun";
        src: url("' . __DIR__ . '/fonts/Sarabun-Bold.ttf' . '") format("truetype");
        font-weight: bold;
        font-style: normal;
    }
    
    @page { 
        margin: 1.8cm 1.5cm; 
        size: A4 portrait;
    }
    
    body {
        font-family: "Sarabun", "Garuda", sans-serif;
        font-size: 13px;
        line-height: 1.8;
        color: #1e293b;
        background: #ffffff;
        position: relative;
        letter-spacing: 0.02em;
    }
    
    .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 80px;
        color: rgba(30, 58, 138, 0.04);
        z-index: -1;
        white-space: nowrap;
        font-weight: bold;
        pointer-events: none;
        font-family: "Sarabun", sans-serif;
    }
    
    .header { 
        text-align: center; 
        border-bottom: 3px solid #1e293b; 
        padding-bottom: 12px; 
        margin-bottom: 16px; 
        position: relative;
    }
    
    .header-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        margin-bottom: 5px;
    }
    
    .header-logo img {
        height: 55px;
        width: auto;
    }
    
    .header h1 { 
        font-size: 20px; 
        margin: 0; 
        color: #1e293b; 
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    
    .header .sub { 
        font-size: 14px; 
        color: #1e293b; 
    }
    
    .header .sub-sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }
    
    .footer { 
        position: fixed; 
        bottom: 0; 
        left: 0; 
        right: 0; 
        text-align: center; 
        font-size: 10px; 
        color: #94a3b8; 
        border-top: 1px solid #e2e8f0; 
        padding-top: 8px; 
    }
    
    .title-section { 
        font-weight: bold; 
        font-size: 15px; 
        color: #1e293b; 
        margin-top: 12px; 
        margin-bottom: 8px; 
        padding-bottom: 4px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .title-section-sm { 
        font-weight: bold; 
        font-size: 13px; 
        color: #1e293b; 
        margin-top: 10px; 
        margin-bottom: 6px; 
        padding-bottom: 3px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .objectives { 
        padding: 8px 14px; 
        margin-bottom: 12px; 
        font-size: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        background: #f8fafc;
        line-height: 1.8;
    }
    
    .objectives ul { 
        margin: 3px 0 0 18px; 
        padding: 0; 
    }
    
    .objectives li {
        margin-bottom: 2px;
    }
    
    .info-grid { 
        display: table; 
        width: 100%; 
        margin-bottom: 8px; 
        border-collapse: collapse; 
        font-size: 12px;
    }
    
    .info-row { 
        display: table-row; 
    }
    
    .info-label { 
        display: table-cell; 
        width: 25%; 
        font-weight: bold; 
        padding: 6px 8px 6px 0; 
        border-bottom: 1px solid #f1f5f9; 
        color: #1e293b; 
        line-height: 1.8;
    }
    
    .info-value { 
        display: table-cell; 
        padding: 6px 0; 
        border-bottom: 1px solid #f1f5f9; 
        line-height: 1.8;
    }
    
    .detail-box { 
        padding: 10px 14px; 
        margin: 5px 0 8px 0; 
        line-height: 1.8;
        font-size: 12px;
        border-left: 3px solid #94a3b8;
        background: #f8fafc;
        border-radius: 3px;
    }
    
    .section-label {
        font-weight: bold;
        font-size: 12px;
        color: #1e293b;
        margin-bottom: 3px;
        margin-top: 6px;
        line-height: 1.8;
    }
    
    .divider {
        border: none;
        border-top: 1px dashed #e2e8f0;
        margin: 8px 0;
    }
    
    .risk-item {
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .risk-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .summary-bar {
        padding: 8px 14px;
        margin: 10px 0 12px 0;
        text-align: center;
        font-size: 13px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        line-height: 1.8;
    }
    
    .summary-bar strong {
        color: #1e293b;
    }
    
    .severity-text {
        font-weight: normal;
    }
    
    .status-pending { color: #92400e; font-weight: bold; }
    .status-progress { color: #1e40af; font-weight: bold; }
    .status-completed { color: #065f46; font-weight: bold; }
    .status-terminated { color: #475569; font-weight: bold; }
    
    .text-muted { color: #94a3b8; font-size: 11px; }
    
    .page-break {
        page-break-before: avoid;
        page-break-after: avoid;
    }
    
    .id-badge {
        display: inline-block;
        background: #f1f5f9;
        padding: 2px 12px;
        border-radius: 4px;
        font-size: 11px;
        color: #64748b;
        font-weight: normal;
        margin-left: 8px;
        line-height: 1.8;
    }
    
    .thai-text {
        font-family: "Sarabun", "Garuda", sans-serif;
        letter-spacing: 0.03em;
        word-spacing: 0.05em;
    }
</style>
</head>
<body>
<div class="watermark">ศูนย์อนามัยที่ 8</div>';

// ----- Header -----
$html .= '
<div class="header">
    <div class="header-logo">
        <img src="' . $logoUrl . '" alt="ศูนย์อนามัยที่ 8">
        <div>
            <h1>รายงานอุบัติการณ์ความเสี่ยง</h1>
            <div class="sub">ศูนย์อนามัยที่ 8 อุดรธานี</div>
            <div class="sub-sub">กรมอนามัย กระทรวงสาธารณสุข</div>
        </div>
    </div>
</div>

<div class="objectives">
    <strong>📋 วัตถุประสงค์</strong>
    <ul>
        <li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li>
        <li>เพื่อป้องกัน ลดการความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
        <li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ และช่วยให้องค์กรพัฒนาเป็นไปในแนวทางเดียวกัน</li>
    </ul>
</div>';

// ----- สรุปภาพรวม -----
$totalRisks = count($risks);
$html .= '
<div class="summary-bar">
    📊 <strong>สรุปภาพรวม</strong> : จำนวนความเสี่ยงทั้งหมด <strong>' . $totalRisks . '</strong> รายการ 
    | วันที่พิมพ์ ' . getThaiDate(date('Y-m-d H:i:s')) . '
</div>';

// ----- Loop ข้อมูล -----
$severityMap = [
    'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
    'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
    'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
    'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
    'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
    'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
];

$statusMap = [
    'ยังไม่ดำเนินการ' => 'status-pending',
    'กำลังดำเนินการ' => 'status-progress',
    'ดำเนินการแล้ว' => 'status-completed',
    'ยุติ' => 'status-terminated'
];

foreach ($risks as $index => $risk) {
    $severityText = isset($severityMap[$risk['severity']]) ? $severityMap[$risk['severity']] : $risk['severity'];
    $statusClass = isset($statusMap[$risk['status']]) ? $statusMap[$risk['status']] : 'status-pending';
    $statusText = $risk['status'] ?? 'ยังไม่ดำเนินการ';
    $report = isset($reportData[$risk['id']]) ? $reportData[$risk['id']] : null;
    
    $html .= '
<div class="risk-item page-break thai-text">
    <div class="title-section">
        📋 ความเสี่ยง #' . ($index + 1) . '
        <span class="id-badge">ID: ' . $risk['id'] . '</span>
    </div>
    
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">หน่วยงานที่เกิดความเสี่ยง</div>
            <div class="info-value">' . htmlspecialchars($risk['unit'] . ($risk['unit_other'] ? ' (' . $risk['unit_other'] . ')' : '')) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">ประเภทความเสี่ยง</div>
            <div class="info-value">' . htmlspecialchars($risk['risk_type'] . ($risk['risk_type_other'] ? ' (' . $risk['risk_type_other'] . ')' : '')) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">ระดับความรุนแรง</div>
            <div class="info-value">
                <strong>' . $risk['severity'] . '</strong> 
                <span class="severity-text">' . htmlspecialchars($severityText) . '</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">สถานะการดำเนินการ</div>
            <div class="info-value"><span class="' . $statusClass . '">' . htmlspecialchars($statusText) . '</span></div>
        </div>
        <div class="info-row">
            <div class="info-label">วันเวลาที่เกิดเหตุการณ์</div>
            <div class="info-value">' . getThaiDate($risk['event_datetime']) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">วันเวลาที่รายงานเหตุการณ์</div>
            <div class="info-value">' . getThaiDate($risk['report_datetime'] ?? $risk['created_at']) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">ผู้รายงาน</div>
            <div class="info-value">' . htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') . '</div>
        </div>
    </div>
    
    <div class="section-label">📝 รายละเอียดเหตุการณ์</div>
    <div class="detail-box">' . nl2br(htmlspecialchars($risk['detail'] ?? $risk['risk_detail'] ?? '-')) . '</div>
    
    <div class="section-label">🔧 การแก้ไขเบื้องต้น</div>
    <div class="detail-box">' . nl2br(htmlspecialchars($risk['initial_solution'] ?? '-')) . '</div>
    
    <div class="section-label">💡 ปัญหาและข้อเสนอแนะ</div>
    <div class="detail-box">' . nl2br(htmlspecialchars($risk['suggestion'] ?? '-')) . '</div>';

    // สรุปผล
    if ($report) {
        $html .= '
    <hr class="divider">
    <div class="title-section-sm">📊 สรุปผลการรายงาน</div>
    
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">📋 มาตรการแก้ไข</div>
            <div class="info-value">' . nl2br(htmlspecialchars($report['corrective_action'] ?? '-')) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">👤 ผู้รับผิดชอบ</div>
            <div class="info-value">' . htmlspecialchars($report['responsible_person'] ?? '-') . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">📈 การติดตามผล</div>
            <div class="info-value">' . nl2br(htmlspecialchars($report['follow_up'] ?? '-')) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">🎯 ผลที่คาดว่าจะได้รับ</div>
            <div class="info-value">' . nl2br(htmlspecialchars($report['expected_outcome'] ?? '-')) . '</div>
        </div>';
        
        if (!empty($report['report_file'])) {
            $html .= '
        <div class="info-row">
            <div class="info-label">📎 ไฟล์แนบ</div>
            <div class="info-value">' . htmlspecialchars(basename($report['report_file'])) . '</div>
        </div>';
        }
        
        if (!empty($report['created_at'])) {
            $html .= '
        <div class="info-row">
            <div class="info-label">📅 วันที่บันทึกสรุปผล</div>
            <div class="info-value">' . getThaiDate($report['created_at']) . '</div>
        </div>';
        }
        
        $html .= '
    </div>';
    }

    $html .= '
</div>';
}

$html .= '
<div class="footer">พิมพ์เมื่อ ' . getThaiDate(date('Y-m-d H:i:s')) . ' | ระบบจัดการความเสี่ยง ศูนย์อนามัยที่ 8</div>
</body></html>';

// ---------- ตั้งค่า Dompdf ----------
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false);
$options->set('defaultFont', 'Sarabun');
$options->set('chroot', __DIR__);
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);

// ลงทะเบียนฟอนต์ Sarabun
$regularFont = __DIR__ . '/fonts/Sarabun-Regular.ttf';
$boldFont    = __DIR__ . '/fonts/Sarabun-Bold.ttf';

if (file_exists($regularFont)) {
    $fontMetrics = $dompdf->getFontMetrics();
    $fontMetrics->registerFont(
        ['family' => 'Sarabun', 'style' => 'normal', 'weight' => 'normal'],
        $regularFont
    );
}

if (file_exists($boldFont)) {
    $fontMetrics = $dompdf->getFontMetrics();
    $fontMetrics->registerFont(
        ['family' => 'Sarabun', 'style' => 'normal', 'weight' => 'bold'],
        $boldFont
    );
}

// ---------- เรนเดอร์ PDF ----------
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_length()) ob_clean();

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ---------- ส่ง PDF ----------
$dompdf->stream('รายงานความเสี่ยง_' . date('Ymd_His') . '.pdf', ['Attachment' => 0]);
exit;