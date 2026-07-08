<?php

/**
 * หน้าโปรไฟล์ผู้ใช้ - UI สวยงาม ทันสมัย เต็มหน้า
 * - แก้ไขข้อมูลส่วนตัว
 * - อัปโหลดรูปโปรไฟล์
 * - เปลี่ยนรหัสผ่าน (Modal)
 * - แสดงสถิติความเสี่ยง
 * - แสดงความเสี่ยงล่าสุด
 * - ใช้ SweetAlert2 สำหรับการแจ้งเตือน
 * - โทนสีฟ้า-น้ำเงิน
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
    if ($status == 'ดำเนินการแล้ว') return ['dot' => '#10b981', 'bg' => '#ecfdf5', 'color' => '#065f46', 'icon' => 'fa-check-circle'];
    if ($status == 'กำลังดำเนินการ') return ['dot' => '#3b82f6', 'bg' => '#eff6ff', 'color' => '#1e40af', 'icon' => 'fa-spinner fa-spin'];
    if ($status == 'ยุติ') return ['dot' => '#6b7280', 'bg' => '#f3f4f6', 'color' => '#374151', 'icon' => 'fa-times-circle'];
    return ['dot' => '#f59e0b', 'bg' => '#fffbeb', 'color' => '#92400e', 'icon' => 'fa-clock'];
}
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --primary-light: #eff6ff;
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
        --surface: #ffffff;
        --surface-secondary: #f8fafc;
        --border: #e2e8f0;
        --text: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
    }

    body {
        background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 30%, #f5f3ff 60%, #fdf2f8 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
        font-size: 14px;
    }

    /* ===== Page Header ===== */
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
        left: -3%;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
        border-radius: 50%;
    }
    .page-header-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .page-header-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        border: 1px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
    }
    .page-header h2 {
        font-size: 1.25rem;
        font-weight: 700;
    }
    .page-header p {
        color: rgba(255,255,255,0.8);
        font-size: 0.75rem;
        margin-top: 0.1rem;
    }

    /* ===== Content Grid ===== */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    /* ===== Card ===== */
    .card {
        background: white;
        border-radius: 0.85rem;
        border: 1px solid var(--border);
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        transition: all 0.2s ease;
    }
    .card:hover {
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.06);
    }
    .card-header {
        padding: 0.7rem 1.1rem;
        background: var(--surface-secondary);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.55rem;
        flex-wrap: wrap;
    }
    .card-header-title {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--text);
    }
    .card-body {
        padding: 1.1rem;
    }
    .card-body.no-pad {
        padding: 0;
    }

    /* ===== Profile Top ===== */
    .profile-top {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1.25rem;
    }
    .avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }
    .avatar-img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #bfdbfe;
        transition: all 0.2s;
        background: #f1f5f9;
    }
    .avatar-img:hover {
        transform: scale(1.05);
        border-color: var(--primary);
    }
    .avatar-overlay {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(0,0,0,0.4);
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
        font-size: 1rem;
    }
    .profile-info {
        flex: 1;
        min-width: 0;
    }
    .profile-name {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text);
    }
    .profile-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
    }
    .profile-role {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.15rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.65rem;
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

    /* ===== Stat Grid ===== */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-top: 1px solid #f1f5f9;
    }
    .stat-item {
        padding: 0.85rem 0.5rem;
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
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text);
    }
    .stat-label-text {
        font-size: 0.65rem;
        color: var(--text-muted);
        margin-top: 0.1rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    /* ===== Info Row ===== */
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0;
        border-bottom: 1px solid #f8fafc;
        gap: 1rem;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        color: var(--text-muted);
        font-size: 0.78rem;
        flex-shrink: 0;
    }
    .info-value {
        font-weight: 600;
        color: var(--text-secondary);
        text-align: right;
        word-break: break-all;
    }

    /* ===== Form ===== */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    .form-grid .full {
        grid-column: 1 / -1;
    }
    .form-group {
        margin-bottom: 0.35rem;
    }
    .form-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 0.25rem;
        display: block;
    }
    .form-input {
        width: 100%;
        padding: 0.5rem 0.7rem;
        border: 1.5px solid #bfdbfe;
        border-radius: 0.45rem;
        font-size: 0.8rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #f8faff;
        color: var(--text);
        transition: all 0.2s;
    }
    .form-input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }
    .form-input:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        border-style: dashed;
        border-color: #e2e8f0;
    }
    .form-hint {
        font-size: 0.6rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
    }

    /* ===== Buttons ===== */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.85rem;
        border-radius: 0.45rem;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid;
        transition: all 0.2s;
        font-family: 'Sarabun', sans-serif;
        text-decoration: none;
    }
    .btn-primary {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }
    .btn-primary:hover {
        background: #dbeafe;
    }
    .btn-danger {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }
    .btn-danger:hover {
        background: #fee2e2;
    }
    .btn-outline {
        background: white;
        color: #3b82f6;
        border-color: #bfdbfe;
    }
    .btn-outline:hover {
        background: #eff6ff;
    }
    .btn-sm {
        padding: 0.3rem 0.6rem;
        font-size: 0.7rem;
    }
    .btn-full {
        width: 100%;
        justify-content: center;
    }

    /* ===== Recent List ===== */
    .recent-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.65rem 1.1rem;
        border-bottom: 1px solid #f8fafc;
        transition: all 0.15s;
        gap: 0.75rem;
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
        color: var(--text);
        font-size: 0.8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .recent-meta {
        font-size: 0.68rem;
        color: var(--text-muted);
    }
    .status-badge {
        padding: 0.12rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.62rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .recent-date {
        font-size: 0.7rem;
        color: var(--text-muted);
        white-space: nowrap;
    }

    /* ===== Empty State ===== */
    .empty-state {
        text-align: center;
        padding: 2.5rem 1.5rem;
        color: var(--text-muted);
    }
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.3;
    }

    /* ===== Info Note ===== */
    .info-note {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.75rem;
        padding: 0.85rem 1.1rem;
        font-size: 0.75rem;
        color: #1e40af;
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-top: 1rem;
    }
    .info-note ul {
        list-style: none;
        padding: 0;
        margin: 0.5rem 0 0;
    }
    .info-note ul li {
        padding: 0.15rem 0;
        font-size: 0.72rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    .info-note ul li::before {
        content: '•';
        color: #3b82f6;
        font-weight: bold;
    }

    /* ===== Modal ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
        padding: 1rem;
    }
    .modal-overlay.active {
        display: flex;
        animation: fadeIn 0.25s ease;
    }
    .modal-content {
        background: white;
        border-radius: 1rem;
        width: 100%;
        max-width: 460px;
        box-shadow: 0 25px 60px rgba(37, 99, 235, 0.2);
        animation: slideUp 0.3s ease;
        overflow: hidden;
    }
    .modal-header {
        background: var(--primary-gradient);
        padding: 1rem 1.15rem;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modal-header h3 {
        font-size: 0.95rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }
    .modal-close {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    .modal-close:hover {
        background: rgba(255,255,255,0.35);
    }
    .modal-body {
        padding: 1.15rem;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        padding: 0.75rem 1.15rem;
        border-top: 1px solid #f1f5f9;
        background: #fafbfc;
    }
    .password-field {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 0.35rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.25rem 0.45rem;
        border-radius: 0.25rem;
        transition: all 0.2s;
        font-size: 0.8rem;
    }
    .password-toggle:hover {
        color: #2563eb;
        background: #eff6ff;
    }
    .password-field .form-input {
        padding-right: 2.5rem;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(15px) scale(0.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* ===== Responsive ===== */
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .profile-top {
            flex-direction: column;
            text-align: center;
        }
        .stat-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-3 md:p-4 overflow-y-auto">

        <!-- Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div>
                    <h2>โปรไฟล์ผู้ใช้</h2>
                    <p>จัดการข้อมูลส่วนตัวของคุณ · @<?= htmlspecialchars($user['username']) ?></p>
                </div>
            </div>
        </div>

        <!-- Main Profile Card -->
        <div class="card" style="margin-bottom:1rem;">
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
                    <h3 class="profile-name"><?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></h3>
                    <p class="profile-meta">
                        @<?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($user['reporter_code'] ?? '-') ?>
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
                    <div class="stat-number"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label-text">ทั้งหมด</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color:#f59e0b;"><?= number_format($stats['pending']) ?></div>
                    <div class="stat-label-text">รอดำเนินการ</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color:#3b82f6;"><?= number_format($stats['in_progress']) ?></div>
                    <div class="stat-label-text">กำลังดำเนินการ</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color:#10b981;"><?= number_format($stats['completed']) ?></div>
                    <div class="stat-label-text">ดำเนินการแล้ว</div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">

            <!-- ข้อมูลส่วนตัว -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title">
                        <i class="fas fa-info-circle" style="color:#2563eb;"></i> ข้อมูลส่วนตัว
                    </span>
                </div>
                <div class="card-body">
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

            <!-- แก้ไขข้อมูล -->
            <div class="card">
                <div class="card-header">
                    <span class="card-header-title">
                        <i class="fas fa-edit" style="color:#6366f1;"></i> แก้ไขข้อมูล
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

                            <div class="form-group full">
                                <label class="form-label">รหัสผ่าน</label>
                                <button type="button" class="btn btn-danger btn-full" onclick="openPasswordModal()">
                                    <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                                </button>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;margin-top:1rem;padding-top:0.85rem;border-top:1px solid #f1f5f9;">
                            <button type="submit" class="btn btn-primary">
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
                    <i class="fas fa-history" style="color:#10b981;"></i> ความเสี่ยงล่าสุดของคุณ
                </span>
                <?php if (!empty($recentRisks)): ?>
                    <a href="risks.php" class="btn btn-outline btn-sm">
                        ดูทั้งหมด <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($recentRisks)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>ยังไม่มีรายการความเสี่ยง</p>
                    <a href="risk_form.php" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">
                        <i class="fas fa-plus"></i> เพิ่มรายการแรก
                    </a>
                </div>
            <?php else: ?>
                <div class="card-body no-pad">
                    <?php foreach ($recentRisks as $risk):
                        $status = $risk['status'] ?: 'ยังไม่ดำเนินการ';
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
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span class="recent-date"><?= date('d/m/Y', strtotime($risk['created_at'])) ?></span>
                                <span class="status-badge" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>;">
                                    <i class="fas <?= $style['icon'] ?> text-xs"></i> <?= htmlspecialchars($status) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info Note -->
        <div class="info-note">
            <i class="fas fa-info-circle text-base" style="color:#3b82f6;"></i>
            <div>
                <p class="font-semibold">📌 หมายเหตุ</p>
                <ul>
                    <li>คุณสามารถอัปเดตข้อมูลส่วนตัวได้ตลอดเวลา</li>
                    <li>รูปโปรไฟล์รองรับ JPG, PNG, GIF, WebP (สูงสุด 5MB)</li>
                    <li>ชื่อผู้ใช้ กลุ่มงาน และบทบาท ไม่สามารถเปลี่ยนแปลงได้</li>
                    <li>หากต้องการเปลี่ยนกลุ่มงาน กรุณาติดต่อผู้ดูแลระบบ</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-lock"></i> เปลี่ยนรหัสผ่าน</h3>
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
                <button type="button" class="btn btn-outline" onclick="closePasswordModal()">
                    <i class="fas fa-times"></i> ยกเลิก
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Avatar Upload
    document.getElementById('avatar-input')?.addEventListener('change', function() {
        if (this.files?.[0]) {
            if (this.files[0].size > 5242880) {
                Swal.fire({ icon: 'error', title: 'ไฟล์ใหญ่เกินไป', text: 'ขนาดไฟล์ต้องไม่เกิน 5MB', confirmButtonColor: '#2563eb' });
                this.value = '';
                return;
            }
            Swal.fire({ title: 'กำลังอัปโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            document.getElementById('avatarForm').submit();
        }
    });

    // Password Modal
    function openPasswordModal() {
        document.getElementById('passwordModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('currentPassword').focus(), 100);
    }
    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('active');
        document.body.style.overflow = '';
        document.getElementById('passwordForm').reset();
        document.querySelectorAll('#passwordForm .password-toggle i').forEach(i => { i.classList.remove('fa-eye-slash'); i.classList.add('fa-eye'); });
        document.querySelectorAll('#passwordForm input[type="text"]').forEach(i => i.type = 'password');
    }
    document.getElementById('passwordModal').addEventListener('click', e => { if (e.target === e.currentTarget) closePasswordModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('passwordModal').classList.contains('active')) closePasswordModal(); });

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
        else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        input.focus();
    }

    // Password Form
    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const cp = document.getElementById('currentPassword').value;
        const np = document.getElementById('newPassword').value;
        const cf = document.getElementById('confirmPassword').value;
        if (!cp || !np || !cf) { Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบ', confirmButtonColor: '#2563eb' }); return; }
        if (np.length < 6) { Swal.fire({ icon: 'warning', title: 'รหัสผ่านสั้นเกินไป', text: 'อย่างน้อย 6 ตัวอักษร', confirmButtonColor: '#2563eb' }); return; }
        if (np !== cf) { Swal.fire({ icon: 'warning', title: 'รหัสผ่านไม่ตรงกัน', confirmButtonColor: '#2563eb' }); return; }
        if (cp === np) { Swal.fire({ icon: 'warning', title: 'รหัสผ่านซ้ำ', text: 'รหัสผ่านใหม่ต้องไม่เหมือนปัจจุบัน', confirmButtonColor: '#2563eb' }); return; }
        Swal.fire({ title: 'กำลังเปลี่ยนรหัสผ่าน...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        this.submit();
    });

    // Profile Form
    document.getElementById('profileForm')?.addEventListener('submit', function() {
        Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    });

    // Messages
    <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= htmlspecialchars($success) ?>', timer: 2000, showConfirmButton: false });
    <?php endif; ?>
    <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: '<?= htmlspecialchars($error) ?>', confirmButtonColor: '#2563eb' }).then(() => {
            if ('<?= htmlspecialchars($error) ?>'.includes('รหัสผ่าน')) openPasswordModal();
        });
    <?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>