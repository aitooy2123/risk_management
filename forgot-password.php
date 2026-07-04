<?php
/**
 * หน้าลืมรหัสผ่าน (Frontend) – ดีไซน์โทนสีฟ้าเข้ม
 * - ส่งอีเมลรีเซ็ตรหัสผ่าน
 * - ใช้ token ในการรีเซ็ต
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request'; // request หรือ reset

// Step 1: ส่งคำขอรีเซ็ตรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request (CSRF token ไม่ถูกต้อง)';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'กรุณากรอกอีเมล';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'รูปแบบอีเมลไม่ถูกต้อง';
        } else {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // สร้าง token สำหรับรีเซ็ต
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // ลบ token เก่า
                $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmtDel->execute([$user['id']]);

                // บันทึก token ใหม่
                $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmtInsert->execute([$user['id'], $token, $expires]);

                // ส่งอีเมล (ในที่นี้แสดง link เฉยๆ ก่อน - ควรใช้ PHPMailer ในการส่งจริง)
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/risk_management/forgot-password.php?step=reset&token=" . $token;
                
                // TODO: ส่งอีเมลจริงด้วย PHPMailer หรือ SwiftMailer
                // mail($email, "รีเซ็ตรหัสผ่าน", "คลิกลิงก์เพื่อรีเซ็ตรหัสผ่าน: " . $resetLink);
                
                $success = 'ระบบได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว (กรุณาตรวจสอบอีเมล)';
                
                // สำหรับทดสอบ แสดงลิงก์ (ควรลบออกเมื่อใช้งานจริง)
                $success .= '<br><small class="text-muted">ลิงก์ทดสอบ: <a href="' . $resetLink . '">คลิกที่นี่</a></small>';
            } else {
                // เพื่อความปลอดภัย ไม่บอกว่าอีเมลมีอยู่หรือไม่
                $success = 'หากอีเมลนี้มีอยู่ในระบบ คุณจะได้รับลิงก์รีเซ็ตรหัสผ่านทางอีเมล';
            }
        }
    }
}

// Step 2: รีเซ็ตรหัสผ่านด้วย token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request (CSRF token ไม่ถูกต้อง)';
    } else {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            $error = 'Token ไม่ถูกต้อง';
        } elseif (strlen($password) < 8) {
            $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        } elseif ($password !== $confirm) {
            $error = 'รหัสผ่านไม่ตรงกัน';
        } else {
            $stmt = $pdo->prepare("SELECT pr.*, u.email FROM password_resets pr 
                                   JOIN users u ON pr.user_id = u.id 
                                   WHERE pr.token = ? AND pr.expires_at > NOW()");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            if ($reset) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                // อัปเดตรหัสผ่าน
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$hashed, $reset['user_id']]);

                // ลบ token ที่ใช้แล้ว
                $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmtDel->execute([$token]);

                $success = 'รีเซ็ตรหัสผ่านสำเร็จ! คุณสามารถเข้าสู่ระบบได้แล้ว';
                
                // Redirect ไปหน้า login หลังจาก 3 วินาที
                header("Refresh: 3; URL=index.php");
            } else {
                $error = 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว';
            }
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<?php include 'includes/header.php'; ?>

<style>
    :root {
        --clr-bg-start: #1e3a8a;
        --clr-bg-mid: #2563eb;
        --clr-bg-end: #3b82f6;
        --clr-card-bg: rgba(255, 255, 255, 0.95);
        --clr-text: #1e3a5f;
        --clr-text-secondary: #475569;
        --clr-text-muted: #64748b;
        --clr-input-bg: #f8fafc;
        --clr-input-border: #e2e8f0;
        --clr-focus: #3b82f6;
        --clr-button: linear-gradient(135deg, #1e40af, #3b82f6);
        --clr-button-hover: linear-gradient(135deg, #1e3a8a, #2563eb);
        --font-size-base: 1rem;
        --font-size-lg: 1.75rem;
        --font-size-sm: 0.9rem;
        --radius: 1.5rem;
    }

    .forgot-bg {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 25%, #1e40af 50%, #2563eb 75%, #3b82f6 100%);
        position: relative;
        overflow: hidden;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }

    .blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.15;
        z-index: 0;
        animation: floatBlob 14s ease-in-out infinite alternate;
    }
    .blob-1 { width: 350px; height: 350px; background: #1e40af; top: -5%; left: -5%; }
    .blob-2 { width: 400px; height: 400px; background: #2563eb; bottom: -10%; right: -5%; animation-delay: -5s; }
    .blob-3 { width: 250px; height: 250px; background: #3b82f6; top: 50%; left: 55%; animation-delay: -9s; }
    @keyframes floatBlob {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(25px, -20px) scale(1.05); }
    }

    .glass-card {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 440px;
        background: var(--clr-card-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: var(--radius);
        padding: 2.5rem 2.2rem;
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.25),
                    0 4px 12px rgba(0, 0, 0, 0.15);
        animation: cardEntry 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    @keyframes cardEntry {
        0% { opacity: 0; transform: translateY(25px) scale(0.97); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    .forgot-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 72px;
        height: 72px;
        background: linear-gradient(135deg, #1e40af, #3b82f6);
        border-radius: 1.2rem;
        box-shadow: 0 8px 20px -6px rgba(30, 64, 175, 0.5);
        margin-bottom: 1.5rem;
        animation: floatIcon 4s ease-in-out infinite;
    }
    .forgot-logo i {
        font-size: 2.2rem;
        color: white;
    }
    @keyframes floatIcon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    .forgot-title {
        font-size: var(--font-size-lg);
        font-weight: 700;
        color: var(--clr-text);
        margin-bottom: 0.25rem;
    }
    .forgot-subtitle {
        font-size: var(--font-size-sm);
        color: var(--clr-text-secondary);
        margin-bottom: 1.5rem;
    }

    .input-group {
        position: relative;
        margin-bottom: 1.2rem;
    }
    .input-group .input-icon {
        position: absolute;
        left: 1.2rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.05rem;
        transition: color 0.3s;
        pointer-events: none;
    }
    .input-group .input-field {
        width: 100%;
        padding: 0.95rem 1.2rem 0.95rem 3rem;
        background: var(--clr-input-bg);
        border: 1px solid var(--clr-input-border);
        border-radius: 1rem;
        color: #1e293b;
        font-size: var(--font-size-base);
        transition: all 0.25s;
        outline: none;
    }
    .input-group .input-field::placeholder {
        color: #94a3b8;
        font-weight: 300;
    }
    .input-group .input-field:focus {
        background: #ffffff;
        border-color: var(--clr-focus);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    }
    .input-group .input-field:focus + .input-icon {
        color: var(--clr-focus);
    }

    .btn-submit {
        position: relative;
        width: 100%;
        padding: 1rem;
        background: var(--clr-button);
        border: none;
        border-radius: 1rem;
        color: white;
        font-size: 1.05rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        overflow: hidden;
        box-shadow: 0 6px 18px -4px rgba(30, 64, 175, 0.5);
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-submit::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent 60%);
        pointer-events: none;
    }
    .btn-submit:hover {
        background: var(--clr-button-hover);
        transform: translateY(-2px);
        box-shadow: 0 10px 24px -4px rgba(30, 64, 175, 0.6);
    }
    .btn-submit:active {
        transform: scale(0.98);
    }

    .forgot-footer {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
    .forgot-footer a {
        color: var(--clr-text-muted);
        font-size: var(--font-size-sm);
        text-decoration: none;
        transition: color 0.2s;
    }
    .forgot-footer a:hover {
        color: #2563eb;
    }
    .forgot-footer a strong {
        color: #1d4ed8;
        font-weight: 600;
    }

    .error-box {
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.25);
        color: #b91c1c;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: flex-start;
        font-size: 0.85rem;
    }

    .success-box {
        background: rgba(34, 197, 94, 0.12);
        border: 1px solid rgba(34, 197, 94, 0.25);
        color: #15803d;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: flex-start;
        font-size: 0.85rem;
    }

    .security-note {
        text-align: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(0,0,0,0.05);
        font-size: 0.7rem;
        color: #94a3b8;
    }

    @media (max-width: 480px) {
        .glass-card {
            padding: 2rem 1.5rem;
            border-radius: 1.5rem;
        }
        .forgot-logo {
            width: 64px;
            height: 64px;
        }
        .forgot-logo i {
            font-size: 2rem;
        }
        .forgot-title {
            font-size: 1.5rem;
        }
        .input-group .input-field {
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            font-size: 0.95rem;
        }
    }
</style>

<div class="forgot-bg">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="glass-card">
        <div class="text-center">
            <div class="forgot-logo">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="forgot-title">
                <?= $step === 'reset' ? 'รีเซ็ตรหัสผ่าน' : 'ลืมรหัสผ่าน?' ?>
            </h1>
            <p class="forgot-subtitle">
                <?= $step === 'reset' ? 'กรุณากำหนดรหัสผ่านใหม่' : 'กรอกอีเมลเพื่อรับลิงก์รีเซ็ตรหัสผ่าน' ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle mt-0.5 mr-2 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <i class="fas fa-check-circle mt-0.5 mr-2 flex-shrink-0"></i>
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 'request'): ?>
            <!-- Step 1: ขอรีเซ็ตรหัสผ่าน -->
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="input-group">
                    <input type="email" name="email" class="input-field"
                           placeholder="กรอกอีเมลของคุณ" required autofocus>
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> ส่งลิงก์รีเซ็ตรหัสผ่าน
                </button>
            </form>
        <?php else: ?>
            <!-- Step 2: รีเซ็ตรหัสผ่าน -->
            <?php 
            $token = $_GET['token'] ?? '';
            if (empty($token)): 
            ?>
                <div class="error-box">
                    <i class="fas fa-exclamation-triangle mt-0.5 mr-2 flex-shrink-0"></i>
                    <span>ลิงก์ไม่ถูกต้อง กรุณาขอลิงก์รีเซ็ตรหัสผ่านใหม่</span>
                </div>
            <?php else: ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="input-group">
                        <input type="password" name="password" class="input-field"
                               placeholder="รหัสผ่านใหม่ (อย่างน้อย 8 ตัว)" required autofocus>
                        <i class="fas fa-lock input-icon"></i>
                    </div>

                    <div class="input-group">
                        <input type="password" name="confirm_password" class="input-field"
                               placeholder="ยืนยันรหัสผ่านใหม่" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-sync-alt"></i> รีเซ็ตรหัสผ่าน
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <div class="forgot-footer">
            <a href="index.php">
                <i class="fas fa-arrow-left mr-1"></i> กลับไปหน้า<strong>เข้าสู่ระบบ</strong>
            </a>
        </div>

        <div class="security-note">
            <i class="fas fa-shield-alt mr-1"></i> ลิงก์รีเซ็ตรหัสผ่านมีอายุ 1 ชั่วโมง
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>