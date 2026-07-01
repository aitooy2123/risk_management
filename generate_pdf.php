<?php
/**
 * generate_pdf.php – สร้าง PDF รายงานความเสี่ยง (ภาษาไทยสมบูรณ์)
 *
 * - ใช้ฟอนต์ Sarabun จาก Google Fonts (ในเครื่อง)
 * - ไม่ต้องใช้อินเทอร์เน็ต
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

// ---------- โหลด Dompdf ----------
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// ---------- สร้าง HTML ----------
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 1.8cm 1.5cm; }
    body {
        font-family: "Sarabun", sans-serif;   /* ใช้ชื่อฟอนต์ที่ลงทะเบียน */
        font-size: 14px;
        line-height: 1.6;
        color: #1e3a8a;
        background: #fff;
        position: relative;
    }
    .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 80px;
        color: rgba(30, 58, 138, 0.06);
        z-index: -1;
        white-space: nowrap;
        font-weight: bold;
        pointer-events: none;
    }
    .header { text-align: center; border-bottom: 2px solid #3b82f6; padding-bottom: 8px; margin-bottom: 20px; }
    .header h1 { font-size: 22px; margin: 0; color: #1e3a8a; }
    .header .sub { font-size: 16px; color: #2563eb; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #dbeafe; padding-top: 6px; }
    .title-section { font-weight: bold; font-size: 18px; color: #1e3a8a; margin-top: 20px; margin-bottom: 10px; border-left: 5px solid #3b82f6; padding-left: 10px; }
    .objectives { background: #eff6ff; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #3b82f6; }
    .objectives ul { margin: 6px 0 0 20px; padding: 0; }
    .info-grid { display: table; width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .info-row { display: table-row; }
    .info-label { display: table-cell; width: 30%; font-weight: bold; padding: 6px 10px 6px 0; border-bottom: 1px dashed #dbeafe; color: #1e3a8a; }
    .info-value { display: table-cell; padding: 6px 0; border-bottom: 1px dashed #dbeafe; }
    .detail-box { background: #f8faff; padding: 12px 16px; border-left: 4px solid #3b82f6; margin: 10px 0; border-radius: 4px; }
    .badge { display: inline-block; padding: 2px 12px; border-radius: 20px; font-weight: bold; font-size: 14px; }
    .badge-A { background: #dbeafe; color: #1e40af; }
    .badge-B { background: #bfdbfe; color: #1e3a8a; }
    .badge-C { background: #93c5fd; color: #1e3a8a; }
    .badge-D { background: #60a5fa; color: #ffffff; }
    .badge-F { background: #3b82f6; color: #ffffff; }
    .badge-E { background: #1e40af; color: #ffffff; }
</style>
</head>
<body>';

$severityMap = [
    'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
    'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
    'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
    'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
    'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
    'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
];

foreach ($risks as $index => $risk) {
    if ($index > 0) $html .= '<div style="page-break-before: always;"></div>';
    $severityText = $severityMap[$risk['severity']] ?? $risk['severity'];
    $html .= '
    <div class="watermark">ศูนย์อนามัยที่ 8</div>
    <div class="header"><h1>รายงานอุบัติการณ์ความเสี่ยง</h1><div class="sub">ศูนย์อนามัยที่ 8 อุดรธานี</div></div>
    <div class="objectives"><strong>วัตถุประสงค์</strong><ul><li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li><li>เพื่อป้องกัน ลดการความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li><li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ และช่วยให้องค์กรพัฒนาเป็นไปในแนวทางเดียวกัน</li></ul></div>
    <div class="title-section">รายละเอียดความเสี่ยง</div>
    <div class="info-grid">
        <div class="info-row"><div class="info-label">หน่วยงานที่เกิดความเสี่ยง</div><div class="info-value">'.htmlspecialchars($risk['unit'].($risk['unit_other'] ? ' ('.$risk['unit_other'].')' : '')).'</div></div>
        <div class="info-row"><div class="info-label">ประเภทความเสี่ยง</div><div class="info-value">'.htmlspecialchars($risk['risk_type'].($risk['risk_type_other'] ? ' ('.$risk['risk_type_other'].')' : '')).'</div></div>
        <div class="info-row"><div class="info-label">ระดับความรุนแรง</div><div class="info-value"><span class="badge badge-'.$risk['severity'].'">'.$risk['severity'].'</span> – '.htmlspecialchars($severityText).'</div></div>
        <div class="info-row"><div class="info-label">วันเวลาที่เกิดเหตุการณ์ (จริง)</div><div class="info-value">'.getThaiDate($risk['event_datetime']).'</div></div>
        <div class="info-row"><div class="info-label">วันเวลาที่รายงานเหตุการณ์</div><div class="info-value">'.getThaiDate($risk['report_datetime']).'</div></div>
        <div class="info-row"><div class="info-label">ผู้รายงาน</div><div class="info-value">'.htmlspecialchars($risk['username'] ?? 'ไม่ระบุ').'</div></div>
        <div class="info-row"><div class="info-label">📄 สถานะการยินยอม (Consent)</div><div class="info-value">'
            .($risk['consent'] == 1 ? '✅ ยินยอมแล้ว ('.getThaiDate($risk['consent_at']).')' : '❌ ยังไม่ยินยอม')
        .'</div></div>
    </div>
    <div><div style="font-weight:bold;font-size:16px;color:#1e3a8a;">รายละเอียดเหตุการณ์</div><div class="detail-box">'.nl2br(htmlspecialchars($risk['detail'])).'</div></div>
    <div><div style="font-weight:bold;font-size:16px;color:#1e3a8a;">การแก้ไขเบื้องต้น</div><div class="detail-box">'.nl2br(htmlspecialchars($risk['initial_solution'])).'</div></div>
    <div><div style="font-weight:bold;font-size:16px;color:#1e3a8a;">ปัญหาและข้อเสนอแนะ</div><div class="detail-box">'.nl2br(htmlspecialchars($risk['suggestion'])).'</div></div>
    <div class="footer">พิมพ์เมื่อ '.getThaiDate(date('Y-m-d H:i:s')).' | ระบบจัดการความเสี่ยง ศูนย์อนามัยที่ 8</div>';
}
$html .= '</body></html>';

// ---------- ตั้งค่า Dompdf + ลงทะเบียนฟอนต์ Sarabun ----------
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Sarabun');

$dompdf = new Dompdf($options);

// ลงทะเบียนฟอนต์ Sarabun (ต้องใช้ชื่อ family "Sarabun" ตรงกับในไฟล์)
$regularFont = __DIR__ . '/fonts/Sarabun-Regular.ttf';
$boldFont    = __DIR__ . '/fonts/Sarabun-Bold.ttf';

if (file_exists($regularFont) && file_exists($boldFont)) {
    $fontMetrics = $dompdf->getFontMetrics();
    // ลงทะเบียนทั้งปกติและตัวหนา
    $fontMetrics->registerFont('Sarabun', 'normal', 'normal', $regularFont);
    $fontMetrics->registerFont('Sarabun', 'normal', 'bold', $boldFont);
} else {
    // fallback (ถ้าหาไฟล์ไม่เจอ)
    $options->set('defaultFont', 'Garuda');
}

// ---------- เรนเดอร์ PDF ----------
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_length()) ob_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ---------- ส่ง PDF ----------
$dompdf->stream('รายงานความเสี่ยง_' . date('Ymd_His') . '.pdf', ['Attachment' => 1]);
exit;