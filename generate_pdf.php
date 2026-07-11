<?php

/**
 * generate_pdf.php – สร้าง PDF รายงานความเสี่ยง
 * ปรับปรุงประสิทธิภาพและความปลอดภัย
 * รองรับการแสดงผลเต็มหน้ากระดาษ A4
 * ป้องกัน input/textarea เกินขอบกระดาษ
 * ระยะขอบ: ซ้าย 3cm, นอกนั้น 2cm
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตั้งค่า error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/pdf_error.log');

// สร้างโฟลเดอร์ logs ถ้ายังไม่มี
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

if (!isLoggedIn()) {
    http_response_code(403);
    die('กรุณาเข้าสู่ระบบ');
}

// ---------- รับและตรวจสอบพารามิเตอร์ ----------
$ids = filter_input(INPUT_GET, 'ids', FILTER_SANITIZE_STRING)
    ?? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (empty($ids)) {
    http_response_code(400);
    die('ไม่พบข้อมูลที่ต้องการพิมพ์');
}

$idsArray = is_numeric($ids) ? [(int)$ids] : array_filter(explode(',', $ids), 'is_numeric');
$idsArray = array_map('intval', $idsArray);

if (empty($idsArray)) {
    http_response_code(400);
    die('รูปแบบข้อมูลไม่ถูกต้อง');
}

// ---------- ดึงข้อมูลตามสิทธิ์ ----------
try {
    $placeholders = implode(',', array_fill(0, count($idsArray), '?'));
    $sql = "SELECT r.*, u.username 
            FROM risks r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.id IN ($placeholders)";

    $params = $idsArray;

    if (!isAdmin()) {
        $sql .= " AND r.user_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($risks)) {
        http_response_code(404);
        die('ไม่พบข้อมูลหรือไม่มีสิทธิ์');
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    die('เกิดข้อผิดพลาดในการดึงข้อมูล');
}

// ---------- ดึงข้อมูลสรุปผลแบบ batch ----------
$riskIds = array_column($risks, 'id');
$reportData = fetchReportData($pdo, $riskIds);

// ---------- โหลด Dompdf ----------
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ---------- ตั้งค่าคงที่ ----------
define('LOGO_URL', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png');

// ---------- Helper Functions ----------
function fetchReportData(PDO $pdo, array $riskIds): array
{
    if (empty($riskIds)) return [];

    $placeholders = implode(',', array_fill(0, count($riskIds), '?'));
    $sql = "SELECT rr.* 
            FROM risk_reports rr 
            INNER JOIN (
                SELECT risk_id, MAX(created_at) as max_date 
                FROM risk_reports 
                WHERE risk_id IN ($placeholders) 
                GROUP BY risk_id
            ) latest ON rr.risk_id = latest.risk_id AND rr.created_at = latest.max_date";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($riskIds);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($reports as $report) {
            $result[$report['risk_id']] = $report;
        }
        return $result;
    } catch (PDOException $e) {
        error_log('Report fetch error: ' . $e->getMessage());
        return [];
    }
}

/**
 * จัดการข้อความภาษาไทยให้แสดงผลได้เหมาะสมใน PDF
 * ป้องกันข้อความยาวเกินขอบกระดาษ
 */
function formatThaiText(string $text): string
{
    if (empty($text)) return $text;
    
    // แทรก zero-width space ระหว่างคำภาษาไทยที่ยาวเกินไป (ทุก 40 ตัวอักษร)
    $text = preg_replace('/([ก-๙a-zA-Z0-9]{40,})/u', '$1​', $text);
    
    // แทรก zero-width space หลังเครื่องหมายวรรคตอนที่ไม่มีช่องว่างตาม
    $text = preg_replace('/([.,;:!?])([ก-๙a-zA-Z])/u', '$1​$2', $text);
    
    return $text;
}

if (!function_exists('getSeverityText')) {
    function getSeverityText(?string $severity): string
    {
        static $severityMap = [
            'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
            'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
            'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
            'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
            'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
            'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
        ];

        return $severityMap[$severity] ?? $severity ?? '-';
    }
}

