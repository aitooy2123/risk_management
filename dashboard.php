<?php
/**
 * Dashboard - Risk Management System
 * Full Page Layout with Organized Structure
 * Version: 2.1 - Fixed Issues
 */

// =============================================
// 1. INITIALIZATION & SECURITY
// =============================================
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// =============================================
// 2. CONFIGURATION & CONSTANTS
// =============================================
define('DATE_REGEX', '/^\d{4}-\d{2}-\d{2}$/');

$UNIT_ORDER = [
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

$RISK_TYPES = [
    'ความเสี่ยงทางด้านกลยุทธ์',
    'ความเสี่ยงทางด้านการเงิน',
    'ความเสี่ยงทางด้านการปฏิบัติงาน',
    'ความเสี่ยงทางด้านกฎหมาย',
    'ความเสี่ยงด้านสิ่งแวดล้อม',
    'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข'
];

$STATUS_LIST = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];

$SEVERITY_MAP = [
    'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
    'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
    'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
    'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
    'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
    'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
];

// 🔧 แก้ไขปัญหา #1: กำหนดสีตามระดับความเสี่ยงโดยตรง
$SEVERITY_COLORS = [
    'A' => '#22c55e',  // สีเขียว
    'B' => '#3b82f6',  // สีน้ำเงิน
    'C' => '#84cc16',  // สีเขียวอ่อน
    'D' => '#eab308',  // สีเหลือง
    'F' => '#f97316',  // สีส้ม
    'E' => '#ef4444'   // สีแดง
];

// 🔧 แก้ไขปัญหา #2: กำหนดสีให้ตรงกับประเภทความเสี่ยง (6 ประเภทเท่านั้น)
$RISK_TYPE_COLORS = [
    'ความเสี่ยงทางด้านกลยุทธ์' => 'rgba(99,102,241,0.75)',
    'ความเสี่ยงทางด้านการเงิน' => 'rgba(59,130,246,0.75)',
    'ความเสี่ยงทางด้านการปฏิบัติงาน' => 'rgba(34,197,94,0.75)',
    'ความเสี่ยงทางด้านกฎหมาย' => 'rgba(234,179,8,0.75)',
    'ความเสี่ยงด้านสิ่งแวดล้อม' => 'rgba(249,115,22,0.75)',
    'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข' => 'rgba(239,68,68,0.75)'
];

// สีสำรองสำหรับ Risk Type Chart (ใช้เมื่อมีประเภทที่ไม่อยู่ใน $RISK_TYPE_COLORS)
$FALLBACK_COLORS = [
    'rgba(139,92,246,0.75)',
    'rgba(20,184,166,0.75)',
    'rgba(236,72,153,0.75)',
    'rgba(168,85,247,0.75)',
    'rgba(52,211,153,0.75)',
    'rgba(251,146,60,0.75)'
];

$CHART_COLORS = [
    'doughnut' => ['#22c55e', '#3b82f6', '#84cc16', '#eab308', '#f97316', '#ef4444'], // 🔧 แก้ไขให้ตรงกับระดับ A,B,C,D,F,E
    'group' => ['#3b82f6', '#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'],
    'groupBorder' => ['#2563eb', '#4f46e5', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626', '#db2777', '#0d9488']
];

// =============================================
// 3. INPUT VALIDATION & SANITIZATION
// =============================================
$filters = [
    'date_from'     => isset($_GET['date_from']) ? trim($_GET['date_from']) : '',
    'date_to'       => isset($_GET['date_to']) ? trim($_GET['date_to']) : '',
    'status_filter' => isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '',
    'type_filter'   => isset($_GET['type_filter']) ? trim($_GET['type_filter']) : ''
];

// Validate date format
foreach (['date_from', 'date_to'] as $field) {
    if (!empty($filters[$field]) && !preg_match(DATE_REGEX, $filters[$field])) {
        $filters[$field] = '';
    }
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

// =============================================
// 4. DATABASE HELPER FUNCTIONS
// =============================================
function dbFetchAll($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return [];
    }
}

function dbFetchColumn($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return 0;
    }
}

// =============================================
// 5. QUERY BUILDER
// =============================================
function buildWhereClause($filters) {
    $conditions = [];
    $params = [];
    
    // Date range filter
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $conditions[] = "r.created_at BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    } elseif (!empty($filters['date_from'])) {
        $conditions[] = "r.created_at >= :date_from";
        $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
    } elseif (!empty($filters['date_to'])) {
        $conditions[] = "r.created_at <= :date_to";
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Status filter
    if (!empty($filters['status_filter'])) {
        $conditions[] = "r.status = :status";
        $params[':status'] = $filters['status_filter'];
    }
    
    // Type filter
    if (!empty($filters['type_filter'])) {
        $conditions[] = "r.risk_type = :risk_type";
        $params[':risk_type'] = $filters['type_filter'];
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$whereClause, $params];
}

// Build main WHERE clause
list($where_clause, $where_params) = buildWhereClause($filters);

// =============================================
// 6. DATA FETCHING - Main Statistics
// =============================================
$data = [];

// 6.1 Total counts
$data['totalRisks'] = dbFetchColumn($pdo, "SELECT COUNT(*) FROM risks r $where_clause", $where_params);
$data['totalUsers'] = dbFetchColumn($pdo, "SELECT COUNT(*) FROM users");
$data['totalAdmins'] = dbFetchColumn($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin'");
$data['totalNormalUsers'] = $data['totalUsers'] - $data['totalAdmins'];
$data['todayRisks'] = dbFetchColumn($pdo, "SELECT COUNT(*) FROM risks WHERE DATE(created_at) = CURDATE()");

// 6.2 Completed risks with date filter
$completedSql = "SELECT COUNT(*) FROM risks r WHERE r.status = 'ดำเนินการแล้ว'";
$completedParams = [];
if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
    $completedSql .= " AND r.created_at BETWEEN :c_from AND :c_to";
    $completedParams = [
        ':c_from' => $filters['date_from'] . ' 00:00:00',
        ':c_to' => $filters['date_to'] . ' 23:59:59'
    ];
} elseif (!empty($filters['date_from'])) {
    $completedSql .= " AND r.created_at >= :c_from";
    $completedParams = [':c_from' => $filters['date_from'] . ' 00:00:00'];
} elseif (!empty($filters['date_to'])) {
    $completedSql .= " AND r.created_at <= :c_to";
    $completedParams = [':c_to' => $filters['date_to'] . ' 23:59:59'];
}
$data['completedRisks'] = dbFetchColumn($pdo, $completedSql, $completedParams);

// 6.3 Completion percentage
$data['completionPercentage'] = $data['totalRisks'] > 0 
    ? round(($data['completedRisks'] / $data['totalRisks']) * 100, 1) 
    : 0;

// =============================================
// 7. DATA FETCHING - Chart Data
// =============================================

// 7.1 Severity distribution
$data['severityData'] = dbFetchAll($pdo, 
    "SELECT severity, COUNT(*) as count 
     FROM risks r $where_clause 
     GROUP BY severity 
     ORDER BY FIELD(severity, 'A','B','C','D','F','E')",
    $where_params
);

// 7.2 Risk type distribution
$data['riskTypes'] = dbFetchAll($pdo, 
    "SELECT risk_type, COUNT(*) as count 
     FROM risks r $where_clause 
     GROUP BY risk_type 
     ORDER BY count DESC",
    $where_params
);

// 7.3 Recent entries
$data['recentRisks'] = dbFetchAll($pdo, 
    "SELECT unit, risk_type, severity, status, created_at 
     FROM risks r $where_clause 
     ORDER BY created_at DESC 
     LIMIT 7",
    $where_params
);

// 7.4 Group summary - 🔧 แก้ไขปัญหา #3: ใช้ LEFT JOIN กับ UNIT_ORDER เพื่อแสดงทุกกลุ่ม
// สร้างตารางชั่วคราวของกลุ่มทั้งหมด
$unitList = "'" . implode("','", $UNIT_ORDER) . "'";

