<?php
/**
 * ฟอร์มเพิ่ม/แก้ไขผู้ใช้ (Frontend) - เฉพาะ Admin
 * - ดีไซน์ Card Layout สวยงาม
 * - รองรับการอัปโหลด Avatar พร้อมแสดงตัวอย่าง
 * - ถ้าแก้ไขและไม่กรอกรหัสผ่าน จะคงรหัสผ่านเดิม
 * - แสดงวันที่ปัจจุบัน (พ.ศ.) ในฟอร์ม และห้ามเลือกวันล่วงหน้า
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

// วันที่ปัจจุบัน (สำหรับแสดงผลภาษาไทย และจำกัดห้ามเลือกอนาคต)
$currentDate = date('Y-m-d');
$thaiDate = getThaiDate($currentDate);
?>
<?php include 'includes/header.php'; ?>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="flex h-[calc(100vh-4rem)] bg-blue-50/30">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-3xl mx-auto">
            <!-- หัวข้อ -->
            <h2 class="text-2xl font-bold mb-6 text-blue-800 flex items-center">
                <i class="fas fa-user-edit mr-3 text-blue-600"></i><?= $title ?>
            </h2>

            <!-- ฟอร์ม -->
            <form id="userForm" method="POST" action="action.php?action=save_user" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="id" value="<?= $id ?>">

                <!-- การ์ด 1: ข้อมูลบัญชี -->
                <div class="bg-white rounded-xl shadow-md border border-blue-100 overflow-hidden">
                    <div class="bg-blue-50 px-5 py-4 border-b border-blue-100">
                        <h3 class="text-lg font-semibold text-blue-800">
                            <i class="fas fa-user-circle mr-2"></i>ข้อมูลบัญชี
                        </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- ชื่อผู้ใช้ -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">
                                👤 ชื่อผู้ใช้ <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                   class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition" 
                                   required placeholder="กรอกชื่อผู้ใช้">
                        </div>
                        
                        <!-- รหัสผ่าน -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">
                                🔒 รหัสผ่าน
                                <?php if ($is_edit): ?>
                                    <span class="text-sm text-gray-500 font-normal">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</span>
                                <?php else: ?>
                                    <span class="text-red-500">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" name="password" 
                                   class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition" 
                                   <?= $is_edit ? '' : 'required' ?> 
                                   placeholder="<?= $is_edit ? 'กรอกรหัสผ่านใหม่ (ถ้าต้องการเปลี่ยน)' : 'กรอกรหัสผ่าน' ?>">
                        </div>

                        <!-- ยืนยันรหัสผ่าน -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">✅ ยืนยันรหัสผ่าน</label>
                            <input type="password" name="confirm_password" 
                                   class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition" 
                                   placeholder="ยืนยันรหัสผ่าน">
                        </div>
                    </div>
                </div>

                <!-- การ์ด 2: รายละเอียดเพิ่มเติม -->
                <div class="bg-white rounded-xl shadow-md border border-blue-100 overflow-hidden">
                    <div class="bg-blue-50 px-5 py-4 border-b border-blue-100">
                        <h3 class="text-lg font-semibold text-blue-800">
                            <i class="fas fa-address-card mr-2"></i>รายละเอียดเพิ่มเติม
                        </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- วันที่ลงทะเบียน -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">📅 วันที่ลงทะเบียน</label>
                            <input type="date" name="register_date" value="<?= $currentDate ?>" max="<?= $currentDate ?>" 
                                   class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition">
                            <p class="text-sm text-gray-500 mt-1">📌 <?= $thaiDate ?></p>
                        </div>

                        <!-- บทบาท -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">🎯 บทบาท</label>
                            <select name="role" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition">
                                <option value="user" <?= ($user['role'] ?? '') == 'user' ? 'selected' : '' ?>>👤 ผู้ใช้ทั่วไป</option>
                                <option value="admin" <?= ($user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>👑 ผู้ดูแลระบบ (Admin)</option>
                            </select>
                        </div>

                        <!-- Avatar -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">🖼️ รูปโปรไฟล์ (Avatar)</label>
                            <div class="flex items-start space-x-4">
                                <!-- ตัวอย่างรูป -->
                                <div class="flex-shrink-0">
                                    <img id="avatarPreview" 
                                         src="<?= ($is_edit && !empty($user['avatar'])) ? 'avatars/' . htmlspecialchars($user['avatar']) : 'https://via.placeholder.com/100?text=Preview' ?>" 
                                         class="w-20 h-20 rounded-full border-2 border-blue-200 object-cover shadow-sm" 
                                         alt="Avatar Preview">
                                </div>
                                <div class="flex-1">
                                    <input type="file" name="avatar" id="avatarInput" accept="image/*" 
                                           class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition">
                                    <p class="text-sm text-gray-500 mt-1">📁 อัปโหลดไฟล์ .jpg, .png, .gif (ขนาดไม่เกิน 2MB)</p>
                                    <?php if ($is_edit && !empty($user['avatar'])): ?>
                                        <p class="text-sm text-blue-600 mt-1">📷 รูปปัจจุบัน: <?= htmlspecialchars($user['avatar']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ปุ่มดำเนินการ -->
                <div class="flex items-center space-x-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow-md hover:shadow-lg transition font-semibold flex items-center">
                        <i class="fas fa-save mr-2"></i> บันทึก
                    </button>
                    <a href="users.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition font-semibold flex items-center">
                        <i class="fas fa-times mr-2"></i> ยกเลิก
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// แสดงตัวอย่างรูปภาพก่อนอัปโหลด
document.getElementById('avatarInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('avatarPreview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// จัดการส่งฟอร์ม
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const password = document.querySelector('input[name="password"]').value;
    const confirm = document.querySelector('input[name="confirm_password"]').value;

    // ถ้ามีการกรอกรหัสผ่าน (หรือยืนยัน)
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

    const formData = new FormData(this);

    fetch('action.php?action=save_user', {
        method: 'POST',
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
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: data.message
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>