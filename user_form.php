<?php
/**
 * ฟอร์มเพิ่ม/แก้ไขผู้ใช้ - UI สวยงาม ทันสมัย เต็มหน้า
 * - เฉพาะ Admin
 * - รองรับทุกฟิลด์: reporter_code, username, fullname, email, phone, department, role, avatar
 * - แผนก/หน่วยงาน เป็น select พร้อมกลุ่ม
 * - ป้องกันการแก้ไข username ของ user (แต่แก้ไข reporter_code ได้)
 * - รหัสผ่านอยู่ใน Modal แยก
 * - UI ทันสมัย สวยงาม โทนสีฟ้า-น้ำเงิน แสดงผลเต็มหน้าจอ
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

$id = $_GET['id'] ?? null;
$user = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) redirect('users.php');
}

$csrf_token = generateCsrfToken();
$is_edit = $id ? true : false;
$title = $is_edit ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้';
$icon = $is_edit ? 'fa-user-edit' : 'fa-user-plus';

// รายการแผนก
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

// ตรวจสอบว่าแผนกปัจจุบันอยู่ในลิสต์หรือไม่
$currentDept = $user['department'] ?? '';
$isCustomDept = !empty($currentDept) && !in_array($currentDept, $departmentsList);
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
        --border: #bfdbfe;
        --text: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --danger: #ef4444;
        --warning: #f59e0b;
        --success: #10b981;
        --info: #3b82f6;
        --accent: #3b82f6;
        --accent-light: #eff6ff;
        --accent-gradient: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #3b82f6 100%);
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
        gap: 0.85rem;
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

    /* ===== Form Card ===== */
    .form-card {
        background: white;
        border-radius: 0.85rem;
        border: 1px solid var(--border);
        margin-bottom: 0.85rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        transition: all 0.2s ease;
    }
    .form-card:hover {
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.06);
        border-color: #93c5fd;
    }
    .card-header {
        padding: 0.7rem 1.1rem;
        background: var(--surface-secondary);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 0.55rem;
        cursor: pointer;
        user-select: none;
        transition: background 0.2s;
    }
    .card-header:hover {
        background: #eff6ff;
    }
    .card-header-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        flex-shrink: 0;
    }
    .card-header-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text);
        flex: 1;
    }
    .card-header-badge {
        font-size: 0.58rem;
        font-weight: 600;
        padding: 0.18rem 0.5rem;
        border-radius: 9999px;
        letter-spacing: 0.3px;
    }
    .card-header .toggle-icon {
        color: var(--text-muted);
        transition: transform 0.3s;
        font-size: 0.65rem;
    }
    .card-header .toggle-icon.active {
        transform: rotate(180deg);
    }
    .card-body {
        padding: 1.1rem;
        transition: all 0.3s ease;
    }
    .card-body.collapsed {
        display: none;
    }

    /* ===== Two Column Layout for Cards ===== */
    .cards-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    .cards-grid .form-card.full-width {
        grid-column: 1 / -1;
    }

    /* ===== Form Elements ===== */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    .form-grid .full {
        grid-column: 1 / -1;
    }
    .form-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.85rem;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .form-label {
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .form-label .required {
        color: var(--danger);
        font-size: 0.6rem;
    }
    .form-label .optional {
        font-size: 0.6rem;
        font-weight: 400;
        color: var(--text-muted);
        text-transform: none;
        letter-spacing: 0;
    }
    .form-input {
        padding: 0.5rem 0.7rem;
        border: 1.5px solid #bfdbfe;
        border-radius: 0.45rem;
        font-size: 0.8rem;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #f8faff;
        color: var(--text);
        transition: all 0.2s;
        width: 100%;
    }
    .form-input:hover {
        border-color: #93c5fd;
        background: #f0f6ff;
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
    select.form-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.7rem center;
        padding-right: 2rem;
    }

    /* ===== Password Section ===== */
    .password-section {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .password-status {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.65rem;
        border-radius: 0.4rem;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .password-status.set {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .password-status.not-set {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .btn-password {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.4rem 0.8rem;
        border-radius: 0.45rem;
        font-size: 0.73rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        font-family: 'Sarabun', sans-serif;
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        transition: all 0.25s;
        white-space: nowrap;
    }
    .btn-password:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
    }

    /* ===== Avatar ===== */
    .avatar-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .avatar-preview {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #bfdbfe;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.08);
        transition: all 0.3s;
        background: #f1f5f9;
        flex-shrink: 0;
    }
    .avatar-preview:hover {
        border-color: var(--primary);
        transform: scale(1.05);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
    }
    .avatar-upload {
        flex: 1;
        min-width: 0;
    }
    .avatar-hint {
        font-size: 0.65rem;
        color: var(--text-muted);
        margin-top: 0.2rem;
        line-height: 1.4;
    }

    /* ===== Buttons ===== */
    .btn-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding-top: 0.35rem;
        flex-wrap: wrap;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.5rem 1.1rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.25s;
        cursor: pointer;
        border: none;
        font-family: 'Sarabun', sans-serif;
        text-decoration: none;
    }
    .btn-primary {
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(37, 99, 235, 0.4);
    }
    .btn-cancel {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .btn-cancel:hover {
        background: #e2e8f0;
        color: #475569;
        transform: translateY(-1px);
    }

    /* ===== Field Note ===== */
    .field-note {
        font-size: 0.63rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .field-note.warning {
        color: #dc2626;
    }
    .field-note.info {
        color: #2563eb;
    }

    /* ===== Hidden Utility ===== */
    .hidden {
        display: none;
    }
    .mt-1 {
        margin-top: 0.25rem;
    }
    .mt-2 {
        margin-top: 0.5rem;
    }

    /* ===== Modal ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(6px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .modal-overlay.active {
        display: flex;
        animation: fadeIn 0.25s ease;
    }
    .modal-dialog {
        background: white;
        border-radius: 1rem;
        width: 100%;
        max-width: 440px;
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
    .modal-body .form-input {
        background: #f8faff;
        border-color: #bfdbfe;
    }
    .modal-body .form-input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }
    .modal-footer {
        padding: 0.75rem 1.15rem;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        background: #f8fafc;
    }
    .modal-footer .btn {
        padding: 0.4rem 0.9rem;
        font-size: 0.73rem;
    }
    .btn-modal-cancel {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .btn-modal-cancel:hover {
        background: #e2e8f0;
        color: #475569;
    }
    .btn-modal-clear {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .btn-modal-clear:hover {
        background: #fee2e2;
    }
    .btn-modal-confirm {
        background: var(--primary-gradient);
        color: white;
    }
    .btn-modal-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    .password-input-wrapper {
        position: relative;
    }
    .password-toggle-btn {
        position: absolute;
        right: 0.35rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #94a3b8;
        padding: 0.25rem 0.45rem;
        border-radius: 0.25rem;
        transition: all 0.2s;
        font-size: 0.8rem;
    }
    .password-toggle-btn:hover {
        color: #2563eb;
        background: #eff6ff;
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
        .cards-grid {
            grid-template-columns: 1fr;
        }
        .form-grid-3 {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 640px) {
        .form-grid,
        .form-grid-3 {
            grid-template-columns: 1fr;
        }
        .avatar-section {
            flex-direction: column;
            align-items: flex-start;
        }
        .btn-row {
            flex-direction: column;
        }
        .btn-row .btn {
            width: 100%;
            justify-content: center;
        }
        .password-section {
            flex-direction: column;
            align-items: flex-start;
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
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div>
                    <h2><?= $title ?></h2>
                    <p><?= $is_edit ? 'แก้ไขข้อมูล: ' . htmlspecialchars($user['username']) : 'สร้างบัญชีผู้ใช้ใหม่' ?></p>
                </div>
            </div>
        </div>

        <form id="userForm" method="POST" action="action.php?action=save_user" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="is_edit" value="<?= $is_edit ? '1' : '0' ?>">
            <input type="hidden" name="password" id="hiddenPassword">
            <input type="hidden" name="confirm_password" id="hiddenConfirmPassword">

            <!-- แบ่งเป็น 2 คอลัมน์ -->
            <div class="cards-grid">

                <!-- ========== คอลัมน์ซ้าย ========== -->
                <div style="display:flex;flex-direction:column;gap:0.85rem;">

                    <!-- ข้อมูลบัญชี -->
                    <div class="form-card">
                        <div class="card-header" onclick="toggleCard(this)">
                            <div class="card-header-icon" style="background:#eff6ff;color:#2563eb;">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <h3 class="card-header-title">ข้อมูลบัญชี</h3>
                            <span class="card-header-badge" style="background:#fef2f2;color:#dc2626;">จำเป็น</span>
                            <i class="fas fa-chevron-down toggle-icon active"></i>
                        </div>
                        <div class="card-body" id="cardAccount">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-id-card"></i> รหัสผู้รายงาน <span class="required">*</span>
                                    </label>
                                    <input type="text" name="reporter_code" 
                                           value="<?= htmlspecialchars($user['reporter_code'] ?? '') ?>" 
                                           class="form-input" 
                                           required 
                                           placeholder="เช่น R10001">
                                    <?php if ($is_edit): ?>
                                        <div class="field-note info">
                                            <i class="fas fa-edit"></i> แก้ไขรหัสได้
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> ชื่อผู้ใช้ <span class="required">*</span>
                                    </label>
                                    <input type="text" name="username" 
                                           value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                           class="form-input" 
                                           <?= $is_edit ? 'disabled' : 'required' ?> 
                                           placeholder="กรอกชื่อผู้ใช้">
                                    <?php if ($is_edit): ?>
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>">
                                        <div class="field-note warning">
                                            <i class="fas fa-lock"></i> ไม่สามารถแก้ไขได้
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">
                                        <i class="fas fa-key"></i> รหัสผ่าน 
                                        <?php if ($is_edit): ?>
                                            <span class="optional">(เปลี่ยนได้)</span>
                                        <?php else: ?>
                                            <span class="required">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="password-section">
                                        <?php if ($is_edit): ?>
                                            <span class="password-status set">
                                                <i class="fas fa-shield-check"></i> ตั้งค่าแล้ว
                                            </span>
                                        <?php else: ?>
                                            <span class="password-status not-set">
                                                <i class="fas fa-exclamation-triangle"></i> ยังไม่ได้ตั้งค่า
                                            </span>
                                        <?php endif; ?>
                                        <button type="button" class="btn-password" onclick="openPasswordModal()">
                                            <i class="fas fa-key"></i> <?= $is_edit ? 'เปลี่ยนรหัสผ่าน' : 'ตั้งรหัสผ่าน' ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">
                                        <i class="fas fa-user-shield"></i> บทบาท
                                    </label>
                                    <select name="role" class="form-input">
                                        <option value="user" <?= ($user['role'] ?? '') == 'user' ? 'selected' : '' ?>>
                                            👤 ผู้ใช้ทั่วไป (User)
                                        </option>
                                        <option value="admin" <?= ($user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>
                                            👑 ผู้ดูแลระบบ (Admin)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Avatar -->
                    <div class="form-card">
                        <div class="card-header" onclick="toggleCard(this)">
                            <div class="card-header-icon" style="background:#eff6ff;color:#2563eb;">
                                <i class="fas fa-image"></i>
                            </div>
                            <h3 class="card-header-title">รูปโปรไฟล์</h3>
                            <span class="card-header-badge" style="background:#eff6ff;color:#1e40af;">Avatar</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="card-body" id="cardAvatar">
                            <div class="avatar-section">
                                <img id="avatarPreview" 
                                     src="<?= ($is_edit && !empty($user['avatar'])) ? 'avatars/' . htmlspecialchars($user['avatar']) : 'avatars/default.png' ?>" 
                                     class="avatar-preview" 
                                     alt="Avatar" 
                                     onerror="this.src='avatars/default.png'">
                                <div class="avatar-upload">
                                    <input type="file" name="avatar" id="avatarInput" accept="image/*" class="form-input">
                                    <p class="avatar-hint">
                                        <i class="fas fa-info-circle"></i> JPG, PNG, GIF, WebP (สูงสุด 5MB)
                                        <?php if ($is_edit && !empty($user['avatar'])): ?>
                                            <br><i class="fas fa-image"></i> ปัจจุบัน: <strong><?= htmlspecialchars($user['avatar']) ?></strong>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ========== คอลัมน์ขวา ========== -->
                <div style="display:flex;flex-direction:column;gap:0.85rem;">

                    <!-- รายละเอียดเพิ่มเติม -->
                    <div class="form-card">
                        <div class="card-header" onclick="toggleCard(this)">
                            <div class="card-header-icon" style="background:#eff6ff;color:#2563eb;">
                                <i class="fas fa-address-card"></i>
                            </div>
                            <h3 class="card-header-title">รายละเอียดเพิ่มเติม</h3>
                            <span class="card-header-badge" style="background:#eff6ff;color:#1e40af;">เพิ่มเติม</span>
                            <i class="fas fa-chevron-down toggle-icon active"></i>
                        </div>
                        <div class="card-body" id="cardDetails">
                            <div class="form-grid">
                                <div class="form-group full">
                                    <label class="form-label">
                                        <i class="fas fa-user-tag"></i> ชื่อ-นามสกุล
                                    </label>
                                    <input type="text" name="fullname" 
                                           value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" 
                                           class="form-input" 
                                           placeholder="ชื่อ-นามสกุลจริง">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i> อีเมล
                                    </label>
                                    <input type="email" name="email" 
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                           class="form-input" 
                                           placeholder="example@email.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-phone"></i> เบอร์โทรศัพท์
                                    </label>
                                    <input type="text" name="phone" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           class="form-input" 
                                           placeholder="08x-xxx-xxxx">
                                </div>
                                <div class="form-group full">
                                    <label class="form-label">
                                        <i class="fas fa-building"></i> แผนก/หน่วยงาน
                                    </label>
                                    <select name="department_select" id="departmentSelect" class="form-input">
                                        <option value="">-- กรุณาเลือกแผนก --</option>
                                        <?php foreach ($departmentsList as $dept): 
                                            $selected = $currentDept == $dept ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($dept) ?>" <?= $selected ?>>
                                                <?= htmlspecialchars($dept) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="อื่นๆ" <?= $isCustomDept ? 'selected' : '' ?>>
                                            ➕ อื่นๆ (ระบุ)
                                        </option>
                                    </select>
                                    <input type="text" name="department" id="departmentOther" 
                                           class="form-input mt-1 <?= $isCustomDept ? '' : 'hidden' ?>" 
                                           placeholder="ระบุแผนก/หน่วยงานอื่น"
                                           value="<?= $isCustomDept ? htmlspecialchars($currentDept) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Buttons -->
            <div class="btn-row" style="margin-top:0.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> บันทึก
                </button>
                <a href="users.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i> ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ==================== PASSWORD MODAL ==================== -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <i class="fas fa-key"></i>
                <?= $is_edit ? 'เปลี่ยนรหัสผ่าน' : 'ตั้งรหัสผ่าน' ?>
            </h3>
            <button type="button" class="modal-close" onclick="closePasswordModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:0.85rem;">
                <label class="form-label">
                    🔒 รหัสผ่าน 
                    <?php if ($is_edit): ?>
                        <span class="optional">(เว้นว่างหากไม่เปลี่ยน)</span>
                    <?php else: ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
                <div class="password-input-wrapper">
                    <input type="password" id="modalPassword" class="form-input" 
                           placeholder="<?= $is_edit ? 'รหัสผ่านใหม่ (เว้นว่าง = ไม่เปลี่ยน)' : 'กรอกรหัสผ่าน' ?>"
                           <?= $is_edit ? '' : 'required' ?>>
                    <button type="button" class="password-toggle-btn" onclick="toggleModalPassword()" title="แสดง/ซ่อน">
                        <i class="fas fa-eye" id="togglePassIcon"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">✅ ยืนยันรหัสผ่าน</label>
                <div class="password-input-wrapper">
                    <input type="password" id="modalConfirmPassword" class="form-input" placeholder="ยืนยันรหัสผ่าน">
                    <button type="button" class="password-toggle-btn" onclick="toggleModalConfirmPassword()" title="แสดง/ซ่อน">
                        <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                    </button>
                </div>
            </div>
            <div class="field-note info mt-2">
                <i class="fas fa-shield-alt"></i> 
                รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร
            </div>
        </div>
        <div class="modal-footer">
            <?php if ($is_edit): ?>
                <button type="button" class="btn btn-modal-clear" onclick="clearPasswordModal()">
                    <i class="fas fa-undo"></i> ล้าง
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-modal-cancel" onclick="closePasswordModal()">
                <i class="fas fa-times"></i> ยกเลิก
            </button>
            <button type="button" class="btn btn-modal-confirm" onclick="savePasswordFromModal()">
                <i class="fas fa-check"></i> ยืนยัน
            </button>
        </div>
    </div>
</div>

<script>
    function toggleCard(header) {
        const body = header.nextElementSibling;
        const icon = header.querySelector('.toggle-icon');
        if (body) {
            body.classList.toggle('collapsed');
            if (icon) icon.classList.toggle('active');
        }
    }

    function openPasswordModal() {
        document.getElementById('passwordModal').classList.add('active');
        setTimeout(() => document.getElementById('modalPassword').focus(), 150);
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('active');
    }

    function toggleModalPassword() {
        const input = document.getElementById('modalPassword');
        const icon = document.getElementById('togglePassIcon');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function toggleModalConfirmPassword() {
        const input = document.getElementById('modalConfirmPassword');
        const icon = document.getElementById('toggleConfirmIcon');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function clearPasswordModal() {
        ['modalPassword', 'modalConfirmPassword'].forEach(id => {
            const el = document.getElementById(id);
            el.value = '';
            el.type = 'password';
        });
        document.getElementById('togglePassIcon').className = 'fas fa-eye';
        document.getElementById('toggleConfirmIcon').className = 'fas fa-eye';
        document.getElementById('hiddenPassword').value = '';
        document.getElementById('hiddenConfirmPassword').value = '';
        Swal.fire({ icon: 'info', title: 'ล้างรหัสผ่านแล้ว', timer: 2000, showConfirmButton: false, confirmButtonColor: '#2563eb' });
        closePasswordModal();
    }

    function savePasswordFromModal() {
        const password = document.getElementById('modalPassword').value;
        const confirm = document.getElementById('modalConfirmPassword').value;
        const isEdit = document.querySelector('input[name="is_edit"]').value === '1';
        if (isEdit && !password && !confirm) {
            document.getElementById('hiddenPassword').value = '';
            document.getElementById('hiddenConfirmPassword').value = '';
            Swal.fire({ icon: 'info', title: 'ไม่มีการเปลี่ยนรหัสผ่าน', timer: 2000, showConfirmButton: false, confirmButtonColor: '#2563eb' });
            closePasswordModal();
            return;
        }
        if (!password) { Swal.fire({ icon: 'error', title: 'กรุณากรอกรหัสผ่าน', confirmButtonColor: '#2563eb' }); return; }
        if (password.length < 8) { Swal.fire({ icon: 'error', title: 'รหัสผ่านสั้นเกินไป', text: 'อย่างน้อย 8 ตัวอักษร', confirmButtonColor: '#2563eb' }); return; }
        if (password !== confirm) { Swal.fire({ icon: 'error', title: 'รหัสผ่านไม่ตรงกัน', confirmButtonColor: '#2563eb' }); return; }
        document.getElementById('hiddenPassword').value = password;
        document.getElementById('hiddenConfirmPassword').value = confirm;
        Swal.fire({ icon: 'success', title: 'ตั้งรหัสผ่านเรียบร้อย', timer: 2000, showConfirmButton: false, confirmButtonColor: '#2563eb' });
        closePasswordModal();
        const badge = document.querySelector('.password-status');
        if (badge) { badge.className = 'password-status set'; badge.innerHTML = '<i class="fas fa-shield-check"></i> ตั้งค่าแล้ว'; }
    }

    document.getElementById('passwordModal').addEventListener('click', e => { if (e.target === e.currentTarget) closePasswordModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('passwordModal').classList.contains('active')) closePasswordModal(); });

    function toggleOtherDept() {
        const s = document.getElementById('departmentSelect'), o = document.getElementById('departmentOther');
        if (s && o) {
            if (s.value === 'อื่นๆ') { o.classList.remove('hidden'); o.focus(); o.required = true; }
            else { o.classList.add('hidden'); o.value = ''; o.required = false; }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const s = document.getElementById('departmentSelect'), o = document.getElementById('departmentOther');
        if (s && o && s.value === 'อื่นๆ') { o.classList.remove('hidden'); o.required = true; }
    });

    document.getElementById('departmentSelect')?.addEventListener('change', toggleOtherDept);

    document.getElementById('avatarInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5242880) { Swal.fire({ icon: 'warning', title: 'ไฟล์ใหญ่เกินไป', confirmButtonColor: '#2563eb' }); this.value = ''; return; }
            const r = new FileReader();
            r.onload = ev => document.getElementById('avatarPreview').src = ev.target.result;
            r.readAsDataURL(file);
        }
    });

    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const ds = document.getElementById('departmentSelect'), dso = document.getElementById('departmentOther');
        if (ds && ds.value === 'อื่นๆ' && !dso.value.trim()) { Swal.fire({ icon: 'error', title: 'กรุณาระบุแผนก', confirmButtonColor: '#2563eb' }); dso.focus(); return; }
        if (ds && ds.value !== 'อื่นๆ') dso.value = '';
        const isEdit = document.querySelector('input[name="is_edit"]').value === '1';
        if (!isEdit && !document.getElementById('hiddenPassword').value) { Swal.fire({ icon: 'error', title: 'กรุณาตั้งรหัสผ่าน', confirmButtonColor: '#2563eb' }); return; }
        const fd = new FormData(this), btn = this.querySelector('button[type="submit"]'), orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
        fetch('action.php?action=save_user', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) Swal.fire({ icon: 'success', title: 'สำเร็จ', timer: 2000, showConfirmButton: false, confirmButtonColor: '#2563eb' }).then(() => window.location.href = 'users.php');
            else { Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message, confirmButtonColor: '#2563eb' }); btn.disabled = false; btn.innerHTML = orig; }
        })
        .catch(() => { Swal.fire({ icon: 'error', title: 'เชื่อมต่อล้มเหลว', confirmButtonColor: '#2563eb' }); btn.disabled = false; btn.innerHTML = orig; });
    });
</script>
<?php include 'includes/footer.php'; ?>