<?php
/**
 * ฟอร์มเพิ่ม/แก้ไขข้อมูลความเสี่ยง (Card Layout)
 * - กลุ่มงานตามโครงสร้างใหม่ 9 กลุ่ม
 * - reporter_code (รหัสผู้รายงาน) ดึงจาก users กรณีเพิ่มใหม่
 * - สถานะ (status) แสดงเฉพาะตอนแก้ไข
 * - flatpickr เลือกวันย้อนหลังได้
 * - แสดง/ซ่อน input "อื่นๆ" อัตโนมัติ
 * - ส่ง AJAX, ตรวจสอบซ้ำ, ป้องกันบันทึกซ้ำ
 * - ✅ แก้ไขไม่ได้เมื่อสถานะ = ดำเนินการแล้ว/ยุติ
 * - ✅ เพิ่ม placeholder สำหรับฟิลด์ที่จำเป็น
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

$id = $_GET['id'] ?? null;
$risk = null;
$is_editable = true;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM risks WHERE id = ?");
    $stmt->execute([$id]);
    $risk = $stmt->fetch();
    if (!$risk) redirect('risks.php');
    if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) redirect('risks.php');

    $locked_statuses = ['ดำเนินการแล้ว', 'ยุติ'];
    if (isset($risk['status']) && in_array($risk['status'], $locked_statuses)) {
        $is_editable = false;
    }
}

$csrf_token = generateCsrfToken();
$_SESSION['form_token'] = bin2hex(random_bytes(32));

$units = [
    'กลุ่มผู้บริหาร','กลุ่มอำนวยการ','กลุ่มขับเคลื่อนยุทธศาสตร์และพัฒนากำลังคน',
    'กลุ่มพัฒนาอนามัยแม่และเด็ก','กลุ่มพัฒนาการส่งเสริมสุขภาพวัยเรียน',
    'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยรุ่น','กลุ่มพัฒนาการส่งเสริมสุขภาพวัยทำงาน',
    'กลุ่มพัฒนาการส่งเสริมสุขภาพวัยสูงอายุ','กลุ่มพัฒนาอนามัยและสิ่งแวดล้อม'
];
$types = [
    'ความเสี่ยงทางด้านกลยุทธ์','ความเสี่ยงทางด้านการเงิน','ความเสี่ยงทางด้านการปฏิบัติงาน',
    'ความเสี่ยงทางด้านกฎหมาย','ความเสี่ยงด้านสิ่งแวดล้อม','หาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข'
];
$severityOptions = [
    'A' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
    'B' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน',
    'C' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง',
    'D' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข',
    'F' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข',
    'E' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร'
];
$statuses = ['ยังไม่ดำเนินการ','กำลังดำเนินการ','ดำเนินการแล้ว','ยุติ'];

$event_datetime  = $risk ? date('Y-m-d H:i', strtotime($risk['event_datetime'])) : date('Y-m-d H:i');
$report_datetime = $risk ? date('Y-m-d H:i', strtotime($risk['report_datetime'])) : date('Y-m-d H:i');

// กำหนดค่า reporter_code
if ($id) {
    // แก้ไข → ใช้ค่าจากข้อมูลเดิม
    $reporter_code = $risk['reporter_code'] ?? '';
} else {
    // เพิ่มใหม่ → ดึงจาก users ที่ล็อกอิน
    $stmt = $pdo->prepare("SELECT reporter_code FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $reporter_code = $user['reporter_code'] ?? '';
}
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>const isEditable = <?= json_encode($is_editable) ?>;</script>

<div class="flex h-screen bg-blue-50/30">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 p-6 overflow-y-auto">
    <div class="max-w-5xl mx-auto">
      <h2 class="text-2xl font-bold mb-6 text-blue-800"><?= $id ? '✏️ แก้ไข' : '➕ เพิ่ม' ?>รายงานความเสี่ยง</h2>

      <div class="objective-box p-4 rounded-lg mb-6 bg-blue-50 border border-blue-200">
        <h3 class="font-bold text-blue-800">📋 วัตถุประสงค์</h3>
        <ul class="list-disc ml-6 mt-2 text-gray-700">
          <li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li>
          <li>เพื่อป้องกัน ลดการความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
          <li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ และช่วยให้องค์กรพัฒนาเป็นไปในแนวทางเดียวกัน</li>
        </ul>
      </div>

      <form id="riskForm" method="POST" action="action.php?action=save_risk" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <!-- รหัสผู้รายงาน (ดึงจาก users / แก้ไขได้) -->
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">🆔 รหัสผู้รายงาน <span class="text-red-500">*</span></h3>
          </div>
          <div class="card-body p-4">
            <input type="text" name="reporter_code" id="reporter_code" value="<?= htmlspecialchars($reporter_code) ?>"
                   class="w-full px-4 py-2 border rounded-lg input-blue" placeholder="เช่น R10001 (รหัสผู้รายงาน)" required <?= !$is_editable ? 'disabled' : '' ?>>
          </div>
        </div>

        <!-- กลุ่มงาน -->
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">🏢 กลุ่มงานที่เกิดความเสี่ยง <span class="text-red-500">*</span></h3>
          </div>
          <div class="card-body p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <?php foreach ($units as $u): ?>
                <label class="flex items-center space-x-2 p-2 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition <?= (($risk['unit']??'')==$u)?'border-blue-500 bg-blue-50':'border-gray-200' ?>">
                  <input type="radio" name="unit" value="<?= $u ?>" <?= (($risk['unit']??'')==$u)?'checked':'' ?> required <?= !$is_editable?'disabled':'' ?>>
                  <span><?= $u ?></span>
                </label>
              <?php endforeach; ?>
              <label class="flex items-center space-x-2 p-2 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition <?= (($risk['unit']??'')=='อื่นๆ')?'border-blue-500 bg-blue-50':'border-gray-200' ?>">
                <input type="radio" name="unit" value="อื่นๆ" <?= (($risk['unit']??'')=='อื่นๆ')?'checked':'' ?> <?= !$is_editable?'disabled':'' ?>>
                <span>อื่นๆ (ระบุ)</span>
              </label>
              <input type="text" name="unit_other" id="unit_other" value="<?= htmlspecialchars($risk['unit_other']??'') ?>"
                     class="col-span-1 sm:col-span-2 w-full px-4 py-2 border rounded-lg input-blue <?= (($risk['unit']??'')=='อื่นๆ')?'':'hidden' ?>"
                     placeholder="ระบุกลุ่มงานอื่น" <?= (($risk['unit']??'')=='อื่นๆ')?'':'disabled' ?> <?= !$is_editable?'disabled':'' ?>>
            </div>
          </div>
        </div>

        <!-- ประเภทความเสี่ยง -->
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">📌 ประเภทของความเสี่ยง</h3>
          </div>
          <div class="card-body p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <?php foreach ($types as $t): ?>
                <label class="flex items-center space-x-2 p-2 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition <?= (($risk['risk_type']??'')==$t)?'border-blue-500 bg-blue-50':'border-gray-200' ?>">
                  <input type="radio" name="risk_type" value="<?= $t ?>" <?= (($risk['risk_type']??'')==$t)?'checked':'' ?> <?= !$is_editable?'disabled':'' ?>>
                  <span><?= $t ?></span>
                </label>
              <?php endforeach; ?>
              <label class="flex items-center space-x-2 p-2 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition <?= (($risk['risk_type']??'')=='อื่นๆ')?'border-blue-500 bg-blue-50':'border-gray-200' ?>">
                <input type="radio" name="risk_type" value="อื่นๆ" <?= (($risk['risk_type']??'')=='อื่นๆ')?'checked':'' ?> <?= !$is_editable?'disabled':'' ?>>
                <span>อื่นๆ (ระบุ)</span>
              </label>
              <input type="text" name="risk_type_other" id="risk_type_other" value="<?= htmlspecialchars($risk['risk_type_other']??'') ?>"
                     class="col-span-1 sm:col-span-2 w-full px-4 py-2 border rounded-lg input-blue <?= (($risk['risk_type']??'')=='อื่นๆ')?'':'hidden' ?>"
                     placeholder="ระบุประเภทอื่น" <?= (($risk['risk_type']??'')=='อื่นๆ')?'':'disabled' ?> <?= !$is_editable?'disabled':'' ?>>
            </div>
          </div>
        </div>

        <!-- ระดับความรุนแรง -->
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">⚠️ ระดับความรุนแรง</h3>
          </div>
          <div class="card-body p-4">
            <div class="grid grid-cols-1 gap-2">
              <?php foreach ($severityOptions as $key => $label): ?>
                <label class="flex items-center space-x-2 p-2 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition <?= (($risk['severity']??'')==$key)?'border-blue-500 bg-blue-50':'border-gray-200' ?>">
                  <input type="radio" name="severity" value="<?= $key ?>" <?= (($risk['severity']??'')==$key)?'checked':'' ?> <?= !$is_editable?'disabled':'' ?>>
                  <span class="font-medium"><?= $key ?></span>
                  <span class="text-gray-600">– <?= $label ?></span>
                </label>
              <?php endforeach; ?>
              <label class="flex items-center space-x-2 p-2 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition <?= (($risk['severity']??'')=='อื่นๆ')?'border-blue-500 bg-blue-50':'border-gray-200' ?>">
                <input type="radio" name="severity" value="อื่นๆ" <?= (($risk['severity']??'')=='อื่นๆ')?'checked':'' ?> <?= !$is_editable?'disabled':'' ?>>
                <span>อื่นๆ (ระบุ)</span>
              </label>
              <input type="text" name="severity_other" id="severity_other" value="<?= htmlspecialchars($risk['severity_other']??'') ?>"
                     class="w-full px-4 py-2 border rounded-lg input-blue <?= (($risk['severity']??'')=='อื่นๆ')?'':'hidden' ?>"
                     placeholder="ระบุระดับอื่น" <?= (($risk['severity']??'')=='อื่นๆ')?'':'disabled' ?> <?= !$is_editable?'disabled':'' ?>>
            </div>
          </div>
        </div>

        <!-- วันเวลา -->
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">📅 วันเวลา</h3>
          </div>
          <div class="card-body p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-700 font-semibold mb-2">📅 วันเวลาที่เกิดเหตุการณ์ <span class="text-red-500">*</span></label>
                <input type="text" id="event_datetime" name="event_datetime" value="<?= $event_datetime ?>"
                       class="w-full px-4 py-2 border rounded-lg input-blue" required placeholder="เลือกวันที่และเวลา" autocomplete="off" <?= !$is_editable?'disabled':'' ?>>
              </div>
              <div>
                <label class="block text-gray-700 font-semibold mb-2">📅 วันเวลาที่รายงานเหตุการณ์ <span class="text-red-500">*</span></label>
                <input type="text" id="report_datetime" name="report_datetime" value="<?= $report_datetime ?>"
                       class="w-full px-4 py-2 border rounded-lg input-blue" required placeholder="เลือกวันที่และเวลา" autocomplete="off" <?= !$is_editable?'disabled':'' ?>>
              </div>
            </div>
          </div>
        </div>

        <!-- รายละเอียด -->
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">📝 รายละเอียดและแนวทางแก้ไข</h3>
          </div>
          <div class="card-body p-4 space-y-4">
            <div>
              <label class="block text-gray-700 font-semibold mb-2">📝 รายละเอียดเหตุการณ์ <span class="text-red-500">*</span></label>
              <textarea name="detail" rows="4" class="w-full px-4 py-2 border rounded-lg input-blue" required placeholder="อธิบายรายละเอียดเหตุการณ์" <?= !$is_editable?'disabled':'' ?>><?= htmlspecialchars($risk['detail']??'') ?></textarea>
            </div>
            <div>
              <label class="block text-gray-700 font-semibold mb-2">🔧 การแก้ไขเบื้องต้น <span class="text-red-500">*</span></label>
              <textarea name="initial_solution" rows="3" class="w-full px-4 py-2 border rounded-lg input-blue" required placeholder="ระบุการแก้ไขเบื้องต้น" <?= !$is_editable?'disabled':'' ?>><?= htmlspecialchars($risk['initial_solution']??'') ?></textarea>
            </div>
            <div>
              <label class="block text-gray-700 font-semibold mb-2">💡 ปัญหาและข้อเสนอแนะ <span class="text-red-500">*</span></label>
              <textarea name="suggestion" rows="3" class="w-full px-4 py-2 border rounded-lg input-blue" required placeholder="ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข" <?= !$is_editable?'disabled':'' ?>><?= htmlspecialchars($risk['suggestion']??'') ?></textarea>
            </div>
          </div>
        </div>

        <!-- สถานะ (เฉพาะแก้ไข) -->
        <?php if ($id): ?>
        <div class="card bg-white rounded-lg shadow-md border border-blue-100 overflow-hidden">
          <div class="card-header bg-blue-50 px-4 py-3 border-b border-blue-100">
            <h3 class="text-lg font-semibold text-blue-800">📊 สถานะการดำเนินการ <span class="text-red-500">*</span></h3>
          </div>
          <div class="card-body p-4">
            <select name="status" id="status" class="w-full px-4 py-2 border rounded-lg input-blue" required <?= !$is_editable?'disabled':'' ?>>
              <option value="">-- กรุณาเลือกสถานะ --</option>
              <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= (($risk['status']??'')==$st)?'selected':'' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="status" value="ยังไม่ดำเนินการ">
        <?php endif; ?>

        <!-- ปุ่ม -->
        <?php if ($is_editable): ?>
        <div class="flex items-center space-x-4 pt-2">
          <button type="submit" class="btn-primary px-6 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow transition">
            <i class="fas fa-save mr-2"></i> บันทึก
          </button>
          <a href="risks.php" class="btn-cancel px-6 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold transition">
            <i class="fas fa-times mr-1"></i> ยกเลิก
          </a>
        </div>
        <?php else: ?>
        <div class="pt-2">
          <a href="risks.php" class="btn-cancel px-6 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold transition">
            <i class="fas fa-arrow-left mr-1"></i> กลับไปหน้ารายการ
          </a>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!isEditable) {
        Swal.fire({ icon:'info', title:'ไม่สามารถแก้ไขได้', text:'รายการนี้ถูกดำเนินการเสร็จสิ้นหรือยุติแล้ว', confirmButtonText:'ตกลง' });
        return;
    }

    const dateConfig = {
        enableTime: true,
        time_24hr: true,
        dateFormat: "Y-m-d H:i",
        altInput: true,
        altFormat: "j F Y H:i",
        locale: "th",
        allowInput: true,
        minuteIncrement: 1
    };
    const eventPicker = flatpickr('#event_datetime', dateConfig);
    const reportPicker = flatpickr('#report_datetime', dateConfig);

    function toggleOther(radioName, inputId) {
        const sel = document.querySelector(`input[name="${radioName}"]:checked`);
        const inp = document.getElementById(inputId);
        if (!inp) return;
        if (sel && sel.value === 'อื่นๆ') {
            inp.classList.remove('hidden');
            inp.disabled = false;
            if (!inp.value.trim()) inp.focus();
        } else {
            inp.classList.add('hidden');
            inp.disabled = true;
        }
    }
    ['unit','risk_type','severity'].forEach(name => {
        const radios = document.querySelectorAll(`input[name="${name}"]`);
        radios.forEach(r => r.addEventListener('change', () => toggleOther(name, name+'_other')));
        toggleOther(name, name+'_other');
    });

    function debounce(fn, delay) {
        let timer;
        return function(...args) { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, args), delay); };
    }
    async function checkDup() {
        const idField = document.querySelector('input[name="id"]');
        if (idField && idField.value !== '') return;
        const formData = new FormData(document.getElementById('riskForm'));
        try {
            const res = await fetch('action.php?action=check_duplicate', { method:'POST', body: formData });
            const data = await res.json();
            if (data.duplicate) {
                Swal.fire({ icon:'warning', title:'ไม่สามารถเพิ่มได้', text: data.message || 'มีรายงานนี้อยู่แล้ว', toast:true, position:'top-end', showConfirmButton:false, timer:4000 });
            }
        } catch(e) { console.error(e); }
    }
    ['detail','initial_solution','suggestion','event_datetime','report_datetime','reporter_code'].forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) el.addEventListener('input', debounce(checkDup, 1500));
    });
    ['unit','risk_type','severity'].forEach(name => {
        document.querySelectorAll(`input[name="${name}"]`).forEach(r => r.addEventListener('change', debounce(checkDup, 800)));
    });

    let submitting = false;
    document.getElementById('riskForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (submitting) { Swal.fire({ icon:'warning', title:'รอสักครู่', text:'กำลังดำเนินการ...' }); return; }

        let otherEmpty = false;
        ['unit','risk_type','severity'].forEach(name => {
            const sel = document.querySelector(`input[name="${name}"]:checked`);
            if (sel && sel.value === 'อื่นๆ') {
                const inp = document.getElementById(name+'_other');
                if (inp && !inp.value.trim()) { otherEmpty = true; inp.classList.add('border-red-500'); setTimeout(()=>inp.classList.remove('border-red-500'),2000); }
            }
        });
        if (otherEmpty) { Swal.fire({ icon:'warning', title:'กรุณาระบุข้อมูล', text:'เลือก "อื่นๆ" แต่ไม่ได้กรอก' }); return; }

        const ev = eventPicker.selectedDates[0], rp = reportPicker.selectedDates[0];
        if (ev && rp && rp < ev) { Swal.fire({ icon:'warning', title:'วันที่ไม่ถูกต้อง', text:'วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุ' }); return; }

        const idField = document.querySelector('input[name="id"]');
        if (!idField || idField.value === '') {
            fetch('action.php?action=check_duplicate', { method:'POST', body: new FormData(this) })
                .then(r=>r.json()).then(d=> { if(d.duplicate) Swal.fire({ icon:'warning', title:'ซ้ำ', text:d.message }); else confirmSubmit(); })
                .catch(()=>confirmSubmit());
        } else confirmSubmit();

        function confirmSubmit() {
            Swal.fire({
                title:'ยืนยันการบันทึก?', text:'คุณต้องการบันทึกรายงานนี้ใช่หรือไม่', icon:'question',
                showCancelButton:true, confirmButtonColor:'#3085d6', cancelButtonColor:'#aaa',
                confirmButtonText:'✅ ยืนยัน', cancelButtonText:'❌ ยกเลิก'
            }).then(result => {
                if (!result.isConfirmed) return;
                submitting = true;
                const btn = document.querySelector('#riskForm button[type="submit"]');
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>กำลังบันทึก...';
                fetch('action.php?action=save_risk', { method:'POST', body: new FormData(document.getElementById('riskForm')) })
                    .then(r=>r.json()).then(data => {
                        if (data.success) {
                            Swal.fire({ icon:'success', title:'บันทึกสำเร็จ', text:data.message }).then(()=> window.location.href='risks.php');
                        } else {
                            Swal.fire({ icon:'error', title:'ผิดพลาด', text:data.message });
                            submitting = false; btn.disabled = false; btn.innerHTML = orig;
                        }
                    }).catch(() => {
                        Swal.fire({ icon:'error', title:'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์' });
                        submitting = false; btn.disabled = false; btn.innerHTML = orig;
                    });
            });
        }
    });
});
</script>
<?php include 'includes/footer.php'; ?>