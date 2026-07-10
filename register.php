<?php
/**
 * หน้าสมัครสมาชิก (Frontend) – Dark Theme พร้อมลูกเล่น
 * - ฟิลด์ reporter_code, username, password, confirm
 * - ตรวจสอบ: รหัสผ่าน >=8, ตรงกัน, reporter_code ไม่ว่าง, username ไม่ซ้ำ
 * - ล็อกอินอัตโนมัติเมื่อสมัครสำเร็จ
 * - Responsive Full Screen Design
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request (CSRF token ไม่ถูกต้อง)';
    } else {
        $username      = trim($_POST['username'] ?? '');
        $password      = $_POST['password'] ?? '';
        $confirm       = $_POST['confirm_password'] ?? '';
        $reporter_code = trim($_POST['reporter_code'] ?? '');

        // Validation
        if (empty($reporter_code)) {
            $error = 'กรุณากรอกรหัสผู้รายงาน';
        } elseif (empty($username)) {
            $error = 'กรุณากรอกชื่อผู้ใช้';
        } elseif (strlen($username) < 3) {
            $error = 'ชื่อผู้ใช้ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'ชื่อผู้ใช้ใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข และ _ เท่านั้น';
        } elseif (strlen($password) < 8) {
            $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว';
        } elseif ($password !== $confirm) {
            $error = 'รหัสผ่านไม่ตรงกัน';
        } else {
            // Check username uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น';
            } else {
                // Check reporter_code uniqueness
                $stmt = $pdo->prepare("SELECT id FROM users WHERE reporter_code = ?");
                $stmt->execute([$reporter_code]);
                if ($stmt->fetch()) {
                    $error = 'รหัสผู้รายงานนี้มีอยู่แล้ว';
                } else {
                    // Insert new user
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, reporter_code, enabled) VALUES (?, ?, ?, ?, 1)");
                    
                    try {
                        $stmt->execute([$username, $hashed, 'user', $reporter_code]);
                        $userId = $pdo->lastInsertId();

                        // Auto login after registration
                        session_regenerate_id(true);
                        $_SESSION['user_id']   = $userId;
                        $_SESSION['username']  = $username;
                        $_SESSION['role']      = 'user';
                        $_SESSION['avatar']    = 'default.png';
                        
                        // Redirect to dashboard
                        redirect('dashboard.php');
                    } catch (PDOException $e) {
                        $error = 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่';
                        error_log('Registration error: ' . $e->getMessage());
                    }
                }
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
    <title>สมัครสมาชิก - ระบบจัดการความเสี่ยง</title>
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
        .register-wrapper {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
            background: radial-gradient(ellipse at 30% 60%, #1e3a5f 0%, transparent 50%),
                        radial-gradient(ellipse at 70% 30%, #1a2744 0%, transparent 50%),
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
            50% { opacity: 1; transform: scale(1.8); }
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
            width: 500px;
            height: 500px;
            background: #8b5cf6;
            top: -15%;
            right: -10%;
            --duration: 13s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: #06b6d4;
            bottom: -10%;
            left: -5%;
            --duration: 16s;
            animation-delay: -5s;
        }

        .orb-3 {
            width: 300px;
            height: 300px;
            background: #3b82f6;
            top: 40%;
            left: 50%;
            --duration: 19s;
            animation-delay: -9s;
        }

        .orb-4 {
            width: 200px;
            height: 200px;
            background: #10b981;
            bottom: 30%;
            right: 25%;
            --duration: 15s;
            animation-delay: -7s;
        }

        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(25px, -20px) scale(1.1); }
            66% { transform: translate(-15px, 15px) scale(0.95); }
            100% { transform: translate(10px, -5px) scale(1.05); }
        }

        /* ===== Grid Lines ===== */
        .grid-overlay {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(139, 92, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(139, 92, 246, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 1;
            pointer-events: none;
            animation: gridMove 25s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
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
            background: rgba(139, 92, 246, 0.6);
            border-radius: 50%;
            animation: floatUp var(--duration) linear infinite;
            animation-delay: var(--delay);
            opacity: 0;
            box-shadow: 0 0 8px rgba(139, 92, 246, 0.6), 0 0 16px rgba(139, 92, 246, 0.3);
        }

        @keyframes floatUp {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 0.8; }
            100% {
                transform: translateY(-100px) translateX(var(--drift));
                opacity: 0;
            }
        }

        /* ===== Register Card ===== */
        .register-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius);
            padding: 2.5rem 2.2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 40px rgba(139, 92, 246, 0.1);
            animation: cardEntry 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        /* Card glow effect */
        .register-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: var(--radius);
            padding: 1px;
            background: linear-gradient(135deg, 
                rgba(139, 92, 246, 0.3),
                transparent 30%,
                transparent 70%,
                rgba(6, 182, 212, 0.3));
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
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            border-radius: 1.2rem;
            box-shadow: 0 8px 32px -8px rgba(139, 92, 246, 0.5);
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
                rgba(139, 92, 246, 0.4),
                transparent,
                rgba(6, 182, 212, 0.4),
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

        .register-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            text-shadow: 0 2px 10px rgba(139, 92, 246, 0.3);
        }

        .register-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15),
                        0 0 20px rgba(139, 92, 246, 0.1);
        }

        .form-group:focus-within .input-icon {
            color: #a78bfa;
            text-shadow: 0 0 10px rgba(139, 92, 246, 0.5);
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
            color: #a78bfa;
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

        /* ===== Password Match Indicator ===== */
        .match-indicator {
            font-size: 0.75rem;
            margin-top: -0.5rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 20px;
        }

        .match-success {
            color: #34d399;
        }

        .match-error {
            color: #f87171;
        }

        /* ===== Submit Button ===== */
        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            border: none;
            border-radius: var(--radius-sm);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px -4px rgba(124, 58, 237, 0.5),
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::after {
            left: 100%;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #6d28d9, #7c3aed);
            transform: translateY(-2px);
            box-shadow: 0 8px 30px -4px rgba(124, 58, 237, 0.6),
                        0 0 20px rgba(139, 92, 246, 0.3);
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
        .register-footer {
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
            color: #a78bfa;
        }

        .login-link strong {
            color: #a78bfa;
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
            border-color: #8b5cf6;
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
        }

        /* ===== Validation States ===== */
        .form-input.input-success {
            border-color: rgba(16, 185, 129, 0.5) !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input.input-error {
            border-color: rgba(239, 68, 68, 0.5) !important;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
            background: rgba(239, 68, 68, 0.05);
        }

        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 768px) {
            .register-wrapper {
                padding: 1rem;
            }

            .register-card {
                max-width: 420px;
                padding: 2rem 1.8rem;
            }

            .orb-1 { width: 350px; height: 350px; }
            .orb-2 { width: 300px; height: 300px; }
            .orb-3 { width: 200px; height: 200px; }
            .orb-4 { width: 150px; height: 150px; }

            .back-home {
                top: 1rem;
                left: 1rem;
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .register-card {
                max-width: 100%;
                padding: 2rem 1.5rem;
                border-radius: 1.2rem;
            }

            .register-card::before {
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

            .register-title {
                font-size: 1.4rem;
            }

            .register-subtitle {
                font-size: 0.85rem;
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
        }

        @media (max-width: 480px) {
            .register-wrapper {
                padding: 0.75rem;
                align-items: flex-start;
                padding-top: 3.5rem;
            }

            .register-card {
                padding: 1.75rem 1.2rem;
                border-radius: 1rem;
            }

            .register-card::before {
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

            .register-title {
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
            .register-wrapper {
                padding: 0.5rem;
                padding-top: 3rem;
            }

            .register-card {
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

            .register-title {
                font-size: 1.15rem;
            }

            .register-subtitle {
                font-size: 0.75rem;
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
            .register-wrapper {
                align-items: flex-start;
                padding-top: 1rem;
            }

            .register-card {
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

            .register-title {
                font-size: 1.2rem;
            }

            .register-subtitle {
                margin-bottom: 1rem;
                font-size: 0.8rem;
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

            .register-footer {
                margin-top: 1rem;
                padding-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">

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

        <!-- Register Card -->
        <div class="register-card">
            <!-- Logo -->
            <div class="logo-section">
                <div class="logo-box">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>

            <!-- Titles -->
            <div class="text-center">
                <h1 class="register-title">สมัครสมาชิก</h1>
                <p class="register-subtitle">สร้างบัญชีผู้ใช้ใหม่เพื่อเข้าใช้งานระบบ</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Register Form -->
            <form method="POST" novalidate id="registerForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <!-- Reporter Code -->
                <div class="form-group">
                    <i class="fas fa-id-card input-icon" aria-hidden="true"></i>
                    <input type="text" name="reporter_code" id="reporter_code" class="form-input"
                           placeholder="รหัสผู้รายงาน (เช่น R10001)" required autofocus
                           aria-label="รหัสผู้รายงาน" maxlength="20">
                </div>

                <!-- Username -->
                <div class="form-group">
                    <i class="fas fa-user input-icon" aria-hidden="true"></i>
                    <input type="text" name="username" id="username" class="form-input"
                           placeholder="ชื่อผู้ใช้ (ภาษาอังกฤษ ตัวเลข หรือ _)" required
                           aria-label="ชื่อผู้ใช้" autocomplete="username" maxlength="50">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" name="password" id="password" class="form-input has-password"
                           placeholder="รหัสผ่าน (อย่างน้อย 8 ตัว)" required
                           aria-label="รหัสผ่าน" autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')" 
                            tabindex="-1" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </button>
                </div>

                <!-- Password Strength Indicator -->
                <div class="password-strength">
                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input has-password"
                           placeholder="ยืนยันรหัสผ่าน" required
                           aria-label="ยืนยันรหัสผ่าน" autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')" 
                            tabindex="-1" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </button>
                </div>

                <!-- Password Match Indicator -->
                <div class="match-indicator" id="matchIndicator"></div>

                <button type="submit" class="btn-submit" id="registerBtn" aria-label="สมัครสมาชิก">
                    <i class="fas fa-user-check"></i> สมัครสมาชิก
                </button>
            </form>

            <!-- Login Link -->
            <div class="register-footer">
                <a href="index.php" class="login-link">
                    มีบัญชีแล้ว? <strong>เข้าสู่ระบบ</strong>
                    <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
                </a>
            </div>

            <!-- Security Badges -->
            <div class="security-note">
                <span><i class="fas fa-shield-alt"></i> CSRF Protection</span>
                <span>•</span>
                <span><i class="fas fa-lock"></i> Password Hashing</span>
                <span>•</span>
                <span><i class="fas fa-user-check"></i> Unique Username</span>
            </div>
        </div>
    </div>

    <script>
        // ===== Generate Stars =====
        function createStars() {
            const container = document.getElementById('starsContainer');
            const starCount = 80;
            
            for (let i = 0; i < starCount; i++) {
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

        // ===== Generate Floating Particles =====
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                particle.style.left = Math.random() * 100 + '%';
                particle.style.setProperty('--duration', (Math.random() * 10 + 10) + 's');
                particle.style.setProperty('--delay', (Math.random() * 12) + 's');
                particle.style.setProperty('--drift', (Math.random() * 80 - 40) + 'px');
                
                container.appendChild(particle);
            }
        }

        // ===== Toggle Password Visibility =====
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
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
        
        // ===== Password Strength Checker =====
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        
        passwordInput?.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
            } else if (strength <= 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
            
            // Update match indicator
            checkPasswordMatch();
        });
        
        // ===== Real-time Password Match Check =====
        const confirmPasswordInput = document.getElementById('confirm_password');
        const matchIndicator = document.getElementById('matchIndicator');
        
        confirmPasswordInput?.addEventListener('input', function() {
            checkPasswordMatch();
        });
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmPasswordInput.value;
            
            if (!confirm) {
                matchIndicator.innerHTML = '';
                confirmPasswordInput.classList.remove('input-success', 'input-error');
                return;
            }
            
            if (password === confirm) {
                matchIndicator.innerHTML = '<i class="fas fa-check-circle"></i> รหัสผ่านตรงกัน';
                matchIndicator.className = 'match-indicator match-success';
                confirmPasswordInput.classList.add('input-success');
                confirmPasswordInput.classList.remove('input-error');
            } else {
                matchIndicator.innerHTML = '<i class="fas fa-times-circle"></i> รหัสผ่านไม่ตรงกัน';
                matchIndicator.className = 'match-indicator match-error';
                confirmPasswordInput.classList.add('input-error');
                confirmPasswordInput.classList.remove('input-success');
            }
        }
        
        // ===== Username Validation =====
        const usernameInput = document.getElementById('username');
        
        usernameInput?.addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9_]+$/;
            
            if (username && !regex.test(username)) {
                this.classList.add('input-error');
                this.classList.remove('input-success');
            } else if (username && regex.test(username)) {
                this.classList.remove('input-error');
                this.classList.add('input-success');
            } else {
                this.classList.remove('input-error', 'input-success');
            }
        });
        
        // ===== Form Submission =====
        let formSubmitted = false;
        
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            const registerBtn = document.getElementById('registerBtn');
            const reporterCode = document.getElementById('reporter_code').value.trim();
            const username = usernameInput.value.trim();
            const password = passwordInput.value;
            const confirm = confirmPasswordInput.value;
            
            let hasError = false;
            
            // Validate reporter code
            if (!reporterCode) {
                document.getElementById('reporter_code').classList.add('input-error');
                hasError = true;
            }
            
            // Validate username
            if (!username) {
                usernameInput.classList.add('input-error');
                hasError = true;
            }
            
            // Validate password
            if (!password || password.length < 8) {
                passwordInput.classList.add('input-error');
                hasError = true;
            }
            
            // Validate confirm password
            if (password !== confirm) {
                confirmPasswordInput.classList.add('input-error');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                
                // Reset error states after 2 seconds
                setTimeout(() => {
                    document.querySelectorAll('.input-error').forEach(field => {
                        field.classList.remove('input-error');
                    });
                }, 2500);
                
                return;
            }
            
            // Prevent double submission
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            
            formSubmitted = true;
            registerBtn.disabled = true;
            registerBtn.innerHTML = '<span class="spinner"></span> กำลังสมัครสมาชิก...';
        });
        
        // ===== Keyboard Shortcut =====
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const form = document.getElementById('registerForm');
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
                const speed = (index + 1) * 12;
                const moveX = (x - 0.5) * speed;
                const moveY = (y - 0.5) * speed;
                orb.style.transform = `translate(${moveX}px, ${moveY}px)`;
            });
        });
        
        // ===== Init =====
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            createParticles();
            
            const reporterInput = document.getElementById('reporter_code');
            if (reporterInput && !reporterInput.value) {
                reporterInput.focus();
            }
            
            console.log('%c🔐 Dark Theme Register %c| %cReady',
                'color: #8b5cf6; font-weight: bold; font-size: 1.1em;',
                '',
                'color: #34d399;');
            console.log('%c✨ Stars: 80 | Particles: 15 | Orbs: 4', 'color: #64748b;');
            console.log('%c📱 Viewport: ' + window.innerWidth + 'x' + window.innerHeight, 'color: #64748b;');
            console.log('%c🛡️ Security: CSRF + Password Hashing + Unique Username', 'color: #34d399;');
            console.log('%c💪 Password Strength Checker: Active', 'color: #f59e0b;');
        });
    </script>
</body>
</html>