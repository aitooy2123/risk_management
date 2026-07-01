<?php
/**
 * รายการความเสี่ยง (ฉบับสมบูรณ์)
 * 
 * - ฟิลเตอร์: กลุ่มงาน (unit), ประเภท, ระดับ, สถานะ, วันที่เริ่ม
 * - แสดง: ลำดับ, กลุ่มงาน, ประเภท, ระดับ, สถานะ, วันที่, ผู้รายงาน, จัดการ
 * - Admin: เห็นทั้งหมด / User: เห็นเฉพาะของตัวเอง
 * - เลือกลบ, พิมพ์ PDF, พิมพ์ทั้งหมด
 * - Pagination 10 รายการ/หน้า
 * - ✅ แสดงวันที่แบบไม่มีเวลา (d/m/Y)
 * - ✅ สลับสีแถวตาราง
 * - ✅ Badge สีแยกตามระดับและสถานะ
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

if (!function_exists('canModify')) {
    function canModify($risk_user_id) {
        if (!isset($_SESSION['user_id'])) return false;
        if (isAdmin()) return true;
        return $_SESSION['user_id'] == $risk_user_id;
    }
}

$type_filter     = $_GET['risk_type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$group_filter    = $_GET['unit'] ?? '';
$status_filter   = $_GET['status'] ?? '';
$date_from       = $_GET['date_from'] ?? '';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($type_filter) {
    $where .= " AND r.risk_type = ?";
    $params[] = $type_filter;
}
if ($severity_filter) {
    $where .= " AND r.severity = ?";
    $params[] = $severity_filter;
}
if ($group_filter) {
    $where .= " AND r.unit = ?";
    $params[] = $group_filter;
}
if ($status_filter) {
    $where .= " AND r.status = ?";
    $params[] = $status_filter;
}
if ($date_from) {
    $where .= " AND DATE(r.event_datetime) >= ?";
    $params[] = $date_from;
}
if (!isAdmin()) {
    $where .= " AND r.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$countSql  = "SELECT COUNT(*) FROM risks r $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$allIdsSql  = "SELECT r.id FROM risks r $where ORDER BY r.created_at DESC";
$allIdsStmt = $pdo->prepare($allIdsSql);
$allIdsStmt->execute($params);
$allIds = $allIdsStmt->fetchAll(PDO::FETCH_COLUMN);

$dataSql   = "SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id $where ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$risks = $stmt->fetchAll();

$types      = $pdo->query("SELECT DISTINCT risk_type FROM risks")->fetchAll(PDO::FETCH_COLUMN);
$severities = $pdo->query("SELECT DISTINCT severity FROM risks")->fetchAll(PDO::FETCH_COLUMN);
$units      = $pdo->query("SELECT DISTINCT unit FROM risks")->fetchAll(PDO::FETCH_COLUMN);

try {
    $statuses = $pdo->query("SELECT DISTINCT status FROM risks WHERE status IS NOT NULL AND status != ''")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($statuses)) {
        $statuses = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];
    }
} catch (PDOException $e) {
    $statuses = ['ยังไม่ดำเนินการ', 'กำลังดำเนินการ', 'ดำเนินการแล้ว', 'ยุติ'];
}

$csrf_token = generateCsrfToken();

function buildPageUrl($page, $currentParams) {
    $query = $currentParams;
    $query['page'] = $page;
    return 'risks.php?' . http_build_query($query);
}

// Mapping สี Badge ตามระดับ
$severityBadgeMap = [
    'A' => 'bg-blue-100 text-blue-800',
    'B' => 'bg-green-100 text-green-800',
    'C' => 'bg-lime-100 text-lime-800',
    'D' => 'bg-yellow-100 text-yellow-800',
    'F' => 'bg-orange-100 text-orange-800',
    'E' => 'bg-red-100 text-red-800'
];

// Mapping สี Badge ตามสถานะ
$statusBadgeMap = [
    'ยังไม่ดำเนินการ' => 'bg-yellow-100 text-yellow-800',
    'กำลังดำเนินการ' => 'bg-blue-100 text-blue-800',
    'ดำเนินการแล้ว' => 'bg-green-100 text-green-800',
    'ยุติ' => 'bg-gray-100 text-gray-600'
];
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="flex h-screen bg-blue-50/30">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-6 overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-blue-800">📋 รายการความเสี่ยง</h2>
            <a href="risk_form.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 shadow transition">
                <i class="fas fa-plus mr-2"></i> เพิ่มรายการ
            </a>
        </div>

        <form method="GET" class="bg-white rounded-xl shadow-md border border-blue-100 p-5 mb-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fas fa-search text-blue-600 text-lg"></i>
                <h3 class="text-lg font-semibold text-gray-700">ค้นหาความเสี่ยง</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">กลุ่มงาน</label>
                    <select name="unit" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= htmlspecialchars($u) ?>" <?= $group_filter == $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">ประเภท</label>
                    <select name="risk_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">ระดับ</label>
                    <select name="severity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($severities as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $severity_filter == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">สถานะ</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $status_filter == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">ตั้งแต่วันที่</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <a href="risks.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-times mr-2"></i> รีเซ็ต
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm transition">
                    <i class="fas fa-search mr-2"></i> ค้นหา
                </button>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2 mb-4">
            <?php if (isAdmin()): ?>
                <button id="deleteSelected" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 shadow transition">
                    <i class="fas fa-trash mr-2"></i> ลบที่เลือก
                </button>
            <?php endif; ?>
            <button id="printSelected" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 shadow transition">
                <i class="fas fa-print mr-2"></i> พิมพ์ PDF ที่เลือก
            </button>
            <a href="generate_pdf.php?ids=<?= implode(',', $allIds) ?>" target="_blank"
                class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 shadow transition">
                <i class="fas fa-file-pdf mr-2"></i> พิมพ์ทั้งหมด
            </a>
        </div>

        <div class="bg-white rounded-lg shadow border border-blue-100 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b">
                    <tr>
                        <?php if (isAdmin()): ?>
                            <th class="px-4 py-2"><input type="checkbox" id="selectAll" class="rounded"></th>
                        <?php endif; ?>
                        <th class="text-left px-4 py-2">#</th>
                        <th class="text-left px-4 py-2">กลุ่มงาน</th>
                        <th class="text-left px-4 py-2">ประเภท</th>
                        <th class="text-left px-4 py-2">ระดับ</th>
                        <th class="text-left px-4 py-2">สถานะ</th>
                        <th class="text-left px-4 py-2">วันที่</th>
                        <th class="text-left px-4 py-2">ผู้รายงาน</th>
                        <th class="text-left px-4 py-2">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($risks as $index => $risk):
                        $rowNumber = ($page - 1) * $perPage + $index + 1;
                        $rowBg = $index % 2 == 0 ? 'bg-white' : 'bg-gray-50';
                        $sevColor = $severityBadgeMap[$risk['severity']] ?? 'bg-gray-100 text-gray-800';
                        $staColor = $statusBadgeMap[$risk['status']] ?? 'bg-gray-100 text-gray-600';
                    ?>
                        <tr class="border-b border-gray-100 <?= $rowBg ?> hover:bg-blue-50 transition-colors">
                            <?php if (isAdmin()): ?>
                                <td class="px-4 py-2"><input type="checkbox" class="risk-checkbox" value="<?= $risk['id'] ?>"></td>
                            <?php endif; ?>
                            <td class="px-4 py-2"><?= $rowNumber ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($risk['unit'] ?? '-') ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($risk['risk_type'] . ($risk['risk_type_other'] ? ' (' . $risk['risk_type_other'] . ')' : '')) ?></td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $sevColor ?>">
                                    <?= htmlspecialchars($risk['severity']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <?php if (!empty($risk['status'])): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $staColor ?>">
                                        <?= htmlspecialchars($risk['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><?= date('d/m/Y', strtotime($risk['event_datetime'])) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></td>
                            <td class="px-4 py-2">
                                <div class="flex gap-1">
                                    <a href="view_risk.php?id=<?= $risk['id'] ?>" class="text-blue-500 hover:text-blue-700" title="ดู">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="generate_pdf.php?id=<?= $risk['id'] ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="พิมพ์ PDF">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if (canModify($risk['user_id'])): ?>
                                        <a href="risk_form.php?id=<?= $risk['id'] ?>" class="text-green-500 hover:text-green-700" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="text-red-500 hover:text-red-700 delete-single" data-id="<?= $risk['id'] ?>" title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between mt-4 gap-2">
                <p class="text-sm text-gray-600">แสดง <?= count($risks) ?> จาก <?= $totalRows ?> รายการ</p>
                <nav class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildPageUrl($page - 1, $_GET) ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-100">&laquo; ก่อนหน้า</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="<?= buildPageUrl($i, $_GET) ?>" class="px-3 py-1 rounded border <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= buildPageUrl($page + 1, $_GET) ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-100">ถัดไป &raquo;</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<meta name="csrf-token" content="<?= $csrf_token ?>">

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    <?php if (isAdmin()): ?>
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.risk-checkbox').forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('deleteSelected').addEventListener('click', function() {
            const selected = document.querySelectorAll('.risk-checkbox:checked');
            if (selected.length === 0) {
                Swal.fire('กรุณาเลือกรายการที่ต้องการลบ', '', 'warning');
                return;
            }
            const ids = Array.from(selected).map(cb => cb.value);
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: `คุณต้องการลบ ${ids.length} รายการ?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('action.php?action=delete_risks', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'   // ✅ เพิ่ม header
                            },
                            body: JSON.stringify({ ids: ids, csrf_token: csrfToken })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('ลบสำเร็จ', '', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                            }
                        });
                }
            });
        });
    <?php endif; ?>

    document.querySelectorAll('.delete-single').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'คุณต้องการลบรายการนี้?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('action.php?action=delete_risks', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'   // ✅ เพิ่ม header
                            },
                            body: JSON.stringify({ ids: [id], csrf_token: csrfToken })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('ลบสำเร็จ', '', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                            }
                        });
                }
            });
        });
    });

    document.getElementById('printSelected').addEventListener('click', function() {
        const selected = document.querySelectorAll('.risk-checkbox:checked');
        if (selected.length === 0) {
            Swal.fire('กรุณาเลือกรายการที่ต้องการพิมพ์', '', 'warning');
            return;
        }
        const ids = Array.from(selected).map(cb => cb.value).join(',');
        window.open('generate_pdf.php?ids=' + ids, '_blank');
    });
</script>
<?php include 'includes/footer.php'; ?>