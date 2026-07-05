<?php

/**
 * หน้าสรุปผลการรายงาน - UI สวยงาม ทันสมัย พร้อม Animation
 * - User: เห็นเฉพาะรายการของตัวเอง / Admin: เห็นทั้งหมด
 * - แสดงเฉพาะสถานะ "ดำเนินการแล้ว" เท่านั้น
 * - บันทึก: มาตรการแก้ไข, ผู้รับผิดชอบ, การติดตามผล, ผลที่คาดว่าจะได้รับ
 * - แนบไฟล์สรุปผลได้ + Lightbox
 * - Pagination 10 รายการ/หน้า
 * - ผู้รับผิดชอบแสดงชื่อผู้ใช้ปัจจุบันเป็นค่าเริ่มต้น
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือน
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

$success = '';
$error = '';

$risk_id = isset($_GET['risk_id']) ? (int)$_GET['risk_id'] : 0;

$search = trim($_GET['search'] ?? '');
$unit_filter = $_GET['unit'] ?? '';
$type_filter = $_GET['risk_type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$risk = null;
if ($risk_id) {
    $stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.status = 'ดำเนินการแล้ว'");
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
                    $success = 'อัปเดตสรุปผลการรายงานเรียบร้อยแล้ว';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO risk_reports (risk_id, corrective_action, responsible_person, follow_up, expected_outcome, report_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$risk_id, $corrective_action, $responsible_person, $follow_up, $expected_outcome, $uploaded_file, $_SESSION['user_id']]);
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

$filterUnits = $pdo->query("SELECT DISTINCT unit FROM risks WHERE unit IS NOT NULL AND unit != '' AND status = 'ดำเนินการแล้ว' ORDER BY unit")->fetchAll(PDO::FETCH_COLUMN);
$filterTypes = $pdo->query("SELECT DISTINCT risk_type FROM risks WHERE status = 'ดำเนินการแล้ว' ORDER BY risk_type")->fetchAll(PDO::FETCH_COLUMN);
$filterSeverities = $pdo->query("SELECT DISTINCT severity FROM risks WHERE status = 'ดำเนินการแล้ว' ORDER BY severity")->fetchAll(PDO::FETCH_COLUMN);

if ($risk_id === 0) {
    $whereClause = "WHERE r.status = 'ดำเนินการแล้ว'";
    $countParams = [];
    $queryParams = [];
    
    if (!isAdmin()) {
        $whereClause .= " AND r.user_id = ?";
        $countParams[] = $_SESSION['user_id'];
        $queryParams[] = $_SESSION['user_id'];
    }
    if ($search !== '') {
        $whereClause .= " AND (r.risk_type LIKE ? OR r.risk_detail LIKE ? OR r.unit LIKE ? OR r.risk_type_other LIKE ?)";
        $searchTerm = "%{$search}%";
        for ($i = 0; $i < 4; $i++) {
            $countParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }
    }
    if ($unit_filter !== '') {
        $whereClause .= " AND r.unit = ?";
        $countParams[] = $unit_filter;
        $queryParams[] = $unit_filter;
    }
    if ($type_filter !== '') {
        $whereClause .= " AND r.risk_type = ?";
        $countParams[] = $type_filter;
        $queryParams[] = $type_filter;
    }
    if ($severity_filter !== '') {
        $whereClause .= " AND r.severity = ?";
        $countParams[] = $severity_filter;
        $queryParams[] = $severity_filter;
    }
    if ($date_from !== '') {
        $whereClause .= " AND DATE(r.event_datetime) >= ?";
        $countParams[] = $date_from;
        $queryParams[] = $date_from;
    }
    if ($date_to !== '') {
        $whereClause .= " AND DATE(r.event_datetime) <= ?";
        $countParams[] = $date_to;
        $queryParams[] = $date_to;
    }

    $countSql = "SELECT COUNT(*) FROM risks r {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRows = $countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $perPage);

    $dataSql = "SELECT r.*, u.username, rr.id as report_id FROM risks r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN risk_reports rr ON r.id = rr.risk_id {$whereClause} ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($queryParams);
    $allRisks = $stmt->fetchAll();
}

function getSeverityBadgeClass($severity)
{
    $map = [
        'A' => 'bg-blue-50 text-blue-700 border-blue-200',
        'B' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'C' => 'bg-lime-50 text-lime-700 border-lime-200',
        'D' => 'bg-amber-50 text-amber-700 border-amber-200',
        'E' => 'bg-red-50 text-red-700 border-red-200',
        'F' => 'bg-orange-50 text-orange-700 border-orange-200'
    ];
    return $map[$severity] ?? 'bg-slate-100 text-slate-600 border-slate-200';
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
    $icons = [
        'pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image',
        'gif' => 'fa-file-image', 'webp' => 'fa-file-image'
    ];
    return $icons[$ext] ?? 'fa-file';
}

function formatFileSize($bytes)
{
    if ($bytes === false || $bytes === null) return 'ไม่ทราบขนาด';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
}

function buildReportPageUrl($page, $currentParams)
{
    $query = $currentParams;
    $query['page'] = $page;
    return 'report_summary.php?' . http_build_query($query);
}

$csrf_token = generateCsrfToken();
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
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

    .page-header .back-link {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-bottom: 0.5rem;
    }

    .page-header .back-link:hover {
        color: white;
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
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
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

    /* Form Styles */
    .form-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.3rem;
        display: block;
    }

    .form-textarea {
        width: 100%;
        min-height: 110px;
        padding: 0.55rem 0.7rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.5rem;
        background: #fafbfc;
        color: #334155;
        font-size: 0.85rem;
        resize: vertical;
        transition: all 0.2s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        line-height: 1.6;
    }

    .form-textarea:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
    }

    .form-input {
        width: 100%;
        padding: 0.55rem 0.7rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.5rem;
        background: #fafbfc;
        color: #334155;
        font-size: 0.85rem;
        transition: all 0.2s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
    }

    .form-input:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
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

    .btn-action.blue {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .btn-action.blue:hover {
        background: #dbeafe;
    }

    .btn-action.gray {
        background: #f1f5f9;
        color: #64748b;
        border-color: #e2e8f0;
    }

    .btn-action.gray:hover {
        background: #e2e8f0;
    }

    .info-detail-card {
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .info-detail-card h3 {
        font-size: 0.95rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* File Upload */
    .file-card {
        background: white;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem;
        overflow: hidden;
        margin-bottom: 0.75rem;
    }

    .file-card-header {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.6rem 1rem;
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
    }

    .file-card-preview {
        background: #f8fafc;
        padding: 1rem;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
    }

    .img-preview-link {
        display: inline-block;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
    }

    .img-preview-link:hover {
        border-color: #3b82f6;
    }

    .img-preview-link img {
        display: block;
        max-width: 100%;
        max-height: 180px;
        object-fit: contain;
    }

    .file-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .file-info-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .file-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .btn-sm {
        padding: 0.4rem 0.7rem;
        border-radius: 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        border: 1px solid;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .btn-sm.download {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .btn-sm.download:hover {
        background: #dbeafe;
    }

    .btn-sm.view {
        background: #f0fdf4;
        color: #16a34a;
        border-color: #bbf7d0;
    }

    .btn-sm.view:hover {
        background: #dcfce7;
    }

    .upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 0.75rem;
        padding: 2rem;
        text-align: center;
        background: #fafbfc;
        cursor: pointer;
        transition: all 0.2s;
        display: block;
    }

    .upload-area:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .upload-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #eff6ff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem;
    }

    .upload-icon i {
        font-size: 1.2rem;
        color: #3b82f6;
    }

    .file-preview-inline {
        margin-top: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.7rem;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
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
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-remove:hover {
        background: #fee2e2;
    }

    .hidden {
        display: none !important;
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

            <?php if (!$risk_id): ?>
                <!-- ========== LIST VIEW ========== -->
                <div class="page-header">
                    <h1>✅ สรุปผลการรายงาน</h1>
                    <p>รายการความเสี่ยงที่ดำเนินการเสร็จสิ้นแล้ว · ทั้งหมด <strong><?= number_format($totalRows) ?></strong> รายการ</p>
                </div>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-badge"><i class="fas fa-check-circle text-emerald-500"></i> ดำเนินการแล้ว <strong><?= number_format($totalRows) ?></strong> รายการ</div>
                    <div class="stat-badge"><i class="fas fa-file-alt"></i> หน้า <strong><?= $page ?></strong> / <?= max(1, $totalPages) ?></div>
                    <?php if ($search || $unit_filter || $type_filter || $severity_filter || $date_from || $date_to): ?>
                        <div class="stat-badge"><i class="fas fa-filter text-blue-500"></i> กรอง <strong><?= number_format($totalRows) ?></strong> รายการ</div>
                    <?php endif; ?>
                </div>

                <!-- Filter -->
                <div class="filter-card">
                    <form method="GET" id="filterForm" action="report_summary.php">
                        <div class="filter-grid">
                            <div class="search-box filter-group">
                                <label class="filter-label">🔍 ค้นหา</label>
                                <div style="position:relative;">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" class="filter-input" placeholder="ประเภท, กลุ่มงาน, รายละเอียด..." style="width:100%;">
                                    <?php if ($search): ?>
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['search' => ''])) ?>" class="search-clear"><i class="fas fa-times"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">🏢 กลุ่มงาน</label>
                                <select name="unit" class="filter-input auto-submit">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($filterUnits as $u): ?>
                                        <option value="<?= htmlspecialchars($u) ?>" <?= $unit_filter == $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">🏷️ ประเภท</label>
                                <select name="risk_type" class="filter-input auto-submit">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($filterTypes as $t): ?>
                                        <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">⚠️ ระดับ</label>
                                <select name="severity" class="filter-input auto-submit">
                                    <option value="">ทั้งหมด</option>
                                    <?php foreach ($filterSeverities as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= $severity_filter == $s ? 'selected' : '' ?>>ระดับ <?= htmlspecialchars($s) ?></option>
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
                        <?php if ($search || $unit_filter || $type_filter || $severity_filter || $date_from || $date_to): ?>
                            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.75rem;">
                                <?php if ($search): ?>
                                    <span class="filter-badge">🔍 "<?= htmlspecialchars($search) ?>"
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['search' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($unit_filter): ?>
                                    <span class="filter-badge">🏢 <?= htmlspecialchars($unit_filter) ?>
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['unit' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($type_filter): ?>
                                    <span class="filter-badge">🏷️ <?= htmlspecialchars($type_filter) ?>
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['risk_type' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($severity_filter): ?>
                                    <span class="filter-badge">⚠️ <?= htmlspecialchars($severity_filter) ?>
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['severity' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($date_from): ?>
                                    <span class="filter-badge">📅 ตั้งแต่ <?= htmlspecialchars($date_from) ?>
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['date_from' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($date_to): ?>
                                    <span class="filter-badge">📅 ถึง <?= htmlspecialchars($date_to) ?>
                                        <a href="<?= buildReportPageUrl(1, array_merge($_GET, ['date_to' => ''])) ?>" class="remove"><i class="fas fa-times"></i></a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="filter-actions">
                            <span style="font-size:0.8rem;color:#94a3b8;">
                                <?php if ($search || $unit_filter || $type_filter || $severity_filter || $date_from || $date_to): ?>
                                    <i class="fas fa-check-circle text-green-500 mr-1"></i> พบ <?= number_format($totalRows) ?> รายการ
                                <?php else: ?>
                                    <i class="fas fa-database mr-1"></i> แสดงทั้งหมด <?= number_format($totalRows) ?> รายการ
                                <?php endif; ?>
                            </span>
                            <div style="display:flex;gap:0.5rem;">
                                <?php if ($search || $unit_filter || $type_filter || $severity_filter || $date_from || $date_to): ?>
                                    <a href="report_summary.php" class="btn-filter danger"><i class="fas fa-times"></i> ล้างทั้งหมด</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <?php if (empty($allRisks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">ไม่พบรายการที่ดำเนินการแล้ว</h3>
                        <p class="text-gray-400"><?= ($search || $unit_filter || $type_filter || $severity_filter || $date_from || $date_to) ? 'ไม่มีข้อมูลตรงตามเงื่อนไข' : 'ยังไม่มีรายการที่ดำเนินการเสร็จสิ้น' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-header-bar">
                            <span class="font-semibold text-gray-700"><i class="fas fa-list text-blue-600 mr-1"></i> รายการที่ดำเนินการแล้ว</span>
                            <span class="text-xs text-gray-500"><?= count($allRisks) ?> / <?= number_format($totalRows) ?> รายการ · หน้า <?= $page ?>/<?= max(1, $totalPages) ?></span>
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
                                        <th style="width:100px;text-align:center;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allRisks as $index => $item):
                                        $rowNum = ($page - 1) * $perPage + $index + 1;
                                        $sevClass = getSeverityBadgeClass($item['severity']);
                                    ?>
                                        <tr style="cursor:pointer;" onclick="window.location='?risk_id=<?= $item['id'] ?>'">
                                            <td class="text-gray-400"><?= $rowNum ?></td>
                                            <td>
                                                <span class="font-medium"><?= htmlspecialchars(mb_substr($item['risk_type'] ?? '-', 0, 25)) ?></span>
                                                <?php if (!empty($item['risk_type_other'])): ?>
                                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['risk_type_other']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-gray-500"><?= htmlspecialchars(mb_substr($item['unit'] ?? '-', 0, 20)) ?></td>
                                            <td>
                                                <span class="badge <?= $sevClass ?>">
                                                    <i class="fas fa-flag text-xs"></i> <?= htmlspecialchars($item['severity'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="text-gray-500 text-sm"><?= date('d/m/Y', strtotime($item['event_datetime'])) ?></td>
                                            <td class="text-gray-500"><?= htmlspecialchars($item['username'] ?? 'ไม่ระบุ') ?></td>
                                            <td>
                                                <span class="badge bg-emerald-50 text-emerald-700 border-emerald-200">
                                                    <i class="fas fa-check-circle text-xs"></i> ดำเนินการแล้ว
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($item['report_id']): ?>
                                                    <span class="badge bg-emerald-50 text-emerald-700 border-emerald-200">
                                                        <i class="fas fa-check-circle text-xs"></i> มีรายงาน
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-amber-50 text-amber-700 border-amber-200">
                                                        <i class="fas fa-clock text-xs"></i> รอรายงาน
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td onclick="event.stopPropagation();">
                                                <div style="display:flex;gap:3px;justify-content:center;">
                                                    <a href="?risk_id=<?= $item['id'] ?>" class="btn-icon bg-blue-50 text-blue-600 hover:bg-blue-100" title="<?= $item['report_id'] ? 'แก้ไขรายงาน' : 'เพิ่มรายงาน' ?>">
                                                        <i class="fas fa-<?= $item['report_id'] ? 'edit' : 'plus' ?> text-sm"></i>
                                                    </a>
                                                    <?php if ($item['report_id']): ?>
                                                        <a href="?risk_id=<?= $item['id'] ?>" class="btn-icon bg-green-50 text-green-600 hover:bg-green-100" title="ดูรายงาน">
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

                    <!-- Pagination -->
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
                    <div style="margin-bottom: 0.5rem;">
                        <a href="report_summary.php" class="back-link">
                            <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                        </a>
                    </div>
                    <h2>📝 สรุปผลการรายงาน</h2>
                    <p>บันทึกมาตรการแก้ไขและการติดตามผล</p>
                </div>

                <!-- Info Detail Card -->
                <div class="info-detail-card">
                    <h3><i class="fas fa-info-circle text-blue-600"></i> ข้อมูลความเสี่ยง</h3>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; font-size: 0.85rem;">
                        <div><span style="color: #94a3b8;">ประเภท:</span> <span style="font-weight: 600;"><?= htmlspecialchars($risk['risk_type'] ?? '-') ?></span></div>
                        <div><span style="color: #94a3b8;">กลุ่มงาน:</span> <span style="font-weight: 600;"><?= htmlspecialchars($risk['unit'] ?? '-') ?></span></div>
                        <div><span style="color: #94a3b8;">ระดับ:</span> <span style="font-weight: 600;"><?= htmlspecialchars($risk['severity'] ?? '-') ?></span></div>
                        <div><span style="color: #94a3b8;">วันที่:</span> <span style="font-weight: 600;"><?= date('d/m/Y', strtotime($risk['event_datetime'])) ?></span></div>
                        <div><span style="color: #94a3b8;">ผู้รายงาน:</span> <span style="font-weight: 600;"><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></span></div>
                        <div><span style="color: #94a3b8;">สถานะ:</span> <span class="badge bg-emerald-50 text-emerald-700 border-emerald-200"><i class="fas fa-check-circle text-xs"></i> <?= htmlspecialchars($risk['status'] ?? 'ดำเนินการแล้ว') ?></span></div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div style="padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div style="padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" class="form-card">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="risk_id" value="<?= $risk_id ?>">

                    <div class="form-group">
                        <label class="form-label">มาตรการแก้ไข</label>
                        <textarea name="corrective_action" class="form-textarea" placeholder="ระบุมาตรการแก้ไขที่ดำเนินการ..." rows="4"><?= htmlspecialchars($existingReport['corrective_action'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ผู้รับผิดชอบ</label>
                        <input type="text" name="responsible_person" class="form-input" placeholder="ระบุชื่อผู้รับผิดชอบ..." value="<?= htmlspecialchars($existingReport['responsible_person'] ?? $_SESSION['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">การติดตามผล</label>
                        <textarea name="follow_up" class="form-textarea" placeholder="ระบุผลการติดตาม..." rows="4"><?= htmlspecialchars($existingReport['follow_up'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ผลที่คาดว่าจะได้รับ</label>
                        <textarea name="expected_outcome" class="form-textarea" placeholder="ระบุผลที่คาดว่าจะได้รับ..." rows="4"><?= htmlspecialchars($existingReport['expected_outcome'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">แนบไฟล์สรุปผล</label>

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
                                    <i class="fas fa-paperclip text-blue-600"></i> ไฟล์ปัจจุบัน
                                </div>
                                <?php if ($img): ?>
                                    <div class="file-card-preview">
                                        <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($fn) ?>" class="img-preview-link">
                                            <img src="<?= htmlspecialchars($fp) ?>" alt="<?= htmlspecialchars($fn) ?>" onerror="this.style.display='none';">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="file-info-row">
                                    <div class="file-info-left">
                                        <div class="file-icon-box" style="<?= $img ? 'background: #f0fdf4;' : 'background: #eff6ff;' ?>">
                                            <i class="fas <?= $img ? 'fa-file-image' : getFileIcon($fp) ?>" style="<?= $img ? 'color: #16a34a;' : 'color: #3b82f6;' ?>"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars(mb_strlen($fn) > 30 ? mb_substr($fn, 0, 27) . '...' : $fn) ?></div>
                                            <div style="font-size: 0.75rem; color: #94a3b8;"><?= strtoupper($fe) ?> · <?= formatFileSize($fs) ?></div>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:0.4rem;">
                                        <a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="btn-sm download" download>
                                            <i class="fas fa-download"></i> ดาวน์โหลด
                                        </a>
                                        <?php if ($img): ?>
                                            <a href="<?= htmlspecialchars($fp) ?>" data-fancybox="gallery" class="btn-sm view">
                                                <i class="fas fa-expand"></i> ดู
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <label class="upload-area" for="report_file">
                            <input type="file" id="report_file" name="report_file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" onchange="handleFileSelect(this)">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <p style="font-weight: 600; color: #475569; font-size: 0.9rem;">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวาง</p>
                            <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">PDF, Word, Excel, รูปภาพ (สูงสุด 10MB)</p>
                            <div id="file-preview-area" class="file-preview-inline" style="display:none;">
                                <i class="fas fa-file text-blue-600"></i>
                                <span id="selected-file-name"></span>
                                <span id="selected-file-size" style="color: #94a3b8;"></span>
                                <button type="button" class="btn-remove" onclick="removeSelectedFile(event)"><i class="fas fa-times"></i></button>
                            </div>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9;">
                        <a href="report_summary.php" class="btn-action gray"><i class="fas fa-times"></i> ยกเลิก</a>
                        <button type="submit" class="btn-action blue"><i class="fas fa-save"></i> <?= $existingReport ? 'อัปเดต' : 'บันทึก' ?></button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Info -->
            <div class="info-card">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-500 text-lg mt-0.5"></i>
                    <div>
                        <p class="font-semibold mb-1">📌 หมายเหตุ</p>
                        <ul class="list-disc ml-4 space-y-0.5 text-sm">
                            <li>แสดงเฉพาะรายการที่มีสถานะ <strong>"ดำเนินการแล้ว"</strong> เท่านั้น</li>
                            <li>สามารถบันทึกมาตรการแก้ไข ผู้รับผิดชอบ และการติดตามผลได้</li>
                            <li>รองรับการแนบไฟล์สรุปผล (PDF, Word, Excel, รูปภาพ)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    // ========== Fancybox ==========
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind("[data-fancybox]", {
                Thumbs: { autoStart: true },
                Toolbar: { display: ["zoom", "slideshow", "fullscreen", "download", "thumbs", "close"] }
            });
        }
    });

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

    // ========== File Upload ==========
    function handleFileSelect(input) {
        const pa = document.getElementById('file-preview-area');
        const fn = document.getElementById('selected-file-name');
        const fs = document.getElementById('selected-file-size');
        
        if (input.files && input.files[0]) {
            const f = input.files[0];
            if (pa) pa.style.display = 'inline-flex';
            if (fn) fn.textContent = f.name;
            if (fs) {
                let s = f.size;
                if (s < 1024) fs.textContent = ' (' + s + ' B)';
                else if (s < 1048576) fs.textContent = ' (' + (s / 1024).toFixed(1) + ' KB)';
                else fs.textContent = ' (' + (s / 1048576).toFixed(1) + ' MB)';
            }
        }
    }

    function removeSelectedFile(e) {
        e.stopPropagation();
        e.preventDefault();
        const fi = document.getElementById('report_file');
        const pa = document.getElementById('file-preview-area');
        if (fi) fi.value = '';
        if (pa) pa.style.display = 'none';
    }

    // ========== Drag & Drop ==========
    const uploadArea = document.querySelector('.upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.borderColor = '#3b82f6';
            this.style.background = '#eff6ff';
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.borderColor = '#cbd5e1';
            this.style.background = '#fafbfc';
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.borderColor = '#cbd5e1';
            this.style.background = '#fafbfc';
            
            const files = e.dataTransfer.files;
            const fi = document.getElementById('report_file');
            if (files.length > 0 && fi) {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fi.files = dt.files;
                handleFileSelect(fi);
            }
        });
    }

    // ========== Form Submit ==========
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
                submitBtn.style.pointerEvents = 'none';
                submitBtn.style.opacity = '0.8';
            }
        });
    }
</script>
<?php include 'includes/footer.php'; ?>