if (!function_exists('getStatusText')) {
    function getStatusText(?string $status): string
    {
        return $status ?? 'ยังไม่ดำเนินการ';
    }
}

// ---------- ฟังก์ชันจัดการรูปภาพพื้นหลัง ----------
function checkBackgroundImage(): void
{
    $bgPath = __DIR__ . '/uploads/background-hero.jpg';

    if (!file_exists($bgPath)) {
        $logMessage = date('Y-m-d H:i:s') . " - Warning: Background image not found at {$bgPath}\n";
        file_put_contents(__DIR__ . '/logs/pdf_error.log', $logMessage, FILE_APPEND);
    }
}

function getBackgroundImagePath(): string
{
    $bgPath = __DIR__ . '/uploads/background-hero.jpg';

    if (file_exists($bgPath)) {
        return $bgPath;
    }

    return ''; // ไม่พบไฟล์
}

function getBase64Background(): string
{
    $bgPath = getBackgroundImagePath();

    if (empty($bgPath)) {
        return ''; // ไม่มีรูป
    }

    try {
        $imageData = file_get_contents($bgPath);
        if ($imageData === false) {
            return '';
        }

        $base64 = base64_encode($imageData);
        $mimeType = mime_content_type($bgPath);

        if ($mimeType === false) {
            $mimeType = 'image/jpeg'; // ค่าเริ่มต้น
        }

        return 'data:' . $mimeType . ';base64,' . $base64;
    } catch (Exception $e) {
        error_log('Error encoding background image: ' . $e->getMessage());
        return '';
    }
}

function getBackgroundCss(): string
{
    $base64Bg = getBase64Background();

    if (empty($base64Bg)) {
        // ใช้สีพื้นหลังแทน
        return 'background: #f8f9fa;';
    }

    return "
        background-image: url('{$base64Bg}');
        background-size: 100% 100%;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
    ";
}

