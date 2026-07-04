<?php
/**
 * หน้าสรุปผลการรายงาน - UI สวยงาม พร้อมพื้นหลัง
 * - User: เห็นเฉพาะรายการของตัวเอง / Admin: เห็นทั้งหมด
 * - บันทึก: มาตรการแก้ไข, ผู้รับผิดชอบ, การติดตามผล, ผลที่คาดว่าจะได้รับ
 * - แนบไฟล์สรุปผลได้ + Lightbox
 * - Pagination 10 รายการ/หน้า + Filter & Search + Hover Tooltip
 * - ผู้รับผิดชอบแสดงชื่อผู้ใช้ปัจจุบันเป็นค่าเริ่มต้น
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

$success = '';
$error = '';

$risk_id = isset($_GET['risk_id']) ? (int)$_GET['risk_id'] : 0;

$search = $_GET['search'] ?? '';
$unit_filter = $_GET['unit'] ?? '';
$type_filter = $_GET['risk_type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';

$risk = null;
if ($risk_id) {
    $stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->execute([$risk_id]);
    $risk = $stmt->fetch();
    if (!$risk) redirect('risks.php');
    if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) redirect('risks.php');
}

$existingReport = null;
if ($risk_id) {
    $stmt = $pdo->prepare("SELECT * FROM risk_reports WHERE risk_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$risk_id]);
    $existingReport = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request (CSRF token ไม่ถูกต้อง)';
    } else {
        $risk_id = (int)($_POST['risk_id'] ?? 0);
        $corrective_action = trim($_POST['corrective_action'] ?? '');
        $responsible_person = trim($_POST['responsible_person'] ?? '');
        $follow_up = trim($_POST['follow_up'] ?? '');
        $expected_outcome = trim($_POST['expected_outcome'] ?? '');

        if (empty($corrective_action) && empty($responsible_person) && empty($follow_up) && empty($expected_outcome)) {
            $error = 'กรุณากรอกข้อมูลอย่างน้อย 1 ฟิลด์';
        } else {
            $uploaded_file = '';
            if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/reports/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_extension = pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    $error = 'ประเภทไฟล์ไม่ถูกต้อง (รองรับ: PDF, Word, Excel, รูปภาพ)';
                } elseif ($_FILES['report_file']['size'] > 10 * 1024 * 1024) {
                    $error = 'ขนาดไฟล์ต้องไม่เกิน 10MB';
                } else {
                    $file_name = 'report_' . $risk_id . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['report_file']['tmp_name'], $file_path)) $uploaded_file = $file_path;
                    else $error = 'ไม่สามารถอัปโหลดไฟล์ได้';
                }
            }

            if (empty($error)) {
                if ($existingReport) {
                    $sql = "UPDATE risk_reports SET corrective_action = ?, responsible_person = ?, follow_up = ?, expected_outcome = ?";
                    $params = [$corrective_action, $responsible_person, $follow_up, $expected_outcome];
                    if ($uploaded_file) {
                        if ($existingReport['report_file'] && file_exists($existingReport['report_file'])) unlink($existingReport['report_file']);
                        $sql .= ", report_file = ?";
                        $params[] = $uploaded_file;
                    }
                    $sql .= " WHERE risk_id = ?";
                    $params[] = $risk_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $stmtUpdate = $pdo->prepare("UPDATE risks SET status = 'ดำเนินการแล้ว' WHERE id = ?");
                    $stmtUpdate->execute([$risk_id]);
                    $success = 'อัปเดตสรุปผลการรายงานเรียบร้อยแล้ว';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, report_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$risk_id, $corrective_action, $responsible_person, $follow_up, $expected_outcome, $uploaded_file, $_SESSION['user_id']]);
                    $stmtUpdate = $pdo->prepare("UPDATE risks SET status = 'ดำเนินการแล้ว' WHERE id = ?");
                    $stmtUpdate->execute([$risk_id]);
                    $success = 'บันทึกสรุปผลการรายงานเรียบร้อยแล้ว';
                }
                $stmt = $pdo->prepare("SELECT * FROM risk_reports WHERE risk_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$risk_id]);
                $existingReport = $stmt->fetch();
            }
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$allRisks = [];
$totalRows = 0;
$totalPages = 0;

$filterUnits = $pdo->query("SELECT DISTINCT unit FROM risks WHERE unit IS NOT NULL AND unit != '' ORDER BY unit")->fetchAll(PDO::FETCH_COLUMN);
$filterTypes = $pdo->query("SELECT DISTINCT risk_type FROM risks ORDER BY risk_type")->fetchAll(PDO::FETCH_COLUMN);
$filterSeverities = $pdo->query("SELECT DISTINCT severity FROM risks ORDER BY severity")->fetchAll(PDO::FETCH_COLUMN);

if ($risk_id === 0) {
    $whereClause = "WHERE 1=1";
    $countParams = [];
    $queryParams = [];
    if (!isAdmin()) {
        $whereClause .= " AND r.user_id = ?";
        $countParams[] = $_SESSION['user_id'];
        $queryParams[] = $_SESSION['user_id'];
    }
    if ($search) {
        $whereClause .= " AND (r.risk_type LIKE ? OR r.risk_detail LIKE ? OR r.unit LIKE ? OR r.risk_type_other LIKE ?)";
        $searchTerm = "%{$search}%";
        for ($i = 0; $i < 4; $i++) {
            $countParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }
    }
    if ($unit_filter) {
        $whereClause .= " AND r.unit = ?";
        $countParams[] = $unit_filter;
        $queryParams[] = $unit_filter;
    }
    if ($type_filter) {
        $whereClause .= " AND r.risk_type = ?";
        $countParams[] = $type_filter;
        $queryParams[] = $type_filter;
    }
    if ($severity_filter) {
        $whereClause .= " AND r.severity = ?";
        $countParams[] = $severity_filter;
        $queryParams[] = $severity_filter;
    }
    if ($status_filter) {
        $whereClause .= " AND r.status = ?";
        $countParams[] = $status_filter;
        $queryParams[] = $status_filter;
    }
    if ($date_from) {
        $whereClause .= " AND DATE(r.event_datetime) >= ?";
        $countParams[] = $date_from;
        $queryParams[] = $date_from;
    }

    $countSql = "SELECT COUNT(*) FROM risks r {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRows = $countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $perPage);

    $limit = (int)$perPage;
    $offsetVal = (int)$offset;
    $sql = "SELECT r.*, u.username, rr.id as report_id FROM risks r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN risk_reports rr ON r.id = rr.risk_id {$whereClause} ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offsetVal}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $allRisks = $stmt->fetchAll();
}

function getStatusClass($status)
{
    if ($status == 'ดำเนินการแล้ว') return 'bg-green-100 text-green-700';
    if ($status == 'กำลังดำเนินการ') return 'bg-blue-100 text-blue-700';
    if ($status == 'ยุติ') return 'bg-gray-100 text-gray-500';
    return 'bg-yellow-100 text-yellow-700';
}
function isImageFile($filename)
{
    if (empty($filename)) return false;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
function getFileIcon($filename)
{
    if (empty($filename)) return 'fa-file';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = ['pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word', 'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image', 'gif' => 'fa-file-image', 'webp' => 'fa-file-image'];
    return isset($icons[$ext]) ? $icons[$ext] : 'fa-file';
}
function formatFileSize($bytes)
{
    if ($bytes === false || $bytes === null) return 'ไม่ทราบขนาด';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
}
function getSeverityBadgeClass($severity)
{
    if ($severity == 'A') return 'bg-blue-100 text-blue-700';
    if ($severity == 'B') return 'bg-green-100 text-green-700';
    if ($severity == 'C') return 'bg-lime-100 text-lime-700';
    if ($severity == 'D') return 'bg-yellow-100 text-yellow-700';
    if ($severity == 'F') return 'bg-orange-100 text-orange-700';
    if ($severity == 'E') return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-600';
}
function buildReportPageUrl($page, $currentParams)
{
    $query = $currentParams;
    unset($query['page']);
    if ($page > 1) $query['page'] = $page;
    $url = 'report_summary.php';
    if (!empty($query)) $url .= '?' . http_build_query($query);
    return $url;
}

$csrf_token = generateCsrfToken();
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />

<style>
    :root {
        --primary: #3b82f6;
        --primary-dark: #1e40af;
    }

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

    .page-container {
        max-width: 1300px;
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

    /* Header */
    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem;
        padding: 1.75rem 2.25rem;
        margin-bottom: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
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

    .page-header h2 {
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

    /* Filter */
    .filter-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
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
        border: 1.5px solid rgba(226, 232, 240, 0.8);
        border-radius: 0.5rem;
        font-size: 0.85rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: rgba(250, 251, 252, 0.8);
        transition: all 0.2s;
        width: 100%;
        backdrop-filter: blur(10px);
    }

    .filter-input:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    select.filter-input {
        cursor: pointer;
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
        justify-content: flex-end;
        align-items: center;
        padding-top: 0.75rem;
        margin-top: 0.5rem;
        border-top: 1px solid rgba(241, 245, 249, 0.8);
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

    .btn-filter.primary {
        background: #3b82f6;
        color: white;
    }

    .btn-filter.primary:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-filter.danger {
        background: #fee2e2;
        color: #dc2626;
        text-decoration: none;
    }

    .btn-filter.danger:hover {
        background: #fecaca;
    }

    .btn-filter.success {
        background: #dcfce7;
        color: #16a34a;
        text-decoration: none;
    }

    .btn-filter.success:hover {
        background: #bbf7d0;
    }

    .filter-stats {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-right: auto;
    }

    /* Table */
    .table-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }

    .table-header-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        background: rgba(250, 251, 252, 0.7);
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
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
        background: rgba(250, 251, 252, 0.5);
        border-bottom: 2px solid rgba(226, 232, 240, 0.5);
        white-space: nowrap;
    }

    td {
        padding: 0.75rem 0.9rem;
        border-bottom: 1px solid rgba(248, 250, 252, 0.8);
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
        background: rgba(240, 249, 255, 0.5);
    }

    tbody tr:nth-child(even) {
        background: rgba(250, 251, 252, 0.3);
    }

    tbody tr:nth-child(even):hover {
        background: rgba(240, 249, 255, 0.5);
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

    /* Empty */
    .empty-state {
        text-align: center;
        padding: 5rem 2rem;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem;
        border: 2px dashed rgba(226, 232, 240, 0.8);
    }

    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    /* Pagination */
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
        border: 1px solid rgba(226, 232, 240, 0.8);
        font-size: 0.85rem;
        font-weight: 500;
        color: #64748b;
        text-decoration: none;
        transition: all 0.2s;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
    }

    .page-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-2px);
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

    /* Form */
    .form-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    }

    .info-card {
        background: rgba(248, 250, 252, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.5);
        border-radius: 0.75rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-label i {
        width: 28px;
        height: 28px;
        background: rgba(239, 246, 255, 0.8);
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        font-size: 0.8rem;
    }

    .form-textarea {
        width: 100%;
        min-height: 120px;
        padding: 0.85rem 1rem;
        border: 1.5px solid rgba(226, 232, 240, 0.8);
        border-radius: 0.7rem;
        background: rgba(250, 251, 252, 0.8);
        color: #1e293b;
        font-size: 0.9rem;
        resize: vertical;
        transition: all 0.2s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        backdrop-filter: blur(10px);
    }

    .form-textarea:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .form-input {
        width: 100%;
        padding: 0.7rem 0.9rem;
        border: 1.5px solid rgba(226, 232, 240, 0.8);
        border-radius: 0.7rem;
        background: rgba(250, 251, 252, 0.8);
        color: #1e293b;
        font-size: 0.9rem;
        transition: all 0.2s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        backdrop-filter: blur(10px);
    }

    .form-input:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .btn-submit {
        background: linear-gradient(135deg, #1e40af, #3b82f6);
        color: white;
        padding: 0.7rem 1.8rem;
        border-radius: 0.7rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
        font-family: 'Sarabun', sans-serif;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 32px rgba(37, 99, 235, 0.45);
    }

    .btn-cancel {
        padding: 0.7rem 1.5rem;
        border-radius: 0.7rem;
        font-weight: 500;
        font-size: 0.9rem;
        background: rgba(241, 245, 249, 0.8);
        color: #64748b;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.5);
    }

    .btn-cancel:hover {
        background: #e2e8f0;
        color: #334155;
        transform: translateY(-2px);
    }

    /* File */
    .file-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.5);
        border-radius: 0.75rem;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .file-card-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1rem;
        background: rgba(248, 250, 252, 0.7);
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        font-size: 0.85rem;
    }

    .file-card-preview {
        background: rgba(241, 245, 249, 0.5);
        padding: 1rem;
        text-align: center;
        border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    }

    .img-preview-link {
        position: relative;
        display: inline-block;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 2px solid rgba(226, 232, 240, 0.8);
        cursor: pointer;
        transition: all 0.3s;
        max-width: 100%;
    }

    .img-preview-link:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
    }

    .img-preview-link img {
        display: block;
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
    }

    .img-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        opacity: 0;
        transition: opacity 0.3s;
        color: white;
    }

    .img-preview-link:hover .img-overlay {
        opacity: 1;
    }

    .img-overlay i {
        font-size: 1.5rem;
    }

    .file-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .file-info-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .file-icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .btn-sm {
        padding: 0.4rem 0.7rem;
        border-radius: 0.4rem;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
        border: 1px solid;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .btn-sm.download {
        background: rgba(239, 246, 255, 0.8);
        color: #2563eb;
        border-color: rgba(191, 219, 254, 0.5);
    }

    .btn-sm.download:hover {
        background: #dbeafe;
        transform: translateY(-2px);
    }

    .btn-sm.view {
        background: rgba(240, 253, 244, 0.8);
        color: #16a34a;
        border-color: rgba(187, 247, 208, 0.5);
    }

    .btn-sm.view:hover {
        background: #dcfce7;
        transform: translateY(-2px);
    }

    .upload-area {
        border: 2px dashed rgba(203, 213, 225, 0.8);
        border-radius: 0.75rem;
        padding: 2rem;
        text-align: center;
        background: rgba(248, 250, 252, 0.5);
        cursor: pointer;
        transition: all 0.2s;
        display: block;
        backdrop-filter: blur(10px);
    }

    .upload-area:hover {
        border-color: #3b82f6;
        background: rgba(239, 246, 255, 0.5);
    }

    .upload-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(239, 246, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem;
        transition: all 0.3s;
    }

    .upload-area:hover .upload-icon {
        background: #dbeafe;
        transform: scale(1.1);
    }

    .upload-icon i {
        font-size: 1.3rem;
        color: #3b82f6;
    }

    .file-preview-inline {
        margin-top: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.7rem;
        background: rgba(239, 246, 255, 0.8);
        border: 1px solid rgba(191, 219, 254, 0.5);
        border-radius: 0.4rem;
        font-size: 0.8rem;
    }

    .btn-remove {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        padding: 0.2rem;
        border-radius: 50%;
    }

    .btn-remove:hover {
        background: #fee2e2;
    }

    @media (max-width: 1024px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 640px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }

    @media print {

        .sidebar,
        .filter-card,
        .pagination-bar,
        .floating-shapes {
            display: none !important;
        }
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
        
        <div class="page-container">

            <?php if (!$risk_id): ?>
                <!-- ========== LIST VIEW ========== -->
                <div class="page-header">
                    <h2>📋 สรุปผลการรายงาน</h2>
                    <p>เลือกเคสที่ต้องการบันทึกสรุปผล <span>(<?= number_format($totalRows) ?> รายการ)</span></p>
                </div>

                <div class="filter-card">
                    <form method="GET" action="report_summary.php" id="filterForm">
                        <div class="filter-grid">
                            <div class="search-box filter-group">
                                <label class="filter-label">🔍 ค้นหา</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="filter-input" placeholder="พิมพ์คำค้นหา..." onkeyup="if(event.key==='Enter') this.form.submit();">
                                <i class="fas fa-search search-icon"></i>
                                <?php if ($search): ?>
                                    <a href="report_summary.php" class="search-clear" title="ล้างคำค้นหา"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">กลุ่มงาน</label>
                                <select name="unit" class="filter-input" onchange="this.form.submit()">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($filterUnits as $u): ?>
                                        <option value="<?= htmlspecialchars($u) ?>" <?= $unit_filter == $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">ประเภท</label>
                                <select name="risk_type" class="filter-input" onchange="this.form.submit()">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($filterTypes as $t): ?>
                                        <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">ระดับ</label>
                                <select name="severity" class="filter-input" onchange="this.form.submit()">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($filterSeverities as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= $severity_filter == $s ? 'selected' : '' ?>>ระดับ <?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">สถานะ</label>
                                <select name="status" class="filter-input" onchange="this.form.submit()">
                                    <option value="">ทั้งหมด</option>
                                    <option value="ยังไม่ดำเนินการ" <?= $status_filter == 'ยังไม่ดำเนินการ' ? 'selected' : '' ?>>ยังไม่ดำเนินการ</option>
                                    <option value="กำลังดำเนินการ" <?= $status_filter == 'กำลังดำเนินการ' ? 'selected' : '' ?>>กำลังดำเนินการ</option>
                                    <option value="ดำเนินการแล้ว" <?= $status_filter == 'ดำเนินการแล้ว' ? 'selected' : '' ?>>ดำเนินการแล้ว</option>
                                    <option value="ยุติ" <?= $status_filter == 'ยุติ' ? 'selected' : '' ?>>ยุติ</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">ตั้งแต่วันที่</label>
                                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="filter-input" onchange="this.form.submit()">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <?php if ($search || $unit_filter || $type_filter || $severity_filter || $status_filter || $date_from): ?>
                                <span class="filter-stats">
                                    <i class="fas fa-filter text-blue-500 mr-1"></i>
                                    กำลังกรอง: <?= number_format($totalRows) ?> รายการ
                                </span>
                                <a href="report_summary.php" class="btn-filter danger">
                                    <i class="fas fa-times"></i> ล้างตัวกรองทั้งหมด
                                </a>
                            <?php else: ?>
                                <span class="filter-stats">
                                    <i class="fas fa-database text-gray-400 mr-1"></i>
                                    ทั้งหมด <?= number_format($totalRows) ?> รายการ
                                </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php if (empty($allRisks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">ไม่พบรายการ</h3>
                        <p class="text-gray-400 mb-4">
                            <?= ($search || $unit_filter || $type_filter || $severity_filter || $status_filter || $date_from)
                                ? 'ไม่พบข้อมูลตรงตามเงื่อนไขที่เลือก'
                                : (isAdmin() ? 'ไม่มีข้อมูลในระบบ' : 'คุณยังไม่มีรายการ') ?>
                        </p>
                        <?php if ($search || $unit_filter || $type_filter || $severity_filter || $status_filter || $date_from): ?>
                            <a href="report_summary.php" class="btn-filter primary" style="text-decoration:none;">
                                <i class="fas fa-redo"></i> ล้างตัวกรอง
                            </a>
                        <?php else: ?>
                            <a href="risk_form.php" class="btn-filter primary" style="text-decoration:none;">
                                <i class="fas fa-plus"></i> เพิ่มความเสี่ยง
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-header-bar">
                            <span class="font-semibold text-gray-700">
                                <i class="fas fa-list text-blue-600 mr-1"></i> รายการความเสี่ยง
                            </span>
                            <span class="text-xs text-gray-500">
                                หน้า <?= $page ?> จาก <?= max(1, $totalPages) ?> |
                                แสดง <?= count($allRisks) ?> / <?= number_format($totalRows) ?> รายการ
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>ประเภท</th>
                                        <th>กลุ่มงาน</th>
                                        <th>ระดับ</th>
                                        <th>วันที่</th>
                                        <th>ผู้รายงาน</th>
                                        <th>สถานะ</th>
                                        <th>รายงาน</th>
                                        <th style="width:80px;text-align:center;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allRisks as $index => $item):
                                        $rowNum = ($page - 1) * $perPage + $index + 1;
                                        $sevClass = getSeverityBadgeClass($item['severity']);
                                        $statusClass = getStatusClass($item['status'] ?? '');
                                    ?>
                                        <tr>
                                            <td class="text-gray-400"><?= $rowNum ?></td>
                                            <td>
                                                <span class="font-medium"><?= htmlspecialchars(mb_substr($item['risk_type'] ?? '-', 0, 25)) ?></span>
                                                <?php if (!empty($item['risk_type_other'])): ?>
                                                    <span class="text-xs text-gray-400">(<?= htmlspecialchars($item['risk_type_other']) ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars(mb_substr($item['unit'] ?? '-', 0, 20)) ?></td>
                                            <td><span class="badge <?= $sevClass ?>"><?= htmlspecialchars($item['severity'] ?? '-') ?></span></td>
                                            <td class="text-gray-500 text-sm"><?= date('d/m/Y', strtotime($item['event_datetime'])) ?></td>
                                            <td><?= htmlspecialchars($item['username'] ?? 'ไม่ระบุ') ?></td>
                                            <td>
                                                <?php if (!empty($item['status'])): ?>
                                                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($item['status']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-gray-100 text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($item['report_id']): ?>
                                                    <span class="badge bg-green-100 text-green-700"><i class="fas fa-check-circle text-xs"></i> มีรายงาน</span>
                                                <?php else: ?>
                                                    <span class="badge bg-yellow-100 text-yellow-700"><i class="fas fa-clock text-xs"></i> รอรายงาน</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:3px;justify-content:center;">
                                                    <a href="?risk_id=<?= $item['id'] ?>" class="btn-icon bg-blue-50 text-blue-600 hover:bg-blue-100" title="<?= $item['report_id'] ? 'แก้ไข' : 'เพิ่ม' ?>">
                                                        <i class="fas fa-<?= $item['report_id'] ? 'edit' : 'plus' ?> text-sm"></i>
                                                    </a>
                                                    <?php if ($item['report_id']): ?>
                                                        <a href="?risk_id=<?= $item['id'] ?>" class="btn-icon bg-green-50 text-green-600 hover:bg-green-100" title="ดู">
                                                            <i class="fas fa-eye text-sm"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-bar">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildReportPageUrl($page - 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            if ($start > 1): ?>
                                <a href="<?= buildReportPageUrl(1, $_GET) ?>" class="page-link">1</a>
                                <?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="<?= buildReportPageUrl($i, $_GET) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
                                <a href="<?= buildReportPageUrl($totalPages, $_GET) ?>" class="page-link"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildReportPageUrl($page + 1, $_GET) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- ========== FORM VIEW ========== -->
                <div class="page-header">
                    <div class="flex items-center gap-3 mb-2">
                        <a href="report_summary.php" class="text-white/80 hover:text-white">
                            <i class="fas fa-arrow-left mr-1"></i> กลับ
                        </a>
                    </div>
                    <h2>📝 สรุปผลการรายงาน</h2>
                    <p>บันทึกมาตรการแก้ไขและการติดตามผล</p>
                </div>

                <div class="info-card">
                    <h3 class="font-semibold text-gray-700 mb-3">📋 ข้อมูลความเสี่ยง</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                        <div><span class="text-gray-500">ประเภท:</span> <span class="font-medium"><?= htmlspecialchars($risk['risk_type'] ?? '-') ?></span></div>
                        <div><span class="text-gray-500">กลุ่มงาน:</span> <span class="font-medium"><?= htmlspecialchars($risk['unit'] ?? '-') ?></span></div>
                        <div><span class="text-gray-500">ระดับ:</span> <span class="font-medium"><?= htmlspecialchars($risk['severity'] ?? '-') ?></span></div>
                        <div><span class="text-gray-500">วันที่:</span> <span class="font-medium"><?= date('d/m/Y', strtotime($risk['event_datetime'])) ?></span></div>
                        <div><span class="text-gray-500">ผู้รายงาน:</span> <span class="font-medium"><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></span></div>
                        <div><span class="text-gray-500">สถานะ:</span> <span class="badge <?= getStatusClass($risk['status'] ?? '') ?>"><?= htmlspecialchars($risk['status'] ?? 'ยังไม่ดำเนินการ') ?></span></div>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="bg-green-50/80 backdrop-blur-sm border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-50/80 backdrop-blur-sm border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="form-card">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="risk_id" value="<?= $risk_id ?>">

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tools"></i> มาตรการแก้ไข</label>
                        <textarea name="corrective_action" class="form-textarea" placeholder="ระบุมาตรการแก้ไขที่ดำเนินการ..." rows="4"><?= htmlspecialchars($existingReport['corrective_action'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-check"></i> ผู้รับผิดชอบ</label>
                        <input type="text" name="responsible_person" class="form-input" placeholder="ระบุชื่อผู้รับผิดชอบ..." value="<?= htmlspecialchars($existingReport['responsible_person'] ?? $_SESSION['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-chart-line"></i> การติดตามผล</label>
                        <textarea name="follow_up" class="form-textarea" placeholder="ระบุผลการติดตาม..." rows="4"><?= htmlspecialchars($existingReport['follow_up'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-bullseye"></i> ผลที่คาดว่าจะได้รับ</label>
                        <textarea name="expected_outcome" class="form-textarea" placeholder="ระบุผลที่คาดว่าจะได้รับ..." rows="4"><?= htmlspecialchars($existingReport['expected_outcome'] ?? '') ?></textarea>
                    </div>

                    <!-- File Upload -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-paperclip"></i> แนบไฟล์สรุปผล</label>

                        <?php if (!empty($existingReport['report_file']) && file_exists($existingReport['report_file'])): ?>
                            <?php
                            $fp = str_replace('\\', '/', $existingReport['report_file']);
                            $fn = basename($fp);
                            $fe = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
                            $img = isImageFile($fp);
                            $fs = filesize($existingReport['report_file']);
                            ?>
                            <div class="file-card">
                                <div class="file-card-header">
                                    <i class="fas fa-paperclip text-blue-500"></i> ไฟล์ปัจจุบัน
                                </div>
                                <?php if ($img): ?>
                                    <div class="file-card-preview">
                                        <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($fn) ?>" class="img-preview-link">
                                            <img src="<?= htmlspecialchars($fp) ?>" alt="<?= htmlspecialchars($fn) ?>" onerror="this.style.display='none';">
                                            <div class="img-overlay"><i class="fas fa-search-plus"></i><span>คลิกเพื่อดู</span></div>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="file-info-row">
                                    <div class="file-info-left">
                                        <div class="file-icon-box <?= $img ? 'bg-green-50' : 'bg-blue-50' ?>">
                                            <i class="fas <?= $img ? 'fa-file-image text-green-600' : getFileIcon($fp) . ' text-blue-600' ?>"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-sm"><?= htmlspecialchars(mb_strlen($fn) > 35 ? mb_substr($fn, 0, 32) . '...' : $fn) ?></div>
                                            <div class="text-xs text-gray-400"><?= strtoupper($fe) ?> · <?= formatFileSize($fs) ?></div>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:0.4rem;">
                                        <a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="btn-sm download" download>
                                            <i class="fas fa-download"></i> ดาวน์โหลด
                                        </a>
                                        <?php if ($img): ?>
                                            <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" class="btn-sm view">
                                                <i class="fas fa-expand"></i> ดูเต็ม
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <label class="upload-area" for="report_file">
                            <input type="file" id="report_file" name="report_file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" onchange="handleFileSelect(this)">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <p class="font-medium text-gray-700">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวาง</p>
                            <p class="text-xs text-gray-400 mt-1">PDF, Word, Excel, รูปภาพ (สูงสุด 10MB)</p>
                            <div id="file-preview-area" class="file-preview-inline" style="display:none;">
                                <i class="fas fa-file text-blue-500"></i>
                                <span id="selected-file-name"></span>
                                <span id="selected-file-size" class="text-gray-400"></span>
                                <button type="button" class="btn-remove" onclick="removeSelectedFile(event)"><i class="fas fa-times"></i></button>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100/50">
                        <a href="report_summary.php" class="btn-cancel"><i class="fas fa-times"></i> ยกเลิก</a>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <?= $existingReport ? 'อัปเดต' : 'บันทึก' ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Fancybox !== 'undefined') {
            var els = document.querySelectorAll('[data-fancybox]');
            if (els.length > 0) {
                Fancybox.bind("[data-fancybox]", {
                    Thumbs: {
                        autoStart: true
                    },
                    Toolbar: {
                        display: ["zoom", "slideshow", "fullscreen", "download", "thumbs", "close"]
                    },
                    Image: {
                        zoom: true,
                        click: "toggleZoom",
                        fit: "contain"
                    }
                });
            }
        }
    });

    function handleFileSelect(input) {
        var pa = document.getElementById('file-preview-area'),
            fn = document.getElementById('selected-file-name'),
            fs = document.getElementById('selected-file-size');
        if (input.files && input.files[0]) {
            var f = input.files[0];
            if (pa) pa.style.display = 'inline-flex';
            if (fn) fn.textContent = f.name;
            if (fs) {
                var s = f.size;
                if (s < 1024) fs.textContent = s + ' B';
                else if (s < 1048576) fs.textContent = (s / 1024).toFixed(1) + ' KB';
                else fs.textContent = (s / 1048576).toFixed(1) + ' MB';
            }
        }
    }

    function removeSelectedFile(e) {
        e.stopPropagation();
        e.preventDefault();
        var fi = document.getElementById('report_file'),
            pa = document.getElementById('file-preview-area');
        if (fi) fi.value = '';
        if (pa) pa.style.display = 'none';
    }

    var ua = document.querySelector('.upload-area');
    if (ua) {
        ua.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.borderColor = '#3b82f6';
            this.style.background = 'rgba(239, 246, 255, 0.5)';
        });
        ua.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.borderColor = 'rgba(203, 213, 225, 0.8)';
            this.style.background = 'rgba(248, 250, 252, 0.5)';
        });
        ua.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.borderColor = 'rgba(203, 213, 225, 0.8)';
            this.style.background = 'rgba(248, 250, 252, 0.5)';
            var files = e.dataTransfer.files,
                fi = document.getElementById('report_file');
            if (files.length > 0 && fi) {
                var dt = new DataTransfer();
                dt.items.add(files[0]);
                fi.files = dt.files;
                handleFileSelect(fi);
            }
        });
    }
</script>
<?php include 'includes/footer.php'; ?>