<?php

/**
 * รายการความเสี่ยง - UI สวยงาม พร้อมระบบจัดการเต็มรูปแบบ
 * - ฟิลเตอร์: กลุ่มงาน, ประเภท, ระดับ, สถานะ, วันที่
 * - แสดงสถานะความเสี่ยง + สถานะการรายงานผล
 * - Admin: เห็นทั้งหมด / User: เห็นเฉพาะของตัวเอง
 * - Admin: แก้ไขได้ทุกหน้า (เนื้อหา, สถานะ, สรุปผล)
 * - Admin: พิมพ์ PDF ได้ทั้งหมด
 * - User: แก้ไขสรุปผลการรายงานได้
 * - User: ปรับสถานะได้เอง
 * - User: เพิ่มสรุปผลการรายงานได้ (ยกเว้นสถานะ "ยุติ")
 * - สถานะ "ดำเนินการแล้ว" ยังเพิ่มสรุปผลได้
 * - เมื่อมีรายงานผลแล้ว จะแก้ไขไม่ได้อีก (ดูได้อย่างเดียว)
 * - แสดงปุ่มตาม Role ชัดเจน
 * - ปุ่มจัดการแบบ Dropdown (⋮)
 * - โทนสีฟ้า-น้ำเงิน ดูมืออาชีพ
 * - วันที่แสดง พ.ศ. ไทย (วัน เดือน ปี)
 * - Table Striped
 * - เลือกลบ, พิมพ์ PDF, พิมพ์ทั้งหมด
 * - Pagination 10 รายการ/หน้า
 * - Badge สีแยกตามระดับและสถานะ
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือนและการยืนยัน
 * - Keyboard Shortcuts: Ctrl+A เลือกทั้งหมด, Ctrl+D ลบที่เลือก, Esc ปิดเมนู
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// ===== ฟังก์ชันแปลงวันที่เป็น พ.ศ. ไทย =====
function thaiDate($date, $showTime = false)
{
    if (empty($date)) {
        return '-';
    }
    $timestamp = strtotime($date);
    $year      = date('Y', $timestamp) + 543;
    $day       = date('d', $timestamp);
    $month     = date('n', $timestamp);

    $thaiMonths = [
        1  => 'มกราคม',
        2  => 'กุมภาพันธ์',
        3  => 'มีนาคม',
        4  => 'เมษายน',
        5  => 'พฤษภาคม',
        6  => 'มิถุนายน',
        7  => 'กรกฎาคม',
        8  => 'สิงหาคม',
        9  => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];

    $thaiMonth = $thaiMonths[$month];

    if ($showTime) {
        $time = date('H:i', $timestamp) . ' น.';
        return "{$day} {$thaiMonth} {$year} {$time}";
    }

    return "{$day} {$thaiMonth} {$year}";
}

// ===== ฟังก์ชันตรวจสอบสิทธิ์ =====
function canModify($risk_user_id)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    return $_SESSION['user_id'] == $risk_user_id;
}

function canDelete()
{
    return isAdmin();
}

function getStatusIcon($status)
{
    if ($status == 'ดำเนินการแล้ว') {
        return 'fa-check-circle';
    }
    if ($status == 'กำลังดำเนินการ') {
        return 'fa-spinner fa-spin';
    }
    if ($status == 'ยุติ') {
        return 'fa-times-circle';
    }
    return 'fa-clock';
}

// ===== ดึงค่าการตั้งค่าระบบ =====
$settings = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key = 'items_per_page'");
        $row = $stmt->fetch();
        if ($row) {
            $settings['items_per_page'] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // ใช้ค่าเริ่มต้น
}

// ===== ตัวแปร Filter =====
$type_filter     = $_GET['risk_type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$group_filter    = $_GET['unit'] ?? '';
$status_filter   = $_GET['status'] ?? '';
$date_from       = $_GET['date_from'] ?? '';
$date_to         = $_GET['date_to'] ?? '';

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($settings['items_per_page'] ?? 10);
$offset  = ($page - 1) * $perPage;

// ===== สร้าง Query =====
$where  = "WHERE 1=1";
$params = [];

if ($type_filter !== '') {
    $where .= " AND r.risk_type = ?";
    $params[] = $type_filter;
}
if ($severity_filter !== '') {
    $where .= " AND r.severity = ?";
    $params[] = $severity_filter;
}
if ($group_filter !== '') {
    $where .= " AND r.unit = ?";
    $params[] = $group_filter;
}
if ($status_filter !== '') {
    if ($status_filter == 'ยังไม่ดำเนินการ') {
        $where .= " AND (r.status = ? OR r.status IS NULL OR r.status = '')";
        $params[] = $status_filter;
    } else {
        $where .= " AND r.status = ?";
        $params[] = $status_filter;
    }
}
if ($date_from !== '') {
    $where .= " AND DATE(r.event_datetime) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where .= " AND DATE(r.event_datetime) <= ?";
    $params[] = $date_to;
}
if (!isAdmin()) {
    $where .= " AND r.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

// ===== นับจำนวนทั้งหมด =====
$countSql  = "SELECT COUNT(*) FROM risks r $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// ===== ดึง ID ทั้งหมด =====
$allIdsSql  = "SELECT r.id FROM risks r $where ORDER BY r.created_at DESC";
$allIdsStmt = $pdo->prepare($allIdsSql);
$allIdsStmt->execute($params);
$allIds = $allIdsStmt->fetchAll(PDO::FETCH_COLUMN);

// ===== ดึงข้อมูลตามหน้า =====
$dataSql = "SELECT r.*, u.username, u.fullname, rr.id as report_id 
            FROM risks r 
            LEFT JOIN users u ON r.user_id = u.id 
            LEFT JOIN risk_reports rr ON r.id = rr.risk_id 
            $where 
            ORDER BY r.created_at DESC 
            LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($dataSql);

$i = 1;
foreach ($params as $param) {
    $stmt->bindValue($i++, $param);
}
$stmt->bindValue($i++, (int) $perPage, PDO::PARAM_INT);
$stmt->bindValue($i, (int) $offset, PDO::PARAM_INT);

$stmt->execute();
$risks = $stmt->fetchAll();

foreach ($risks as &$risk) {
    if (empty($risk['status']) || $risk['status'] == '') {
        $risk['status'] = 'ยังไม่ดำเนินการ';
    }
}
unset($risk);

// ===== ดึงข้อมูลสำหรับ Filter =====
$types      = $pdo->query("SELECT DISTINCT risk_type FROM risks ORDER BY risk_type")->fetchAll(PDO::FETCH_COLUMN);
$severities = $pdo->query("SELECT DISTINCT severity FROM risks ORDER BY severity")->fetchAll(PDO::FETCH_COLUMN);
$units      = $pdo->query("SELECT DISTINCT unit FROM risks ORDER BY unit")->fetchAll(PDO::FETCH_COLUMN);

try {
    $statuses = $pdo->query("SELECT DISTINCT status FROM risks WHERE status IS NOT NULL AND status != '' ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($statuses)) {
        $statuses = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];
    }
    if (!in_array('ยังไม่ดำเนินการ', $statuses)) {
        array_unshift($statuses, 'ยังไม่ดำเนินการ');
    }
} catch (PDOException $e) {
    $statuses = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];
}

$csrf_token       = generateCsrfToken();
$hasActiveFilters = ($type_filter !== '' || $severity_filter !== '' || $group_filter !== '' || $status_filter !== '' || $date_from !== '' || $date_to !== '');

function buildRiskPageUrl($page, $currentParams)
{
    $query = $currentParams;
    $query['page'] = $page;
    return 'risks.php?' . http_build_query($query);
}

$severityLabels = [
    'A' => 'ต่ำมาก',
    'B' => 'ต่ำ',
    'C' => 'ปานกลาง',
    'D' => 'สูง',
    'E' => 'สูงมาก',
    'F' => 'สูงสุด',
];

$severityBadgeMap = [
    'A' => 'bg-blue-50 text-blue-700',
    'B' => 'bg-sky-50 text-sky-700',
    'C' => 'bg-cyan-50 text-cyan-700',
    'D' => 'bg-amber-50 text-amber-700',
    'E' => 'bg-orange-50 text-orange-700',
    'F' => 'bg-red-50 text-red-700',
];

$statusBadgeMap = [
    'ยังไม่ดำเนินการ' => 'bg-amber-50 text-amber-700',
    'กำลังดำเนินการ' => 'bg-sky-50 text-sky-700',
    'ดำเนินการแล้ว'  => 'bg-emerald-50 text-emerald-700',
    'ยุติ'            => 'bg-red-50 text-red-700',
];

$reportStatusLabels = [
    'no_report'  => ['label' => 'ยังไม่มีรายงาน', 'class' => 'bg-amber-50 text-amber-700',   'icon' => 'fa-file'],
    'has_report' => ['label' => 'มีรายงานแล้ว',   'class' => 'bg-emerald-50 text-emerald-700', 'icon' => 'fa-file-check'],
];

$isAdmin = isAdmin();
$page_title = 'รายการความเสี่ยง';
?>
<?php include 'includes/header.php'; ?>

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
        max-width: 100%;
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
        box-shadow: 0 10px 40px rgba(37, 99, 235, 0.2);
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

    /* ==================== FILTER CARD ==================== */
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

    .filter-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
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

    /* ==================== ACTION BAR ==================== */
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

    .btn-action.print {
        background: var(--info-light);
        color: var(--info);
        border-color: #bae6fd;
    }

    .btn-action.pdf {
        background: var(--primary-light);
        color: var(--primary);
        border-color: #bfdbfe;
    }

    .btn-action.add {
        background: var(--purple-light);
        color: var(--purple);
        border-color: #ddd6fe;
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
        text-align: center;
        padding: 0.6rem 0.5rem;
        font-size: 0.66rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: var(--surface-secondary);
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
        vertical-align: middle;
    }

    td {
        padding: 0.65rem 0.5rem;
        border-bottom: 1px solid var(--border-light);
        font-size: 0.83rem;
        color: var(--text-secondary);
        text-align: center;
        vertical-align: middle;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tbody tr:nth-child(odd) {
        background: #ffffff;
    }

    tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    tbody tr {
        transition: background 0.2s ease, box-shadow 0.2s ease;
    }

    tbody tr:hover {
        background: var(--hover) !important;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.06);
    }

    /* ==================== PILLS ==================== */
    .pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
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

    .dropdown-item.view { color: var(--primary); }
    .dropdown-item.view:hover { background: var(--primary-light); }
    .dropdown-item.print { color: var(--info); }
    .dropdown-item.print:hover { background: var(--info-light); }
    .dropdown-item.edit { color: var(--warning); }
    .dropdown-item.edit:hover { background: var(--warning-light); }
    .dropdown-item.report { color: var(--purple); }
    .dropdown-item.report:hover { background: var(--purple-light); }
    .dropdown-item.delete { color: var(--danger); }
    .dropdown-item.delete:hover { background: var(--danger-light); }

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

    .dropdown-item-text {
        font-size: 0.6rem;
        color: var(--text-muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 0.35rem 0.75rem 0.15rem;
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

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 1024px) {
        .filter-grid-4 {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .filter-grid-4,
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
    }

    @media (max-width: 480px) {
        .action-bar {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <div class="page-container">

            <!-- ==================== PAGE HEADER ==================== -->
            <div class="page-header">
                <h1><span class="icon-circle">📋</span> รายการความเสี่ยง</h1>
                <p>
                    <?= !isAdmin() ? 'รายการของคุณ · <strong>' . htmlspecialchars($_SESSION['username']) . '</strong>' : '👑 มุมมองผู้ดูแลระบบ' ?>
                    · ทั้งหมด <strong><?= number_format($totalRows) ?></strong> รายการ
                    <?php if ($totalPages > 1): ?> · หน้า <strong><?= $page ?>/<?= $totalPages ?></strong><?php endif; ?>
                </p>
            </div>

            <!-- ==================== FILTER CARD ==================== -->
            <div class="filter-card">
                <div class="filter-header" onclick="toggleFilter()">
                    <div class="filter-header-left">
                        <div class="filter-icon-circle"><i class="fas fa-sliders-h"></i></div>
                        <div>
                            <div class="filter-title">ตัวกรองข้อมูล</div>
                            <div class="filter-subtitle">คลิกเพื่อ<?= $hasActiveFilters ? 'ปิด' : 'เปิด' ?>กรองข้อมูล</div>
                        </div>
                    </div>
                    <div class="filter-header-right">
                        <?php if ($hasActiveFilters): ?><span class="filter-count-badge"><i class="fas fa-check-circle"></i> <?= number_format($totalRows) ?> รายการ</span><?php endif; ?>
                        <div class="filter-toggle-icon <?= $hasActiveFilters ? 'open' : '' ?>" id="filterToggleIcon"><i class="fas fa-chevron-down"></i></div>
                    </div>
                </div>
                <div class="filter-collapse <?= $hasActiveFilters ? 'open' : '' ?>" id="filterCollapse">
                    <form method="GET" id="filterForm" action="risks.php">
                        <div class="filter-body">
                            <div class="filter-section">
                                <div class="filter-section-title"><i class="fas fa-tags"></i> หมวดหมู่</div>
                                <div class="filter-grid-4">
                                    <div class="filter-group">
                                        <label class="filter-label"><i class="fas fa-building"></i> กลุ่มงาน</label>
                                        <select name="unit" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option>
                                            <?php foreach ($units as $u): ?><option value="<?= htmlspecialchars($u) ?>" <?= $group_filter == $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label class="filter-label"><i class="fas fa-tag"></i> ประเภท</label>
                                        <select name="risk_type" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option>
                                            <?php foreach ($types as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $type_filter == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label class="filter-label"><i class="fas fa-exclamation-triangle"></i> ระดับ</label>
                                        <select name="severity" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option>
                                            <?php foreach ($severities as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $severity_filter == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?> - <?= $severityLabels[$s] ?? $s ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label class="filter-label"><i class="fas fa-chart-bar"></i> สถานะ</label>
                                        <select name="status" class="filter-input auto-submit">
                                            <option value="">ทั้งหมด</option>
                                            <?php foreach ($statuses as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $status_filter == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="filter-section">
                                <div class="filter-section-title"><i class="fas fa-calendar-alt"></i> ช่วงเวลา</div>
                                <div class="filter-grid-2">
                                    <div class="filter-group">
                                        <label class="filter-label"><i class="fas fa-calendar"></i> วันที่เริ่มต้น</label>
                                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="filter-input auto-submit">
                                    </div>
                                    <div class="filter-group">
                                        <label class="filter-label"><i class="fas fa-calendar"></i> วันที่สิ้นสุด</label>
                                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="filter-input auto-submit">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($hasActiveFilters): ?>
                            <div class="active-filters-bar">
                                <span class="active-filters-label">🔍 ตัวกรองที่ใช้อยู่:</span>
                                <?php if ($group_filter): ?><span class="filter-tag">กลุ่มงาน: <?= htmlspecialchars($group_filter) ?> <a href="<?= buildRiskPageUrl(1, array_merge($_GET, ['unit' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($type_filter): ?><span class="filter-tag">ประเภท: <?= htmlspecialchars($type_filter) ?> <a href="<?= buildRiskPageUrl(1, array_merge($_GET, ['risk_type' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($severity_filter): ?><span class="filter-tag">ระดับ: <?= htmlspecialchars($severity_filter) ?> <a href="<?= buildRiskPageUrl(1, array_merge($_GET, ['severity' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($status_filter): ?><span class="filter-tag">สถานะ: <?= htmlspecialchars($status_filter) ?> <a href="<?= buildRiskPageUrl(1, array_merge($_GET, ['status' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($date_from): ?><span class="filter-tag">ตั้งแต่ <?= htmlspecialchars($date_from) ?> <a href="<?= buildRiskPageUrl(1, array_merge($_GET, ['date_from' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <?php if ($date_to): ?><span class="filter-tag">ถึง <?= htmlspecialchars($date_to) ?> <a href="<?= buildRiskPageUrl(1, array_merge($_GET, ['date_to' => ''])) ?>" class="remove-tag"><i class="fas fa-times"></i></a></span><?php endif; ?>
                                <a href="risks.php" class="btn-clear-all"><i class="fas fa-times"></i> ล้างทั้งหมด</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- ==================== ACTION BAR ==================== -->
            <div class="action-bar">
                <?php if (isAdmin()): ?>
                    <button id="deleteSelected" class="btn-action danger"><i class="fas fa-trash-alt"></i> ลบที่เลือก</button>
                    <button id="printSelected" class="btn-action print"><i class="fas fa-print"></i> พิมพ์ PDF ที่เลือก</button>
                    <a href="generate_pdf.php?ids=<?= implode(',', $allIds) ?>" target="_blank" class="btn-action pdf"><i class="fas fa-file-pdf"></i> พิมพ์ทั้งหมด</a>
                <?php else: ?>
                    <button id="printSelected" class="btn-action print"><i class="fas fa-print"></i> พิมพ์ PDF ที่เลือก</button>
                    <a href="generate_pdf.php?ids=<?= implode(',', $allIds) ?>" target="_blank" class="btn-action pdf"><i class="fas fa-file-pdf"></i> พิมพ์ทั้งหมด</a>
                <?php endif; ?>
                <a href="risk_form.php" class="btn-action add" style="margin-left:auto;"><i class="fas fa-plus-circle"></i> เพิ่มรายการใหม่</a>
                <span class="shortcuts-hint">
                    <span><kbd>Ctrl+A</kbd> เลือกทั้งหมด</span>
                    <?php if (isAdmin()): ?><span><kbd>Ctrl+D</kbd> ลบที่เลือก</span><?php endif; ?>
                    <span><kbd>Esc</kbd> ปิดเมนู</span>
                </span>
            </div>

            <!-- ==================== TABLE ==================== -->
            <?php if (empty($risks)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>ไม่พบรายการความเสี่ยง</h3>
                    <p><?= ($hasActiveFilters) ? 'ไม่มีข้อมูลตรงตามเงื่อนไขการกรอง' : 'ยังไม่มีรายการความเสี่ยง' ?></p>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-header-bar">
                        <span class="font-semibold text-gray-700"><i class="fas fa-list text-blue-600 mr-1"></i> รายการความเสี่ยง</span>
                        <span class="text-xs text-gray-500"><?= count($risks) ?> / <?= number_format($totalRows) ?> · หน้า <?= $page ?>/<?= max(1, $totalPages) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:38px;">
                                        <?php if (isAdmin()): ?>
                                            <input type="checkbox" id="selectAll" title="เลือกทั้งหมด (Ctrl+A)">
                                        <?php endif; ?>
                                    </th>
                                    <th style="width:35px;">#</th>
                                    <th style="text-align:left;">กลุ่มงาน</th>
                                    <th style="text-align:left;">ประเภท</th>
                                    <th>ระดับ</th>
                                    <th>สถานะ</th>
                                    <th>การรายงานผล</th>
                                    <th>วันที่</th>
                                    <?php if (isAdmin()): ?><th>ผู้รายงาน</th><?php endif; ?>
                                    <th style="width:45px;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($risks as $index => $risk):
                                    $rowNum        = ($page - 1) * $perPage + $index + 1;
                                    $sevBadge      = $severityBadgeMap[$risk['severity']] ?? 'bg-slate-50 text-slate-600';
                                    $sevLabel      = $severityLabels[$risk['severity']] ?? $risk['severity'];
                                    $displayStatus = !empty($risk['status']) ? $risk['status'] : 'ยังไม่ดำเนินการ';
                                    $staBadge      = $statusBadgeMap[$displayStatus] ?? 'bg-slate-50 text-slate-500';
                                    $statusIcon    = getStatusIcon($displayStatus);
                                    $isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $risk['user_id'];
                                    $isLockedForReport = in_array($displayStatus, ['ยุติ']);
                                    $isLockedForEdit = in_array($displayStatus, ['ดำเนินการแล้ว', 'ยุติ']);
                                    $canAddReport = ($isOwner || isAdmin()) && empty($risk['report_id']) && !$isLockedForReport;
                                    $canViewReport = ($isOwner || isAdmin()) && !empty($risk['report_id']);
                                    $canEditRisk = isAdmin() || ($isOwner && !$isLockedForEdit);
                                    $hasReport        = !empty($risk['report_id']);
                                    $dropdownId       = 'dm-' . $risk['id'];
                                    $reportStatusKey  = $hasReport ? 'has_report' : 'no_report';
                                    $reportStatusInfo = $reportStatusLabels[$reportStatusKey];
                                    $riskTypeDisplay = htmlspecialchars($risk['risk_type']);
                                    if (!empty($risk['risk_type_other'])) {
                                        $riskTypeDisplay .= ' <small class="text-gray-400">(' . htmlspecialchars($risk['risk_type_other']) . ')</small>';
                                    }
                                ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <?php if (isAdmin()): ?>
                                                <input type="checkbox" class="risk-checkbox" value="<?= $risk['id'] ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;" class="text-gray-400 text-sm"><?= $rowNum ?></td>
                                        <td style="text-align:left;">
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($risk['unit'] ?? '-') ?></span>
                                        </td>
                                        <td style="text-align:left;">
                                            <span class="text-gray-700"><?= $riskTypeDisplay ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="pill <?= $sevBadge ?>">
                                                <?= htmlspecialchars($risk['severity']) ?> - <?= $sevLabel ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="pill <?= $staBadge ?>">
                                                <i class="fas <?= $statusIcon ?> text-xs"></i> 
                                                <?= htmlspecialchars($displayStatus) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="pill <?= $reportStatusInfo['class'] ?>">
                                                <i class="fas <?= $reportStatusInfo['icon'] ?> text-xs"></i> 
                                                <?= $reportStatusInfo['label'] ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;" class="text-gray-500 text-sm">
                                            <?= thaiDate($risk['event_datetime']) ?>
                                        </td>
                                        <?php if (isAdmin()): ?>
                                            <td style="text-align:center;">
                                                <span class="text-gray-700">
                                                    <?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td style="text-align:center;">
                                            <div class="dropdown-wrapper">
                                                <button class="dropdown-toggle" onclick="toggleDropdown(event, '<?= $dropdownId ?>')" title="เมนู">⋮</button>
                                                <div class="dropdown-menu" id="<?= $dropdownId ?>">
                                                    <a href="view_risk.php?id=<?= $risk['id'] ?>" class="dropdown-item view">
                                                        <i class="fas fa-eye"></i> ดูรายละเอียด
                                                    </a>
                                                    <a href="generate_pdf.php?id=<?= $risk['id'] ?>" target="_blank" class="dropdown-item print">
                                                        <i class="fas fa-print"></i> พิมพ์ PDF
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <?php if ($canAddReport): ?>
                                                        <a href="report_summary.php?risk_id=<?= $risk['id'] ?>" class="dropdown-item report">
                                                            <i class="fas fa-file-alt"></i> เพิ่มสรุปผล
                                                        </a>
                                                    <?php elseif ($canViewReport): ?>
                                                        <a href="view_report.php?risk_id=<?= $risk['id'] ?>" class="dropdown-item report">
                                                            <i class="fas fa-file-invoice"></i> ดูสรุปผล
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="dropdown-item locked">
                                                            <i class="fas fa-file"></i> 
                                                            <?= ($isLockedForReport && empty($risk['report_id'])) ? 'สรุปผล (ล็อกจากสถานะ)' : 'สรุปผล (ไม่มีสิทธิ์)' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="dropdown-divider"></div>
                                                    <span class="dropdown-item-text">จัดการ</span>
                                                    <?php if (isAdmin()): ?>
                                                        <?php if (!$isLockedForEdit): ?>
                                                            <a href="risk_form.php?id=<?= $risk['id'] ?>" class="dropdown-item edit">
                                                                <i class="fas fa-edit"></i> แก้ไข
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="dropdown-item locked">
                                                                <i class="fas fa-lock"></i> แก้ไข (ล็อกจากสถานะ)
                                                            </span>
                                                        <?php endif; ?>
                                                        <div class="dropdown-divider"></div>
                                                        <button class="dropdown-item delete delete-single" data-id="<?= $risk['id'] ?>">
                                                            <i class="fas fa-trash"></i> ลบ
                                                        </button>
                                                    <?php else: ?>
                                                        <?php if ($canEditRisk): ?>
                                                            <a href="risk_form.php?id=<?= $risk['id'] ?>" class="dropdown-item edit">
                                                                <i class="fas fa-edit"></i> แก้ไข
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="dropdown-item locked">
                                                                <i class="fas fa-lock"></i> แก้ไข (<?= $isLockedForEdit ? 'ล็อกจากสถานะ' : 'แจ้ง Admin' ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="dropdown-item locked">
                                                            <i class="fas fa-ban"></i> ลบ (เฉพาะ Admin)
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
                    <?php if ($page > 1): ?><a href="<?= buildRiskPageUrl($page - 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
                    <?php $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2); ?>
                    <?php if ($start > 1): ?><a href="<?= buildRiskPageUrl(1, $_GET) ?>" class="page-link">1</a><?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?><?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++): ?><a href="<?= buildRiskPageUrl($i, $_GET) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                            <?php if ($end < $totalPages): ?><?php if ($end < $totalPages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?><a href="<?= buildRiskPageUrl($totalPages, $_GET) ?>" class="page-link"><?= $totalPages ?></a><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= buildRiskPageUrl($page + 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ==================== INFO CARD ==================== -->
            <div class="info-card">
                <div class="info-icon-circle">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h4>
                        <i class="fas fa-shield-alt text-blue-600"></i>
                        ข้อมูลการจัดการความเสี่ยง
                    </h4>
                    <ul>
                        <li><span class="dot"></span> <strong>Admin:</strong> จัดการได้ทั้งหมด</li>
                        <li><span class="dot"></span> <strong>User:</strong> แก้ไข/เพิ่มสรุปผลของตัวเอง</li>
                        <li><span class="dot"></span> <strong>สถานะ "ยุติ":</strong> <span style="color:#dc2626;font-weight:600;">ล็อกทั้งหมด</span></li>
                        <li><span class="dot"></span> <strong>มีรายงานแล้ว:</strong> แก้ไขไม่ได้อีก</li>
                        <li><span class="dot"></span> <strong>ดำเนินการแล้ว:</strong> ยังเพิ่มสรุปผลได้</li>
                        <li><span class="dot"></span> <strong>การลบ:</strong> เฉพาะ Admin เท่านั้น</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= $csrf_token ?>">

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ========== FILTER TOGGLE ==========
    function toggleFilter() {
        const collapse = document.getElementById('filterCollapse');
        const icon = document.getElementById('filterToggleIcon');
        const subtitle = document.querySelector('.filter-subtitle');
        collapse.classList.toggle('open');
        icon.classList.toggle('open');
        if (subtitle) {
            subtitle.textContent = collapse.classList.contains('open') ? 'คลิกเพื่อปิดกรองข้อมูล' : 'คลิกเพื่อเปิดกรองข้อมูล';
        }
    }

    // ========== DROPDOWN TOGGLE ==========
    function toggleDropdown(e, id) {
        e.stopPropagation();
        const menu = document.getElementById(id);
        const toggle = menu.previousElementSibling;
        document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
            if (openMenu.id !== id) {
                openMenu.classList.remove('show');
                if (openMenu.previousElementSibling) openMenu.previousElementSibling.classList.remove('active');
            }
        });
        menu.classList.toggle('show');
        toggle.classList.toggle('active');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-wrapper')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
                if (menu.previousElementSibling) menu.previousElementSibling.classList.remove('active');
            });
        }
    });

    // ========== AUTO SUBMIT FILTER ==========
    document.querySelectorAll('.auto-submit').forEach(function(element) {
        element.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // ========== DELETE RISKS ==========
    function deleteRisks(ids) {
        Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch('action.php?action=delete_risks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'ลบสำเร็จ!', timer: 2000, showConfirmButton: false }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message || 'ไม่สามารถลบรายการได้' });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'เชื่อมต่อล้มเหลว', text: 'กรุณาลองใหม่อีกครั้ง' }));
    }

    <?php if (isAdmin()): ?>
        // Select All
        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.risk-checkbox').forEach(cb => cb.checked = this.checked);
        });

        // Delete Selected
        document.getElementById('deleteSelected')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.risk-checkbox:checked');
            if (!checked.length) return Swal.fire({ icon: 'warning', title: 'กรุณาเลือกรายการ', confirmButtonColor: '#2563eb' });
            Swal.fire({
                title: '⚠️ ยืนยันการลบ',
                html: `<p>ต้องการลบ <strong>${checked.length} รายการ</strong>?</p><p style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> ข้อมูลจะถูกลบถาวร!</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '🗑️ ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) deleteRisks(Array.from(checked).map(cb => cb.value));
            });
        });

        // Delete Single
        document.querySelectorAll('.delete-single').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                Swal.fire({
                    title: '⚠️ ยืนยันการลบ',
                    html: '<p>ต้องการลบรายการนี้?</p><p style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> ข้อมูลจะถูกลบถาวร!</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '🗑️ ลบ',
                    cancelButtonText: 'ยกเลิก'
                }).then(result => {
                    if (result.isConfirmed) {
                        deleteRisks([this.dataset.id]);
                        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                    }
                });
            });
        });
    <?php endif; ?>

    // Print Selected
    document.getElementById('printSelected')?.addEventListener('click', function() {
        const selected = document.querySelectorAll('.risk-checkbox:checked');
        if (!selected.length) return Swal.fire({ icon: 'warning', title: 'กรุณาเลือกรายการ', confirmButtonColor: '#2563eb' });
        const ids = Array.from(selected).map(cb => cb.value).join(',');
        window.open('generate_pdf.php?ids=' + ids, '_blank');
    });

    // ========== KEYBOARD SHORTCUTS ==========
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                if (m.previousElementSibling) m.previousElementSibling.classList.remove('active');
            });
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            if (!e.target.closest('input, textarea, select')) {
                e.preventDefault();
                const sa = document.getElementById('selectAll');
                if (sa) { sa.checked = !sa.checked; sa.dispatchEvent(new Event('change')); }
            }
        }
        <?php if (isAdmin()): ?>
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            if (!e.target.closest('input, textarea, select')) {
                e.preventDefault();
                document.getElementById('deleteSelected')?.click();
            }
        }
        <?php endif; ?>
    });

    // Initialize
    (function() {
        const collapse = document.getElementById('filterCollapse');
        if (collapse && collapse.classList.contains('open')) {
            collapse.style.maxHeight = collapse.scrollHeight + 'px';
        }
    })();

    console.log('✅ Risk list loaded! Total:', <?= $totalRows ?>, '| Page:', <?= $page ?>, '/', <?= $totalPages ?>);
    <?= isAdmin() ? "console.log('👑 Admin mode');" : "console.log('👤 User: " . htmlspecialchars($_SESSION['username']) . "');" ?>
</script>

<?php include 'includes/footer.php'; ?>