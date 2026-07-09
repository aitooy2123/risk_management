<?php

/**
 * หน้าจัดการผู้ใช้ (CRUD) - UI สวยงาม ค้นหาอัตโนมัติ
 * - เฉพาะ Admin เท่านั้น
 * - Pagination 10 รายการ/หน้า
 * - ค้นหาอัตโนมัติเมื่อพิมพ์/เปลี่ยนค่า
 * - ป้องกันลบตัวเองและ Admin คนสุดท้าย
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือนและการยืนยัน
 * - Keyboard Shortcuts: Ctrl+A เลือกทั้งหมด, Ctrl+D ลบที่เลือก
 * - วันที่แสดง พ.ศ. ไทย (วัน เดือน ปี)
 * - Switch Toggle สำหรับเปิด/ปิดการใช้งานผู้ใช้
 * - ข้อความยาวตัดด้วย ... และแสดง tooltip
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

// ===== ฟังก์ชันแปลงวันที่เป็น พ.ศ. ไทย =====
function thaiDateShort($date) {
    if (empty($date)) return '-';
    $ts = strtotime($date);
    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return date('j', $ts) . ' ' . $months[date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

$search       = trim($_GET['search'] ?? '');
$role_filter  = $_GET['role'] ?? '';
$dept_filter  = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';
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
if ($status_filter !== '') {
    $where .= " AND enabled = ?";
    $params[] = ($status_filter === 'enabled') ? 1 : 0;
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

$dataSql = "SELECT id, username, fullname, email, phone, department, role, avatar, reporter_code, enabled, created_at FROM users $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$totalFiltered = $totalUsers;

$csrf_token = generateCsrfToken();
$hasActiveFilters = ($search !== '' || $role_filter !== '' || $dept_filter !== '' || $status_filter !== '' || $date_from !== '' || $date_to !== '');

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
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --primary-light: #eff6ff;
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #1d4ed8 100%);
        --surface: #ffffff;
        --surface-secondary: #f8fafc;
        --border: #e2e8f0;
        --border-light: #f1f5f9;
        --text: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --danger: #dc2626;
        --danger-light: #fef2f2;
        --info: #0284c7;
        --info-light: #f0f9ff;
        --purple: #7c3aed;
        --purple-light: #f5f3ff;
        --warning: #d97706;
        --warning-light: #fffbeb;
        --success: #059669;
        --success-light: #ecfdf5;
        --hover: #eff6ff;
    }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
    }

    .page-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* ==================== HEADER ==================== */
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

    .page-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        z-index: 1;
    }

    .page-header h1 .icon-circle {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .page-header p {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.9rem;
        margin-top: 0.5rem;
        position: relative;
        z-index: 1;
    }

    .page-header p strong {
        color: white;
        font-weight: 600;
    }

    /* ==================== FILTER ==================== */
    .filter-card {
        background: var(--surface);
        border-radius: 1rem;
        border: 1px solid var(--border);
        margin-bottom: 1.25rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .filter-header {
        background: var(--surface-secondary);
        padding: 0.9rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
    }

    .filter-header:hover {
        background: #f1f5f9;
    }

    .filter-header-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .filter-icon-circle {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 0.85rem;
        border: 1px solid #bfdbfe;
    }

    .filter-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text);
    }

    .filter-subtitle {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 1px;
    }

    .filter-header-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .filter-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.7rem;
        background: var(--primary-light);
        color: var(--primary);
        border-radius: 9999px;
        font-size: 0.73rem;
        font-weight: 600;
        border: 1px solid #bfdbfe;
        white-space: nowrap;
    }

    .filter-toggle-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: white;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        font-size: 0.75rem;
        transition: all 0.3s;
    }

    .filter-toggle-icon.open {
        transform: rotate(180deg);
        background: var(--primary-light);
        color: var(--primary);
        border-color: #bfdbfe;
    }

    .filter-collapse {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease;
    }

    .filter-collapse.open {
        max-height: 800px;
    }

    .filter-body {
        padding: 1.25rem 1.5rem;
    }

    .filter-section {
        margin-bottom: 1rem;
    }

    .filter-section-title {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 0.65rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding-bottom: 0.4rem;
        border-bottom: 1px solid var(--border-light);
    }

    .search-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 0.6rem 1rem 0.6rem 2.5rem;
        border: 1.5px solid var(--border);
        border-radius: 0.6rem;
        font-size: 0.85rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #fafbfc;
        color: var(--text);
    }

    .search-box input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    .search-box .search-icon {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .filter-grid-5 {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
        gap: 0.65rem;
    }

    .filter-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.65rem;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .filter-label {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .filter-label i {
        color: var(--text-muted);
        font-size: 0.6rem;
        width: 14px;
        text-align: center;
    }

    .filter-input {
        padding: 0.55rem 0.75rem;
        border: 1.5px solid var(--border);
        border-radius: 0.5rem;
        font-size: 0.83rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #fafbfc;
        color: var(--text);
        width: 100%;
    }

    .filter-input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    select.filter-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.7rem center;
        background-size: 11px;
        padding-right: 2rem;
    }

    .active-filters-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
        padding: 0.6rem 1.5rem;
        background: var(--warning-light);
        border-top: 1px solid #fde68a;
        min-height: 40px;
    }

    .active-filters-label {
        font-size: 0.62rem;
        font-weight: 700;
        color: #92400e;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.18rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 500;
        background: white;
        color: var(--primary-dark);
        border: 1px solid #bfdbfe;
    }

    .filter-tag .remove-tag {
        cursor: pointer;
        color: #ef4444;
        font-size: 0.6rem;
        text-decoration: none;
    }

    .btn-clear-all {
        padding: 0.25rem 0.7rem;
        border-radius: 0.45rem;
        font-size: 0.7rem;
        font-weight: 600;
        border: 1px solid #fecaca;
        background: var(--danger-light);
        color: var(--danger);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        margin-left: auto;
        white-space: nowrap;
    }

    /* ==================== ACTIONS ==================== */
    .action-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
        align-items: center;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 0.6rem;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        font-family: 'Sarabun', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        transition: all 0.25s;
    }

    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-action:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .btn-action:disabled:hover {
        transform: none;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .btn-action.danger {
        background: var(--danger-light);
        color: var(--danger);
        border-color: #fecaca;
    }

    .btn-action.danger:hover:not(:disabled) {
        background: #fee2e2;
    }

    .btn-action.add {
        background: var(--purple-light);
        color: var(--purple);
        border-color: #ddd6fe;
    }

    .btn-action.add:hover {
        background: #ede9fe;
    }

    /* ==================== TABLE ==================== */
    .table-card {
        background: var(--surface);
        border-radius: 1rem;
        border: 1px solid var(--border);
        overflow: visible;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .table-header-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem 1.25rem;
        background: var(--surface-secondary);
        border-bottom: 1px solid var(--border);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 0.6rem 0.75rem;
        font-size: 0.66rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: var(--surface-secondary);
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }

    td {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--border-light);
        font-size: 0.83rem;
        color: var(--text-secondary);
    }

    tr:last-child td {
        border-bottom: none;
    }

    tbody tr {
        transition: background 0.15s;
    }

    tbody tr:hover {
        background: var(--hover);
    }

    /* Text Truncate */
    .truncate-cell {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: block;
    }

    /* Pills */
    .pill {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.18rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .pill-admin {
        background: #fef3c7;
        color: #92400e;
    }

    .pill-user {
        background: #dbeafe;
        color: #1e40af;
    }

    .pill-you {
        background: #2563eb;
        color: white;
        font-size: 0.6rem;
        padding: 0.1rem 0.45rem;
        margin-left: 0.3rem;
    }

    /* Avatar */
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

    /* ==================== SWITCH TOGGLE ==================== */
    .switch-wrapper {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 26px;
        cursor: pointer;
    }

    .switch-wrapper:hover {
        opacity: 0.9;
    }

    .switch-input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .switch-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #e2e8f0;
        transition: all 0.3s ease;
        border-radius: 34px;
        border: 2px solid #e2e8f0;
    }

    .switch-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: all 0.3s ease;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .switch-input:checked + .switch-slider {
        background-color: #10b981;
        border-color: #10b981;
    }

    .switch-input:checked + .switch-slider:before {
        transform: translateX(22px);
    }

    .switch-input:disabled + .switch-slider {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #cbd5e1;
        border-color: #cbd5e1;
    }

    .switch-input:focus + .switch-slider {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }

    .switch-input:checked:focus + .switch-slider {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
    }

    .switch-input:not(:disabled) + .switch-slider:active:before {
        width: 22px;
    }

    .switch-slider::after {
        content: '✕';
        position: absolute;
        right: 7px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 10px;
        color: #94a3b8;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .switch-input:checked + .switch-slider::after {
        content: '✓';
        left: 7px;
        right: auto;
        color: white;
    }

    /* ==================== DROPDOWN ==================== */
    .dropdown-wrapper {
        position: relative;
        display: inline-block;
    }

    .dropdown-toggle {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: white;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 1rem;
        font-weight: 700;
        transition: all 0.2s;
    }

    .dropdown-toggle:hover {
        background: var(--primary-light);
        border-color: #bfdbfe;
        color: var(--primary);
    }

    .dropdown-toggle.active {
        background: var(--primary-light);
        border-color: #93c5fd;
        color: var(--primary);
    }

    .dropdown-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 5px);
        background: white;
        border: 1px solid var(--border);
        border-radius: 0.7rem;
        min-width: 200px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        z-index: 100;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.2s;
        overflow: hidden;
        padding: 0.3rem;
    }

    .dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.5rem 0.75rem;
        border-radius: 0.45rem;
        font-size: 0.78rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-decoration: none;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        font-family: 'Sarabun', sans-serif;
    }

    .dropdown-item:hover {
        background: var(--surface-secondary);
    }

    .dropdown-item i {
        width: 18px;
        text-align: center;
        font-size: 0.8rem;
    }

    .dropdown-item.edit {
        color: var(--warning);
    }

    .dropdown-item.edit:hover {
        background: var(--warning-light);
    }

    .dropdown-item.view {
        color: var(--primary);
    }

    .dropdown-item.view:hover {
        background: var(--primary-light);
    }

    .dropdown-item.delete {
        color: var(--danger);
    }

    .dropdown-item.delete:hover {
        background: var(--danger-light);
    }

    .dropdown-item.locked {
        color: var(--text-muted);
        cursor: not-allowed;
        opacity: 0.55;
    }

    .dropdown-divider {
        height: 1px;
        background: var(--border-light);
        margin: 0.2rem 0.55rem;
    }

    /* ==================== INFO CARD ==================== */
    .info-card {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 1.25rem;
        padding: 1.5rem 1.75rem;
        margin-top: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1.25rem;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.06);
    }

    .info-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: #dbeafe;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        border: 1px solid #bfdbfe;
        color: #2563eb;
    }

    .info-content h4 {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #1e293b;
    }

    .info-content ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .info-content ul li {
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: white;
        padding: 0.5rem 0.75rem;
        border-radius: 0.6rem;
        border: 1px solid #dbeafe;
        color: #475569;
    }

    .info-content ul li .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #60a5fa;
        flex-shrink: 0;
    }

    .info-content ul li strong {
        color: #1e293b;
    }

    .info-content ul li .highlight {
        color: #dc2626;
        font-weight: 600;
    }

    .info-content ul li .shortcut {
        font-size: 0.6rem;
        background: #f1f5f9;
        padding: 0.05rem 0.4rem;
        border-radius: 4px;
        color: #64748b;
        font-weight: 600;
        margin-left: 0.3rem;
    }

    /* ==================== PAGINATION ==================== */
    .pagination-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        border-radius: 0.45rem;
        border: 1px solid var(--border);
        font-size: 0.83rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-decoration: none;
        background: white;
    }

    .page-link:hover {
        background: var(--primary-light);
        border-color: #bfdbfe;
        color: var(--primary);
    }

    .page-link.active {
        background: var(--primary-gradient);
        color: white;
        border-color: transparent;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
    }

    .page-link.disabled {
        opacity: 0.35;
        pointer-events: none;
    }

    .empty-state {
        text-align: center;
        padding: 5rem 2rem;
        background: white;
        border-radius: 1rem;
        border: 2px dashed var(--border);
    }

    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    /* ==================== KEYBOARD SHORTCUTS HINT ==================== */
    .shortcuts-hint {
        font-size: 0.65rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.25rem;
        flex-wrap: wrap;
    }

    .shortcuts-hint kbd {
        background: #f1f5f9;
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        font-size: 0.6rem;
        font-weight: 600;
        color: #475569;
    }

    @media (max-width: 1024px) {
        .filter-grid-5 {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .filter-grid-5,
        .filter-grid-2 {
            grid-template-columns: 1fr;
        }

        .page-header {
            padding: 1.25rem 1.5rem;
        }

        .page-header h1 {
            font-size: 1.25rem;
        }

        .info-content ul {
            grid-template-columns: 1fr;
        }

        .table-responsive {
            overflow-x: auto;
        }
        
        .truncate-cell {
            max-width: 120px;
        }
    }

    @media (max-width: 480px) {
        .action-bar {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
            justify-content: center;
        }
        
        .truncate-cell {
            max-width: 100px;
        }
    }

    @media print {
        .sidebar,
        .action-bar,
        .filter-card,
        .info-card,
        .page-header::before,
        .page-header::after {
            display: none !important;
        }

        .page-header {
            background: #0f172a !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .table-card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }

        body {
            background: white !important;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <div class="page-container">

            <!-- ==================== HEADER ==================== -->
            <div class="page-header">
                <h1><span class="icon-circle">👥</span> จัดการผู้ใช้</h1>
                <p>จัดการบัญชีผู้ใช้ในระบบ · ทั้งหมด <strong><?= number_format($totalUsers) ?></strong> คน · Admin <strong><?= $adminCount ?></strong> คน · User <strong><?= number_format($totalUsers - $adminCount) ?></strong> คน</p>
            </div>

            <!-- ==================== FILTER CARD ==================== -->
            <div class="filter-card">
                <div class="filter-header" onclick="toggleFilter()">
                    <div class="filter-header-left">
                        <div class="filter-icon-circle"><i class="fas fa-sliders-h"></i></div>
                        <div>
                            <div class="filter-title">ตัวกรองข้อมูล</div>
                            <div class="filter-subtitle">คลิกเพื่อ<?= $hasActiveFilters ? 'ปิด' : 'เปิด' ?>ค้นหาและกรอง</div>
                        </div>
                    </div>
                    <div class="filter-header-right">
                        <?php if ($hasActiveFilters): ?><span class="filter-count-badge"><i class="fas fa-check-circle"></i> <?= number_format($totalUsers) ?> คน</span><?php endif; ?>
                        <div class="filter-toggle-icon <?= $hasActiveFilters ? 'open' : '' ?>" id="filterToggleIcon"><i class="fas fa-chevron-down"></i></div>
                    </div>
                </div>
                <div class="filter-collapse <?= $hasActiveFilters ? 'open' : '' ?>" id="filterCollapse">
                    <form method="GET" id="filterForm" action="users.php">
                        <div class="filter-body">
                            <div class="filter-section">
                                <div class="filter-section-title"><i class="fas fa-search"></i> ค้นหาทั่วไป</div>
                                <div class="search-row">
                                    <div class="search-box"><i class="fas fa-search search-icon"></i><input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ, อีเมล, แผนก, รหัส..." autocomplete="off"></div>
                                </div>
                            </div>
                            <div class="filter-section">
                                <div class="filter-section-title"><i class="fas fa-tags"></i> หมวดหมู่</div>
                                <div class="filter-grid-5">
                                    <div class="filter-group"><label class="filter-label"><i class="fas fa-user-tag"></i> บทบาท</label><select name="role" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option>
                                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>👑 Admin</option>
                                            <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>👤 User</option>
                                        </select></div>
                                    <div class="filter-group"><label class="filter-label"><i class="fas fa-building"></i> แผนก</label><select name="department" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept) ?>" <?= $dept_filter === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option><?php endforeach; ?>
                                        </select></div>
                                    <div class="filter-group"><label class="filter-label"><i class="fas fa-power-off"></i> สถานะ</label><select name="status" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option>
                                            <option value="enabled" <?= $status_filter === 'enabled' ? 'selected' : '' ?>>✅ ใช้งาน</option>
                                            <option value="disabled" <?= $status_filter === 'disabled' ? 'selected' : '' ?>>🚫 ไม่ใช้งาน</option>
                                        </select></div>
                                    <div class="filter-group"><label class="filter-label"><i class="fas fa-calendar"></i> ตั้งแต่</label><input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="filter-input auto-submit" max="<?= date('Y-m-d') ?>"></div>
                                    <div class="filter-group"><label class="filter-label"><i class="fas fa-calendar"></i> ถึง</label><input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="filter-input auto-submit" max="<?= date('Y-m-d') ?>"></div>
                                </div>
                            </div>
                        </div>
                        <?php if ($hasActiveFilters): ?>
                            <div class="active-filters-bar"><span class="active-filters-label">🔍 ตัวกรอง:</span>
                                <?php if ($search): ?><span class="filter-tag">"<?= htmlspecialchars($search) ?>" <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['search' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($role_filter): ?><span class="filter-tag"><?= $role_filter === 'admin' ? 'Admin' : 'User' ?> <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['role' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($dept_filter): ?><span class="filter-tag"><?= htmlspecialchars($dept_filter) ?> <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['department' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($status_filter): ?><span class="filter-tag"><?= $status_filter === 'enabled' ? 'ใช้งาน' : 'ไม่ใช้งาน' ?> <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['status' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($date_from): ?><span class="filter-tag">ตั้งแต่ <?= htmlspecialchars($date_from) ?> <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['date_from' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($date_to): ?><span class="filter-tag">ถึง <?= htmlspecialchars($date_to) ?> <a href="<?= buildUserPageUrl(1, array_merge($_GET, ['date_to' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <a href="users.php" class="btn-clear-all"><i class="fas fa-times"></i> ล้างทั้งหมด</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- ==================== ACTION BAR ==================== -->
            <div class="action-bar">
                <button id="deleteSelected" class="btn-action danger"><i class="fas fa-trash-alt"></i> ลบที่เลือก</button>
                <a href="user_form.php" class="btn-action add" style="margin-left:auto;"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้</a>
                <span class="shortcuts-hint">
                    <span><kbd>Ctrl+A</kbd> เลือกทั้งหมด</span>
                    <span><kbd>Ctrl+D</kbd> ลบที่เลือก</span>
                    <span><kbd>Ctrl+E</kbd> แก้ไข</span>
                    <span><kbd>Esc</kbd> ปิดเมนู</span>
                </span>
            </div>

            <!-- ==================== TABLE ==================== -->
            <?php if (empty($users)): ?>
                <div class="empty-state"><i class="fas fa-users-slash"></i>
                    <h3>ไม่พบผู้ใช้</h3>
                    <p><?= ($hasActiveFilters) ? 'ไม่มีข้อมูลตรงตามเงื่อนไข' : 'ยังไม่มีผู้ใช้ในระบบ' ?></p>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-header-bar">
                        <span class="font-semibold text-gray-700"><i class="fas fa-users text-blue-600 mr-1"></i> รายชื่อผู้ใช้</span>
                        <span class="text-xs text-gray-500"><?= count($users) ?> / <?= number_format($totalUsers) ?> คน · หน้า <?= $page ?>/<?= max(1, $totalPages) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:38px;"><input type="checkbox" id="selectAll"></th>
                                    <th style="width:35px;">#</th>
                                    <th style="min-width:150px;">ผู้ใช้</th>
                                    <th style="min-width:100px;">รหัสผู้รายงาน</th>
                                    <th style="min-width:180px;">อีเมล</th>
                                    <th style="min-width:130px;">แผนก</th>
                                    <th style="min-width:80px;">บทบาท</th>
                                    <th style="text-align:center;width:70px;">สถานะ</th>
                                    <th style="min-width:100px;">วันที่สมัคร</th>
                                    <th style="width:45px;text-align:center;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): 
                                    $isEnabled = isset($user['enabled']) ? $user['enabled'] : 1;
                                ?>
                                    <tr>
                                        <td><input type="checkbox" class="user-checkbox" value="<?= $user['id'] ?>"></td>
                                        <td class="text-gray-400 text-sm"><?= ($page - 1) * $perPage + $index + 1 ?></td>
                                        <td>
                                            <div class="avatar-cell">
                                                <img src="avatars/<?= htmlspecialchars($user['avatar'] ?: 'default.png') ?>" class="avatar-img-sm" onerror="this.src='avatars/default.png'">
                                                <div style="min-width:0;">
                                                    <div class="font-medium truncate-cell" style="max-width:120px;" title="<?= htmlspecialchars($user['username']) ?>">
                                                        <?= htmlspecialchars($user['username']) ?>
                                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                            <span class="pill pill-you">คุณ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($user['fullname'])): ?>
                                                        <div class="text-xs text-gray-400 truncate-cell" style="max-width:120px;" title="<?= htmlspecialchars($user['fullname']) ?>">
                                                            <?= htmlspecialchars($user['fullname']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="truncate-cell" title="<?= htmlspecialchars($user['reporter_code'] ?? '-') ?>">
                                                <?= htmlspecialchars($user['reporter_code'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="truncate-cell" title="<?= htmlspecialchars($user['email'] ?: '-') ?>">
                                                <?= htmlspecialchars($user['email'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="truncate-cell" title="<?= htmlspecialchars($user['department'] ?: '-') ?>">
                                                <?= htmlspecialchars($user['department'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td><span class="pill <?= $user['role'] == 'admin' ? 'pill-admin' : 'pill-user' ?>"><i class="fas <?= $user['role'] == 'admin' ? 'fa-crown' : 'fa-user' ?> text-xs"></i> <?= $user['role'] == 'admin' ? 'Admin' : 'User' ?></span></td>
                                        <td style="text-align:center;">
                                            <label class="switch-wrapper" title="<?= $isEnabled ? 'คลิกเพื่อระงับการใช้งาน' : 'คลิกเพื่อเปิดการใช้งาน' ?>">
                                                <input type="checkbox" 
                                                       class="switch-input toggle-user-switch" 
                                                       data-id="<?= $user['id'] ?>" 
                                                       data-username="<?= htmlspecialchars($user['username']) ?>"
                                                       <?= $isEnabled ? 'checked' : '' ?>
                                                       <?= ($user['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                                <span class="switch-slider"></span>
                                            </label>
                                        </td>
                                        <td class="text-gray-500 text-sm"><?= thaiDateShort($user['created_at']) ?></td>
                                        <td>
                                            <div class="dropdown-wrapper">
                                                <button class="dropdown-toggle" onclick="toggleDropdown(event, 'udm-<?= $user['id'] ?>')" title="เมนู">⋮</button>
                                                <div class="dropdown-menu" id="udm-<?= $user['id'] ?>">
                                                    <a href="user_form.php?id=<?= $user['id'] ?>" class="dropdown-item edit">
                                                        <i class="fas fa-edit"></i> แก้ไขผู้ใช้
                                                    </a>
                                                    <a href="view_user.php?id=<?= $user['id'] ?>" class="dropdown-item view">
                                                        <i class="fas fa-eye"></i> ดูรายละเอียด
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <button class="dropdown-item delete delete-single" data-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                                                            <i class="fas fa-trash"></i> ลบผู้ใช้
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="dropdown-item locked">
                                                            <i class="fas fa-lock"></i> ไม่สามารถลบตัวเองได้
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ==================== PAGINATION ==================== -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-bar">
                    <?php if ($page > 1): ?><a href="<?= buildUserPageUrl($page - 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
                    <?php $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2); ?>
                    <?php if ($start > 1): ?><a href="<?= buildUserPageUrl(1, $_GET) ?>" class="page-link">1</a><?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?><?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++): ?><a href="<?= buildUserPageUrl($i, $_GET) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                            <?php if ($end < $totalPages): ?><?php if ($end < $totalPages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?><a href="<?= buildUserPageUrl($totalPages, $_GET) ?>" class="page-link"><?= $totalPages ?></a><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= buildUserPageUrl($page + 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ==================== INFO CARD ==================== -->
            <div class="info-card">
                <div class="info-icon-circle">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h4>
                        <i class="fas fa-user-shield text-blue-600"></i>
                        ข้อมูลการจัดการผู้ใช้
                        <span style="font-size:0.7rem;font-weight:400;color:#64748b;margin-left:0.5rem;">⌨️ ใช้แป้นพิมพ์ลัดเพื่อความรวดเร็ว</span>
                    </h4>
                    <ul>
                        <li><span class="dot"></span> <strong>Admin</strong> สามารถเพิ่ม/แก้ไข/ลบผู้ใช้ได้ทั้งหมด</li>
                        <li><span class="dot"></span> <strong>ไม่สามารถลบตัวเอง</strong> หรือ <strong>Admin คนสุดท้าย</strong> ได้ <span class="shortcut">🔒</span></li>
                        <li><span class="dot"></span> <span class="highlight">การลบผู้ใช้จะลบข้อมูลความเสี่ยงที่ผู้ใช้คนนั้นสร้างไว้ทั้งหมด</span> <span class="shortcut">⚠️</span></li>
                        <li><span class="dot"></span> <strong>Shortcuts:</strong> <kbd>Ctrl+A</kbd> เลือกทั้งหมด · <kbd>Ctrl+D</kbd> ลบที่เลือก · <kbd>Ctrl+E</kbd> แก้ไข · <kbd>Esc</kbd> ปิดเมนู</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= $csrf_token ?>">

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ============================================================
    // FILTER TOGGLE
    // ============================================================
    function toggleFilter() {
        const c = document.getElementById('filterCollapse'),
            i = document.getElementById('filterToggleIcon'),
            s = document.querySelector('.filter-subtitle');
        c.classList.toggle('open');
        i.classList.toggle('open');
        s.textContent = c.classList.contains('open') ? 'คลิกเพื่อปิดค้นหาและกรอง' : 'คลิกเพื่อเปิดค้นหาและกรอง';
    }

    // ============================================================
    // DROPDOWN TOGGLE
    // ============================================================
    function toggleDropdown(e, id) {
        e.stopPropagation();

        const menu = document.getElementById(id);
        const toggle = menu.previousElementSibling;

        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
            if (openMenu.id !== id) {
                openMenu.classList.remove('show');
                if (openMenu.previousElementSibling) {
                    openMenu.previousElementSibling.classList.remove('active');
                }
            }
        });

        // Toggle current dropdown
        menu.classList.toggle('show');
        toggle.classList.toggle('active');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-wrapper')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
                if (menu.previousElementSibling) {
                    menu.previousElementSibling.classList.remove('active');
                }
            });
        }
    });

    // ============================================================
    // DEBOUNCE SEARCH
    // ============================================================
    function debounce(f, d) {
        let t;
        return function(...a) {
            clearTimeout(t);
            t = setTimeout(() => f.apply(this, a), d);
        };
    }

    document.querySelectorAll('.auto-submit').forEach(e => e.addEventListener('change', () => document.getElementById('filterForm').submit()));

    const si = document.getElementById('searchInput');
    if (si) {
        si.addEventListener('input', debounce(() => document.getElementById('filterForm').submit(), 500));
        si.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });
        // Focus search on Ctrl+F
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                if (!e.target.closest('input, textarea, select')) {
                    e.preventDefault();
                    si.focus();
                    si.select();
                }
            }
        });
    }

    // ============================================================
    // DELETE USERS API
    // ============================================================
    function deleteUsers(ids) {
        if (!ids || !ids.length) return;
        Swal.fire({
            title: 'กำลังลบ...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('action.php?action=delete_users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ids,
                    csrf_token: csrfToken
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบสำเร็จ!',
                        text: d.message || `ลบผู้ใช้ ${ids.length} คนสำเร็จ`,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: d.message || 'ไม่สามารถลบได้'
                    });
                }
            })
            .catch(() => Swal.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว',
                text: 'กรุณาตรวจสอบเครือข่ายและลองอีกครั้ง'
            }));
    }

    // ============================================================
    // TOGGLE USER STATUS (SWITCH)
    // ============================================================
    document.querySelectorAll('.toggle-user-switch').forEach(switchEl => {
        switchEl.addEventListener('change', function(e) {
            e.stopPropagation();
            
            const userId = this.dataset.id;
            const username = this.dataset.username;
            const newStatus = this.checked ? 1 : 0;
            const switchElement = this;
            
            // ปิดการใช้งานชั่วคราวระหว่างรอ
            switchElement.disabled = true;
            
            Swal.fire({
                title: 'ยืนยันการเปลี่ยนสถานะ',
                html: `<p>ต้องการ<strong>${newStatus ? 'เปิดการใช้งาน' : 'ระงับการใช้งาน'}</strong> ผู้ใช้ <strong>"${username}"</strong> ใช่หรือไม่?</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: newStatus ? '#059669' : '#d97706',
                cancelButtonColor: '#64748b',
                confirmButtonText: `<i class="fas ${newStatus ? 'fa-check' : 'fa-ban'} mr-1"></i> ${newStatus ? 'เปิดการใช้งาน' : 'ระงับการใช้งาน'}`,
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then(r => {
                if (r.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังดำเนินการ...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch('action.php?action=toggle_user_status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            enabled: newStatus,
                            csrf_token: csrfToken
                        })
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: d.message || `${newStatus ? 'เปิด' : 'ระงับ'}การใช้งานผู้ใช้สำเร็จ`,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            switchElement.checked = !switchElement.checked;
                            switchElement.disabled = false;
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: d.message || 'ไม่สามารถเปลี่ยนสถานะได้'
                            });
                        }
                    })
                    .catch(() => {
                        switchElement.checked = !switchElement.checked;
                        switchElement.disabled = false;
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'การเชื่อมต่อล้มเหลว',
                            text: 'กรุณาตรวจสอบเครือข่ายและลองอีกครั้ง'
                        });
                    });
                } else {
                    switchElement.checked = !switchElement.checked;
                    switchElement.disabled = false;
                }
            });
        });
    });

    // ============================================================
    // SELECT ALL
    // ============================================================
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(c => c.checked = this.checked);
    });

    // ============================================================
    // DELETE SELECTED
    // ============================================================
    document.getElementById('deleteSelected')?.addEventListener('click', function() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (!checked.length) {
            return Swal.fire({
                icon: 'warning',
                title: 'กรุณาเลือกรายการ',
                text: 'กรุณาเลือกผู้ใช้อย่างน้อย 1 คน',
                confirmButtonColor: '#2563eb'
            });
        }

        const selfSelected = Array.from(checked).some(c => c.value == <?= $_SESSION['user_id'] ?>);
        if (selfSelected) {
            return Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถลบตัวเองได้',
                text: 'กรุณายกเลิกการเลือกชื่อของคุณ',
                confirmButtonColor: '#2563eb'
            });
        }

        Swal.fire({
            title: '⚠️ ยืนยันการลบ',
            html: `<div style="text-align:left;">
                <p>ต้องการลบผู้ใช้ <strong>${checked.length} คน</strong>?</p>
                <p style="color:#ef4444;font-size:0.9rem;margin-top:0.5rem;">
                    <i class="fas fa-exclamation-triangle"></i> ข้อมูลความเสี่ยงจะถูกลบด้วย!
                </p>
            </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '🗑️ ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                const ids = Array.from(checked).map(c => c.value);
                deleteUsers(ids);
            }
        });
    });

    // ============================================================
    // DELETE SINGLE
    // ============================================================
    document.querySelectorAll('.delete-single').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const dropdownMenu = this.closest('.dropdown-menu');
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
                if (dropdownMenu.previousElementSibling) {
                    dropdownMenu.previousElementSibling.classList.remove('active');
                }
            }

            Swal.fire({
                title: '⚠️ ยืนยันการลบ',
                html: `<div style="text-align:left;">
                    <p>ต้องการลบผู้ใช้ <strong>"${this.dataset.username}"</strong>?</p>
                    <p style="color:#ef4444;font-size:0.9rem;margin-top:0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> ข้อมูลความเสี่ยงจะถูกลบด้วย!
                    </p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '🗑️ ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) {
                    deleteUsers([this.dataset.id]);
                }
            });
        });
    });

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            if (!e.target.closest('input, textarea, select')) {
                e.preventDefault();
                const selectAll = document.getElementById('selectAll');
                if (selectAll) {
                    selectAll.checked = !selectAll.checked;
                    selectAll.dispatchEvent(new Event('change'));
                }
            }
        }

        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            if (!e.target.closest('input, textarea, select')) {
                e.preventDefault();
                const deleteBtn = document.getElementById('deleteSelected');
                if (deleteBtn) deleteBtn.click();
            }
        }

        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            if (!e.target.closest('input, textarea, select')) {
                e.preventDefault();
                const firstEdit = document.querySelector('.dropdown-item.edit');
                if (firstEdit) {
                    window.location.href = firstEdit.href;
                }
            }
        }

        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
                if (menu.previousElementSibling) {
                    menu.previousElementSibling.classList.remove('active');
                }
            });
        }
    });

    // ============================================================
    // INITIALIZE
    // ============================================================
    (function() {
        const collapse = document.getElementById('filterCollapse');
        if (collapse && collapse.classList.contains('open')) {
            collapse.style.maxHeight = collapse.scrollHeight + 'px';
        }
    })();

    console.log('✅ User management loaded successfully!');
    console.log('📊 Total users:', <?= $totalUsers ?>);
    console.log('👑 Admin count:', <?= $adminCount ?>);
    console.log('📄 Current page:', <?= $page ?>, 'of', <?= $totalPages ?>);
    console.log('⌨️ Keyboard shortcuts: Ctrl+A (select all), Ctrl+D (delete), Ctrl+E (edit), Ctrl+F (search)');
</script>

<?php include 'includes/footer.php'; ?>