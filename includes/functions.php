<?php
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * ตรวจสอบว่าเป็นแอดมินหรือไม่
 */
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirect ไปยัง URL ที่กำหนด
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * แปลงวันที่เป็นรูปแบบไทย (พ.ศ.)
 */
function getThaiDate($datetime)
{
    $date = new DateTime($datetime);
    $months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $thaiYear = $date->format('Y') + 543;
    $month = $months[(int)$date->format('m') - 1];
    return $date->format('j') . ' ' . $month . ' ' . $thaiYear . ' ' . $date->format('H:i');
}

/**
 * คืนค่า CSS badge สำหรับระดับความรุนแรง
 */
function getSeverityBadge($severity)
{
    $map = [
        'A' => 'badge-A',
        'B' => 'badge-B',
        'C' => 'badge-C',
        'D' => 'badge-D',
        'F' => 'badge-F',
        'E' => 'badge-E'
    ];
    return $map[$severity] ?? 'bg-gray-100 text-gray-800';
}

/**
 * สร้าง CSRF Token (ถ้ายังไม่มี)
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF Token
 */
function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ตรวจสอบ Brute Force (จำกัดจำนวนครั้งที่ล็อกอินผิด)
 */
function checkBruteForce($username)
{
    $key = 'login_attempts_' . md5($username);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
    if ($attempts['count'] >= 5 && (time() - $attempts['time']) < 900) {
        return false;
    }
    return true;
}

/**
 * บันทึกจำนวนครั้งที่ล็อกอินผิด
 */
function recordFailedAttempt($username)
{
    $key = 'login_attempts_' . md5($username);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
    $attempts['count']++;
    $attempts['time'] = time();
    $_SESSION[$key] = $attempts;
}

/**
 * รีเซ็ตตัวนับ Brute Force หลังจากล็อกอินสำเร็จ
 */
function resetBruteForce($username)
{
    $key = 'login_attempts_' . md5($username);
    unset($_SESSION[$key]);
}

/**
 * คืนค่าข้อมูลผู้ใช้ปัจจุบัน (ใช้ใน Navbar และหน้าอื่น ๆ)
 * @return array|null
 */
function getCurrentUser()
{
    if (!isLoggedIn()) return null;
    global $pdo; // ใช้ PDO ที่กำหนดใน config/db.php
    $stmt = $pdo->prepare("SELECT id, username, role, avatar, fullname, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * ส่งอีเมล (ใช้ PHPMailer; ถ้าไม่ติดตั้งจะใช้ mail() ของ PHP เบื้องต้น)
 * @param string $to อีเมลผู้รับ
 * @param string $subject หัวเรื่อง
 * @param string $body เนื้อหา (HTML ได้)
 * @return bool ส่งสำเร็จหรือไม่
 */
function sendEmail($to, $subject, $body)
{
    // -- เลือกใช้ PHPMailer ถ้ามี (แนะนำ) --
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        // Composer autoload
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // ตั้งค่า SMTP (ปรับตามผู้ให้บริการอีเมลของคุณ)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';          // ตัวอย่าง Gmail
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your-email@gmail.com';    // อีเมลของคุณ
            $mail->Password   = 'your-app-password';       // รหัสผ่านแอป (App Password)
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // ผู้ส่ง (ควรเป็นอีเมลเดียวกับ Username)
            $mail->setFrom('your-email@gmail.com', 'ระบบจัดการความเสี่ยง');
            $mail->addAddress($to);

            // เนื้อหา
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            // ถ้าต้องการ plain text สำรอง: $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email error: " . $mail->ErrorInfo);
            return false;
        }
    } 
    // -- fallback ใช้ mail() ของ PHP (อาจถูกบล็อก) --
    else {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: ระบบจัดการความเสี่ยง <no-reply@yourdomain.com>\r\n";
        return mail($to, $subject, $body, $headers);
    }
}