<?php
/**
 * หน้า Login (Frontend) – โทนสีฟ้า พร้อมดึงค่าจาก System Settings
 * - ผู้ใช้ทั่วไป → risk_form.php
 * - Admin → dashboard.php
 * - ตรวจสอบสถานะ enabled ก่อนอนุญาตให้ login
 * - ดึงชื่อระบบ, คำอธิบาย, หน่วยงาน, โลโก้ จาก system_settings
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard.php' : 'risk_form.php');
}

// ===== ดึงค่าการตั้งค่าระบบ =====
$site_name = 'Risk Management';
$site_description = 'ระบบบริหารจัดการความเสี่ยง';
$site_organization = 'ศูนย์อนามัยที่ 8 อุดรธานี';
$site_logo = null;

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'site_description', 'site_organization', 'site_logo')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'site_name' && !empty($row['setting_value'])) {
                $site_name = $row['setting_value'];
            } elseif ($row['setting_key'] === 'site_description' && !empty($row['setting_value'])) {
                $site_description = $row['setting_value'];
            } elseif ($row['setting_key'] === 'site_organization' && !empty($row['setting_value'])) {
                $site_organization = $row['setting_value'];
            } elseif ($row['setting_key'] === 'site_logo' && !empty($row['setting_value'])) {
                $site_logo = $row['setting_value'];
            }
        }
    }
} catch (Exception $e) {
    // ใช้ค่าเริ่มต้น
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
        if (isset($user['enabled']) && $user['enabled'] == 0) {
            setcookie('remember_me', '', time() - 3600, '/');
            $error = 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
            redirect(isAdmin() ? 'dashboard.php' : 'risk_form.php');
        }
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
                if (isset($user['enabled']) && $user['enabled'] == 0) {
                    $error = 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
                } else {
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
                }
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
        --clr-button: linear-gradient(135deg, #1e40af, #3b82f6);
        --clr-button-hover: linear-gradient(135deg, #1e3a8a, #2563eb);
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
        padding: 1rem;
    }

    /* Animated Blobs */
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

    /* Grid Pattern Overlay */
    .grid-pattern {
        position: absolute;
        inset: 0;
        background-image: 
            linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 60px 60px;
        z-index: 1;
    }

    /* Glass Card */
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

    /* Logo */
    .login-logo-wrapper {
        display: flex;
        justify-content: center;
        margin-bottom: 1.5rem;
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
        animation: floatIcon 4s ease-in-out infinite;
        overflow: hidden;
    }
    
    .login-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 8px;
        background: white;
        border-radius: 1.2rem;
    }
    
    .login-logo i {
        font-size: 2.2rem;
        color: white;
    }
    
    @keyframes floatIcon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    /* Titles */
    .login-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 0.25rem;
    }
    
    .login-subtitle {
        font-size: 0.9rem;
        color: #475569;
        margin-bottom: 0.15rem;
    }
    
    .login-org {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 1.5rem;
    }

    /* Input Fields */
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
        z-index: 2;
    }
    
    .input-group .input-field {
        width: 100%;
        padding: 0.95rem 1.2rem 0.95rem 3rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        color: #1e293b;
        font-size: 1rem;
        font-family: 'Sarabun', sans-serif;
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
    
    .input-group:focus-within .input-icon {
        color: #3b82f6;
    }

    /* Password Toggle */
    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.25rem;
        font-size: 1rem;
        transition: color 0.2s;
        z-index: 2;
    }
    
    .password-toggle:hover {
        color: #3b82f6;
    }
    
    .input-group .input-field {
        padding-right: 3rem;
    }

    /* Login Button */
    .btn-login {
        width: 100%;
        padding: 1rem;
        background: var(--clr-button);
        border: none;
        border-radius: 1rem;
        color: white;
        font-size: 1.05rem;
        font-weight: 600;
        font-family: 'Sarabun', sans-serif;
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
    
    .btn-login:active {
        transform: translateY(0);
    }
    
    .btn-login:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    /* Options */
    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 1.25rem 0 1.5rem;
        font-size: 0.8rem;
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
        cursor: pointer;
    }
    
    .login-options .forgot-link {
        color: #64748b;
        text-decoration: none;
        transition: color 0.2s;
        font-weight: 500;
    }
    
    .login-options .forgot-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    /* Footer */
    .login-footer {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
    
    .login-footer .register-link {
        color: #64748b;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .login-footer .register-link:hover {
        color: #2563eb;
    }
    
    .login-footer .register-link strong {
        color: #1d4ed8;
        font-weight: 600;
    }

    /* Security Note */
    .security-note {
        text-align: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(0,0,0,0.05);
        font-size: 0.7rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .security-note span {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Alert */
    .alert-error {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1.25rem;
        font-size: 0.85rem;
        display: flex;
        align-items: flex-start;
        animation: shake 0.5s ease;
    }
    
    .alert-error i {
        margin-top: 0.15rem;
        margin-right: 0.5rem;
        flex-shrink: 0;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    /* Loading Spinner */
    .spinner {
        display: inline-block;
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Responsive */
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
            font-size: 0.9rem;
        }
        
        .login-options {
            flex-direction: column;
            gap: 0.75rem;
            align-items: flex-start;
        }
    }
</style>

<div class="login-bg">
    <!-- Animated Background -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="grid-pattern"></div>

    <!-- Login Card -->
    <div class="glass-card">
        <!-- Logo -->
        <div class="login-logo-wrapper">
            <div class="login-logo">
                <?php if ($site_logo): ?>
                    <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                    <i class="fas fa-shield-alt" style="display:none;"></i>
                <?php else: ?>
                    <i class="fas fa-shield-alt"></i>
                <?php endif; ?>
            </div>
        </div>

        <!-- Titles -->
        <div class="text-center">
            <h1 class="login-title"><?= htmlspecialchars($site_name) ?></h1>
            <p class="login-subtitle"><?= htmlspecialchars($site_description) ?></p>
            <p class="login-org"><?= htmlspecialchars($site_organization) ?></p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" novalidate id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="input-group">
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" id="username" class="input-field" 
                       placeholder="ชื่อผู้ใช้" required autofocus autocomplete="username">
            </div>

            <div class="input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" id="password" class="input-field" 
                       placeholder="รหัสผ่าน" required autocomplete="current-password">
                <button type="button" class="password-toggle" onclick="togglePassword()" tabindex="-1" aria-label="แสดง/ซ่อนรหัสผ่าน">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </button>
            </div>

            <div class="login-options">
                <label class="remember">
                    <input type="checkbox" name="remember" id="remember">
                    <span>จำฉันไว้</span>
                </label>
                <a href="forgot-password.php" class="forgot-link">
                    <i class="fas fa-question-circle"></i> ลืมรหัสผ่าน?
                </a>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </button>
        </form>

        <!-- Register Link -->
        <div class="login-footer">
            <a href="register.php" class="register-link">
                ยังไม่มีบัญชี? <strong>สมัครสมาชิก</strong>
                <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>

        <!-- Security Features -->
        <div class="security-note">
            <span><i class="fas fa-shield-alt"></i> CSRF</span>
            <span>•</span>
            <span><i class="fas fa-lock"></i> Brute Force</span>
            <span>•</span>
            <span><i class="fas fa-user-check"></i> Status Check</span>
        </div>
    </div>
</div>

<script>
    // ========== Toggle Password Visibility ==========
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // ========== Form Submission ==========
    let formSubmitted = false;
    
    document.getElementById('loginForm')?.addEventListener('submit', function(e) {
        const loginBtn = document.getElementById('loginBtn');
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        // Validate
        if (!username || !password) {
            e.preventDefault();
            return;
        }
        
        // Prevent double submission
        if (formSubmitted) {
            e.preventDefault();
            return false;
        }
        
        formSubmitted = true;
        
        // Show loading state
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<span class="spinner"></span> กำลังเข้าสู่ระบบ...';
    });
    
    // ========== Keyboard Shortcuts ==========
    document.addEventListener('keydown', function(e) {
        // Enter key submits form (default behavior)
        // Ctrl+Enter also submits
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const form = document.getElementById('loginForm');
            if (form) {
                form.requestSubmit();
            }
        }
    });
    
    // ========== Auto-focus ==========
    document.addEventListener('DOMContentLoaded', function() {
        const usernameInput = document.getElementById('username');
        if (usernameInput && !usernameInput.value) {
            usernameInput.focus();
        }
        
        // Log security features
        console.log('✅ Login page loaded');
        console.log('🏢 Site:', '<?= htmlspecialchars($site_name) ?>');
        console.log('🔒 CSRF Protection: Active');
        console.log('🛡️ Brute Force Protection: Active');
        console.log('👤 Account Status Check: Active');
        console.log('🍪 Remember Me: Available');
    });
</script>

<?php include 'includes/footer.php'; ?>