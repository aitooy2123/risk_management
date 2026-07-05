<?php

/**
 * หน้าโปรไฟล์ผู้ใช้ - UI สวยงาม ทันสมัย
 * - แก้ไขข้อมูลส่วนตัว
 * - อัปโหลดรูปโปรไฟล์
 * - เปลี่ยนรหัสผ่าน (Modal)
 * - แสดงสถิติความเสี่ยง
 * - แสดงความเสี่ยงล่าสุด
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือน
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request';
        } else {
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

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

    if ($action === 'change_password') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request';
        } else {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            } elseif (strlen($new_password) < 6) {
                $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
            } elseif ($new_password !== $confirm_password) {
                $error = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
            } elseif ($current_password === $new_password) {
                $error = 'รหัสผ่านใหม่ต้องไม่เหมือนกับรหัสผ่านปัจจุบัน';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $success = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
    }

    .page-container {
        max-width: 1000px;
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

    .card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.5rem;
    }

    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .card-header-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
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
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .avatar-img {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e2e8f0;
        transition: transform 0.2s;
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
        transition: opacity 0.2s;
        cursor: pointer;
    }

    .avatar-wrap:hover .avatar-overlay {
        opacity: 1;
    }

    .avatar-overlay i {
        color: white;
        font-size: 1.1rem;
    }

    .profile-info {
        flex: 1;
    }

    .profile-name {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1e293b;
    }

    .profile-role {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.15rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 0.3rem;
    }

    .profile-role.admin {
        background: #fef3c7;
        color: #92400e;
    }

    .profile-role.user {
        background: #dbeafe;
        color: #1e40af;
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

    /* Stat Grid */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-top: 1px solid #f1f5f9;
    }

    .stat-item {
        padding: 1rem 0.5rem;
        text-align: center;
        position: relative;
        transition: all 0.15s;
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
        background: #fafbfc;
    }

    .stat-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1e293b;
    }

    .stat-label-text {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.1rem;
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
        font-size: 0.65rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.3rem;
        display: block;
    }

    .form-input {
        width: 100%;
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

    .form-hint {
        font-size: 0.65rem;
        color: #94a3b8;
        margin-top: 0.2rem;
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

    .btn-action.red {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .btn-action.red:hover {
        background: #fee2e2;
    }

    .btn-action.outline {
        background: white;
        color: #3b82f6;
        border-color: #bfdbfe;
    }

    .btn-action.outline:hover {
        background: #eff6ff;
    }

    .btn-sm {
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
    }

    /* Info Row */
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem 0;
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
        color: #334155;
    }

    /* Recent List */
    .recent-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1.5rem;
        border-bottom: 1px solid #f8fafc;
        transition: all 0.15s;
        gap: 1rem;
    }

    .recent-item:last-child {
        border-bottom: none;
    }

    .recent-item:hover {
        background: #f0f9ff;
    }

    .recent-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
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

    .info-card {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: #1e40af;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 1rem;
        width: 90%;
        max-width: 480px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: #f1f5f9;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 1rem;
    }

    .modal-close:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid #f1f5f9;
    }

    .password-field {
        position: relative;
    }

    .password-toggle {
        position: absolute;
        right: 0.7rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.3rem;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .password-toggle:hover {
        color: #64748b;
    }

    .password-field .form-input {
        padding-right: 2.5rem;
    }

    @media (max-width: 768px) {
        .profile-top {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }

        .stat-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .avatar-img {
            width: 70px;
            height: 70px;
        }

        .modal-content {
            width: 95%;
            margin: 1rem;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <div class="page-container">

            <!-- Header -->
            <div class="page-header">
                <h1>👤 โปรไฟล์ผู้ใช้</h1>
                <p>จัดการข้อมูลส่วนตัวของคุณ · @<?= htmlspecialchars($user['username']) ?></p>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-badge"><i class="fas fa-layer-group"></i> ทั้งหมด <strong><?= number_format($stats['total']) ?></strong> รายการ</div>
                <div class="stat-badge"><i class="fas fa-clock text-yellow-500"></i> รอดำเนินการ <strong><?= number_format($stats['pending']) ?></strong></div>
                <div class="stat-badge"><i class="fas fa-spinner text-blue-500"></i> กำลังดำเนินการ <strong><?= number_format($stats['in_progress']) ?></strong></div>
                <div class="stat-badge"><i class="fas fa-check-circle text-green-500"></i> ดำเนินการแล้ว <strong><?= number_format($stats['completed']) ?></strong></div>
            </div>

            <!-- Main Profile Card -->
            <div class="card">
                <div class="profile-top">
                    <div class="avatar-wrap">
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

                <!-- Stats Grid -->
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #1e293b;"><?= number_format($stats['total']) ?></div>
                        <div class="stat-label-text">ทั้งหมด</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #eab308;"><?= number_format($stats['pending']) ?></div>
                        <div class="stat-label-text">รอดำเนินการ</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #3b82f6;"><?= number_format($stats['in_progress']) ?></div>
                        <div class="stat-label-text">กำลังดำเนินการ</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #22c55e;"><?= number_format($stats['completed']) ?></div>
                        <div class="stat-label-text">ดำเนินการแล้ว</div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;">
                <!-- ข้อมูลส่วนตัว -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-header-title">
                            <i class="fas fa-info-circle text-blue-600"></i> ข้อมูลส่วนตัว
                        </span>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;flex-direction:column;">
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
                                    <span class="info-label">กลุ่มงาน</span>
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
                        <span class="card-header-title">
                            <i class="fas fa-edit text-indigo-600"></i> แก้ไขข้อมูล
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="profileForm">
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
                                        <label class="form-label">กลุ่มงาน</label>
                                        <input type="text" value="<?= htmlspecialchars($user['department'] ?? '') ?>" class="form-input" disabled>
                                        <p class="form-hint">🔒 กลุ่มงานถูกกำหนดโดยผู้ดูแลระบบ ไม่สามารถเปลี่ยนแปลงได้</p>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group full">
                                    <label class="form-label">รหัสผ่าน</label>
                                    <button type="button" class="btn-action red" onclick="openPasswordModal()" style="width:100%;justify-content:center;">
                                        <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                                    </button>
                                </div>
                            </div>

                            <div style="display:flex;justify-content:flex-end;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                                <button type="submit" class="btn-action blue">
                                    <i class="fas fa-save"></i> บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Risks -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title">
                        <i class="fas fa-history text-emerald-600"></i> ความเสี่ยงล่าสุดของคุณ
                    </span>
                    <?php if (!empty($recentRisks)): ?>
                        <a href="risks.php" class="btn-action outline btn-sm">
                            ดูทั้งหมด <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($recentRisks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>ยังไม่มีรายการความเสี่ยง</p>
                        <a href="risk_form.php" class="btn-action blue btn-sm" style="margin-top:0.75rem;">
                            <i class="fas fa-plus"></i> เพิ่มรายการแรก
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card-body no-pad">
                        <?php foreach ($recentRisks as $risk):
                            $status = $risk['status'] ?? 'ยังไม่ดำเนินการ';
                            $style = getStatusStyle($status);
                        ?>
                            <div class="recent-item">
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="info-card">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-500 text-lg mt-0.5"></i>
                    <div>
                        <p class="font-semibold mb-1">📌 หมายเหตุ</p>
                        <ul class="list-disc ml-4 space-y-0.5 text-sm">
                            <li>คุณสามารถอัปเดตข้อมูลส่วนตัวได้ตลอดเวลา</li>
                            <li>รูปโปรไฟล์รองรับ JPG, PNG, GIF, WebP (สูงสุด 5MB)</li>
                            <li><strong>ชื่อผู้ใช้ กลุ่มงาน และบทบาท ไม่สามารถเปลี่ยนแปลงได้</strong></li>
                            <li>หากต้องการเปลี่ยนกลุ่มงาน กรุณาติดต่อผู้ดูแลระบบ</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-lock text-red-500"></i> เปลี่ยนรหัสผ่าน</h3>
            <button class="modal-close" onclick="closePasswordModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="passwordForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="change_password">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">รหัสผ่านปัจจุบัน</label>
                    <div class="password-field">
                        <input type="password" name="current_password" id="currentPassword" class="form-input" placeholder="กรอกรหัสผ่านปัจจุบัน" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('currentPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">รหัสผ่านใหม่</label>
                    <div class="password-field">
                        <input type="password" name="new_password" id="newPassword" class="form-input" placeholder="รหัสผ่านใหม่อย่างน้อย 6 ตัวอักษร" required minlength="6">
                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                    <div class="password-field">
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-input" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required minlength="6">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-action outline" onclick="closePasswordModal()">
                    <i class="fas fa-times"></i> ยกเลิก
                </button>
                <button type="submit" class="btn-action red">
                    <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ========== Avatar Upload ==========
    document.getElementById('avatar-input')?.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            if (this.files[0].size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'ไฟล์ใหญ่เกินไป',
                    text: 'ขนาดไฟล์ต้องไม่เกิน 5MB',
                    confirmButtonColor: '#3b82f6'
                });
                this.value = '';
                return;
            }

            Swal.fire({
                title: 'กำลังอัปโหลด...',
                html: '<div class="flex justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i></div>',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            document.getElementById('avatarForm').submit();
        }
    });

    // ========== Password Modal ==========
    function openPasswordModal() {
        document.getElementById('passwordModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            document.getElementById('currentPassword').focus();
        }, 100);
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('active');
        document.body.style.overflow = '';
        document.getElementById('passwordForm').reset();
        document.querySelectorAll('#passwordForm .password-toggle i').forEach(icon => {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        });
        document.querySelectorAll('#passwordForm input[type="text"]').forEach(input => {
            input.type = 'password';
        });
    }

    document.getElementById('passwordModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePasswordModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('passwordModal').classList.contains('active')) {
            closePasswordModal();
        }
    });

    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
        
        input.focus();
    }

    // ========== Password Form Submit ==========
    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบถ้วน',
                text: 'กรุณากรอกข้อมูลให้ครบทุกช่อง',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        if (newPassword.length < 6) {
            Swal.fire({
                icon: 'warning',
                title: 'รหัสผ่านสั้นเกินไป',
                text: 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        if (newPassword !== confirmPassword) {
            Swal.fire({
                icon: 'warning',
                title: 'รหัสผ่านไม่ตรงกัน',
                text: 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        if (currentPassword === newPassword) {
            Swal.fire({
                icon: 'warning',
                title: 'รหัสผ่านซ้ำ',
                text: 'รหัสผ่านใหม่ต้องไม่เหมือนกับรหัสผ่านปัจจุบัน',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        Swal.fire({
            title: 'กำลังเปลี่ยนรหัสผ่าน...',
            html: '<div class="flex justify-center"><i class="fas fa-spinner fa-spin text-2xl text-red-500"></i></div>',
            allowOutsideClick: false,
            showConfirmButton: false
        });

        this.submit();
    });

    // ========== Profile Form Submit ==========
    document.getElementById('profileForm')?.addEventListener('submit', function() {
        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            html: '<div class="flex justify-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i></div>',
            allowOutsideClick: false,
            showConfirmButton: false
        });
    });

    // ========== Success/Error Messages ==========
    <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?= htmlspecialchars($success) ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>

    <?php if ($error): ?>
        const errorMsg = '<?= htmlspecialchars($error) ?>';
        if (errorMsg.includes('รหัสผ่าน')) {
            Swal.fire({
                icon: 'error',
                title: 'เปลี่ยนรหัสผ่านไม่สำเร็จ',
                text: errorMsg,
                confirmButtonColor: '#dc2626'
            }).then(() => {
                openPasswordModal();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: errorMsg,
                confirmButtonColor: '#3b82f6'
            });
        }
    <?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>