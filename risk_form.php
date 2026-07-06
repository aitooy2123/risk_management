<?php

/**
 * ฟอร์มเพิ่ม/แก้ไขข้อมูลความเสี่ยง (Card Layout) - UI สวยงาม
 * - ไล่สีตามระดับความรุนแรง
 * - Animation สวยงาม
 * - สถานะการดำเนินการแก้ไขได้เฉพาะ Admin เท่านั้น
 * - สถานะการดำเนินการมีสีแตกต่างกัน
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

$id = $_GET['id'] ?? null;
$risk = null;
$is_editable = true;
$is_admin = isAdmin();

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
$types = [
  'ความเสี่ยงทางด้านกลยุทธ์',
  'ความเสี่ยงทางด้านการเงิน',
  'ความเสี่ยงทางด้านการปฏิบัติงาน',
  'ความเสี่ยงทางด้านกฎหมาย',
  'ความเสี่ยงด้านสิ่งแวดล้อม',
  'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข'
];
$severityOptions = [
  'A' => ['label' => 'ระดับ A : มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'border' => '#93c5fd', 'icon' => 'fa-circle-info'],
  'B' => ['label' => 'ระดับ B : เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน', 'color' => '#22c55e', 'bg' => '#f0fdf4', 'border' => '#86efac', 'icon' => 'fa-circle-check'],
  'C' => ['label' => 'ระดับ C : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง', 'color' => '#84cc16', 'bg' => '#f7fee7', 'border' => '#bef264', 'icon' => 'fa-circle-exclamation'],
  'D' => ['label' => 'ระดับ D : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข', 'color' => '#eab308', 'bg' => '#fefce8', 'border' => '#fde047', 'icon' => 'fa-triangle-exclamation'],
  'F' => ['label' => 'ระดับ F : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข', 'color' => '#f97316', 'bg' => '#fff7ed', 'border' => '#fdba74', 'icon' => 'fa-fire'],
  'E' => ['label' => 'ระดับ E : เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร', 'color' => '#ef4444', 'bg' => '#fef2f2', 'border' => '#fca5a5', 'icon' => 'fa-skull']
];

// ===== สถานะการดำเนินการ พร้อมสี =====
$statuses = [
  'ยังไม่ดำเนินการ' => ['color' => '#6b7280', 'bg' => '#f3f4f6', 'icon' => 'fa-clock', 'label' => 'ยังไม่ดำเนินการ'],
  'กำลังดำเนินการ' => ['color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'fa-spinner', 'label' => 'กำลังดำเนินการ'],
  'ดำเนินการแล้ว' => ['color' => '#22c55e', 'bg' => '#f0fdf4', 'icon' => 'fa-check-circle', 'label' => 'ดำเนินการแล้ว'],
  'ยุติ' => ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-ban', 'label' => 'ยุติ']
];

// ตั้งค่าเริ่มต้นเป็นเวลาปัจจุบัน
$current_datetime = date('Y-m-d H:i');
$event_datetime  = $risk ? date('Y-m-d H:i', strtotime($risk['event_datetime'])) : $current_datetime;
$report_datetime = $risk ? date('Y-m-d H:i', strtotime($risk['report_datetime'])) : $current_datetime;

if ($id) {
  $reporter_code = $risk['reporter_code'] ?? '';
} else {
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
<script>
  const isEditable = <?= json_encode($is_editable) ?>;
  const isAdmin = <?= json_encode($is_admin) ?>;
</script>

<style>
  :root {
    --primary: #3b82f6;
    --primary-dark: #1e40af;
  }

  body {
    background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
    min-height: 100vh;
  }

  .form-container {
    max-width: 800px;
    margin: 0 auto;
  }

  /* Header */
  .form-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
    border-radius: 1.5rem;
    padding: 2rem;
    margin-bottom: 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
  }

  .form-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 50%;
  }

  .form-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    position: relative;
    z-index: 1;
  }

  .form-header p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    position: relative;
    z-index: 1;
  }

  /* Objective Box */
  .objective-box {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #bfdbfe;
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
  }

  .objective-box h3 {
    font-weight: 700;
    color: #1e40af;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .objective-box ul {
    list-style: none;
    padding: 0;
  }

  .objective-box li {
    padding: 0.35rem 0;
    color: #334155;
    font-size: 0.9rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .objective-box li::before {
    content: '✓';
    color: #3b82f6;
    font-weight: 700;
    flex-shrink: 0;
  }

  /* Card */
  .form-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.5);
    margin-bottom: 1.25rem;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
  }

  .form-card:hover {
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
  }

  .card-header {
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.8);
    background: rgba(250, 251, 252, 0.7);
  }

  .card-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .card-header-title {
    font-weight: 700;
    color: #1e293b;
    font-size: 1rem;
  }

  .card-header-badge {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    margin-left: auto;
  }

  .card-header-badge.admin-only {
    background: #fef3c7;
    color: #92400e;
  }

  .card-header-badge.readonly {
    background: #f1f5f9;
    color: #64748b;
  }

  .card-body {
    padding: 1.5rem;
  }

  /* Input */
  .form-input {
    width: 100%;
    padding: 0.7rem 0.9rem;
    border: 1.5px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.6rem;
    font-size: 0.9rem;
    transition: all 0.25s;
    outline: none;
    font-family: 'Sarabun', sans-serif;
    background: rgba(250, 251, 252, 0.8);
    color: #1e293b;
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

  textarea.form-input {
    resize: vertical;
    min-height: 100px;
    line-height: 1.6;
  }

  .form-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
    margin-bottom: 0.4rem;
  }

  .form-label .required {
    color: #ef4444;
    font-size: 0.7rem;
  }

  /* Radio Card */
  .radio-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
  }

  .radio-card {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 0.9rem;
    border: 2px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.6rem;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    position: relative;
  }

  .radio-card:hover {
    border-color: #93c5fd;
    background: #f8fafc;
  }

  .radio-card:has(input:checked) {
    border-color: #3b82f6;
    background: #eff6ff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
  }

  .radio-card input[type="radio"] {
    accent-color: #3b82f6;
    width: 16px;
    height: 16px;
  }

  .radio-card .radio-label {
    font-size: 0.85rem;
    color: #334155;
    font-weight: 500;
  }

  /* Severity Radio */
  .severity-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
  }

  .severity-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 0.8rem 0.5rem;
    border: 2px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
    text-align: center;
    position: relative;
  }

  .severity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  }

  .severity-card:has(input:checked) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
  }

  .severity-card input[type="radio"] {
    display: none;
  }

  .severity-card .sev-icon {
    font-size: 1.5rem;
    margin-bottom: 0.2rem;
  }

  .severity-card .sev-letter {
    font-size: 1.25rem;
    font-weight: 700;
  }

  .severity-card .sev-desc1 {
    font-size: 0.65rem;
    color: #64748b;
    line-height: 1.3;
    margin-top: 0.15rem;
  }

  /* Status Select - สีต่างกัน */
  .status-select {
    width: 100%;
    padding: 0.7rem 0.9rem;
    border: 2px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.6rem;
    font-size: 0.9rem;
    transition: all 0.25s;
    outline: none;
    font-family: 'Sarabun', sans-serif;
    background: rgba(250, 251, 252, 0.8);
    color: #1e293b;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.9rem center;
    padding-right: 2.5rem;
  }

  .status-select:focus {
    border-color: #3b82f6;
    background: white;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
  }

  .status-select option {
    padding: 0.5rem;
    font-weight: 500;
  }

  /* Status Badge แสดงสถานะ */
  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
  }

  /* Buttons */
  .btn-submit {
    padding: 0.75rem 2rem;
    border-radius: 0.7rem;
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(37, 99, 235, 0.45);
  }

  .btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .btn-cancel {
    padding: 0.75rem 1.5rem;
    border-radius: 0.7rem;
    font-weight: 500;
    font-size: 0.9rem;
    background: rgba(241, 245, 249, 0.8);
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid rgba(226, 232, 240, 0.5);
  }

  .btn-cancel:hover {
    background: #e2e8f0;
    color: #334155;
    transform: translateY(-2px);
  }

  .btn-back {
    padding: 0.75rem 1.5rem;
    border-radius: 0.7rem;
    font-weight: 500;
    font-size: 0.9rem;
    background: rgba(239, 246, 255, 0.8);
    color: #3b82f6;
    text-decoration: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid rgba(191, 219, 254, 0.5);
  }

  .btn-back:hover {
    background: #dbeafe;
    transform: translateY(-2px);
  }

  .btn-default {
    padding: 0.4rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.8rem;
    background: rgba(241, 245, 249, 0.8);
    color: #64748b;
    border: 1px solid rgba(226, 232, 240, 0.5);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-family: 'Sarabun', sans-serif;
  }

  .btn-default:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
  }

  .btn-default.filled {
    background: rgba(209, 250, 229, 0.8);
    border-color: rgba(110, 231, 183, 0.5);
    color: #065f46;
  }

  /* Locked Overlay */
  .locked-overlay {
    background: rgba(254, 243, 199, 0.9);
    backdrop-filter: blur(10px);
    border: 2px dashed #fcd34d;
    border-radius: 0.6rem;
    padding: 1rem;
    text-align: center;
    color: #92400e;
    font-weight: 500;
    margin-bottom: 1rem;
  }

  .status-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.7rem 0.9rem;
    border: 1.5px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.6rem;
    background: rgba(241, 245, 249, 0.6);
    color: #64748b;
    font-size: 0.9rem;
  }

  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .animate-in {
    animation: slideUp 0.5s ease forwards;
  }

  .animate-in:nth-child(1) { animation-delay: 0s; }
  .animate-in:nth-child(2) { animation-delay: 0.05s; }
  .animate-in:nth-child(3) { animation-delay: 0.1s; }
  .animate-in:nth-child(4) { animation-delay: 0.15s; }
  .animate-in:nth-child(5) { animation-delay: 0.2s; }
  .animate-in:nth-child(6) { animation-delay: 0.25s; }
  .animate-in:nth-child(7) { animation-delay: 0.3s; }

  @media (max-width: 768px) {
    .radio-grid,
    .severity-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="flex h-screen" style="position:relative;z-index:1;">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 p-4 md:p-6 overflow-y-auto">
    <div class="form-container">

      <!-- Header -->
      <div class="form-header">
        <h2><?= $id ? '✏️ แก้ไขรายงานความเสี่ยง' : '➕ เพิ่มรายงานความเสี่ยง' ?></h2>
        <p>ศูนย์อนามัยที่ 8 อุดรธานี | ระบบบริหารจัดการความเสี่ยง</p>
      </div>

      <!-- Locked Warning -->
      <?php if (!$is_editable): ?>
        <div class="locked-overlay">
          <i class="fas fa-lock mr-2"></i> รายการนี้ถูกดำเนินการเสร็จสิ้นหรือยุติแล้ว ไม่สามารถแก้ไขได้
        </div>
      <?php endif; ?>

      <!-- Objective -->
      <div class="objective-box">
        <h3>📋 วัตถุประสงค์</h3>
        <ul>
          <li>เพื่อแก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสม และทันเวลา</li>
          <li>เพื่อป้องกัน ลดการความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
          <li>เพื่อให้องค์กรสามารถหาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำ</li>
        </ul>
      </div>

      <form id="riskForm" method="POST" action="action.php?action=save_risk">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <!-- รหัสผู้รายงาน -->
        <div class="form-card animate-in">
          <div class="card-header">
            <div class="card-header-icon" style="background:rgba(219,234,254,0.8);color:#2563eb;"><i class="fas fa-id-card"></i></div>
            <h3 class="card-header-title">รหัสผู้รายงาน</h3>
            <span class="card-header-badge" style="background:rgba(254,226,226,0.8);color:#dc2626;">จำเป็น</span>
          </div>
          <div class="card-body">
            <input type="text" name="reporter_code" id="reporter_code" value="<?= htmlspecialchars($reporter_code) ?>"
              class="form-input" placeholder="เช่น R10001 (รหัสผู้รายงาน)" required <?= !$is_editable ? 'disabled' : '' ?>>
          </div>
        </div>

        <!-- กลุ่มงาน -->
        <div class="form-card animate-in">
          <div class="card-header">
            <div class="card-header-icon" style="background:rgba(224,231,255,0.8);color:#4338ca;"><i class="fas fa-building"></i></div>
            <h3 class="card-header-title">กลุ่มงานที่เกิดความเสี่ยง</h3>
            <span class="card-header-badge" style="background:rgba(254,226,226,0.8);color:#dc2626;">จำเป็น</span>
          </div>
          <div class="card-body">
            <div class="radio-grid">
              <?php foreach ($units as $u): ?>
                <label class="radio-card <?= (($risk['unit'] ?? '') == $u) ? 'border-blue-500 bg-blue-50' : '' ?>">
                  <input type="radio" name="unit" value="<?= $u ?>" <?= (($risk['unit'] ?? '') == $u) ? 'checked' : '' ?> required <?= !$is_editable ? 'disabled' : '' ?>>
                  <span class="radio-label"><?= $u ?></span>
                </label>
              <?php endforeach; ?>
              <label class="radio-card <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? 'border-blue-500 bg-blue-50' : '' ?>">
                <input type="radio" name="unit" value="อื่นๆ" <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? 'checked' : '' ?> <?= !$is_editable ? 'disabled' : '' ?>>
                <span class="radio-label">อื่นๆ (ระบุ)</span>
              </label>
            </div>
            <input type="text" name="unit_other" id="unit_other" value="<?= htmlspecialchars($risk['unit_other'] ?? '') ?>"
              class="form-input mt-2 <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? '' : 'hidden' ?>"
              placeholder="ระบุกลุ่มงานอื่น" <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? '' : 'disabled' ?> <?= !$is_editable ? 'disabled' : '' ?>>
          </div>
        </div>

        <!-- ประเภทความเสี่ยง -->
        <div class="form-card animate-in">
          <div class="card-header">
            <div class="card-header-icon" style="background:rgba(252,211,77,0.2);color:#d97706;"><i class="fas fa-tag"></i></div>
            <h3 class="card-header-title">ประเภทของความเสี่ยง</h3>
          </div>
          <div class="card-body">
            <div class="radio-grid">
              <?php foreach ($types as $t): ?>
                <label class="radio-card <?= (($risk['risk_type'] ?? '') == $t) ? 'border-blue-500 bg-blue-50' : '' ?>">
                  <input type="radio" name="risk_type" value="<?= $t ?>" <?= (($risk['risk_type'] ?? '') == $t) ? 'checked' : '' ?> <?= !$is_editable ? 'disabled' : '' ?>>
                  <span class="radio-label"><?= $t ?></span>
                </label>
              <?php endforeach; ?>
              <label class="radio-card <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? 'border-blue-500 bg-blue-50' : '' ?>">
                <input type="radio" name="risk_type" value="อื่นๆ" <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? 'checked' : '' ?> <?= !$is_editable ? 'disabled' : '' ?>>
                <span class="radio-label">อื่นๆ (ระบุ)</span>
              </label>
            </div>
            <input type="text" name="risk_type_other" id="risk_type_other" value="<?= htmlspecialchars($risk['risk_type_other'] ?? '') ?>"
              class="form-input mt-2 <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? '' : 'hidden' ?>"
              placeholder="ระบุประเภทอื่น" <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? '' : 'disabled' ?> <?= !$is_editable ? 'disabled' : '' ?>>
          </div>
        </div>

        <!-- ระดับความรุนแรง -->
        <div class="form-card animate-in">
          <div class="card-header">
            <div class="card-header-icon" style="background:rgba(254,226,226,0.8);color:#dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
            <h3 class="card-header-title">ระดับความรุนแรง</h3>
          </div>
          <div class="card-body">
            <div class="severity-grid">
              <?php foreach ($severityOptions as $key => $opt):
                $isChecked = ($risk['severity'] ?? '') == $key;
                $cardStyle = $isChecked ? "border-color:{$opt['color']};background:{$opt['bg']};box-shadow:0 0 0 3px {$opt['color']}20;" : '';
              ?>
                <label class="severity-card" style="<?= $cardStyle ?>">
                  <input type="radio" name="severity" value="<?= $key ?>" <?= $isChecked ? 'checked' : '' ?> <?= !$is_editable ? 'disabled' : '' ?>>
                  <div class="sev-icon" style="color:<?= $opt['color'] ?>"><i class="fas <?= $opt['icon'] ?>"></i></div>
                  <div class="sev-letter" style="color:<?= $opt['color'] ?>"><?= $key ?></div>
                  <div class="sev-desc1"><?= $opt['label'] ?></div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- วันเวลา -->
        <div class="form-card animate-in">
          <div class="card-header">
            <div class="card-header-icon" style="background:rgba(209,250,229,0.8);color:#16a34a;"><i class="fas fa-calendar-alt"></i></div>
            <h3 class="card-header-title">วันเวลา</h3>
            <span class="card-header-badge" style="background:rgba(254,226,226,0.8);color:#dc2626;">จำเป็น</span>
          </div>
          <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">📅 วันที่เกิดเหตุการณ์ <span class="required">*</span></label>
                <input type="text" id="event_datetime" name="event_datetime" value="<?= $event_datetime ?>"
                  class="form-input" required placeholder="เลือกวันที่และเวลา" autocomplete="off" <?= !$is_editable ? 'disabled' : '' ?>>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle"></i> กรุณาเลือกวันที่และเวลาเกิดเหตุการณ์</p>
              </div>
              <div>
                <label class="form-label">📅 วันที่รายงาน <span class="required">*</span></label>
                <input type="text" id="report_datetime" name="report_datetime" value="<?= $report_datetime ?>"
                  class="form-input" required placeholder="เลือกวันที่และเวลา" autocomplete="off" <?= !$is_editable ? 'disabled' : '' ?>>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle"></i> กรุณาเลือกวันที่และเวลาที่รายงาน</p>
              </div>
            </div>
          </div>
        </div>

        <!-- รายละเอียด -->
        <div class="form-card animate-in">
          <div class="card-header">
            <div class="card-header-icon" style="background:rgba(237,233,254,0.8);color:#6d28d9;"><i class="fas fa-pen-to-square"></i></div>
            <h3 class="card-header-title">รายละเอียดและแนวทางแก้ไข</h3>
            <span class="card-header-badge" style="background:rgba(254,226,226,0.8);color:#dc2626;">จำเป็น</span>
          </div>
          <div class="card-body space-y-4">
            <div>
              <label class="form-label">📝 รายละเอียดเหตุการณ์ <span class="required">*</span></label>
              <textarea name="detail" id="detail" rows="4" class="form-input" required
                placeholder="อธิบายรายละเอียดเหตุการณ์ที่เกิดขึ้น..." <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['detail'] ?? '') ?></textarea>
            </div>
            <div>
              <label class="form-label">🔧 การแก้ไขเบื้องต้น <span class="required">*</span></label>
              <textarea name="initial_solution" id="initial_solution" rows="3" class="form-input" required
                placeholder="ระบุการแก้ไขเบื้องต้นที่ได้ดำเนินการ..." <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['initial_solution'] ?? '') ?></textarea>
            </div>
            <div>
              <label class="form-label">💡 ปัญหาและข้อเสนอแนะ <span class="required">*</span></label>
              <textarea name="suggestion" id="suggestion" rows="3" class="form-input" required
                placeholder="ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข..." <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['suggestion'] ?? '') ?></textarea>
            </div>
            <?php if ($is_editable): ?>
              <div class="flex justify-end pt-2">
                <a href="#" id="fillDefaultLink" class="btn-default"
                  data-default-detail="ไม่มีรายละเอียดเพิ่มเติม"
                  data-default-solution="ไม่มีการแก้ไขเบื้องต้น"
                  data-default-suggestion="ไม่มีข้อเสนอแนะเพิ่มเติม">
                  <i class="fas fa-pen"></i> ไม่มีข้อมูลในส่วนนี้
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ===== สถานะการดำเนินการ - แสดงเฉพาะ Admin ===== -->
        <?php if ($is_admin): ?>
          <div class="form-card animate-in">
            <div class="card-header">
              <div class="card-header-icon" style="background:rgba(153,246,228,0.8);color:#0d9488;"><i class="fas fa-chart-simple"></i></div>
              <h3 class="card-header-title">สถานะการดำเนินการ</h3>
              <span class="card-header-badge admin-only"><i class="fas fa-crown"></i> Admin เท่านั้น</span>
            </div>
            <div class="card-body">
              <?php if ($is_admin && $is_editable): ?>
                <select name="status" id="status" class="status-select">
                  <option value="">-- กรุณาเลือกสถานะ --</option>
                  <?php foreach ($statuses as $key => $st): ?>
                    <?php 
                      $selected = (($risk['status'] ?? '') == $key) ? 'selected' : '';
                      $color = $st['color'];
                    ?>
                    <option value="<?= $key ?>" <?= $selected ?> style="color:<?= $color ?>; font-weight:600;">
                      <?= $st['label'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle"></i> เฉพาะ Admin เท่านั้นที่สามารถเปลี่ยนสถานะได้</p>
                
                <!-- แสดงสถานะปัจจุบันแบบ Badge -->
                <?php if (!empty($risk['status'])): ?>
                  <?php 
                    $currentStatus = $risk['status'];
                    $statusInfo = $statuses[$currentStatus] ?? null;
                    if ($statusInfo):
                  ?>
                    <div class="mt-3 flex items-center gap-2">
                      <span class="text-sm text-gray-500">สถานะปัจจุบัน:</span>
                      <span class="status-badge" style="background:<?= $statusInfo['bg'] ?>; color:<?= $statusInfo['color'] ?>; border:1px solid <?= $statusInfo['color'] ?>40;">
                        <i class="fas <?= $statusInfo['icon'] ?>"></i>
                        <?= $statusInfo['label'] ?>
                      </span>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
                
              <?php else: ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($risk['status'] ?? 'ยังไม่ดำเนินการ') ?>">
                <div class="status-display">
                  <i class="fas fa-lock text-gray-400"></i>
                  <span>สถานะปัจจุบัน: 
                    <strong>
                      <?php 
                        $currentStatus = $risk['status'] ?? 'ยังไม่ดำเนินการ';
                        $statusInfo = $statuses[$currentStatus] ?? null;
                        if ($statusInfo):
                      ?>
                        <span class="status-badge" style="background:<?= $statusInfo['bg'] ?>; color:<?= $statusInfo['color'] ?>; border:1px solid <?= $statusInfo['color'] ?>40;">
                          <i class="fas <?= $statusInfo['icon'] ?>"></i>
                          <?= $statusInfo['label'] ?>
                        </span>
                      <?php else: ?>
                        <?= htmlspecialchars($currentStatus) ?>
                      <?php endif; ?>
                    </strong>
                  </span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <!-- ถ้าไม่ใช่ Admin ให้ส่งสถานะเริ่มต้นไปแบบ hidden -->
          <input type="hidden" name="status" value="<?= htmlspecialchars($risk['status'] ?? 'ยังไม่ดำเนินการ') ?>">
        <?php endif; ?>

        <!-- Buttons -->
        <div class="flex items-center gap-3 pt-2 pb-8">
          <?php if ($is_editable): ?>
            <button type="submit" class="btn-submit">
              <i class="fas fa-save"></i> บันทึกรายงาน
            </button>
            <a href="risks.php" class="btn-cancel">
              <i class="fas fa-times"></i> ยกเลิก
            </a>
          <?php else: ?>
            <a href="risks.php" class="btn-back">
              <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (!isEditable) {
      Swal.fire({
        icon: 'info',
        title: 'ไม่สามารถแก้ไขได้',
        text: 'รายการนี้ถูกดำเนินการเสร็จสิ้นหรือยุติแล้ว',
        confirmButtonText: 'ตกลง'
      });
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
      minuteIncrement: 1,
      defaultDate: new Date()
    };
    const eventPicker = flatpickr('#event_datetime', dateConfig);
    const reportPicker = flatpickr('#report_datetime', dateConfig);

    // Toggle Other fields
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
    ['unit', 'risk_type', 'severity'].forEach(name => {
      const radios = document.querySelectorAll(`input[name="${name}"]`);
      radios.forEach(r => r.addEventListener('change', () => toggleOther(name, name + '_other')));
      toggleOther(name, name + '_other');
    });

    // Severity card click
    document.querySelectorAll('.severity-card').forEach(card => {
      card.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if (radio && !radio.disabled) radio.checked = true;
        document.querySelectorAll('.severity-card').forEach(c => {
          c.style.borderColor = '#e2e8f0';
          c.style.background = 'white';
          c.style.boxShadow = '';
        });
        const color = this.querySelector('.sev-icon')?.style.color || '#3b82f6';
        this.style.borderColor = color;
        this.style.background = color + '15';
        this.style.boxShadow = '0 0 0 3px ' + color + '20';
      });
    });

    // Quick Fill Link
    const fillLink = document.getElementById('fillDefaultLink');
    if (fillLink) {
      fillLink.addEventListener('click', function(e) {
        e.preventDefault();
        const detail = document.getElementById('detail');
        const solution = document.getElementById('initial_solution');
        const suggestion = document.getElementById('suggestion');
        const defDetail = this.dataset.defaultDetail;
        const defSolution = this.dataset.defaultSolution;
        const defSuggestion = this.dataset.defaultSuggestion;

        let filled = false;
        if (detail.value.trim() === '') {
          detail.value = defDetail;
          filled = true;
        }
        if (solution.value.trim() === '') {
          solution.value = defSolution;
          filled = true;
        }
        if (suggestion.value.trim() === '') {
          suggestion.value = defSuggestion;
          filled = true;
        }

        if (filled) {
          this.innerHTML = '<i class="fas fa-check-circle"></i> เติมข้อมูลแล้ว';
          this.classList.add('filled');
        }

        if (detail.value.trim() !== '' && solution.value.trim() !== '' && suggestion.value.trim() !== '') {
          this.innerHTML = '<i class="fas fa-check-circle"></i> ข้อมูลครบถ้วน';
          this.classList.add('filled');
        }
      });
    }

    // Form Submit
    let submitting = false;
    document.getElementById('riskForm').addEventListener('submit', function(e) {
      e.preventDefault();
      if (submitting) {
        Swal.fire({
          icon: 'warning',
          title: 'รอสักครู่',
          text: 'กำลังดำเนินการ...'
        });
        return;
      }

      let otherEmpty = false;
      ['unit', 'risk_type', 'severity'].forEach(name => {
        const sel = document.querySelector(`input[name="${name}"]:checked`);
        if (sel && sel.value === 'อื่นๆ') {
          const inp = document.getElementById(name + '_other');
          if (inp && !inp.value.trim()) {
            otherEmpty = true;
            inp.classList.add('border-red-500');
            setTimeout(() => inp.classList.remove('border-red-500'), 2000);
          }
        }
      });
      if (otherEmpty) {
        Swal.fire({
          icon: 'warning',
          title: 'กรุณาระบุข้อมูล',
          text: 'เลือก "อื่นๆ" แต่ไม่ได้กรอก'
        });
        return;
      }

      const evInput = document.getElementById('event_datetime');
      const rpInput = document.getElementById('report_datetime');
      
      if (!evInput.value.trim()) {
        evInput.value = '<?= date('Y-m-d H:i') ?>';
      }
      if (!rpInput.value.trim()) {
        rpInput.value = '<?= date('Y-m-d H:i') ?>';
      }

      const ev = eventPicker.selectedDates[0];
      const rp = reportPicker.selectedDates[0];
      
      const evDate = ev || new Date(evInput.value);
      const rpDate = rp || new Date(rpInput.value);

      if (rpDate && evDate && rpDate < evDate) {
        Swal.fire({
          icon: 'warning',
          title: 'วันที่ไม่ถูกต้อง',
          text: 'วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุ'
        });
        return;
      }

      Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: 'คุณต้องการบันทึกรายงานนี้ใช่หรือไม่',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '✅ ยืนยัน',
        cancelButtonText: '❌ ยกเลิก'
      }).then(result => {
        if (!result.isConfirmed) return;
        submitting = true;
        const btn = document.querySelector('#riskForm button[type="submit"]');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
        fetch('action.php?action=save_risk', {
            method: 'POST',
            body: new FormData(document.getElementById('riskForm'))
          })
          .then(r => r.json()).then(data => {
            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                text: data.message
              }).then(() => window.location.href = 'risks.php');
            } else {
              Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด',
                text: data.message
              });
              submitting = false;
              btn.disabled = false;
              btn.innerHTML = orig;
            }
          }).catch(() => {
            Swal.fire({
              icon: 'error',
              title: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์'
            });
            submitting = false;
            btn.disabled = false;
            btn.innerHTML = orig;
          });
      });
    });
  });
</script>
<?php include 'includes/footer.php'; ?>