// ---------- CSS สำหรับ PDF ----------
function getCssContent(): string
{
    static $css = null;

    if ($css === null) {
        $cssFile = __DIR__ . '/templates/pdf-style.css';
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
        }

        if ($css === false || empty($css)) {
            $bgCss = getBackgroundCss();

            $css = '
            @font-face {
                font-family: "Sarabun";
                src: url("' . __DIR__ . '/fonts/Sarabun-Regular.ttf") format("truetype");
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: "Sarabun";
                src: url("' . __DIR__ . '/fonts/Sarabun-Bold.ttf") format("truetype");
                font-weight: bold;
                font-style: normal;
            }
            
            @page { 
                margin: 2cm 2cm 2cm 3cm;
                size: A4 portrait;
            }
            
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            
            body {
                font-family: "Sarabun", "Garuda", sans-serif;
                font-size: 13px;
                color: #1e293b;
                letter-spacing: 0.02em;
                margin: 0;
                padding: 0;
                line-height: 1.6;
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
                ' . $bgCss . '
            }
            
            .content-wrapper {
                margin: 2cm 2cm 2cm 3cm;
                overflow: hidden;
            }
            
            /* ===== หัวข้อเอกสาร ===== */
            .doc-header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #1a3c6e;
                width: 100%;
                max-width: 100%;
            }
            
            .doc-header .logo {
                max-width: 55px;
                height: auto;
                margin-bottom: 3px;
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
                padding: 10px 15px ;
                border-radius: 4px;
                margin-bottom: 15px;
                border: 1px solid #dce2ea;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }
            
            .objective-box .obj-label {
                font-weight: 700;
                font-size: 14px;
                color: #1a3c6e;
                display: block;
                margin-bottom: 5px;
            }
            
            .objective-box ol {
                margin: 0;
                padding-left: 20px;
                font-size: 13px;
                color: #2a3a5a;
                line-height: 1.7;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            .objective-box ol li {
                margin-bottom: 3px;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            /* ===== ฟิลด์ฟอร์ม ===== */
            .form-field {
                margin-bottom: 8px;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }
            
            .form-field .field-label {
                font-weight: 600;
                font-size: 13px;
                color: #1a2a4a;
                display: block;
                margin-bottom: 2px;
                width: 100%;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            .form-field .field-label .label-en {
                font-weight: 400;
                color: #8a9aa8;
                font-size: 10px;
                margin-left: 4px;
            }
            
            .form-field .field-value {
                width: 100%;
                max-width: 100%;
                padding: 6px 10px;
                border: 1px solid #dce2ea;
                border-radius: 4px;
                font-size: 13px;
                font-family: "Sarabun", sans-serif;
                background: #fafbfc;
                color: #1a2a4a;
                min-height: 28px;
                line-height: 1.5;
                
                /* จัดการข้อความไม่ให้ล้น */
                white-space: pre-wrap;
                word-wrap: break-word;
                overflow-wrap: break-word;
                word-break: break-word;
                hyphens: auto;
                overflow: hidden;
            }
            
            .form-field .field-value.readonly {
                background: #f0f2f5;
                color: #4a5a6a;
            }
            
            .form-field .field-value.textarea {
                min-height: 50px;
                padding: 6px 10px;
                white-space: pre-wrap;
                
                /* จัดการข้อความยาวใน textarea */
                word-wrap: break-word;
                overflow-wrap: break-word;
                word-break: break-word;
                overflow: hidden;
                max-height: none;
            }
            
            .form-field .field-hint {
                font-size: 9px;
                color: #8a9aa8;
                margin-top: 2px;
                width: 100%;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            /* ===== แถว 2 คอลัมน์ ===== */
            .form-row-2col {
                display: table;
                width: 100%;
                max-width: 100%;
                border-collapse: collapse;
                margin-bottom: 8px;
                table-layout: fixed;
            }
            
            .form-row-2col .col {
                display: table-cell;
                width: 50%;
                padding-right: 8px;
                vertical-align: top;
                max-width: 50%;
                overflow: hidden;
            }
            
            .form-row-2col .col:last-child {
                padding-right: 0;
                padding-left: 8px;
            }
            
            .form-row-2col .col .field-value {
                word-break: break-word;
                overflow-wrap: break-word;
                white-space: pre-wrap;
                max-width: 100%;
            }
            
            /* ===== ข้อมูลความเสี่ยง ===== */
            .risk-info-bar {
                background: #f7faff;
                border: 1px solid #dce2ea;
                border-radius: 4px;
                padding: 10px 12px;
                margin-bottom: 10px;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }
            
            .risk-info-bar .info-row {
                padding: 3px 0;
                width: 100%;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            .risk-info-bar .label {
                display: inline-block;
                min-width: 120px;
                color: #7a8a9a;
                font-weight: 500;
            }
            
            .risk-info-bar .value {
                color: #1a2a4a;
                font-weight: 600;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            /* ===== ตัวแบ่ง ===== */
            .divider-solid {
                border: none;
                border-top: 1.5px solid #e2e8f0;
                margin: 8px 0;
                width: 100%;
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
                max-width: 100%;
                word-wrap: break-word;
            }
            
            /* ===== Footer ===== */
            .footer { 
                position: fixed; 
                bottom: 0; 
                left: 3cm; 
                right: 2cm; 
                text-align: center; 
                font-size: 9px; 
                color: #94a3b8; 
                border-top: 1px solid #e2e8f0; 
                padding: 6px 0; 
                background: rgba(255,255,255,0.9);
                border-radius: 3px;
                max-width: 100%;
                overflow: hidden;
            }
            
            /* ===== ตัวแบ่งหน้า ===== */
            .page-break {
                page-break-after: always;
            }
            
            /* ===== คลาสสำหรับภาษาไทย ===== */
            .thai-text {
                font-family: "Sarabun", "Garuda", sans-serif;
                letter-spacing: 0.03em;
                word-spacing: 0.05em;
                width: 100%;
                max-width: 100%;
            }
            
            .thai-text .field-value {
                word-break: break-all;
                overflow-wrap: anywhere;
            }
            
            /* ===== หมายเลขความเสี่ยง ===== */
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
                max-width: 100%;
                word-wrap: break-word;
            }
            
            /* ===== รายการความเสี่ยง ===== */
            .risk-item {
                margin-bottom: 15px;
                padding-bottom: 10px;
                clear: both;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }
            
            .risk-item:not(:last-child) {
                border-bottom: 1.5px solid #e2e8f0;
            }

            /* ===== Clearfix ===== */
            .clearfix::after {
                content: "";
                clear: both;
                display: table;
            }
            
            /* ===== ป้องกันข้อความล้นทุก element ===== */
            .field-value * {
                max-width: 100% !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
            }
            
            p, div, span, li, td, th {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            ';
        }
    }

    return $css;
}

// ---------- สร้าง HTML ----------
function buildHtmlContent(array $risks, array $reportData, string $css): string
{
    $html = '<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>' . $css . '</style>
</head>
<body class="thai-text">
<div class="content-wrapper">';

    // Header
    $html .= buildHeader();

    // Objective
    $html .= buildObjective();

    // Risk items
    foreach ($risks as $index => $risk) {
        $html .= buildRiskItem($risk, $index, $reportData[$risk['id']] ?? null);

        if ($index < count($risks) - 1) {
            $html .= '<div class="page-break"></div>';
        }
    }

    $html .= '</div>';
    $html .= buildFooter();
    $html .= '</body></html>';

    return $html;
}

function buildHeader(): string
{
    $logoUrl = LOGO_URL;

    return '<div class="doc-header">
    <img src="' . $logoUrl . '" alt="ศูนย์อนามัยที่ 8" class="logo">
    <div class="main-title">RISK MANAGEMENT</div>
    <div class="sub-title">การจัดการความเสี่ยง</div>
    <div class="sub-title-en">รายงานอุบัติการณ์ความเสี่ยงในศูนย์อนามัยที่ 8 อุดรธานี</div>
</div>';
}

function buildObjective(): string
{
    return '<div class="objective-box">
    <span class="obj-label">📌 วัตถุประสงค์</span>
    <ol>
        <li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li>
        <li>เพื่อป้องกัน ลดความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
        <li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ และช่วยให้องค์กรพัฒนาไปในแนวทางเดียวกัน</li>
    </ol>
</div>';
}

function buildRiskItem(array $risk, int $index, ?array $report): string
{
    $html = '<div class="risk-item">';

    // Risk number
    $html .= sprintf(
        '<div class="risk-number">ความเสี่ยงที่ %d | ID: %d</div><div class="clearfix"></div>',
        $index + 1,
        $risk['id']
    );

    // Basic fields
    $html .= buildField(
        'รหัสประจำตัว (ผู้รายงานความเสี่ยง)',
        'ID / Reporter',
        htmlspecialchars($risk['username'] ?? $_SESSION['username'] ?? 'ไม่ระบุ'),
        'รหัสประจำตัวของผู้ที่รายงานความเสี่ยงนี้'
    );

    $html .= buildField(
        'หน่วยงานที่เกิดความเสี่ยง (ผู้ถูกรายงานความเสี่ยง)',
        'Unit / Reported To',
        htmlspecialchars($risk['unit'] . ($risk['unit_other'] ? " ({$risk['unit_other']})" : '')),
        'หน่วยงานที่เกิดเหตุการณ์ความเสี่ยง'
    );

    $html .= buildField(
        'ประเภทของความเสี่ยง',
        'Risk Type',
        htmlspecialchars($risk['risk_type'] . ($risk['risk_type_other'] ? " ({$risk['risk_type_other']})" : '')),
        'ประเภทของความเสี่ยงที่เกิดขึ้น'
    );

    // Date fields
    $html .= buildTwoColumnField(
        'วันเวลาที่เกิดเหตุการณ์ (จริง)',
        'Event Date/Time',
        getThaiDate($risk['event_datetime']),
        'วันเวลาที่รายงานเหตุการณ์',
        'Report Date/Time',
        getThaiDate($risk['report_datetime'] ?? $risk['created_at'])
    );

    // Detail fields
    $html .= buildTextField(
        'รายละเอียดเหตุการณ์',
        'Event Details',
        $risk['detail'] ?? $risk['risk_detail'] ?? 'ไม่มีรายละเอียด',
        'รายละเอียดของเหตุการณ์ความเสี่ยงที่เกิดขึ้น'
    );

    $html .= buildTextField(
        'การแก้ไขเบื้องต้น',
        'Initial Action',
        $risk['initial_solution'] ?? 'ไม่มีข้อมูล',
        'การดำเนินการแก้ไขเบื้องต้นเมื่อเกิดเหตุการณ์'
    );

    $html .= buildTextField(
        'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข',
        'Problems & Suggestions',
        $report['corrective_action'] ?? $risk['suggestion'] ?? 'ไม่มีข้อมูล',
        'ปัญหาที่พบและข้อเสนอแนะสำหรับการปรับปรุงแก้ไข'
    );

    // Risk info bar
    $html .= buildRiskInfoBar($risk);

    // Report summary
    if ($report) {
        $html .= buildReportSummary($report);
    }

    $html .= '</div>';
    return $html;
}

function buildField(string $label, string $labelEn, string $value, string $hint = ''): string
{
    $formattedValue = formatThaiText($value);
    
    $html = '<div class="form-field">';
    $html .= '<label class="field-label">' . htmlspecialchars($label) . ' <span class="label-en">(' . htmlspecialchars($labelEn) . ')</span></label>';
    $html .= '<div class="field-value readonly">' . $formattedValue . '</div>';
    if (!empty($hint)) {
        $html .= '<div class="field-hint">' . htmlspecialchars($hint) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function buildTextField(string $label, string $labelEn, string $value, string $hint = ''): string
{
    $formattedValue = !empty($value) ? nl2br(htmlspecialchars(formatThaiText($value))) : '-';

    $html = '<div class="form-field">';
    $html .= '<label class="field-label">' . htmlspecialchars($label) . ' <span class="label-en">(' . htmlspecialchars($labelEn) . ')</span></label>';
    $html .= '<div class="field-value textarea readonly">' . $formattedValue . '</div>';
    if (!empty($hint)) {
        $html .= '<div class="field-hint">' . htmlspecialchars($hint) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function buildTwoColumnField(
    string $label1,
    string $labelEn1,
    string $value1,
    string $label2,
    string $labelEn2,
    string $value2
): string {
    $formattedValue1 = formatThaiText($value1);
    $formattedValue2 = formatThaiText($value2);
    
    $html = '<div class="form-row-2col">';

    $html .= '<div class="col">';
    $html .= '<div class="form-field">';
    $html .= '<label class="field-label">' . htmlspecialchars($label1) . ' <span class="label-en">(' . htmlspecialchars($labelEn1) . ')</span></label>';
    $html .= '<div class="field-value readonly">' . $formattedValue1 . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="col">';
    $html .= '<div class="form-field">';
    $html .= '<label class="field-label">' . htmlspecialchars($label2) . ' <span class="label-en">(' . htmlspecialchars($labelEn2) . ')</span></label>';
    $html .= '<div class="field-value readonly">' . $formattedValue2 . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

function buildRiskInfoBar(array $risk): string
{
    $severityText = getSeverityText($risk['severity']);
    $statusText = getStatusText($risk['status'] ?? 'ยังไม่ดำเนินการ');

    $severity = htmlspecialchars($risk['severity'] ?? '-');
    $status = htmlspecialchars($statusText);
    $severityTextHtml = htmlspecialchars(formatThaiText($severityText));

    return '<div class="risk-info-bar">
    <div class="info-row">
        <span class="label">⚠️ ระดับความเสี่ยง</span>
        <span class="value">' . $severity . ' - ' . $severityTextHtml . '</span>
    </div>
    <div class="info-row">
        <span class="label">📌 สถานะ</span>
        <span class="value">' . $status . '</span>
    </div>
</div>';
}

function buildReportSummary(array $report): string
{
    $html = '<hr class="divider-solid">';
    $html .= '<div class="summary-title">📊 สรุปผลการรายงาน</div>';

    $html .= buildTextField(
        'มาตรการแก้ไข',
        'Corrective Action',
        $report['corrective_action'] ?? '-'
    );

    $html .= buildField(
        'ผู้รับผิดชอบ',
        'Responsible Person',
        htmlspecialchars($report['responsible_person'] ?? '-')
    );

    $html .= buildTextField(
        'การติดตามผล',
        'Follow-up',
        $report['follow_up'] ?? '-'
    );

    $html .= buildTextField(
        'ผลที่คาดว่าจะได้รับ',
        'Expected Outcome',
        $report['expected_outcome'] ?? '-'
    );

    if (!empty($report['report_file'])) {
        $html .= buildField(
            'ไฟล์แนบ',
            'Attachment',
            '📎 ' . htmlspecialchars(basename($report['report_file']))
        );
    }

    if (!empty($report['created_at'])) {
        $html .= buildField(
            'วันที่บันทึกสรุปผล',
            'Report Date',
            getThaiDate($report['created_at'])
        );
    }

    return $html;
}

function buildFooter(): string
{
    $currentDate = getThaiDate(date('Y-m-d H:i:s'));

    return '<div class="footer">
    พิมพ์เมื่อ ' . $currentDate . ' | ระบบจัดการความเสี่ยง ศูนย์อนามัยที่ 8 อุดรธานี
</div>';
}

function initializeDompdf(): Dompdf
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', 'Sarabun');
    $options->set('chroot', __DIR__);
    $options->set('isFontSubsettingEnabled', true);
    $options->set('dpi', 96);
    $options->set('debugPng', false);
    $options->set('debugKeepTemp', false);
    $options->set('debugCss', false);
    $options->set('debugLayout', false);
    $options->set('debugLayoutLines', false);
    $options->set('debugLayoutBlocks', false);
    $options->set('debugLayoutInline', false);
    $options->set('debugLayoutPaddingBox', false);
    
    // เพิ่ม options สำหรับจัดการการตัดคำ
    $options->set('defaultMediaType', 'print');
    $options->set('isJavascriptEnabled', false);

    $dompdf = new Dompdf($options);

    registerFonts($dompdf);
    registerImages($dompdf);

    return $dompdf;
}

function registerFonts(Dompdf $dompdf): void
{
    $fontMetrics = $dompdf->getFontMetrics();
    $fonts = [
        ['family' => 'Sarabun', 'style' => 'normal', 'weight' => 'normal', 'file' => 'Sarabun-Regular.ttf'],
        ['family' => 'Sarabun', 'style' => 'normal', 'weight' => 'bold', 'file' => 'Sarabun-Bold.ttf'],
    ];

    foreach ($fonts as $font) {
        $fontPath = __DIR__ . '/fonts/' . $font['file'];
        if (file_exists($fontPath)) {
            try {
                $fontMetrics->registerFont(
                    ['family' => $font['family'], 'style' => $font['style'], 'weight' => $font['weight']],
                    $fontPath
                );
            } catch (Exception $e) {
                error_log('Font registration error: ' . $e->getMessage());
            }
        } else {
            error_log('Font file not found: ' . $fontPath);
        }
    }
}

function registerImages(Dompdf $dompdf): void
{
    // ลงทะเบียนโฟลเดอร์ uploads ให้ Dompdf
    $uploadPath = realpath(__DIR__ . '/uploads');
    if ($uploadPath) {
        try {
            $dompdf->getOptions()->set('chroot', $uploadPath);
        } catch (Exception $e) {
            error_log('Image registration error: ' . $e->getMessage());
        }
    }
}

function generatePdf(Dompdf $dompdf, string $html): void
{
    // ล้าง output buffer ทั้งหมด
    while (ob_get_level()) {
        ob_end_clean();
    }

    try {
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'รายงานความเสี่ยง_' . date('Ymd_His') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => 0]);
        exit;
    } catch (Exception $e) {
        error_log('PDF generation error: ' . $e->getMessage());
        throw $e;
    }
}

// ---------- ตรวจสอบไฟล์รูปภาพ ----------
checkBackgroundImage();

// ---------- Execute ----------
try {
    $cssContent = getCssContent();
    $html = buildHtmlContent($risks, $reportData, $cssContent);
    $dompdf = initializeDompdf();
    generatePdf($dompdf, $html);
} catch (Exception $e) {
    error_log('PDF Generation Error: ' . $e->getMessage());
    http_response_code(500);
    die('เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage());
}