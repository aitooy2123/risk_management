<?php

/**
 * หน้าโปรไฟล์ผู้ใช้ - UI สวยงาม ทันสมัย
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) redirect('index.php');

$hasEmail = false;
$hasPhone = false;
$hasDepartment = false;
try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $hasEmail = in_array('email', $columns);
    $hasPhone = in_array('phone', $columns);
    $hasDepartment = in_array('department', $columns);
} catch (Exception $e) {
}

// รายการแผนกทั้งหมด
$departmentsList = [
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request';
        } else {
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');

            // ตรวจสอบว่าถ้าเลือก "อื่นๆ" ให้ใช้ค่าจาก department_other
            if ($department === 'อื่นๆ' && !empty($_POST['department_other'])) {
                $department = trim($_POST['department_other']);
            }

            $updateFields = ["fullname = ?"];
            $updateParams = [$fullname];

            if ($hasEmail) {
                $updateFields[] = "email = ?";
                $updateParams[] = $email;
            }
            if ($hasPhone) {
                $updateFields[] = "phone = ?";
                $updateParams[] = $phone;
            }
            if ($hasDepartment) {
                $updateFields[] = "department = ?";
                $updateParams[] = $department;
            }
            $updateParams[] = $_SESSION['user_id'];

            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
            $success = 'อัปเดตโปรไฟล์เรียบร้อยแล้ว';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        }
    }

    if ($action === 'update_avatar') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request';
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $error = 'รองรับเฉพาะ JPG, PNG, GIF, WebP';
            } elseif ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                $error = 'ขนาดไฟล์ต้องไม่เกิน 5MB';
            } else {
                $file_name = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $file_name)) {
                    $old = $user['avatar'] ?? '';
                    if ($old && $old != 'default.png' && file_exists($upload_dir . $old)) {
                        unlink($upload_dir . $old);
                    }
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$file_name, $_SESSION['user_id']]);
                    $_SESSION['avatar'] = $file_name;
                    $success = 'อัปเดตรูปโปรไฟล์เรียบร้อยแล้ว';

                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                } else {
                    $error = 'ไม่สามารถอัปโหลดไฟล์ได้';
                }
            }
        }
    }
}

// สถิติ
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN status='ดำเนินการแล้ว' THEN 1 ELSE 0 END) as completed, 
            SUM(CASE WHEN status='กำลังดำเนินการ' THEN 1 ELSE 0 END) as in_progress, 
            SUM(CASE WHEN status='ยังไม่ดำเนินการ' OR status IS NULL OR status='' THEN 1 ELSE 0 END) as pending 
        FROM risks WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'pending' => 0];
}

// ความเสี่ยงล่าสุด
try {
    $stmt = $pdo->prepare("SELECT * FROM risks WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recentRisks = $stmt->fetchAll();
} catch (Exception $e) {
    $recentRisks = [];
}

$csrf_token = generateCsrfToken();

// ฟังก์ชันช่วยเหลือ
function getStatusStyle($status)
{
    if ($status == 'ดำเนินการแล้ว') {
        return ['dot' => '#22c55e', 'bg' => '#f0fdf4', 'color' => '#166534'];
    } elseif ($status == 'กำลังดำเนินการ') {
        return ['dot' => '#3b82f6', 'bg' => '#eff6ff', 'color' => '#1e40af'];
    } elseif ($status == 'ยุติ') {
        return ['dot' => '#94a3b8', 'bg' => '#f1f5f9', 'color' => '#64748b'];
    } else {
        return ['dot' => '#eab308', 'bg' => '#fefce8', 'color' => '#854d0e'];
    }
}
?>
<?php include 'includes/header.php'; ?>

<style>
    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
    }

    .page-container {
        max-width: 1000px;
        margin: 0 auto;
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
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .page-header h1 {
        position: relative;
        z-index: 1;
    }

    .page-header p {
        position: relative;
        z-index: 1;
        color: rgba(255, 255, 255, 0.7);
    }

    /* Card */
    .card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        margin-bottom: 1.5rem;
        transition: all 0.3s;
    }

    .card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        background: #fafbfc;
    }

    .card-header-icon {
        width: 34px;
        height: 34px;
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
        padding: 1.5rem;
    }

    .card-body.no-pad {
        padding: 0;
    }

    /* Profile Top */
    .profile-top {
        display: flex;
        align-items: center;
        gap: 2rem;
        padding: 2rem;
    }

    .avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .avatar-ring {
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid transparent;
        border-top-color: #3b82f6;
        border-right-color: #60a5fa;
        animation: spin 3s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .avatar-img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 2;
        transition: transform 0.3s;
    }

    .avatar-img:hover {
        transform: scale(1.05);
    }

    .avatar-overlay {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;
        z-index: 3;
        cursor: pointer;
    }

    .avatar-wrap:hover .avatar-overlay {
        opacity: 1;
    }

    .avatar-overlay i {
        color: white;
        font-size: 1.3rem;
    }

    .profile-info {
        flex: 1;
    }

    .profile-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1e293b;
    }

    .profile-role {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.7rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 0.35rem;
    }

    .profile-role.admin {
        background: #fef3c7;
        color: #92400e;
    }

    .profile-role.user {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-top: 1px solid #f1f5f9;
    }

    .stat-item {
        padding: 1.25rem 0.75rem;
        text-align: center;
        position: relative;
        transition: all 0.2s;
    }

    .stat-item:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 20%;
        height: 60%;
        width: 1px;
        background: #f1f5f9;
    }

    .stat-item:hover {
        background: #f8fafc;
    }

    .stat-icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.6rem;
        font-size: 1.1rem;
        transition: all 0.2s;
    }

    .stat-item:hover .stat-icon-circle {
        transform: scale(1.1);
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }

    .stat-label-text {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.15rem;
    }

    /* Form */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-grid .full {
        grid-column: 1 / -1;
    }

    .form-group {
        margin-bottom: 0.5rem;
    }

    .form-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.3rem;
    }

    .form-input {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        font-size: 0.88rem;
        transition: all 0.25s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #fafbfc;
        color: #1e293b;
    }

    .form-input:focus {
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
    }

    .form-input:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        border-style: dashed;
    }

    select.form-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.9rem center;
        padding-right: 2.5rem;
    }

    .form-hint {
        font-size: 0.65rem;
        color: #94a3b8;
        margin-top: 0.2rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.65rem 1.25rem;
        border-radius: 0.6rem;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        font-family: 'Sarabun', sans-serif;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1e40af, #3b82f6);
        color: white;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    }

    .btn-outline {
        background: white;
        color: #3b82f6;
        border: 1.5px solid #bfdbfe;
    }

    .btn-outline:hover {
        background: #eff6ff;
    }

    .btn-sm {
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
    }

    /* Recent List */
    .recent-list {
        list-style: none;
    }

    .recent-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.85rem 1.5rem;
        border-bottom: 1px solid #f8fafc;
        transition: all 0.15s;
        gap: 1rem;
    }

    .recent-item:last-child {
        border-bottom: none;
    }

    .recent-item:hover {
        background: #fafbfc;
    }

    .recent-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        flex: 1;
    }

    .recent-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .recent-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .recent-meta {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .status-badge {
        padding: 0.15rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.65rem;
        font-weight: 600;
        white-space: nowrap;
    }

    /* Alert */
    .alert {
        padding: 0.8rem 1.25rem;
        border-radius: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
    }

    .alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
        opacity: 0.4;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding-bottom: 0.6rem;
        border-bottom: 1px solid #f8fafc;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #94a3b8;
        font-size: 0.82rem;
    }

    .info-value {
        font-weight: 600;
    }

    .dept-other-input {
        margin-top: 0.5rem;
        display: none;
    }

    .dept-other-input.visible {
        display: block;
    }

    @media (max-width: 768px) {
        .profile-top {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }

        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .avatar-img {
            width: 80px;
            height: 80px;
        }

        .page-container {
            padding: 0 0.5rem;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 overflow-y-auto">
        <div class="p-4 md:p-5">
            <div class="page-container">

                <!-- Header -->
                <div class="page-header">
                    <h1 class="text-xl font-bold"><i class="fas fa-user-circle mr-2"></i>โปรไฟล์ผู้ใช้</h1>
                    <p class="text-sm mt-1">จัดการข้อมูลส่วนตัวของคุณ</p>
                </div>

                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Main Profile Card -->
                <div class="card">
                    <div class="profile-top">
                        <div class="avatar-wrap">
                            <div class="avatar-ring"></div>
                            <img src="avatars/<?= htmlspecialchars($user['avatar'] ?: 'default.png') ?>"
                                alt="Avatar" class="avatar-img"
                                onerror="this.src='avatars/default.png'">
                            <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="update_avatar">
                                <input type="file" id="avatar-input" name="avatar" accept="image/*">
                            </form>
                            <label class="avatar-overlay" for="avatar-input">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <div class="profile-info">
                            <h2 class="profile-name"><?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></h2>
                            <p class="text-gray-500 text-sm">
                                @<?= htmlspecialchars($user['username']) ?> ·
                                <?= htmlspecialchars($user['reporter_code'] ?? '-') ?>
                            </p>
                            <span class="profile-role <?= isAdmin() ? 'admin' : 'user' ?>">
                                <i class="fas <?= isAdmin() ? 'fa-crown' : 'fa-user' ?> text-xs"></i>
                                <?= isAdmin() ? 'ผู้ดูแลระบบ' : 'ผู้ใช้ทั่วไป' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-icon-circle bg-blue-50 text-blue-600">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="stat-number"><?= number_format($stats['total']) ?></div>
                            <div class="stat-label-text">ทั้งหมด</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon-circle bg-yellow-50 text-yellow-600">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number"><?= number_format($stats['pending']) ?></div>
                            <div class="stat-label-text">รอดำเนินการ</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon-circle bg-blue-50 text-blue-600">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <div class="stat-number"><?= number_format($stats['in_progress']) ?></div>
                            <div class="stat-label-text">กำลังดำเนินการ</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon-circle bg-green-50 text-green-600">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?= number_format($stats['completed']) ?></div>
                            <div class="stat-label-text">ดำเนินการแล้ว</div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;">
                    <!-- ข้อมูลส่วนตัว -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon bg-blue-50 text-blue-600">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <h3 class="card-header-title">ข้อมูลส่วนตัว</h3>
                        </div>
                        <div class="card-body">
                            <div style="display:flex;flex-direction:column;gap:0.85rem;">
                                <div class="info-row">
                                    <span class="info-label">ชื่อผู้ใช้</span>
                                    <span class="info-value"><?= htmlspecialchars($user['username']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">ชื่อ-นามสกุล</span>
                                    <span class="info-value"><?= htmlspecialchars($user['fullname'] ?: '-') ?></span>
                                </div>
                                <?php if ($hasEmail): ?>
                                    <div class="info-row">
                                        <span class="info-label">อีเมล</span>
                                        <span class="info-value"><?= htmlspecialchars($user['email'] ?: '-') ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasPhone && !empty($user['phone'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">เบอร์โทร</span>
                                        <span class="info-value"><?= htmlspecialchars($user['phone']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasDepartment && !empty($user['department'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">แผนก</span>
                                        <span class="info-value"><?= htmlspecialchars($user['department']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">สมาชิกตั้งแต่</span>
                                    <span class="info-value"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- แก้ไขข้อมูล -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon bg-indigo-50 text-indigo-600">
                                <i class="fas fa-edit"></i>
                            </div>
                            <h3 class="card-header-title">แก้ไขข้อมูล</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">ชื่อผู้ใช้</label>
                                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" class="form-input" disabled>
                                        <p class="form-hint">🔒 เปลี่ยนไม่ได้</p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ชื่อ-นามสกุล</label>
                                        <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" class="form-input" placeholder="กรอกชื่อ-นามสกุล">
                                    </div>

                                    <?php if ($hasEmail): ?>
                                        <div class="form-group">
                                            <label class="form-label">อีเมล</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-input" placeholder="example@email.com">
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasPhone): ?>
                                        <div class="form-group">
                                            <label class="form-label">เบอร์โทรศัพท์</label>
                                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-input" placeholder="08x-xxx-xxxx">
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasDepartment): ?>
                                        <div class="form-group full">
                                            <label class="form-label">แผนก/หน่วยงาน</label>
                                            <select name="department" id="departmentSelect" class="form-input">
                                                <option value="">-- เลือกแผนก/หน่วยงาน --</option>
                                                <?php foreach ($departmentsList as $dept):
                                                    $selected = ($user['department'] ?? '') == $dept ? 'selected' : '';
                                                ?>
                                                    <option value="<?= htmlspecialchars($dept) ?>" <?= $selected ?>>
                                                        <?= htmlspecialchars($dept) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="อื่นๆ"
                                                    <?= !empty($user['department']) && !in_array($user['department'], $departmentsList) ? 'selected' : '' ?>>
                                                    ➕ อื่นๆ (ระบุ)
                                                </option>
                                            </select>
                                            <input type="text" name="department_other" id="deptOther"
                                                class="form-input dept-other-input <?= !empty($user['department']) && !in_array($user['department'], $departmentsList) ? 'visible' : '' ?>"
                                                placeholder="ระบุแผนก/หน่วยงานอื่น"
                                                value="<?= !empty($user['department']) && !in_array($user['department'], $departmentsList) ? htmlspecialchars($user['department']) : '' ?>">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div style="display:flex;justify-content:flex-end;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f8fafc;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> บันทึกข้อมูล
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Recent Risks -->
                <div class="card" style="margin-top:1.5rem;">
                    <div class="card-header">
                        <div class="card-header-icon bg-emerald-50 text-emerald-600">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="card-header-title">ความเสี่ยงล่าสุดของคุณ</h3>
                        <?php if (!empty($recentRisks)): ?>
                            <a href="risks.php" class="btn btn-outline btn-sm" style="margin-left:auto;">
                                ดูทั้งหมด <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($recentRisks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>ยังไม่มีรายการความเสี่ยง</p>
                            <a href="risk_form.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;">
                                <i class="fas fa-plus"></i> เพิ่มรายการแรก
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="card-body no-pad">
                            <ul class="recent-list">
                                <?php foreach ($recentRisks as $risk):
                                    $status = $risk['status'] ?? 'ยังไม่ดำเนินการ';
                                    $style = getStatusStyle($status);
                                ?>
                                    <li class="recent-item">
                                        <div class="recent-left">
                                            <div class="recent-dot" style="background:<?= $style['dot'] ?>;"></div>
                                            <div>
                                                <div class="recent-title"><?= htmlspecialchars($risk['risk_type'] ?? '-') ?></div>
                                                <div class="recent-meta"><?= htmlspecialchars($risk['unit'] ?? '-') ?></div>
                                            </div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:0.6rem;">
                                            <span style="font-size:0.75rem;color:#94a3b8;">
                                                <?= date('d/m/Y', strtotime($risk['created_at'])) ?>
                                            </span>
                                            <span class="status-badge" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>;">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Avatar upload
    document.getElementById('avatar-input')?.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            if (this.files[0].size > 5 * 1024 * 1024) {
                alert('ขนาดไฟล์ต้องไม่เกิน 5MB');
                this.value = '';
                return;
            }
            document.getElementById('avatarForm').submit();
        }
    });

    // Toggle other department
    function toggleOtherDept() {
        const select = document.getElementById('departmentSelect');
        const other = document.getElementById('deptOther');
        if (select && other) {
            if (select.value === 'อื่นๆ') {
                other.classList.add('visible');
                other.focus();
                other.required = true;
            } else {
                other.classList.remove('visible');
                other.value = '';
                other.required = false;
            }
        }
    }

    // Initial check
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('departmentSelect');
        const other = document.getElementById('deptOther');
        if (select && other && select.value === 'อื่นๆ') {
            other.classList.add('visible');
            other.required = true;
        }
    });

    // Toggle on change
    document.getElementById('departmentSelect')?.addEventListener('change', toggleOtherDept);

    // Handle form submit for department
    document.querySelector('form[action*="update_profile"]')?.addEventListener('submit', function(e) {
        const select = document.getElementById('departmentSelect');
        const other = document.getElementById('deptOther');

        if (select && select.value === 'อื่นๆ') {
            if (!other.value.trim()) {
                e.preventDefault();
                alert('กรุณาระบุแผนก/หน่วยงาน');
                other.focus();
                return;
            }
            // Rename other field to department
            other.name = 'department';
        }
    });
</script>
<?php include 'includes/footer.php'; ?>