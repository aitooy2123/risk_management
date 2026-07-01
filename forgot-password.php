<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';
$success = '';
$dev_token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
        if (empty($usernameOrEmail)) {
            $error = 'กรุณากรอกชื่อผู้ใช้หรืออีเมล';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmtDel->execute([$user['id']]);
                $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmtInsert->execute([$user['id'], $token, $expires]);

                if (function_exists('sendEmail')) {
                    $resetLink = "http://localhost/risk_management/reset-password.php?token=" . urlencode($token);
                    $subject = "รีเซ็ตรหัสผ่าน - ระบบจัดการความเสี่ยง";
                    $body = "<p>เรียนคุณ {$user['username']},</p><p>คลิกลิงก์เพื่อตั้งรหัสผ่านใหม่:</p><p><a href='{$resetLink}'>{$resetLink}</a></p><p>ลิงก์หมดอายุใน 1 ชั่วโมง</p>";
                    sendEmail($user['email'], $subject, $body);
                } else {
                    $dev_token = $token; // fallback
                }
            }
            $success = 'หากข้อมูลถูกต้อง ระบบจะส่งลิงก์รีเซ็ตรหัสผ่านให้ทางอีเมล';
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<?php include 'includes/header.php'; ?>
<!-- ส่วน HTML/CSS เหมือนเดิม -->
...