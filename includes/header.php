<?php

/**
 * ส่วนหัวของ HTML (Frontend)
 * - ตรวจสอบการล็อกอิน (ยกเว้นหน้าสาธารณะ)
 * - โหลด CSS, JavaScript
 * - แสดง Cookie Consent Banner
 */

// ตรวจสอบว่าเรียกผ่านระบบหรือไม่
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/functions.php';

// หน้าไม่ต้อง Login (สาธารณะ)
$public_pages = ['index.php', 'register.php'];
$current_page = basename($_SERVER['PHP_SELF']);

// หากไม่ได้ Login และไม่ใช่หน้าสาธารณะ ให้ไปที่ Login
if (!in_array($current_page, $public_pages) && !isLoggedIn()) {
    redirect('index.php');
}

// ตรวจสอบว่ามี Cookie Consent หรือยัง (ถ้ายังไม่มีให้แสดง Banner)
$show_consent_banner = !isset($_COOKIE['cookie_consent']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>ระบบจัดการความเสี่ยง</title>
    <!-- Tailwind CSS (จาก CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script> -->
    <!-- Font Awesome (ไอคอน) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- SweetAlert2 (Popup สวย ๆ) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS (โทนสีฟ้า) -->
    <link rel="stylesheet" href="assets/css/custom.css">
    <!-- Cookie Consent JavaScript -->
    <script src="assets/js/cookie-consent.js" defer></script>
</head>

<body>
    