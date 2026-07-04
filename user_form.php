<?php
/**
 * ฟอร์มเพิ่ม/แก้ไขผู้ใช้ - UI สวยงาม
 * - เฉพาะ Admin
 * - รองรับทุกฟิลด์: reporter_code, username, fullname, email, phone, department, role, avatar
 * - แผนก/หน่วยงาน เป็น select พร้อมกลุ่ม
 * - ป้องกันการแก้ไข username ของ user (แต่แก้ไข reporter_code ได้)
 * - รหัสผ่านอยู่ในแทปแยก
 * - มีพื้นหลังสวยงาม
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
$title = $is_edit ? '✏️ แก้ไขผู้ใช้' : '➕ เพิ่มผู้ใช้';

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
    .form-container { 
        max-width: 800px; 
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
    
    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem; padding: 1.75rem 2.25rem; margin-bottom: 1.5rem;
        color: white; position: relative; overflow: hidden;
        box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
    }
    .page-header::before { 
        content: ''; 
        position: absolute; 
        top: -50%; 
        right: -10%; 
        width: 300px; 
        height: 300px; 
        background: rgba(255,255,255,0.05); 
        border-radius: 50%; 
    }
    .page-header::after {
        content: '';
        position: absolute;
        bottom: -50%;
        left: -5%;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
    }
    .page-header h2 { 
        font-size: 1.5rem; 
        font-weight: 700; 
        position: relative; 
        z-index: 1; 
    }
    .page-header p { 
        color: rgba(255,255,255,0.7); 
        font-size: 0.85rem; 
        position: relative; 
        z-index: 1; 
    }
    
    .card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 1rem; 
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem; 
        overflow: hidden;
        transition: all 0.3s;
    }
    .card:hover { 
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }
    .card-header {
        padding: 1rem 1.5rem; 
        border-bottom: 1px solid rgba(241, 245, 249, 0.8);
        background: rgba(250, 251, 252, 0.7);
        display: flex; 
        align-items: center; 
        gap: 0.75rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .card-header:hover {
        background: rgba(241, 245, 249, 0.9);
    }
    .card-header-icon { 
        width: 36px; 
        height: 36px; 
        border-radius: 10px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1rem; 
        flex-shrink: 0; 
    }
    .card-header-title { 
        font-weight: 700; 
        color: #1e293b; 
        font-size: 1rem; 
        flex: 1; 
    }
    .card-header .toggle-icon {
        transition: transform 0.3s;
        color: #94a3b8;
    }
    .card-header .toggle-icon.active {
        transform: rotate(180deg);
    }
    .card-body {
        padding: 1.5rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.5);
    }
    .card-body.collapsed {
        display: none;
    }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-label { 
        font-size: 0.78rem; 
        font-weight: 700; 
        color: #475569; 
        display: flex; 
        align-items: center; 
        gap: 0.35rem; 
    }
    .form-label .required { color: #ef4444; }
    .form-label .optional { font-size: 0.7rem; font-weight: 400; color: #94a3b8; }
    .form-input {
        padding: 0.7rem 0.9rem; 
        border: 1.5px solid rgba(226, 232, 240, 0.8);
        border-radius: 0.6rem;
        font-size: 0.9rem; 
        outline: none; 
        font-family: 'Sarabun', sans-serif;
        background: rgba(250, 251, 252, 0.8);
        color: #1e293b; 
        transition: all 0.25s;
        backdrop-filter: blur(10px);
    }
    .form-input:hover { 
        border-color: #cbd5e1; 
        background: rgba(255, 255, 255, 0.9);
    }
    .form-input:focus { 
        border-color: #3b82f6; 
        background: white; 
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    .form-input:disabled {
        background: rgba(241, 245, 249, 0.6);
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
    select.form-input optgroup { font-weight: 700; color: #1e293b; }
    select.form-input option { font-weight: 400; }
    
    .avatar-section { display: flex; align-items: center; gap: 1.5rem; }
    .avatar-preview {
        width: 90px; height: 90px; border-radius: 50%; object-fit: cover;
        border: 4px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        transition: all 0.3s;
        background: #f1f5f9;
    }
    .avatar-preview:hover { 
        border-color: #3b82f6; 
        transform: scale(1.05);
        box-shadow: 0 12px 40px rgba(37, 99, 235, 0.2);
    }
    .avatar-upload { flex: 1; }
    .avatar-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 0.35rem; }
    
    .btn-row { display: flex; align-items: center; gap: 1rem; padding-top: 0.5rem; }
    .btn {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.7rem 1.5rem;
        border-radius: 0.7rem; font-weight: 600; font-size: 0.9rem; transition: all 0.3s;
        cursor: pointer; border: none; font-family: 'Sarabun', sans-serif; text-decoration: none;
    }
    .btn-primary { 
        background: linear-gradient(135deg, #1e40af, #3b82f6); 
        color: white; 
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
    }
    .btn-primary:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 10px 32px rgba(37, 99, 235, 0.45);
    }
    .btn-cancel { 
        background: rgba(241, 245, 249, 0.8);
        color: #64748b;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.5);
    }
    .btn-cancel:hover { 
        background: #e2e8f0; 
        transform: translateY(-2px);
    }
    
    .dept-other-input {
        margin-top: 0.5rem;
    }
    .dept-other-input.hidden {
        display: none;
    }
    
    .field-note {
        color: #94a3b8;
        font-size: 0.7rem;
        margin-top: 0.2rem;
    }
    .field-note i {
        margin-right: 0.2rem;
    }
    
    .tab-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.6rem;
        font-weight: 600;
        background: rgba(219, 234, 254, 0.8);
        color: #1e40af;
        backdrop-filter: blur(10px);
    }
    .tab-indicator i {
        font-size: 0.5rem;
    }
    
    /* Helper buttons in password card */
    .helper-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid rgba(226, 232, 240, 0.5);
        background: rgba(241, 245, 249, 0.7);
        color: #64748b;
        transition: all 0.2s;
        backdrop-filter: blur(10px);
        font-family: 'Sarabun', sans-serif;
    }
    .helper-btn:hover {
        background: rgba(226, 232, 240, 0.9);
        transform: translateY(-1px);
    }
    .helper-btn.danger {
        background: rgba(254, 242, 242, 0.7);
        color: #dc2626;
        border-color: rgba(254, 202, 202, 0.5);
    }
    .helper-btn.danger:hover {
        background: rgba(254, 226, 226, 0.9);
    }
    
    @media (max-width: 640px) { 
        .form-grid { grid-template-columns: 1fr; } 
        .avatar-section { flex-direction: column; align-items: flex-start; } 
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
        
        <div class="form-container">
            
            <div class="page-header">
                <h2><?= $title ?></h2>
                <p><?= $is_edit ? 'แก้ไขข้อมูลผู้ใช้: ' . htmlspecialchars($user['username']) : 'สร้างบัญชีผู้ใช้ใหม่' ?></p>
            </div>

            <form id="userForm" method="POST" action="action.php?action=save_user" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="is_edit" value="<?= $is_edit ? '1' : '0' ?>">

                <!-- ข้อมูลบัญชี (เปิดเสมอ) -->
                <div class="card">
                    <div class="card-header" onclick="toggleCard(this)">
                        <div class="card-header-icon" style="background:rgba(219,234,254,0.8);color:#2563eb;"><i class="fas fa-user-circle"></i></div>
                        <h3 class="card-header-title">ข้อมูลบัญชี</h3>
                        <span class="tab-indicator"><i class="fas fa-check-circle"></i> จำเป็น</span>
                        <i class="fas fa-chevron-down toggle-icon active"></i>
                    </div>
                    <div class="card-body" id="cardAccount">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">🆔 รหัสผู้รายงาน <span class="required">*</span></label>
                                <input type="text" name="reporter_code" 
                                       value="<?= htmlspecialchars($user['reporter_code'] ?? '') ?>" 
                                       class="form-input" 
                                       required 
                                       placeholder="เช่น R10001">
                                <?php if ($is_edit): ?>
                                    <div class="field-note"><i class="fas fa-edit" style="color:#3b82f6;"></i> สามารถแก้ไขรหัสผู้รายงานได้</div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">👤 ชื่อผู้ใช้ <span class="required">*</span></label>
                                <input type="text" name="username" 
                                       value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                       class="form-input" 
                                       <?= $is_edit ? 'disabled' : 'required' ?> 
                                       placeholder="กรอกชื่อผู้ใช้">
                                <?php if ($is_edit): ?>
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>">
                                    <div class="field-note"><i class="fas fa-lock" style="color:#ef4444;"></i> ไม่สามารถแก้ไขชื่อผู้ใช้ได้</div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group full">
                                <label class="form-label">🎯 บทบาท</label>
                                <select name="role" class="form-input">
                                    <option value="user" <?= ($user['role'] ?? '') == 'user' ? 'selected' : '' ?>>👤 ผู้ใช้ทั่วไป (User)</option>
                                    <option value="admin" <?= ($user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>👑 ผู้ดูแลระบบ (Admin)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- รหัสผ่าน (แทปแยก) -->
                <div class="card" id="passwordCard">
                    <div class="card-header" onclick="toggleCard(this)">
                        <div class="card-header-icon" style="background:rgba(254,243,199,0.8);color:#d97706;"><i class="fas fa-key"></i></div>
                        <h3 class="card-header-title">รหัสผ่าน</h3>
                        <span class="tab-indicator" style="background:rgba(254,243,199,0.8);color:#92400e;">
                            <i class="fas fa-<?= $is_edit ? 'edit' : 'plus' ?>"></i> 
                            <?= $is_edit ? 'เปลี่ยน' : 'ตั้งค่า' ?>
                        </span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="card-body collapsed" id="cardPassword">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">🔒 รหัสผ่าน <?= $is_edit ? '<span class="optional">(เว้นว่างไว้หากไม่เปลี่ยน)</span>' : '<span class="required">*</span>' ?></label>
                                <input type="password" name="password" id="passwordInput" class="form-input" <?= $is_edit ? '' : 'required' ?> placeholder="<?= $is_edit ? 'รหัสผ่านใหม่ (ถ้าต้องการเปลี่ยน)' : 'กรอกรหัสผ่าน' ?>">
                                <div class="field-note">
                                    <i class="fas fa-info-circle" style="color:#3b82f6;"></i> 
                                    <?= $is_edit ? 'กรอกรหัสผ่านใหม่หากต้องการเปลี่ยน' : 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร' ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">✅ ยืนยันรหัสผ่าน</label>
                                <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-input" placeholder="ยืนยันรหัสผ่าน">
                            </div>
                            <div class="form-group full">
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.5rem;">
                                    <button type="button" class="helper-btn" onclick="togglePasswordVisibility('passwordInput')">
                                        <i class="fas fa-eye"></i> แสดงรหัสผ่าน
                                    </button>
                                    <button type="button" class="helper-btn" onclick="togglePasswordVisibility('confirmPasswordInput')">
                                        <i class="fas fa-eye"></i> แสดงยืนยัน
                                    </button>
                                    <?php if ($is_edit): ?>
                                    <button type="button" class="helper-btn danger" onclick="clearPasswordFields()">
                                        <i class="fas fa-undo"></i> ล้าง
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- รายละเอียดเพิ่มเติม (แทปแยก) -->
                <div class="card">
                    <div class="card-header" onclick="toggleCard(this)">
                        <div class="card-header-icon" style="background:rgba(224,231,255,0.8);color:#4338ca;"><i class="fas fa-address-card"></i></div>
                        <h3 class="card-header-title">รายละเอียดเพิ่มเติม</h3>
                        <span class="tab-indicator" style="background:rgba(224,231,255,0.8);color:#4338ca;"><i class="fas fa-chevron-down"></i> เพิ่มเติม</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="card-body collapsed" id="cardDetails">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">📝 ชื่อ-นามสกุล</label>
                                <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" class="form-input" placeholder="กรอกชื่อ-นามสกุล">
                            </div>
                            <div class="form-group">
                                <label class="form-label">📧 อีเมล</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-input" placeholder="example@email.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">📱 เบอร์โทรศัพท์</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-input" placeholder="08x-xxx-xxxx">
                            </div>
                            <div class="form-group">
                                <label class="form-label">🏢 แผนก/หน่วยงาน</label>
                                <select name="department_select" id="departmentSelect" class="form-input">
                                    <option value="">-- กรุณาเลือกแผนก/หน่วยงาน --</option>
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
                                       class="form-input dept-other-input <?= $isCustomDept ? '' : 'hidden' ?>" 
                                       placeholder="ระบุแผนก/หน่วยงานอื่น"
                                       value="<?= $isCustomDept ? htmlspecialchars($currentDept) : '' ?>">
                                <small class="field-note">
                                    💡 หากเลือก "อื่นๆ" กรุณาระบุแผนกในช่องด้านล่าง
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Avatar (แทปแยก) -->
                <div class="card">
                    <div class="card-header" onclick="toggleCard(this)">
                        <div class="card-header-icon" style="background:rgba(237,233,254,0.8);color:#6d28d9;"><i class="fas fa-image"></i></div>
                        <h3 class="card-header-title">รูปโปรไฟล์ (Avatar)</h3>
                        <span class="tab-indicator" style="background:rgba(237,233,254,0.8);color:#6d28d9;"><i class="fas fa-image"></i> รูปภาพ</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="card-body collapsed" id="cardAvatar">
                        <div class="avatar-section">
                            <img id="avatarPreview" 
                                 src="<?= ($is_edit && !empty($user['avatar'])) ? 'avatars/' . htmlspecialchars($user['avatar']) : 'avatars/default.png' ?>" 
                                 class="avatar-preview" alt="Avatar" onerror="this.src='avatars/default.png'">
                            <div class="avatar-upload">
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" class="form-input">
                                <p class="avatar-hint">
                                    📁 รองรับ JPG, PNG, GIF, WebP (สูงสุด 5MB)
                                    <?php if ($is_edit && !empty($user['avatar'])): ?>
                                        <br>📷 รูปปัจจุบัน: <strong><?= htmlspecialchars($user['avatar']) ?></strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึก</button>
                    <a href="users.php" class="btn btn-cancel"><i class="fas fa-times"></i> ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ========== Toggle Card ==========
    function toggleCard(header) {
        const body = header.nextElementSibling;
        const icon = header.querySelector('.toggle-icon');
        
        if (body) {
            body.classList.toggle('collapsed');
            if (icon) {
                icon.classList.toggle('active');
            }
        }
    }

    // ========== Toggle Password Visibility ==========
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        if (input) {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            const btn = input.closest('.form-group').querySelector('.helper-btn');
            if (btn) {
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
                }
                btn.innerHTML = type === 'password' ? ' แสดงรหัสผ่าน' : ' ซ่อนรหัสผ่าน';
                btn.prepend(icon);
            }
        }
    }

    // ========== Clear Password Fields ==========
    function clearPasswordFields() {
        const password = document.getElementById('passwordInput');
        const confirm = document.getElementById('confirmPasswordInput');
        if (password) password.value = '';
        if (confirm) confirm.value = '';
        
        if (password) password.setAttribute('type', 'password');
        if (confirm) confirm.setAttribute('type', 'password');
        
        document.querySelectorAll('#passwordCard .helper-btn').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-eye';
            }
            btn.innerHTML = ' แสดงรหัสผ่าน';
            btn.prepend(icon);
        });
    }

    // ========== Toggle other department ==========
    function toggleOtherDept() {
        const select = document.getElementById('departmentSelect');
        const other = document.getElementById('departmentOther');
        if (select && other) {
            if (select.value === 'อื่นๆ') {
                other.classList.remove('hidden');
                other.focus();
                other.required = true;
            } else {
                other.classList.add('hidden');
                other.value = '';
                other.required = false;
            }
        }
    }
    
    // ========== Initial check ==========
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.card-body').forEach(body => {
            if (body.id !== 'cardAccount') {
                body.classList.add('collapsed');
            }
        });
        
        const select = document.getElementById('departmentSelect');
        const other = document.getElementById('departmentOther');
        if (select && other && select.value === 'อื่นๆ') {
            other.classList.remove('hidden');
            other.required = true;
        }
    });

    // ========== Toggle on change ==========
    document.getElementById('departmentSelect')?.addEventListener('change', toggleOtherDept);

    // ========== Preview avatar ==========
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({ icon: 'warning', title: 'ไฟล์ใหญ่เกินไป', text: 'ขนาดไฟล์ต้องไม่เกิน 5MB' });
                this.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(ev) { document.getElementById('avatarPreview').src = ev.target.result; };
            reader.readAsDataURL(file);
        }
    });

    // ========== Submit form ==========
    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const deptSelect = document.getElementById('departmentSelect');
        const deptOther = document.getElementById('departmentOther');
        
        if (deptSelect && deptSelect.value === 'อื่นๆ') {
            if (!deptOther.value.trim()) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'กรุณาระบุแผนก', 
                    text: 'กรุณากรอกชื่อแผนก/หน่วยงานที่ต้องการเพิ่ม' 
                });
                deptOther.focus();
                return;
            }
        } else if (deptSelect && deptSelect.value !== '' && deptSelect.value !== 'อื่นๆ') {
            deptOther.value = '';
        }
        
        const password = document.getElementById('passwordInput').value;
        const confirm = document.getElementById('confirmPasswordInput').value;

        if (password || confirm) {
            if (password !== confirm) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'รหัสผ่านไม่ตรงกัน', 
                    text: 'กรุณากรอกรหัสผ่านและยืนยันรหัสผ่านให้ตรงกัน' 
                });
                return;
            }
            if (password.length < 8) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'รหัสผ่านสั้นเกินไป', 
                    text: 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร' 
                });
                return;
            }
        }

        const isEdit = document.querySelector('input[name="is_edit"]').value === '1';
        if (isEdit) {
            const usernameHidden = document.querySelector('input[name="username"]');
            if (usernameHidden) {
                const usernameInput = document.querySelector('input[name="username"]');
                if (usernameInput) usernameInput.value = usernameHidden.value;
            }
        }

        const formData = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

        fetch('action.php?action=save_user', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'สำเร็จ', 
                    text: data.message, 
                    timer: 2000, 
                    showConfirmButton: false 
                }).then(() => window.location.href = 'users.php');
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message });
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        })
        .catch(() => {
            Swal.fire({ 
                icon: 'error', 
                title: 'เกิดข้อผิดพลาด', 
                text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้' 
            });
            btn.disabled = false;
            btn.innerHTML = origText;
        });
    });
</script>
<?php include 'includes/footer.php'; ?>