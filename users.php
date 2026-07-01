<?php
/**
 * หน้าจัดการผู้ใช้ (CRUD) - เฉพาะ Admin เท่านั้น
 * - แสดงรายการผู้ใช้ในรูปแบบตาราง
 * - มีปุ่มเพิ่ม แก้ไข ลบ
 * - ลบได้หลายรายการพร้อมกัน
 * - แบ่งหน้า (pagination) 10 รายการต่อหน้า
 * - ค้นหาด้วยชื่อผู้ใช้, บทบาท, วันที่สมัคร
 * - ป้องกันการลบตัวเองและ Admin คนสุดท้าย
 * - ✅ สลับสีแถวตาราง (alternating row colors)
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

// รับค่าฟิลเตอร์
$search    = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// สร้าง WHERE clause
$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND username LIKE ?";
    $params[] = "%{$search}%";
}
if ($role_filter !== '') {
    $where .= " AND role = ?";
    $params[] = $role_filter;
}
if ($date_from !== '') {
    $where .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

// นับจำนวนผู้ใช้ทั้งหมดตาม filter
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// ดึงข้อมูลผู้ใช้เฉพาะหน้าปัจจุบัน
$dataSql = "SELECT id, username, role, avatar, reporter_code, created_at FROM users $where ORDER BY id LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$csrf_token = generateCsrfToken();

// ฟังก์ชันสร้าง URL pagination โดยคง filter parameters
function buildUserPageUrl($page, $currentParams) {
    $query = $currentParams;
    $query['page'] = $page;
    return 'users.php?' . http_build_query($query);
}
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="flex h-screen bg-blue-50/30">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-6 overflow-y-auto">
        
        <!-- หัวข้อ + ปุ่มเพิ่ม -->
        <div class="flex flex-wrap justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-blue-800">
                    <i class="fas fa-users-cog mr-2 text-blue-600"></i>จัดการผู้ใช้
                </h2>
                <p class="text-sm text-gray-500 mt-1">จัดการบัญชีผู้ใช้ในระบบ ทั้งหมด <span class="font-bold text-blue-600"><?= $totalUsers ?></span> คน</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="deleteSelected" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow transition">
                    <i class="fas fa-trash mr-2"></i> ลบที่เลือก
                </button>
                <a href="user_form.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow transition">
                    <i class="fas fa-user-plus mr-2"></i> เพิ่มผู้ใช้
                </a>
            </div>
        </div>

        <!-- ฟอร์มค้นหา/กรอง -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-blue-100 p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm text-gray-500 mb-1">ค้นหาชื่อผู้ใช้</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ชื่อผู้ใช้..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-blue-300 outline-none">
            </div>
            <div>
                <label class="block text-sm text-gray-500 mb-1">บทบาท</label>
                <select name="role" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
                    <option value="">ทั้งหมด</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-500 mb-1">สมัครตั้งแต่</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            </div>
            <div>
                <label class="block text-sm text-gray-500 mb-1">สมัครถึง</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm shadow-sm">
                    <i class="fas fa-search mr-1"></i> ค้นหา
                </button>
                <a href="users.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm border border-gray-200">
                    <i class="fas fa-times mr-1"></i> รีเซ็ต
                </a>
            </div>
        </form>

        <!-- Card ตารางผู้ใช้ -->
        <div class="bg-white rounded-xl shadow-md border border-blue-100 overflow-hidden">
            <!-- หัวตาราง -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-4 flex items-center">
                <i class="fas fa-users text-white text-xl mr-3"></i>
                <h3 class="text-white font-semibold text-lg">รายชื่อผู้ใช้ทั้งหมด</h3>
                <span class="ml-auto bg-white/20 text-white text-xs font-medium px-3 py-1 rounded-full">
                    <?= $totalUsers ?> รายการ
                </span>
            </div>

            <!-- ตาราง -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-blue-50 border-b border-blue-200">
                            <th class="px-4 py-3 text-center">
                                <input type="checkbox" id="selectAll" class="rounded">
                            </th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">#</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">ชื่อผู้ใช้</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">รหัสผู้รายงาน</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">บทบาท</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">Avatar</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">วันที่สมัคร</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-blue-700 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-users-slash text-3xl block mb-2"></i>
                                    ไม่พบผู้ใช้ตามเงื่อนไขค้นหา
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $index => $user): 
                                // ✅ สลับสีพื้นหลัง: แถวคู่สีขาว, แถวคี่สีเทาอ่อน
                                $rowBg = $index % 2 == 0 ? 'bg-white' : 'bg-gray-50';
                            ?>
                            <tr class="border-b border-blue-50 <?= $rowBg ?> hover:bg-blue-50/70 transition-colors duration-150">
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" class="user-checkbox" value="<?= $user['id'] ?>">
                                </td>
                                <td class="px-4 py-3 text-gray-500 font-medium"><?= ($page - 1) * $perPage + $index + 1 ?></td>
                                
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <div class="flex items-center">
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full mr-2">คุณ</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($user['username']) ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($user['reporter_code'] ?? '-') ?></td>
                                
                                <td class="px-4 py-3">
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-sm">
                                            <i class="fas fa-crown mr-1"></i> Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200">
                                            <i class="fas fa-user mr-1"></i> User
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3">
                                    <img src="avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" 
                                         class="w-10 h-10 rounded-full object-cover border-2 border-blue-100 shadow-sm hover:scale-110 transition-transform duration-200"
                                         alt="avatar">
                                </td>
                                
                                <td class="px-4 py-3 text-gray-600 text-sm">
                                    <i class="far fa-calendar-alt text-blue-400 mr-1"></i>
                                    <?= getThaiDate($user['created_at']) ?>
                                </td>
                                
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="user_form.php?id=<?= $user['id'] ?>" 
                                           class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-all duration-200" 
                                           title="แก้ไขผู้ใช้">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-all duration-200 delete-single" 
                                                    data-id="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    title="ลบผู้ใช้">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-300 bg-gray-50 p-2 rounded-lg cursor-not-allowed" title="ไม่สามารถลบตัวเองได้">
                                                <i class="fas fa-trash-alt"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ส่วนท้ายตาราง -->
            <div class="bg-gray-50 px-6 py-3 border-t border-blue-100 flex justify-between items-center text-xs text-gray-500">
                <span>แสดง <?= count($users) ?> จาก <?= $totalUsers ?> รายการ</span>
                <span><i class="fas fa-shield-alt text-blue-400 mr-1"></i> เฉพาะผู้ดูแลระบบเท่านั้น</span>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between mt-4 gap-2">
                <p class="text-sm text-gray-600">หน้า <?= $page ?> จาก <?= $totalPages ?></p>
                <nav class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildUserPageUrl($page - 1, $_GET) ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-100">&laquo; ก่อนหน้า</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="<?= buildUserPageUrl($i, $_GET) ?>" class="px-3 py-1 rounded border <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= buildUserPageUrl($page + 1, $_GET) ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-100">ถัดไป &raquo;</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <!-- คำแนะนำ -->
        <div class="mt-6 bg-blue-50/50 rounded-lg p-4 border border-blue-100 text-sm text-gray-600">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-500 text-lg mr-3 mt-0.5"></i>
                <div>
                    <p class="font-medium text-blue-700">📌 หมายเหตุ</p>
                    <ul class="list-disc ml-4 mt-1 space-y-1">
                        <li><span class="font-medium">Admin</span> สามารถเพิ่ม/แก้ไข/ลบผู้ใช้ได้ทั้งหมด</li>
                        <li><span class="font-medium">ไม่สามารถลบตัวเอง</span> หรือ <span class="font-medium">Admin คนสุดท้าย</span> ได้</li>
                        <li>การลบผู้ใช้จะลบ <span class="font-medium">ข้อมูลความเสี่ยง</span> ที่ผู้ใช้คนนั้นสร้างไว้ทั้งหมดด้วย</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<meta name="csrf-token" content="<?= $csrf_token ?>">

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// เลือกทั้งหมด
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
});

// ลบหลายรายการ
document.getElementById('deleteSelected').addEventListener('click', function() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    if (checked.length === 0) {
        Swal.fire('กรุณาเลือกรายการ', '', 'warning');
        return;
    }
    const ids = Array.from(checked).map(cb => cb.value);
    Swal.fire({
        title: '⚠️ ยืนยันการลบหลายรายการ',
        html: `คุณต้องการลบผู้ใช้ ${ids.length} คนใช่หรือไม่?<br><span class="text-red-500 text-sm">⚠️ ข้อมูลความเสี่ยงที่เกี่ยวข้องจะถูกลบด้วย!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('action.php?action=delete_users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('ลบสำเร็จ', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                }
            })
            .catch(() => Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถลบข้อมูลได้', 'error'));
        }
    });
});

// ลบรายการเดียว
document.querySelectorAll('.delete-single').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const username = this.dataset.username;
        Swal.fire({
            title: '⚠️ ยืนยันการลบ',
            html: `คุณต้องการลบผู้ใช้ <strong>"${username}"</strong> ใช่หรือไม่?<br><br><span class="text-red-500 text-sm">⚠️ ข้อมูลความเสี่ยงที่เกี่ยวข้องจะถูกลบด้วย!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('action.php?action=delete_users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: [id], csrf_token: csrfToken })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('ลบสำเร็จ', '', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถลบข้อมูลได้', 'error'));
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>