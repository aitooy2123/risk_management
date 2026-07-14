<?php

/**
 * ฟอร์มเพิ่ม/แก้ไขข้อมูลความเสี่ยง (Premium Card Layout)
 * Version: 3.2 - Hide Status on New Risk
 * 
 * Features:
 * - 🎨 Premium UI/UX Design
 * - 🎯 Severity Cards with Advanced Hover Animation
 * - 📝 Summernote Rich Text Editor
 * - 💾 Auto-save Draft
 * - ⌨️ Keyboard Shortcuts
 * - 📱 Fully Responsive (Single Column)
 * - 🔒 Status Control (Admin Only)
 * - 🆕 Hide Status Card on New Risk Creation
 * - ✨ Smooth Animations
 * - 🖨️ Print Optimized
 * - 🔢 Numbered Objective List
 */

// =============================================
// 1. INITIALIZATION & SECURITY
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('index.php');

// =============================================
// 2. DATA FETCHING
// =============================================

// 🔧 กำหนดค่า $id ก่อนใช้งาน
$id = isset($_GET['id']) ? $_GET['id'] : null;
$risk = null;
$is_editable = true;
$is_admin = isAdmin();

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM risks WHERE id = ?");
    $stmt->execute([$id]);
    $risk = $stmt->fetch();
    
    if (!$risk) redirect('risks.php');
    if (!isAdmin() && $risk['user_id'] != $_SESSION['user_id']) redirect('risks.php');

    // Lock completed/terminated risks
    $locked_statuses = ['ดำเนินการแล้ว', 'ยุติ'];
    if (isset($risk['status']) && in_array($risk['status'], $locked_statuses)) {
        $is_editable = false;
    }
}

// =============================================
// 3. CONFIGURATION
// =============================================

$csrf_token = generateCsrfToken();
$_SESSION['form_token'] = bin2hex(random_bytes(32));

// Units
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

// Risk Types
$types = [
    'ความเสี่ยงทางด้านกลยุทธ์',
    'ความเสี่ยงทางด้านการเงิน',
    'ความเสี่ยงทางด้านการปฏิบัติงาน',
    'ความเสี่ยงทางด้านกฎหมาย',
    'ความเสี่ยงด้านสิ่งแวดล้อม',
    'ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข'
];

// Severity Options with Enhanced Design
$severityOptions = [
    'A' => [
        'label' => 'มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น',
        'short_label' => 'ต่ำมาก',
        'color' => '#3b82f6',
        'bg' => '#eff6ff',
        'border' => '#93c5fd',
        'hover_bg' => '#dbeafe',
        'icon' => 'fa-shield-halved',
        'gradient' => 'linear-gradient(135deg, #eff6ff, #dbeafe)',
        'hover_gradient' => 'linear-gradient(135deg, #dbeafe, #bfdbfe)',
        'hover_shadow' => '0 20px 40px rgba(59, 130, 246, 0.25)'
    ],
    'B' => [
        'label' => 'เกิดความเสี่ยง ยังไม่ถึงตัวบุคคล',
        'short_label' => 'ต่ำ',
        'color' => '#22c55e',
        'bg' => '#f0fdf4',
        'border' => '#86efac',
        'hover_bg' => '#dcfce7',
        'icon' => 'fa-circle-check',
        'gradient' => 'linear-gradient(135deg, #f0fdf4, #dcfce7)',
        'hover_gradient' => 'linear-gradient(135deg, #dcfce7, #bbf7d0)',
        'hover_shadow' => '0 20px 40px rgba(34, 197, 94, 0.25)'
    ],
    'C' => [
        'label' => 'เกิดความเสี่ยง ถึงตัวบุคคล ผลกระทบเบื้องต้น',
        'short_label' => 'ปานกลาง',
        'color' => '#84cc16',
        'bg' => '#f7fee7',
        'border' => '#bef264',
        'hover_bg' => '#ecfccb',
        'icon' => 'fa-circle-exclamation',
        'gradient' => 'linear-gradient(135deg, #f7fee7, #ecfccb)',
        'hover_gradient' => 'linear-gradient(135deg, #ecfccb, #d9f99d)',
        'hover_shadow' => '0 20px 40px rgba(132, 204, 22, 0.25)'
    ],
    'D' => [
        'label' => 'เกิดความเสี่ยง ผลกระทบปานกลาง ต้องให้เพื่อนร่วมงานช่วย',
        'short_label' => 'สูง',
        'color' => '#eab308',
        'bg' => '#fefce8',
        'border' => '#fde047',
        'hover_bg' => '#fef9c3',
        'icon' => 'fa-triangle-exclamation',
        'gradient' => 'linear-gradient(135deg, #fefce8, #fef9c3)',
        'hover_gradient' => 'linear-gradient(135deg, #fef9c3, #fef08a)',
        'hover_shadow' => '0 20px 40px rgba(234, 179, 8, 0.25)'
    ],
    'E' => [
        'label' => 'เกิดความเสี่ยง ผลกระทบสูง ต้องแจ้งหัวหน้างาน',
        'short_label' => 'สูงมาก',
        'color' => '#f97316',
        'bg' => '#fff7ed',
        'border' => '#fdba74',
        'hover_bg' => '#ffedd5',
        'icon' => 'fa-fire',
        'gradient' => 'linear-gradient(135deg, #fff7ed, #ffedd5)',
        'hover_gradient' => 'linear-gradient(135deg, #ffedd5, #fed7aa)',
        'hover_shadow' => '0 20px 40px rgba(249, 115, 22, 0.25)'
    ],
    'F' => [
        'label' => 'เกิดความเสี่ยง ผลกระทบสูงสุด ไม่สามารถแก้ไขได้',
        'short_label' => 'รุนแรงที่สุด',
        'color' => '#ef4444',
        'bg' => '#fef2f2',
        'border' => '#fca5a5',
        'hover_bg' => '#fee2e2',
        'icon' => 'fa-skull',
        'gradient' => 'linear-gradient(135deg, #fef2f2, #fee2e2)',
        'hover_gradient' => 'linear-gradient(135deg, #fee2e2, #fecaca)',
        'hover_shadow' => '0 20px 40px rgba(239, 68, 68, 0.25)'
    ]
];

