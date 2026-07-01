<?php
/**
 * หน้าแสดงรายละเอียดความเสี่ยง (View Risk)
 * - ดีไซน์สวย อ่านง่าย แยกส่วนชัดเจน
 * - แสดงข้อมูลครบทุกฟิลด์ (ยกเว้น Consent ถูกนำออกแล้ว)
 * - รองรับการแสดงผลทั้ง Admin และ User
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

$id = $_GET['id'] ?? null;
if (!$id) redirect('risks.php');

$stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$id]);
$risk = $stmt->fetch();

if (!$risk) redirect('risks.php');
if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) redirect('risks.php');

$canEdit = (isAdmin() || $risk['user_id'] == $_SESSION['user_id']);

$severityMap = [
    'A' => 'มีโอกาสเกิดแต่ยังไม่เกิดขึ้น',
    'B' => 'เกิด ยังไม่ถึงตัวบุคคล ไม่กระทบงาน',
    'C' => 'เกิดถึงตัวบุคคล กระทบเบื้องต้น แก้ไขเองได้',
    'D' => 'เกิดถึงตัวบุคคล กระทบปานกลาง ต้องให้เพื่อนช่วย',
    'F' => 'เกิดถึงตัวบุคคล กระทบสูง ต้องแจ้งหัวหน้า',
    'E' => 'เกิดถึงตัวบุคคล กระทบสูงสุด รายงานผู้บริหาร'
];

$severityBadgeColors = [
    'A' => 'bg-blue-100 text-blue-800 border-blue-300',
    'B' => 'bg-green-100 text-green-800 border-green-300',
    'C' => 'bg-lime-100 text-lime-800 border-lime-300',
    'D' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
    'F' => 'bg-orange-100 text-orange-800 border-orange-300',
    'E' => 'bg-red-100 text-red-800 border-red-300'
];

$statusBadgeColors = [
    'ยังไม่ดำเนินการ' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
    'กำลังดำเนินการ' => 'bg-blue-100 text-blue-800 border-blue-300',
    'ดำเนินการแล้ว' => 'bg-green-100 text-green-800 border-green-300',
    'ยุติ' => 'bg-gray-100 text-gray-600 border-gray-300'
];

function renderBadge($text, $colorMap, $default = 'bg-gray-100 text-gray-600 border-gray-300') {
    $class = $colorMap[$text] ?? $default;
    return "<span class=\"px-3 py-1 rounded-full text-xs font-semibold border {$class}\">" . htmlspecialchars($text) . "</span>";
}
?>
<?php include 'includes/header.php'; ?>

<style>
    .view-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        border-radius: 1rem;
        padding: 1.5rem 2rem;
        color: white;
        box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
    }
    .info-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .info-card:hover {
        box-shadow: 0 8px 20px -6px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
    }
    .detail-text {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 1.25rem;
        line-height: 1.8;
        color: #334155;
    }
    .objective-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 1rem;
        padding: 1.25rem;
    }
    .info-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }
    .info-value {
        font-weight: 500;
        color: #1e293b;
    }
</style>

<div class="flex h-screen bg-gray-50/50">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-6 overflow-y-auto">
        
        <!-- Header -->
        <div class="view-header mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <p class="text-blue-200 text-sm mb-1">รายละเอียดความเสี่ยง</p>
                    <h1 class="text-2xl font-bold">
                        #<?= $risk['id'] ?> — <?= htmlspecialchars($risk['risk_type']) ?>
                    </h1>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <a href="risks.php" class="bg-white/20 text-white px-4 py-2 rounded-lg hover:bg-white/30 transition backdrop-blur-sm text-sm">
                        <i class="fas fa-arrow-left mr-2"></i>กลับ
                    </a>
                    <?php if ($canEdit): ?>
                        <a href="risk_form.php?id=<?= $id ?>" class="bg-white/20 text-white px-4 py-2 rounded-lg hover:bg-white/30 transition backdrop-blur-sm text-sm">
                            <i class="fas fa-edit mr-2"></i>แก้ไข
                        </a>
                    <?php endif; ?>
                    <a href="generate_pdf.php?id=<?= $id ?>" target="_blank" class="bg-white text-blue-700 px-4 py-2 rounded-lg hover:bg-gray-100 transition text-sm font-medium">
                        <i class="fas fa-file-pdf mr-2"></i>พิมพ์ PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- เนื้อหาหลัก -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- คอลัมน์ซ้าย: ข้อมูลทั่วไป + รายละเอียด -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- ข้อมูลทั่วไป -->
                <div class="info-card">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-blue-500 rounded-full"></span>
                        ข้อมูลทั่วไป
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="info-label">กลุ่มงาน</div>
                            <div class="info-value"><?= htmlspecialchars($risk['unit'] . ($risk['unit_other'] ? ' (' . $risk['unit_other'] . ')' : '')) ?></div>
                        </div>
                        <div>
                            <div class="info-label">รหัสผู้รายงาน</div>
                            <div class="info-value"><?= htmlspecialchars($risk['reporter_code'] ?? '-') ?></div>
                        </div>
                        <div>
                            <div class="info-label">ผู้รายงาน</div>
                            <div class="info-value"><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></div>
                        </div>
                        <div>
                            <div class="info-label">วันที่เกิดเหตุการณ์</div>
                            <div class="info-value"><?= getThaiDate($risk['event_datetime']) ?></div>
                        </div>
                        <div>
                            <div class="info-label">วันที่รายงาน</div>
                            <div class="info-value"><?= getThaiDate($risk['report_datetime']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- รายละเอียดเหตุการณ์ -->
                <div class="info-card">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-indigo-500 rounded-full"></span>
                        รายละเอียดเหตุการณ์
                    </h3>
                    <div class="detail-text">
                        <?= nl2br(htmlspecialchars($risk['detail'])) ?>
                    </div>
                </div>

                <!-- การแก้ไขเบื้องต้น -->
                <div class="info-card">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-teal-500 rounded-full"></span>
                        การแก้ไขเบื้องต้น
                    </h3>
                    <div class="detail-text">
                        <?= nl2br(htmlspecialchars($risk['initial_solution'])) ?>
                    </div>
                </div>

                <!-- ปัญหาและข้อเสนอแนะ -->
                <div class="info-card">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-orange-500 rounded-full"></span>
                        ปัญหาและข้อเสนอแนะ
                    </h3>
                    <div class="detail-text">
                        <?= nl2br(htmlspecialchars($risk['suggestion'])) ?>
                    </div>
                </div>
            </div>

            <!-- คอลัมน์ขวา: สถานะ / วัตถุประสงค์ -->
            <div class="space-y-6">
                
                <!-- วัตถุประสงค์ -->
                <div class="objective-box">
                    <h3 class="font-semibold text-blue-900 mb-3 flex items-center gap-2">
                        <i class="fas fa-bullseye text-blue-600"></i> วัตถุประสงค์
                    </h3>
                    <ul class="text-sm text-blue-800 space-y-1.5 list-disc list-inside leading-relaxed">
                        <li>แก้ไขเหตุการณ์อย่างเหมาะสม ทันเวลา</li>
                        <li>ป้องกัน ลดความเสียหายที่อาจเกิดขึ้น</li>
                        <li>หาแนวทางป้องกันและพัฒนาองค์กร</li>
                    </ul>
                </div>

                <!-- ระดับความรุนแรง -->
                <div class="info-card">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-red-400 rounded-full"></span>
                        ระดับความรุนแรง
                    </h3>
                    <?= renderBadge("ระดับ {$risk['severity']} — {$severityMap[$risk['severity']]}", $severityBadgeColors) ?>
                </div>

                <!-- สถานะการดำเนินการ -->
                <div class="info-card">
                    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-blue-500 rounded-full"></span>
                        สถานะการดำเนินการ
                    </h3>
                    <?= renderBadge($risk['status'] ?? 'ยังไม่ดำเนินการ', $statusBadgeColors) ?>
                </div>

                <!-- ข้อมูลเวลา -->
                <div class="info-card text-xs text-gray-500 space-y-1">
                    <p>📅 สร้างเมื่อ: <?= getThaiDate($risk['created_at']) ?></p>
                    <p>🔄 แก้ไขล่าสุด: <?= getThaiDate($risk['updated_at']) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>