<?php
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isAdmin();

// ===== ฟังก์ชันดึงค่าการตั้งค่าระบบ =====
function getSystemSettings($pdo) {
    $defaults = [
        'site_name' => 'Risk Management',
        'site_description' => 'ระบบบริหารความเสี่ยง',
        'site_organization' => 'ศูนย์อนามัยที่ 8 อุดรธานี',
        'site_logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png/1920px-%E0%B9%82%E0%B8%A5%E0%B9%82%E0%B8%81%E0%B9%89%E0%B8%A8%E0%B8%B9%E0%B8%99%E0%B8%A2%E0%B9%8C%E0%B8%AD%E0%B8%99%E0%B8%B2%E0%B8%A1%E0%B8%B1%E0%B8%A2%E0%B8%97%E0%B8%B5%E0%B9%88_8.png',
        'sidebar_show_dashboard' => '1',
        'sidebar_show_reports' => '1',
        'menu_dashboard' => 'ภาพรวมระบบ',
        'menu_dashboard_visible' => '1',
        'menu_risks' => 'รายการความเสี่ยง',
        'menu_risks_visible' => '1',
        'menu_risk_form' => 'เพิ่มความเสี่ยง',
        'menu_risk_form_visible' => '1',
        'menu_reports' => 'รายงาน',
        'menu_reports_visible' => '1',
        'menu_users' => 'จัดการผู้ใช้',
        'menu_users_visible' => '1',
        'menu_settings' => 'ตั้งค่าระบบ',
        'menu_settings_visible' => '1',
        'menu_logout' => 'ออกจากระบบ',
        'menu_logout_visible' => '1'
    ];
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (array_key_exists($row['setting_key'], $defaults) && $row['setting_value'] !== '') {
                    $defaults[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    } catch (Exception $e) {
        // ใช้ค่าเริ่มต้น
    }
    
    return $defaults;
}

$settings = isset($pdo) ? getSystemSettings($pdo) : [];

$site_name = $settings['site_name'] ?? 'Risk Management';
$site_description = $settings['site_description'] ?? 'ระบบบริหารความเสี่ยง';
$site_organization = $settings['site_organization'] ?? 'ศูนย์อนามัยที่ 8 อุดรธานี';
$site_logo = $settings['site_logo'] ?? 'assets/default-logo.png';

// ดึงชื่อเมนู
$menu_labels = [
    'dashboard' => $settings['menu_dashboard'] ?? 'ภาพรวมระบบ',
    'risks' => $settings['menu_risks'] ?? 'รายการความเสี่ยง',
    'risk_form' => $settings['menu_risk_form'] ?? 'เพิ่มความเสี่ยง',
    'reports' => $settings['menu_reports'] ?? 'รายงาน',
    'users' => $settings['menu_users'] ?? 'จัดการผู้ใช้',
    'settings' => $settings['menu_settings'] ?? 'ตั้งค่าระบบ',
    'logout' => $settings['menu_logout'] ?? 'ออกจากระบบ'
];

// ✅ ใช้ Switch ควบคุมทั้งหมด
$menu_visible = [
    'dashboard' => ($settings['menu_dashboard_visible'] ?? '1') == '1',
    'risks' => ($settings['menu_risks_visible'] ?? '1') == '1',
    'risk_form' => ($settings['menu_risk_form_visible'] ?? '1') == '1',
    'reports' => ($settings['menu_reports_visible'] ?? '1') == '1',
    'users' => ($settings['menu_users_visible'] ?? '1') == '1',
    'settings' => ($settings['menu_settings_visible'] ?? '1') == '1',
    'logout' => ($settings['menu_logout_visible'] ?? '1') == '1'
];

// นับจำนวนความเสี่ยง
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
    } catch (Exception $e) {}
}

// นับจำนวนผู้ใช้
$user_count = 0;
if ($is_admin && isset($pdo)) {
    try {
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Exception $e) {}
}