// Status Options
$statuses = [
    'ยังไม่ดำเนินการ' => ['color' => '#6b7280', 'bg' => '#f3f4f6', 'icon' => 'fa-clock', 'label' => 'ยังไม่ดำเนินการ'],
    'กำลังดำเนินการ' => ['color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'fa-spinner', 'label' => 'กำลังดำเนินการ'],
    'ดำเนินการแล้ว' => ['color' => '#22c55e', 'bg' => '#f0fdf4', 'icon' => 'fa-check-circle', 'label' => 'ดำเนินการแล้ว'],
    'ยุติ' => ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-ban', 'label' => 'ยุติ']
];

// Date/Time
$current_datetime = date('Y-m-d H:i');
$event_datetime  = $risk ? date('Y-m-d\TH:i', strtotime($risk['event_datetime'] ?? 'now')) : date('Y-m-d\TH:i');
$report_datetime = $risk ? date('Y-m-d\TH:i', strtotime($risk['report_datetime'] ?? 'now')) : date('Y-m-d\TH:i');

// Reporter Code
if ($id) {
    $reporter_code = $risk['reporter_code'] ?? '';
} else {
    $stmt = $pdo->prepare("SELECT reporter_code FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $reporter_code = $user['reporter_code'] ?? '';
}

// 🔧 ตรวจสอบว่า $id เป็นค่าว่างหรือไม่ ถ้าเป็นให้กำหนดเป็น null
if (empty($id)) {
    $id = null;
}
?>
<?php include 'includes/header.php'; ?>

<!-- CSS & JS Libraries -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // 🔧 ส่งค่าเป็น boolean และ null ให้ JavaScript
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
        --form-max-width: 1000px;
    }

    * { box-sizing: border-box; }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
        margin: 0;
        padding: 0;
    }

    .main-wrapper {
        display: flex;
        min-height: 100vh;
        width: 100%;
    }

    .content-area {
        flex: 1;
        min-width: 0;
        padding: 2rem;
        overflow-y: auto;
        height: 100vh;
        display: flex;
        justify-content: center;
    }

    .form-container {
        width: 100%;
        max-width: var(--form-max-width);
        margin: 0 auto;
    }

    /* ============================================ */
    /* HEADER - PREMIUM DESIGN */
    /* ============================================ */
    .form-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 25%, #312e81 60%, #1e1b4b 100%);
        border-radius: 1.5rem;
        padding: 2rem 2.5rem;
        margin-bottom: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.3);
    }

    .form-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .form-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 250px;
        height: 250px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
        border-radius: 50%;
    }

    .header-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-icon {
        width: 56px;
        height: 56px;
        min-width: 56px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-text h1 {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0;
        line-height: 1.3;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .header-text p {
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.9rem;
        margin: 0.25rem 0 0 0;
    }

    .header-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.8rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin-top: 0.5rem;
    }

    /* ============================================ */
    /* OBJECTIVE BOX WITH NUMBERED LIST */
    /* ============================================ */
    .objective-box {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .objective-box h3 {
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .objective-box h3 i {
        color: #2563eb;
        font-size: 1.1rem;
    }

    .objective-list {
        list-style: none;
        padding: 0;
        margin: 0;
        counter-reset: objective-counter;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .objective-list li {
        padding: 0.7rem 0;
        padding-left: 3rem;
        color: #475569;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        position: relative;
        counter-increment: objective-counter;
        line-height: 1.5;
    }

    .objective-list li::before {
        content: counter(objective-counter);
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        font-weight: 700;
        font-size: 0.85rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    /* ============================================ */
    /* CARDS - PREMIUM DESIGN */
    /* ============================================ */
    .form-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 1.25rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: all 0.3s ease;
    }

    .form-card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
    }

    .card-header {
        padding: 1.15rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(135deg, #fafbfc, #f8fafc);
    }

    .card-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .card-title {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.95rem;
        flex: 1;
    }

    .card-badge {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 0.2rem 0.7rem;
        border-radius: 9999px;
        white-space: nowrap;
    }

    .card-badge.required {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .card-badge.optional {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .card-badge.admin {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .card-body {
        padding: 1.5rem;
    }

    /* ============================================ */
    /* SUMMERNOTE OVERRIDES */
    /* ============================================ */
    .note-editor.note-frame {
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 0.75rem !important;
        overflow: hidden !important;
        transition: all 0.3s ease !important;
    }

    .note-editor.note-frame:focus-within {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08) !important;
    }

    .note-editor.note-frame .note-toolbar {
        background: #fafbfc !important;
        border-bottom: 1px solid #f1f5f9 !important;
        padding: 0.5rem !important;
        border-radius: 0.75rem 0.75rem 0 0 !important;
    }

    .note-editor.note-frame .note-editing-area {
        border-radius: 0 0 0.75rem 0.75rem !important;
    }

    .note-editor.note-frame .note-statusbar {
        border-top: 1px solid #f1f5f9 !important;
        background: #fafbfc !important;
        border-radius: 0 0 0.75rem 0.75rem !important;
    }

    .note-editor.note-frame .note-btn {
        border-radius: 0.5rem !important;
        border: 1px solid transparent !important;
        background: transparent !important;
        color: #475569 !important;
        font-family: 'Sarabun', sans-serif !important;
        font-size: 0.8rem !important;
        padding: 0.3rem 0.6rem !important;
        transition: all 0.15s !important;
    }

    .note-editor.note-frame .note-btn:hover {
        background: #e2e8f0 !important;
        color: #1e293b !important;
    }

    .note-editor.note-frame .note-btn.active {
        background: #dbeafe !important;
        color: #2563eb !important;
        border-color: #93c5fd !important;
    }

    .note-editor.note-frame .note-editable {
        font-family: 'Sarabun', sans-serif !important;
        font-size: 0.9rem !important;
        line-height: 1.7 !important;
        color: #1e293b !important;
        min-height: 150px !important;
    }

    .note-editor.note-frame .note-placeholder {
        font-family: 'Sarabun', sans-serif !important;
        color: #94a3b8 !important;
        font-size: 0.9rem !important;
    }

    .note-editor.disabled {
        opacity: 0.6;
        pointer-events: none;
    }

    .note-editor.disabled .note-editable {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
    }

    /* ============================================ */
    /* FORM ELEMENTS */
    /* ============================================ */
    .form-input {
        width: 100%;
        padding: 0.7rem 0.9rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.65rem;
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
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
    }

    .form-input:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        border-style: dashed;
    }

    .form-input.error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
        animation: shake 0.5s ease;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
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

    .form-label .required-star {
        color: #ef4444;
        font-size: 0.7rem;
        margin-left: 0.15rem;
    }

    /* ============================================ */
    /* RADIO CARDS */
    /* ============================================ */
    .radio-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 0.6rem;
    }

    .radio-card {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.7rem 0.9rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.6rem;
        cursor: pointer;
        transition: all 0.2s;
        background: white;
    }

    .radio-card:hover {
        border-color: #93c5fd;
        background: #f8faff;
        transform: translateX(2px);
    }

    .radio-card:has(input:checked) {
        border-color: #2563eb;
        background: #eff6ff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.06);
    }

    .radio-card input[type="radio"] {
        accent-color: #2563eb;
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .radio-card .radio-label {
        font-size: 0.85rem;
        color: #334155;
        font-weight: 500;
        cursor: pointer;
    }

    /* ============================================ */
    /* 🎯 SEVERITY CARDS - ENHANCED HOVER EFFECTS */
    /* ============================================ */
    .severity-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.85rem;
    }

    .severity-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1.25rem 0.75rem;
        border: 2px solid #e2e8f0;
        border-radius: 1rem;
        cursor: pointer;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        background: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .severity-card::before {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 1rem;
        opacity: 0;
        transition: opacity 0.35s ease;
        z-index: -1;
        pointer-events: none;
    }

    .severity-card:hover::before {
        opacity: 1;
    }

    .severity-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
        pointer-events: none;
    }

    .severity-card:hover::after {
        left: 100%;
    }

    .severity-card input[type="radio"] {
        display: none;
    }

    .severity-card .sev-icon {
        font-size: 1.8rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 1;
    }

    .severity-card .sev-letter {
        font-size: 1.3rem;
        font-weight: 800;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .severity-card .sev-level {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .severity-card .sev-desc {
        font-size: 0.73rem;
        color: #64748b;
        line-height: 1.4;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .severity-card.severity-a { --sev-color: #3b82f6; }
    .severity-card.severity-b { --sev-color: #22c55e; }
    .severity-card.severity-c { --sev-color: #84cc16; }
    .severity-card.severity-d { --sev-color: #eab308; }
    .severity-card.severity-e { --sev-color: #f97316; }
    .severity-card.severity-f { --sev-color: #ef4444; }

    .severity-card:hover {
        transform: translateY(-8px) scale(1.03);
        border-color: var(--sev-color) !important;
        background: linear-gradient(135deg, 
            color-mix(in srgb, var(--sev-color) 5%, white),
            color-mix(in srgb, var(--sev-color) 12%, white)
        ) !important;
        box-shadow: 
            0 20px 40px color-mix(in srgb, var(--sev-color) 25%, transparent),
            0 0 0 1px color-mix(in srgb, var(--sev-color) 30%, transparent) !important;
    }

    .severity-card:hover .sev-icon {
        transform: scale(1.25) rotate(8deg);
        filter: drop-shadow(0 4px 8px color-mix(in srgb, var(--sev-color) 40%, transparent));
    }

    .severity-card:hover .sev-letter {
        transform: scale(1.1);
        color: var(--sev-color) !important;
    }

    .severity-card:hover .sev-level {
        color: var(--sev-color) !important;
        letter-spacing: 1px;
    }

    .severity-card:hover .sev-desc {
        color: #475569;
    }

    .severity-card:hover {
        animation: borderPulse 2s ease-in-out infinite;
    }

    @keyframes borderPulse {
        0%, 100% {
            border-color: var(--sev-color);
            box-shadow: 
                0 20px 40px color-mix(in srgb, var(--sev-color) 25%, transparent),
                0 0 0 1px color-mix(in srgb, var(--sev-color) 30%, transparent);
        }
        50% {
            border-color: color-mix(in srgb, var(--sev-color) 60%, white);
            box-shadow: 
                0 25px 50px color-mix(in srgb, var(--sev-color) 35%, transparent),
                0 0 0 3px color-mix(in srgb, var(--sev-color) 40%, transparent);
        }
    }

    .severity-card.selected {
        transform: translateY(-3px);
        border-color: var(--sev-color) !important;
        background: linear-gradient(135deg, 
            color-mix(in srgb, var(--sev-color) 8%, white),
            color-mix(in srgb, var(--sev-color) 15%, white)
        ) !important;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--sev-color) 25%, transparent) !important;
    }

    .severity-card.selected .sev-icon {
        transform: scale(1.1);
    }

    .severity-preview {
        margin-top: 1rem;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        border: 2px solid #e2e8f0;
        display: none;
        align-items: center;
        gap: 1rem;
        animation: slideUp 0.3s ease;
        background: white;
    }

    .severity-preview.active {
        display: flex;
    }

    .severity-preview .preview-icon {
        font-size: 2rem;
        flex-shrink: 0;
        width: 50px;
        text-align: center;
    }

    .severity-preview .preview-info {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .severity-preview .preview-level {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .severity-preview .preview-desc {
        font-size: 0.85rem;
        color: #64748b;
    }

    /* ============================================ */
    /* STATUS SELECT */
    /* ============================================ */
    .status-select {
        width: 100%;
        padding: 0.7rem 0.9rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.65rem;
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

    .status-select:hover {
        border-color: #93c5fd;
    }

    .status-select:focus {
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid;
    }

    .status-preview-area {
        margin-top: 0.75rem;
        display: none;
        align-items: center;
        gap: 0.5rem;
    }

    .status-preview-area.active {
        display: flex;
    }

    /* ============================================ */
    /* LOCKED OVERLAY */
    /* ============================================ */
    .locked-overlay {
        background: linear-gradient(135deg, #fef3c7, #fef9c3);
        border: 2px dashed #fcd34d;
        border-radius: 0.85rem;
        padding: 1.25rem;
        text-align: center;
        color: #92400e;
        font-weight: 600;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        font-size: 0.9rem;
    }

    .locked-overlay i {
        font-size: 1.2rem;
        color: #f59e0b;
    }

    /* ============================================ */
    /* BUTTONS */
    /* ============================================ */
    .btn {
        padding: 0.7rem 1.5rem;
        border-radius: 0.65rem;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s;
        font-family: 'Sarabun', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        border: none;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1e40af, #2563eb);
        color: white;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    }

    .btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .btn-outline {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    .btn-outline:hover {
        background: #dbeafe;
    }

    .btn-sm {
        padding: 0.4rem 1rem;
        font-size: 0.8rem;
        border-radius: 0.5rem;
        font-weight: 500;
    }

    .btn-danger {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .btn-danger:hover {
        background: #fee2e2;
    }

    /* ============================================ */
    /* AUTO-SAVE INDICATOR */
    /* ============================================ */
    .auto-save-toast {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: #f0fdf4;
        border: 1px solid #86efac;
        color: #16a34a;
        padding: 0.6rem 1.2rem;
        border-radius: 0.75rem;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.15);
        display: none;
        align-items: center;
        gap: 0.5rem;
        z-index: 999;
        animation: slideUp 0.3s ease;
    }

    /* ============================================ */
    /* UTILITIES */
    /* ============================================ */
    .grid { display: grid; gap: 1rem; }
    .grid-2 { grid-template-columns: 1fr 1fr; }
    .grid-3 { grid-template-columns: repeat(3, 1fr); }

    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .justify-end { justify-content: flex-end; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }

    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-3 { margin-top: 0.75rem; }
    .mb-1 { margin-bottom: 0.25rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-4 { margin-bottom: 1rem; }
    .pt-2 { padding-top: 0.5rem; }
    .pb-4 { padding-bottom: 1rem; }
    .pb-8 { padding-bottom: 2rem; }

    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.85rem; }
    .text-gray-400 { color: #94a3b8; }
    .text-gray-500 { color: #64748b; }
    .text-red-500 { color: #ef4444; }

    .hidden { display: none !important; }
    .w-full { width: 100%; }
    .space-y-4 > * + * { margin-top: 1rem; }

    /* ============================================ */
    /* ANIMATIONS */
    /* ============================================ */
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .animate-slide-up { animation: slideUp 0.4s ease forwards; }

    .stagger-1 { animation-delay: 0.05s; }
    .stagger-2 { animation-delay: 0.1s; }
    .stagger-3 { animation-delay: 0.15s; }
    .stagger-4 { animation-delay: 0.2s; }
    .stagger-5 { animation-delay: 0.25s; }
    .stagger-6 { animation-delay: 0.3s; }
    .stagger-7 { animation-delay: 0.35s; }

    /* ============================================ */
    /* RESPONSIVE */
    /* ============================================ */
    @media (max-width: 1200px) {
        .severity-grid { grid-template-columns: repeat(3, 1fr); }
        .radio-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .content-area { padding: 1rem; }
        
        .form-header { 
            padding: 1.25rem 1.5rem; 
            border-radius: 1rem; 
        }
        
        .header-icon { 
            width: 44px; height: 44px; min-width: 44px; 
            font-size: 1.2rem; border-radius: 12px; 
        }
        
        .header-text h1 { font-size: 1.2rem; }
        .header-text p { font-size: 0.78rem; }

        .severity-grid { 
            grid-template-columns: repeat(2, 1fr); 
            gap: 0.6rem; 
        }
        
        .severity-card { padding: 1rem 0.5rem; }
        .severity-card .sev-icon { font-size: 1.4rem; }
        .severity-card .sev-letter { font-size: 1.1rem; }
        .severity-card .sev-desc { font-size: 0.68rem; }

        .radio-grid { grid-template-columns: 1fr; }
        
        .grid-2 { grid-template-columns: 1fr; }
        .grid-3 { grid-template-columns: 1fr; }
        
        .btn { width: 100%; justify-content: center; }
        
        .card-body { padding: 1rem; }
        .card-header { padding: 0.85rem 1rem; }
        
        .objective-list li { padding-left: 2.5rem; font-size: 0.85rem; }
        .objective-list li::before { width: 28px; height: 28px; font-size: 0.75rem; }
    }

    @media (max-width: 480px) {
        .content-area { padding: 0.5rem; }
        
        .severity-grid { 
            grid-template-columns: 1fr 1fr; 
            gap: 0.4rem; 
        }
        
        .severity-card { padding: 0.75rem 0.3rem; }
        .severity-card .sev-icon { font-size: 1.2rem; }
        .severity-card .sev-letter { font-size: 0.95rem; }
        .severity-card .sev-desc { font-size: 0.62rem; }
        
        .form-header { padding: 1rem; }
        .header-text h1 { font-size: 1.05rem; }
    }

    /* ============================================ */
    /* PRINT STYLES */
    /* ============================================ */
    @media print {
        .sidebar, .auto-save-toast, .locked-overlay, 
        .btn, .note-toolbar, .note-statusbar { display: none !important; }
        
        body { background: white !important; }
        .content-area { padding: 0 !important; height: auto !important; }
        .form-card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
    }

    /* Fallback for browsers that don't support color-mix() */
    @supports not (color: color-mix(in srgb, red 50%, blue)) {
        .severity-card.severity-a:hover {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe) !important;
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.25), 0 0 0 1px rgba(59, 130, 246, 0.3) !important;
        }
        
        .severity-card.severity-b:hover {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0) !important;
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.25), 0 0 0 1px rgba(34, 197, 94, 0.3) !important;
        }
        
        .severity-card.severity-c:hover {
            background: linear-gradient(135deg, #ecfccb, #d9f99d) !important;
            box-shadow: 0 20px 40px rgba(132, 204, 22, 0.25), 0 0 0 1px rgba(132, 204, 22, 0.3) !important;
        }
        
        .severity-card.severity-d:hover {
            background: linear-gradient(135deg, #fef9c3, #fef08a) !important;
            box-shadow: 0 20px 40px rgba(234, 179, 8, 0.25), 0 0 0 1px rgba(234, 179, 8, 0.3) !important;
        }
        
        .severity-card.severity-e:hover {
            background: linear-gradient(135deg, #ffedd5, #fed7aa) !important;
            box-shadow: 0 20px 40px rgba(249, 115, 22, 0.25), 0 0 0 1px rgba(249, 115, 22, 0.3) !important;
        }
        
        .severity-card.severity-f:hover {
            background: linear-gradient(135deg, #fee2e2, #fecaca) !important;
            box-shadow: 0 20px 40px rgba(239, 68, 68, 0.25), 0 0 0 1px rgba(239, 68, 68, 0.3) !important;
        }
    }
</style>

<!-- Main Layout -->
<div class="main-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="content-area">
        <div class="form-container">
            
            <!-- Header -->
            <div class="form-header">
                <div class="header-content">
                    <div class="header-icon">
                        <?= $id ? '✏️' : '📝' ?>
                    </div>
                    <div>
                        <div class="header-text">
                            <h1><?= $id ? 'แก้ไขรายงานความเสี่ยง' : 'เพิ่มรายงานความเสี่ยงใหม่' ?></h1>
                            <p>ศูนย์อนามัยที่ 8 อุดรธานี | ระบบบริหารจัดการความเสี่ยง</p>
                        </div>
                        <div class="header-badge">
                            <i class="fas fa-shield-halved"></i>
                            ข้อมูลทั้งหมดจะถูกเก็บเป็นความลับ
                        </div>
                    </div>
                </div>
            </div>

            <!-- Locked Warning -->
            <?php if (!$is_editable): ?>
                <div class="locked-overlay">
                    <i class="fas fa-lock"></i>
                    <span>รายการนี้ถูกดำเนินการเสร็จสิ้นหรือยุติแล้ว <strong>ไม่สามารถแก้ไขข้อมูลได้</strong></span>
                </div>
            <?php endif; ?>

            <!-- 🔢 Objective Box with Numbered List -->
            <div class="objective-box animate-slide-up">
                <h3>
                    <i class="fas fa-bullseye"></i>
                    วัตถุประสงค์ของการรายงาน
                </h3>
                <ol class="objective-list">
                    <li>แก้ไขเหตุการณ์ที่เกิดขึ้นในหน่วยงานได้อย่างเหมาะสมและทันเวลา</li>
                    <li>ป้องกัน/ลดความเสียหายที่อาจเกิดขึ้นในงานให้น้อยลงหรือไม่มีเลย</li>
                    <li>หาแนวทางป้องกันไม่ให้เกิดอุบัติการณ์เสี่ยงซ้ำในอนาคต</li>
                </ol>
            </div>

            <!-- Form -->
            <form id="riskForm" method="POST" action="action.php?action=save_risk">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
                <input type="hidden" name="id" value="<?= $id ?>">

                <!-- Card 1: Reporter Code -->
                <div class="form-card animate-slide-up stagger-1">
                    <div class="card-header">
                        <div class="card-icon" style="background:#eff6ff;color:#2563eb;">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <h3 class="card-title">รหัสผู้รายงาน</h3>
                        <span class="card-badge required">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <input type="text" name="reporter_code" id="reporter_code" 
                               value="<?= htmlspecialchars($reporter_code) ?>"
                               class="form-input" 
                               placeholder="กรอกรหัสผู้รายงาน (เช่น R10001)"
                               required <?= !$is_editable ? 'disabled' : '' ?>>
                    </div>
                </div>

                <!-- Card 2: Unit -->
                <div class="form-card animate-slide-up stagger-2">
                    <div class="card-header">
                        <div class="card-icon" style="background:#eef2ff;color:#4338ca;">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="card-title">กลุ่มงานที่เกิดความเสี่ยง</h3>
                        <span class="card-badge required">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="radio-grid" id="unit-group">
                            <?php foreach ($units as $u): ?>
                                <label class="radio-card">
                                    <input type="radio" name="unit" value="<?= $u ?>" 
                                           <?= (($risk['unit'] ?? '') == $u) ? 'checked' : '' ?> 
                                           required <?= !$is_editable ? 'disabled' : '' ?>>
                                    <span class="radio-label"><?= $u ?></span>
                                </label>
                            <?php endforeach; ?>
                            <label class="radio-card">
                                <input type="radio" name="unit" value="อื่นๆ" 
                                       <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? 'checked' : '' ?> 
                                       <?= !$is_editable ? 'disabled' : '' ?>>
                                <span class="radio-label">อื่นๆ (โปรดระบุ)</span>
                            </label>
                        </div>
                        <input type="text" name="unit_other" id="unit_other" 
                               value="<?= htmlspecialchars($risk['unit_other'] ?? '') ?>"
                               class="form-input mt-2 <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? '' : 'hidden' ?>"
                               placeholder="ระบุกลุ่มงานอื่นๆ..." 
                               <?= (($risk['unit'] ?? '') == 'อื่นๆ') ? '' : 'disabled' ?> 
                               <?= !$is_editable ? 'disabled' : '' ?>>
                    </div>
                </div>

                <!-- Card 3: Risk Type -->
                <div class="form-card animate-slide-up stagger-3">
                    <div class="card-header">
                        <div class="card-icon" style="background:#fffbeb;color:#d97706;">
                            <i class="fas fa-tag"></i>
                        </div>
                        <h3 class="card-title">ประเภทของความเสี่ยง</h3>
                        <span class="card-badge required">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="radio-grid" id="risk_type-group">
                            <?php foreach ($types as $t): ?>
                                <label class="radio-card">
                                    <input type="radio" name="risk_type" value="<?= $t ?>" 
                                           <?= (($risk['risk_type'] ?? '') == $t) ? 'checked' : '' ?> 
                                           <?= !$is_editable ? 'disabled' : '' ?>>
                                    <span class="radio-label"><?= $t ?></span>
                                </label>
                            <?php endforeach; ?>
                            <label class="radio-card">
                                <input type="radio" name="risk_type" value="อื่นๆ" 
                                       <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? 'checked' : '' ?> 
                                       <?= !$is_editable ? 'disabled' : '' ?>>
                                <span class="radio-label">อื่นๆ (โปรดระบุ)</span>
                            </label>
                        </div>
                        <input type="text" name="risk_type_other" id="risk_type_other" 
                               value="<?= htmlspecialchars($risk['risk_type_other'] ?? '') ?>"
                               class="form-input mt-2 <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? '' : 'hidden' ?>"
                               placeholder="ระบุประเภทอื่นๆ..." 
                               <?= (($risk['risk_type'] ?? '') == 'อื่นๆ') ? '' : 'disabled' ?> 
                               <?= !$is_editable ? 'disabled' : '' ?>>
                    </div>
                </div>

                <!-- Card 4: Severity - Enhanced Hover -->
                <div class="form-card animate-slide-up stagger-4">
                    <div class="card-header">
                        <div class="card-icon" style="background:#fef2f2;color:#dc2626;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="card-title">ระดับความรุนแรง</h3>
                        <span class="card-badge required">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="severity-grid" id="severity-group">
                            <?php foreach ($severityOptions as $key => $opt):
                                $isChecked = ($risk['severity'] ?? '') == $key;
                            ?>
                                <label class="severity-card severity-<?= strtolower($key) ?> <?= $isChecked ? 'selected' : '' ?>"
                                       data-severity="<?= $key ?>"
                                       data-color="<?= $opt['color'] ?>"
                                       data-icon="<?= $opt['icon'] ?>"
                                       data-desc="<?= $opt['label'] ?>"
                                       data-level="<?= $opt['short_label'] ?>"
                                       style="<?= $isChecked ? "--sev-color:{$opt['color']};border-color:{$opt['color']};background:{$opt['gradient']};box-shadow:0 0 0 4px {$opt['color']}25;" : "--sev-color:{$opt['color']};" ?>">
                                    <input type="radio" name="severity" value="<?= $key ?>" 
                                           <?= $isChecked ? 'checked' : '' ?> 
                                           <?= !$is_editable ? 'disabled' : '' ?>>
                                    <div class="sev-icon" style="color:<?= $opt['color'] ?>">
                                        <i class="fas <?= $opt['icon'] ?>"></i>
                                    </div>
                                    <div class="sev-letter" style="color:<?= $opt['color'] ?>">
                                        ระดับ <?= $key ?>
                                    </div>
                                    <div class="sev-level" style="color:<?= $opt['color'] ?>">
                                        <?= $opt['short_label'] ?>
                                    </div>
                                    <div class="sev-desc"><?= $opt['label'] ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Severity Preview -->
                        <div class="severity-preview" id="severity-preview">
                            <div class="preview-icon">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <div class="preview-info">
                                <div class="preview-level">ระดับ A - ต่ำมาก</div>
                                <div class="preview-desc">มีโอกาสเกิดความเสี่ยงแต่ยังไม่เกิดขึ้น</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Date/Time -->
                <div class="form-card animate-slide-up stagger-5">
                    <div class="card-header">
                        <div class="card-icon" style="background:#f0fdf4;color:#16a34a;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="card-title">วันเวลา</h3>
                        <span class="card-badge required">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-2 gap-4">
                            <div>
                                <label class="form-label">
                                    📅 วันที่เกิดเหตุการณ์ <span class="required-star">*</span>
                                </label>
                                <input type="datetime-local" id="event_datetime" name="event_datetime"
                                       value="<?= $event_datetime ?>"
                                       class="form-input" required <?= !$is_editable ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="form-label">
                                    📅 วันที่รายงาน <span class="required-star">*</span>
                                </label>
                                <input type="datetime-local" id="report_datetime" name="report_datetime"
                                       value="<?= $report_datetime ?>"
                                       class="form-input" required <?= !$is_editable ? 'disabled' : '' ?>>
                                <small class="text-xs text-gray-400 mt-1" style="display:block;">
                                    <i class="fas fa-info-circle"></i> วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Details (Summernote) -->
                <div class="form-card animate-slide-up stagger-6">
                    <div class="card-header">
                        <div class="card-icon" style="background:#f5f3ff;color:#6d28d9;">
                            <i class="fas fa-pen-to-square"></i>
                        </div>
                        <h3 class="card-title">รายละเอียดและแนวทางแก้ไข</h3>
                        <span class="card-badge required">จำเป็น</span>
                    </div>
                    <div class="card-body">
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">📝 รายละเอียดเหตุการณ์ <span class="required-star">*</span></label>
                                <textarea name="detail" id="detail" class="summernote-editor"
                                    placeholder="อธิบายรายละเอียดเหตุการณ์ที่เกิดขึ้น..." 
                                    <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['detail'] ?? '') ?></textarea>
                            </div>
                            
                            <div>
                                <label class="form-label">🔧 การแก้ไขเบื้องต้น <span class="required-star">*</span></label>
                                <textarea name="initial_solution" id="initial_solution" class="summernote-editor"
                                    placeholder="ระบุการแก้ไขเบื้องต้นที่ได้ดำเนินการ..." 
                                    <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['initial_solution'] ?? '') ?></textarea>
                            </div>
                            
                            <div>
                                <label class="form-label">💡 ปัญหาและข้อเสนอแนะ <span class="required-star">*</span></label>
                                <textarea name="suggestion" id="suggestion" class="summernote-editor"
                                    placeholder="ปัญหาและข้อเสนอแนะที่อยากให้ช่วยแก้ไข..." 
                                    <?= !$is_editable ? 'disabled' : '' ?>><?= htmlspecialchars($risk['suggestion'] ?? '') ?></textarea>
                            </div>
                            
                            <?php if ($is_editable): ?>
                                <div class="flex justify-end pt-2">
                                    <button type="button" id="fillDefaultBtn" class="btn btn-sm btn-secondary"
                                        onclick="fillDefaultTexts(); return false;">
                                        <i class="fas fa-pen"></i> ไม่มีข้อมูลในส่วนนี้
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 🆕 Card 7: Status - แสดงเฉพาะตอนแก้ไข -->
                <?php if ($id): ?>
                    <div class="form-card animate-slide-up stagger-7">
                        <div class="card-header">
                            <div class="card-icon" style="background:#ecfdf5;color:#0d9488;">
                                <i class="fas fa-chart-simple"></i>
                            </div>
                            <h3 class="card-title">สถานะการดำเนินการ</h3>
                            <?php if ($is_admin): ?>
                                <span class="card-badge admin"><i class="fas fa-crown"></i> Admin</span>
                            <?php else: ?>
                                <span class="card-badge optional">สถานะ</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($is_editable): ?>
                                <select name="status" id="status" class="status-select" <?= !$is_admin ? 'disabled' : '' ?>>
                                    <option value="">-- กรุณาเลือกสถานะ --</option>
                                    <?php foreach ($statuses as $key => $st): 
                                        $selected = ((($risk['status'] ?? '') == $key) ? 'selected' : '');
                                    ?>
                                        <option value="<?= $key ?>" <?= $selected ?> 
                                                style="color:<?= $st['color'] ?>; font-weight:600;">
                                            <?= $st['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <?php if ($is_admin): ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="fas fa-info-circle"></i> คุณสามารถเปลี่ยนสถานะได้ในฐานะ Admin
                                    </p>
                                <?php else: ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="fas fa-lock"></i> เฉพาะ Admin เท่านั้นที่เปลี่ยนสถานะได้
                                    </p>
                                <?php endif; ?>
                                
                                <div class="status-preview-area" id="status-preview"></div>
                            <?php else: ?>
                                <input type="hidden" name="status" value="<?= htmlspecialchars($risk['status'] ?? 'ยังไม่ดำเนินการ') ?>">
                                <div class="flex items-center gap-3" style="padding:0.7rem 0.9rem;border:1.5px solid #e2e8f0;border-radius:0.65rem;background:#f1f5f9;">
                                    <i class="fas fa-lock text-gray-400"></i>
                                    <span style="font-size:0.9rem;color:#64748b;">สถานะปัจจุบัน:</span>
                                    <?php
                                    $currentStatus = $risk['status'] ?? 'ยังไม่ดำเนินการ';
                                    if (isset($statuses[$currentStatus])):
                                        $info = $statuses[$currentStatus];
                                    ?>
                                        <span class="status-badge" style="background:<?= $info['bg'] ?>; color:<?= $info['color'] ?>; border-color:<?= $info['color'] ?>40;">
                                            <i class="fas <?= $info['icon'] ?>"></i>
                                            <?= $info['label'] ?>
                                        </span>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($currentStatus) ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 🆕 ตอนเพิ่มใหม่: ส่งสถานะเริ่มต้นแบบ hidden -->
                    <input type="hidden" name="status" value="ยังไม่ดำเนินการ">
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3 pt-2 pb-8 flex-wrap">
                    <?php if ($is_editable): ?>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> บันทึกรายงาน
                        </button>
                        <a href="risks.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> ยกเลิก
                        </a>
                        <span class="text-xs text-gray-400" id="shortcut-hint" style="display:none;">
                            ⌨️ Ctrl+S บันทึก • Esc ยกเลิก
                        </span>
                    <?php else: ?>
                        <a href="risks.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto-save Toast -->
<div class="auto-save-toast" id="auto-save-toast">
    <i class="fas fa-check-circle"></i> บันทึกฉบับร่างอัตโนมัติ
</div>

<script>
    // Show shortcut hint on desktop
    if (window.innerWidth >= 768) {
        const hint = document.getElementById('shortcut-hint');
        if (hint) hint.style.display = 'inline';
    }

    /**
     * Fill default texts in Summernote editors
     */
    function fillDefaultTexts() {
        const defaultTexts = {
            detail: 'ไม่มีรายละเอียดเพิ่มเติม',
            initial_solution: 'ไม่มีการแก้ไขเบื้องต้น',
            suggestion: 'ไม่มีข้อเสนอแนะเพิ่มเติม'
        };

        let hasFilled = false;
        const btn = document.getElementById('fillDefaultBtn');

        Object.keys(defaultTexts).forEach(id => {
            const $editor = $('#' + id);
            if ($editor.length > 0 && $editor.data('summernote')) {
                const isEmpty = $editor.summernote('isEmpty');
                const content = $editor.summernote('code').replace(/<[^>]*>/g, '').trim();
                
                if (isEmpty || !content || content === '<p><br></p>') {
                    $editor.summernote('code', defaultTexts[id]);
                    hasFilled = true;
                }
            }
        });

        if (btn && hasFilled) {
            btn.innerHTML = '<i class="fas fa-check-circle"></i> เติมข้อมูลแล้ว';
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-danger');
            btn.disabled = true;
        }

        Swal.fire({
            icon: 'success',
            title: 'เติมข้อมูลเรียบร้อย',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ============================================
        // SUMMERNOTE INITIALIZATION
        // ============================================
        const summernoteConfig = {
            height: 200,
            minHeight: 150,
            maxHeight: 400,
            lang: 'th-TH',
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'table', 'hr']],
                ['view', ['codeview', 'help']]
            ],
            fontSizes: ['12', '14', '16', '18', '20', '24', '30'],
            callbacks: {
                onChange: function() {
                    clearTimeout(window._autoSaveTimer);
                    window._autoSaveTimer = setTimeout(autoSaveDraft, 30000);
                    window._formChanged = true;
                }
            }
        };

        // Initialize all Summernote editors
        ['detail', 'initial_solution', 'suggestion'].forEach(id => {
            const $el = $('#' + id);
            if ($el.length > 0) {
                $el.summernote({
                    ...summernoteConfig,
                    placeholder: $el.attr('placeholder') || 'พิมพ์เนื้อหาที่นี่...'
                });
                
                if (!isEditable) {
                    $el.summernote('disable');
                    $el.closest('.note-editor').addClass('disabled');
                }
            }
        });

        // ============================================
        // AUTO-SAVE DRAFT
        // ============================================
        function autoSaveDraft() {
            ['detail', 'initial_solution', 'suggestion'].forEach(id => {
                const $el = $('#' + id);
                if ($el.length > 0 && $el.data('summernote')) {
                    $el.val($el.summernote('code'));
                }
            });

            const formData = new FormData(document.getElementById('riskForm'));
            formData.append('auto_save', '1');
            
            fetch('action.php?action=save_risk_draft', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const toast = document.getElementById('auto-save-toast');
                        if (toast) {
                            toast.style.display = 'flex';
                            setTimeout(() => { toast.style.display = 'none'; }, 3000);
                        }
                    }
                })
                .catch(() => {});
        }

        // ============================================
        // DATE VALIDATION
        // ============================================
        const eventDatetime = document.getElementById('event_datetime');
        const reportDatetime = document.getElementById('report_datetime');

        if (eventDatetime && reportDatetime) {
            const now = new Date();
            const todayStr = now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0') + 'T' +
                String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0');

            eventDatetime.max = todayStr;
            reportDatetime.max = todayStr;

            if (eventDatetime.value) {
                reportDatetime.min = eventDatetime.value;
                if (reportDatetime.value && reportDatetime.value < eventDatetime.value) {
                    reportDatetime.value = eventDatetime.value;
                }
            }

            eventDatetime.addEventListener('change', function() {
                if (this.value) {
                    reportDatetime.min = this.value;
                    if (reportDatetime.value && reportDatetime.value < this.value) {
                        reportDatetime.value = this.value;
                        Swal.fire({
                            icon: 'info',
                            title: 'ปรับวันที่รายงานอัตโนมัติ',
                            text: 'วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์',
                            timer: 2500,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                    if (!reportDatetime.value) reportDatetime.value = this.value;
                }
            });

            reportDatetime.addEventListener('change', function() {
                if (eventDatetime.value && this.value && this.value < eventDatetime.value) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'วันที่ไม่ถูกต้อง',
                        text: '📅 วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์',
                        confirmButtonColor: '#2563eb'
                    });
                    this.value = eventDatetime.value;
                }
            });
        }

        // ============================================
        // TOGGLE "อื่นๆ" FIELDS
        // ============================================
        function toggleOther(radioName, inputId) {
            const selected = document.querySelector(`input[name="${radioName}"]:checked`);
            const input = document.getElementById(inputId);
            if (!input) return;
            
            if (selected && selected.value === 'อื่นๆ') {
                input.classList.remove('hidden');
                input.disabled = false;
                if (!input.value.trim()) input.focus();
            } else {
                input.classList.add('hidden');
                input.disabled = true;
            }
        }

        ['unit', 'risk_type'].forEach(name => {
            const radios = document.querySelectorAll(`input[name="${name}"]`);
            radios.forEach(r => r.addEventListener('change', () => toggleOther(name, name + '_other')));
            toggleOther(name, name + '_other');
            setTimeout(() => toggleOther(name, name + '_other'), 100);
        });

        // ============================================
        // SEVERITY CARDS - INTERACTIVE
        // ============================================
        const severityCards = document.querySelectorAll('.severity-card');
        const previewEl = document.getElementById('severity-preview');

        function updateSeverityPreview(card) {
            if (!card) {
                previewEl.classList.remove('active');
                return;
            }
            
            const color = card.dataset.color || '#2563eb';
            const icon = card.dataset.icon || 'fa-circle-info';
            const severity = card.dataset.severity || 'A';
            const level = card.dataset.level || '';
            const desc = card.dataset.desc || '';

            previewEl.querySelector('.preview-icon i').className = 'fas ' + icon;
            previewEl.querySelector('.preview-icon').style.color = color;
            previewEl.querySelector('.preview-level').textContent = `ระดับ ${severity} - ${level}`;
            previewEl.querySelector('.preview-level').style.color = color;
            previewEl.querySelector('.preview-desc').textContent = desc;
            previewEl.style.borderColor = color;
            previewEl.style.background = color + '10';
            previewEl.classList.add('active');
        }

        severityCards.forEach(card => {
            card.addEventListener('click', function(e) {
                const radio = this.querySelector('input[type="radio"]');
                if (radio && !radio.disabled) {
                    radio.checked = true;
                }
                
                // Reset all cards
                severityCards.forEach(c => {
                    c.classList.remove('selected');
                    c.style.borderColor = '#e2e8f0';
                    c.style.background = 'white';
                    c.style.boxShadow = '';
                });
                
                // Highlight selected
                const color = this.dataset.color || '#2563eb';
                this.classList.add('selected');
                this.style.setProperty('--sev-color', color);
                this.style.borderColor = color;
                this.style.background = `linear-gradient(135deg, ${color}10, ${color}18)`;
                this.style.boxShadow = `0 0 0 4px ${color}25`;
                
                updateSeverityPreview(this);
            });
        });

        // Initialize preview for pre-selected card
        const checkedCard = document.querySelector('.severity-card input[type="radio"]:checked');
        if (checkedCard) {
            updateSeverityPreview(checkedCard.closest('.severity-card'));
        }

        // ============================================
        // STATUS PREVIEW (เฉพาะตอนแก้ไข)
        // ============================================
        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            const statusPreview = document.getElementById('status-preview');
            const statusColors = {
                'ยังไม่ดำเนินการ': { color: '#6b7280', bg: '#f3f4f6', icon: 'fa-clock' },
                'กำลังดำเนินการ': { color: '#3b82f6', bg: '#eff6ff', icon: 'fa-spinner' },
                'ดำเนินการแล้ว': { color: '#22c55e', bg: '#f0fdf4', icon: 'fa-check-circle' },
                'ยุติ': { color: '#ef4444', bg: '#fef2f2', icon: 'fa-ban' }
            };

            statusSelect.addEventListener('change', function() {
                const value = this.value;
                if (value && statusColors[value]) {
                    const s = statusColors[value];
                    statusPreview.innerHTML = `
                        <span class="text-sm text-gray-500">ตัวอย่าง:</span>
                        <span class="status-badge" style="background:${s.bg};color:${s.color};border-color:${s.color}40;">
                            <i class="fas ${s.icon}"></i> ${value}
                        </span>`;
                    statusPreview.classList.add('active');
                } else {
                    statusPreview.classList.remove('active');
                }
            });
            
            if (statusSelect.value) statusSelect.dispatchEvent(new Event('change'));
        }

        // ============================================
        // FORM SUBMISSION
        // ============================================
        let submitting = false;
        const form = document.getElementById('riskForm');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Sync Summernote content
            ['detail', 'initial_solution', 'suggestion'].forEach(id => {
                const $el = $('#' + id);
                if ($el.length > 0 && $el.data('summernote')) {
                    $el.val($el.summernote('code'));
                }
            });

            // Validate dates
            const eventDate = eventDatetime?.value;
            const reportDate = reportDatetime?.value;
            if (eventDate && reportDate && reportDate < eventDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'วันที่ไม่ถูกต้อง',
                    text: '📅 วันที่รายงานต้องไม่ก่อนวันที่เกิดเหตุการณ์',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            // Validate "อื่นๆ" fields
            let otherEmpty = false;
            ['unit', 'risk_type'].forEach(name => {
                const selected = document.querySelector(`input[name="${name}"]:checked`);
                if (selected && selected.value === 'อื่นๆ') {
                    const input = document.getElementById(name + '_other');
                    if (input && !input.value.trim()) {
                        otherEmpty = true;
                        input.classList.add('error');
                        setTimeout(() => input.classList.remove('error'), 3000);
                    }
                }
            });

            if (otherEmpty) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาระบุข้อมูล',
                    text: 'เลือก "อื่นๆ" แต่ไม่ได้กรอกรายละเอียด',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            if (submitting) return;

            // Confirmation
            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                text: 'กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนบันทึก',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
                cancelButtonText: 'ตรวจสอบอีกครั้ง'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        text: 'กรุณารอสักครู่',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => Swal.showLoading()
                    });

                    submitting = true;
                    const btn = document.getElementById('submitBtn');
                    const origText = btn.innerHTML;
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
                                title: 'บันทึกสำเร็จ!',
                                text: data.message || 'ข้อมูลถูกบันทึกเรียบร้อยแล้ว',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'risks.php';
                            });
                            setTimeout(() => { window.location.href = 'risks.php'; }, 2500);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message || 'ไม่สามารถบันทึกข้อมูลได้',
                                confirmButtonColor: '#2563eb'
                            });
                            submitting = false;
                            btn.disabled = false;
                            btn.innerHTML = origText;
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
                        btn.innerHTML = origText;
                    });
                }
            });
        });

        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (isEditable) form.dispatchEvent(new Event('submit'));
            }
            if (e.key === 'Escape') {
                const cancelBtn = document.querySelector('.btn-secondary[href="risks.php"]');
                if (cancelBtn && isEditable) {
                    e.preventDefault();
                    window.location.href = 'risks.php';
                }
            }
        });

        // ============================================
        // FORM CHANGE DETECTION
        // ============================================
        window._formChanged = false;
        form.addEventListener('input', () => { window._formChanged = true; });
        form.addEventListener('change', () => { window._formChanged = true; });
        
        window.addEventListener('beforeunload', function(e) {
            if (window._formChanged && isEditable) {
                e.preventDefault();
                e.returnValue = 'คุณยังไม่ได้บันทึกข้อมูล ต้องการออกจากหน้านี้ใช่หรือไม่?';
                return e.returnValue;
            }
        });
        
        form.addEventListener('submit', function() {
            window._formChanged = false;
        });

        console.log('✅ Premium Risk Form v3.2 Ready!');
        console.log('🆕 Status Card Hidden on New Risk Creation');
        console.log('🎯 Enhanced Severity Hover Effects Active');
        console.log('🔢 Numbered Objective List Active');
    });
</script>

<?php include 'includes/footer.php'; ?>