<?php

/**
 * หน้าแสดงรายละเอียดความเสี่ยง (View Risk)
 * - ดีไซน์สวย อ่านง่าย แยกส่วนชัดเจน
 * - แสดงข้อมูลครบทุกฟิลด์
 * - รองรับการแสดงผลทั้ง Admin และ User
 * - ปรับขนาดให้พอดีกับหน้าจอ Notebook 14 นิ้ว (1366x768 ขึ้นไป)
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('risks.php');
}

// ดึงข้อมูลความเสี่ยง
$stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$id]);
$risk = $stmt->fetch();

if (!$risk) {
    redirect('risks.php');
}

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) {
    redirect('risks.php');
}

// สิทธิ์การแก้ไข
$canEdit = (isAdmin() || $risk['user_id'] == $_SESSION['user_id']);

// แผนที่ระดับความรุนแรง
$severityMap = [
    'A' => 'มีโอกาสเกิดแต่ยังไม่เกิดขึ้น',
    'B' => 'เกิด ยังไม่ถึงตัวบุคคล ไม่กระทบงาน',
    'C' => 'เกิดถึงตัวบุคคล กระทบเบื้องต้น แก้ไขเองได้',
    'D' => 'เกิดถึงตัวบุคคล กระทบปานกลาง ต้องให้เพื่อนช่วย',
    'F' => 'เกิดถึงตัวบุคคล กระทบสูง ต้องแจ้งหัวหน้า',
    'E' => 'เกิดถึงตัวบุคคล กระทบสูงสุด รายงานผู้บริหาร'
];

// สีของ Badge ตามระดับความรุนแรง
$severityBadgeColors = [
    'A' => 'bg-blue-100 text-blue-800 border-blue-300',
    'B' => 'bg-green-100 text-green-800 border-green-300',
    'C' => 'bg-lime-100 text-lime-800 border-lime-300',
    'D' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
    'F' => 'bg-orange-100 text-orange-800 border-orange-300',
    'E' => 'bg-red-100 text-red-800 border-red-300'
];

// สีของ Badge ตามสถานะ
$statusBadgeColors = [
    'ยังไม่ดำเนินการ' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
    'กำลังดำเนินการ' => 'bg-blue-100 text-blue-800 border-blue-300',
    'ดำเนินการแล้ว' => 'bg-green-100 text-green-800 border-green-300',
    'ยุติ' => 'bg-gray-100 text-gray-600 border-gray-300'
];

/**
 * สร้าง Badge HTML
 */
function renderBadge($text, $colorMap, $default = 'bg-gray-100 text-gray-600 border-gray-300')
{
    $class = $colorMap[$text] ?? $default;
    return "<span class=\"badge-status {$class}\">" . htmlspecialchars($text) . "</span>";
}

// ✅ ใช้ฟังก์ชัน getThaiDate() จาก functions.php (ไม่ต้องประกาศซ้ำ)

