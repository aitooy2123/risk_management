<?php

/**
 * รายการความเสี่ยง - UI สวยงาม ค้นหาอัตโนมัติ
 * - ฟิลเตอร์: กลุ่มงาน, ประเภท, ระดับ, สถานะ, วันที่, ค้นหา
 * - Admin: เห็นทั้งหมด / User: เห็นเฉพาะของตัวเอง
 * - เลือกลบ, พิมพ์ PDF, พิมพ์ทั้งหมด
 * - Pagination 10 รายการ/หน้า
 * - Badge สีแยกตามระดับและสถานะ
 * - ค้นหาอัตโนมัติเมื่อพิมพ์/เปลี่ยนค่า
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

if (!function_exists('canModify')) {
    function canModify($risk_user_id)
    {
        if (!isset($_SESSION['user_id'])) return false;
        if (isAdmin()) return true;
        return $_SESSION['user_id'] == $risk_user_id;
    }
}

function getStatusIcon($status)
{
    if ($status == 'ดำเนินการแล้ว') return 'fa-check-circle';
    if ($status == 'กำลังดำเนินการ') return 'fa-spinner';
    if ($status == 'ยุติ') return 'fa-stop-circle';
    return 'fa-clock';
}

$type_filter     = $_GET['risk_type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$group_filter    = $_GET['unit'] ?? '';
$status_filter   = $_GET['status'] ?? '';
$date_from       = $_GET['date_from'] ?? '';
$search          = $_GET['search'] ?? '';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (r.risk_type LIKE ? OR r.unit LIKE ? OR r.risk_detail LIKE ? OR r.risk_type_other LIKE ?)";
    $searchTerm = "%{$search}%";
    for ($i = 0; $i < 4; $i++) $params[] = $searchTerm;
}
if ($type_filter) {
    $where .= " AND r.risk_type = ?";
    $params[] = $type_filter;
}
if ($severity_filter) {
    $where .= " AND r.severity = ?";
    $params[] = $severity_filter;
}
if ($group_filter) {
    $where .= " AND r.unit = ?";
    $params[] = $group_filter;
}
if ($status_filter) {
    $where .= " AND r.status = ?";
    $params[] = $status_filter;
}
if ($date_from) {
    $where .= " AND DATE(r.event_datetime) >= ?";
    $params[] = $date_from;
}
if (!isAdmin()) {
    $where .= " AND r.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$countSql  = "SELECT COUNT(*) FROM risks r $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$allIdsSql  = "SELECT r.id FROM risks r $where ORDER BY r.created_at DESC";
$allIdsStmt = $pdo->prepare($allIdsSql);
$allIdsStmt->execute($params);
$allIds = $allIdsStmt->fetchAll(PDO::FETCH_COLUMN);

$dataSql = "SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id $where ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$risks = $stmt->fetchAll();

$types      = $pdo->query("SELECT DISTINCT risk_type FROM risks ORDER BY risk_type")->fetchAll(PDO::FETCH_COLUMN);
$severities = $pdo->query("SELECT DISTINCT severity FROM risks ORDER BY severity")->fetchAll(PDO::FETCH_COLUMN);
$units      = $pdo->query("SELECT DISTINCT unit FROM risks ORDER BY unit")->fetchAll(PDO::FETCH_COLUMN);

try {
    $statuses = $pdo->query("SELECT DISTINCT status FROM risks WHERE status IS NOT NULL AND status != '' ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($statuses)) $statuses = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];
} catch (PDOException $e) {
    $statuses = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];
}

$csrf_token = generateCsrfToken();

function buildPageUrl($page, $currentParams)
{
    $query = $currentParams;
    $query['page'] = $page;
    return 'risks.php?' . http_build_query($query);
}

$severityBadgeMap = [
    'A' => 'bg-blue-100 text-blue-700',
    'B' => 'bg-green-100 text-green-700',
    'C' => 'bg-lime-100 text-lime-700',
    'D' => 'bg-yellow-100 text-yellow-700',
    'F' => 'bg-orange-100 text-orange-700',
    'E' => 'bg-red-100 text-red-700'
];

$statusBadgeMap = [
    'ยังไม่ดำเนินการ' => 'bg-yellow-100 text-yellow-700',
    'กำลังดำเนินการ' => 'bg-blue-100 text-blue-700',
    'ดำเนินการแล้ว' => 'bg-green-100 text-green-700',
    'ยุติ' => 'bg-gray-100 text-gray-500'
];

$myCaseCount = 0;
if (!isAdmin()) {
    $myCaseStmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ?");
    $myCaseStmt->execute([$_SESSION['user_id']]);
    $myCaseCount = $myCaseStmt->fetchColumn();
}
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary: #3b82f6;
        --primary-dark: #1e40af;
    }

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
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
    }

    .filter-input:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
    }

    select.filter-input {
        cursor: pointer;
    }

    .search-box {
        position: relative;
        grid-column: 1 / -1;
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
        font-size: 0.9rem;
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
    }

    .btn-filter.danger {
        background: #fee2e2;
        color: #dc2626;
        text-decoration: none;
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

    .btn-action.green {
        background: #f0fdf4;
        color: #16a34a;
        border-color: #bbf7d0;
    }

    .btn-action.green:hover {
        background: #dcfce7;
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

    .user-cell {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .user-avatar-sm {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #eff6ff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        font-size: 0.7rem;
        flex-shrink: 0;
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

    .btn-add-floating {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #3b82f6;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
        transition: all 0.3s;
        z-index: 50;
        text-decoration: none;
    }

    .btn-add-floating:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 40px rgba(59, 130, 246, 0.5);
        background: #2563eb;
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr;
        }

        .stats-row {
            flex-direction: column;
        }
    }

    @media print {

        .sidebar,
        .filter-card,
        .action-bar,
        .pagination-bar,
        .btn-add-floating {
            display: none !important;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
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

            <div class="page-header">
                <h1>📋 รายการความเสี่ยง</h1>
                <p><?= !isAdmin() ? 'แสดงรายการของ <strong>' . htmlspecialchars($_SESSION['username']) . '</strong>' : 'มุมมองผู้ดูแลระบบ - เห็นทุกรายการ' ?></p>
            </div>

            <div class="stats-row">
                <div class="stat-badge"><i class="fas fa-database"></i><span>ทั้งหมด <strong><?= number_format($totalRows) ?></strong> รายการ</span></div>
                <?php if (!isAdmin()): ?><div class="stat-badge"><i class="fas fa-user"></i><span>ของคุณ <strong><?= number_format($myCaseCount) ?></strong> รายการ</span></div><?php endif; ?>
                <div class="stat-badge"><i class="fas fa-file-alt"></i><span>หน้า <strong><?= $page ?></strong> / <?= max(1, $totalPages) ?></span></div>
            </div>

            <!-- Filter (ค้นหาอัตโนมัติ) -->
            <div class="filter-card">
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" class="filter-input" placeholder="🔍 ค้นหาอัตโนมัติ... (พิมพ์แล้วรอ 500ms)">
                            <?php if ($search): ?><a href="risks.php" class="search-clear"><i class="fas fa-times"></i></a><?php endif; ?>
                        </div>
                        <div class="filter-group"><label class="filter-label">กลุ่มงาน</label><select name="unit" class="filter-input auto-submit">
                                <option value="">ทั้งหมด</option><?php foreach ($units as $u): ?><option value="<?= htmlspecialchars($u) ?>" <?= $group_filter == $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-group"><label class="filter-label">ประเภท</label><select name="risk_type" class="filter-input auto-submit">
                                <option value="">ทั้งหมด</option><?php foreach ($types as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $type_filter == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-group"><label class="filter-label">ระดับ</label><select name="severity" class="filter-input auto-submit">
                                <option value="">ทั้งหมด</option><?php foreach ($severities as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $severity_filter == $s ? 'selected' : '' ?>>ระดับ <?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-group"><label class="filter-label">สถานะ</label><select name="status" class="filter-input auto-submit">
                                <option value="">ทั้งหมด</option><?php foreach ($statuses as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $status_filter == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-group"><label class="filter-label">ตั้งแต่วันที่</label><input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="filter-input auto-submit"></div>
                    </div>
                    <div class="filter-actions">
                        <span style="font-size:0.8rem;color:#94a3b8;"><?php if ($search || $group_filter || $type_filter || $severity_filter || $status_filter || $date_from): ?><i class="fas fa-filter text-blue-500 mr-1"></i> กรองอยู่: <?= number_format($totalRows) ?> รายการ<?php endif; ?></span>
                        <?php if ($search || $group_filter || $type_filter || $severity_filter || $status_filter || $date_from): ?><a href="risks.php" class="btn-filter danger"><i class="fas fa-times"></i> ล้างตัวกรอง</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="action-bar">
                <?php if (isAdmin()): ?><button id="deleteSelected" class="btn-action red"><i class="fas fa-trash-alt"></i> ลบที่เลือก</button><?php endif; ?>
                <button id="printSelected" class="btn-action blue"><i class="fas fa-print"></i> พิมพ์ PDF ที่เลือก</button>
                <a href="generate_pdf.php?ids=<?= implode(',', $allIds) ?>" target="_blank" class="btn-action green"><i class="fas fa-file-pdf"></i> พิมพ์ทั้งหมด</a>
                <a href="risk_form.php" class="btn-action blue" style="margin-left:auto;"><i class="fas fa-plus-circle"></i> เพิ่มรายการใหม่</a>
            </div>

            <?php if (empty($risks)): ?>
                <div class="empty-state"><i class="fas fa-inbox"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">ไม่พบรายการความเสี่ยง</h3>
                    <p class="text-gray-400 mb-4"><?= isAdmin() ? 'ไม่มีข้อมูลตรงตามเงื่อนไข' : 'คุณยังไม่มีรายการ' ?></p><a href="risk_form.php" class="btn-filter danger" style="text-decoration:none;background:#3b82f6;color:white;"><?= $search || $group_filter || $type_filter || $severity_filter || $status_filter || $date_from ? '<i class="fas fa-redo"></i> ล้างตัวกรอง' : '<i class="fas fa-plus"></i> เพิ่มรายการแรก' ?></a>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-header-bar">
                        <span class="font-semibold text-gray-700"><i class="fas fa-list text-blue-600 mr-1"></i> รายการความเสี่ยง</span>
                        <span class="text-xs text-gray-500">แสดง <?= count($risks) ?> จาก <?= number_format($totalRows) ?> รายการ</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr><?php if (isAdmin()): ?><th style="width:40px;"><input type="checkbox" id="selectAll"></th><?php endif; ?><th style="width:40px;">#</th>
                                    <th>กลุ่มงาน</th>
                                    <th>ประเภท</th>
                                    <th>ระดับ</th>
                                    <th>สถานะ</th>
                                    <th>วันที่</th>
                                    <th>ผู้รายงาน</th>
                                    <th style="width:130px;text-align:center;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($risks as $index => $risk): $rowNum = ($page - 1) * $perPage + $index + 1;
                                    $sevBadge = $severityBadgeMap[$risk['severity']] ?? 'bg-gray-100 text-gray-600';
                                    $staBadge = $statusBadgeMap[$risk['status']] ?? 'bg-gray-100 text-gray-500';
                                    $isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $risk['user_id'];
                                    $statusIcon = getStatusIcon($risk['status']); ?>
                                    <tr>
                                        <?php if (isAdmin()): ?><td><input type="checkbox" class="risk-checkbox" value="<?= $risk['id'] ?>"></td><?php endif; ?>
                                        <td class="text-gray-400"><?= $rowNum ?></td>
                                        <td><span class="font-medium"><?= htmlspecialchars(mb_substr($risk['unit'] ?? '-', 0, 20)) ?></span></td>
                                        <td><?= htmlspecialchars(mb_substr($risk['risk_type'] . ($risk['risk_type_other'] ? ' (' . $risk['risk_type_other'] . ')' : ''), 0, 30)) ?></td>
                                        <td><span class="badge <?= $sevBadge ?>"><i class="fas fa-flag text-xs"></i> <?= htmlspecialchars($risk['severity']) ?></span></td>
                                        <td><?php if (!empty($risk['status'])): ?><span class="badge <?= $staBadge ?>"><i class="fas <?= $statusIcon ?> text-xs"></i> <?= htmlspecialchars($risk['status']) ?></span><?php else: ?><span class="badge bg-gray-100 text-gray-400">-</span><?php endif; ?></td>
                                        <td class="text-gray-500 text-sm"><?= date('d/m/Y', strtotime($risk['event_datetime'])) ?></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm"><i class="fas fa-user"></i></div><span><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?><?= ($isOwner && !isAdmin()) ? ' <span class="text-blue-500">(คุณ)</span>' : '' ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:3px;justify-content:center;"><a href="view_risk.php?id=<?= $risk['id'] ?>" class="btn-icon bg-blue-50 text-blue-600 hover:bg-blue-100" title="ดู"><i class="fas fa-eye text-sm"></i></a><a href="generate_pdf.php?id=<?= $risk['id'] ?>" target="_blank" class="btn-icon bg-green-50 text-green-600 hover:bg-green-100" title="PDF"><i class="fas fa-print text-sm"></i></a><?php if (canModify($risk['user_id'])): ?><a href="risk_form.php?id=<?= $risk['id'] ?>" class="btn-icon bg-yellow-50 text-yellow-600 hover:bg-yellow-100" title="แก้ไข"><i class="fas fa-edit text-sm"></i></a><button class="btn-icon bg-red-50 text-red-600 hover:bg-red-100 delete-single" data-id="<?= $risk['id'] ?>" title="ลบ"><i class="fas fa-trash text-sm"></i></button><?php endif; ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination-bar">
                    <?php if ($page > 1): ?><a href="<?= buildPageUrl($page - 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
                    <?php $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    if ($start > 1): ?><a href="<?= buildPageUrl(1, $_GET) ?>" class="page-link">1</a><?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?><?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++): ?><a href="<?= buildPageUrl($i, $_GET) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                            <?php if ($end < $totalPages): ?><?php if ($end < $totalPages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?><a href="<?= buildPageUrl($totalPages, $_GET) ?>" class="page-link"><?= $totalPages ?></a><?php endif; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= buildPageUrl($page + 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <a href="risk_form.php" class="btn-add-floating" title="เพิ่มรายการใหม่"><i class="fas fa-plus"></i></a>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= $csrf_token ?>">

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ========== Auto Submit Filter ==========
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
    function deleteRisks(ids) {
        fetch('action.php?action=delete_risks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                ids: ids,
                csrf_token: csrfToken
            })
        }).then(res => res.json()).then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'ลบสำเร็จ',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.message || 'ไม่สามารถลบได้'
                });
            }
        }).catch(() => Swal.fire({
            icon: 'error',
            title: 'การเชื่อมต่อล้มเหลว'
        }));
    }

    <?php if (isAdmin()): ?>
        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.risk-checkbox').forEach(cb => cb.checked = this.checked);
        });
        document.getElementById('deleteSelected')?.addEventListener('click', function() {
            const selected = document.querySelectorAll('.risk-checkbox:checked');
            if (selected.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกรายการ'
                });
                return;
            }
            const ids = Array.from(selected).map(cb => cb.value);
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบ <strong class="text-red-600">${ids.length} รายการ</strong> ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) deleteRisks(ids);
            });
        });
    <?php endif; ?>

    document.querySelectorAll('.delete-single').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'คุณต้องการลบรายการนี้ใช่หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if (r.isConfirmed) deleteRisks([this.dataset.id]);
            });
        });
    });

    document.getElementById('printSelected')?.addEventListener('click', function() {
        const selected = document.querySelectorAll('.risk-checkbox:checked');
        if (selected.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาเลือกรายการ'
            });
            return;
        }
        window.open('generate_pdf.php?ids=' + Array.from(selected).map(cb => cb.value).join(','), '_blank');
    });
</script>
<?php include 'includes/footer.php'; ?>