// ดึงข้อมูลกลุ่มที่มีความเสี่ยง (ตาม filter)
$groupDataRaw = dbFetchAll($pdo, 
    "SELECT unit, COUNT(*) as total 
     FROM risks r $where_clause 
     GROUP BY unit",
    $where_params
);

// แปลงเป็น associative array
$groupDataMap = [];
foreach ($groupDataRaw as $item) {
    $groupDataMap[$item['unit']] = (int)$item['total'];
}

// 🔧 สร้างข้อมูลกลุ่มให้ครบทุกกลุ่ม (ถึงไม่มีข้อมูลก็แสดง)
$data['groupSummary'] = [];
foreach ($UNIT_ORDER as $unit) {
    $data['groupSummary'][] = [
        'unit' => $unit,
        'total' => isset($groupDataMap[$unit]) ? $groupDataMap[$unit] : 0
    ];
}

// 7.5 Status summary by group - 🔧 แก้ไขปัญหา #4: แสดงทุกกลุ่ม
$statusDataRaw = dbFetchAll($pdo, 
    "SELECT unit,
        SUM(CASE WHEN status = 'ยังไม่ดำเนินการ' OR status IS NULL OR status = '' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'กำลังดำเนินการ' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'ดำเนินการแล้ว' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'ยุติ' THEN 1 ELSE 0 END) as terminated_count,
        COUNT(*) as total
     FROM risks r $where_clause 
     GROUP BY unit",
    $where_params
);

// แปลงเป็น associative array
$statusDataMap = [];
foreach ($statusDataRaw as $item) {
    $statusDataMap[$item['unit']] = $item;
}

// 🔧 สร้างข้อมูลสถานะให้ครบทุกกลุ่ม
$data['statusSummary'] = [];
foreach ($UNIT_ORDER as $unit) {
    if (isset($statusDataMap[$unit])) {
        $data['statusSummary'][] = $statusDataMap[$unit];
    } else {
        $data['statusSummary'][] = [
            'unit' => $unit,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'terminated_count' => 0,
            'total' => 0
        ];
    }
}

// 7.6 Top reporters
$data['topReporters'] = dbFetchAll($pdo, 
    "SELECT u.username, u.avatar, COUNT(*) as report_count 
     FROM risks r 
     LEFT JOIN users u ON r.user_id = u.id 
     $where_clause 
     GROUP BY r.user_id, u.username, u.avatar 
     ORDER BY report_count DESC 
     LIMIT 5",
    $where_params
);

