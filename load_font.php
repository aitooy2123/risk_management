<?php
/**
 * ไฟล์สำหรับติดตั้งฟอนต์ภาษาไทย (THSarabun) ให้ Dompdf ใช้งาน
 * เปิด Command Prompt แล้วรัน: php load_font.php
 */

// เรียกใช้ autoload ของ Composer
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ตั้งค่า Options
$options = new Options();
$options->set('defaultFont', 'sans-serif');
$dompdf = new Dompdf($options);

// ดึง FontMetrics เพื่อลงทะเบียนฟอนต์
$fontMetrics = $dompdf->getFontMetrics();
$fontPath = 'fonts/THSarabun.ttf';

// ตรวจสอบว่ามีไฟล์ฟอนต์หรือไม่
if (file_exists($fontPath)) {
    // ลงทะเบียนฟอนต์ THSarabun
    $fontMetrics->registerFont('THSarabun', 'normal', $fontPath);
    echo "✅ ติดตั้งฟอนต์ THSarabun สำเร็จ!\n";
    echo "📍 ตำแหน่ง: " . realpath($fontPath) . "\n";
} else {
    echo "❌ ไม่พบไฟล์ฟอนต์: " . $fontPath . "\n";
    echo "📌 กรุณาดาวน์โหลด THSarabun.ttf วางในโฟลเดอร์ fonts/\n";
}