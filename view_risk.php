<?php
/**
 * หน้าแสดงรายละเอียดความเสี่ยง (Frontend)
 * - แสดงข้อมูลครบถ้วนทุกฟิลด์
 * - แสดงสถานะ Consent และวันที่ยินยอม
 * - มีปุ่มกลับและพิมพ์ PDF
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

$id = $_GET['id'] ?? null;
if (!$id) redirect('risks.php');

// ดึงข้อมูล
$stmt = $pdo->prepare("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$id]);
$risk = $stmt->fetch();

// ตรวจสอบว่ามีข้อมูลหรือไม่
if (!$risk) redirect('risks.php');
if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) redirect('risks.php');

// แปลงระดับความรุนแรงเป็นข้อความ
$severityMap = [
    'A'=>'ระดับ A : มีโอกาสเกิดแต่ยังไม่เกิดขึ้น',
    'B'=>'ระดับ B : เกิด ยังไม่ถึงตัวบุคคล ไม่กระทบงาน',
    'C'=>'ระดับ C : เกิดถึงตัวบุคคล กระทบเบื้องต้น แก้ไขเองได้',
    'D'=>'ระดับ D : เกิดถึงตัวบุคคล กระทบปานกลาง ต้องให้เพื่อนช่วย',
    'F'=>'ระดับ F : เกิดถึงตัวบุคคล กระทบสูง ต้องแจ้งหัวหน้า',
    'E'=>'ระดับ E : เกิดถึงตัวบุคคล กระทบสูงสุด รายงานผู้บริหาร'
];
$severityText = $severityMap[$risk['severity']] ?? $risk['severity'];
?>
<?php include 'includes/header.php'; ?>
<div class="flex h-screen bg-blue-50/30">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-6 overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-blue-800">📄 รายละเอียดความเสี่ยง</h2>
            <div>
                <a href="risks.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600"><i class="fas fa-arrow-left mr-2"></i> กลับ</a>
                <a href="generate_pdf.php?id=<?= $id ?>" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-file-pdf mr-2"></i> พิมพ์ PDF</a>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border border-blue-100">
            <!-- วัตถุประสงค์ -->
            <div class="objective-box p-4 rounded-lg mb-6">
                <h3 class="font-bold text-blue-800">วัตถุประสงค์</h3>
                <ul class="list-disc ml-6 mt-2 text-gray-700">
                    <li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li>
                    <li>เพื่อป้องกัน ลดการความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
                    <li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ และช่วยให้องค์กรพัฒนาเป็นไปในแนวทางเดียวกัน</li>
                </ul>
            </div>
            
            <!-- ข้อมูลหลัก -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><span class="font-bold text-blue-700">หน่วยงานที่เกิดความเสี่ยง</span><br><?= htmlspecialchars($risk['unit'] . ($risk['unit_other'] ? ' ('.$risk['unit_other'].')' : '')) ?></div>
                <div><span class="font-bold text-blue-700">ประเภทความเสี่ยง</span><br><?= htmlspecialchars($risk['risk_type'] . ($risk['risk_type_other'] ? ' ('.$risk['risk_type_other'].')' : '')) ?></div>
                <div><span class="font-bold text-blue-700">ระดับความรุนแรง</span><br><span class="px-2 py-1 rounded-full text-xs font-semibold <?= getSeverityBadge($risk['severity']) ?>"><?= $risk['severity'] ?></span> – <?= htmlspecialchars($severityText) ?></div>
                <div><span class="font-bold text-blue-700">วันเวลาที่เกิดเหตุการณ์ (จริง)</span><br><?= getThaiDate($risk['event_datetime']) ?></div>
                <div><span class="font-bold text-blue-700">วันเวลาที่รายงานเหตุการณ์</span><br><?= getThaiDate($risk['report_datetime']) ?></div>
                <div><span class="font-bold text-blue-700">ผู้รายงาน</span><br><?= htmlspecialchars($risk['username'] ?? 'ไม่ระบุ') ?></div>
                <div><span class="font-bold text-blue-700">📄 สถานะการยินยอม (Consent)</span><br>
                    <?php if ($risk['consent'] == 1): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                            <i class="fas fa-check-circle mr-1"></i> ยินยอมแล้ว
                        </span>
                        <span class="text-sm text-gray-500 ml-2">
                            (<?= getThaiDate($risk['consent_at']) ?>)
                        </span>
                    <?php else: ?>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                            <i class="fas fa-times-circle mr-1"></i> ยังไม่ยินยอม
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- รายละเอียดเพิ่มเติม -->
            <div class="mt-6"><div class="font-bold text-blue-700 text-lg">📝 รายละเอียดเหตุการณ์</div><div class="bg-gray-50 p-4 rounded-lg mt-2 border border-blue-50"><?= nl2br(htmlspecialchars($risk['detail'])) ?></div></div>
            <div class="mt-4"><div class="font-bold text-blue-700 text-lg">🔧 การแก้ไขเบื้องต้น</div><div class="bg-gray-50 p-4 rounded-lg mt-2 border border-blue-50"><?= nl2br(htmlspecialchars($risk['initial_solution'])) ?></div></div>
            <div class="mt-4"><div class="font-bold text-blue-700 text-lg">💡 ปัญหาและข้อเสนอแนะ</div><div class="bg-gray-50 p-4 rounded-lg mt-2 border border-blue-50"><?= nl2br(htmlspecialchars($risk['suggestion'])) ?></div></div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>