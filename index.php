<?php
/**
 * หน้า Login (Frontend) – Dark Theme พร้อมลูกเล่น
 * - ผู้ใช้ทั่วไป → risk_form.php
 * - Admin → dashboard.php
 * - ตรวจสอบสถานะ enabled ก่อนอนุญาตให้ login
 * - ดึงชื่อระบบ, คำอธิบาย, หน่วยงาน, โลโก้ จาก system_settings
 * - Responsive Full Screen Design
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
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($site_name) ?> - เข้าสู่ระบบ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #3b82f6;
            --primary-light: #60a5fa;
            --primary-glow: rgba(59, 130, 246, 0.5);
            --surface: rgba(15, 23, 42, 0.85);
            --surface-border: rgba(255, 255, 255, 0.08);
            --text: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --input-bg: rgba(30, 41, 59, 0.8);
            --input-border: rgba(255, 255, 255, 0.1);
            --input-focus: rgba(59, 130, 246, 0.5);
            --radius: 1.25rem;
            --radius-sm: 0.875rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: #0a0f1a;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
        }

        /* ===== Main Container ===== */
        .login-wrapper {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
            background: radial-gradient(ellipse at 20% 50%, #1e3a5f 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, #1a2744 0%, transparent 50%),
                        radial-gradient(ellipse at 50% 80%, #0f1d36 0%, transparent 50%),
                        #0a0f1a;
        }

        /* ===== Animated Stars ===== */
        .stars-container {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle var(--duration) ease-in-out infinite;
            animation-delay: var(--delay);
            opacity: 0;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }

        /* ===== Animated Orbs ===== */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            z-index: 0;
            pointer-events: none;
            animation: floatOrb var(--duration) ease-in-out infinite alternate;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: #3b82f6;
            top: -10%;
            left: -10%;
            --duration: 12s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: #6366f1;
            bottom: -15%;
            right: -5%;
            --duration: 15s;
            animation-delay: -4s;
        }

        .orb-3 {
            width: 300px;
            height: 300px;
            background: #06b6d4;
            top: 50%;
            left: 60%;
            --duration: 18s;
            animation-delay: -8s;
        }

        .orb-4 {
            width: 250px;
            height: 250px;
            background: #8b5cf6;
            top: 20%;
            right: 30%;
            --duration: 14s;
            animation-delay: -6s;
        }

        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.1); }
            66% { transform: translate(-20px, 15px) scale(0.95); }
            100% { transform: translate(10px, -10px) scale(1.05); }
        }

        /* ===== Grid Lines ===== */
        .grid-overlay {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 1;
            pointer-events: none;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }

        /* ===== Floating Particles ===== */
        .particles {
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(59, 130, 246, 0.6);
            border-radius: 50%;
            animation: floatUp var(--duration) linear infinite;
            animation-delay: var(--delay);
            opacity: 0;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.8), 0 0 20px rgba(59, 130, 246, 0.4);
        }

        @keyframes floatUp {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                transform: translateY(-100px) translateX(var(--drift));
                opacity: 0;
            }
        }

        /* ===== Login Card ===== */
        .login-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius);
            padding: 2.5rem 2.2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 40px rgba(59, 130, 246, 0.1);
            animation: cardEntry 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        /* Card glow effect */
        .login-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: var(--radius);
            padding: 1px;
            background: linear-gradient(135deg, 
                rgba(59, 130, 246, 0.3),
                transparent 30%,
                transparent 70%,
                rgba(99, 102, 241, 0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            animation: borderGlow 4s ease-in-out infinite alternate;
        }

        @keyframes borderGlow {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes cardEntry {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ===== Logo ===== */
        .logo-section {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .logo-box {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 1.2rem;
            box-shadow: 0 8px 32px -8px rgba(59, 130, 246, 0.5);
            overflow: visible;
            transition: transform 0.3s ease;
        }

        .logo-box:hover {
            transform: translateY(-3px) scale(1.05);
        }

        /* Logo glow ring */
        .logo-box::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 1.4rem;
            background: conic-gradient(
                from 0deg,
                transparent,
                rgba(59, 130, 246, 0.4),
                transparent,
                rgba(99, 102, 241, 0.4),
                transparent
            );
            animation: logoSpin 4s linear infinite;
            z-index: -1;
        }

        @keyframes logoSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
            background: white;
            border-radius: 1.2rem;
            position: relative;
            z-index: 1;
        }

        .logo-box i {
            font-size: 2.2rem;
            color: white;
            position: relative;
            z-index: 1;
            animation: logoPulse 2s ease-in-out infinite;
        }

        @keyframes logoPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* ===== Text ===== */
        .text-center {
            text-align: center;
        }

        .login-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            text-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.15rem;
        }

        .login-org {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 1.75rem;
            font-weight: 500;
        }

        /* ===== Alert Error ===== */
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            animation: shake 0.5s ease;
            backdrop-filter: blur(10px);
        }

        .alert-error i {
            margin-top: 0.15rem;
            flex-shrink: 0;
            color: #ef4444;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }

        /* ===== Form ===== */
        .form-group {
            position: relative;
            margin-bottom: 1.2rem;
        }

        .form-group .input-icon {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.05rem;
            pointer-events: none;
            transition: color 0.3s;
            z-index: 2;
        }

        .form-input {
            width: 100%;
            padding: 0.9rem 1.1rem 0.9rem 2.8rem;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 0.95rem;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
            outline: none;
        }

        .form-input::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .form-input:focus {
            background: rgba(30, 41, 59, 0.95);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15),
                        0 0 20px rgba(59, 130, 246, 0.1);
        }

        .form-group:focus-within .input-icon {
            color: var(--primary-light);
            text-shadow: 0 0 10px var(--primary-glow);
        }

        /* Password Toggle */
        .form-input.has-password {
            padding-right: 3rem;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 2;
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: var(--primary-light);
        }

        /* ===== Options ===== */
        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1rem 0 1.5rem;
            font-size: 0.85rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            min-height: 44px;
            padding: 0.25rem 0;
            user-select: none;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
            min-width: 18px;
        }

        .forgot-link {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            min-height: 44px;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .forgot-link:hover {
            color: white;
            text-shadow: 0 0 10px var(--primary-glow);
        }

        /* ===== Submit Button ===== */
        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border: none;
            border-radius: var(--radius-sm);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px -4px rgba(37, 99, 235, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 50px;
            -webkit-tap-highlight-color: transparent;
            position: relative;
            overflow: hidden;
        }

        /* Button shine effect */
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::after {
            left: 100%;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 8px 30px -4px rgba(37, 99, 235, 0.6),
                        0 0 20px rgba(59, 130, 246, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit:disabled::after {
            display: none;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== Footer ===== */
        .login-footer {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .register-link {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            min-height: 44px;
            padding: 0.25rem 0.5rem;
        }

        .register-link:hover {
            color: var(--primary-light);
        }

        .register-link strong {
            color: var(--primary-light);
            font-weight: 600;
        }

        /* ===== Security Note ===== */
        .security-note {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .security-note span {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }

        .security-note i {
            font-size: 0.65rem;
            color: #22c55e;
        }

        /* ===== Back to Home ===== */
        .back-home {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            transition: all 0.3s;
        }

        .back-home:hover {
            color: white;
            border-color: var(--primary);
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.2);
        }

        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 768px) {
            .login-wrapper {
                padding: 1rem;
            }

            .login-card {
                max-width: 400px;
                padding: 2rem 1.8rem;
            }

            .orb-1 { width: 350px; height: 350px; }
            .orb-2 { width: 300px; height: 300px; }
            .orb-3 { width: 200px; height: 200px; }
            .orb-4 { width: 180px; height: 180px; }

            .back-home {
                top: 1rem;
                left: 1rem;
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .login-card {
                max-width: 100%;
                padding: 2rem 1.5rem;
                border-radius: 1.2rem;
            }

            .login-card::before {
                border-radius: 1.2rem;
            }

            .logo-box {
                width: 60px;
                height: 60px;
                border-radius: 1rem;
            }

            .logo-box::after {
                border-radius: 1.2rem;
            }

            .logo-box i {
                font-size: 1.8rem;
            }

            .login-title {
                font-size: 1.4rem;
            }

            .login-subtitle {
                font-size: 0.85rem;
            }

            .login-org {
                font-size: 0.78rem;
                margin-bottom: 1.5rem;
            }

            .form-input {
                padding: 0.8rem 1rem 0.8rem 2.6rem;
                font-size: 0.9rem;
                border-radius: 0.8rem;
            }

            .form-group .input-icon {
                left: 0.9rem;
                font-size: 0.95rem;
            }

            .btn-submit {
                padding: 0.85rem;
                font-size: 0.95rem;
                border-radius: 0.8rem;
                min-height: 48px;
            }

            .login-options {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .login-wrapper {
                padding: 0.75rem;
                align-items: flex-start;
                padding-top: 3.5rem;
            }

            .login-card {
                padding: 1.75rem 1.2rem;
                border-radius: 1rem;
            }

            .login-card::before {
                border-radius: 1rem;
            }

            .logo-box {
                width: 54px;
                height: 54px;
                border-radius: 0.9rem;
            }

            .logo-box::after {
                border-radius: 1.1rem;
            }

            .login-title {
                font-size: 1.3rem;
            }

            .form-input {
                padding: 0.75rem 0.8rem 0.75rem 2.4rem;
                font-size: 0.875rem;
            }

            .form-group .input-icon {
                left: 0.75rem;
                font-size: 0.9rem;
            }

            .btn-submit {
                padding: 0.8rem;
                font-size: 0.9rem;
                min-height: 46px;
            }

            .security-note {
                font-size: 0.65rem;
            }
        }

        @media (max-width: 360px) {
            .login-wrapper {
                padding: 0.5rem;
                padding-top: 3rem;
            }

            .login-card {
                padding: 1.5rem 1rem;
            }

            .logo-box {
                width: 48px;
                height: 48px;
                border-radius: 0.8rem;
            }

            .logo-box::after {
                border-radius: 1rem;
            }

            .login-title {
                font-size: 1.15rem;
            }

            .login-subtitle {
                font-size: 0.75rem;
            }

            .login-org {
                font-size: 0.7rem;
                margin-bottom: 1.25rem;
            }

            .form-input {
                padding: 0.7rem 0.7rem 0.7rem 2.2rem;
                font-size: 0.85rem;
            }

            .form-group .input-icon {
                left: 0.65rem;
                font-size: 0.85rem;
            }

            .btn-submit {
                padding: 0.7rem;
                font-size: 0.85rem;
                min-height: 42px;
            }
        }

        /* Landscape */
        @media (max-height: 600px) and (orientation: landscape) {
            .login-wrapper {
                align-items: flex-start;
                padding-top: 1rem;
            }

            .login-card {
                padding: 1.5rem 1.8rem;
                margin-bottom: 1rem;
            }

            .logo-section {
                margin-bottom: 0.75rem;
            }

            .logo-box {
                width: 44px;
                height: 44px;
            }

            .login-title {
                font-size: 1.2rem;
            }

            .login-org {
                margin-bottom: 1rem;
            }

            .form-group {
                margin-bottom: 0.75rem;
            }

            .form-input {
                padding: 0.65rem 1rem 0.65rem 2.4rem;
                font-size: 0.85rem;
            }

            .btn-submit {
                padding: 0.7rem;
                min-height: 42px;
            }

            .login-options {
                margin: 0.75rem 0 1rem;
            }

            .login-footer {
                margin-top: 1rem;
                padding-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">

        <!-- Animated Stars -->
        <div class="stars-container" id="starsContainer"></div>

        <!-- Floating Particles -->
        <div class="particles" id="particlesContainer"></div>

        <!-- Animated Orbs -->
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>

        <!-- Grid Overlay -->
        <div class="grid-overlay"></div>

        <!-- Login Card -->
        <div class="login-card">
            <!-- Logo -->
            <div class="logo-section">
                <div class="logo-box">
                    <?php if ($site_logo): ?>
                        <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" 
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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
                <div class="alert-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" novalidate id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="form-group">
                    <i class="fas fa-user input-icon" aria-hidden="true"></i>
                    <input type="text" name="username" id="username" class="form-input" 
                           placeholder="ชื่อผู้ใช้" required autofocus autocomplete="username"
                           aria-label="ชื่อผู้ใช้">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" name="password" id="password" class="form-input has-password" 
                           placeholder="รหัสผ่าน" required autocomplete="current-password"
                           aria-label="รหัสผ่าน">
                    <button type="button" class="password-toggle" onclick="togglePassword()" tabindex="-1" 
                            aria-label="แสดงหรือซ่อนรหัสผ่าน">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="login-options">
                    <label class="remember-me" for="remember">
                        <input type="checkbox" name="remember" id="remember">
                        <span>จำฉันไว้ในระบบ</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">
                        <i class="fas fa-question-circle"></i> ลืมรหัสผ่าน?
                    </a>
                </div>

                <button type="submit" class="btn-submit" id="loginBtn" aria-label="เข้าสู่ระบบ">
                    <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                </button>
            </form>

            <!-- Register Link -->
            <div class="login-footer">
                <a href="register.php" class="register-link">
                    ยังไม่มีบัญชี? <strong>สมัครสมาชิก</strong>
                    <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
                </a>
            </div>

            <!-- Security Badges -->
            <div class="security-note">
                <span><i class="fas fa-shield-alt"></i> CSRF Protection</span>
                <span>•</span>
                <span><i class="fas fa-lock"></i> Brute Force</span>
                <span>•</span>
                <span><i class="fas fa-user-check"></i> Status Check</span>
            </div>
        </div>
    </div>

    <script>
        // ===== Generate Stars =====
        function createStars() {
            const container = document.getElementById('starsContainer');
            const starCount = 100;
            
            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.classList.add('star');
                
                const size = Math.random() * 3 + 1;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.setProperty('--duration', (Math.random() * 3 + 2) + 's');
                star.style.setProperty('--delay', (Math.random() * 5) + 's');
                
                container.appendChild(star);
            }
        }

        // ===== Generate Floating Particles =====
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                particle.style.left = Math.random() * 100 + '%';
                particle.style.setProperty('--duration', (Math.random() * 10 + 8) + 's');
                particle.style.setProperty('--delay', (Math.random() * 10) + 's');
                particle.style.setProperty('--drift', (Math.random() * 100 - 50) + 'px');
                
                container.appendChild(particle);
            }
        }

        // ===== Toggle Password =====
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
        
        // ===== Form Submit =====
        let formSubmitted = false;
        
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            // Validate empty fields
            if (!username || !password) {
                e.preventDefault();
                
                if (!username) {
                    const userInput = document.getElementById('username');
                    userInput.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                    userInput.style.background = 'rgba(239, 68, 68, 0.1)';
                    userInput.style.boxShadow = '0 0 15px rgba(239, 68, 68, 0.2)';
                    setTimeout(() => {
                        userInput.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                        userInput.style.background = 'rgba(30, 41, 59, 0.8)';
                        userInput.style.boxShadow = 'none';
                    }, 2000);
                }
                if (!password) {
                    const passInput = document.getElementById('password');
                    passInput.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                    passInput.style.background = 'rgba(239, 68, 68, 0.1)';
                    passInput.style.boxShadow = '0 0 15px rgba(239, 68, 68, 0.2)';
                    setTimeout(() => {
                        passInput.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                        passInput.style.background = 'rgba(30, 41, 59, 0.8)';
                        passInput.style.boxShadow = 'none';
                    }, 2000);
                }
                
                return;
            }
            
            // Prevent double submission
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            
            formSubmitted = true;
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="spinner"></span> กำลังเข้าสู่ระบบ...';
        });
        
        // ===== Keyboard Shortcut =====
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form) form.requestSubmit();
            }
        });
        
        // ===== Viewport Height Fix for Mobile =====
        function setViewportHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
        
        window.addEventListener('resize', setViewportHeight);
        window.addEventListener('orientationchange', () => setTimeout(setViewportHeight, 100));
        setViewportHeight();
        
        // ===== Mouse Parallax Effect on Orbs =====
        document.addEventListener('mousemove', function(e) {
            const orbs = document.querySelectorAll('.orb');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            orbs.forEach((orb, index) => {
                const speed = (index + 1) * 15;
                const moveX = (x - 0.5) * speed;
                const moveY = (y - 0.5) * speed;
                orb.style.transform = `translate(${moveX}px, ${moveY}px)`;
            });
        });
        
        // ===== Init =====
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            createParticles();
            
            const usernameInput = document.getElementById('username');
            if (usernameInput && !usernameInput.value) {
                usernameInput.focus();
            }
            
            console.log('%c🔐 Dark Theme Login %c| %cReady',
                'color: #3b82f6; font-weight: bold; font-size: 1.1em;',
                '',
                'color: #22c55e;');
            console.log('%c🏢 ' + '<?= addslashes($site_name) ?>', 'color: #94a3b8;');
            console.log('%c✨ Stars: 100 | Particles: 20 | Orbs: 4', 'color: #64748b;');
            console.log('%c📱 Viewport: ' + window.innerWidth + 'x' + window.innerHeight, 'color: #64748b;');
            console.log('%c🛡️ Security: CSRF + Brute Force + Status Check', 'color: #22c55e;');
        });
    </script>
</body>
</html>