// 7.7 Status counts
$data['statusCounts'] = [];
foreach ($STATUS_LIST as $status) {
    if ($status === 'ยังไม่ดำเนินการ') {
        $statusCondition = "(r.status = :s OR r.status IS NULL OR r.status = '')";
    } else {
        $statusCondition = "r.status = :s";
    }
    
    $countSql = "SELECT COUNT(*) FROM risks r WHERE $statusCondition";
    $countParams = [':s' => $status];
    
    // Add date filter to status count
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $countSql .= " AND r.created_at BETWEEN :sd_from AND :sd_to";
        $countParams[':sd_from'] = $filters['date_from'] . ' 00:00:00';
        $countParams[':sd_to'] = $filters['date_to'] . ' 23:59:59';
    } elseif (!empty($filters['date_from'])) {
        $countSql .= " AND r.created_at >= :sd_from";
        $countParams[':sd_from'] = $filters['date_from'] . ' 00:00:00';
    } elseif (!empty($filters['date_to'])) {
        $countSql .= " AND r.created_at <= :sd_to";
        $countParams[':sd_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    $data['statusCounts'][$status] = dbFetchColumn($pdo, $countSql, $countParams);
}

// =============================================
// 8. DATA PROCESSING - Top Types per Group
// =============================================
$data['groupTopTypes'] = [];
foreach ($data['groupSummary'] as $group) {
    $unit = $group['unit'];
    
    // ถ้าไม่มีข้อมูลความเสี่ยงสำหรับกลุ่มนี้
    if ($group['total'] == 0) {
        $data['groupTopTypes'][$unit] = '-';
        continue;
    }
    
    // Build sub-query conditions
    $subConditions = ["unit = :u"];
    $subParams = [':u' => $unit];
    
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $subConditions[] = "created_at BETWEEN :ud_from AND :ud_to";
        $subParams[':ud_from'] = $filters['date_from'] . ' 00:00:00';
        $subParams[':ud_to'] = $filters['date_to'] . ' 23:59:59';
    } elseif (!empty($filters['date_from'])) {
        $subConditions[] = "created_at >= :ud_from";
        $subParams[':ud_from'] = $filters['date_from'] . ' 00:00:00';
    } elseif (!empty($filters['date_to'])) {
        $subConditions[] = "created_at <= :ud_to";
        $subParams[':ud_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    if (!empty($filters['status_filter'])) {
        $subConditions[] = "status = :us";
        $subParams[':us'] = $filters['status_filter'];
    }
    
    if (!empty($filters['type_filter'])) {
        $subConditions[] = "risk_type = :ut";
        $subParams[':ut'] = $filters['type_filter'];
    }
    
    $subWhere = 'WHERE ' . implode(' AND ', $subConditions);
    $result = dbFetchAll($pdo, 
        "SELECT risk_type FROM risks $subWhere 
         GROUP BY risk_type 
         ORDER BY COUNT(*) DESC 
         LIMIT 1", 
        $subParams
    );
    
    $data['groupTopTypes'][$unit] = !empty($result) ? $result[0]['risk_type'] : '-';
}

// =============================================
// 9. PREPARE CHART DATA ARRAYS
// =============================================
$chartData = [
    'severityLabels' => [],
    'severityCounts' => [],
    'severityFullLabels' => [],
    'severityFullCounts' => [],
    'severityColors' => [],  // 🔧 เพิ่มสำหรับปัญหา #1
    'riskTypeLabels' => [],
    'riskTypeCounts' => [],
    'riskTypeColors' => [],  // 🔧 เพิ่มสำหรับปัญหา #2
    'groupUnits' => [],
    'groupTotals' => [],
    'groupTopTypesArray' => [],
    'statusUnits' => [],
    'statusPending' => [],
    'statusInProgress' => [],
    'statusCompleted' => [],
    'statusTerminated' => []
];

// 🔧 แก้ไขปัญหา #1: Process severity data with correct colors
foreach ($data['severityData'] as $item) {
    $chartData['severityLabels'][] = $item['severity'];
    $chartData['severityCounts'][] = (int)$item['count'];
    
    $label = isset($SEVERITY_MAP[$item['severity']]) 
        ? $SEVERITY_MAP[$item['severity']] 
        : $item['severity'];
    $chartData['severityFullLabels'][] = $label;
    $chartData['severityFullCounts'][] = (int)$item['count'];
    
    // 🔧 ใช้สีตามระดับความเสี่ยงโดยตรง
    $chartData['severityColors'][] = isset($SEVERITY_COLORS[$item['severity']]) 
        ? $SEVERITY_COLORS[$item['severity']] 
        : '#94a3b8';
}

// 🔧 แก้ไขปัญหา #2: Process risk type data with correct colors
$fallbackIndex = 0;
foreach ($data['riskTypes'] as $item) {
    $chartData['riskTypeLabels'][] = $item['risk_type'];
    $chartData['riskTypeCounts'][] = (int)$item['count'];
    
    // 🔧 ใช้สีตามประเภทความเสี่ยงที่กำหนดไว้
    if (isset($RISK_TYPE_COLORS[$item['risk_type']])) {
        $chartData['riskTypeColors'][] = $RISK_TYPE_COLORS[$item['risk_type']];
    } else {
        // ใช้สีสำรองถ้าไม่ตรงกับที่กำหนด
        $chartData['riskTypeColors'][] = $FALLBACK_COLORS[$fallbackIndex % count($FALLBACK_COLORS)];
        $fallbackIndex++;
    }
}

// Process group summary data (🔧 แก้ไขปัญหา #3: ข้อมูลครบทุกกลุ่มแล้ว)
foreach ($data['groupSummary'] as $item) {
    $chartData['groupUnits'][] = $item['unit'];
    $chartData['groupTotals'][] = (int)$item['total'];
    $chartData['groupTopTypesArray'][] = isset($data['groupTopTypes'][$item['unit']]) 
        ? $data['groupTopTypes'][$item['unit']] 
        : '-';
}

// Process status summary data (🔧 แก้ไขปัญหา #4: ข้อมูลครบทุกกลุ่มแล้ว)
foreach ($data['statusSummary'] as $item) {
    $chartData['statusUnits'][] = $item['unit'];
    $chartData['statusPending'][] = (int)$item['pending'];
    $chartData['statusInProgress'][] = (int)$item['in_progress'];
    $chartData['statusCompleted'][] = (int)$item['completed'];
    $chartData['statusTerminated'][] = (int)$item['terminated_count'];
}

// Calculate total reports for top reporters
$data['totalTopReports'] = array_sum(array_column($data['topReporters'], 'report_count'));

// =============================================
// 10. HELPER FUNCTIONS
// =============================================

/**
 * Get CSS class for risk status badge
 */
function getStatusBadgeClass($status) {
    $classes = [
        'ดำเนินการแล้ว' => 'badge-success',
        'กำลังดำเนินการ' => 'badge-info',
        'ยุติ' => 'badge-secondary',
    ];
    return isset($classes[$status]) ? $classes[$status] : 'badge-warning';
}

/**
 * Get CSS class for severity badge
 */
function getSeverityBadgeClass($severity) {
    $classes = [
        'A' => 'badge-success',   // 🔧 แก้ไขให้ตรงกับสี
        'B' => 'badge-info',
        'C' => 'badge-lime',
        'D' => 'badge-warning',
        'F' => 'badge-orange',
        'E' => 'badge-danger',
    ];
    return isset($classes[$severity]) ? $classes[$severity] : 'badge-secondary';
}

/**
 * Get CSS class for status color
 */
function getStatusColorClass($status) {
    $classes = [
        'ยังไม่ดำเนินการ' => 'status-pending',
        'กำลังดำเนินการ' => 'status-progress',
        'ดำเนินการแล้ว' => 'status-completed',
        'ยุติ' => 'status-terminated',
    ];
    return isset($classes[$status]) ? $classes[$status] : 'status-pending';
}

/**
 * Convert date to Thai Buddhist calendar format
 */
function dateToThai($date, $format = 'full') {
    if (empty($date)) {
        return '-';
    }
    
    $timestamp = strtotime($date);
    $day = (int)date('j', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp) + 543;
    
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

/**
 * Check if any filter is active
 */
function hasActiveFilters($filters) {
    return !empty($filters['date_from']) || 
           !empty($filters['date_to']) || 
           !empty($filters['status_filter']) || 
           !empty($filters['type_filter']);
}

// Prepare display variables
$currentThaiDate = dateToThai(date('Y-m-d'), 'full');
$currentThaiDateShort = dateToThai(date('Y-m-d'), 'short');
$activeFilters = hasActiveFilters($filters);

// =============================================
// 11. INCLUDE HEADER & START HTML
// =============================================
include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบจัดการความเสี่ยง ศูนย์อนามัยที่ 8</title>
    
    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* ============================================= */
        /* 12. CSS - DESIGN SYSTEM */
        /* ============================================= */
        
        /* 12.1 Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap');
        
        /* 12.2 CSS Variables */
        :root {
            /* Colors */
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #dbeafe;
            --success: #22c55e;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #6366f1;
            --info-light: #e0e7ff;
            
            /* Neutrals */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Effects */
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            
            /* Glass Effect */
            --glass-bg: rgba(255,255,255,0.85);
            --glass-border: rgba(255,255,255,0.3);
            --glass-blur: blur(20px);
            
            /* Layout */
            --sidebar-width: 260px;
            --header-height: auto;
        }
        
        /* 12.3 Reset & Base */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--gray-800);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }
        
        /* 12.4 Animated Background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(59,130,246,0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139,92,246,0.08) 0%, transparent 50%),
                radial-gradient(circle at 50% 80%, rgba(236,72,153,0.08) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        /* 12.5 Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }
        
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            transition: margin-left 0.3s ease;
        }
        
        .content-area {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }
        
        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* 12.6 Floating Animation */
        .bg-animation {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        
        .bg-animation .orb {
            position: absolute;
            border-radius: 50%;
            opacity: 0.4;
            filter: blur(60px);
        }
        
        .bg-animation .orb:nth-child(1) {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(59,130,246,0.15), transparent);
            top: -150px; right: -100px;
            animation: float1 20s ease-in-out infinite;
        }
        
        .bg-animation .orb:nth-child(2) {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(139,92,246,0.15), transparent);
            bottom: -100px; left: -100px;
            animation: float2 25s ease-in-out infinite reverse;
        }
        
        .bg-animation .orb:nth-child(3) {
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(236,72,153,0.15), transparent);
            top: 40%; left: 50%;
            animation: float3 30s ease-in-out infinite;
        }
        
        @keyframes float1 {
            0%,100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(50px,-30px) scale(1.1); }
            66% { transform: translate(-30px,50px) scale(0.9); }
        }
        
        @keyframes float2 {
            0%,100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(-40px,40px) scale(1.1); }
            66% { transform: translate(40px,-20px) scale(0.9); }
        }
        
        @keyframes float3 {
            0%,100% { transform: translate(-50%,-50%) scale(1); }
            33% { transform: translate(-40%,-60%) scale(1.15); }
            66% { transform: translate(-60%,-40%) scale(0.85); }
        }
        
        /* 12.7 Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--gray-900) 0%, #1e3a8a 40%, var(--primary) 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.02);
            border-radius: 50%;
        }
        
        .dashboard-header::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.02);
            border-radius: 50%;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-title-section h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .header-title-section p {
            color: rgba(255,255,255,0.75);
            font-size: 0.9rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .refresh-indicator {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            position: relative;
            z-index: 1;
        }
        
        /* 12.8 Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-family: 'Sarabun', sans-serif;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-xs {
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            border-radius: var(--radius-sm);
        }
        
        .btn-red { background: var(--danger-light); color: var(--danger); }
        .btn-blue { background: var(--primary-light); color: var(--primary-dark); }
        .btn-green { background: var(--success-light); color: #065f46; }
        .btn-yellow { background: var(--warning-light); color: #92400e; }
        .btn-gray { background: var(--gray-100); color: var(--gray-600); }
        
        .btn-red:hover { background: #fecaca; }
        .btn-blue:hover { background: #bfdbfe; }
        .btn-green:hover { background: #a7f3d0; }
        .btn-yellow:hover { background: #fde68a; }
        .btn-gray:hover { background: var(--gray-200); }
        
        /* 12.9 Filter Bar */
        .filter-section {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-md);
            border: 1px solid var(--glass-border);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .filter-form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            outline: none;
            background: white;
            transition: all 0.2s;
            font-family: 'Sarabun', sans-serif;
            min-width: 120px;
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .filter-separator {
            color: var(--gray-400);
            font-size: 0.85rem;
        }
        
        /* 12.10 Active Filters Info */
        .active-filters-info {
            background: var(--primary-light);
            border: 1px solid #93c5fd;
            border-radius: var(--radius);
            padding: 0.6rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            color: var(--primary-dark);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        
        /* 12.11 Status Summary Bar */
        .status-summary-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.9rem;
            border-radius: var(--radius);
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .status-count {
            font-weight: 700;
            margin-left: 0.15rem;
        }
        
        /* Status Colors */
        .status-pending { background: rgba(254,243,199,0.6); border-color: #fcd34d; }
        .status-progress { background: rgba(219,234,254,0.6); border-color: #93c5fd; }
        .status-completed { background: rgba(209,250,229,0.6); border-color: #6ee7b7; }
        .status-terminated { background: rgba(241,245,249,0.6); border-color: #cbd5e1; }
        .dot-pending { background: #fbbf24; }
        .dot-progress { background: #60a5fa; }
        .dot-completed { background: #34d399; }
        .dot-terminated { background: #94a3b8; }
        
        /* 12.12 Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.06;
        }
        
        .stat-card.blue::after { background: #3b82f6; }
        .stat-card.purple::after { background: #8b5cf6; }
        .stat-card.green::after { background: #22c55e; }
        .stat-card.orange::after { background: #f97316; }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .stat-trend {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            white-space: nowrap;
        }
        
        .stat-trend.up { background: #f0fdf4; color: #166534; }
        .stat-trend.info { background: #eff6ff; color: #1e40af; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.1;
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        .stat-sub {
            font-size: 0.75rem;
            color: var(--gray-400);
            margin-top: 0.4rem;
        }
        
        /* 12.13 Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .content-grid.single-col {
            grid-template-columns: 1fr;
        }
        
        /* 12.14 Cards */
        .card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(226,232,240,0.5);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(250,251,252,0.5);
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .card-title {
            font-weight: 700;
            color: var(--gray-800);
            font-size: 1rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-body.no-padding {
            padding: 0;
        }
        
        /* 12.15 Chart Container */
        .chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }
        
        .chart-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        /* 12.16 Table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 0.6rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(250,251,252,0.5);
            border-bottom: 2px solid rgba(226,232,240,0.5);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            text-align: center;
        }
        
        .data-table tbody td {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid rgba(226,232,240,0.3);
            font-size: 0.85rem;
            color: var(--gray-700);
        }
        
        .data-table tbody td:first-child {
            text-align: center;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:hover td {
            background: rgba(250,251,252,0.5);
        }
        
        /* 12.17 Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-lime { background: #ecfccb; color: #3f6212; }
        
        /* 12.18 Text Utilities */
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: var(--gray-400); }
        .text-sm { font-size: 0.85rem; }
        .text-xs { font-size: 0.75rem; }
        
        /* 12.19 Spacing Utilities */
        .py-10 { padding-top: 2.5rem; padding-bottom: 2.5rem; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .block { display: block; }
        
        /* 12.20 Top Reporters */
        .reporters-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        
        .reporter-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem;
            background: white;
            border-radius: 14px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .reporter-item:hover {
            transform: translateX(6px);
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
            border-color: #93c5fd;
        }
        
        .reporter-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            transition: all 0.3s;
        }
        
        .reporter-item.rank-1::before { background: linear-gradient(to bottom, #fbbf24, #f59e0b); }
        .reporter-item.rank-2::before { background: linear-gradient(to bottom, #94a3b8, #64748b); }
        .reporter-item.rank-3::before { background: linear-gradient(to bottom, #fb923c, #f97316); }
        .reporter-item.rank-4::before { background: linear-gradient(to bottom, #60a5fa, #3b82f6); }
        .reporter-item.rank-5::before { background: linear-gradient(to bottom, #34d399, #10b981); }
        
        .reporter-item:hover::before {
            width: 6px;
        }
        
        .rank-display {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .rank-medal {
            font-size: 1.6rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .rank-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gray-200), var(--gray-300));
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            border: 2px solid var(--gray-300);
        }
        
        .avatar-container {
            position: relative;
            flex-shrink: 0;
        }
        
        .avatar-ring {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            padding: 3px;
            border: 3px solid;
            background: white;
            transition: all 0.3s;
        }
        
        .reporter-item:hover .avatar-ring {
            transform: scale(1.1);
        }
        
        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
        
        .crown-icon {
            position: absolute;
            top: -14px;
            right: -8px;
            font-size: 1.4rem;
            animation: crownBounce 2s ease-in-out infinite;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        @keyframes crownBounce {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-5px) rotate(5deg); }
        }
        
        .reporter-details {
            flex: 1;
            min-width: 0;
        }
        
        .reporter-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .top-badge {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 0.1rem 0.45rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .reporter-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-bar-mini {
            flex: 1;
            height: 6px;
            background: var(--gray-100);
            border-radius: 9999px;
            overflow: hidden;
            max-width: 100px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.6s ease;
        }
        
        .report-count {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
            white-space: nowrap;
        }
        
        .percentage-badge {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #1e40af;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid #93c5fd;
            flex-shrink: 0;
            transition: all 0.3s;
        }
        
        .reporter-item:hover .percentage-badge {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            transform: scale(1.05);
        }
        
        .reporters-footer {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 0.75rem;
        }
        
        .footer-stat {
            flex: 1;
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            border-radius: 8px;
            padding: 0.6rem 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }
        
        .footer-stat i {
            font-size: 0.85rem;
            color: var(--gray-400);
            margin-bottom: 0.1rem;
        }
        
        .footer-stat span {
            font-size: 0.7rem;
            color: var(--gray-400);
            font-weight: 500;
        }
        
        .footer-stat strong {
            font-size: 0.9rem;
            color: var(--gray-800);
            font-weight: 700;
        }
        
        /* 12.21 Toast Notification */
        .toast-notification {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            padding: 0.85rem 1.25rem;
            border-radius: var(--radius-md);
            border: 1px solid;
            box-shadow: var(--shadow-xl);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 0.75rem;
            max-width: 450px;
            animation: slideInRight 0.3s ease;
            font-size: 0.9rem;
        }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        /* 12.22 Mobile Menu */
        .menu-toggle {
            display: none;
            background: var(--gray-800);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            z-index: 50;
        }
        
        /* 12.23 Responsive Design */
        @media (max-width: 1400px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-area {
                padding: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            html {
                font-size: 14px;
            }
            
            .app-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                display: none;
            }
            
            .sidebar.open {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 100;
                width: 280px;
                box-shadow: var(--shadow-xl);
            }
            
            .menu-toggle {
                display: block;
            }
            
            .dashboard-header {
                padding: 1.25rem;
                border-radius: var(--radius-lg);
            }
            
            .header-content {
                flex-direction: column;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-input {
                width: 100%;
            }
            
            .chart-wrapper {
                height: 280px;
            }
            
            .status-summary-bar {
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            html {
                font-size: 13px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                padding: 1rem;
            }
            
            .dashboard-header h1 {
                font-size: 1.25rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.6rem;
            }
            
            .reporter-item {
                padding: 0.65rem;
                gap: 0.5rem;
            }
            
            .avatar-ring {
                width: 40px;
                height: 40px;
            }
            
            .reporters-footer {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        /* 12.24 Print Styles */
        @media print {
            .sidebar,
            .filter-section,
            .bg-animation,
            .header-actions,
            .toast-notification,
            .refresh-indicator,
            .menu-toggle {
                display: none !important;
            }
            
            .dashboard-header {
                background: var(--gray-900) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .stat-card,
            .card {
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                break-inside: avoid;
            }
            
            body {
                background: white !important;
            }
            
            .content-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="bg-animation">
        <div class="orb"></div>
        <div class="orb"></div>
        <div class="orb"></div>
    </div>

    <!-- Main Layout -->
    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content Area -->
        <main class="main-area">
            <div class="content-area">
                <!-- Mobile Menu Toggle -->
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="เปิดเมนู">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="dashboard-container">
                    
                    <!-- ============================================= -->
                    <!-- HEADER SECTION -->
                    <!-- ============================================= -->
                    <header class="dashboard-header">
                        <div class="header-content">
                            <div class="header-title-section">
                                <h1>
                                    <i class="fas fa-chart-line"></i> 
                                    ภาพรวมระบบความเสี่ยง
                                </h1>
                                <p>
                                    <i class="fas fa-calendar-alt"></i> 
                                    ข้อมูล ณ วันที่ <?= $currentThaiDate ?> | ศูนย์อนามัยที่ 8 อุดรธานี
                                </p>
                            </div>
                            
                            <div class="header-actions">
                                <button onclick="exportDashboard('pdf')" class="btn btn-red btn-sm">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button onclick="exportDashboard('png')" class="btn btn-blue btn-sm">
                                    <i class="fas fa-image"></i> รูปภาพ
                                </button>
                                <button onclick="window.print()" class="btn btn-green btn-sm">
                                    <i class="fas fa-print"></i> พิมพ์
                                </button>
                                <button onclick="refreshDashboard()" class="btn btn-yellow btn-sm" id="refreshBtn">
                                    <i class="fas fa-sync-alt"></i> รีเฟรช
                                </button>
                            </div>
                        </div>
                        
                        <div class="refresh-indicator">
                            <i class="fas fa-clock"></i>
                            <span id="lastRefreshTime">อัปเดตล่าสุด: <?= date('H:i:s') ?></span>
                            <span id="autoRefreshStatus">● อัปเดตอัตโนมัติทุก 60 วินาที</span>
                        </div>
                    </header>
                    
                    <!-- ============================================= -->
                    <!-- FILTER SECTION -->
                    <!-- ============================================= -->
                    <section class="filter-section">
                        <form method="GET" id="filterForm" class="filter-form">
                            <span class="filter-label">
                                <i class="fas fa-filter"></i> ตัวกรอง:
                            </span>
                            
                            <input type="date" 
                                   name="date_from" 
                                   value="<?= htmlspecialchars($filters['date_from']) ?>" 
                                   class="filter-input auto-submit" 
                                   max="<?= date('Y-m-d') ?>"
                                   aria-label="วันที่เริ่มต้น">
                            
                            <span class="filter-separator">ถึง</span>
                            
                            <input type="date" 
                                   name="date_to" 
                                   value="<?= htmlspecialchars($filters['date_to']) ?>" 
                                   class="filter-input auto-submit" 
                                   max="<?= date('Y-m-d') ?>"
                                   aria-label="วันที่สิ้นสุด">
                            
                            <select name="status_filter" class="filter-input auto-submit" aria-label="กรองตามสถานะ">
                                <option value="">📋 สถานะ: ทั้งหมด</option>
                                <?php foreach ($STATUS_LIST as $status): ?>
                                    <option value="<?= $status ?>" <?= $filters['status_filter'] === $status ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="type_filter" class="filter-input auto-submit" aria-label="กรองตามประเภท">
                                <option value="">📂 ประเภท: ทั้งหมด</option>
                                <?php foreach ($RISK_TYPES as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= $filters['type_filter'] === $type ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <?php if ($activeFilters): ?>
                                <a href="dashboard.php" class="btn btn-red btn-sm">
                                    <i class="fas fa-times"></i> รีเซ็ตตัวกรอง
                                </a>
                            <?php endif; ?>
                        </form>
                    </section>
                    
                    <!-- ============================================= -->
                    <!-- ACTIVE FILTERS INFO -->
                    <!-- ============================================= -->
                    <?php if ($activeFilters): ?>
                    <div class="active-filters-info">
                        <i class="fas fa-info-circle"></i>
                        <span>กำลังแสดงข้อมูล:</span>
                        <strong>
                            <?= !empty($filters['date_from']) ? dateToThai($filters['date_from'], 'short') : 'ไม่จำกัดเริ่มต้น' ?>
                        </strong>
                        <span>ถึง</span>
                        <strong>
                            <?= !empty($filters['date_to']) ? dateToThai($filters['date_to'], 'short') : 'ปัจจุบัน' ?>
                        </strong>
                        <?php if (!empty($filters['status_filter'])): ?>
                            <span class="badge badge-warning"><?= htmlspecialchars($filters['status_filter']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($filters['type_filter'])): ?>
                            <span class="badge badge-info"><?= htmlspecialchars(mb_substr($filters['type_filter'], 0, 25)) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ============================================= -->
                    <!-- STATUS SUMMARY BAR -->
                    <!-- ============================================= -->
                    <div class="status-summary-bar">
                        <?php 
                        $statusConfig = [
                            'ยังไม่ดำเนินการ' => ['class' => 'status-pending', 'dot' => 'dot-pending', 'icon' => 'fa-clock'],
                            'กำลังดำเนินการ' => ['class' => 'status-progress', 'dot' => 'dot-progress', 'icon' => 'fa-spinner'],
                            'ดำเนินการแล้ว' => ['class' => 'status-completed', 'dot' => 'dot-completed', 'icon' => 'fa-check-circle'],
                            'ยุติ' => ['class' => 'status-terminated', 'dot' => 'dot-terminated', 'icon' => 'fa-stop-circle']
                        ];
                        foreach ($statusConfig as $name => $cfg): 
                        ?>
                        <div class="status-item <?= $cfg['class'] ?>">
                            <span class="status-dot <?= $cfg['dot'] ?>"></span>
                            <i class="fas <?= $cfg['icon'] ?> text-xs"></i>
                            <span><?= $name ?></span>
                            <span class="status-count"><?= number_format($data['statusCounts'][$name]) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- ============================================= -->
                    <!-- STATISTICS CARDS -->
                    <!-- ============================================= -->
                    <div class="stats-grid">
                        <!-- Total Risks -->
                        <div class="stat-card blue">
                            <div class="stat-header">
                                <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <span class="stat-trend up">
                                    <i class="fas fa-arrow-up"></i> +<?= number_format($data['todayRisks']) ?> วันนี้
                                </span>
                            </div>
                            <div class="stat-value" id="statTotalRisks"><?= number_format($data['totalRisks']) ?></div>
                            <div class="stat-label">ความเสี่ยงทั้งหมด</div>
                            <div class="stat-sub">ดำเนินการแล้ว <?= number_format($data['completedRisks']) ?> รายการ</div>
                        </div>
                        
                        <!-- Total Users -->
                        <div class="stat-card purple">
                            <div class="stat-header">
                                <div class="stat-icon" style="background:#f3e8ff;color:#8b5cf6;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span class="stat-trend info">
                                    <i class="fas fa-user-shield"></i> <?= number_format($data['totalAdmins']) ?> ผู้ดูแล
                                </span>
                            </div>
                            <div class="stat-value" id="statTotalUsers"><?= number_format($data['totalUsers']) ?></div>
                            <div class="stat-label">ผู้ใช้งานทั้งหมด</div>
                            <div class="stat-sub"><?= number_format($data['totalNormalUsers']) ?> ผู้ใช้ทั่วไป</div>
                        </div>
                        
                        <!-- Today's Reports -->
                        <div class="stat-card green">
                            <div class="stat-header">
                                <div class="stat-icon" style="background:#f0fdf4;color:#22c55e;">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="statTodayRisks"><?= number_format($data['todayRisks']) ?></div>
                            <div class="stat-label">รายงานวันนี้</div>
                            <div class="stat-sub"><?= $currentThaiDateShort ?></div>
                        </div>
                        
                        <!-- Completed -->
                        <div class="stat-card orange">
                            <div class="stat-header">
                                <div class="stat-icon" style="background:#fff7ed;color:#f97316;">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="statCompletedRisks"><?= number_format($data['completedRisks']) ?></div>
                            <div class="stat-label">ดำเนินการแล้ว</div>
                            <div class="stat-sub"><?= $data['completionPercentage'] ?>% ของทั้งหมด</div>
                        </div>
                    </div>
                    
                    <!-- ============================================= -->
                    <!-- CHARTS ROW 1: Severity + Risk Types -->
                    <!-- ============================================= -->
                    <div class="content-grid">
                        <!-- Severity Doughnut Chart -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background:#fff7ed;color:#f97316;">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <h3 class="card-title">ระดับความรุนแรงของความเสี่ยง</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-wrapper">
                                    <canvas id="severityChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Risk Type Polar Chart -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background:#ecfeff;color:#06b6d4;">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <h3 class="card-title">ประเภทความเสี่ยง</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-wrapper">
                                    <canvas id="riskTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================= -->
                    <!-- CHARTS ROW 2: Group + Status -->
                    <!-- ============================================= -->
                    <div class="content-grid">
                        <!-- Group Bar Chart -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background:#eef2ff;color:#6366f1;">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h3 class="card-title">จำนวนเคสตามกลุ่มงาน</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-wrapper">
                                    <canvas id="groupChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Stacked Bar Chart -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background:#eff6ff;color:#3b82f6;">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <h3 class="card-title">สถานะการดำเนินการแยกตามกลุ่มงาน</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-wrapper">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ============================================= -->
                    <!-- BOTTOM ROW: Recent + Top Reporters -->
                    <!-- ============================================= -->
                    <div class="content-grid">
                        <!-- Recent Risks Table -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background:#ecfdf5;color:#10b981;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="card-title">📋 รายการล่าสุด</h3>
                                <a href="risks.php" style="margin-left:auto;font-size:0.8rem;color:var(--primary);text-decoration:none;font-weight:600;">
                                    ดูทั้งหมด <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <div class="card-body no-padding">
                                <?php if (empty($data['recentRisks'])): ?>
                                    <div class="text-center py-10 text-muted">
                                        <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                        <p class="text-sm">ยังไม่มีข้อมูลในช่วงวันที่เลือก</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:60px;text-align:center;">#</th>
                                                    <th>กลุ่มงาน</th>
                                                    <th>ประเภท</th>
                                                    <th>ระดับ</th>
                                                    <th>สถานะ</th>
                                                    <th class="text-right">วันที่บันทึก</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $rowNumber = 1;
                                                foreach ($data['recentRisks'] as $row): 
                                                    $status = !empty($row['status']) ? $row['status'] : 'ยังไม่ดำเนินการ';
                                                    $unitDisplay = mb_substr($row['unit'] ?? '-', 0, 18);
                                                    $typeDisplay = mb_substr($row['risk_type'] ?? '-', 0, 22);
                                                ?>
                                                <tr>
                                                    <td style="font-weight:600;color:var(--gray-400);">
                                                        <?= $rowNumber++ ?>
                                                    </td>
                                                    <td>
                                                        <span class="text-truncate" style="max-width:140px;" title="<?= htmlspecialchars($row['unit'] ?? '') ?>">
                                                            <?= htmlspecialchars($unitDisplay) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-truncate" style="max-width:150px;" title="<?= htmlspecialchars($row['risk_type'] ?? '') ?>">
                                                            <?= htmlspecialchars($typeDisplay) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= getSeverityBadgeClass($row['severity']) ?>">
                                                            <?= htmlspecialchars($row['severity'] ?? '-') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= getStatusBadgeClass($status) ?>">
                                                            <?= htmlspecialchars(mb_substr($status, 0, 12)) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-right text-muted text-xs">
                                                        <?= dateToThai($row['created_at'], 'short') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Top Reporters -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background:#fffbeb;color:#f59e0b;">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <h3 class="card-title">🏆 ผู้รายงานสูงสุด</h3>
                                <span style="margin-left:auto;font-size:0.75rem;color:var(--gray-400);font-weight:600;">
                                    <i class="fas fa-ranking-star"></i> Top 5
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($data['topReporters'])): ?>
                                    <div class="text-center py-10 text-muted">
                                        <i class="fas fa-users text-2xl mb-2 block"></i>
                                        <p class="text-sm">ยังไม่มีข้อมูลผู้รายงาน</p>
                                    </div>
                                <?php else: ?>
                                    <div class="reporters-list">
                                        <?php 
                                        $medals = ['🥇', '🥈', '🥉', '⭐', '🌟'];
                                        $borderColors = ['#fbbf24', '#94a3b8', '#fb923c', '#60a5fa', '#34d399'];
                                        $fillColors = ['#fbbf24', '#94a3b8', '#fb923c', '#60a5fa', '#34d399'];
                                        $maxCount = max(1, $data['topReporters'][0]['report_count']);
                                        
                                        foreach ($data['topReporters'] as $index => $reporter): 
                                            $rank = $index + 1;
                                            $percentage = $data['totalTopReports'] > 0 
                                                ? round(($reporter['report_count'] / $data['totalTopReports']) * 100, 1) 
                                                : 0;
                                            $barWidth = min(100, ($reporter['report_count'] / $maxCount) * 100);
                                        ?>
                                        <div class="reporter-item rank-<?= $rank ?>" 
                                             style="animation: slideInRight <?= 0.3 + $index * 0.1 ?>s ease-out;">
                                            
                                            <!-- Rank -->
                                            <div class="rank-display">
                                                <?php if ($index < 3): ?>
                                                    <span class="rank-medal"><?= $medals[$index] ?></span>
                                                <?php else: ?>
                                                    <span class="rank-circle"><?= $rank ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Avatar -->
                                            <div class="avatar-container">
                                                <div class="avatar-ring" style="border-color: <?= $borderColors[$index] ?>;">
                                                    <img src="avatars/<?= htmlspecialchars(!empty($reporter['avatar']) ? $reporter['avatar'] : 'default.png') ?>" 
                                                         class="avatar-img" 
                                                         onerror="this.src='avatars/default.png'"
                                                         alt="<?= htmlspecialchars($reporter['username']) ?>"
                                                         loading="lazy">
                                                </div>
                                                <?php if ($index === 0): ?>
                                                    <span class="crown-icon">👑</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Info -->
                                            <div class="reporter-details">
                                                <div class="reporter-name">
                                                    <?= htmlspecialchars($reporter['username']) ?>
                                                    <?php if ($index === 0): ?>
                                                        <span class="top-badge">#1</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="reporter-stats">
                                                    <div class="progress-bar-mini">
                                                        <div class="progress-fill" 
                                                             style="width: <?= $barWidth ?>%; background: <?= $fillColors[$index] ?>;"></div>
                                                    </div>
                                                    <span class="report-count">
                                                        <i class="fas fa-file-alt"></i> 
                                                        <?= number_format($reporter['report_count']) ?> รายการ
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Percentage -->
                                            <span class="percentage-badge"><?= $percentage ?>%</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Footer Stats -->
                                    <div class="reporters-footer">
                                        <div class="footer-stat">
                                            <i class="fas fa-users"></i>
                                            <span>รวมการรายงานทั้งหมด</span>
                                            <strong><?= number_format($data['totalTopReports']) ?> รายการ</strong>
                                        </div>
                                        <div class="footer-stat">
                                            <i class="fas fa-trophy"></i>
                                            <span>ผู้รายงานอันดับ 1</span>
                                            <strong><?= htmlspecialchars($data['topReporters'][0]['username']) ?></strong>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                </div><!-- /dashboard-container -->
            </div><!-- /content-area -->
        </main>
    </div><!-- /app-wrapper -->
    
    <!-- Toast Notification -->
    <div id="toastNotification" class="toast-notification"></div>
    
    <!-- ============================================= -->
    <!-- JAVASCRIPT -->
    <!-- ============================================= -->
    <script>
    /**
     * Dashboard JavaScript
     * Handles charts, filters, refresh, export and notifications
     * Version: 2.1 - Fixed all chart issues
     */
    
    // =============================================
    // DATA FROM PHP
    // =============================================
    var DASHBOARD_DATA = {
        // Severity Chart - 🔧 ใช้สีที่แมปตามระดับ
        severityLabels: <?= json_encode($chartData['severityFullLabels'], JSON_UNESCAPED_UNICODE) ?>,
        severityCounts: <?= json_encode($chartData['severityFullCounts']) ?>,
        severityColors: <?= json_encode($chartData['severityColors']) ?>,
        
        // Risk Type Chart - 🔧 ใช้สีที่แมปตามประเภท (ไม่มีสีเกิน)
        riskTypeLabels: <?= json_encode($chartData['riskTypeLabels'], JSON_UNESCAPED_UNICODE) ?>,
        riskTypeCounts: <?= json_encode($chartData['riskTypeCounts']) ?>,
        riskTypeColors: <?= json_encode($chartData['riskTypeColors']) ?>,
        
        // Group Chart - 🔧 ข้อมูลครบทุกกลุ่ม
        groupUnits: <?= json_encode($chartData['groupUnits'], JSON_UNESCAPED_UNICODE) ?>,
        groupTotals: <?= json_encode($chartData['groupTotals']) ?>,
        groupTopTypes: <?= json_encode($chartData['groupTopTypesArray'], JSON_UNESCAPED_UNICODE) ?>,
        groupColors: <?= json_encode($CHART_COLORS['group']) ?>,
        groupBorderColors: <?= json_encode($CHART_COLORS['groupBorder']) ?>,
        
        // Status Chart - 🔧 ข้อมูลครบทุกกลุ่ม
        statusUnits: <?= json_encode($chartData['statusUnits'], JSON_UNESCAPED_UNICODE) ?>,
        statusPending: <?= json_encode($chartData['statusPending']) ?>,
        statusInProgress: <?= json_encode($chartData['statusInProgress']) ?>,
        statusCompleted: <?= json_encode($chartData['statusCompleted']) ?>,
        statusTerminated: <?= json_encode($chartData['statusTerminated']) ?>,
        
        // Security
        csrfToken: '<?= $csrf_token ?>'
    };
    
    // =============================================
    // UTILITY FUNCTIONS
    // =============================================
    
    /**
     * Query selector shortcut
     */
    function qs(selector) {
        return document.querySelector(selector);
    }
    
    /**
     * Query selector all shortcut
     */
    function qsa(selector) {
        return document.querySelectorAll(selector);
    }
    
    /**
     * Toggle mobile sidebar
     */
    function toggleSidebar() {
        var sidebar = qs('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    }
    
    /**
     * Format number with commas
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // =============================================
    // FILTER AUTO-SUBMIT
    // =============================================
    qsa('.auto-submit').forEach(function(el) {
        el.addEventListener('change', function() {
            qs('#filterForm').submit();
        });
    });
    
    // =============================================
    // CHART INITIALIZATION
    // =============================================
    var charts = {};
    
    function initializeCharts() {
        // ---- Severity Doughnut Chart ----
        var severityCanvas = qs('#severityChart');
        if (severityCanvas) {
            // 🔧 ใช้สีที่แมปตามระดับความเสี่ยงโดยตรง
            var bgColors = DASHBOARD_DATA.severityColors && DASHBOARD_DATA.severityColors.length > 0 
                ? DASHBOARD_DATA.severityColors 
                : ['#22c55e', '#3b82f6', '#84cc16', '#eab308', '#f97316', '#ef4444'];
            
            charts.severity = new Chart(severityCanvas, {
                type: 'doughnut',
                data: {
                    labels: DASHBOARD_DATA.severityLabels,
                    datasets: [{
                        data: DASHBOARD_DATA.severityCounts,
                        backgroundColor: bgColors,
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverBorderWidth: 4,
                        hoverBorderColor: '#e2e8f0'
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
                                padding: 16,
                                font: { size: 11, family: 'Sarabun' },
                                boxWidth: 12,
                                boxHeight: 12,
                                generateLabels: function(chart) {
                                    var data = chart.data;
                                    return data.labels.map(function(label, i) {
                                        return {
                                            text: label.length > 40 ? label.substring(0, 40) + '...' : label,
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
                            backgroundColor: 'rgba(15,23,42,0.95)',
                            titleFont: { size: 13, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 12, family: 'Sarabun' },
                            padding: 14,
                            cornerRadius: 10,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var percent = ((value / total) * 100).toFixed(1);
                                    return '  จำนวน: ' + value + ' รายการ (' + percent + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // ---- Risk Type Polar Area Chart ----
        var riskTypeCanvas = qs('#riskTypeChart');
        if (riskTypeCanvas) {
            // 🔧 ใช้สีที่แมปตามประเภทความเสี่ยง (ไม่มีสีเกิน)
            var polarColors = DASHBOARD_DATA.riskTypeColors && DASHBOARD_DATA.riskTypeColors.length > 0
                ? DASHBOARD_DATA.riskTypeColors
                : [
                    'rgba(99,102,241,0.75)',
                    'rgba(59,130,246,0.75)',
                    'rgba(34,197,94,0.75)',
                    'rgba(234,179,8,0.75)',
                    'rgba(249,115,22,0.75)',
                    'rgba(239,68,68,0.75)'
                  ];
            
            charts.riskType = new Chart(riskTypeCanvas, {
                type: 'polarArea',
                data: {
                    labels: DASHBOARD_DATA.riskTypeLabels,
                    datasets: [{
                        data: DASHBOARD_DATA.riskTypeCounts,
                        backgroundColor: polarColors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
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
                                padding: 18,
                                font: { size: 11, family: 'Sarabun' },
                                boxWidth: 10,
                                boxHeight: 10
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,0.95)',
                            titleFont: { size: 13, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 12, family: 'Sarabun' },
                            padding: 14,
                            cornerRadius: 10,
                            callbacks: {
                                label: function(context) {
                                    var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var percent = ((context.raw / total) * 100).toFixed(1);
                                    return '  ' + context.label + ': ' + context.raw + ' รายการ (' + percent + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        r: {
                            ticks: { display: false },
                            grid: { color: 'rgba(226,232,240,0.5)' }
                        }
                    }
                }
            });
        }
        
        // ---- Group Bar Chart ----
        var groupCanvas = qs('#groupChart');
        if (groupCanvas) {
            // 🔧 สร้างสีให้พอดีกับจำนวนกลุ่ม (9 กลุ่ม)
            var groupBgColors = [];
            var groupBorderColorsList = [];
            var allGroupColors = DASHBOARD_DATA.groupColors;
            var allGroupBorderColors = DASHBOARD_DATA.groupBorderColors;
            
            for (var i = 0; i < DASHBOARD_DATA.groupUnits.length; i++) {
                groupBgColors.push(allGroupColors[i % allGroupColors.length]);
                groupBorderColorsList.push(allGroupBorderColors[i % allGroupBorderColors.length]);
            }
            
            charts.group = new Chart(groupCanvas, {
                type: 'bar',
                data: {
                    labels: DASHBOARD_DATA.groupUnits,
                    datasets: [{
                        label: 'จำนวนเคส',
                        data: DASHBOARD_DATA.groupTotals,
                        backgroundColor: groupBgColors,
                        borderColor: groupBorderColorsList,
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
                            backgroundColor: 'rgba(15,23,42,0.95)',
                            titleFont: { size: 13, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 12, family: 'Sarabun' },
                            padding: 14,
                            cornerRadius: 10,
                            callbacks: {
                                label: function(context) {
                                    return '  จำนวน: ' + context.raw + ' รายการ';
                                },
                                afterLabel: function(context) {
                                    return '  ประเภทที่พบบ่อย: ' + DASHBOARD_DATA.groupTopTypes[context.dataIndex];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return Math.floor(value) === value ? value : '';
                                },
                                font: { family: 'Sarabun', size: 11 }
                            },
                            grid: { color: 'rgba(241,245,249,0.5)' },
                            title: {
                                display: true,
                                text: 'จำนวนรายการ',
                                color: '#94a3b8',
                                font: { size: 11, family: 'Sarabun' }
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11, family: 'Sarabun' },
                                color: '#475569'
                            }
                        }
                    }
                }
            });
        }
        
        // ---- Status Stacked Bar Chart ----
        var statusCanvas = qs('#statusChart');
        if (statusCanvas) {
            charts.status = new Chart(statusCanvas, {
                type: 'bar',
                data: {
                    labels: DASHBOARD_DATA.statusUnits,
                    datasets: [
                        {
                            label: 'ยังไม่ดำเนินการ',
                            data: DASHBOARD_DATA.statusPending,
                            backgroundColor: '#fbbf24',
                            borderRadius: 5,
                            borderSkipped: false
                        },
                        {
                            label: 'กำลังดำเนินการ',
                            data: DASHBOARD_DATA.statusInProgress,
                            backgroundColor: '#60a5fa',
                            borderRadius: 5,
                            borderSkipped: false
                        },
                        {
                            label: 'ดำเนินการแล้ว',
                            data: DASHBOARD_DATA.statusCompleted,
                            backgroundColor: '#34d399',
                            borderRadius: 5,
                            borderSkipped: false
                        },
                        {
                            label: 'ยุติ',
                            data: DASHBOARD_DATA.statusTerminated,
                            backgroundColor: '#94a3b8',
                            borderRadius: 5,
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
                                padding: 20,
                                font: { size: 11, family: 'Sarabun' },
                                pointStyleWidth: 12,
                                generateLabels: function(chart) {
                                    return chart.data.datasets.map(function(dataset, i) {
                                        return {
                                            text: dataset.label,
                                            fillStyle: dataset.backgroundColor,
                                            strokeStyle: dataset.backgroundColor,
                                            lineWidth: 0,
                                            hidden: false,
                                            index: i,
                                            pointStyle: 'rectRounded',
                                            pointStyleWidth: 14
                                        };
                                    });
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,0.95)',
                            titleFont: { size: 13, family: 'Sarabun', weight: 'bold' },
                            bodyFont: { size: 12, family: 'Sarabun' },
                            padding: 14,
                            cornerRadius: 10,
                            callbacks: {
                                label: function(context) {
                                    return '  ' + context.dataset.label + ': ' + context.raw + ' รายการ';
                                },
                                footer: function(tooltipItems) {
                                    var sum = 0;
                                    tooltipItems.forEach(function(item) { sum += item.raw; });
                                    return 'รวมทั้งหมด: ' + sum + ' รายการ';
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
                                callback: function(value) {
                                    return Math.floor(value) === value ? value : '';
                                },
                                font: { family: 'Sarabun', size: 11 }
                            },
                            grid: { color: 'rgba(241,245,249,0.5)' },
                            title: {
                                display: true,
                                text: 'จำนวนรายการ',
                                color: '#94a3b8',
                                font: { size: 11, family: 'Sarabun' }
                            }
                        },
                        y: {
                            stacked: true,
                            grid: { display: false },
                            ticks: {
                                font: { size: 11, family: 'Sarabun' }
                            }
                        }
                    }
                }
            });
        }
    }
    
    // =============================================
    // EXPORT FUNCTION
    // =============================================
    function exportDashboard(format) {
        Swal.fire({
            title: 'กำลังส่งออกข้อมูล...',
            text: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
        
        var params = new URLSearchParams({
            action: 'export_dashboard',
            format: format,
            date_from: qs('[name="date_from"]') ? qs('[name="date_from"]').value : '',
            date_to: qs('[name="date_to"]') ? qs('[name="date_to"]').value : '',
            status_filter: qs('[name="status_filter"]') ? qs('[name="status_filter"]').value : '',
            type_filter: qs('[name="type_filter"]') ? qs('[name="type_filter"]').value : '',
            csrf_token: DASHBOARD_DATA.csrfToken
        });
        
        fetch('action.php?' + params.toString())
            .then(function(response) { return response.blob(); })
            .then(function(blob) {
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'dashboard_report_' + new Date().toISOString().slice(0,10) + '.' + format;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                
                Swal.fire({
                    icon: 'success',
                    title: 'ส่งออกสำเร็จ!',
                    text: 'ไฟล์ถูกดาวน์โหลดเรียบร้อยแล้ว',
                    timer: 2500,
                    timerProgressBar: true,
                    confirmButtonColor: '#2563eb'
                });
            })
            .catch(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถส่งออกข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
                    confirmButtonColor: '#2563eb'
                });
            });
    }
    
    // =============================================
    // REFRESH FUNCTION
    // =============================================
    var isRefreshing = false;
    
    function refreshDashboard() {
        if (isRefreshing) return;
        isRefreshing = true;
        
        var refreshBtn = qs('#refreshBtn');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> กำลังโหลด...';
        }
        
        var params = new URLSearchParams({
            action: 'dashboard_data',
            date_from: qs('[name="date_from"]') ? qs('[name="date_from"]').value : '',
            date_to: qs('[name="date_to"]') ? qs('[name="date_to"]').value : '',
            status_filter: qs('[name="status_filter"]') ? qs('[name="status_filter"]').value : '',
            type_filter: qs('[name="type_filter"]') ? qs('[name="type_filter"]').value : '',
            csrf_token: DASHBOARD_DATA.csrfToken
        });
        
        fetch('action.php?' + params.toString())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('🔄 อัปเดตข้อมูลเรียบร้อย', 'success');
                }
            })
            .catch(function() {
                showToast('⚠️ ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์', 'warning');
            })
            .finally(function() {
                isRefreshing = false;
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> รีเฟรช';
                }
                updateRefreshTime();
            });
    }
    
    function updateRefreshTime() {
        var now = new Date();
        var timeStr = 
            String(now.getHours()).padStart(2, '0') + ':' +
            String(now.getMinutes()).padStart(2, '0') + ':' +
            String(now.getSeconds()).padStart(2, '0');
        
        var timeEl = qs('#lastRefreshTime');
        if (timeEl) {
            timeEl.textContent = 'อัปเดตล่าสุด: ' + timeStr;
        }
    }
    
    // =============================================
    // TOAST NOTIFICATION
    // =============================================
    function showToast(message, type) {
        type = type || 'info';
        
        var styles = {
            info: { bg: '#eff6ff', border: '#93c5fd', color: '#1e40af', icon: 'fa-info-circle' },
            success: { bg: '#f0fdf4', border: '#86efac', color: '#16a34a', icon: 'fa-check-circle' },
            warning: { bg: '#fffbeb', border: '#fcd34d', color: '#92400e', icon: 'fa-exclamation-triangle' },
            error: { bg: '#fef2f2', border: '#fca5a5', color: '#dc2626', icon: 'fa-times-circle' }
        };
        
        var style = styles[type] || styles.info;
        var toast = qs('#toastNotification');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toastNotification';
            toast.className = 'toast-notification';
            document.body.appendChild(toast);
        }
        
        toast.style.background = style.bg;
        toast.style.borderColor = style.border;
        toast.style.color = style.color;
        toast.style.display = 'flex';
        toast.innerHTML = '<i class="fas ' + style.icon + '"></i> ' + message;
        
        setTimeout(function() {
            toast.style.display = 'none';
        }, 4000);
    }
    
    // =============================================
    // INITIALIZATION
    // =============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts
        initializeCharts();
        
        // Set max date for date inputs
        var today = new Date().toISOString().split('T')[0];
        qsa('input[type="date"]').forEach(function(input) {
            input.setAttribute('max', today);
        });
        
        // Auto refresh every 60 seconds
        setInterval(refreshDashboard, 60000);
        
        // Close sidebar when clicking outside (mobile)
        document.addEventListener('click', function(event) {
            var sidebar = qs('.sidebar');
            var menuToggle = qs('.menu-toggle');
            
            if (sidebar && sidebar.classList.contains('open') && 
                !sidebar.contains(event.target) && 
                menuToggle && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
                if (!event.target.closest('input, textarea, select')) {
                    event.preventDefault();
                    refreshDashboard();
                }
            }
            
            if (event.key === 'Escape') {
                var resetLink = qs('a[href="dashboard.php"]');
                if (resetLink && resetLink.classList.contains('btn-red')) {
                    window.location.href = resetLink.href;
                }
            }
        });
        
        console.log('✅ Dashboard v2.1 initialized successfully');
        console.log('🔧 Fixed: Severity colors mapped correctly');
        console.log('🔧 Fixed: Risk type colors no longer exceed');
        console.log('🔧 Fixed: All groups displayed in charts');
        console.log('🔄 Auto-refresh enabled (every 60 seconds)');
    });
    </script>
</body>
</html>
<?php include 'includes/footer.php'; ?>