// ตรวจสอบสถานะปัจจุบัน
$currentStatus = !empty($risk['status']) ? $risk['status'] : 'ยังไม่ดำเนินการ';
?>
<?php include 'includes/header.php'; ?>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --primary-light: #eff6ff;
        --surface: #ffffff;
        --surface-secondary: #f8fafc;
        --border: #e2e8f0;
        --border-light: #f1f5f9;
        --text: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
    }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
        font-size: 14px;
    }

    /* ===== View Header ===== */
    .view-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1rem;
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.25rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
    }

    .view-header::before {
        content: '';
        position: absolute;
        top: -30%;
        right: -5%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.06) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .view-header::after {
        content: '';
        position: absolute;
        bottom: -20%;
        left: -3%;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.04) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .view-header p {
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
        position: relative;
        z-index: 1;
    }

    .view-header h1 {
        font-size: 1.3rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .btn-header {
        padding: 0.4rem 0.85rem;
        border-radius: 0.5rem;
        font-size: 0.78rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: all 0.2s;
        position: relative;
        z-index: 1;
        white-space: nowrap;
    }

    .btn-header:hover {
        transform: translateY(-1px);
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        backdrop-filter: blur(10px);
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-edit {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        backdrop-filter: blur(10px);
    }

    .btn-edit:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-pdf {
        background: white;
        color: #1e40af;
        font-weight: 600;
    }

    .btn-pdf:hover {
        background: #f1f5f9;
    }

    /* ===== Info Card ===== */
    .info-card {
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        padding: 1rem 1.25rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        transition: box-shadow 0.2s ease;
    }

    .info-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .info-card-title {
        font-size: 0.82rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-card-title .dot {
        width: 4px;
        height: 18px;
        border-radius: 2px;
        flex-shrink: 0;
    }

    .dot-blue { background: #3b82f6; }
    .dot-indigo { background: #6366f1; }
    .dot-teal { background: #14b8a6; }
    .dot-orange { background: #f97316; }
    .dot-red { background: #ef4444; }

    /* ===== Info Grid ===== */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.65rem 1rem;
    }

    .info-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.65rem 1rem;
    }

    .info-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 0.15rem;
    }

    .info-value {
        font-size: 0.82rem;
        font-weight: 500;
        color: #1e293b;
        word-break: break-word;
    }

    /* ===== Detail Box ===== */
    .detail-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.6rem;
        padding: 0.85rem 1rem;
        font-size: 0.82rem;
        line-height: 1.7;
        color: #334155;
        max-height: 180px;
        overflow-y: auto;
    }

    .detail-box::-webkit-scrollbar {
        width: 4px;
    }

    .detail-box::-webkit-scrollbar-track {
        background: transparent;
    }

    .detail-box::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .detail-box::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* ===== Objective Box ===== */
    .objective-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
    }

    .objective-box h3 {
        font-size: 0.82rem;
        font-weight: 700;
        color: #1e40af;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .objective-box ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .objective-box ul li {
        font-size: 0.73rem;
        color: #1e40af;
        padding: 0.3rem 0;
        display: flex;
        align-items: flex-start;
        gap: 0.4rem;
        line-height: 1.5;
    }

    .objective-box ul li::before {
        content: '•';
        color: #3b82f6;
        font-weight: bold;
        flex-shrink: 0;
    }

    /* ===== Badge Status ===== */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.73rem;
        font-weight: 600;
        border: 1px solid;
        white-space: nowrap;
    }

    /* ===== Time Info ===== */
    .time-info {
        font-size: 0.72rem;
        color: #94a3b8;
    }

    .time-info p {
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    /* ===== Empty State ===== */
    .text-muted {
        color: #94a3b8;
        font-style: italic;
    }

    /* ===== Responsive ===== */
    @media (max-width: 1024px) {
        .info-grid-3 {
            grid-template-columns: repeat(2, 1fr);
        }

        .detail-box {
            max-height: 150px;
        }
    }

    @media (max-width: 768px) {
        .view-header {
            padding: 1rem 1.25rem;
        }

        .view-header h1 {
            font-size: 1.1rem;
        }

        .info-grid,
        .info-grid-3 {
            grid-template-columns: 1fr;
        }

        .detail-box {
            max-height: 130px;
            font-size: 0.78rem;
        }

        .btn-header {
            padding: 0.35rem 0.65rem;
            font-size: 0.72rem;
        }

        .info-card {
            padding: 0.85rem 1rem;
        }
    }

    @media (max-width: 480px) {
        .view-header h1 {
            font-size: 1rem;
        }

        .btn-header {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-3 md:p-4 overflow-y-auto">

        <!-- Header -->
        <div class="view-header">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <p>📋 รายละเอียดความเสี่ยง</p>
                    <h1>
                        #<?= htmlspecialchars($risk['id']) ?> — <?= htmlspecialchars($risk['risk_type']) ?>
                        <?php if (!empty($risk['risk_type_other'])): ?>
                            <span class="text-blue-200 text-sm font-normal">(<?= htmlspecialchars($risk['risk_type_other']) ?>)</span>
                        <?php endif; ?>
                    </h1>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <a href="risks.php" class="btn-header btn-back">
                        <i class="fas fa-arrow-left text-xs"></i> กลับ
                    </a>
                    <?php if ($canEdit): ?>
                        <a href="risk_form.php?id=<?= htmlspecialchars($id) ?>" class="btn-header btn-edit">
                            <i class="fas fa-edit text-xs"></i> แก้ไข
                        </a>
                    <?php endif; ?>
                    <a href="generate_pdf.php?id=<?= htmlspecialchars($id) ?>" target="_blank" class="btn-header btn-pdf">
                        <i class="fas fa-file-pdf text-xs"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- เนื้อหาหลัก -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- ========== คอลัมน์ซ้าย: ข้อมูลทั่วไป + รายละเอียด ========== -->
            <div class="lg:col-span-2 space-y-4">

                <!-- ข้อมูลทั่วไป -->
                <div class="info-card">
                    <h3 class="info-card-title">
                        <span class="dot dot-blue"></span> ข้อมูลทั่วไป
                    </h3>
                    <div class="info-grid-3">
                        <div>
                            <div class="info-label">กลุ่มงาน</div>
                            <div class="info-value">
                                <?= htmlspecialchars($risk['unit']) ?>
                                <?php if (!empty($risk['unit_other'])): ?>
                                    <span class="text-gray-400">(<?= htmlspecialchars($risk['unit_other']) ?>)</span>
                                <?php endif; ?>
                            </div>
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
                    <h3 class="info-card-title">
                        <span class="dot dot-indigo"></span> รายละเอียดเหตุการณ์
                    </h3>
                    <div class="detail-box">
                        <?php if (!empty($risk['detail'])): ?>
                            <?= nl2br(htmlspecialchars($risk['detail'])) ?>
                        <?php else: ?>
                            <span class="text-muted">ไม่มีข้อมูล</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- การแก้ไขเบื้องต้น -->
                <div class="info-card">
                    <h3 class="info-card-title">
                        <span class="dot dot-teal"></span> การแก้ไขเบื้องต้น
                    </h3>
                    <div class="detail-box">
                        <?php if (!empty($risk['initial_solution'])): ?>
                            <?= nl2br(htmlspecialchars($risk['initial_solution'])) ?>
                        <?php else: ?>
                            <span class="text-muted">ไม่มีข้อมูล</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ปัญหาและข้อเสนอแนะ -->
                <div class="info-card">
                    <h3 class="info-card-title">
                        <span class="dot dot-orange"></span> ปัญหาและข้อเสนอแนะ
                    </h3>
                    <div class="detail-box">
                        <?php if (!empty($risk['suggestion'])): ?>
                            <?= nl2br(htmlspecialchars($risk['suggestion'])) ?>
                        <?php else: ?>
                            <span class="text-muted">ไม่มีข้อมูล</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ========== คอลัมน์ขวา: สถานะ / วัตถุประสงค์ ========== -->
            <div class="space-y-4">

                <!-- วัตถุประสงค์ -->
                <div class="objective-box">
                    <h3>
                        <i class="fas fa-bullseye"></i> วัตถุประสงค์
                    </h3>
                    <ul>
                        <li>แก้ไขเหตุการณ์อย่างเหมาะสม ทันเวลา</li>
                        <li>ป้องกัน ลดความเสียหายที่อาจเกิดขึ้น</li>
                        <li>หาแนวทางป้องกันและพัฒนาองค์กร</li>
                    </ul>
                </div>

                <!-- ระดับความรุนแรง -->
                <div class="info-card">
                    <h3 class="info-card-title">
                        <span class="dot dot-red"></span> ระดับความรุนแรง
                    </h3>
                    <div class="mt-1">
                        <?= renderBadge(
                            "ระดับ {$risk['severity']} — " . ($severityMap[$risk['severity']] ?? 'ไม่ระบุ'),
                            $severityBadgeColors
                        ) ?>
                    </div>
                    <?php if (isset($severityMap[$risk['severity']])): ?>
                        <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                            <?= htmlspecialchars($severityMap[$risk['severity']]) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- สถานะการดำเนินการ -->
                <div class="info-card">
                    <h3 class="info-card-title">
                        <span class="dot dot-blue"></span> สถานะการดำเนินการ
                    </h3>
                    <div class="mt-1">
                        <?= renderBadge($currentStatus, $statusBadgeColors) ?>
                    </div>
                    <?php
                    $statusDescriptions = [
                        'ยังไม่ดำเนินการ' => 'ยังไม่ได้เริ่มดำเนินการใดๆ กับความเสี่ยงนี้',
                        'กำลังดำเนินการ' => 'อยู่ระหว่างการดำเนินการแก้ไขความเสี่ยง',
                        'ดำเนินการแล้ว' => 'ดำเนินการแก้ไขเรียบร้อยแล้ว',
                        'ยุติ' => 'ยุติการดำเนินการ ไม่มีการแก้ไขต่อ'
                    ];
                    ?>
                    <?php if (isset($statusDescriptions[$currentStatus])): ?>
                        <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                            <?= htmlspecialchars($statusDescriptions[$currentStatus]) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- ข้อมูลเวลา -->
                <div class="info-card time-info">
                    <p>
                        <i class="far fa-calendar-plus"></i> 
                        สร้างเมื่อ: <?= getThaiDate($risk['created_at']) ?>
                    </p>
                    <p>
                        <i class="far fa-calendar-check"></i> 
                        แก้ไขล่าสุด: <?= getThaiDate($risk['updated_at']) ?>
                    </p>
                    <?php if (isAdmin()): ?>
                        <p class="mt-2 pt-2 border-t border-gray-100">
                            <i class="fas fa-user-shield"></i> 
                            <span class="text-blue-600 font-medium">กำลังดูในโหมด Admin</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Keyboard shortcut: กด Escape เพื่อกลับไปหน้ารายการ
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'risks.php';
        }
    });

    // Smooth scroll for detail boxes
    document.querySelectorAll('.detail-box').forEach(function(box) {
        box.addEventListener('wheel', function(e) {
            e.stopPropagation();
        }, { passive: false });
    });

    // Console info
    console.log('📋 View Risk #<?= htmlspecialchars($risk['id']) ?>');
    console.log('👤 User: <?= htmlspecialchars($_SESSION['username'] ?? "Guest") ?>');
    console.log('🔑 Role: <?= isAdmin() ? "Admin" : "User" ?>');
</script>

<?php include 'includes/footer.php'; ?>