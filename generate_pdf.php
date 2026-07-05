<?php

/**
 * generate_pdf.php – สร้าง PDF รายงานความเสี่ยง (รูปแบบตามภาพ A4.jpg)
 * - ใช้ฟอนต์ Sarabun
 * - รองรับภาษาไทย 100%
 * - มีหัวข้อ # RISK MANAGEMENT และวัตถุประสงค์ 3 ข้อ
 * - แสดงฟิลด์ครบตามภาพ
 * - จัดรูปแบบสวยงาม เหมือนเอกสาร
 * - รองรับการตัดบรรทัดอัตโนมัติ
 * - ไม่มีกรอบเอกสาร
 * - มีพื้นหลังรูปภาพ background-hero.jpg
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
    function getThaiDate($datetime)
    {
        if (empty($datetime)) return '-';
        $thai_months = [
            'มกราคม',
            'กุมภาพันธ์',
            'มีนาคม',
            'เมษายน',
            'พฤษภาคม',
            'มิถุนายน',
            'กรกฎาคม',
            'สิงหาคม',
            'กันยายน',
            'ตุลาคม',
            'พฤศจิกายน',
            'ธันวาคม'
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

// ---------- ตรวจสอบฟังก์ชัน getSeverityText() ----------
if (!function_exists('getSeverityText')) {
    function getSeverityText($severity)
    {
        $map = [
            'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
            'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
            'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
            'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
            'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
            'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
        ];
        return $map[$severity] ?? $severity;
    }
}

// ---------- ตรวจสอบฟังก์ชัน getStatusBadge() ----------
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status)
    {
        $colors = [
            'ยังไม่ดำเนินการ' => '#f59e0b',
            'กำลังดำเนินการ' => '#3b82f6',
            'ดำเนินการแล้ว' => '#22c55e',
            'ยุติ' => '#6b7280'
        ];
        return $colors[$status] ?? '#6b7280';
    }
}

// ---------- สร้าง HTML (รูปแบบเหมือนภาพ A4.jpg) ----------
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
        margin: 2cm 1.8cm;
        size: A4 portrait;
    }
    
    body {
        font-family: "Sarabun", "Garuda", sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #1e293b;
        background-image: url("uploads/background-hero.jpg");
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        letter-spacing: 0.02em;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }
    
    /* ===== เนื้อหาหลัก ===== */
    .content-wrapper {
        padding: 20px 25px 15px 25px;
        border-radius: 8px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.06);
        min-height: 90vh;
        width: 90%;
        box-sizing: border-box;
    }
    
    /* ===== หัวข้อเอกสาร ===== */
    .doc-header {
        text-align: center;
        margin-bottom: 15px;
        padding-bottom: 12px;
        border-bottom: 2px solid #1a3c6e;
    }
    
    .doc-header .logo {
        max-width: 70px;
        height: auto;
        margin-bottom: 4px;
    }
    
    .doc-header .main-title {
        font-size: 22px;
        font-weight: 700;
        color: #1a3c6e;
        letter-spacing: 3px;
        margin: 2px 0;
    }
    
    .doc-header .sub-title {
        font-size: 17px;
        font-weight: 600;
        color: #2a4a7a;
        margin: 2px 0;
    }
    
    .doc-header .sub-title-en {
        font-size: 12px;
        font-weight: 400;
        color: #6a8aaa;
        letter-spacing: 0.5px;
        margin: 0;
    }
    
    /* ===== วัตถุประสงค์ ===== */
    .objective-box {
        background: #f7faff;
        border-left: 4px solid #1a3c6e;
        padding: 10px 15px 8px;
        margin-bottom: 15px;
        border-radius: 0 4px 4px 0;
    }
    
    .objective-box .obj-label {
        font-weight: 700;
        font-size: 13px;
        color: #1a3c6e;
        display: block;
        margin-bottom: 2px;
    }
    
    .objective-box ol {
        margin: 0;
        padding-left: 20px;
        font-size: 12px;
        color: #2a3a5a;
        line-height: 1.7;
    }
    
    .objective-box ol li {
        margin-bottom: 1px;
    }
    
    /* ===== ฟิลด์ฟอร์ม ===== */
    .form-field {
        margin-bottom: 10px;
    }
    
    .form-field .field-label {
        font-weight: 600;
        font-size: 13px;
        color: #1a2a4a;
        display: block;
        margin-bottom: 2px;
    }
    
    .form-field .field-label .label-en {
        font-weight: 400;
        color: #8a9aa8;
        font-size: 11px;
        margin-left: 5px;
    }
    
    .form-field .field-value {
        width: 100%;
        padding: 5px 10px;
        border: 1px solid #dce2ea;
        border-radius: 4px;
        font-size: 12px;
        font-family: "Sarabun", sans-serif;
        background: #fafbfc;
        color: #1a2a4a;
        box-sizing: border-box;
        min-height: 28px;
        line-height: 1.5;
        word-wrap: break-word;
        word-break: break-word;
        white-space: pre-wrap;
        overflow-wrap: break-word;
    }
    
    .form-field .field-value.readonly {
        background: #f0f2f5;
        color: #4a5a6a;
    }
    
    .form-field .field-value.textarea {
        min-height: 50px;
        padding: 6px 10px;
    }
    
    .form-field .field-hint {
        font-size: 10px;
        color: #8a9aa8;
        margin-top: 1px;
    }
    
    /* ===== แถว 2 คอลัมน์ ===== */
    .form-row-2col {
        display: table;
        width: 90%;
        border-collapse: collapse;
    }
    
    .form-row-2col .col {
        display: table-cell;
        width: 50%;
        padding-right: 12px;
    }
    
    .form-row-2col .col:last-child {
        padding-right: 0;
        padding-left: 12px;
    }
    
    /* ===== ข้อมูลความเสี่ยง (Bar) ===== */
    .risk-info-bar {
        background: #f7faff;
        border: 1px solid #dce2ea;
        border-radius: 4px;
        padding: 8px 15px;
        margin-bottom: 12px;
        display: table;
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    .risk-info-bar .info-item {
        display: table-cell;
        padding: 3px 10px 3px 0;
        white-space: normal;
        word-wrap: break-word;
        word-break: break-word;
    }
    
    .risk-info-bar .info-item .label {
        color: #7a8a9a;
        font-weight: 500;
        white-space: nowrap;
    }
    
    .risk-info-bar .info-item .value {
        color: #1a2a4a;
        font-weight: 600;
        word-wrap: break-word;
        word-break: break-word;
        white-space: normal;
    }
    
    .risk-info-bar .badge-status {
        display: inline-block;
        padding: 1px 12px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 600;
        color: #ffffff;
        white-space: nowrap;
    }
    
    /* ===== ตัวแบ่ง ===== */
    .divider-solid {
        border: none;
        border-top: 1.5px solid #e2e8f0;
        margin: 10px 0;
    }
    
    /* ===== ส่วนสรุปผล ===== */
    .summary-title {
        font-weight: 700;
        font-size: 14px;
        color: #1a3c6e;
        margin: 8px 0 6px 0;
        padding: 4px 12px;
        background: #f7faff;
        border-radius: 4px;
        display: inline-block;
        border: 1px solid #dce2ea;
    }
    
    /* ===== Footer ===== */
    .footer { 
        position: fixed; 
        bottom: 0.8cm; 
        left: 2cm; 
        right: 2cm; 
        text-align: center; 
        font-size: 9px; 
        color: #94a3b8; 
        border-top: 1px solid #e2e8f0; 
        padding: 6px 0; 
        background: rgba(255,255,255,0.7);
        border-radius: 3px;
    }
    
    .page-break {
        page-break-after: always;
    }
    
    .thai-text {
        font-family: "Sarabun", "Garuda", sans-serif;
        letter-spacing: 0.03em;
        word-spacing: 0.05em;
    }
    
    .risk-number {
        font-size: 10px;
        color: #94a3b8;
        margin-bottom: 6px;
        text-align: right;
        padding: 3px 8px;
        background: #f8fafc;
        border-radius: 3px;
        display: inline-block;
        float: right;
        border: 1px solid #eef2f7;
    }
    
    .risk-item {
        margin-bottom: 15px;
        padding-bottom: 12px;
        clear: both;
    }
    
    .risk-item:not(:last-child) {
        border-bottom: 1.5px solid #e2e8f0;
    }
</style>
</head>
<body>

<div class="thai-text">

<!-- ===== เนื้อหาหลัก ===== -->
<div class="content-wrapper">

<!-- ===== หัวข้อเอกสาร ===== -->
<div class="doc-header">
    <img src="' . $logoUrl . '" alt="ศูนย์อนามัยที่ 8" class="logo">
    <div class="main-title">RISK MANAGEMENT</div>
    <div class="sub-title">การจัดการความเสี่ยง</div>
    <div class="sub-title-en">รายงานอุบัติการณ์ความเสี่ยงในศูนย์อนามัยที่ 8 อุดรธานี</div>
</div>

<!-- ===== วัตถุประสงค์ ===== -->
<div class="objective-box">
    <span class="obj-label">📌 วัตถุประสงค์</span>
    <ol>
        <li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li>
        <li>เพื่อป้องกัน ลดความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
        <li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ และช่วยให้องค์กรพัฒนาไปในแนวทางเดียวกัน</li>
    </ol>
</div>';

// ============================================================
// ========== ข้อมูลความเสี่ยง ==========
// ============================================================
foreach ($risks as $index => $risk) {
    $severityText = getSeverityText($risk['severity']);
    $statusText = $risk['status'] ?? 'ยังไม่ดำเนินการ';
    $statusColor = getStatusBadge($statusText);
    $report = isset($reportData[$risk['id']]) ? $reportData[$risk['id']] : null;

    $html .= '
<div class="risk-item">
    <div class="risk-number">ความเสี่ยงที่ ' . ($index + 1) . ' | ID: ' . $risk['id'] . '</div>
    <div style="clear:both;"></div>

    <!-- 1. รหัสประจำตัว (ผู้รายงานความเสี่ยง) -->
    <div class="form-field">
        <label class="field-label">รหัสประจำตัว (ผู้รายงานความเสี่ยง) <span class="label-en">(ID / Reporter)</span></label>
        <div class="field-value readonly">' . htmlspecialchars($risk['username'] ?? $_SESSION['username'] ?? 'ไม่ระบุ') . '</div>
        <div class="field-hint">รหัสประจำตัวของผู้ที่รายงานความเสี่ยงนี้</div>
    </div>

    <!-- 2. หน่วยงานที่เกิดความเสี่ยง (ผู้ถูกรายงานความเสี่ยง) -->
    <div class="form-field">
        <label class="field-label">หน่วยงานที่เกิดความเสี่ยง (ผู้ถูกรายงานความเสี่ยง) <span class="label-en">(Unit / Reported To)</span></label>
        <div class="field-value readonly">' . htmlspecialchars($risk['unit'] . ($risk['unit_other'] ? ' (' . $risk['unit_other'] . ')' : '')) . '</div>
        <div class="field-hint">หน่วยงานที่เกิดเหตุการณ์ความเสี่ยง</div>
    </div>

    <!-- 3. ประเภทของความเสี่ยง -->
    <div class="form-field">
        <label class="field-label">ประเภทของความเสี่ยง <span class="label-en">(Risk Type)</span></label>
        <div class="field-value readonly">' . htmlspecialchars($risk['risk_type'] . ($risk['risk_type_other'] ? ' (' . $risk['risk_type_other'] . ')' : '')) . '</div>
        <div class="field-hint">ประเภทของความเสี่ยงที่เกิดขึ้น</div>
    </div>

    <!-- 4. วันเวลาที่เกิดเหตุการณ์ (จริง) / วันเวลาที่รายงานเหตุการณ์ -->
    <div class="form-row-2col">
        <div class="col">
            <div class="form-field">
                <label class="field-label">วันเวลาที่เกิดเหตุการณ์ (จริง) <span class="label-en">(Event Date/Time)</span></label>
                <div class="field-value readonly">' . getThaiDate($risk['event_datetime']) . '</div>
            </div>
        </div>
        <div class="col">
            <div class="form-field">
                <label class="field-label">วันเวลาที่รายงานเหตุการณ์ <span class="label-en">(Report Date/Time)</span></label>
                <div class="field-value readonly">' . getThaiDate($risk['report_datetime'] ?? $risk['created_at']) . '</div>
            </div>
        </div>
    </div>

    <!-- 5. รายละเอียดเหตุการณ์ -->
    <div class="form-field">
        <label class="field-label">รายละเอียดเหตุการณ์ <span class="label-en">(Event Details)</span></label>
        <div class="field-value textarea readonly">' . nl2br(htmlspecialchars($risk['detail'] ?? $risk['risk_detail'] ?? 'ไม่มีรายละเอียด')) . '</div>
        <div class="field-hint">รายละเอียดของเหตุการณ์ความเสี่ยงที่เกิดขึ้น</div>
    </div>

    <!-- 6. การแก้ไขเบื้องต้น -->
    <div class="form-field">
        <label class="field-label">การแก้ไขเบื้องต้น <span class="label-en">(Initial Action)</span></label>
        <div class="field-value textarea readonly">' . nl2br(htmlspecialchars($risk['initial_solution'] ?? 'ไม่มีข้อมูล')) . '</div>
        <div class="field-hint">การดำเนินการแก้ไขเบื้องต้นเมื่อเกิดเหตุการณ์</div>
    </div>

    <!-- 7. ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข -->
    <div class="form-field">
        <label class="field-label">ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข <span class="label-en">(Problems & Suggestions)</span></label>
        <div class="field-value textarea readonly">' . nl2br(htmlspecialchars($report['corrective_action'] ?? $risk['suggestion'] ?? 'ไม่มีข้อมูล')) . '</div>
        <div class="field-hint">ปัญหาที่พบและข้อเสนอแนะสำหรับการปรับปรุงแก้ไข</div>
    </div>

    <!-- ข้อมูลเพิ่มเติม: ระดับความเสี่ยง + สถานะ -->
    <div class="risk-info-bar">
        <div class="info-item">
            <span class="label">⚠️ ระดับความเสี่ยง:</span>
            <span class="value">' . htmlspecialchars($risk['severity']) . ' - ' . htmlspecialchars($severityText) . '</span>
        </div>
        <div class="info-item">
            <span class="label">📌 สถานะ:</span>
            <span class="badge-status" style="background-color: ' . $statusColor . ';">' . htmlspecialchars($statusText) . '</span>
        </div>
    </div>';

    // ===== สรุปผลการรายงาน (ถ้ามี) =====
    if ($report) {
        $html .= '
    <hr class="divider-solid">
    <div class="summary-title">📊 สรุปผลการรายงาน</div>

    <!-- มาตรการแก้ไข -->
    <div class="form-field">
        <label class="field-label">มาตรการแก้ไข <span class="label-en">(Corrective Action)</span></label>
        <div class="field-value textarea readonly">' . nl2br(htmlspecialchars($report['corrective_action'] ?? '-')) . '</div>
    </div>

    <!-- ผู้รับผิดชอบ -->
    <div class="form-field">
        <label class="field-label">ผู้รับผิดชอบ <span class="label-en">(Responsible Person)</span></label>
        <div class="field-value readonly">' . htmlspecialchars($report['responsible_person'] ?? '-') . '</div>
    </div>

    <!-- การติดตามผล -->
    <div class="form-field">
        <label class="field-label">การติดตามผล <span class="label-en">(Follow-up)</span></label>
        <div class="field-value textarea readonly">' . nl2br(htmlspecialchars($report['follow_up'] ?? '-')) . '</div>
    </div>

    <!-- ผลที่คาดว่าจะได้รับ -->
    <div class="form-field">
        <label class="field-label">ผลที่คาดว่าจะได้รับ <span class="label-en">(Expected Outcome)</span></label>
        <div class="field-value textarea readonly">' . nl2br(htmlspecialchars($report['expected_outcome'] ?? '-')) . '</div>
    </div>';

        // ไฟล์แนบ
        if (!empty($report['report_file'])) {
            $html .= '
    <div class="form-field">
        <label class="field-label">ไฟล์แนบ <span class="label-en">(Attachment)</span></label>
        <div class="field-value readonly">📎 ' . htmlspecialchars(basename($report['report_file'])) . '</div>
    </div>';
        }

        // วันที่บันทึก
        if (!empty($report['created_at'])) {
            $html .= '
    <div class="form-field">
        <label class="field-label">วันที่บันทึกสรุปผล <span class="label-en">(Report Date)</span></label>
        <div class="field-value readonly">' . getThaiDate($report['created_at']) . '</div>
    </div>';
        }
    }

    $html .= '
</div>';

    // เพิ่ม page break ถ้ายังมีข้อมูลถัดไป
    if ($index < count($risks) - 1) {
        $html .= '<div class="page-break"></div>';
    }
}

$html .= '
</div>
<!-- ===== จบ content-wrapper ===== -->

<!-- ===== Footer ===== -->
<div class="footer">
    พิมพ์เมื่อ ' . getThaiDate(date('Y-m-d H:i:s')) . ' | ระบบจัดการความเสี่ยง ศูนย์อนามัยที่ 8 อุดรธานี
</div>

</div>
</body>
</html>';

// ============================================================
// ========== ตั้งค่า Dompdf ==========
// ============================================================
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
