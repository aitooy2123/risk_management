<?php
/**
 * ดูรายละเอียดผู้ใช้ - UI สวยงาม
 * - แสดงข้อมูลผู้ใช้แบบละเอียด
 * - สถิติการรายงานความเสี่ยง
 * - เฉพาะ Admin เท่านั้นที่เข้าถึงได้
 * - วันที่แสดง พ.ศ. ไทย (วัน เดือน ปี)
 */
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('dashboard.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    redirect('users.php');
}

// ===== ฟังก์ชันแปลงวันที่เป็น พ.ศ. ไทย =====
function thaiDateFull($date) {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp) + 543;
    
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    return $day . ' ' . $thaiMonths[$month] . ' ' . $year;
}

function thaiDateShort($date) {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp) + 543;
    
    $thaiMonthsShort = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    
    return $day . ' ' . $thaiMonthsShort[$month] . ' ' . $year;
}

// ดึงข้อมูลผู้ใช้
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'ไม่พบผู้ใช้ที่ต้องการดู';
    redirect('users.php');
}

// สถิติความเสี่ยงของผู้ใช้
$stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalRisks = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ? AND status = 'ดำเนินการแล้ว'");
$stmt->execute([$user_id]);
$completedRisks = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ? AND (status = 'ยังไม่ดำเนินการ' OR status IS NULL OR status = '')");
$stmt->execute([$user_id]);
$pendingRisks = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ? AND status = 'กำลังดำเนินการ'");
$stmt->execute([$user_id]);
$inProgressRisks = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM risks WHERE user_id = ? AND status = 'ยุติ'");
$stmt->execute([$user_id]);
$terminatedRisks = $stmt->fetchColumn();

