<?php
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isAdmin();

// นับจำนวนความเสี่ยงที่ยังไม่ดำเนินการ (สำหรับแสดง badge)
$pending_risk_count = 0;
if (isset($pdo)) {
    try {
        if ($is_admin) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM risks WHERE status IS NULL OR status = '' OR status = 'ยังไม่ดำเนินการ'");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ? AND (status IS NULL OR status = '' OR status = 'ยังไม่ดำเนินการ')");
            $stmt->execute([$_SESSION['user_id']]);
        }
        $pending_risk_count = $stmt->fetchColumn();
    } catch (Exception $e) {
        $pending_risk_count = 0;
    }
}

// นับจำนวนผู้ใช้ทั้งหมด (เฉพาะ Admin)
$user_count = 0;
if ($is_admin && isset($pdo)) {
    try {
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Exception $e) {
        $user_count = 0;
    }
}

// ฟังก์ชันสำหรับเช็คว่าเมนู active หรือไม่ (รวมถึง sub-pages)
function isMenuActive($page, $current_page, $sub_pages = []) {
    if ($current_page == $page) return true;
    foreach ($sub_pages as $sub) {
        if ($current_page == $sub) return true;
    }
    return false;
}
?>
<!-- Sidebar สวยงาม -->
<div class="w-60 sidebar-gradient flex flex-col h-full shadow-2xl">
    <!-- Logo Section -->
    <div class="px-5 py-5 border-b border-white/[0.08] bg-white/[0.02]">
        <div class="flex items-center gap-3">
            <div class="logo-wrapper">
                <div class="logo-ring"></div>
                <div class="logo-glow"></div>
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png"
                    alt="Logo" class="logo-image"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="logo-fallback" style="display:none;">
                    <i class="fas fa-hospital-alt text-xl"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-sm font-bold text-white leading-tight">Risk Management</h1>
                <p class="text-[10px] text-blue-300/70 leading-tight mt-0.5">ระบบบริหารความเสี่ยง</p>
                <p class="text-[9px] text-blue-300/50 leading-tight mt-0.5">ศูนย์อนามัยที่ 8 อุดรธานี</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="px-3 py-4 flex-1 space-y-0.5 overflow-y-auto scrollbar-thin">
        
        <!-- Dashboard (เฉพาะ Admin) -->
        <?php if ($is_admin): ?>
            <div class="nav-section">
                <div class="nav-section-label">
                    <i class="fas fa-crown text-amber-400 text-[8px] mr-1"></i> ผู้ดูแลระบบ
                </div>
                <a href="dashboard.php" class="menu-item <?= isMenuActive('dashboard.php', $current_page) ? 'active' : '' ?>">
                    <div class="menu-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="menu-content">
                        <span class="menu-text">ภาพรวมระบบ</span>
                        <span class="menu-badge menu-badge-admin">Admin</span>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- เมนูหลัก -->
        <div class="nav-section">
            <div class="nav-section-label">
                <i class="fas fa-th-large text-blue-400 text-[8px] mr-1"></i> เมนูหลัก
            </div>
            
            <!-- เมนูรายการความเสี่ยง (รวมหน้า view, report) -->
            <a href="risks.php" class="menu-item <?= isMenuActive('risks.php', $current_page, ['view_risk.php', 'report_summary.php', 'view_report.php']) ? 'active' : '' ?>">
                <div class="menu-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="menu-content">
                    <span class="menu-text">รายการความเสี่ยง</span>
                    <?php if ($pending_risk_count > 0): ?>
                        <span class="menu-badge menu-badge-pending"><?= number_format($pending_risk_count) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            
            <!-- เมนูเพิ่มความเสี่ยง (แยกอิสระ) -->
            <a href="risk_form.php" class="menu-item <?= isMenuActive('risk_form.php', $current_page) ? 'active' : '' ?>">
                <div class="menu-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="menu-content">
                    <span class="menu-text">เพิ่มความเสี่ยง</span>
                </div>
            </a>
        </div>

        <!-- จัดการระบบ (เฉพาะ Admin) -->
        <?php if ($is_admin): ?>
            <div class="nav-section">
                <div class="nav-section-label">
                    <i class="fas fa-cog text-slate-400 text-[8px] mr-1"></i> จัดการระบบ
                </div>
                <a href="users.php" class="menu-item <?= isMenuActive('users.php', $current_page, ['user_form.php', 'view_user.php']) ? 'active' : '' ?>">
                    <div class="menu-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="menu-content">
                        <span class="menu-text">จัดการผู้ใช้</span>
                        <?php if ($user_count > 0): ?>
                            <span class="menu-badge menu-badge-users"><?= number_format($user_count) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <!-- User Bottom -->
    <div class="p-3 border-t border-white/[0.08] bg-white/[0.02]">
        <div class="flex items-center gap-2.5">
            <div class="flex items-center gap-2.5 flex-1 min-w-0 p-1.5">
                <div class="relative">
                    <img src="avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>"
                        alt="avatar" class="w-9 h-9 rounded-full object-cover border-2 border-white/10"
                        onerror="this.src='avatars/default.png'">
                    <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 rounded-full border-2 border-slate-800"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white text-xs font-semibold truncate leading-tight"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></div>
                    <div class="text-[10px] text-slate-400 truncate leading-tight"><?= $is_admin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน' ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn group" title="ออกจากระบบ" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="logout-tooltip">ออกจากระบบ</span>
            </a>
        </div>
    </div>
