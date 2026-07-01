<?php
/**
 * ออกจากระบบ (Backend)
 * - ลบ Session ทั้งหมด
 * - ไปที่หน้า Login
 */
define('ACCESS_ALLOWED', true);
require_once 'includes/functions.php';

// ลบ Session
session_destroy();

// ไปที่หน้า Login
redirect('index.php');