<?php
/**
 * การเชื่อมต่อฐานข้อมูล (Backend)
 * ใช้ PDO เพื่อป้องกัน SQL Injection
 * 
 * ไฟล์นี้ถูกเรียกผ่าน Constant ACCESS_ALLOWED เท่านั้น
 * เพื่อป้องกันการเข้าถึงโดยตรง
 */

// ตรวจสอบว่าเรียกผ่านระบบหรือไม่
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

// กำหนดค่าการเชื่อมต่อฐานข้อมูล
$host = 'localhost';
$dbname = 'risk_management';
$username = 'root';
$password = 'XBqGHC7C4mCArpxWTop8523ae4444';

try {
    // สร้างการเชื่อมต่อ PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ถ้าเชื่อมต่อไม่ได้ ให้แสดงข้อผิดพลาด
    die('Connection failed: ' . $e->getMessage());
}
?>