<?php
/**
 * Header Template - รวมไฟล์ CSS, JS และ Meta Tags
 * ใช้สำหรับทุกหน้าในระบบ Risk Management
 */
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

// Generate CSRF Token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="ระบบบริหารความเสี่ยง - ศูนย์อนามัยที่ 8 อุดรธานี">
    <meta name="author" content="Risk Management System">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="ระบบบริหารความเสี่ยง">
    <meta property="og:description" content="ระบบบริหารความเสี่ยง - ศูนย์อนามัยที่ 8 อุดรธานี">
    <meta property="og:type" content="website">
    
    <!-- Theme Color -->
    <meta name="theme-color" content="#1e3a8a">
    
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>ระบบบริหารความเสี่ยง</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
      <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sarabun': ['Sarabun', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts - Sarabun -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">

    <!-- Cookie Consent JavaScript -->
    <script src="assets/js/cookie-consent.js" defer></script>

    <!-- Base Styles -->
    <style>
        /* ==================== RESET & BASE ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==================== SCROLLBAR ==================== */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
            transition: background 0.3s;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Firefox Scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        /* ==================== SELECTION ==================== */
        ::selection {
            background: rgba(37, 99, 235, 0.2);
            color: #1e293b;
        }

        ::-moz-selection {
            background: rgba(37, 99, 235, 0.2);
            color: #1e293b;
        }

        /* ==================== FOCUS STYLES ==================== */
        :focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ==================== ANIMATIONS ==================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        .animate-slide-in {
            animation: slideInRight 0.4s ease forwards;
        }

        .animate-pulse-slow {
            animation: pulse 3s ease-in-out infinite;
        }

        /* ==================== LOADING SKELETON ==================== */
        .skeleton {
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
            border-radius: 0.5rem;
        }

        .skeleton-text {
            height: 1rem;
            margin-bottom: 0.5rem;
        }

        .skeleton-title {
            height: 1.5rem;
            width: 60%;
            margin-bottom: 1rem;
        }

        .skeleton-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        /* ==================== TOOLTIP ==================== */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            padding: 0.4rem 0.8rem;
            background: #1e293b;
            color: white;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.4rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            pointer-events: none;
            z-index: 1000;
        }

        [data-tooltip]:hover:before {
            opacity: 1;
            visibility: visible;
        }

        /* ==================== PRINT STYLES ==================== */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }

            .sidebar,
            .no-print,
            .btn-action,
            button,
            .dropdown-menu {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            a {
                text-decoration: none !important;
                color: black !important;
            }

            @page {
                margin: 1.5cm;
                size: A4;
            }
        }

        .print-only {
            display: none;
        }

        /* ==================== ACCESSIBILITY ==================== */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* ==================== REDUCED MOTION ==================== */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ==================== HIGH CONTRAST ==================== */
        @media (prefers-contrast: high) {
            body {
                background: white;
            }
            
            .sidebar-gradient {
                background: #0f172a;
            }
        }

        /* ==================== DARK MODE SUPPORT ==================== */
        @media (prefers-color-scheme: dark) {
            /* สามารถเพิ่ม Dark Mode styles ได้ที่นี่ */
            /* ตัวอย่าง:
            body {
                background: #0f172a;
                color: #e2e8f0;
            }
            */
        }
    </style>
</head>

<body class="font-sarabun antialiased">
    <!-- Skip to main content (Accessibility) -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white focus:rounded-lg">
        ข้ามไปยังเนื้อหาหลัก
    </a>

    <!-- Loading Overlay (สำหรับใช้กับ AJAX requests) -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-8 flex flex-col items-center gap-4 shadow-2xl">
            <div class="w-12 h-12 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
            <p class="text-gray-600 font-medium">กำลังโหลด...</p>
        </div>
    </div>

    <!-- Toast Container (สำหรับการแจ้งเตือนแบบ Toast) -->
    <div id="toastContainer" class="fixed top-4 right-4 z-[9998] flex flex-col gap-2"></div>

    <!-- Main Content Wrapper -->
    <div id="main-content" class="flex h-screen overflow-hidden">