</div>

<script>
    // ========== Logout with SweetAlert2 ==========
    document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'ออกจากระบบ?',
            html: '<p class="text-slate-600">คุณต้องการออกจากระบบใช่หรือไม่</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ',
            cancelButtonText: '<i class="fas fa-times mr-1"></i> ยกเลิก',
            reverseButtons: true,
            customClass: {
                popup: 'swal-popup',
                title: 'swal-title',
                htmlContainer: 'swal-html',
                confirmButton: 'swal-confirm-btn',
                cancelButton: 'swal-cancel-btn',
                icon: 'swal-icon'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'กำลังออกจากระบบ...',
                    html: '<p class="text-slate-500">กรุณารอสักครู่</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                setTimeout(() => {
                    window.location.href = 'logout.php';
                }, 600);
            }
        });
    });
</script>

<style>
    /* ========== Sidebar Core ========== */
    .sidebar-gradient {
        background: linear-gradient(195deg, #0f172a 0%, #1e3a8a 25%, #312e81 60%, #1e1b4b 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .sidebar-gradient::before {
        content: '';
        position: absolute;
        top: -20%;
        right: -40%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(96, 165, 250, 0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .sidebar-gradient::after {
        content: '';
        position: absolute;
        bottom: 10%;
        left: -30%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(167, 139, 250, 0.06) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    /* ========== Logo ========== */
    .logo-wrapper {
        position: relative;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .logo-ring {
        position: absolute;
        inset: -2px;
        border: 1.5px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: ringSpin 10s linear infinite;
    }
    
    .logo-ring::before {
        content: '';
        position: absolute;
        top: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 5px;
        height: 5px;
        background: #60a5fa;
        border-radius: 50%;
        box-shadow: 0 0 8px #60a5fa;
    }
    
    .logo-glow {
        position: absolute;
        inset: -8px;
        background: radial-gradient(circle, rgba(96, 165, 250, 0.15) 0%, transparent 70%);
        border-radius: 50%;
        animation: pulse 3s ease-in-out infinite;
    }
    
    @keyframes ringSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.05); }
    }
    
    .logo-image {
        width: 40px;
        height: 40px;
        object-fit: contain;
        border-radius: 50%;
        background: white;
        padding: 2px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        position: relative;
        z-index: 2;
        transition: transform 0.3s ease;
    }
    
    .logo-image:hover {
        transform: scale(1.1) rotate(5deg);
    }
    
    .logo-fallback {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        position: relative;
        z-index: 2;
    }

    /* ========== Navigation ========== */
    .nav-section {
        margin-bottom: 0.25rem;
    }
    
    .nav-section-label {
        font-size: 0.6rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.25);
        text-transform: uppercase;
        letter-spacing: 1.2px;
        padding: 0.65rem 0.7rem 0.3rem;
        display: flex;
        align-items: center;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.6rem 0.75rem;
        border-radius: 0.65rem;
        color: rgba(255, 255, 255, 0.55);
        font-weight: 500;
        font-size: 0.84rem;
        transition: all 0.25s ease;
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }
    
    .menu-item:hover {
        background: rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.85);
    }
    
    .menu-item.active {
        background: rgba(255, 255, 255, 0.08);
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .menu-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.04);
        font-size: 0.85rem;
        flex-shrink: 0;
        transition: all 0.25s ease;
    }
    
    .menu-item:hover .menu-icon {
        background: rgba(255, 255, 255, 0.08);
        transform: scale(1.05);
    }
    
    .menu-item.active .menu-icon {
        background: rgba(59, 130, 246, 0.25);
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
        color: #93c5fd;
    }

    .menu-content {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }
    
    .menu-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .menu-badge {
        font-size: 0.6rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 9999px;
        letter-spacing: 0.3px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    .menu-badge-admin {
        background: rgba(251, 191, 36, 0.2);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.3);
    }
    
    .menu-badge-pending {
        background: rgba(251, 191, 36, 0.2);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.3);
        animation: badgePulse 2s ease-in-out infinite;
    }
    
    @keyframes badgePulse {
        0%, 100% { opacity: 0.8; }
        50% { opacity: 1; }
    }
    
    .menu-badge-users {
        background: rgba(167, 139, 250, 0.25);
        color: #c4b5fd;
        border: 1px solid rgba(167, 139, 250, 0.3);
    }

    /* ========== Logout Button ========== */
    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.3);
        text-decoration: none;
        transition: all 0.25s ease;
        flex-shrink: 0;
        cursor: pointer;
        position: relative;
    }
    
    .logout-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #fca5a5;
        transform: translateX(-1px);
    }
    
    .logout-btn i {
        font-size: 1rem;
        transition: transform 0.25s ease;
    }
    
    .logout-btn:hover i {
        transform: translateX(-2px);
    }
    
    .logout-tooltip {
        position: absolute;
        right: calc(100% + 10px);
        top: 50%;
        transform: translateY(-50%);
        background: #1e293b;
        color: white;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 0.35rem 0.7rem;
        border-radius: 0.4rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        pointer-events: none;
    }
    
    .logout-tooltip::after {
        content: '';
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-left-color: #1e293b;
    }
    
    .logout-btn:hover .logout-tooltip {
        opacity: 1;
        visibility: visible;
    }

    /* ========== Scrollbar ========== */
    .scrollbar-thin::-webkit-scrollbar {
        width: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.06);
        border-radius: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    /* ========== SweetAlert2 Custom ========== */
    .swal-popup {
        border-radius: 1.2rem !important;
        padding: 2rem !important;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25) !important;
    }
    
    .swal-title {
        font-family: 'Sarabun', sans-serif !important;
        font-weight: 700 !important;
        font-size: 1.3rem !important;
        color: #1e293b !important;
    }
    
    .swal-html {
        font-family: 'Sarabun', sans-serif !important;
    }
    
    .swal-confirm-btn {
        border-radius: 0.6rem !important;
        font-family: 'Sarabun', sans-serif !important;
        font-weight: 600 !important;
        padding: 0.6rem 1.5rem !important;
        font-size: 0.85rem !important;
        transition: all 0.2s ease !important;
    }
    
    .swal-confirm-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    
    .swal-cancel-btn {
        border-radius: 0.6rem !important;
        font-family: 'Sarabun', sans-serif !important;
        font-weight: 500 !important;
        padding: 0.6rem 1.5rem !important;
        font-size: 0.85rem !important;
        transition: all 0.2s ease !important;
    }
    
    .swal-cancel-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.2);
    }
    
    .swal-icon {
        border-width: 3px !important;
    }

    /* ========== Responsive ========== */
    @media (max-width: 768px) {
        .w-60 {
            width: 220px;
        }
        
        .menu-item {
            font-size: 0.8rem;
            padding: 0.55rem 0.65rem;
        }
        
        .menu-icon {
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }
    }
</style>