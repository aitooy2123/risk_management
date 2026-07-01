<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = $success = '';
$token = $_GET['token'] ?? '';
if (!$token) die('Token required');
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();
if (!$reset) $error = 'Token ไม่ถูกต้องหรือหมดอายุ';
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8) $error = 'รหัสผ่านต้องอย่างน้อย 8 ตัว';
    elseif ($pw !== ($_POST['confirm'] ?? '')) $error = 'รหัสผ่านไม่ตรงกัน';
    else {
        $hashed = password_hash($pw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $reset['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$reset['id']]);
        $success = 'ตั้งรหัสผ่านใหม่สำเร็จ <a href="index.php">เข้าสู่ระบบ</a>';
    }
}
$csrf_token = generateCsrfToken();
?>
<?php include 'includes/header.php'; ?>
<!-- ใช้ดีไซน์ Glass เหมือนเดิม -->
...