// ดึงรายการความเสี่ยงล่าสุด
$stmt = $pdo->prepare("
    SELECT r.*, rr.id as report_id 
    FROM risks r 
    LEFT JOIN risk_reports rr ON r.id = rr.risk_id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recentRisks = $stmt->fetchAll();

// สถิติตามระดับความรุนแรง
$stmt = $pdo->prepare("
    SELECT severity, COUNT(*) as count 
    FROM risks 
    WHERE user_id = ? 
    GROUP BY severity 
    ORDER BY severity
");
$stmt->execute([$user_id]);
$severityStats = $stmt->fetchAll();

// วันที่สมัครเป็น พ.ศ.
$registerDateFull = thaiDateFull($user['created_at']);

function getSeverityLabel($severity) {
    $labels = [
        'A' => 'ต่ำมาก',
        'B' => 'ต่ำ',
        'C' => 'ปานกลาง',
        'D' => 'สูง',
        'E' => 'สูงมาก',
        'F' => 'สูงสุด',
    ];
    return $labels[$severity] ?? $severity;
}

function getSeverityBadgeClass($severity) {
    $classes = [
        'A' => 'bg-blue-50 text-blue-700',
        'B' => 'bg-sky-50 text-sky-700',
        'C' => 'bg-cyan-50 text-cyan-700',
        'D' => 'bg-amber-50 text-amber-700',
        'E' => 'bg-orange-50 text-orange-700',
        'F' => 'bg-red-50 text-red-700',
    ];
    return $classes[$severity] ?? 'bg-slate-50 text-slate-600';
}

function getStatusBadgeClass($status) {
    if (empty($status)) $status = 'ยังไม่ดำเนินการ';
    $classes = [
        'ยังไม่ดำเนินการ' => 'bg-amber-50 text-amber-700',
        'กำลังดำเนินการ' => 'bg-sky-50 text-sky-700',
        'ดำเนินการแล้ว' => 'bg-emerald-50 text-emerald-700',
        'ยุติ' => 'bg-red-50 text-red-700',
    ];
    return $classes[$status] ?? 'bg-slate-50 text-slate-500';
}
?>
<?php include 'includes/header.php'; ?>

<style>
    :root {
        --primary: #2563eb;
        --primary-light: #eff6ff;
        --surface: #ffffff;
        --border: #e2e8f0;
        --text: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
    }

    body {
        background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 30%, #ede9fe 60%, #fce7f3 100%);
        min-height: 100vh;
        font-family: 'Sarabun', sans-serif;
    }

    .page-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Header */
    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #2563eb 100%);
        border-radius: 1.5rem;
        padding: 1.75rem 2.25rem;
        margin-bottom: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 350px;
        height: 350px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 50%;
    }

    .page-header::after {
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

    .page-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        z-index: 1;
    }

    .page-header .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 0.5rem;
        color: white;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s;
        position: relative;
        z-index: 1;
        margin-top: 0.75rem;
    }

    .page-header .back-btn:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    /* Profile Card */
    .profile-card {
        background: var(--surface);
        border-radius: 1rem;
        border: 1px solid var(--border);
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .profile-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .avatar-lg {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e2e8f0;
    }

    .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 0.25rem;
    }

    .profile-role {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .profile-role.admin {
        background: #fef3c7;
        color: #92400e;
    }

    .profile-role.user {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Info Grid */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .info-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .info-value {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
        padding: 0.5rem 0.75rem;
        background: #f8fafc;
        border-radius: 0.5rem;
        border: 1px solid #f1f5f9;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: var(--surface);
        border-radius: 0.75rem;
        border: 1px solid var(--border);
        padding: 1.25rem;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Table */
    .table-card {
        background: var(--surface);
        border-radius: 1rem;
        border: 1px solid var(--border);
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.5rem;
    }

    .table-header {
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        font-weight: 700;
        color: var(--text);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 0.6rem 1rem;
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
    }

    td {
        padding: 0.65rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.83rem;
        color: var(--text-secondary);
    }

    tr:last-child td {
        border-bottom: none;
    }

    tbody tr {
        transition: background 0.15s;
    }

    tbody tr:hover {
        background: #f8fafc;
    }

    .pill {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.18rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-muted);
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 0.6rem;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        font-family: 'Sarabun', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        transition: all 0.25s;
    }

    .btn-action.add {
        background: #f5f3ff;
        color: #7c3aed;
        border-color: #ddd6fe;
    }

    .btn-action.add:hover {
        background: #ede9fe;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .profile-header {
            flex-direction: column;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            padding: 1.25rem 1.5rem;
        }
        
        .page-header h1 {
            font-size: 1.25rem;
        }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-4 md:p-5 overflow-y-auto">
        <div class="page-container">

            <!-- Header -->
            <div class="page-header">
                <h1>👤 ข้อมูลผู้ใช้</h1>
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้ารายชื่อผู้ใช้
                </a>
            </div>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <img src="avatars/<?= htmlspecialchars($user['avatar'] ?: 'default.png') ?>" 
                         alt="<?= htmlspecialchars($user['username']) ?>" 
                         class="avatar-lg"
                         onerror="this.src='avatars/default.png'">
                    <div style="flex:1;">
                        <div class="profile-name">
                            <?= htmlspecialchars($user['username']) ?>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <span class="pill" style="background:#2563eb;color:white;font-size:0.65rem;margin-left:0.5rem;">คุณ</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($user['fullname'])): ?>
                            <div style="color:#64748b;margin-bottom:0.5rem;"><?= htmlspecialchars($user['fullname']) ?></div>
                        <?php endif; ?>
                        <span class="profile-role <?= $user['role'] == 'admin' ? 'admin' : 'user' ?>">
                            <i class="fas <?= $user['role'] == 'admin' ? 'fa-crown' : 'fa-user' ?>"></i>
                            <?= $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้ทั่วไป' ?>
                        </span>
                    </div>
                    <div>
                        <a href="user_form.php?id=<?= $user['id'] ?>" class="btn-action add">
                            <i class="fas fa-edit"></i> แก้ไขผู้ใช้
                        </a>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-envelope"></i> อีเมล</div>
                        <div class="info-value"><?= htmlspecialchars($user['email'] ?: '-') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-phone"></i> เบอร์โทรศัพท์</div>
                        <div class="info-value"><?= htmlspecialchars($user['phone'] ?: '-') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-building"></i> แผนก</div>
                        <div class="info-value"><?= htmlspecialchars($user['department'] ?: '-') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-id-card"></i> รหัสผู้รายงาน</div>
                        <div class="info-value"><?= htmlspecialchars($user['reporter_code'] ?: '-') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar-alt"></i> วันที่สมัคร</div>
                        <div class="info-value"><?= $registerDateFull ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-fingerprint"></i> ID ผู้ใช้</div>
                        <div class="info-value" style="font-family:monospace;">#<?= $user['id'] ?></div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#eff6ff;color:#2563eb;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($totalRisks) ?></div>
                    <div class="stat-label">รายงานทั้งหมด</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef3c7;color:#d97706;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= number_format($pendingRisks) ?></div>
                    <div class="stat-label">ยังไม่ดำเนินการ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dbeafe;color:#2563eb;">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-value"><?= number_format($inProgressRisks) ?></div>
                    <div class="stat-label">กำลังดำเนินการ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#d1fae5;color:#059669;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($completedRisks) ?></div>
                    <div class="stat-label">ดำเนินการแล้ว</div>
                </div>
            </div>

            <!-- Recent Risks -->
            <div class="table-card">
                <div class="table-header">
                    <i class="fas fa-history text-blue-600 mr-2"></i>
                    รายการความเสี่ยงล่าสุด
                </div>
                <?php if (empty($recentRisks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox text-3xl mb-2" style="color:#cbd5e1;"></i>
                        <p>ยังไม่มีรายการความเสี่ยง</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>กลุ่มงาน</th>
                                    <th>ประเภท</th>
                                    <th>ระดับ</th>
                                    <th>สถานะ</th>
                                    <th>วันที่</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRisks as $risk): 
                                    $status = !empty($risk['status']) ? $risk['status'] : 'ยังไม่ดำเนินการ';
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($risk['unit'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($risk['risk_type'] ?? '-') ?></td>
                                        <td>
                                            <span class="pill <?= getSeverityBadgeClass($risk['severity']) ?>">
                                                <?= htmlspecialchars($risk['severity']) ?> - <?= getSeverityLabel($risk['severity']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pill <?= getStatusBadgeClass($status) ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                        <td style="color:#94a3b8;font-size:0.8rem;">
                                            <?= thaiDateShort($risk['created_at']) ?>
                                        </td>
                                        <td>
                                            <a href="view_risk.php?id=<?= $risk['id'] ?>" 
                                               style="color:#2563eb;text-decoration:none;font-size:0.8rem;font-weight:500;">
                                                ดูรายละเอียด →
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Severity Distribution -->
            <?php if (!empty($severityStats)): ?>
            <div class="table-card">
                <div class="table-header">
                    <i class="fas fa-chart-bar text-blue-600 mr-2"></i>
                    การกระจายตามระดับความรุนแรง
                </div>
                <div style="padding:1.25rem;">
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;">
                        <?php foreach ($severityStats as $stat): 
                            $percentage = $totalRisks > 0 ? round(($stat['count'] / $totalRisks) * 100, 1) : 0;
                        ?>
                            <div style="flex:1;min-width:120px;text-align:center;padding:1rem;background:#f8fafc;border-radius:0.75rem;border:1px solid #f1f5f9;transition:all 0.3s;">
                                <span class="pill <?= getSeverityBadgeClass($stat['severity']) ?>" style="margin-bottom:0.5rem;">
                                    <?= htmlspecialchars($stat['severity']) ?>
                                </span>
                                <div style="font-size:1.5rem;font-weight:700;color:#0f172a;"><?= $stat['count'] ?></div>
                                <div style="font-size:0.75rem;color:#94a3b8;"><?= $percentage ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>