function isMenuActive($page, $current_page, $sub_pages = []) {
    if ($current_page == $page) return true;
    foreach ($sub_pages as $sub) {
        if ($current_page == $sub) return true;
    }
    return false;
}
?>
<!-- Sidebar -->
<div class="w-60 sidebar-gradient flex flex-col h-full shadow-2xl">
    <!-- Logo Section -->
    <div class="px-5 py-5 border-b border-white/[0.08] bg-white/[0.02]">
        <div class="flex items-center gap-3">
            <div class="logo-wrapper">
                <div class="logo-ring"></div>
                <div class="logo-glow"></div>
                <img src="<?= htmlspecialchars($site_logo) ?>"
                    alt="Logo" class="logo-image"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="logo-fallback" style="display:none;">
                    <i class="fas fa-hospital-alt text-xl"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-sm font-bold text-white leading-tight">
                    <?= htmlspecialchars($site_name) ?>
                </h1>
                <p class="text-[10px] text-blue-300/70 leading-tight mt-0.5">
                    <?= htmlspecialchars($site_description) ?>
                </p>
                <p class="text-[9px] text-blue-300/50 leading-tight mt-0.5">
                    <?= htmlspecialchars($site_organization) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="px-3 py-4 flex-1 space-y-0.5 overflow-y-auto scrollbar-thin">
        
        <!-- Dashboard - ✅ ใช้แค่ $menu_visible['dashboard'] -->
        <?php if ($is_admin && $menu_visible['dashboard']): ?>
            <div class="nav-section">
                <div class="nav-section-label">
                    <i class="fas fa-crown text-amber-400 text-[8px] mr-1"></i> ผู้ดูแลระบบ
                </div>
                <a href="dashboard.php" class="menu-item <?= isMenuActive('dashboard.php', $current_page) ? 'active' : '' ?>">
                    <div class="menu-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="menu-content">
                        <span class="menu-text"><?= htmlspecialchars($menu_labels['dashboard']) ?></span>
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
            
            <?php if ($menu_visible['risks']): ?>
            <a href="risks.php" class="menu-item <?= isMenuActive('risks.php', $current_page, ['view_risk.php', 'edit_risk.php']) ? 'active' : '' ?>">
                <div class="menu-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="menu-content">
                    <span class="menu-text"><?= htmlspecialchars($menu_labels['risks']) ?></span>
                    <?php if ($pending_risk_count > 0): ?>
                        <span class="menu-badge menu-badge-pending"><?= number_format($pending_risk_count) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($menu_visible['risk_form']): ?>
            <a href="risk_form.php" class="menu-item <?= isMenuActive('risk_form.php', $current_page) ? 'active' : '' ?>">
                <div class="menu-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="menu-content">
                    <span class="menu-text"><?= htmlspecialchars($menu_labels['risk_form']) ?></span>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($menu_visible['reports']): ?>
            <a href="reports.php" class="menu-item <?= isMenuActive('reports.php', $current_page) ? 'active' : '' ?>">
                <div class="menu-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="menu-content">
                    <span class="menu-text"><?= htmlspecialchars($menu_labels['reports']) ?></span>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- จัดการระบบ (เฉพาะ Admin) -->
        <?php if ($is_admin): ?>
            <div class="nav-section">
                <div class="nav-section-label">
                    <i class="fas fa-cog text-slate-400 text-[8px] mr-1"></i> จัดการระบบ
                </div>
                
                <?php if ($menu_visible['users']): ?>
                <a href="users.php" class="menu-item <?= isMenuActive('users.php', $current_page, ['user_form.php', 'view_user.php']) ? 'active' : '' ?>">
                    <div class="menu-icon"><i class="fas fa-users-cog"></i></div>
                    <div class="menu-content">
                        <span class="menu-text"><?= htmlspecialchars($menu_labels['users']) ?></span>
                        <?php if ($user_count > 0): ?>
                            <span class="menu-badge menu-badge-users"><?= number_format($user_count) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endif; ?>
                
                <?php if ($menu_visible['settings']): ?>
                <a href="settings.php" class="menu-item <?= isMenuActive('settings.php', $current_page) ? 'active' : '' ?>">
                    <div class="menu-icon"><i class="fas fa-sliders-h"></i></div>
                    <div class="menu-content">
                        <span class="menu-text"><?= htmlspecialchars($menu_labels['settings']) ?></span>
                    </div>
                </a>
                <?php endif; ?>
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
                    <div class="text-white text-xs font-semibold truncate leading-tight">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?>
                    </div>
                    <div class="text-[10px] text-slate-400 truncate leading-tight">
                        <?= $is_admin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน' ?>
                    </div>
                </div>
            </div>
            <?php if ($menu_visible['logout']): ?>
            <a href="logout.php" class="logout-btn group" title="<?= htmlspecialchars($menu_labels['logout']) ?>" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="logout-tooltip"><?= htmlspecialchars($menu_labels['logout']) ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '<?= htmlspecialchars($menu_labels['logout']) ?>?',
                text: 'คุณต้องการ<?= htmlspecialchars(mb_strtolower($menu_labels['logout'])) ?>ใช่หรือไม่',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<?= htmlspecialchars($menu_labels['logout']) ?>',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'logout.php';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        document.querySelectorAll('.menu-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href && currentPath.includes(href)) item.classList.add('active');
        });
    });
