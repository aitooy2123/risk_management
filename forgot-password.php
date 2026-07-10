<?php
/**
 * หน้าลืมรหัสผ่าน (Frontend) – Dark Theme พร้อมลูกเล่น
 * - ส่งอีเมลรีเซ็ตรหัสผ่าน
 * - ใช้ token ในการรีเซ็ต
 * - แสดงสถานะการหมดอายุของ token
 * - Responsive Full Screen Design
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
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmtDel->execute([$user['id']]);

                $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmtInsert->execute([$user['id'], $token, $expires]);

                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/risk_management/forgot-password.php?step=reset&token=" . $token;
                
                $success = 'ระบบได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว (กรุณาตรวจสอบอีเมล)';
                $success .= '<br><small class="text-muted">ลิงก์ทดสอบ: <a href="' . htmlspecialchars($resetLink) . '" style="color:#a78bfa;">คลิกที่นี่</a></small>';
            } else {
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
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว';
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
                
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$hashed, $reset['user_id']]);

                $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmtDel->execute([$token]);

                $success = 'รีเซ็ตรหัสผ่านสำเร็จ! คุณสามารถเข้าสู่ระบบได้แล้ว';
                header("Refresh: 3; URL=index.php");
            } else {
                $error = 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว';
            }
        }
    }
}

$csrf_token = generateCsrfToken();

$urlToken = $_GET['token'] ?? '';
$tokenValid = false;
$tokenExpired = false;

if ($step === 'reset' && !empty($urlToken)) {
    $stmt = $pdo->prepare("SELECT expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$urlToken]);
    $tokenData = $stmt->fetch();
    
    if ($tokenData) {
        if (strtotime($tokenData['expires_at']) > time()) {
            $tokenValid = true;
        } else {
            $tokenExpired = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= $step === 'reset' ? 'รีเซ็ตรหัสผ่าน' : 'ลืมรหัสผ่าน?' ?> - ระบบจัดการความเสี่ยง</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #06b6d4;
            --primary-light: #22d3ee;
            --primary-glow: rgba(6, 182, 212, 0.5);
            --surface: rgba(15, 23, 42, 0.85);
            --surface-border: rgba(255, 255, 255, 0.08);
            --text: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --input-bg: rgba(30, 41, 59, 0.8);
            --input-border: rgba(255, 255, 255, 0.1);
            --input-focus: rgba(6, 182, 212, 0.5);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
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
        .forgot-wrapper {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
            background: radial-gradient(ellipse at 70% 30%, #164e63 0%, transparent 50%),
                        radial-gradient(ellipse at 30% 70%, #1a2744 0%, transparent 50%),
                        radial-gradient(ellipse at 50% 50%, #0f1d36 0%, transparent 50%),
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
            0%, 100% { opacity: 0.15; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.6); }
        }

        /* ===== Animated Orbs ===== */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.12;
            z-index: 0;
            pointer-events: none;
            animation: floatOrb var(--duration) ease-in-out infinite alternate;
        }

        .orb-1 {
            width: 450px;
            height: 450px;
            background: #06b6d4;
            top: -10%;
            left: -8%;
            --duration: 13s;
        }

        .orb-2 {
            width: 380px;
            height: 380px;
            background: #0891b2;
            bottom: -12%;
            right: -5%;
            --duration: 16s;
            animation-delay: -4s;
        }

        .orb-3 {
            width: 280px;
            height: 280px;
            background: #22d3ee;
            top: 45%;
            left: 55%;
            --duration: 18s;
            animation-delay: -8s;
        }

        .orb-4 {
            width: 200px;
            height: 200px;
            background: #67e8f9;
            bottom: 35%;
            right: 20%;
            --duration: 14s;
            animation-delay: -6s;
        }

        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(20px, -25px) scale(1.08); }
            66% { transform: translate(-18px, 12px) scale(0.94); }
            100% { transform: translate(8px, -8px) scale(1.04); }
        }

        /* ===== Grid Lines ===== */
        .grid-overlay {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(6, 182, 212, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(6, 182, 212, 0.03) 1px, transparent 1px);
            background-size: 55px 55px;
            z-index: 1;
            pointer-events: none;
            animation: gridMove 22s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(55px, 55px); }
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
            width: 3px;
            height: 3px;
            background: rgba(6, 182, 212, 0.5);
            border-radius: 50%;
            animation: floatUp var(--duration) linear infinite;
            animation-delay: var(--delay);
            opacity: 0;
            box-shadow: 0 0 8px rgba(6, 182, 212, 0.5), 0 0 16px rgba(6, 182, 212, 0.2);
        }

        @keyframes floatUp {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 0.7; }
            100% {
                transform: translateY(-100px) translateX(var(--drift));
                opacity: 0;
            }
        }

        /* ===== Card ===== */
        .forgot-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius);
            padding: 2.5rem 2.2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 40px rgba(6, 182, 212, 0.1);
            animation: cardEntry 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .forgot-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: var(--radius);
            padding: 1px;
            background: linear-gradient(135deg, 
                rgba(6, 182, 212, 0.3),
                transparent 30%,
                transparent 70%,
                rgba(34, 211, 238, 0.3));
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
            background: linear-gradient(135deg, #0891b2, #06b6d4);
            border-radius: 1.2rem;
            box-shadow: 0 8px 32px -8px rgba(6, 182, 212, 0.5);
            overflow: visible;
            transition: transform 0.3s ease;
        }

        .logo-box:hover {
            transform: translateY(-3px) scale(1.05);
        }

        .logo-box::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 1.4rem;
            background: conic-gradient(
                from 0deg,
                transparent,
                rgba(6, 182, 212, 0.4),
                transparent,
                rgba(34, 211, 238, 0.4),
                transparent
            );
            animation: logoSpin 4s linear infinite;
            z-index: -1;
        }

        @keyframes logoSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        .forgot-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            text-shadow: 0 2px 10px rgba(6, 182, 212, 0.3);
        }

        .forgot-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
        }

        /* ===== Alerts ===== */
        .alert {
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
        }

        .alert i {
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            animation: shake 0.5s ease;
        }

        .alert-error i { color: #ef4444; }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .alert-success i { color: #10b981; }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        .alert-warning i { color: #f59e0b; }

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
            border-color: #06b6d4;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.15),
                        0 0 20px rgba(6, 182, 212, 0.1);
        }

        .form-group:focus-within .input-icon {
            color: #22d3ee;
            text-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
        }

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
            color: #22d3ee;
        }

        /* ===== Password Strength ===== */
        .password-strength {
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            height: 4px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
            border-radius: 2px;
        }

        .strength-weak { width: 33%; background: #ef4444; box-shadow: 0 0 8px rgba(239, 68, 68, 0.5); }
        .strength-medium { width: 66%; background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.5); }
        .strength-strong { width: 100%; background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5); }

        /* ===== Match Indicator ===== */
        .match-indicator {
            font-size: 0.75rem;
            margin-top: -0.5rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 20px;
        }

        .match-success { color: #34d399; }
        .match-error { color: #f87171; }

        .form-input.input-success {
            border-color: rgba(16, 185, 129, 0.5) !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input.input-error {
            border-color: rgba(239, 68, 68, 0.5) !important;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
            background: rgba(239, 68, 68, 0.05);
        }

        /* ===== Submit Button ===== */
        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #0891b2, #06b6d4);
            border: none;
            border-radius: var(--radius-sm);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px -4px rgba(6, 182, 212, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 50px;
            -webkit-tap-highlight-color: transparent;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::after {
            left: 100%;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #0e7490, #0891b2);
            transform: translateY(-2px);
            box-shadow: 0 8px 30px -4px rgba(6, 182, 212, 0.6),
                        0 0 20px rgba(6, 182, 212, 0.3);
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
        .forgot-footer {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .login-link {
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

        .login-link:hover {
            color: #22d3ee;
        }

        .login-link strong {
            color: #22d3ee;
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
            color: #34d399;
        }

        /* ===== Countdown Timer ===== */
        .countdown-timer {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
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
            border-color: #06b6d4;
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.2);
        }

        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 768px) {
            .forgot-wrapper { padding: 1rem; }
            .forgot-card { max-width: 420px; padding: 2rem 1.8rem; }
            .orb-1 { width: 320px; height: 320px; }
            .orb-2 { width: 280px; height: 280px; }
            .orb-3 { width: 200px; height: 200px; }
            .orb-4 { width: 150px; height: 150px; }
            .back-home { top: 1rem; left: 1rem; font-size: 0.8rem; padding: 0.4rem 0.8rem; }
        }

        @media (max-width: 576px) {
            .forgot-card { max-width: 100%; padding: 2rem 1.5rem; border-radius: 1.2rem; }
            .forgot-card::before { border-radius: 1.2rem; }
            .logo-box { width: 60px; height: 60px; border-radius: 1rem; }
            .logo-box::after { border-radius: 1.2rem; }
            .logo-box i { font-size: 1.8rem; }
            .forgot-title { font-size: 1.4rem; }
            .forgot-subtitle { font-size: 0.85rem; margin-bottom: 1.5rem; }
            .form-input { padding: 0.8rem 1rem 0.8rem 2.6rem; font-size: 0.9rem; border-radius: 0.8rem; }
            .form-group .input-icon { left: 0.9rem; font-size: 0.95rem; }
            .btn-submit { padding: 0.85rem; font-size: 0.95rem; border-radius: 0.8rem; min-height: 48px; }
        }

        @media (max-width: 480px) {
            .forgot-wrapper { padding: 0.75rem; align-items: flex-start; padding-top: 3.5rem; }
            .forgot-card { padding: 1.75rem 1.2rem; border-radius: 1rem; }
            .forgot-card::before { border-radius: 1rem; }
            .logo-box { width: 54px; height: 54px; border-radius: 0.9rem; }
            .logo-box::after { border-radius: 1.1rem; }
            .forgot-title { font-size: 1.3rem; }
            .form-input { padding: 0.75rem 0.8rem 0.75rem 2.4rem; font-size: 0.875rem; }
            .form-group .input-icon { left: 0.75rem; font-size: 0.9rem; }
            .btn-submit { padding: 0.8rem; font-size: 0.9rem; min-height: 46px; }
            .security-note { font-size: 0.65rem; }
        }

        @media (max-width: 360px) {
            .forgot-wrapper { padding: 0.5rem; padding-top: 3rem; }
            .forgot-card { padding: 1.5rem 1rem; }
            .logo-box { width: 48px; height: 48px; border-radius: 0.8rem; }
            .logo-box::after { border-radius: 1rem; }
            .forgot-title { font-size: 1.15rem; }
            .forgot-subtitle { font-size: 0.75rem; margin-bottom: 1.25rem; }
            .form-input { padding: 0.7rem 0.7rem 0.7rem 2.2rem; font-size: 0.85rem; }
            .form-group .input-icon { left: 0.65rem; font-size: 0.85rem; }
            .btn-submit { padding: 0.7rem; font-size: 0.85rem; min-height: 42px; }
        }

        @media (max-height: 600px) and (orientation: landscape) {
            .forgot-wrapper { align-items: flex-start; padding-top: 1rem; }
            .forgot-card { padding: 1.5rem 1.8rem; margin-bottom: 1rem; }
            .logo-section { margin-bottom: 0.75rem; }
            .logo-box { width: 44px; height: 44px; }
            .forgot-title { font-size: 1.2rem; }
            .forgot-subtitle { margin-bottom: 1rem; font-size: 0.8rem; }
            .form-group { margin-bottom: 0.75rem; }
            .form-input { padding: 0.65rem 1rem 0.65rem 2.4rem; font-size: 0.85rem; }
            .btn-submit { padding: 0.7rem; min-height: 42px; }
            .forgot-footer { margin-top: 1rem; padding-top: 1rem; }
        }
    </style>
</head>
<body>
    <div class="forgot-wrapper">
   

        <div class="stars-container" id="starsContainer"></div>
        <div class="particles" id="particlesContainer"></div>

        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>
        <div class="grid-overlay"></div>

        <div class="forgot-card">
            <div class="logo-section">
                <div class="logo-box">
                    <?php if ($step === 'reset'): ?>
                        <i class="fas fa-sync-alt"></i>
                    <?php else: ?>
                        <i class="fas fa-key"></i>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center">
                <h1 class="forgot-title">
                    <?= $step === 'reset' ? 'รีเซ็ตรหัสผ่าน' : 'ลืมรหัสผ่าน?' ?>
                </h1>
                <p class="forgot-subtitle">
                    <?= $step === 'reset' ? 'กรุณากำหนดรหัสผ่านใหม่' : 'กรอกอีเมลเพื่อรับลิงก์รีเซ็ตรหัสผ่าน' ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>

            <?php if ($step === 'reset' && $tokenExpired): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-clock"></i>
                    <span>ลิงก์รีเซ็ตรหัสผ่านหมดอายุแล้ว กรุณาขอลิงก์ใหม่</span>
                </div>
            <?php endif; ?>

            <?php if ($step === 'request'): ?>
                <form method="POST" novalidate id="forgotForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div class="form-group">
                        <i class="fas fa-envelope input-icon" aria-hidden="true"></i>
                        <input type="email" name="email" id="email" class="form-input"
                               placeholder="กรอกอีเมลของคุณ" required autofocus
                               aria-label="อีเมล" autocomplete="email">
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn" aria-label="ส่งลิงก์รีเซ็ตรหัสผ่าน">
                        <i class="fas fa-paper-plane"></i> ส่งลิงก์รีเซ็ตรหัสผ่าน
                    </button>
                </form>
            <?php else: ?>
                <?php if (empty($urlToken) || !$tokenValid): ?>
                    <?php if (!$tokenExpired): ?>
                        <div class="alert alert-error" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>ลิงก์ไม่ถูกต้อง กรุณาขอลิงก์รีเซ็ตรหัสผ่านใหม่</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center" style="margin-top: 1rem;">
                        <a href="forgot-password.php" class="btn-submit" style="display: inline-flex; width: auto; padding-left: 2rem; padding-right: 2rem;">
                            <i class="fas fa-redo"></i> ขอลิงก์ใหม่
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" novalidate id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($urlToken) ?>">

                        <div class="form-group">
                            <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                            <input type="password" name="password" id="password" class="form-input has-password"
                                   placeholder="รหัสผ่านใหม่ (อย่างน้อย 8 ตัว)" required autofocus
                                   aria-label="รหัสผ่านใหม่" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')" 
                                    tabindex="-1" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>

                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>

                        <div class="form-group">
                            <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input has-password"
                                   placeholder="ยืนยันรหัสผ่านใหม่" required
                                   aria-label="ยืนยันรหัสผ่านใหม่" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')" 
                                    tabindex="-1" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>

                        <div class="match-indicator" id="matchIndicator"></div>

                        <button type="submit" class="btn-submit" id="resetBtn" aria-label="รีเซ็ตรหัสผ่าน">
                            <i class="fas fa-sync-alt"></i> รีเซ็ตรหัสผ่าน
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div class="forgot-footer">
                <a href="index.php" class="login-link">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้า<strong>เข้าสู่ระบบ</strong>
                </a>
            </div>

            <div class="security-note">
                <span><i class="fas fa-clock"></i> ลิงก์มีอายุ 1 ชั่วโมง</span>
                <span>•</span>
                <span><i class="fas fa-shield-alt"></i> ปลอดภัยด้วย Token</span>
            </div>
        </div>
    </div>

    <script>
        // ===== Generate Stars =====
        function createStars() {
            const container = document.getElementById('starsContainer');
            for (let i = 0; i < 70; i++) {
                const star = document.createElement('div');
                star.classList.add('star');
                const size = Math.random() * 2.5 + 1;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.setProperty('--duration', (Math.random() * 3 + 2) + 's');
                star.style.setProperty('--delay', (Math.random() * 5) + 's');
                container.appendChild(star);
            }
        }

        // ===== Generate Particles =====
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            for (let i = 0; i < 15; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                particle.style.left = Math.random() * 100 + '%';
                particle.style.setProperty('--duration', (Math.random() * 10 + 10) + 's');
                particle.style.setProperty('--delay', (Math.random() * 12) + 's');
                particle.style.setProperty('--drift', (Math.random() * 80 - 40) + 'px');
                container.appendChild(particle);
            }
        }

        // ===== Toggle Password =====
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // ===== Password Strength =====
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        
        passwordInput?.addEventListener('input', function() {
            const p = this.value;
            let strength = 0;
            if (p.length >= 8) strength++;
            if (p.match(/[A-Z]/)) strength++;
            if (p.match(/[0-9]/)) strength++;
            if (p.match(/[^A-Za-z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (p.length === 0) { strengthBar.style.width = '0'; }
            else if (strength <= 1) { strengthBar.classList.add('strength-weak'); }
            else if (strength <= 2) { strengthBar.classList.add('strength-medium'); }
            else { strengthBar.classList.add('strength-strong'); }
            checkMatch();
        });

        // ===== Password Match =====
        const confirmInput = document.getElementById('confirm_password');
        const matchIndicator = document.getElementById('matchIndicator');
        
        confirmInput?.addEventListener('input', checkMatch);
        
        function checkMatch() {
            const p = passwordInput?.value || '';
            const c = confirmInput?.value || '';
            if (!c) {
                matchIndicator.innerHTML = '';
                confirmInput?.classList.remove('input-success', 'input-error');
                return;
            }
            if (p === c) {
                matchIndicator.innerHTML = '<i class="fas fa-check-circle"></i> รหัสผ่านตรงกัน';
                matchIndicator.className = 'match-indicator match-success';
                confirmInput?.classList.add('input-success');
                confirmInput?.classList.remove('input-error');
            } else {
                matchIndicator.innerHTML = '<i class="fas fa-times-circle"></i> รหัสผ่านไม่ตรงกัน';
                matchIndicator.className = 'match-indicator match-error';
                confirmInput?.classList.add('input-error');
                confirmInput?.classList.remove('input-success');
            }
        }

        // ===== Email Validation =====
        const emailInput = document.getElementById('email');
        emailInput?.addEventListener('input', function() {
            const email = this.value;
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !regex.test(email)) {
                this.classList.add('input-error');
                this.classList.remove('input-success');
            } else if (email && regex.test(email)) {
                this.classList.remove('input-error');
                this.classList.add('input-success');
            } else {
                this.classList.remove('input-error', 'input-success');
            }
        });

        // ===== Form Submit =====
        let submitted = false;

        document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const email = emailInput?.value.trim();
            if (!email) {
                e.preventDefault();
                emailInput?.classList.add('input-error');
                setTimeout(() => emailInput?.classList.remove('input-error'), 2000);
                return;
            }
            if (submitted) { e.preventDefault(); return; }
            submitted = true;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> กำลังส่ง...';
        });

        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('resetBtn');
            const p = passwordInput?.value || '';
            const c = confirmInput?.value || '';
            let err = false;
            if (!p || p.length < 8) { passwordInput?.classList.add('input-error'); err = true; }
            if (p !== c) { confirmInput?.classList.add('input-error'); err = true; }
            if (err) {
                e.preventDefault();
                setTimeout(() => document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error')), 2000);
                return;
            }
            if (submitted) { e.preventDefault(); return; }
            submitted = true;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> กำลังรีเซ็ต...';
        });

        // ===== Viewport Height =====
        function setVH() {
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        }
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', () => setTimeout(setVH, 100));
        setVH();

        // ===== Mouse Parallax =====
        document.addEventListener('mousemove', function(e) {
            const orbs = document.querySelectorAll('.orb');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            orbs.forEach((orb, i) => {
                const speed = (i + 1) * 12;
                orb.style.transform = `translate(${(x - 0.5) * speed}px, ${(y - 0.5) * speed}px)`;
            });
        });

        // ===== Init =====
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            createParticles();
            const first = document.querySelector('.input-field');
            if (first && !first.value) first.focus();
        });
    </script>
</body>
</html>