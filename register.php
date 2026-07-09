<?php
/**
 * หน้าสมัครสมาชิก (Frontend) – ดีไซน์โทนสีฟ้า
 * - ฟิลด์ reporter_code, username, password, confirm
 * - ตรวจสอบ: รหัสผ่าน >=8, ตรงกัน, reporter_code ไม่ว่าง, username ไม่ซ้ำ
 * - ล็อกอินอัตโนมัติเมื่อสมัครสำเร็จ
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $username      = trim($_POST['username'] ?? '');
        $password      = $_POST['password'] ?? '';
        $confirm       = $_POST['confirm_password'] ?? '';
        $reporter_code = trim($_POST['reporter_code'] ?? '');

        if (empty($reporter_code)) {
            $error = 'กรุณากรอกรหัสผู้รายงาน';
        } elseif (strlen($password) < 8) {
            $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        } elseif ($password !== $confirm) {
            $error = 'รหัสผ่านไม่ตรงกัน';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE reporter_code = ?");
                $stmt->execute([$reporter_code]);
                if ($stmt->fetch()) {
                    $error = 'รหัสผู้รายงานนี้มีอยู่แล้ว';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, reporter_code) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed, 'user', $reporter_code]);

                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $pdo->lastInsertId();
                    $_SESSION['username']  = $username;
                    $_SESSION['role']      = 'user';
                    $_SESSION['avatar']    = 'default.png';
                    redirect('dashboard.php');
                }
            }
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<?php include 'includes/header.php'; ?>

<style>
    /* ============================================
       ดีไซน์โทนสีฟ้าเข้ม (Dark Blue Theme)
       ============================================ */
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

    .register-bg {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 25%, #1e40af 50%, #2563eb 75%, #3b82f6 100%);
        position: relative;
        overflow: hidden;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }

    /* วงกลมเบลอๆ สไตล์ฟ้า */
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

    /* การ์ดสมัครสมาชิก */
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

    /* ไอคอนด้านบน */
    .register-logo {
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
    .register-logo i {
        font-size: 2.2rem;
        color: white;
    }
    @keyframes floatIcon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    .register-title {
        font-size: var(--font-size-lg);
        font-weight: 700;
        color: var(--clr-text);
        letter-spacing: -0.5px;
        margin-bottom: 0.25rem;
    }
    .register-subtitle {
        font-size: var(--font-size-sm);
        color: var(--clr-text-secondary);
        margin-bottom: 1.5rem;
    }

    /* ช่องกรอกข้อมูล */
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

    /* ปุ่มสมัครสมาชิก */
    .btn-register {
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
    .btn-register::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent 60%);
        pointer-events: none;
    }
    .btn-register:hover {
        background: var(--clr-button-hover);
        transform: translateY(-2px);
        box-shadow: 0 10px 24px -4px rgba(30, 64, 175, 0.6);
    }
    .btn-register:active {
        transform: scale(0.98);
    }

    /* ลิงก์ด้านล่าง */
    .register-footer {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
    .register-footer a {
        color: var(--clr-text-muted);
        font-size: var(--font-size-sm);
        text-decoration: none;
        transition: color 0.2s;
    }
    .register-footer a:hover {
        color: #2563eb;
    }
    .register-footer a strong {
        color: #1d4ed8;
        font-weight: 600;
    }

    /* ข้อความแจ้งเตือน */
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

    /* ข้อความด้านล่าง */
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
        .register-logo {
            width: 64px;
            height: 64px;
        }
        .register-logo i {
            font-size: 2rem;
        }
        .register-title {
            font-size: 1.5rem;
        }
        .input-group .input-field {
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            font-size: 0.95rem;
        }
    }
</style>

<div class="register-bg">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="glass-card">
        <div class="text-center">
            <div class="register-logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="register-title">สมัครสมาชิก</h1>
            <p class="register-subtitle">สร้างบัญชีผู้ใช้ใหม่</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle mt-0.5 mr-2 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <!-- รหัสผู้รายงาน -->
            <div class="input-group">
                <input type="text" name="reporter_code" class="input-field"
                       placeholder="รหัสผู้รายงาน (เช่น R10001)" required autofocus>
                <i class="fas fa-id-card input-icon"></i>
            </div>

            <!-- ชื่อผู้ใช้ -->
            <div class="input-group">
                <input type="text" name="username" class="input-field"
                       placeholder="ชื่อผู้ใช้" required>
                <i class="fas fa-user input-icon"></i>
            </div>

            <!-- รหัสผ่าน -->
            <div class="input-group">
                <input type="password" name="password" class="input-field"
                       placeholder="รหัสผ่าน (อย่างน้อย 8 ตัว)" required>
                <i class="fas fa-lock input-icon"></i>
            </div>

            <!-- ยืนยันรหัสผ่าน -->
            <div class="input-group">
                <input type="password" name="confirm_password" class="input-field"
                       placeholder="ยืนยันรหัสผ่าน" required>
                <i class="fas fa-lock input-icon"></i>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-check"></i> สมัครสมาชิก
            </button>
        </form>

        <div class="register-footer">
            <a href="index.php">มีบัญชีแล้ว? <strong>เข้าสู่ระบบ</strong> <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
        </div>

        <!-- <div class="security-note">
            <i class="fas fa-shield-alt mr-1"></i> ระบบปลอดภัยด้วย CSRF & การเข้ารหัสรหัสผ่าน
        </div> -->
    </div>
</div>

<?php include 'includes/footer.php'; ?>