</script>

<style>
    .sidebar-gradient {
        background: linear-gradient(195deg, #0f172a 0%, #1e3a8a 25%, #312e81 60%, #1e1b4b 100%);
        color: white; position: relative; overflow: hidden;
    }
    .sidebar-gradient::before {
        content: ''; position: absolute; top: -20%; right: -40%;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(96,165,250,0.08) 0%, transparent 70%);
        border-radius: 50%; pointer-events: none;
    }
    .sidebar-gradient::after {
        content: ''; position: absolute; bottom: 10%; left: -30%;
        width: 200px; height: 200px;
        background: radial-gradient(circle, rgba(167,139,250,0.06) 0%, transparent 70%);
        border-radius: 50%; pointer-events: none;
    }
    .logo-wrapper { position: relative; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .logo-ring { position: absolute; inset: -2px; border: 1.5px solid rgba(255,255,255,0.1); border-radius: 50%; animation: ringSpin 10s linear infinite; }
    .logo-ring::before { content: ''; position: absolute; top: -2px; left: 50%; transform: translateX(-50%); width: 5px; height: 5px; background: #60a5fa; border-radius: 50%; box-shadow: 0 0 8px #60a5fa; }
    .logo-glow { position: absolute; inset: -8px; background: radial-gradient(circle, rgba(96,165,250,0.15) 0%, transparent 70%); border-radius: 50%; animation: pulse 3s ease-in-out infinite; }
    @keyframes ringSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes pulse { 0%,100%{opacity:0.5;transform:scale(1)} 50%{opacity:1;transform:scale(1.05)} }
    .logo-image { width: 40px; height: 40px; object-fit: contain; border-radius: 50%; background: white; padding: 2px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); position: relative; z-index: 2; }
    .logo-fallback { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.3); position: relative; z-index: 2; }
    .nav-section { margin-bottom: 0.25rem; }
    .nav-section-label { font-size: 0.6rem; font-weight: 700; color: rgba(255,255,255,0.25); text-transform: uppercase; letter-spacing: 1.2px; padding: 0.65rem 0.7rem 0.3rem; display: flex; align-items: center; }
    .menu-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.75rem; border-radius: 0.65rem; color: rgba(255,255,255,0.55); font-weight: 500; font-size: 0.84rem; transition: all 0.25s; text-decoration: none; position: relative; overflow: hidden; }
    .menu-item::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 0; background: #60a5fa; border-radius: 0 3px 3px 0; transition: height 0.3s; }
    .menu-item:hover::before { height: 60%; }
    .menu-item:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.85); }
    .menu-item.active::before { height: 80%; background: #3b82f6; }
    .menu-item.active { background: rgba(255,255,255,0.08); color: white; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    .menu-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.04); font-size: 0.85rem; flex-shrink: 0; }
    .menu-item.active .menu-icon { background: rgba(59,130,246,0.25); box-shadow: 0 0 15px rgba(59,130,246,0.2); color: #93c5fd; }
    .menu-content { flex: 1; display: flex; align-items: center; gap: 0.5rem; min-width: 0; }
    .menu-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .menu-badge { font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 9999px; white-space: nowrap; flex-shrink: 0; }
    .menu-badge-admin { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
    .menu-badge-pending { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); animation: badgePulse 2s ease-in-out infinite; }
    .menu-badge-users { background: rgba(167,139,250,0.25); color: #c4b5fd; border: 1px solid rgba(167,139,250,0.3); }
    @keyframes badgePulse { 0%,100%{opacity:0.8} 50%{opacity:1} }
    .logout-btn { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; color: rgba(255,255,255,0.3); text-decoration: none; transition: all 0.25s; flex-shrink: 0; cursor: pointer; position: relative; }
    .logout-btn:hover { background: rgba(239,68,68,0.2); color: #fca5a5; }
    .logout-tooltip { position: absolute; right: calc(100% + 10px); top: 50%; transform: translateY(-50%); background: #1e293b; color: white; font-size: 0.65rem; padding: 0.35rem 0.7rem; border-radius: 0.4rem; white-space: nowrap; opacity: 0; visibility: hidden; transition: all 0.2s; pointer-events: none; }
    .logout-btn:hover .logout-tooltip { opacity: 1; visibility: visible; }
    .scrollbar-thin::-webkit-scrollbar { width: 3px; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.06); border-radius: 3px; }
    @media (max-width: 768px) {
        .w-60 { width: 220px; }
        .menu-item { font-size: 0.8rem; padding: 0.55rem 0.65rem; }
        .menu-icon { width: 28px; height: 28px; font-size: 0.75rem; }
    }
</style>