<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

// รับค่าช่วงวันที่
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';

if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = '';

// CSRF Token สำหรับ refresh
$csrf_token = generateCsrfToken();

// สร้าง WHERE clause แบบปลอดภัย
$where_clause = '';
$where_params = [];

if ($date_from && $date_to) {
    $where_clause = "WHERE r.created_at BETWEEN :date_from AND :date_to";
    $where_params = [
        ':date_from' => $date_from . ' 00:00:00',
        ':date_to' => $date_to . ' 23:59:59'
    ];
} elseif ($date_from) {
    $where_clause = "WHERE r.created_at >= :date_from";
    $where_params = [
        ':date_from' => $date_from . ' 00:00:00'
    ];
} elseif ($date_to) {
    $where_clause = "WHERE r.created_at <= :date_to";
    $where_params = [
        ':date_to' => $date_to . ' 23:59:59'
    ];
}

// เพิ่มตัวกรองสถานะ
if ($status_filter) {
    if ($where_clause) {
        $where_clause .= " AND r.status = :status";
    } else {
        $where_clause = "WHERE r.status = :status";
    }
    $where_params[':status'] = $status_filter;
}

// เพิ่มตัวกรองประเภท
if ($type_filter) {
    if ($where_clause) {
        $where_clause .= " AND r.risk_type = :risk_type";
    } else {
        $where_clause = "WHERE r.risk_type = :risk_type";
    }
    $where_params[':risk_type'] = $type_filter;
}

