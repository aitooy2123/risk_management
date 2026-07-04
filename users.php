<?php

/**
 * หน้าจัดการผู้ใช้ (CRUD) - UI สวยงาม ค้นหาอัตโนมัติ
 * - เฉพาะ Admin เท่านั้น
 * - Pagination 10 รายการ/หน้า
 * - ค้นหาอัตโนมัติเมื่อพิมพ์/เปลี่ยนค่า
 * - ป้องกันลบตัวเองและ Admin คนสุดท้าย
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือนและการยืนยัน
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

$search       = trim($_GET['search'] ?? '');
$role_filter  = $_GET['role'] ?? '';
$dept_filter  = $_GET['department'] ?? '';
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (username LIKE ? OR fullname LIKE ? OR reporter_code LIKE ? OR email LIKE ? OR department LIKE ?)";
    for ($i = 0; $i < 5; $i++) $params[] = "%{$search}%";
}
if ($role_filter !== '') {
    $where .= " AND role = ?";
    $params[] = $role_filter;
}
if ($dept_filter !== '') {
    $where .= " AND department = ?";
    $params[] = $dept_filter;
}
if ($date_from !== '') {
    $where .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

$dataSql = "SELECT id, username, fullname, email, phone, department, role, avatar, reporter_code, created_at FROM users $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ข้อมูลสำหรับ filter dropdowns
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$totalFiltered = $totalUsers;

$csrf_token = generateCsrfToken();

function buildUserPageUrl($page, $currentParams)
{
    $query = $currentParams;
    $query['page'] = $page;
    return 'users.php?' . http_build_query($query);
}
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
    }

    .page-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem;
        padding: 1.75rem 2.25rem;
        margin-bottom: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 350px;
        height: 350px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .page-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .page-header p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.85rem;
        position: relative;
        z-index: 1;
    }

    .stats-row {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.25rem;
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        font-size: 0.85rem;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .stat-badge i {
        color: #3b82f6;
    }

    .stat-badge strong {
        color: #1e40af;
    }

    .filter-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
        gap: 0.75rem;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .filter-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-input {
        padding: 0.55rem 0.7rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #fafbfc;
        transition: all 0.2s;
        color: #1e293b;
    }

    .filter-input:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
    }

    select.filter-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.7rem center;
        padding-right: 2rem;
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        padding-left: 2.5rem;
    }

    .search-box .search-icon {
        position: absolute;
        left: 0.9rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .search-box .search-clear {
        position: absolute;
        right: 0.7rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        padding: 0.2rem;
        border-radius: 50%;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }

    .search-box .search-clear:hover {
        background: #fee2e2;
        color: #ef4444;
    }

    .filter-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 0.75rem;
        margin-top: 0.5rem;
        border-top: 1px solid #f1f5f9;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .btn-filter {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        font-family: 'Sarabun', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
    }

    .btn-filter.danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-filter.danger:hover {
        background: #fecaca;
    }

    .action-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .btn-action {
        padding: 0.5rem 0.9rem;
        border-radius: 0.5rem;
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid;
        transition: all 0.2s;
        font-family: 'Sarabun', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
    }

    .btn-action.red {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .btn-action.red:hover {
        background: #fee2e2;
    }

    .btn-action.blue {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .btn-action.blue:hover {
        background: #dbeafe;
    }

    .table-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .table-header-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 0.7rem 0.9rem;
        font-size: 0.68rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #fafbfc;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }

    td {
        padding: 0.75rem 0.9rem;
        border-bottom: 1px solid #f8fafc;
        font-size: 0.85rem;
        color: #334155;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tbody tr {
        transition: all 0.15s;
    }

    tbody tr:hover {
        background: #f0f9ff;
    }

    tbody tr:nth-child(even) {
        background: #fafbfc;
    }

    tbody tr:nth-child(even):hover {
        background: #f0f9ff;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-admin {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-user {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-you {
        background: #3b82f6;
        color: white;
        font-size: 0.6rem;
        padding: 0.1rem 0.45rem;
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 7px;
        transition: all 0.2s;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }

    .btn-icon:hover {
        transform: scale(1.12);
    }

    .btn-icon.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }

    .avatar-cell {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .avatar-img-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e2e8f0;
    }

    .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
        display: inline-block;
    }

    .empty-state {
        text-align: center;
        padding: 5rem 2rem;
        background: white;
        border-radius: 1rem;
        border: 2px dashed #e2e8f0;
    }

    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    .pagination-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 0.5rem;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        font-size: 0.85rem;
        font-weight: 500;
        color: #64748b;
        text-decoration: none;
        transition: all 0.2s;
        background: white;
    }

    .page-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .page-link.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    .page-link.disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    .info-card {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: #1e40af;
    }

    .filter-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #eff6ff;
        color: #2563eb;
    }

    .filter-badge .remove {
        cursor: pointer;
        margin-left: 0.2rem;
        color: #ef4444;
    }

    /* Toast Notification */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 400px;
        width: 100%;
    }

    .toast {
        padding: 12px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.9rem;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.5s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .toast-success {
        background: #22c55e;
    }

    .toast-error {
        background: #ef4444;
    }

    .toast-warning {
        background: #f59e0b;
    }

    .toast-info {
        background: #3b82f6;
    }

    .toast i {
        font-size: 1.1rem;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @media (max-width: 1024px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <div class="page-container">

            <div class="page-header">
                <h1>👥 จัดการผู้ใช้</h1>
                <p>จัดการบัญชีผู้ใช้ในระบบ · ทั้งหมด <strong><?= number_format($totalUsers) ?></strong> คน</p>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-badge"><i class="fas fa-users"></i> ทั้งหมด <strong><?= number_format($totalUsers) ?></strong> คน</div>
                <div class="stat-badge"><i class="fas fa-crown text-amber-500"></i> Admin <strong><?= $adminCount ?></strong> คน</div>
                <div class="stat-badge"><i class="fas fa-user"></i> User <strong><?= number_format($totalUsers - $adminCount) ?></strong> คน</div>
                <?php if ($search || $role_filter || $dept_filter || $date_from || $date_to): ?>
                    <div class="stat-badge"><i class="fas fa-filter text-blue-500"></i> กรอง <strong><?= number_format($totalFiltered) ?></strong> คน</div>
                <?php endif; ?>
            </div>

            <!-- Filter -->
            <div class="filter-card">
                <form method="GET" id="filterForm" action="users.php">
                    <div class="filter-grid">
                        <div class="search-box filter-group">
                            <label class="filter-label">🔍 ค้นหา</label>
                            <div style="position:relative;">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" class="filter-input" placeholder="ชื่อ, อีเมล, แผนก, รหัสผู้รายงาน..." style="width:100%;">
                                <?php if ($search): ?>
                                    <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['search' => ''])) ?>" class="search-clear"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">👤 บทบาท</label>
                            <select name="role" class="filter-input auto-submit">
                                <option value="">ทั้งหมด</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>👑 Admin</option>
                                <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>👤 User</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">🏢 แผนก</label>
                            <select name="department" class="filter-input auto-submit">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>" <?= $dept_filter === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">📅 ตั้งแต่</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="filter-input auto-submit">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">📅 ถึง</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="filter-input auto-submit">
                        </div>
                    </div>

                    <!-- Active Filters -->
                    <?php if ($search || $role_filter || $dept_filter || $date_from || $date_to): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.75rem;">
                            <?php if ($search): ?>
                                <span class="filter-badge">🔍 "<?= htmlspecialchars($search) ?>"
                                    <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['search' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($role_filter): ?>
                                <span class="filter-badge">👤 <?= $role_filter === 'admin' ? 'Admin' : 'User' ?>
                                    <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['role' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($dept_filter): ?>
                                <span class="filter-badge">🏢 <?= htmlspecialchars($dept_filter) ?>
                                    <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['department' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($date_from): ?>
                                <span class="filter-badge">📅 ตั้งแต่ <?= htmlspecialchars($date_from) ?>
                                    <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['date_from' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($date_to): ?>
                                <span class="filter-badge">📅 ถึง <?= htmlspecialchars($date_to) ?>
                                    <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['date_to' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="filter-actions">
                        <span style="font-size:0.8rem;color:#94a3b8;">
                            <?php if ($search || $role_filter || $dept_filter || $date_from || $date_to): ?>
                                <i class="fas fa-check-circle text-green-500 mr-1"></i> พบ <?= number_format($totalUsers) ?> คน
                            <?php else: ?>
                                <i class="fas fa-database mr-1"></i> แสดงทั้งหมด <?= number_format($totalUsers) ?> คน
                            <?php endif; ?>
                        </span>
                        <div style="display:flex;gap:0.5rem;">
                            <?php if ($search || $role_filter || $dept_filter || $date_from || $date_to): ?>
                                <a href="users.php" class="btn-filter danger"><i class="fas fa-times"></i> ล้างทั้งหมด</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Actions -->
            <div class="action-bar">
                <button id="deleteSelected" class="btn-action red"><i class="fas fa-trash-alt"></i> ลบที่เลือก</button>
                <a href="user_form.php" class="btn-action blue" style="margin-left:auto;"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้</a>
            </div>

            <!-- Table -->
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">ไม่พบผู้ใช้</h3>
                    <p class="text-gray-400"><?= ($search || $role_filter || $dept_filter || $date_from || $date_to) ? 'ไม่มีข้อมูลตรงตามเงื่อนไข' : 'ยังไม่มีผู้ใช้ในระบบ' ?></p>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-header-bar">
                        <span class="font-semibold text-gray-700"><i class="fas fa-users text-blue-600 mr-1"></i> รายชื่อผู้ใช้</span>
                        <span class="text-xs text-gray-500"><?= count($users) ?> / <?= number_format($totalUsers) ?> คน · หน้า <?= $page ?>/<?= max(1, $totalPages) ?></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                                    <th style="width:40px;">#</th>
                                    <th>ผู้ใช้</th>
                                    <th>รหัสผู้รายงาน</th>
                                    <th>อีเมล</th>
                                    <th>แผนก</th>
                                    <th>บทบาท</th>
                                    <th>วันที่สมัคร</th>
                                    <th style="width:100px;text-align:center;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <td><input type="checkbox" class="user-checkbox" value="<?= $user['id'] ?>"></td>
                                        <td class="text-gray-400"><?= ($page - 1) * $perPage + $index + 1 ?></td>
                                        <td>
                                            <div class="avatar-cell">
                                                <img src="avatars/<?= htmlspecialchars($user['avatar'] ?: 'default.png') ?>" class="avatar-img-sm" onerror="this.src='avatars/default.png'">
                                                <div>
                                                    <div class="font-medium"><?= htmlspecialchars($user['username']) ?></div>
                                                    <?php if (!empty($user['fullname'])): ?>
                                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($user['fullname']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge badge-you">คุณ</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-gray-500"><?= htmlspecialchars($user['reporter_code'] ?? '-') ?></td>
                                        <td class="text-gray-500">
                                            <?php if (!empty($user['email'])): ?>
                                                <span class="text-truncate" title="<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></span>
                                            <?php else: ?><span class="text-gray-300">-</span><?php endif; ?>
                                        </td>
                                        <td class="text-gray-500">
                                            <?php if (!empty($user['department'])): ?>
                                                <span class="text-truncate" title="<?= htmlspecialchars($user['department']) ?>"><?= htmlspecialchars($user['department']) ?></span>
                                            <?php else: ?><span class="text-gray-300">-</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $user['role'] == 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                                <i class="fas <?= $user['role'] == 'admin' ? 'fa-crown' : 'fa-user' ?> text-xs"></i>
                                                <?= $user['role'] == 'admin' ? 'Admin' : 'User' ?>
                                            </span>
                                        </td>
                                        <td class="text-gray-500 text-sm"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <div style="display:flex;gap:3px;justify-content:center;">
                                                <a href="user_form.php?id=<?= $user['id'] ?>" class="btn-icon bg-blue-50 text-blue-600 hover:bg-blue-100" title="แก้ไข"><i class="fas fa-edit text-sm"></i></a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn-icon bg-red-50 text-red-600 hover:bg-red-100 delete-single" data-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" title="ลบ"><i class="fas fa-trash text-sm"></i></button>
                                                <?php else: ?>
                                                    <span class="btn-icon disabled bg-gray-50 text-gray-300" title="ไม่สามารถลบตัวเองได้"><i class="fas fa-trash text-sm"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-bar">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildUserPageUrl($page - 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    if ($start > 1): ?>
                        <a href="<?= buildUserPageUrl(1, $_GET) ?>" class="page-link">1</a>
                        <?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?= buildUserPageUrl($i, $_GET) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
                        <a href="<?= buildUserPageUrl($totalPages, $_GET) ?>" class="page-link"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= buildUserPageUrl($page + 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Info -->
            <div class="info-card">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-500 text-lg mt-0.5"></i>
                    <div>
                        <p class="font-semibold mb-1">📌 หมายเหตุ</p>
                        <ul class="list-disc ml-4 space-y-0.5 text-sm">
                            <li><strong>Admin</strong> สามารถเพิ่ม/แก้ไข/ลบผู้ใช้ได้ทั้งหมด</li>
                            <li><strong>ไม่สามารถลบตัวเอง</strong> หรือ <strong>Admin คนสุดท้าย</strong> ได้</li>
                            <li>การลบผู้ใช้จะลบ <strong>ข้อมูลความเสี่ยง</strong> ที่ผู้ใช้คนนั้นสร้างไว้ทั้งหมด</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= $csrf_token ?>">

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ========== Toast Notification ==========
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer') || (() => {
            const div = document.createElement('div');
            div.id = 'toastContainer';
            div.className = 'toast-container';
            document.body.appendChild(div);
            return div;
        })();

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${message}`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }

    // ========== Auto Submit ==========
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    document.querySelectorAll('.auto-submit').forEach(el => {
        el.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            document.getElementById('filterForm').submit();
        }, 500));
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });
    }

    // ========== Delete ==========
    function deleteUsers(ids) {
        const loading = Swal.fire({
            title: 'กำลังลบ...',
            html: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('action.php?action=delete_users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ids: ids,
                    csrf_token: csrfToken
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบสำเร็จ!',
                        text: data.message || `ลบผู้ใช้ ${ids.length} คนสำเร็จ`,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message || 'ไม่สามารถลบได้'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'การเชื่อมต่อล้มเหลว',
                    text: 'กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต'
                });
            });
    }

    // Select All
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
    });

    // Delete Selected
    document.getElementById('deleteSelected').addEventListener('click', function() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาเลือกรายการ',
                text: 'กรุณาเลือกผู้ใช้อย่างน้อย 1 คน',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        Swal.fire({
            title: '⚠️ ยืนยันการลบ',
            html: `
                <div style="text-align:left;">
                    <p>คุณต้องการลบผู้ใช้ <strong>${checked.length} คน</strong>?</p>
                    <p style="color:#ef4444;font-size:0.9rem;margin-top:0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        ข้อมูลความเสี่ยงของผู้ใช้เหล่านี้จะถูกลบด้วย!
                    </p>
                    <div style="margin-top:0.5rem;max-height:150px;overflow-y:auto;background:#f8fafc;padding:0.5rem;border-radius:0.5rem;">
                        ${Array.from(checked).map(cb => {
                            const row = cb.closest('tr');
                            const name = row?.querySelector('.font-medium')?.textContent || 'ไม่ระบุ';
                            return `<div style="font-size:0.8rem;padding:0.15rem 0;">• ${name}</div>`;
                        }).join('')}
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '🗑️ ลบ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then(result => {
            if (result.isConfirmed) {
                const ids = Array.from(checked).map(cb => cb.value);
                deleteUsers(ids);
            }
        });
    });

    // Delete Single
    document.querySelectorAll('.delete-single').forEach(btn => {
        btn.addEventListener('click', function() {
            const username = this.dataset.username;
            const userId = this.dataset.id;

            Swal.fire({
                title: '⚠️ ยืนยันการลบ',
                html: `
                    <div style="text-align:left;">
                        <p>คุณต้องการลบผู้ใช้ <strong>"${username}"</strong>?</p>
                        <p style="color:#ef4444;font-size:0.9rem;margin-top:0.5rem;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            ข้อมูลความเสี่ยงของผู้ใช้จะถูกลบด้วย!
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '🗑️ ลบ',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then(result => {
                if (result.isConfirmed) {
                    deleteUsers([userId]);
                }
            });
        });
    });
</script>
<?php include 'includes/footer.php'; ?>