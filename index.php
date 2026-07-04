<?php
/**
 * หน้า Login (Frontend) – โทนสีฟ้า
 * - ผู้ใช้ทั่วไป → risk_form.php
 * - Admin → dashboard.php
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard.php' : 'risk_form.php');
}

$error = '';

// Remember Me Auto Login
if (!isLoggedIn() && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $pdo->prepare("SELECT u.* FROM users u 
                           JOIN user_tokens t ON u.id = t.user_id 
                           WHERE t.token = ? AND t.expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
        redirect(isAdmin() ? 'dashboard.php' : 'risk_form.php');
    } else {
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

// ตรวจสอบการ Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request (CSRF token ไม่ถูกต้อง)';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!checkBruteForce($username)) {
            $error = 'คุณพยายามเข้าสู่ระบบผิดพลาดมากเกินไป กรุณารอ 15 นาที';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                resetBruteForce($username);
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                    $stmtDel = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?");
                    $stmtDel->execute([$user['id']]);

                    $stmtToken = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmtToken->execute([$user['id'], $token, $expires]);

                    setcookie('remember_me', $token, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                redirect(isAdmin() ? 'dashboard.php' : 'risk_form.php');
            } else {
                recordFailedAttempt($username);
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
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
        --clr-white: #1e293b;
        --clr-text-secondary: #334155;
        --clr-text-muted: #64748b;
        --clr-input-bg: #ffffff;
        --clr-input-border: #cbd5e1;
        --clr-focus: #3b82f6;
        --clr-button: linear-gradient(135deg, #1e40af, #3b82f6);
        --clr-button-hover: linear-gradient(135deg, #1e3a8a, #2563eb);
        --font-size-base: 1rem;
        --font-size-lg: 1.75rem;
        --font-size-sm: 0.9rem;
        --font-size-xs: 0.8rem;
        --spacing: 1rem;
        --radius: 1.25rem;
    }

    .login-bg {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 25%, #1e40af 50%, #2563eb 75%, #3b82f6 100%);
        position: relative;
        overflow: hidden;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--spacing);
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
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: var(--radius);
        padding: 2.5rem 2.2rem;
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.25), 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: cardEntry 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    @keyframes cardEntry {
        0% { opacity: 0; transform: translateY(25px) scale(0.97); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    .login-logo {
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
    .login-logo i {
        font-size: 2.2rem;
        color: white;
    }
    @keyframes floatIcon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    .login-title {
        font-size: var(--font-size-lg);
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 0.25rem;
    }
    .login-subtitle {
        font-size: var(--font-size-sm);
        color: #475569;
    }
    .login-org {
        font-size: var(--font-size-xs);
        color: #64748b;
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
        font-size: 1.1rem;
        pointer-events: none;
        transition: color 0.25s;
    }
    .input-group .input-field {
        width: 100%;
        padding: 0.95rem 1.2rem 0.95rem 3rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
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
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    }
    .input-group .input-field:focus + .input-icon {
        color: #3b82f6;
    }

    .btn-login {
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
        box-shadow: 0 6px 18px -4px rgba(30, 64, 175, 0.5);
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-login:hover {
        background: var(--clr-button-hover);
        transform: translateY(-2px);
        box-shadow: 0 10px 24px -4px rgba(30, 64, 175, 0.6);
    }

    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 1.25rem 0 1.5rem;
        font-size: var(--font-size-xs);
    }
    .login-options .remember {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #475569;
        cursor: pointer;
    }
    .login-options .remember input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #3b82f6;
    }
    .login-options .forgot-link {
        color: #64748b;
        text-decoration: none;
        transition: color 0.2s;
    }
    .login-options .forgot-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    .login-footer {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
    .login-footer .register-link {
        color: #64748b;
        font-size: var(--font-size-sm);
        text-decoration: none;
        transition: color 0.2s;
    }
    .login-footer .register-link:hover {
        color: #2563eb;
    }
    .login-footer .register-link strong {
        color: #1d4ed8;
        font-weight: 600;
    }

    .security-note {
        text-align: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(0,0,0,0.05);
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .bg-red-100 {
        background-color: #fee2e2;
    }
    .border-red-200 {
        border-color: #fecaca;
    }
    .text-red-700 {
        color: #b91c1c;
    }

    @media (max-width: 480px) {
        .glass-card {
            padding: 2rem 1.5rem;
            border-radius: 1.5rem;
        }
        .login-logo {
            width: 64px;
            height: 64px;
        }
        .login-logo i {
            font-size: 2rem;
        }
        .login-title {
            font-size: 1.5rem;
        }
        .input-group .input-field {
            padding: 0.85rem 1rem 0.85rem 2.8rem;
        }
    }
</style>

<div class="login-bg">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="glass-card">
        <div class="text-center">
            <div class="login-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">Risk Management</h1>
            <p class="login-subtitle">ระบบบริหารจัดการความเสี่ยง</p>
            <p class="login-org">ศูนย์อนามัยที่ 8 อุดรธานี</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-5 text-sm flex items-start">
                <i class="fas fa-exclamation-circle mt-0.5 mr-2 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="input-group">
                <input type="text" name="username" class="input-field" placeholder="ชื่อผู้ใช้" required autofocus>
                <i class="fas fa-user input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" class="input-field" placeholder="รหัสผ่าน" required>
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="login-options">
                <label class="remember">
                    <input type="checkbox" name="remember">
                    จำฉันไว้
                </label>
                <a href="forgot-password.php" class="forgot-link">ลืมรหัสผ่าน?</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </button>
        </form>

        <div class="login-footer">
            <a href="register.php" class="register-link">
                ยังไม่มีบัญชี? <strong>สมัครสมาชิก</strong> <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>

        <div class="security-note">
            <i class="fas fa-shield-alt mr-1"></i> ระบบปลอดภัยด้วย CSRF & Brute Force Protection
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>