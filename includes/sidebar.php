<?php
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}
?>
<!-- Sidebar พร้อม Gradient และ Head Menu -->
<div class="w-64 sidebar-gradient flex flex-col h-full shadow-2xl">
    <!-- Head Menu (ส่วนหัวด้านบน) -->
    <div class="p-5 bg-white/10 backdrop-blur-sm border-b border-white/10">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-xl">
                <i class="fas fa-shield-haltered"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white leading-tight">Risk Management</h1>
                <p class="text-xs text-blue-200">ศูนย์อนามัยที่ 8 อุดรธานี</p>
            </div>
        </div>

    </div>

    <!-- เมนูหลัก -->
    <nav class="p-4 flex-1 space-y-1">
        <?php if (isAdmin()): ?>
            <a href="dashboard.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>ภาพรวม</span>
            </a>
            <a href="risks.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'risks.php' ? 'active' : '' ?>">
                <i class="fas fa-list w-5 text-center"></i>
                <span>รายการความเสี่ยง</span>
            </a>
        <?php endif; ?>

        <a href="risk_form.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'risk_form.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle w-5 text-center"></i>
            <span>เพิ่มความเสี่ยง</span>
        </a>
        <?php if (isAdmin()): ?>
            <a href="users.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog w-5 text-center"></i>
                <span>จัดการผู้ใช้</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- โปรไฟล์ผู้ใช้ด้านล่าง (มีเส้นคั่นระหว่างชื่อกับ role) -->
    <div class="p-4 border-t border-white/10 bg-white/5">
        <a href="profile.php" class="flex items-center gap-3 group">
            <img src="avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>"
                alt="avatar"
                class="w-10 h-10 rounded-full border-2 border-white/30 group-hover:border-white/70 transition">
            <div>
                <!-- ✅ ชื่อผู้ใช้ + เส้นคั่นบาง ๆ ด้านล่าง -->
                <p class="font-semibold text-white text-sm mb-1 pb-1 border-b border-white/20">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?>
                </p>
                <p class="text-xs text-blue-200"><?= isAdmin() ? '👑 ผู้ดูแลระบบ' : '👤 ผู้ใช้ทั่วไป' ?></p>
            </div>
        </a>
        <a href="logout.php" class="flex items-center justify-center gap-2 mt-3 py-2 text-sm text-blue-200 hover:text-white hover:bg-white/10 rounded-lg transition">
            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
        </a>
    </div>
</div>

<style>
    /* CSS สำหรับ Sidebar Gradient */
    .sidebar-gradient {
        background: linear-gradient(180deg, #1e3a8a 0%, #312e81 60%, #1e1b4b 100%);
        color: white;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-radius: 0.75rem;
        color: rgba(255, 255, 255, 0.75);
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .menu-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .menu-item.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        font-weight: 600;
    }
</style>