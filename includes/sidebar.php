<?php
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isAdmin();
?>
<!-- Sidebar กระชับ -->
<div class="w-56 sidebar-gradient flex flex-col h-full shadow-2xl">
    <!-- Logo Section -->
    <div class="p-4 bg-white/5 border-b border-white/10">
        <div class="flex flex-col items-center">
            <div class="logo-wrapper mb-2">
                <div class="logo-ring"></div>
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png"
                    alt="Logo" class="logo-image"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="logo-fallback" style="display:none;"><i class="fas fa-hospital-alt"></i></div>
            </div>
            <h1 class="text-sm font-bold text-white">Risk Management</h1>
            <p class="text-[9px] text-blue-300/60 mt-0.5">ศูนย์อนามัยที่ 8 อุดรธานี</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="px-2.5 py-3 flex-1 space-y-0.5 overflow-y-auto">
        <?php if ($is_admin): ?>
            <div class="nav-section-label">ผู้ดูแลระบบ</div>
            <a href="dashboard.php" class="menu-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <div class="menu-icon"><i class="fas fa-tachometer-alt"></i></div>
                <span>ภาพรวมระบบ</span>
            </a>
        <?php endif; ?>

        <div class="nav-section-label">เมนูหลัก</div>
        
        <a href="risks.php" class="menu-item <?= $current_page == 'risks.php' ? 'active' : '' ?>">
            <div class="menu-icon"><i class="fas fa-clipboard-list"></i></div>
            <span>รายการความเสี่ยง</span>
        </a>
        
        <a href="risk_form.php" class="menu-item <?= $current_page == 'risk_form.php' ? 'active' : '' ?>">
            <div class="menu-icon"><i class="fas fa-plus-circle"></i></div>
            <span>เพิ่มความเสี่ยง</span>
        </a>
        
        <a href="report_summary.php" class="menu-item <?= $current_page == 'report_summary.php' ? 'active' : '' ?>">
            <div class="menu-icon"><i class="fas fa-file-alt"></i></div>
            <span>สรุปผลการรายงาน</span>
        </a>

        <?php if ($is_admin): ?>
            <div class="nav-section-label">จัดการระบบ</div>
            <a href="users.php" class="menu-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <div class="menu-icon"><i class="fas fa-users-cog"></i></div>
                <span>จัดการผู้ใช้</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- User Bottom (กระชับ) -->
    <div class="p-2.5 border-t border-white/10 bg-white/[0.02]">
        <div class="flex items-center gap-2">
            <a href="profile.php" class="flex items-center gap-2 flex-1 min-w-0 p-1.5 rounded-lg hover:bg-white/5 transition-all">
                <img src="avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>"
                    alt="avatar" class="w-8 h-8 rounded-full object-cover border border-white/10"
                    onerror="this.src='avatars/default.png'">
                <span class="text-white text-xs font-medium truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></span>
            </a>
            <a href="logout.php" class="logout-btn" title="ออกจากระบบ" onclick="return confirm('ออกจากระบบ?');">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</div>

<style>
    .sidebar-gradient {
        background: linear-gradient(195deg, #0f172a 0%, #1e3a8a 30%, #312e81 65%, #1e1b4b 100%);
        color: white; position: relative; overflow: hidden;
    }
    .sidebar-gradient::before {
        content: ''; position: absolute; top: -15%; right: -30%;
        width: 250px; height: 250px;
        background: radial-gradient(circle, rgba(96,165,250,0.08) 0%, transparent 70%);
        border-radius: 50%; pointer-events: none;
    }

    /* Logo */
    .logo-wrapper { position: relative; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; }
    .logo-ring {
        position: absolute; inset: 0; border: 1.5px solid rgba(255,255,255,0.12);
        border-radius: 50%; animation: ringSpin 8s linear infinite;
    }
    .logo-ring::before {
        content: ''; position: absolute; top: -2px; left: 50%; transform: translateX(-50%);
        width: 4px; height: 4px; background: #60a5fa; border-radius: 50%;
    }
    @keyframes ringSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .logo-image {
        width: 44px; height: 44px; object-fit: contain; border-radius: 50%;
        background: white; padding: 2px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        position: relative; z-index: 2; transition: transform 0.3s;
    }
    .logo-image:hover { transform: scale(1.1); }
    .logo-fallback {
        width: 44px; height: 44px; border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }

    /* Nav */
    .nav-section-label {
        font-size: 0.55rem; font-weight: 700; color: rgba(255,255,255,0.2);
        text-transform: uppercase; letter-spacing: 1.5px;
        padding: 0.6rem 0.6rem 0.25rem;
    }

    .menu-item {
        display: flex; align-items: center; gap: 0.55rem;
        padding: 0.55rem 0.65rem; border-radius: 0.6rem;
        color: rgba(255,255,255,0.6); font-weight: 500;
        font-size: 0.82rem; transition: all 0.2s ease;
        text-decoration: none; position: relative;
    }
    .menu-item:hover { background: rgba(255,255,255,0.06); color: white; }
    .menu-item.active { background: rgba(255,255,255,0.1); color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-weight: 600; }

    .menu-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.05); font-size: 0.8rem;
        flex-shrink: 0; transition: all 0.2s;
    }
    .menu-item:hover .menu-icon { background: rgba(255,255,255,0.1); }
    .menu-item.active .menu-icon { background: rgba(59,130,246,0.3); box-shadow: 0 0 10px rgba(59,130,246,0.2); }

    /* Logout */
    .logout-btn {
        display: flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 8px;
        color: rgba(255,255,255,0.3); text-decoration: none;
        transition: all 0.2s; flex-shrink: 0;
    }
    .logout-btn:hover { background: rgba(239,68,68,0.15); color: #fca5a5; }
    .logout-btn i { font-size: 0.95rem; transition: transform 0.2s; }
    .logout-btn:hover i { transform: translateX(-2px); }

    /* Scrollbar */
    nav::-webkit-scrollbar { width: 2px; }
    nav::-webkit-scrollbar-track { background: transparent; }
    nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.06); border-radius: 2px; }

    @media (max-width: 768px) { .w-56 { width: 200px; } }
</style>