// ฟังก์ชันสำหรับ execute query
function executeQuery($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll($pdo, $sql, $params = []) {
    return executeQuery($pdo, $sql, $params)->fetchAll();
}

function fetchColumn($pdo, $sql, $params = []) {
    return executeQuery($pdo, $sql, $params)->fetchColumn();
}

// ===== ลำดับกลุ่มงานที่ต้องการ =====
$unitOrderList = [
    'กลุ่มผู้บริหาร',
    'กลุ่มอำนวยการ',
    'กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน',
    'กลุ่มพัฒนาอนามัยแม่และเด็ก',
    'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน',
    'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยรุ่น',
    'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน',
    'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ',
    'กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม'
];
$unitOrderString = "'" . implode("','", $unitOrderList) . "'";

// ===== สถิติรวม =====
$totalRisks = fetchColumn($pdo, "SELECT COUNT(*) FROM risks r " . $where_clause, $where_params);
$totalUsers = fetchColumn($pdo, "SELECT COUNT(*) FROM users");

// ===== จำนวนความเสี่ยงตามระดับ - Doughnut Chart =====
$severityData = fetchAll($pdo, 
    "SELECT severity, COUNT(*) as count FROM risks r " . $where_clause . " 
     GROUP BY severity 
     ORDER BY CASE severity 
        WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C' THEN 3 
        WHEN 'D' THEN 4 WHEN 'F' THEN 5 WHEN 'E' THEN 6 
     END",
    $where_params
);

$severityFullMap = [
    'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
    'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
    'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
    'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
    'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
    'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
];

$severityFullLabels = [];
$severityFullCounts = [];
foreach ($severityData as $item) {
    $severityFullLabels[] = $severityFullMap[$item['severity']] ?? $item['severity'];
    $severityFullCounts[] = $item['count'];
}

// ===== ประเภทความเสี่ยง - Polar Area =====
$riskTypes = fetchAll($pdo, 
    "SELECT risk_type, COUNT(*) as count FROM risks r " . $where_clause . " 
     GROUP BY risk_type ORDER BY count DESC",
    $where_params
);

// ===== รายการล่าสุด =====
$recent = fetchAll($pdo, 
    "SELECT r.unit, r.risk_type, r.severity, r.status, r.created_at 
    FROM risks r " . $where_clause . " 
    ORDER BY r.created_at DESC LIMIT 7",
    $where_params
);

// ===== สรุปกลุ่มงาน - เรียงตามลำดับที่กำหนด =====
$groupSummary = fetchAll($pdo, 
    "SELECT r.unit, COUNT(*) as total
    FROM risks r " . $where_clause . " 
    GROUP BY r.unit 
    ORDER BY FIELD(r.unit, $unitOrderString), total DESC",
    $where_params
);

// หา top type สำหรับแต่ละกลุ่ม
$groupTopTypes = [];
foreach ($groupSummary as $group) {
    $unit = $group['unit'];
    
    // สร้าง WHERE clause สำหรับ query ย่อย
    $unitWhere = "WHERE unit = :unit_name";
    $unitParams = [':unit_name' => $unit];
    
    // เพิ่มเงื่อนไขวันที่ถ้ามี
    if ($date_from && $date_to) {
        $unitWhere .= " AND created_at BETWEEN :date_from AND :date_to";
        $unitParams[':date_from'] = $date_from . ' 00:00:00';
        $unitParams[':date_to'] = $date_to . ' 23:59:59';
    } elseif ($date_from) {
        $unitWhere .= " AND created_at >= :date_from";
        $unitParams[':date_from'] = $date_from . ' 00:00:00';
    } elseif ($date_to) {
        $unitWhere .= " AND created_at <= :date_to";
        $unitParams[':date_to'] = $date_to . ' 23:59:59';
    }
    
    // เพิ่มตัวกรองสถานะ
    if ($status_filter) {
        $unitWhere .= " AND status = :status";
        $unitParams[':status'] = $status_filter;
    }
    
    // เพิ่มตัวกรองประเภท
    if ($type_filter) {
        $unitWhere .= " AND risk_type = :risk_type";
        $unitParams[':risk_type'] = $type_filter;
    }
    
    $topTypeSql = "SELECT risk_type FROM risks " . $unitWhere . 
                  " GROUP BY risk_type ORDER BY COUNT(*) DESC LIMIT 1";
    
    $topTypeResult = fetchAll($pdo, $topTypeSql, $unitParams);
    $groupTopTypes[$unit] = !empty($topTypeResult) ? $topTypeResult[0]['risk_type'] : '-';
}

// ===== สรุปสถานะ - เรียงตามลำดับที่กำหนด =====
$statusSummary = fetchAll($pdo, 
    "SELECT unit,
        SUM(CASE WHEN status='ยังไม่ดำเนินการ' OR status IS NULL OR status='' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='กำลังดำเนินการ' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status='ดำเนินการแล้ว' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='ยุติ' THEN 1 ELSE 0 END) as terminated_count,
        COUNT(*) as total
    FROM risks r " . $where_clause . " 
    GROUP BY unit 
    ORDER BY FIELD(unit, $unitOrderString), total DESC",
    $where_params
);

// ===== จำนวนผู้ใช้ตามบทบาท =====
$totalAdmins = fetchColumn($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin'");
$totalNormalUsers = fetchColumn($pdo, "SELECT COUNT(*) FROM users WHERE role = 'user'");

// ===== ผู้ใช้ที่รายงานมากที่สุด =====
$topReporters = fetchAll($pdo, 
    "SELECT u.username, u.avatar, COUNT(*) as report_count 
    FROM risks r 
    LEFT JOIN users u ON r.user_id = u.id 
    " . $where_clause . " 
    GROUP BY r.user_id, u.username, u.avatar 
    ORDER BY report_count DESC LIMIT 5",
    $where_params
);

// ===== จำนวนความเสี่ยงวันนี้ =====
$todayRisks = fetchColumn($pdo, "SELECT COUNT(*) FROM risks WHERE DATE(created_at) = CURDATE()");

// ===== จำนวนความเสี่ยงที่ดำเนินการแล้ว =====
$completedSql = "SELECT COUNT(*) FROM risks r WHERE status = 'ดำเนินการแล้ว'";
if (!empty($where_params)) {
    if ($date_from && $date_to) {
        $completedSql .= " AND r.created_at BETWEEN :date_from AND :date_to";
    } elseif ($date_from) {
        $completedSql .= " AND r.created_at >= :date_from";
    } elseif ($date_to) {
        $completedSql .= " AND r.created_at <= :date_to";
    }
}
$completedRisks = fetchColumn($pdo, $completedSql, $where_params);

// ===== สถานะแยกตามประเภท =====
$statusCounts = [
    'ยังไม่ดำเนินการ' => 0,
    'กำลังดำเนินการ' => 0,
    'ดำเนินการแล้ว' => 0,
    'ยุติ' => 0
];

// นับจำนวนแต่ละสถานะ
$statusConditions = [
    'ยังไม่ดำเนินการ' => "(status = 'ยังไม่ดำเนินการ' OR status IS NULL OR status = '')",
    'กำลังดำเนินการ' => "status = 'กำลังดำเนินการ'",
    'ดำเนินการแล้ว' => "status = 'ดำเนินการแล้ว'",
    'ยุติ' => "status = 'ยุติ'"
];

foreach ($statusConditions as $statusName => $statusCondition) {
    $countSql = "SELECT COUNT(*) FROM risks r WHERE " . $statusCondition;
    if (!empty($where_params)) {
        if ($date_from && $date_to) {
            $countSql .= " AND r.created_at BETWEEN :date_from AND :date_to";
        } elseif ($date_from) {
            $countSql .= " AND r.created_at >= :date_from";
        } elseif ($date_to) {
            $countSql .= " AND r.created_at <= :date_to";
        }
    }
    $statusCounts[$statusName] = fetchColumn($pdo, $countSql, $where_params);
}

// ===== เตรียมข้อมูลกราฟ =====
$severityLabels = array_column($severityData, 'severity');
$severityCounts = array_column($severityData, 'count');
$doughnutColors = ['#3b82f6', '#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'];

$riskLabels = array_column($riskTypes, 'risk_type');
$riskCounts = array_column($riskTypes, 'count');

$groupUnits = array_column($groupSummary, 'unit');
$groupTotals = array_column($groupSummary, 'total');
$groupTopTypesArray = [];
foreach ($groupUnits as $unit) {
    $groupTopTypesArray[] = $groupTopTypes[$unit] ?? '-';
}

$statusUnits = array_column($statusSummary, 'unit');
$statusPending = array_column($statusSummary, 'pending');
$statusInProgress = array_column($statusSummary, 'in_progress');
$statusCompleted = array_column($statusSummary, 'completed');
$statusTerminated = array_column($statusSummary, 'terminated_count');

// สีสำหรับกราฟกลุ่มงาน (กลุ่มละสี)
$groupColors = [
    '#3b82f6', '#6366f1', '#8b5cf6', '#06b6d4',
    '#10b981', '#f59e0b', '#ef4444', '#ec4899',
    '#14b8a6', '#f97316', '#84cc16', '#a855f7'
];
$groupBorderColors = [
    '#2563eb', '#4f46e5', '#7c3aed', '#0891b2',
    '#059669', '#d97706', '#dc2626', '#db2777',
    '#0d9488', '#ea580c', '#65a30d', '#9333ea'
];

// ===== ฟังก์ชั่นช่วย =====
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

function getStatusColorClass($status) {
    if ($status == 'ยังไม่ดำเนินการ') return 'status-pending';
    if ($status == 'กำลังดำเนินการ') return 'status-progress';
    if ($status == 'ดำเนินการแล้ว') return 'status-completed';
    if ($status == 'ยุติ') return 'status-terminated';
    return 'status-pending';
}

// ===== ฟังก์ชันแปลงวันที่เป็น พ.ศ. พร้อมชื่อเดือนภาษาไทย =====
function dateToThai($date, $format = 'full') {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp) + 543;
    
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $thaiMonthsShort = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    
    switch ($format) {
        case 'full':
            return $day . ' ' . $thaiMonths[$month] . ' ' . $year;
        case 'short':
            return $day . ' ' . $thaiMonthsShort[$month] . ' ' . $year;
        case 'numeric':
            return date('d/m/', $timestamp) . $year;
        default:
            return date('d/m/', $timestamp) . $year;
    }
}

// วันที่ปัจจุบันในรูปแบบ พ.ศ.
$currentThaiDate = dateToThai(date('Y-m-d'), 'full');
$currentThaiDateShort = dateToThai(date('Y-m-d'), 'short');

// ประเภทความเสี่ยงทั้งหมดสำหรับตัวกรอง
$types = [
    'ความเสี่ยงทางด้านกลยุทธ์',
    'ความเสี่ยงทางด้านการเงิน',
    'ความเสี่ยงทางด้านการปฏิบัติงาน',
    'ความเสี่ยงทางด้านกฎหมาย',
    'ความเสี่ยงด้านสิ่งแวดล้อม',
    'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข'
];
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap');
    
    * {
        font-family: 'Sarabun', sans-serif;
    }
    
    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        position: relative;
    }
    body::before {
        content: '';
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: 
            radial-gradient(circle at 20% 50%, rgba(59,130,246,0.05) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139,92,246,0.05) 0%, transparent 50%),
            radial-gradient(circle at 50% 80%, rgba(236,72,153,0.05) 0%, transparent 50%);
        pointer-events: none; z-index: 0;
    }

    .dashboard-container { max-width: 1400px; margin: 0 auto; position: relative; z-index: 1; }

    .floating-shapes { position: fixed; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; z-index: 0; overflow: hidden; }
    .floating-shapes .shape { position: absolute; border-radius: 50%; opacity: 0.3; }
    .floating-shapes .shape:nth-child(1) { width: 300px; height: 300px; background: radial-gradient(circle, rgba(59,130,246,0.1), transparent); top: -100px; right: -100px; animation: float 20s ease-in-out infinite; }
    .floating-shapes .shape:nth-child(2) { width: 200px; height: 200px; background: radial-gradient(circle, rgba(139,92,246,0.1), transparent); bottom: 50px; left: -50px; animation: float 25s ease-in-out infinite reverse; }
    .floating-shapes .shape:nth-child(3) { width: 150px; height: 150px; background: radial-gradient(circle, rgba(236,72,153,0.1), transparent); top: 50%; left: 50%; transform: translate(-50%, -50%); animation: float 30s ease-in-out infinite; }
    @keyframes float { 0%,100% { transform: translate(0,0) scale(1); } 25% { transform: translate(30px,-30px) scale(1.1); } 50% { transform: translate(-20px,20px) scale(0.9); } 75% { transform: translate(20px,-10px) scale(1.05); } }

    .dash-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem; padding: 1.75rem 2.25rem; margin-bottom: 1.5rem;
        color: white; position: relative; overflow: hidden;
        box-shadow: 0 10px 40px rgba(37,99,235,0.3);
    }
    .dash-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: rgba(255,255,255,0.03); border-radius: 50%; }
    .dash-header h1 { font-size: 1.6rem; font-weight: 700; position: relative; z-index: 1; }
    .dash-header p { color: rgba(255,255,255,0.7); font-size: 0.85rem; position: relative; z-index: 1; }

    .header-actions {
        display: flex; gap: 0.5rem; margin-left: auto; position: relative; z-index: 1;
        flex-wrap: wrap;
    }

    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card {
        background: rgba(255,255,255,0.9); backdrop-filter: blur(20px);
        border-radius: 1rem; padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.5);
        transition: all 0.3s; box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        position: relative; overflow: hidden;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 48px rgba(0,0,0,0.12); }
    .stat-card::after { content: ''; position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; border-radius: 50%; opacity: 0.08; }
    .stat-card.blue::after { background: #3b82f6; }
    .stat-card.green::after { background: #22c55e; }
    .stat-card.purple::after { background: #8b5cf6; }
    .stat-card.orange::after { background: #f97316; }

    .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
    .stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .stat-trend { font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 9999px; }
    .stat-trend.up { background: #f0fdf4; color: #166534; }
    .stat-trend.info { background: #eff6ff; color: #1e40af; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: #0f172a; line-height: 1; margin-bottom: 0.25rem; }
    .stat-label { font-size: 0.75rem; color: #64748b; font-weight: 500; }
    .stat-sub { font-size: 0.7rem; color: #94a3b8; margin-top: 0.35rem; }

    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
    .card {
        background: rgba(255,255,255,0.9); backdrop-filter: blur(20px);
        border-radius: 1rem; border: 1px solid rgba(255,255,255,0.5);
        box-shadow: 0 8px 32px rgba(0,0,0,0.08); overflow: hidden; transition: all 0.3s;
    }
    .card:hover { box-shadow: 0 12px 48px rgba(0,0,0,0.12); }
    .card-header {
        padding: 1rem 1.25rem; border-bottom: 1px solid rgba(248,250,252,0.8);
        display: flex; align-items: center; gap: 0.65rem; background: rgba(250,251,252,0.5);
    }
    .card-header-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
    .card-header-title { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
    .card-body { padding: 1.25rem; background: rgba(255,255,255,0.3); }

    .table-compact { width: 100%; border-collapse: collapse; }
    .table-compact th { text-align: left; padding: 0.45rem 0.6rem; font-size: 0.62rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.3px; background: rgba(250,251,252,0.5); border-bottom: 1.5px solid rgba(226,232,240,0.5); }
    .table-compact td { padding: 0.5rem 0.6rem; border-bottom: 1px solid rgba(248,250,252,0.8); font-size: 0.78rem; color: #334155; }
    .table-compact tr:last-child td { border-bottom: none; }
    .table-compact tr:hover td { background: rgba(250,251,252,0.5); }
    .badge-xs { display: inline-flex; align-items: center; padding: 0.08rem 0.4rem; border-radius: 9999px; font-size: 0.62rem; font-weight: 600; white-space: nowrap; }

    .filter-bar {
        background: rgba(255,255,255,0.9); backdrop-filter: blur(20px);
        border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.5);
        padding: 0.85rem 1.25rem; margin-bottom: 1.5rem;
        display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
        box-shadow: 0 8px 32px rgba(0,0,0,0.06);
    }
    .filter-input { padding: 0.45rem 0.65rem; border: 1px solid rgba(226,232,240,0.8); border-radius: 0.4rem; font-size: 0.8rem; outline: none; font-family: 'Sarabun', sans-serif; background: rgba(250,251,252,0.8); transition: all 0.2s; }
    .filter-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    .filter-input:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-filter { padding: 0.45rem 0.85rem; border-radius: 0.4rem; font-size: 0.8rem; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; font-family: 'Sarabun', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
    .btn-filter.primary { background: #dbeafe; color: #1e40af; }
    .btn-filter.primary:hover { background: #bfdbfe; }
    .btn-filter.danger { background: #fee2e2; color: #dc2626; }
    .btn-filter.danger:hover { background: #fecaca; }
    .btn-filter.success { background: #d1fae5; color: #065f46; }
    .btn-filter.success:hover { background: #a7f3d0; }
    .btn-filter.warning { background: #fef3c7; color: #92400e; }
    .btn-filter.warning:hover { background: #fde68a; }

    .top-reporter-card {
        text-align: center; padding: 1.25rem 0.75rem; background: rgba(250,251,252,0.5);
        border-radius: 0.75rem; min-width: 110px; transition: all 0.3s; flex: 1;
        border: 1px solid rgba(226,232,240,0.3);
    }
    .top-reporter-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); background: rgba(255,255,255,0.9); }
    .top-reporter-card .medal { font-size: 1.5rem; margin-bottom: 0.35rem; }
    .top-reporter-card .avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(226,232,240,0.8); margin-bottom: 0.35rem; }
    .top-reporter-card .name { font-weight: 600; color: #1e293b; font-size: 0.8rem; }
    .top-reporter-card .count { font-size: 1.5rem; font-weight: 700; color: #3b82f6; }
    .top-reporter-card .label { font-size: 0.65rem; color: #94a3b8; }

    .date-warning { font-size: 0.7rem; color: #94a3b8; display: flex; align-items: center; gap: 0.3rem; }
    .date-warning i { color: #f59e0b; }

    .status-pending { background: #fef3c7; color: #92400e; }
    .status-progress { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-terminated { background: #f1f5f9; color: #475569; }

    .status-summary-box { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .status-summary-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 500; border: 1px solid rgba(226,232,240,0.5); }
    .status-summary-item .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .status-summary-item .count { font-weight: 700; margin-left: 0.2rem; }
    .dot-pending { background: #fbbf24; }
    .dot-progress { background: #60a5fa; }
    .dot-completed { background: #34d399; }
    .dot-terminated { background: #94a3b8; }

    .text-center { text-align: center; }
    .py-10 { padding-top: 2.5rem; padding-bottom: 2.5rem; }
    .text-gray-400 { color: #9ca3af; }
    .text-2xl { font-size: 1.5rem; }
    .mb-1 { margin-bottom: 0.25rem; }
    .mr-1 { margin-right: 0.25rem; }
    .text-xs { font-size: 0.75rem; }

    /* Notification Toast */
    #notification-toast {
        position: fixed; top: 1.5rem; right: 1.5rem;
        padding: 0.75rem 1.25rem; border-radius: 0.75rem; border: 1px solid;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12); z-index: 1000;
        display: none; align-items: center; gap: 0.75rem;
        font-family: 'Sarabun', sans-serif; max-width: 400px;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Refresh Indicator */
    #refresh-indicator {
        font-size: 0.7rem; color: rgba(255,255,255,0.5);
        display: flex; align-items: center; gap: 0.3rem;
        margin-top: 0.25rem; position: relative; z-index: 1;
    }
    #refresh-indicator .spinning { animation: spin 1s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }

    @media (max-width: 1024px) { 
        .stats-grid { grid-template-columns: repeat(2, 1fr); } 
        .content-grid { grid-template-columns: 1fr; } 
    }
    @media (max-width: 640px) { 
        .stats-grid { grid-template-columns: 1fr; } 
        .status-summary-box { flex-direction: column; align-items: flex-start; }
        .dash-header { padding: 1rem 1.25rem; }
        .dash-header h1 { font-size: 1.2rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .filter-input { width: 100%; }
        .stat-card { padding: 0.75rem; }
        .stat-value { font-size: 1.4rem; }
        .top-reporter-card { min-width: 80px; }
        .top-reporter-card .count { font-size: 1.2rem; }
        .header-actions { width: 100%; justify-content: flex-start; }
        .header-actions .btn-filter { font-size: 0.7rem; padding: 0.3rem 0.6rem; }
    }
    @media print { 
        .sidebar, .filter-bar, .floating-shapes, .header-actions, 
        #notification-toast, #refresh-indicator { display: none !important; }
        .dash-header { background: #0f172a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card, .card { background: white !important; border: 1px solid #ddd !important; box-shadow: none !important; }
        body { background: white !important; }
    }
</style>

<div class="flex h-screen" style="position:relative;z-index:1;">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <div class="floating-shapes"><div class="shape"></div><div class="shape"></div><div class="shape"></div></div>
        
        <div class="dashboard-container">

            <!-- ส่วนหัว -->
            <div class="dash-header">
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <h1>📊 ภาพรวมระบบความเสี่ยง</h1>
                    <div class="header-actions">
                        <button onclick="exportDashboard('pdf')" class="btn-filter primary" style="background:#fef2f2;color:#dc2626;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button onclick="exportDashboard('png')" class="btn-filter primary" style="background:#eff6ff;color:#2563eb;">
                            <i class="fas fa-image"></i> รูปภาพ
                        </button>
                        <button onclick="window.print()" class="btn-filter primary" style="background:#f0fdf4;color:#16a34a;">
                            <i class="fas fa-print"></i> พิมพ์
                        </button>
                        <button onclick="refreshDashboard()" class="btn-filter primary" style="background:#fffbeb;color:#92400e;" id="refreshBtn">
                            <i class="fas fa-sync-alt"></i> รีเฟรช
                        </button>
                    </div>
                </div>
                <p>ข้อมูล ณ วันที่ <?= $currentThaiDate ?> | ศูนย์อนามัยที่ 8 อุดรธานี</p>
                <div id="refresh-indicator">
                    <i class="fas fa-clock"></i> 
                    <span id="lastRefreshTime">อัปเดตล่าสุด: <?= date('H:i:s') ?></span>
                    <span style="margin-left:0.5rem;" id="autoRefreshStatus">● อัตโนมัติ</span>
                </div>
            </div>

            <!-- แถบกรองวันที่ -->
            <form method="GET" id="filterForm" class="filter-bar">
                <span style="font-weight:600;color:#64748b;font-size:0.8rem;"><i class="fas fa-filter mr-1"></i> กรองข้อมูล:</span>
                <input type="date" name="date_from" id="dateFrom" value="<?= htmlspecialchars($date_from) ?>" class="filter-input auto-submit" max="<?= date('Y-m-d') ?>">
                <span style="color:#94a3b8;font-size:0.8rem;">ถึง</span>
                <input type="date" name="date_to" id="dateTo" value="<?= htmlspecialchars($date_to) ?>" class="filter-input auto-submit" max="<?= date('Y-m-d') ?>">
                
                <span style="font-size:0.75rem;color:#64748b;font-weight:500;margin-left:0.25rem;">สถานะ:</span>
                <select name="status_filter" class="filter-input auto-submit" style="min-width:120px;">
                    <option value="">ทั้งหมด</option>
                    <option value="ยังไม่ดำเนินการ" <?= ($status_filter ?? '') == 'ยังไม่ดำเนินการ' ? 'selected' : '' ?>>ยังไม่ดำเนินการ</option>
                    <option value="กำลังดำเนินการ" <?= ($status_filter ?? '') == 'กำลังดำเนินการ' ? 'selected' : '' ?>>กำลังดำเนินการ</option>
                    <option value="ดำเนินการแล้ว" <?= ($status_filter ?? '') == 'ดำเนินการแล้ว' ? 'selected' : '' ?>>ดำเนินการแล้ว</option>
                    <option value="ยุติ" <?= ($status_filter ?? '') == 'ยุติ' ? 'selected' : '' ?>>ยุติ</option>
                </select>
                
                <span style="font-size:0.75rem;color:#64748b;font-weight:500;">ประเภท:</span>
                <select name="type_filter" class="filter-input auto-submit" style="min-width:150px;">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= ($type_filter ?? '') == $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <span class="date-warning"><i class="fas fa-info-circle"></i> ไม่สามารถเลือกวันที่ในอนาคตได้</span>
                
                <?php if ($date_from || $date_to || $status_filter || $type_filter): ?>
                    <a href="dashboard.php" class="btn-filter danger"><i class="fas fa-times"></i> รีเซ็ต</a>
                <?php endif; ?>
            </form>

            <!-- แสดงช่วงวันที่ที่กรอง -->
            <?php if ($date_from || $date_to || $status_filter || $type_filter): ?>
            <div style="background: rgba(219, 234, 254, 0.5); border: 1px solid #93c5fd; border-radius: 0.5rem; padding: 0.5rem 1rem; margin-bottom: 1rem; font-size: 0.8rem; color: #1e40af; display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
                <i class="fas fa-calendar-alt mr-1"></i>
                แสดงข้อมูลระหว่างวันที่ 
                <strong><?= $date_from ? dateToThai($date_from, 'short') : 'ไม่จำกัดเริ่มต้น' ?></strong>
                ถึง 
                <strong><?= $date_to ? dateToThai($date_to, 'short') : 'ปัจจุบัน' ?></strong>
                <?php if ($status_filter): ?>
                    <span class="badge-xs status-pending" style="font-size:0.7rem;padding:0.15rem 0.6rem;"><?= htmlspecialchars($status_filter) ?></span>
                <?php endif; ?>
                <?php if ($type_filter): ?>
                    <span class="badge-xs" style="background:#dbeafe;color:#1e40af;font-size:0.7rem;padding:0.15rem 0.6rem;"><?= htmlspecialchars(mb_substr($type_filter, 0, 20)) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- สรุปสถานะ -->
            <div class="status-summary-box">
                <div class="status-summary-item" style="background:rgba(254,243,199,0.5);border-color:#fcd34d;">
                    <span class="dot dot-pending"></span> ยังไม่ดำเนินการ <span class="count"><?= number_format($statusCounts['ยังไม่ดำเนินการ']) ?></span>
                </div>
                <div class="status-summary-item" style="background:rgba(219,234,254,0.5);border-color:#93c5fd;">
                    <span class="dot dot-progress"></span> กำลังดำเนินการ <span class="count"><?= number_format($statusCounts['กำลังดำเนินการ']) ?></span>
                </div>
                <div class="status-summary-item" style="background:rgba(209,250,229,0.5);border-color:#6ee7b7;">
                    <span class="dot dot-completed"></span> ดำเนินการแล้ว <span class="count"><?= number_format($statusCounts['ดำเนินการแล้ว']) ?></span>
                </div>
                <div class="status-summary-item" style="background:rgba(241,245,249,0.5);border-color:#cbd5e1;">
                    <span class="dot dot-terminated"></span> ยุติ <span class="count"><?= number_format($statusCounts['ยุติ']) ?></span>
                </div>
            </div>

            <!-- สถิติหลัก 4 กล่อง -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-icon bg-blue-50 text-blue-600"><i class="fas fa-exclamation-triangle"></i></div>
                        <span class="stat-trend up"><i class="fas fa-arrow-up text-xs"></i> +<?= number_format($todayRisks) ?> วันนี้</span>
                    </div>
                    <div class="stat-value" id="statTotalRisks"><?= number_format($totalRisks) ?></div>
                    <div class="stat-label">ความเสี่ยงทั้งหมด</div>
                    <div class="stat-sub">ดำเนินการแล้ว <?= number_format($completedRisks) ?> รายการ</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-icon bg-purple-50 text-purple-600"><i class="fas fa-users"></i></div>
                        <span class="stat-trend info"><i class="fas fa-user-shield text-xs"></i> <?= $totalAdmins ?> ผู้ดูแล</span>
                    </div>
                    <div class="stat-value" id="statTotalUsers"><?= number_format($totalUsers) ?></div>
                    <div class="stat-label">ผู้ใช้งานทั้งหมด</div>
                    <div class="stat-sub"><?= number_format($totalNormalUsers) ?> ผู้ใช้ทั่วไป</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-header"><div class="stat-icon bg-green-50 text-green-600"><i class="fas fa-calendar-day"></i></div></div>
                    <div class="stat-value" id="statTodayRisks"><?= number_format($todayRisks) ?></div>
                    <div class="stat-label">รายงานวันนี้</div>
                    <div class="stat-sub"><?= $currentThaiDateShort ?></div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-header"><div class="stat-icon bg-orange-50 text-orange-600"><i class="fas fa-check-double"></i></div></div>
                    <div class="stat-value" id="statCompletedRisks"><?= number_format($completedRisks) ?></div>
                    <div class="stat-label">ดำเนินการแล้ว</div>
                    <div class="stat-sub"><?= $totalRisks > 0 ? number_format(($completedRisks / $totalRisks) * 100, 1) . '%' : '0%' ?> ของทั้งหมด</div>
                </div>
            </div>

            <!-- กราฟแถวที่ 1: ระดับความรุนแรง + ประเภทความเสี่ยง -->
            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-orange-50 text-orange-600"><i class="fas fa-chart-pie"></i></div>
                        <h3 class="card-header-title">ระดับความรุนแรงของความเสี่ยง</h3>
                    </div>
                    <div class="card-body"><div style="height: 320px;position:relative;"><canvas id="severityDoughnut"></canvas></div></div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-cyan-50 text-cyan-600"><i class="fas fa-chart-pie"></i></div>
                        <h3 class="card-header-title">ประเภทความเสี่ยง</h3>
                    </div>
                    <div class="card-body"><div style="height: 320px;position:relative;"><canvas id="riskTypePolar"></canvas></div></div>
                </div>
            </div>

            <!-- กราฟแถวที่ 2: จำนวนเคสตามกลุ่มงาน + สถานะการดำเนินการ -->
            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-indigo-50 text-indigo-600"><i class="fas fa-chart-bar"></i></div>
                        <h3 class="card-header-title">จำนวนเคสตามกลุ่มงาน</h3>
                    </div>
                    <div class="card-body"><div style="height: 320px;position:relative;"><canvas id="groupChart"></canvas></div></div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-blue-50 text-blue-600"><i class="fas fa-tasks"></i></div>
                        <h3 class="card-header-title">สถานะการดำเนินการแยกตามกลุ่มงาน</h3>
                    </div>
                    <div class="card-body"><div style="height: 320px;position:relative;"><canvas id="statusChart"></canvas></div></div>
                </div>
            </div>

            <!-- แถวที่ 3: รายการล่าสุด + ผู้รายงานสูงสุด -->
            <div class="content-grid">
                <!-- รายการล่าสุด -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-emerald-50 text-emerald-600"><i class="fas fa-clock"></i></div>
                        <h3 class="card-header-title">📋 รายการล่าสุด</h3>
                        <a href="risks.php" style="margin-left:auto;font-size:0.7rem;color:#3b82f6;text-decoration:none;font-weight:500;">ดูทั้งหมด →</a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($recent)): ?>
                            <div class="text-center py-10 text-gray-400">
                                <i class="fas fa-inbox text-2xl mb-1"></i>
                                <p style="font-size:0.8rem;">ยังไม่มีข้อมูลในช่วงวันที่เลือก</p>
                            </div>
                        <?php else: ?>
                            <table class="table-compact">
                                <thead>
                                    <tr>
                                        <th>กลุ่มงาน</th>
                                        <th>ประเภท</th>
                                        <th>ระดับ</th>
                                        <th>สถานะ</th>
                                        <th style="text-align:right;">วันที่บันทึก</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $row): 
                                        $sevClass = getSeverityClass($row['severity']);
                                        $status = $row['status'] ?: 'ยังไม่ดำเนินการ';
                                        $statusColor = getStatusColorClass($status);
                                    ?>
                                        <tr>
                                            <td style="color:#94a3b8;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['unit'] ?? '-') ?>">
                                                <?= htmlspecialchars(mb_substr($row['unit'] ?? '-', 0, 15)) ?>
                                            </td>
                                            <td style="font-weight:500;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['risk_type'] ?? '-') ?>">
                                                <?= htmlspecialchars(mb_substr($row['risk_type'] ?? '-', 0, 20)) ?>
                                            </td>
                                            <td><span class="badge-xs <?= $sevClass ?>"><?= htmlspecialchars($row['severity'] ?? '-') ?></span></td>
                                            <td><span class="badge-xs <?= $statusColor ?>"><?= htmlspecialchars(mb_substr($status, 0, 10)) ?></span></td>
                                            <td style="text-align:right;color:#94a3b8;font-size:0.72rem;"><?= dateToThai($row['created_at'], 'short') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ผู้รายงานสูงสุด -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon bg-amber-50 text-amber-600"><i class="fas fa-trophy"></i></div>
                        <h3 class="card-header-title">🏆 ผู้รายงานสูงสุด</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topReporters)): ?>
                            <div class="text-center py-10 text-gray-400">
                                <i class="fas fa-inbox text-2xl mb-1"></i>
                                <p style="font-size:0.8rem;">ยังไม่มีข้อมูลในช่วงวันที่เลือก</p>
                            </div>
                        <?php else: ?>
                            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; justify-content:center;">
                                <?php foreach ($topReporters as $index => $reporter): 
                                    $medal = $index == 0 ? '🥇' : ($index == 1 ? '🥈' : ($index == 2 ? '🥉' : '⭐'));
                                ?>
                                    <div class="top-reporter-card">
                                        <div class="medal"><?= $medal ?></div>
                                        <img src="avatars/<?= htmlspecialchars($reporter['avatar'] ?: 'default.png') ?>" 
                                             class="avatar" 
                                             onerror="this.src='avatars/default.png'"
                                             alt="<?= htmlspecialchars($reporter['username']) ?>">
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

<!-- Notification Toast -->
<div id="notification-toast"></div>

<script>
    // ============================================================
    // 1. AUTO SUBMIT FILTER
    // ============================================================
    document.querySelectorAll('.auto-submit').forEach(el => { 
        el.addEventListener('change', function() { 
            document.getElementById('filterForm').submit(); 
        }); 
    });

    // ============================================================
    // 2. DATE VALIDATION
    // ============================================================
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

    // ============================================================
    // 3. CREATE CHARTS
    // ============================================================
    let severityChart, riskTypeChart, groupChart, statusChart;

    document.addEventListener('DOMContentLoaded', function() {
        
        // ----- 3.1 Doughnut Chart - ระดับความรุนแรง -----
        var ctxSeverity = document.getElementById('severityDoughnut');
        if (ctxSeverity) {
            severityChart = new Chart(ctxSeverity, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($severityFullLabels, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{ 
                        data: <?= json_encode($severityFullCounts) ?>, 
                        backgroundColor: <?= json_encode($doughnutColors) ?>, 
                        borderWidth: 3, 
                        borderColor: '#fff',
                        hoverBorderColor: '#e2e8f0',
                        hoverBorderWidth: 2
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
                                padding: 15, 
                                font: { size: 10, family: 'Sarabun' }, 
                                boxWidth: 12, 
                                boxHeight: 12,
                                generateLabels: function(chart) {
                                    var data = chart.data;
                                    return data.labels.map(function(label, i) {
                                        return {
                                            text: label.length > 35 ? label.substring(0, 35) + '...' : label,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].backgroundColor[i],
                                            lineWidth: 0,
                                            hidden: false,
                                            index: i,
                                            pointStyle: 'circle',
                                            pointStyleWidth: 10
                                        };
                                    });
                                }
                            } 
                        },
                        tooltip: { 
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleFont: { size: 12, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 11, family: 'Sarabun' },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: { 
                                label: function(ctx) { 
                                    var v = ctx.parsed || 0; 
                                    var t = ctx.dataset.data.reduce((a,b) => a+b, 0); 
                                    var p = t > 0 ? ((v/t)*100).toFixed(1) : 0; 
                                    return ' จำนวน: ' + v + ' รายการ (' + p + '%)'; 
                                } 
                            } 
                        }
                    }
                }
            });
        }

        // ----- 3.2 Polar Area Chart - ประเภทความเสี่ยง -----
        var ctxPolar = document.getElementById('riskTypePolar');
        if (ctxPolar) {
            riskTypeChart = new Chart(ctxPolar, {
                type: 'polarArea',
                data: {
                    labels: <?= json_encode($riskLabels, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{ 
                        data: <?= json_encode($riskCounts) ?>, 
                        backgroundColor: [
                            'rgba(99,102,241,0.7)',
                            'rgba(59,130,246,0.7)',
                            'rgba(34,197,94,0.7)',
                            'rgba(234,179,8,0.7)',
                            'rgba(249,115,22,0.7)',
                            'rgba(239,68,68,0.7)',
                            'rgba(139,92,246,0.7)',
                            'rgba(20,184,166,0.7)'
                        ], 
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
                                padding: 15, 
                                font: { size: 10, family: 'Sarabun' } 
                            } 
                        },
                        tooltip: { 
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleFont: { size: 12, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 11, family: 'Sarabun' },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: { 
                                label: function(ctx) { 
                                    var v = ctx.raw || 0; 
                                    var t = ctx.dataset.data.reduce((a,b) => a+b, 0); 
                                    var p = t > 0 ? ((v/t)*100).toFixed(1) : 0; 
                                    return ' จำนวน: ' + v + ' รายการ (' + p + '%)'; 
                                } 
                            } 
                        }
                    },
                    scales: { r: { ticks: { display: false } } }
                }
            });
        }

        // ----- 3.3 Horizontal Bar Chart - จำนวนเคสตามกลุ่มงาน -----
        var ctxGroup = document.getElementById('groupChart');
        if (ctxGroup) {
            groupChart = new Chart(ctxGroup, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($groupUnits, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        label: 'จำนวนเคส',
                        data: <?= json_encode($groupTotals) ?>,
                        backgroundColor: <?= json_encode($groupColors) ?>,
                        borderColor: <?= json_encode($groupBorderColors) ?>,
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
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleFont: { size: 12, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 11, family: 'Sarabun' },
                            padding: 12, 
                            cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) { 
                                    return ' จำนวน: ' + (ctx.raw || 0) + ' รายการ'; 
                                },
                                afterLabel: function(ctx) { 
                                    return 'ประเภทที่พบบ่อย: ' + <?= json_encode($groupTopTypesArray, JSON_UNESCAPED_UNICODE) ?>[ctx.dataIndex]; 
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true, 
                            ticks: { 
                                stepSize: 1, 
                                callback: function(v) { return Math.floor(v) === v ? v : ''; },
                                font: { family: 'Sarabun', size: 10 }
                            }, 
                            grid: { color: 'rgba(241,245,249,0.5)' }, 
                            title: { 
                                display: true, 
                                text: 'จำนวนรายการ', 
                                color: '#94a3b8', 
                                font: { size: 10, family: 'Sarabun' } 
                            } 
                        },
                        y: { 
                            grid: { display: false }, 
                            ticks: { 
                                font: { size: 10, family: 'Sarabun' }, 
                                color: '#475569' 
                            } 
                        }
                    }
                }
            });
        }

        // ----- 3.4 Stacked Bar Chart - สถานะการดำเนินการ -----
        var ctxStatus = document.getElementById('statusChart');
        if (ctxStatus) {
            statusChart = new Chart(ctxStatus, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($statusUnits, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [
                        { 
                            label: 'ยังไม่ดำเนินการ', 
                            data: <?= json_encode($statusPending) ?>, 
                            backgroundColor: '#fbbf24', 
                            borderRadius: 4, 
                            borderSkipped: false 
                        },
                        { 
                            label: 'กำลังดำเนินการ', 
                            data: <?= json_encode($statusInProgress) ?>, 
                            backgroundColor: '#60a5fa', 
                            borderRadius: 4, 
                            borderSkipped: false 
                        },
                        { 
                            label: 'ดำเนินการแล้ว', 
                            data: <?= json_encode($statusCompleted) ?>, 
                            backgroundColor: '#34d399', 
                            borderRadius: 4, 
                            borderSkipped: false 
                        },
                        { 
                            label: 'ยุติ', 
                            data: <?= json_encode($statusTerminated) ?>, 
                            backgroundColor: '#94a3b8', 
                            borderRadius: 4, 
                            borderSkipped: false 
                        }
                    ]
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
                                padding: 16, 
                                font: { size: 10, family: 'Sarabun' },
                                generateLabels: function(chart) { 
                                    return chart.data.datasets.map(function(ds, i) { 
                                        return { 
                                            text: ds.label, 
                                            fillStyle: ds.backgroundColor, 
                                            strokeStyle: ds.backgroundColor, 
                                            lineWidth: 0, 
                                            hidden: false, 
                                            index: i, 
                                            pointStyle: 'rectRounded', 
                                            pointStyleWidth: 12 
                                        }; 
                                    }); 
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleFont: { size: 12, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 11, family: 'Sarabun' },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) { 
                                    return ' ' + ctx.dataset.label + ': ' + (ctx.raw || 0) + ' รายการ'; 
                                },
                                footer: function(items) { 
                                    var t = 0; 
                                    items.forEach(function(i) { t += i.raw || 0; }); 
                                    return 'รวมทั้งหมด: ' + t + ' รายการ'; 
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            stacked: true, 
                            beginAtZero: true, 
                            ticks: { 
                                stepSize: 1, 
                                callback: function(v) { return Math.floor(v) === v ? v : ''; },
                                font: { family: 'Sarabun', size: 10 }
                            }, 
                            grid: { color: 'rgba(241,245,249,0.5)' }, 
                            title: { 
                                display: true, 
                                text: 'จำนวนรายการ', 
                                color: '#94a3b8', 
                                font: { size: 10, family: 'Sarabun' } 
                            } 
                        },
                        y: { 
                            stacked: true, 
                            grid: { display: false }, 
                            ticks: { 
                                font: { size: 10, family: 'Sarabun' } 
                            } 
                        }
                    }
                }
            });
        }

    });

    // ============================================================
    // 4. EXPORT DASHBOARD
    // ============================================================
    function exportDashboard(format) {
        Swal.fire({
            title: 'กำลังส่งออกข้อมูล...',
            text: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        const dateFrom = document.getElementById('dateFrom')?.value || '';
        const dateTo = document.getElementById('dateTo')?.value || '';
        const statusFilter = document.querySelector('[name="status_filter"]')?.value || '';
        const typeFilter = document.querySelector('[name="type_filter"]')?.value || '';
        
        fetch('action.php?action=export_dashboard&format=' + format + 
            '&date_from=' + encodeURIComponent(dateFrom) + 
            '&date_to=' + encodeURIComponent(dateTo) +
            '&status_filter=' + encodeURIComponent(statusFilter) +
            '&type_filter=' + encodeURIComponent(typeFilter) +
            '&csrf_token=<?= $csrf_token ?>')
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'dashboard_risk_management_' + new Date().toISOString().slice(0,10) + 
                    '.' + (format === 'pdf' ? 'pdf' : 'png');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'ส่งออกสำเร็จ',
                    text: 'ไฟล์ถูกดาวน์โหลดเรียบร้อยแล้ว',
                    confirmButtonColor: '#2563eb',
                    timer: 2000,
                    timerProgressBar: true
                });
            })
            .catch(() => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถส่งออกข้อมูลได้',
                    confirmButtonColor: '#2563eb'
                });
            });
    }

    // ============================================================
    // 5. REFRESH DASHBOARD
    // ============================================================
    let isRefreshing = false;
    let refreshInterval = null;

    function refreshDashboard() {
        if (isRefreshing) return;
        isRefreshing = true;
        
        const btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังโหลด...';
        }
        
        const dateFrom = document.getElementById('dateFrom')?.value || '';
        const dateTo = document.getElementById('dateTo')?.value || '';
        const statusFilter = document.querySelector('[name="status_filter"]')?.value || '';
        const typeFilter = document.querySelector('[name="type_filter"]')?.value || '';
        
        fetch('action.php?action=dashboard_data' + 
            '?date_from=' + encodeURIComponent(dateFrom) + 
            '&date_to=' + encodeURIComponent(dateTo) +
            '&status_filter=' + encodeURIComponent(statusFilter) +
            '&type_filter=' + encodeURIComponent(typeFilter) +
            '&csrf_token=<?= $csrf_token ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardStats(data);
                    updateCharts(data);
                    updateLastRefreshTime();
                    showNotification('🔄 อัปเดตข้อมูลสำเร็จ', 'success');
                }
                isRefreshing = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> รีเฟรช';
                }
            })
            .catch(() => {
                isRefreshing = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> รีเฟรช';
                }
                showNotification('⚠️ ไม่สามารถอัปเดตข้อมูลได้', 'warning');
            });
    }

    function updateDashboardStats(data) {
        const elTotal = document.getElementById('statTotalRisks');
        const elUsers = document.getElementById('statTotalUsers');
        const elToday = document.getElementById('statTodayRisks');
        const elCompleted = document.getElementById('statCompletedRisks');
        
        if (elTotal) elTotal.textContent = formatNumber(data.totalRisks);
        if (elUsers) elUsers.textContent = formatNumber(data.totalUsers);
        if (elToday) elToday.textContent = formatNumber(data.todayRisks);
        if (elCompleted) elCompleted.textContent = formatNumber(data.completedRisks);
    }

    function updateCharts(data) {
        if (severityChart && data.severityLabels && data.severityCounts) {
            severityChart.data.labels = data.severityLabels;
            severityChart.data.datasets[0].data = data.severityCounts;
            severityChart.update();
        }
        if (riskTypeChart && data.riskLabels && data.riskCounts) {
            riskTypeChart.data.labels = data.riskLabels;
            riskTypeChart.data.datasets[0].data = data.riskCounts;
            riskTypeChart.update();
        }
        if (groupChart && data.groupUnits && data.groupTotals) {
            groupChart.data.labels = data.groupUnits;
            groupChart.data.datasets[0].data = data.groupTotals;
            groupChart.update();
        }
        if (statusChart && data.statusData) {
            statusChart.data.datasets.forEach((ds, i) => {
                if (data.statusData[i]) {
                    ds.data = data.statusData[i];
                }
            });
            statusChart.update();
        }
    }

    function updateLastRefreshTime() {
        const el = document.getElementById('lastRefreshTime');
        if (el) {
            const now = new Date();
            el.textContent = 'อัปเดตล่าสุด: ' + 
                String(now.getHours()).padStart(2,'0') + ':' + 
                String(now.getMinutes()).padStart(2,'0') + ':' + 
                String(now.getSeconds()).padStart(2,'0');
        }
    }

    function formatNumber(num) {
        if (num === undefined || num === null) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // ============================================================
    // 6. AUTO REFRESH
    // ============================================================
    function startAutoRefresh(interval = 60000) {
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(function() {
            refreshDashboard();
        }, interval);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    // เริ่ม auto-refresh ทุก 60 วินาที
    startAutoRefresh(60000);

    // หยุด auto-refresh เมื่อออกจากหน้า
    window.addEventListener('beforeunload', function() {
        stopAutoRefresh();
    });

    // ============================================================
    // 7. NOTIFICATION SYSTEM
    // ============================================================
    function showNotification(message, type = 'info') {
        const colors = {
            info: { bg: '#eff6ff', border: '#93c5fd', color: '#1e40af', icon: 'fa-info-circle' },
            success: { bg: '#f0fdf4', border: '#86efac', color: '#16a34a', icon: 'fa-check-circle' },
            warning: { bg: '#fffbeb', border: '#fcd34d', color: '#92400e', icon: 'fa-exclamation-triangle' },
            error: { bg: '#fef2f2', border: '#fca5a5', color: '#dc2626', icon: 'fa-times-circle' }
        };
        const style = colors[type] || colors.info;
        
        let el = document.getElementById('notification-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'notification-toast';
            document.body.appendChild(el);
        }
        el.style.cssText = `
            position: fixed; top: 1.5rem; right: 1.5rem;
            padding: 0.75rem 1.25rem; border-radius: 0.75rem; border: 1px solid ${style.border};
            background: ${style.bg}; color: ${style.color};
            box-shadow: 0 8px 32px rgba(0,0,0,0.12); z-index: 1000;
            display: flex; align-items: center; gap: 0.75rem;
            font-family: 'Sarabun', sans-serif; max-width: 400px;
            animation: slideDown 0.3s ease;
        `;
        el.innerHTML = `<i class="fas ${style.icon}"></i> ${message}`;
        el.style.display = 'flex';
        
        setTimeout(() => {
            el.style.display = 'none';
        }, 4000);
    }

    // ============================================================
    // 8. CHECK NEW RISKS (Real-time Notification)
    // ============================================================
    function checkNewRisks() {
        const lastCheck = localStorage.getItem('lastDashboardCheck') || '';
        const dateFrom = document.getElementById('dateFrom')?.value || '';
        const dateTo = document.getElementById('dateTo')?.value || '';
        const statusFilter = document.querySelector('[name="status_filter"]')?.value || '';
        const typeFilter = document.querySelector('[name="type_filter"]')?.value || '';
        
        fetch('action.php?action=check_new_risks' +
            '?last_check=' + encodeURIComponent(lastCheck) + 
            '&date_from=' + encodeURIComponent(dateFrom) + 
            '&date_to=' + encodeURIComponent(dateTo) +
            '&status_filter=' + encodeURIComponent(statusFilter) +
            '&type_filter=' + encodeURIComponent(typeFilter) +
            '&csrf_token=<?= $csrf_token ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.new_count > 0) {
                    showNotification('🔔 มีรายงานความเสี่ยงใหม่ ' + data.new_count + ' รายการ', 'info');
                    localStorage.setItem('lastDashboardCheck', data.current_time);
                }
            })
            .catch(() => {});
    }

    // ตรวจสอบข้อมูลใหม่ทุก 2 นาที
    setInterval(checkNewRisks, 120000);
    // ตรวจสอบครั้งแรกหลังจากโหลดหน้า 5 วินาที
    setTimeout(checkNewRisks, 5000);

    // ============================================================
    // 9. KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+R = Refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            if (!e.target.closest('input, textarea, select')) {
                e.preventDefault();
                refreshDashboard();
            }
        }
        // Escape = Clear filters
        if (e.key === 'Escape') {
            const resetBtn = document.querySelector('.btn-filter.danger');
            if (resetBtn) {
                const href = resetBtn.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            }
        }
    });

    console.log('✅ Dashboard loaded successfully!');
    console.log('📊 Data period:', '<?= $date_from ? $date_from : "all" ?> - <?= $date_to ? $date_to : "now" ?>');
    console.log('📈 Total risks:', <?= $totalRisks ?>);
    console.log('🔄 Auto-refresh enabled');
</script>

<?php include 'includes/footer.php'; ?>