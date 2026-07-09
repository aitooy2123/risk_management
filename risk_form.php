<?php
/**
 * ฟอร์มเพิ่ม/แก้ไขข้อมูลความเสี่ยง (Card Layout) - UI สวยงาม
 * - ไล่สีตามระดับความรุนแรง
 * - Animation สวยงาม
 * - สถานะการดำเนินการแก้ไขได้เฉพาะ Admin เท่านั้น (แต่เลือกได้ตอนเพิ่มใหม่)
 * - สถานะเริ่มต้นเป็น "ยังไม่ดำเนินการ" เมื่อเพิ่มรายงานใหม่
 * - สถานะการดำเนินการมีสีแตกต่างกัน
 * - ตัวหนังสือระดับความรุนแรงใหญ่ขึ้น อ่านง่าย
 * - Hover มีสีตามระดับความรุนแรง
 * - Interactive Features: Live Preview, Auto-save, Keyboard Shortcuts
 * - Date Picker แบบ datetime-local (เสถียร 100%)
 * - วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์
 * - ปุ่มเติมข้อมูลอัตโนมัติสำหรับรายละเอียด
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$id = $_GET['id'] ?? null;
$risk = null;
$is_editable = true;
$is_admin = isAdmin();

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM risks WHERE id = ?");
    $stmt->execute([$id]);
    $risk = $stmt->fetch();
    if (!$risk) {
        redirect('risks.php');
    }
    if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) {
        redirect('risks.php');
    }

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
    'A' => ['label' => 'มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'border' => '#93c5fd', 'hover_bg' => '#dbeafe', 'icon' => 'fa-circle-info'],
    'B' => ['label' => 'เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล ไม่เกิดผลกระทบต่องาน', 'color' => '#22c55e', 'bg' => '#f0fdf4', 'border' => '#86efac', 'hover_bg' => '#dcfce7', 'icon' => 'fa-circle-check'],
    'C' => ['label' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับเบื้องต้น สามารถแก้ไขได้ด้วยตนเอง', 'color' => '#84cc16', 'bg' => '#f7fee7', 'border' => '#bef264', 'hover_bg' => '#ecfccb', 'icon' => 'fa-circle-exclamation'],
    'D' => ['label' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับปานกลางต้องให้เพื่อนร่วมงานช่วยแก้ไข', 'color' => '#eab308', 'bg' => '#fefce8', 'border' => '#fde047', 'hover_bg' => '#fef9c3', 'icon' => 'fa-triangle-exclamation'],
    'E' => ['label' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงต้องแจ้งหัวหน้างานช่วยแก้ไข', 'color' => '#f97316', 'bg' => '#fff7ed', 'border' => '#fdba74', 'hover_bg' => '#ffedd5', 'icon' => 'fa-fire'],
    'F' => ['label' => 'เกิดความเสี่ยง ถึงตัวบุคคล เกิดผลกระทบต่องานระดับสูงสุดไม่สามารถแก้ไขได้ รายงานผู้บริหาร', 'color' => '#ef4444', 'bg' => '#fef2f2', 'border' => '#fca5a5', 'hover_bg' => '#fee2e2', 'icon' => 'fa-skull']
];

$statuses = [
    'ยังไม่ดำเนินการ' => ['color' => '#6b7280', 'bg' => '#f3f4f6', 'icon' => 'fa-clock', 'label' => 'ยังไม่ดำเนินการ'],
    'กำลังดำเนินการ' => ['color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'fa-spinner', 'label' => 'กำลังดำเนินการ'],
    'ดำเนินการแล้ว' => ['color' => '#22c55e', 'bg' => '#f0fdf4', 'icon' => 'fa-check-circle', 'label' => 'ดำเนินการแล้ว'],
    'ยุติ' => ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-ban', 'label' => 'ยุติ']
];

$current_datetime = date('Y-m-d H:i');
$event_datetime  = $risk ? date('Y-m-d\TH:i', strtotime($risk['event_datetime'] ?? 'now')) : date('Y-m-d\TH:i');
$report_datetime = $risk ? date('Y-m-d\TH:i', strtotime($risk['report_datetime'] ?? 'now')) : date('Y-m-d\TH:i');

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const isEditable = <?= json_encode($is_editable) ?>;
    const isAdmin = <?= json_encode($is_admin) ?>;
    const riskId = <?= json_encode($id) ?>;
</script>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --primary-light: #eff6ff;
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #1e40af 40%, #2563eb 100%);
    }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
    }

    .form-container {
        max-width: 800px;
        margin: 0 auto;
    }

    /* Header */
    .form-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem;
        padding: 1.75rem 2.25rem;
        margin-bottom: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .form-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 350px;
        height: 350px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .form-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .form-header h2 {
        font-size: 1.6rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .form-header h2 .icon-circle {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .form-header p {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.9rem;
        position: relative;
        z-index: 1;
        margin-top: 0.5rem;
    }

    /* Objective Box */
    .objective-box {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .objective-box h3 {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .objective-box ul {
        list-style: none;
        padding: 0;
    }

    .objective-box li {
        padding: 0.3rem 0;
        color: #475569;
        font-size: 0.85rem;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .objective-box li::before {
        content: '✓';
        color: #2563eb;
        font-weight: 700;
        flex-shrink: 0;
    }

    /* Card */
    .form-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 1.25rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: all 0.2s;
    }

    .form-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .card-header {
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfc;
    }

    .card-header-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .card-header-title {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.95rem;
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

    .card-body {
        padding: 1.5rem;
    }

    /* Input */
    .form-input {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        font-size: 0.9rem;
        transition: all 0.2s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #fafbfc;
        color: #1e293b;
    }

    .form-input:hover {
        border-color: #cbd5e1;
        background: white;
    }

    .form-input:focus {
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    .form-input:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        border-style: dashed;
    }

    .form-input.error {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    input[type="datetime-local"].form-input {
        cursor: pointer;
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
        font-size: 0.85rem;
        margin-bottom: 0.35rem;
    }

    .form-label .required {
        color: #ef4444;
        font-size: 0.7rem;
    }

    /* Character Counter */
    .char-counter {
        font-size: 0.7rem;
        text-align: right;
        margin-top: 0.25rem;
        color: #94a3b8;
        transition: color 0.3s;
    }
    .char-counter.warning {
        color: #f59e0b;
    }
    .char-counter.danger {
        color: #ef4444;
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
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.55rem;
        cursor: pointer;
        transition: all 0.2s;
        background: white;
    }

    .radio-card:hover {
        border-color: #93c5fd;
        background: #f8faff;
    }

    .radio-card:has(input:checked) {
        border-color: #2563eb;
        background: #eff6ff;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.06);
    }

    .radio-card input[type="radio"] {
        accent-color: #2563eb;
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
        gap: 0.75rem;
    }

    .severity-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 0.5rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
        text-align: center;
        position: relative;
    }

    <?php foreach ($severityOptions as $key => $opt): ?>
    .severity-card.severity-<?= strtolower($key) ?>:hover {
        border-color: <?= $opt['color'] ?> !important;
        background: <?= $opt['hover_bg'] ?> !important;
        transform: translateY(-4px);
        box-shadow: 0 8px 25px <?= $opt['color'] ?>30 !important;
    }
    <?php endforeach; ?>

    .severity-card:has(input:checked) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }

    .severity-card input[type="radio"] {
        display: none;
    }

    .severity-card .sev-icon {
        font-size: 1.8rem;
        margin-bottom: 0.1rem;
        transition: transform 0.3s ease;
    }

    .severity-card:hover .sev-icon {
        transform: scale(1.15);
    }

    .severity-card .sev-letter {
        font-size: 1.4rem;
        font-weight: 700;
        transition: transform 0.3s ease;
    }

    .severity-card:hover .sev-letter {
        transform: scale(1.05);
    }

    .severity-card .sev-desc {
        font-size: 0.75rem;
        color: #64748b;
        line-height: 1.4;
        margin-top: 0.1rem;
        transition: color 0.3s ease;
    }

    .severity-card:hover .sev-desc {
        color: #475569;
    }

    /* Severity Preview */
    #severity-preview {
        margin-top: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 0.6rem;
        border: 2px solid #e2e8f0;
        display: none;
        align-items: center;
        gap: 0.75rem;
        animation: slideUp 0.3s ease;
    }
    #severity-preview.visible {
        display: flex;
    }
    #severity-preview .preview-icon {
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    #severity-preview .preview-text {
        display: flex;
        flex-direction: column;
    }
    #severity-preview .preview-letter {
        font-weight: 700;
        font-size: 1.1rem;
    }
    #severity-preview .preview-desc {
        font-size: 0.85rem;
        color: #64748b;
    }

    /* Status Select */
    .status-select {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        font-size: 0.9rem;
        transition: all 0.2s;
        outline: none;
        font-family: 'Sarabun', sans-serif;
        background: #fafbfc;
        color: #1e293b;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.9rem center;
        padding-right: 2.5rem;
    }

    .status-select:focus {
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    .status-select option {
        padding: 0.5rem;
        font-weight: 500;
    }

    .status-select option[value=""] {
        color: #94a3b8 !important;
        font-weight: 400 !important;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.8rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        transition: all 0.2s;
    }

    /* Buttons */
    .btn-submit {
        padding: 0.7rem 2rem;
        border-radius: 0.65rem;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-family: 'Sarabun', sans-serif;
        background: linear-gradient(135deg, #1e40af, #2563eb);
        color: white;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-submit:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    }

    .btn-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #94a3b8 !important;
        box-shadow: none !important;
        transform: none !important;
    }

    .btn-cancel {
        padding: 0.7rem 1.5rem;
        border-radius: 0.65rem;
        font-weight: 500;
        font-size: 0.85rem;
        background: #f1f5f9;
        color: #64748b;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid #e2e8f0;
    }

    .btn-cancel:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .btn-back {
        padding: 0.7rem 1.5rem;
        border-radius: 0.65rem;
        font-weight: 500;
        font-size: 0.85rem;
        background: #eff6ff;
        color: #2563eb;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid #bfdbfe;
    }

    .btn-back:hover {
        background: #dbeafe;
    }

    .btn-default {
        padding: 0.4rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        font-size: 0.8rem;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-family: 'Sarabun', sans-serif;
        user-select: none;
        -webkit-user-select: none;
    }

    .btn-default:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .btn-default:active {
        transform: scale(0.97);
        transition: transform 0.1s;
    }

    .btn-default.filled {
        background: #dbeafe;
        border-color: #93c5fd;
        color: #1e40af;
        cursor: default;
        pointer-events: none;
    }

    .btn-default.filled:hover {
        background: #dbeafe;
        transform: none;
    }

    /* Locked Overlay */
    .locked-overlay {
        background: #fef3c7;
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
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        background: #f1f5f9;
        color: #64748b;
        font-size: 0.9rem;
    }

    /* Auto-save indicator */
    #auto-save-indicator {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        background: #f0fdf4;
        border: 1px solid #86efac;
        color: #16a34a;
        padding: 0.5rem 1rem;
        border-radius: 0.6rem;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.15);
        display: none;
        align-items: center;
        gap: 0.5rem;
        z-index: 999;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-in {
        animation: slideUp 0.4s ease forwards;
    }

    .animate-in:nth-child(1) { animation-delay: 0s; }
    .animate-in:nth-child(2) { animation-delay: 0.04s; }
    .animate-in:nth-child(3) { animation-delay: 0.08s; }
    .animate-in:nth-child(4) { animation-delay: 0.12s; }
    .animate-in:nth-child(5) { animation-delay: 0.16s; }
    .animate-in:nth-child(6) { animation-delay: 0.2s; }
    .animate-in:nth-child(7) { animation-delay: 0.24s; }
    .animate-in:nth-child(8) { animation-delay: 0.28s; }

    /* Status Preview */
    #status-preview {
        margin-top: 0.75rem;
        display: none;
        align-items: center;
        gap: 0.5rem;
    }

    #status-preview.visible {
        display: flex;
    }

    .swal2-toast {
        font-family: 'Sarabun', sans-serif !important;
    }

    @media print {
        .sidebar, .form-header, .objective-box, .btn-submit, .btn-cancel, .btn-back,
        #auto-save-indicator, .locked-overlay, .card-header-badge {
            display: none !important;
        }
        body {
            background: white !important;
        }
        .form-card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
        .form-container {
            max-width: 100% !important;
        }
        .form-input, .status-select {
            border: 1px solid #ccc !important;
            background: white !important;
        }
        .severity-card:has(input:checked) {
            border: 2px solid #000 !important;
        }
        .severity-card {
            border: 1px solid #ccc !important;
        }
        #severity-preview {
            border: 1px solid #ccc !important;
        }
        .radio-card:has(input:checked) {
            border: 1px solid #000 !important;
        }
    }

    @media (max-width: 768px) {
        .radio-grid,
        .severity-grid {
            grid-template-columns: 1fr;
        }
        .form-header {
            padding: 1.25rem 1.25rem;
        }
        .form-header h2 {
            font-size: 1.2rem;
        }
        .card-body {
            padding: 1rem;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-6 overflow-y-auto">
        <div class="form-container">

            <!-- Header -->
            <div class="form-header">
                <h2>
                    <span class="icon-circle"><?= $id ? '✏️' : '➕' ?></span>
                    <?= $id ? 'แก้ไขรายงานความเสี่ยง' : 'เพิ่มรายงานความเสี่ยง' ?>
                </h2>
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
                        <div class="card-header-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-id-card"></i></div>
                        <h3 class="card-header-title">รหัสผู้รายงาน</h3>
                        <span class="card-header-badge" style="background:#fef2f2;color:#dc2626;">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <input type="text" name="reporter_code" id="reporter_code" value="<?= htmlspecialchars($reporter_code) ?>"
                            class="form-input" placeholder="เช่น R10001 (รหัสผู้รายงาน)" required <?= !$is_editable ? 'disabled' : '' ?>>
                    </div>
                </div>

                <!-- กลุ่มงาน -->
                <div class="form-card animate-in">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:#eef2ff;color:#4338ca;"><i class="fas fa-building"></i></div>
                        <h3 class="card-header-title">กลุ่มงานที่เกิดความเสี่ยง</h3>
                        <span class="card-header-badge" style="background:#fef2f2;color:#dc2626;">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="radio-grid" id="unit-group">
                            <?php foreach ($units as $u): ?>
                                <label class="radio-card">
                                    <input type="radio" name="unit" value="<?= $u ?>" <?= (($risk['unit'] ?? '') == $u) ? 'checked' : '' ?> required <?= !$is_editable ? 'disabled' : '' ?>>
                                    <span class="radio-label"><?= $u ?></span>
                                </label>
                            <?php endforeach; ?>
                            <label class="radio-card">
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
                        <div class="card-header-icon" style="background:#fffbeb;color:#d97706;"><i class="fas fa-tag"></i></div>
                        <h3 class="card-header-title">ประเภทของความเสี่ยง</h3>
                    </div>
                    <div class="card-body">
                        <div class="radio-grid" id="risk_type-group">
                            <?php foreach ($types as $t): ?>
                                <label class="radio-card">
                                    <input type="radio" name="risk_type" value="<?= $t ?>" <?= (($risk['risk_type'] ?? '') == $t) ? 'checked' : '' ?> <?= !$is_editable ? 'disabled' : '' ?>>
                                    <span class="radio-label"><?= $t ?></span>
                                </label>
                            <?php endforeach; ?>
                            <label class="radio-card">
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
                        <div class="card-header-icon" style="background:#fef2f2;color:#dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
                        <h3 class="card-header-title">ระดับความรุนแรง</h3>
                    </div>
                    <div class="card-body">
                        <div class="severity-grid" id="severity-group">
                            <?php foreach ($severityOptions as $key => $opt):
                                $isChecked = ($risk['severity'] ?? '') == $key;
                                $cardStyle = $isChecked ? "border-color:{$opt['color']};background:{$opt['bg']};box-shadow:0 0 0 3px {$opt['color']}30;" : '';
                            ?>
                                <label class="severity-card severity-<?= strtolower($key) ?>" style="<?= $cardStyle ?>" data-severity="<?= $key ?>" data-color="<?= $opt['color'] ?>" data-icon="<?= $opt['icon'] ?>" data-desc="<?= $opt['label'] ?>">
                                    <input type="radio" name="severity" value="<?= $key ?>" <?= $isChecked ? 'checked' : '' ?> <?= !$is_editable ? 'disabled' : '' ?>>
                                    <div class="sev-icon" style="color:<?= $opt['color'] ?>"><i class="fas <?= $opt['icon'] ?>"></i></div>
                                    <div class="sev-letter" style="color:<?= $opt['color'] ?>">ระดับ <?= $key ?></div>
                                    <div class="sev-desc"><?= $opt['label'] ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="severity-preview">
                            <span class="preview-icon"><i class="fas fa-circle-info"></i></span>
                            <div class="preview-text">
                                <span class="preview-letter">ระดับ A</span>
                                <span class="preview-desc">มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- วันเวลา -->
                <div class="form-card animate-in">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-calendar-alt"></i></div>
                        <h3 class="card-header-title">วันเวลา</h3>
                        <span class="card-header-badge" style="background:#fef2f2;color:#dc2626;">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">📅 วันที่เกิดเหตุการณ์ <span class="required">*</span></label>
                                <input type="datetime-local" id="event_datetime" name="event_datetime" 
                                    value="<?= $event_datetime ?>"
                                    class="form-input" required <?= !$is_editable ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="form-label">📅 วันที่รายงาน <span class="required">*</span></label>
                                <input type="datetime-local" id="report_datetime" name="report_datetime" 
                                    value="<?= $report_datetime ?>"
                                    class="form-input" required <?= !$is_editable ? 'disabled' : '' ?>>
                                <small class="text-gray-400" style="font-size: 0.7rem;">วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- รายละเอียดและแนวทางแก้ไข -->
                <div class="form-card animate-in">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:#f5f3ff;color:#6d28d9;"><i class="fas fa-pen-to-square"></i></div>
                        <h3 class="card-header-title">รายละเอียดและแนวทางแก้ไข</h3>
                        <span class="card-header-badge" style="background:#fef2f2;color:#dc2626;">จำเป็น</span>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label">📝 รายละเอียดเหตุการณ์ <span class="required">*</span></label>
                            <textarea name="detail" id="detail" rows="4" class="form-input" required
                                placeholder="อธิบายรายละเอียดเหตุการณ์ที่เกิดขึ้น..." <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['detail'] ?? '') ?></textarea>
                            <div class="char-counter" id="detail-counter">0 / 500</div>
                        </div>
                        <div>
                            <label class="form-label">🔧 การแก้ไขเบื้องต้น <span class="required">*</span></label>
                            <textarea name="initial_solution" id="initial_solution" rows="3" class="form-input" required
                                placeholder="ระบุการแก้ไขเบื้องต้นที่ได้ดำเนินการ..." <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['initial_solution'] ?? '') ?></textarea>
                            <div class="char-counter" id="solution-counter">0 / 500</div>
                        </div>
                        <div>
                            <label class="form-label">💡 ปัญหาและข้อเสนอแนะ <span class="required">*</span></label>
                            <textarea name="suggestion" id="suggestion" rows="3" class="form-input" required
                                placeholder="ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข..." <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['suggestion'] ?? '') ?></textarea>
                            <div class="char-counter" id="suggestion-counter">0 / 500</div>
                        </div>
                        <?php if ($is_editable): ?>
                            <div class="flex justify-end pt-2">
                                <button type="button" id="fillDefaultBtn" class="btn-default"
                                    data-default-detail="ไม่มีรายละเอียดเพิ่มเติม"
                                    data-default-solution="ไม่มีการแก้ไขเบื้องต้น"
                                    data-default-suggestion="ไม่มีข้อเสนอแนะเพิ่มเติม"
                                    onclick="fillDefaultTexts(); return false;">
                                    <i class="fas fa-pen"></i> ไม่มีข้อมูลในส่วนนี้
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- สถานะการดำเนินการ -->
                <?php if ($is_admin || !$id): ?>
                    <div class="form-card animate-in">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:#ecfdf5;color:#0d9488;"><i class="fas fa-chart-simple"></i></div>
                            <h3 class="card-header-title">สถานะการดำเนินการ</h3>
                            <?php if ($is_admin): ?>
                                <span class="card-header-badge admin-only"><i class="fas fa-crown"></i> Admin เท่านั้น</span>
                            <?php else: ?>
                                <span class="card-header-badge" style="background:#eff6ff;color:#2563eb;">เลือกสถานะ</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($is_editable): ?>
                                <select name="status" id="status" class="status-select">
                                    <option value="">-- กรุณาเลือกสถานะ --</option>
                                    <?php foreach ($statuses as $key => $st): ?>
                                        <?php
                                        if (!$id && $key == 'ยังไม่ดำเนินการ') {
                                            $selected = 'selected';
                                        } else {
                                            $selected = (($risk['status'] ?? '') == $key) ? 'selected' : '';
                                        }
                                        $color = $st['color'];
                                        ?>
                                        <option value="<?= $key ?>" <?= $selected ?> style="color:<?= $color ?>; font-weight:600;">
                                            <?= $st['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($is_admin): ?>
                                    <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle"></i> เฉพาะ Admin เท่านั้นที่สามารถเปลี่ยนสถานะได้</p>
                                <?php endif; ?>

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
                    <input type="hidden" name="status" value="<?= htmlspecialchars($risk['status'] ?? 'ยังไม่ดำเนินการ') ?>">
                <?php endif; ?>

                <!-- Buttons -->
                <div class="flex items-center gap-3 pt-2 pb-8 flex-wrap">
                    <?php if ($is_editable): ?>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-save"></i> บันทึกรายงาน
                        </button>
                        <a href="risks.php" class="btn-cancel">
                            <i class="fas fa-times"></i> ยกเลิก
                        </a>
                        <span class="text-xs text-gray-400 ml-auto hidden md:inline">Ctrl+S บันทึก • Esc ยกเลิก</span>
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

<!-- Auto-save Indicator -->
<div id="auto-save-indicator">
    <i class="fas fa-check-circle"></i> บันทึกอัตโนมัติ
</div>

<script>
function fillDefaultTexts() {
    var detail = document.getElementById('detail');
    var solution = document.getElementById('initial_solution');
    var suggestion = document.getElementById('suggestion');
    var btn = document.getElementById('fillDefaultBtn');
    
    if (!detail || !solution || !suggestion) {
        alert('ไม่พบช่องกรอกข้อมูล');
        return;
    }
    
    var hasFilled = false;
    
    if (detail.value.trim() === '') {
        detail.value = 'ไม่มีรายละเอียดเพิ่มเติม';
        detail.dispatchEvent(new Event('input', { bubbles: true }));
        detail.dispatchEvent(new Event('change', { bubbles: true }));
        hasFilled = true;
    }
    
    if (solution.value.trim() === '') {
        solution.value = 'ไม่มีการแก้ไขเบื้องต้น';
        solution.dispatchEvent(new Event('input', { bubbles: true }));
        solution.dispatchEvent(new Event('change', { bubbles: true }));
        hasFilled = true;
    }
    
    if (suggestion.value.trim() === '') {
        suggestion.value = 'ไม่มีข้อเสนอแนะเพิ่มเติม';
        suggestion.dispatchEvent(new Event('input', { bubbles: true }));
        suggestion.dispatchEvent(new Event('change', { bubbles: true }));
        hasFilled = true;
    }
    
    if (btn && hasFilled) {
        btn.innerHTML = '<i class="fas fa-check-circle"></i> เติมข้อมูลแล้ว';
        btn.classList.add('filled');
        btn.disabled = true;
    }
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'เติมข้อมูลเรียบร้อย',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (!isEditable) {
            Swal.fire({
                icon: 'info',
                title: 'ไม่สามารถแก้ไขได้',
                text: 'รายการนี้ถูกดำเนินการเสร็จสิ้นหรือยุติแล้ว',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        // ============================================================
        // DATE VALIDATION - วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์
        // ============================================================
        const eventDatetimeInput = document.getElementById('event_datetime');
        const reportDatetimeInput = document.getElementById('report_datetime');

        if (eventDatetimeInput && reportDatetimeInput) {
            // ตั้งค่าวันที่ปัจจุบันเป็น max
            const now = new Date();
            const todayStr = now.getFullYear() + '-' + 
                             String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                             String(now.getDate()).padStart(2, '0') + 'T' +
                             String(now.getHours()).padStart(2, '0') + ':' + 
                             String(now.getMinutes()).padStart(2, '0');
            
            eventDatetimeInput.max = todayStr;
            reportDatetimeInput.max = todayStr;
            
            // ตั้งค่า min ของวันที่รายงาน = วันที่เกิดเหตุการณ์
            if (eventDatetimeInput.value) {
                reportDatetimeInput.min = eventDatetimeInput.value;
                if (reportDatetimeInput.value && reportDatetimeInput.value < eventDatetimeInput.value) {
                    reportDatetimeInput.value = eventDatetimeInput.value;
                }
            }
            
            // เมื่อเปลี่ยนวันที่เกิดเหตุการณ์ -> ปรับ min ของวันที่รายงาน
            eventDatetimeInput.addEventListener('change', function() {
                if (this.value) {
                    reportDatetimeInput.min = this.value;
                    
                    // ถ้าวันที่รายงานน้อยกว่า -> ปรับให้เท่ากัน
                    if (reportDatetimeInput.value && reportDatetimeInput.value < this.value) {
                        reportDatetimeInput.value = this.value;
                        Swal.fire({
                            icon: 'info',
                            title: 'ปรับวันที่รายงาน',
                            text: 'วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                    
                    // ถ้ายังไม่มีวันที่รายงาน -> ตั้งให้เท่ากัน
                    if (!reportDatetimeInput.value) {
                        reportDatetimeInput.value = this.value;
                    }
                }
            });
            
            // ตรวจสอบเมื่อเปลี่ยนวันที่รายงาน
            reportDatetimeInput.addEventListener('change', function() {
                const eventDate = eventDatetimeInput.value;
                const reportDate = this.value;
                
                if (eventDate && reportDate && reportDate < eventDate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'วันที่ไม่ถูกต้อง',
                        text: '📅 วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์',
                        confirmButtonColor: '#2563eb'
                    });
                    this.value = eventDate;
                }
            });
            
            console.log('✅ Date Validation พร้อมใช้งาน');
        }

        // Toggle "อื่นๆ" Fields
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
        
        ['unit', 'risk_type'].forEach(name => {
            const radios = document.querySelectorAll(`input[name="${name}"]`);
            radios.forEach(r => r.addEventListener('change', function() {
                toggleOther(name, name + '_other');
            }));
            toggleOther(name, name + '_other');
            setTimeout(() => toggleOther(name, name + '_other'), 100);
        });

        // Severity Cards
        const severityCards = document.querySelectorAll('.severity-card');
        const previewEl = document.getElementById('severity-preview');

        function updateSeverityPreview(card) {
            if (!card) {
                previewEl.classList.remove('visible');
                return;
            }
            const icon = card.querySelector('.sev-icon i')?.className || 'fa-circle-info';
            const letter = card.querySelector('.sev-letter')?.textContent || 'ระดับ A';
            const desc = card.querySelector('.sev-desc')?.textContent || '';
            const color = card.dataset.color || '#2563eb';

            previewEl.querySelector('.preview-icon i').className = 'fas ' + icon;
            previewEl.querySelector('.preview-icon').style.color = color;
            previewEl.querySelector('.preview-letter').textContent = letter;
            previewEl.querySelector('.preview-letter').style.color = color;
            previewEl.querySelector('.preview-desc').textContent = desc;
            previewEl.style.borderColor = color;
            previewEl.style.background = color + '10';
            previewEl.classList.add('visible');
        }

        severityCards.forEach(card => {
            card.addEventListener('click', function(e) {
                const radio = this.querySelector('input[type="radio"]');
                if (radio && !radio.disabled) {
                    radio.checked = true;
                }

                severityCards.forEach(c => {
                    c.style.borderColor = '#e2e8f0';
                    c.style.background = 'white';
                    c.style.boxShadow = '';
                });

                const color = this.dataset.color || '#2563eb';
                this.style.borderColor = color;
                this.style.background = color + '18';
                this.style.boxShadow = '0 0 0 3px ' + color + '30';

                updateSeverityPreview(this);
            });
        });

        const checkedCard = document.querySelector('.severity-card input[type="radio"]:checked');
        if (checkedCard) {
            const parent = checkedCard.closest('.severity-card');
            if (parent) {
                const color = parent.dataset.color || '#2563eb';
                parent.style.borderColor = color;
                parent.style.background = color + '18';
                parent.style.boxShadow = '0 0 0 3px ' + color + '30';
                updateSeverityPreview(parent);
            }
        }

        // Character Counter
        function setupCharCounter(textareaId, counterId, maxLength = 500) {
            const textarea = document.getElementById(textareaId);
            const counter = document.getElementById(counterId);
            if (!textarea || !counter) return;

            function updateCounter() {
                const len = textarea.value.length;
                counter.textContent = len + ' / ' + maxLength;
                counter.className = 'char-counter';
                if (len > maxLength) {
                    counter.classList.add('danger');
                    textarea.classList.add('error');
                } else if (len > maxLength * 0.8) {
                    counter.classList.add('warning');
                    textarea.classList.remove('error');
                } else {
                    textarea.classList.remove('error');
                }
            }

            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }

        setupCharCounter('detail', 'detail-counter', 500);
        setupCharCounter('initial_solution', 'solution-counter', 500);
        setupCharCounter('suggestion', 'suggestion-counter', 500);

        // Status Select
        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            let statusPreview = document.getElementById('status-preview');
            if (!statusPreview) {
                statusPreview = document.createElement('div');
                statusPreview.id = 'status-preview';
                statusSelect.parentNode.appendChild(statusPreview);
            }
            
            const statusColors = {
                'ยังไม่ดำเนินการ': { color: '#6b7280', bg: '#f3f4f6', icon: 'fa-clock' },
                'กำลังดำเนินการ': { color: '#3b82f6', bg: '#eff6ff', icon: 'fa-spinner' },
                'ดำเนินการแล้ว': { color: '#22c55e', bg: '#f0fdf4', icon: 'fa-check-circle' },
                'ยุติ': { color: '#ef4444', bg: '#fef2f2', icon: 'fa-ban' }
            };
            
            statusSelect.addEventListener('change', function() {
                const selectedValue = this.value;
                
                if (selectedValue && statusColors[selectedValue]) {
                    const status = statusColors[selectedValue];
                    statusPreview.innerHTML = `
                        <span class="text-sm text-gray-500">ตัวอย่าง:</span>
                        <span class="status-badge" style="background:${status.bg}; color:${status.color}; border:1px solid ${status.color}40;">
                            <i class="fas ${status.icon}"></i>
                            ${selectedValue}
                        </span>
                    `;
                    statusPreview.classList.add('visible');
                } else {
                    statusPreview.classList.remove('visible');
                }
            });
            
            if (statusSelect.value) {
                statusSelect.dispatchEvent(new Event('change'));
            }
        }

        // Auto-save
        let autoSaveTimer = null;
        const form = document.getElementById('riskForm');
        const autoSaveIndicator = document.getElementById('auto-save-indicator');

        function autoSaveDraft() {
            if (!isEditable) return;
            const formData = new FormData(form);
            formData.append('auto_save', '1');

            fetch('action.php?action=save_risk_draft', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    autoSaveIndicator.style.display = 'flex';
                    setTimeout(() => {
                        autoSaveIndicator.style.display = 'none';
                    }, 3000);
                }
            })
            .catch(() => {});
        }

        form.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSaveDraft, 30000);
        });
        form.addEventListener('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSaveDraft, 30000);
        });

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (isEditable) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
            if (e.key === 'Escape') {
                const cancelBtn = document.querySelector('.btn-cancel');
                if (cancelBtn) {
                    e.preventDefault();
                    cancelBtn.click();
                }
            }
        });

        // Validation
        document.querySelectorAll('.form-input, .form-select').forEach(el => {
            el.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.classList.add('error');
                this.scrollIntoView({ behavior: 'smooth', block: 'center' });

                const msg = document.createElement('div');
                msg.className = 'text-red-500 text-xs mt-1 animate-in';
                msg.textContent = '⚠️ ' + (this.validationMessage || 'กรุณากรอกข้อมูลให้ถูกต้อง');
                this.parentNode.appendChild(msg);
                setTimeout(() => msg.remove(), 4000);
            });

            el.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });

        // Leave Warning
        let formChanged = false;
        form.addEventListener('input', () => { formChanged = true; });
        form.addEventListener('change', () => { formChanged = true; });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged && isEditable) {
                e.preventDefault();
                e.returnValue = 'คุณยังไม่ได้บันทึกข้อมูล ต้องการออกจากหน้านี้ใช่หรือไม่?';
                return e.returnValue;
            }
        });

        form.addEventListener('submit', function() {
            formChanged = false;
        });

        // Form Submit
        let submitting = false;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // ตรวจสอบวันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์
            const eventDate = document.getElementById('event_datetime').value;
            const reportDate = document.getElementById('report_datetime').value;
            
            if (eventDate && reportDate && reportDate < eventDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'วันที่ไม่ถูกต้อง',
                    text: '📅 วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            if (submitting) {
                Swal.fire({
                    icon: 'warning',
                    title: 'รอสักครู่',
                    text: 'กำลังดำเนินการ...',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            let otherEmpty = false;
            ['unit', 'risk_type'].forEach(name => {
                const sel = document.querySelector(`input[name="${name}"]:checked`);
                if (sel && sel.value === 'อื่นๆ') {
                    const inp = document.getElementById(name + '_other');
                    if (inp && !inp.value.trim()) {
                        otherEmpty = true;
                        inp.classList.add('error');
                        inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => inp.classList.remove('error'), 3000);
                    }
                }
            });
            if (otherEmpty) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาระบุข้อมูล',
                    text: 'เลือก "อื่นๆ" แต่ไม่ได้กรอก',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                text: 'คุณต้องการบันทึกรายงานนี้ใช่หรือไม่',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '✅ ยืนยัน',
                cancelButtonText: '❌ ยกเลิก'
            }).then(result => {
                if (!result.isConfirmed) return;

                submitting = true;
                const btn = document.getElementById('submitBtn');
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

                fetch('action.php?action=save_risk', {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ',
                            text: data.message,
                            confirmButtonColor: '#2563eb'
                        }).then(() => {
                            window.location.href = 'risks.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: data.message || 'เกิดข้อผิดพลาดในการบันทึก',
                            confirmButtonColor: '#2563eb'
                        });
                        submitting = false;
                        btn.disabled = false;
                        btn.innerHTML = orig;
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์',
                        text: 'กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต',
                        confirmButtonColor: '#2563eb'
                    });
                    submitting = false;
                    btn.disabled = false;
                    btn.innerHTML = orig;
                });
            });
        });

        console.log('✅ ระบบพร้อมใช้งาน! (Date Validation Active)');
    });
</script>
<?php include 'includes/footer.php'; ?>