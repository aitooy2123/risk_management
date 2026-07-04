<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

// รับค่าช่วงวันที่
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = '';

$date_where = '';
$date_where_clause = '';
if ($date_from && $date_to) {
    $date_where = " WHERE event_datetime BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59' ";
    $date_where_clause = " AND event_datetime BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59' ";
} elseif ($date_from) {
    $date_where = " WHERE event_datetime >= '{$date_from} 00:00:00' ";
    $date_where_clause = " AND event_datetime >= '{$date_from} 00:00:00' ";
} elseif ($date_to) {
    $date_where = " WHERE event_datetime <= '{$date_to} 23:59:59' ";
    $date_where_clause = " AND event_datetime <= '{$date_to} 23:59:59' ";
}

// สถิติรวม
$totalRisks = $pdo->query("SELECT COUNT(*) FROM risks" . $date_where)->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// จำนวนความเสี่ยงตามระดับ - Doughnut Chart
$severityData = $pdo->query("SELECT severity, COUNT(*) as count FROM risks" . $date_where . " GROUP BY severity ORDER BY 
    CASE severity WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C' THEN 3 WHEN 'D' THEN 4 WHEN 'F' THEN 5 WHEN 'E' THEN 6 END")->fetchAll();

// ระดับความรุนแรงแบบเต็ม
$severityFullMap = [
    'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
    'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
    'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
    'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
    'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
    'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
];

// เตรียมข้อมูลสำหรับแสดงใน Tooltip
$severityFullLabels = [];
$severityFullCounts = [];
foreach ($severityData as $item) {
    $severityFullLabels[] = $severityFullMap[$item['severity']] ?? $item['severity'];
    $severityFullCounts[] = $item['count'];
}

// ประเภทความเสี่ยง - Polar Area
$riskTypes = $pdo->query("SELECT risk_type, COUNT(*) as count FROM risks" . $date_where . " GROUP BY risk_type ORDER BY count DESC")->fetchAll();

// รายการล่าสุด (กระชับ)
$recent = $pdo->query("SELECT r.unit, r.risk_type, r.severity, r.status, r.created_at 
    FROM risks r" . $date_where . " ORDER BY r.created_at DESC LIMIT 7")->fetchAll();

// สรุปกลุ่มงาน - Horizontal Bar ไล่สี
$groupSummary = $pdo->query("
    SELECT r.unit, COUNT(*) as total,
    COALESCE((SELECT r2.risk_type FROM risks r2 WHERE r2.unit = r.unit " . ($date_where ? ' AND ' . substr($date_where, 6) : '') . " GROUP BY r2.risk_type ORDER BY COUNT(*) DESC LIMIT 1), '-') as top_type
    FROM risks r $date_where GROUP BY r.unit ORDER BY total DESC
")->fetchAll();

// สรุปสถานะ - Stacked Bar
$statusSummary = $pdo->query("
    SELECT unit,
        SUM(CASE WHEN status='ยังไม่ดำเนินการ' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='กำลังดำเนินการ' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status='ดำเนินการแล้ว' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='ยุติ' THEN 1 ELSE 0 END) as terminated_count,
        COUNT(*) as total
    FROM risks $date_where GROUP BY unit ORDER BY total DESC
")->fetchAll();

// จำนวนผู้ใช้ตามบทบาท
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalNormalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// ผู้ใช้ที่รายงานมากที่สุด
$topReporters = $pdo->query("
    SELECT u.username, u.avatar, COUNT(*) as report_count 
    FROM risks r LEFT JOIN users u ON r.user_id = u.id 
    $date_where GROUP BY r.user_id ORDER BY report_count DESC LIMIT 5
")->fetchAll();

// จำนวนความเสี่ยงวันนี้
$todayRisks = $pdo->query("SELECT COUNT(*) FROM risks WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// จำนวนความเสี่ยงที่ดำเนินการแล้ว
$completedRisks = $pdo->query("SELECT COUNT(*) FROM risks WHERE status = 'ดำเนินการแล้ว'" . ($date_where ? ' AND ' . substr($date_where, 6) : ''))->fetchColumn();

// สถานะแยกตามประเภท (สำหรับแสดงจำนวน)
$statusCounts = [
    'ยังไม่ดำเนินการ' => $pdo->query("SELECT COUNT(*) FROM risks WHERE status = 'ยังไม่ดำเนินการ'" . $date_where_clause)->fetchColumn(),
    'กำลังดำเนินการ' => $pdo->query("SELECT COUNT(*) FROM risks WHERE status = 'กำลังดำเนินการ'" . $date_where_clause)->fetchColumn(),
    'ดำเนินการแล้ว' => $pdo->query("SELECT COUNT(*) FROM risks WHERE status = 'ดำเนินการแล้ว'" . $date_where_clause)->fetchColumn(),
    'ยุติ' => $pdo->query("SELECT COUNT(*) FROM risks WHERE status = 'ยุติ'" . $date_where_clause)->fetchColumn()
];

// เตรียมข้อมูลกราฟ
$severityLabels = array_column($severityData, 'severity');
$severityCounts = array_column($severityData, 'count');
$doughnutColors = ['#3b82f6', '#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'];

$riskLabels = array_column($riskTypes, 'risk_type');
$riskCounts = array_column($riskTypes, 'count');

$groupUnits = array_column($groupSummary, 'unit');
$groupTotals = array_column($groupSummary, 'total');
$groupTopTypes = array_column($groupSummary, 'top_type');

$statusUnits = array_column($statusSummary, 'unit');
$statusPending = array_column($statusSummary, 'pending');
$statusInProgress = array_column($statusSummary, 'in_progress');
$statusCompleted = array_column($statusSummary, 'completed');
$statusTerminated = array_column($statusSummary, 'terminated_count');

function getStatusClass($status)
{
    if ($status == 'ดำเนินการแล้ว') return 'bg-green-100 text-green-700';
    if ($status == 'กำลังดำเนินการ') return 'bg-blue-100 text-blue-700';
    if ($status == 'ยุติ') return 'bg-gray-100 text-gray-500';
    return 'bg-yellow-100 text-yellow-700';
}

function getSeverityClass($severity)
{
    if ($severity == 'A') return 'bg-blue-100 text-blue-700';
    if ($severity == 'B') return 'bg-green-100 text-green-700';
    if ($severity == 'C') return 'bg-lime-100 text-lime-700';
    if ($severity == 'D') return 'bg-yellow-100 text-yellow-700';
    if ($severity == 'F') return 'bg-orange-100 text-orange-700';
    if ($severity == 'E') return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-600';
}
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        position: relative;
    }
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 50% 80%, rgba(236, 72, 153, 0.05) 0%, transparent 50%);
        pointer-events: none;
        z-index: 0;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    /* Floating shapes decoration */
    .floating-shapes {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    }
    .floating-shapes .shape {
        position: absolute;
        border-radius: 50%;
        opacity: 0.3;
    }
    .floating-shapes .shape:nth-child(1) {
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(59,130,246,0.1), transparent);
        top: -100px;
        right: -100px;
        animation: float 20s ease-in-out infinite;
    }
    .floating-shapes .shape:nth-child(2) {
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(139,92,246,0.1), transparent);
        bottom: 50px;
        left: -50px;
        animation: float 25s ease-in-out infinite reverse;
    }
    .floating-shapes .shape:nth-child(3) {
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(236,72,153,0.1), transparent);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        animation: float 30s ease-in-out infinite;
    }
    @keyframes float {
        0%, 100% { transform: translate(0, 0) scale(1); }
        25% { transform: translate(30px, -30px) scale(1.1); }
        50% { transform: translate(-20px, 20px) scale(0.9); }
        75% { transform: translate(20px, -10px) scale(1.05); }
    }

    .dash-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem;
        padding: 1.75rem 2.25rem;
        margin-bottom: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
    }

    .dash-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .dash-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .dash-header p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.85rem;
        position: relative;
        z-index: 1;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem;
        padding: 1.25rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
        transition: all 0.3s;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.12);
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: -20px;
        right: -20px;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        opacity: 0.08;
    }

    .stat-card.blue::after { background: #3b82f6; }
    .stat-card.green::after { background: #22c55e; }
    .stat-card.purple::after { background: #8b5cf6; }
    .stat-card.orange::after { background: #f97316; }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .stat-trend {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
    }

    .stat-trend.up {
        background: #f0fdf4;
        color: #166534;
    }

    .stat-trend.info {
        background: #eff6ff;
        color: #1e40af;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
    }

    .stat-sub {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.35rem;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: all 0.3s;
    }

    .card:hover {
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.12);
    }

    .card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(248, 250, 252, 0.8);
        display: flex;
        align-items: center;
        gap: 0.65rem;
        background: rgba(250, 251, 252, 0.5);
    }

    .card-header-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .card-header-title {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.95rem;
    }

    .card-body {
        padding: 1.25rem;
        background: rgba(255, 255, 255, 0.3);
    }

    .table-compact {
        width: 100%;
        border-collapse: collapse;
    }

    .table-compact th {
        text-align: left;
        padding: 0.45rem 0.6rem;
        font-size: 0.62rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        background: rgba(250, 251, 252, 0.5);
        border-bottom: 1.5px solid rgba(226, 232, 240, 0.5);
    }

    .table-compact td {
        padding: 0.5rem 0.6rem;
        border-bottom: 1px solid rgba(248, 250, 252, 0.8);
        font-size: 0.78rem;
        color: #334155;
    }

    .table-compact tr:last-child td {
        border-bottom: none;
    }

    .table-compact tr:hover td {
        background: rgba(250, 251, 252, 0.5);
    }

    .badge-xs {
        display: inline-flex;
        align-items: center;
        padding: 0.08rem 0.4rem;
        border-radius: 9999px;
        font-size: 0.62rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .filter-bar {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
        padding: 0.85rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    }

    .filter-input {
        padding: 0.45rem 0.65rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 0.4rem;
        font-size: 0.8rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: rgba(250, 251, 252, 0.8);
        transition: all 0.2s;
    }

    .filter-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-filter {
        padding: 0.45rem 0.85rem;
        border-radius: 0.4rem;
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        font-family: 'Sarabun', sans-serif;
    }

    .btn-filter.danger {
        background: #fee2e2;
        color: #dc2626;
        text-decoration: none;
    }

    .btn-filter.danger:hover {
        background: #fecaca;
    }

    .top-reporter-card {
        text-align: center;
        padding: 1.25rem 0.75rem;
        background: rgba(250, 251, 252, 0.5);
        border-radius: 0.75rem;
        min-width: 110px;
        transition: all 0.3s;
        flex: 1;
        border: 1px solid rgba(226, 232, 240, 0.3);
    }

    .top-reporter-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
        background: rgba(255, 255, 255, 0.9);
    }

    .top-reporter-card .medal {
        font-size: 1.5rem;
        margin-bottom: 0.35rem;
    }

    .top-reporter-card .avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(226, 232, 240, 0.8);
        margin-bottom: 0.35rem;
    }

    .top-reporter-card .name {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.8rem;
    }

    .top-reporter-card .count {
        font-size: 1.5rem;
        font-weight: 700;
        color: #3b82f6;
    }

    .top-reporter-card .label {
        font-size: 0.65rem;
        color: #94a3b8;
    }

    /* ข้อความเตือนวันที่ */
    .date-warning {
        font-size: 0.7rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    .date-warning i {
        color: #f59e0b;
    }

    /* สถานะสี */
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-progress { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-terminated { background: #f1f5f9; color: #475569; }

    /* สถานะ Summary Box */
    .status-summary-box {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
    }
    .status-summary-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.3rem 0.8rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        border: 1px solid rgba(226, 232, 240, 0.5);
    }
    .status-summary-item .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .status-summary-item .count {
        font-weight: 700;
        margin-left: 0.2rem;
    }
    .dot-pending { background: #fbbf24; }
    .dot-progress { background: #60a5fa; }
    .dot-completed { background: #34d399; }
    .dot-terminated { background: #94a3b8; }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .content-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .stats-grid { grid-template-columns: 1fr; }
        .status-summary-box { flex-direction: column; align-items: flex-start; }
    }

    @media print {
        .sidebar, .filter-bar, .floating-shapes { display: none !important; }
    }
</style>

<div class="flex h-screen" style="position:relative;z-index:1;">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <!-- Floating Shapes Background -->
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        
        <div class="dashboard-container">

            <div class="dash-header">
                <h1>📊 ภาพรวมระบบ</h1>
                <p>ข้อมูล ณ วันที่ <?= date('d/m/Y') ?> | ศูนย์อนามัยที่ 8 อุดรธานี</p>
            </div>

            <!-- Filter (ค้นหาอัตโนมัติ) -->
            <form method="GET" id="filterForm" class="filter-bar">
                <span style="font-weight:600;color:#64748b;font-size:0.8rem;"><i class="fas fa-filter mr-1"></i> กรองตามวันที่:</span>
                <input type="date" name="date_from" id="dateFrom" value="<?= htmlspecialchars($date_from) ?>" class="filter-input auto-submit" max="<?= date('Y-m-d') ?>">
                <span style="color:#94a3b8;font-size:0.8rem;">ถึง</span>
                <input type="date" name="date_to" id="dateTo" value="<?= htmlspecialchars($date_to) ?>" class="filter-input auto-submit" max="<?= date('Y-m-d') ?>">
                <span class="date-warning"><i class="fas fa-info-circle"></i> ไม่สามารถเลือกวันที่ในอนาคตได้</span>
                <?php if ($date_from || $date_to): ?>
                    <a href="dashboard.php" class="btn-filter danger"><i class="fas fa-times"></i> รีเซ็ต</a>
                <?php endif; ?>
            </form>

            <!-- Status Summary Box -->
            <div class="status-summary-box" style="margin-bottom:1.5rem;">
                <div class="status-summary-item" style="background:rgba(254,243,199,0.5);border-color:#fcd34d;">
                    <span class="dot dot-pending"></span>
                    ยังไม่ดำเนินการ <span class="count"><?= $statusCounts['ยังไม่ดำเนินการ'] ?></span>
                </div>
                <div class="status-summary-item" style="background:rgba(219,234,254,0.5);border-color:#93c5fd;">
                    <span class="dot dot-progress"></span>
                    กำลังดำเนินการ <span class="count"><?= $statusCounts['กำลังดำเนินการ'] ?></span>
                </div>
                <div class="status-summary-item" style="background:rgba(209,250,229,0.5);border-color:#6ee7b7;">
                    <span class="dot dot-completed"></span>
                    ดำเนินการแล้ว <span class="count"><?= $statusCounts['ดำเนินการแล้ว'] ?></span>
                </div>
                <div class="status-summary-item" style="background:rgba(241,245,249,0.5);border-color:#cbd5e1;">
                    <span class="dot dot-terminated"></span>
                    ยุติ <span class="count"><?= $statusCounts['ยุติ'] ?></span>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-icon bg-blue-50 text-blue-600"><i class="fas fa-exclamation-triangle"></i></div>
                        <span class="stat-trend up"><i class="fas fa-arrow-up text-xs"></i> +<?= $todayRisks ?> วันนี้</span>
                    </div>
                    <div class="stat-value"><?= number_format($totalRisks) ?></div>
                    <div class="stat-label">ความเสี่ยงทั้งหมด</div>
                    <div class="stat-sub">ดำเนินการแล้ว <?= number_format($completedRisks) ?> รายการ</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-icon bg-purple-50 text-purple-600"><i class="fas fa-users"></i></div>
                        <span class="stat-trend info"><i class="fas fa-user-shield text-xs"></i> <?= $totalAdmins ?> Admin</span>
                    </div>
                    <div class="stat-value"><?= number_format($totalUsers) ?></div>
                    <div class="stat-label">ผู้ใช้งานทั้งหมด</div>
                    <div class="stat-sub"><?= number_format($totalNormalUsers) ?> ผู้ใช้ทั่วไป</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-icon bg-green-50 text-green-600"><i class="fas fa-calendar-day"></i></div>
                    </div>
                    <div class="stat-value"><?= number_format($todayRisks) ?></div>
                    <div class="stat-label">รายงานวันนี้</div>
                    <div class="stat-sub"><?= date('d/m/Y') ?></div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-header">
                        <div class="stat-icon bg-orange-50 text-orange-600"><i class="fas fa-check-double"></i></div>
                    </div>
                    <div class="stat-value"><?= number_format($completedRisks) ?></div>
                    <div class="stat-label">ดำเนินการแล้ว</div>
                    <div class="stat-sub"><?= $totalRisks > 0 ? number_format(($completedRisks / $totalRisks) * 100, 1) . '%' : '0%' ?> ของทั้งหมด</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-orange-50 text-orange-600"><i class="fas fa-chart-pie"></i></div>
                        <h3 class="card-header-title">ระดับความรุนแรง</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 320px;"><canvas id="severityDoughnut"></canvas></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-cyan-50 text-cyan-600"><i class="fas fa-chart-pie"></i></div>
                        <h3 class="card-header-title">ประเภทความเสี่ยง</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 320px;"><canvas id="riskTypePolar"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-indigo-50 text-indigo-600"><i class="fas fa-chart-bar"></i></div>
                        <h3 class="card-header-title">จำนวนเคสตามกลุ่มงาน</h3>
                        <span style="font-size:0.65rem;color:#94a3b8;margin-left:auto;">🔴มาก → 🔵น้อย</span>
                    </div>
                    <div class="card-body">
                        <div style="height: 320px;"><canvas id="groupChart"></canvas></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-blue-50 text-blue-600"><i class="fas fa-tasks"></i></div>
                        <h3 class="card-header-title">สถานะการดำเนินการ</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 320px;"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-emerald-50 text-emerald-600"><i class="fas fa-clock"></i></div>
                        <h3 class="card-header-title">รายการล่าสุด</h3>
                        <a href="risks.php" style="margin-left:auto;font-size:0.7rem;color:#3b82f6;text-decoration:none;">ดูทั้งหมด →</a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($recent)): ?>
                            <div class="text-center py-10 text-gray-400"><i class="fas fa-inbox text-2xl mb-1"></i>
                                <p style="font-size:0.8rem;">ยังไม่มีข้อมูล</p>
                            </div>
                        <?php else: ?>
                            <table class="table-compact">
                                <thead>
                                    <tr>
                                        <th>กลุ่มงาน</th>
                                        <th>ประเภท</th>
                                        <th>ระดับ</th>
                                        <th>สถานะ</th>
                                        <th style="text-align:right;">วันที่</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $row): 
                                        $sevClass = getSeverityClass($row['severity']);
                                        $status = $row['status'] ?? 'ยังไม่ดำเนินการ';
                                        $statusColor = '';
                                        if ($status == 'ยังไม่ดำเนินการ') $statusColor = 'status-pending';
                                        elseif ($status == 'กำลังดำเนินการ') $statusColor = 'status-progress';
                                        elseif ($status == 'ดำเนินการแล้ว') $statusColor = 'status-completed';
                                        elseif ($status == 'ยุติ') $statusColor = 'status-terminated';
                                    ?>
                                        <tr>
                                            <td style="color:#94a3b8;"><?= htmlspecialchars(mb_substr($row['unit'] ?? '-', 0, 12)) ?></td>
                                            <td style="font-weight:500;"><?= htmlspecialchars(mb_substr($row['risk_type'] ?? '-', 0, 18)) ?></td>
                                            <td><span class="badge-xs <?= $sevClass ?>"><?= htmlspecialchars($row['severity'] ?? '-') ?></span></td>
                                            <td>
                                                <span class="badge-xs <?= $statusColor ?>"><?= htmlspecialchars(mb_substr($status, 0, 10)) ?></span>
                                            </td>
                                            <td style="text-align:right;color:#94a3b8;font-size:0.72rem;"><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-amber-50 text-amber-600"><i class="fas fa-trophy"></i></div>
                        <h3 class="card-header-title">🏆 ผู้รายงานสูงสุด</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topReporters)): ?>
                            <div class="text-center py-10 text-gray-400"><i class="fas fa-inbox text-2xl mb-1"></i>
                                <p style="font-size:0.8rem;">ยังไม่มีข้อมูล</p>
                            </div>
                        <?php else: ?>
                            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; justify-content:center;">
                                <?php foreach ($topReporters as $index => $reporter): $medal = $index == 0 ? '🥇' : ($index == 1 ? '🥈' : ($index == 2 ? '🥉' : '⭐')); ?>
                                    <div class="top-reporter-card">
                                        <div class="medal"><?= $medal ?></div>
                                        <img src="avatars/<?= htmlspecialchars($reporter['avatar'] ?: 'default.png') ?>" class="avatar" onerror="this.src='avatars/default.png'">
                                        <div class="name"><?= htmlspecialchars($reporter['username']) ?></div>
                                        <div class="count"><?= number_format($reporter['report_count']) ?></div>
                                        <div class="label">รายการ</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // ========== Auto Submit Filter ==========
    document.querySelectorAll('.auto-submit').forEach(el => {
        el.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // ========== ห้ามเลือกวันที่ในอนาคต ==========
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const dateFrom = document.getElementById('dateFrom');
        const dateTo = document.getElementById('dateTo');
        
        if (dateFrom) dateFrom.setAttribute('max', today);
        if (dateTo) dateTo.setAttribute('max', today);

        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', function() {
                if (dateTo.value && this.value > dateTo.value) {
                    alert('⚠️ วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด');
                    this.value = dateTo.value;
                }
            });
            dateTo.addEventListener('change', function() {
                if (dateFrom.value && this.value < dateFrom.value) {
                    alert('⚠️ วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มต้น');
                    this.value = dateFrom.value;
                }
            });
        }
    });

    // ========== Charts ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Doughnut - ใช้ชื่อเต็มใน Legend
        new Chart(document.getElementById('severityDoughnut'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($severityFullLabels) ?>,
                datasets: [{
                    data: <?= json_encode($severityFullCounts) ?>,
                    backgroundColor: <?= json_encode($doughnutColors) ?>,
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 12,
                            font: {
                                size: 10
                            },
                            boxWidth: 12,
                            boxHeight: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.parsed || 0;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' รายการ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Polar Area
        new Chart(document.getElementById('riskTypePolar'), {
            type: 'polarArea',
            data: {
                labels: <?= json_encode($riskLabels) ?>,
                datasets: [{
                    data: <?= json_encode($riskCounts) ?>,
                    backgroundColor: ['rgba(99,102,241,0.7)', 'rgba(59,130,246,0.7)', 'rgba(34,197,94,0.7)', 'rgba(234,179,8,0.7)', 'rgba(249,115,22,0.7)', 'rgba(239,68,68,0.7)', 'rgba(139,92,246,0.7)', 'rgba(20,184,166,0.7)'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 12,
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.parsed || 0;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' รายการ (' + percentage + '%)';
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        ticks: {
                            display: false
                        }
                    }
                }
            }
        });

        // Heatmap Bar
        var groupData = <?= json_encode($groupTotals) ?>;
        var maxVal = Math.max.apply(null, groupData);
        var groupBgColors = [], groupBorderColors = [];
        var heatColors = ['#dc2626', '#ef4444', '#f97316', '#fb923c', '#f59e0b', '#fbbf24', '#a3e635', '#22c55e', '#10b981', '#06b6d4', '#3b82f6', '#6366f1'];
        for (var i = 0; i < <?= count($groupUnits) ?>; i++) {
            var ratio = maxVal > 0 ? groupData[i] / maxVal : 0;
            var colorIndex = Math.max(0, Math.min(Math.round(ratio * (heatColors.length - 1)), heatColors.length - 1));
            groupBgColors.push(heatColors[colorIndex]);
            groupBorderColors.push(heatColors[colorIndex]);
        }
        new Chart(document.getElementById('groupChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($groupUnits) ?>,
                datasets: [{
                    label: 'จำนวนเคส',
                    data: groupData,
                    backgroundColor: groupBgColors,
                    borderColor: groupBorderColors,
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(ctx) {
                                return 'ประเภทที่พบบ่อย: ' + <?= json_encode($groupTopTypes) ?>[ctx.dataIndex];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(241, 245, 249, 0.5)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Stacked Bar
        new Chart(document.getElementById('statusChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($statusUnits) ?>,
                datasets: [{
                    label: 'ยังไม่ดำเนินการ',
                    data: <?= json_encode($statusPending) ?>,
                    backgroundColor: '#fbbf24',
                    borderRadius: 4
                }, {
                    label: 'กำลังดำเนินการ',
                    data: <?= json_encode($statusInProgress) ?>,
                    backgroundColor: '#60a5fa',
                    borderRadius: 4
                }, {
                    label: 'ดำเนินการแล้ว',
                    data: <?= json_encode($statusCompleted) ?>,
                    backgroundColor: '#34d399',
                    borderRadius: 4
                }, {
                    label: 'ยุติ',
                    data: <?= json_encode($statusTerminated) ?>,
                    backgroundColor: '#94a3b8',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                var value = context.parsed.x || 0;
                                return label + ': ' + value + ' รายการ';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(241, 245, 249, 0.5)'
                        }
                    },
                    y: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
</script>
<?php include 'includes/footer.php'; ?>