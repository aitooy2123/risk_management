<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

// รับค่าช่วงวันที่
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = '';

$date_where = '';
if ($date_from && $date_to) {
    $date_where = " WHERE event_datetime BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59' ";
} elseif ($date_from) {
    $date_where = " WHERE event_datetime >= '{$date_from} 00:00:00' ";
} elseif ($date_to) {
    $date_where = " WHERE event_datetime <= '{$date_to} 23:59:59' ";
}

// สถิติรวม
$totalRisks = $pdo->query("SELECT COUNT(*) FROM risks" . $date_where)->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// จำนวนรายการแต่ละระดับ (ใช้ query GROUP BY เพียงครั้งเดียว)
$levelOrder = ['A', 'B', 'C', 'D', 'F', 'E'];
$levelNames = [
    'A' => 'ระดับ A (ยังไม่เกิด)',
    'B' => 'ระดับ B (เกิดขึ้นเล็กน้อย)',
    'C' => 'ระดับ C (เกิดขึ้นปานกลาง)',
    'D' => 'ระดับ D (เกิดขึ้นสูง)',
    'F' => 'ระดับ F (รุนแรง)',
    'E' => 'ระดับ E (รุนแรงสูงสุด)'
];
$levelColors = [
    'A' => ['bg' => 'bg-blue-50 border-blue-200', 'text' => 'text-blue-700', 'icon' => 'bg-blue-200 text-blue-700'],
    'B' => ['bg' => 'bg-green-50 border-green-200', 'text' => 'text-green-700', 'icon' => 'bg-green-200 text-green-700'],
    'C' => ['bg' => 'bg-lime-50 border-lime-200', 'text' => 'text-lime-700', 'icon' => 'bg-lime-200 text-lime-700'],
    'D' => ['bg' => 'bg-yellow-50 border-yellow-200', 'text' => 'text-yellow-700', 'icon' => 'bg-yellow-200 text-yellow-700'],
    'F' => ['bg' => 'bg-orange-50 border-orange-200', 'text' => 'text-orange-700', 'icon' => 'bg-orange-200 text-orange-700'],
    'E' => ['bg' => 'bg-red-50 border-red-200', 'text' => 'text-red-700', 'icon' => 'bg-red-200 text-red-700']
];
$levelIcons = ['A' => 'fa-check-circle', 'B' => 'fa-info-circle', 'C' => 'fa-exclamation-circle', 'D' => 'fa-exclamation-triangle', 'F' => 'fa-fire', 'E' => 'fa-radiation'];

// query ระดับความเสี่ยงเพียงครั้งเดียว
$severities = $pdo->query("SELECT severity, COUNT(*) as count FROM risks" . $date_where . " GROUP BY severity")->fetchAll();
$severityMap = [];
foreach ($severities as $s) {
    $severityMap[$s['severity']] = $s['count'];
}
$levelCounts = [];
foreach ($levelOrder as $lvl) {
    $levelCounts[$lvl] = $severityMap[$lvl] ?? 0;
}

// ข้อมูลอื่น ๆ
$riskTypes = $pdo->query("SELECT risk_type, COUNT(*) as count FROM risks" . $date_where . " GROUP BY risk_type")->fetchAll();
$recent = $pdo->query("SELECT r.*, u.username FROM risks r LEFT JOIN users u ON r.user_id = u.id" . $date_where . " ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

// เตรียมข้อมูลกราฟวงกลม
$severityLabels = [];
$severityCounts = [];
$pieColors = [];
$pieHex = ['A' => '#3b82f6', 'B' => '#22c55e', 'C' => '#84cc16', 'D' => '#eab308', 'F' => '#f97316', 'E' => '#ef4444'];
foreach ($levelOrder as $sev) {
    if (isset($severityMap[$sev])) {
        $severityLabels[] = $sev;
        $severityCounts[] = $severityMap[$sev];
        $pieColors[] = $pieHex[$sev];
    }
}

$riskLabels = array_column($riskTypes, 'risk_type');
$riskCounts = array_column($riskTypes, 'count');

$groupSummary = $pdo->query("
    SELECT r.unit, COUNT(*) as total,
    COALESCE((SELECT r2.risk_type FROM risks r2 WHERE r2.unit = r.unit " . ($date_where ? ' AND ' . substr($date_where, 6) : '') . " GROUP BY r2.risk_type ORDER BY COUNT(*) DESC LIMIT 1), '-') as top_type
    FROM risks r $date_where GROUP BY r.unit ORDER BY total DESC
")->fetchAll();
$groupUnits = array_column($groupSummary, 'unit');
$groupTotals = array_column($groupSummary, 'total');
$groupTopTypes = array_column($groupSummary, 'top_type');

$statusSummary = $pdo->query("
    SELECT unit,
        SUM(CASE WHEN status='ยังไม่ดำเนินการ' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='กำลังดำเนินการ' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status='ดำเนินการแล้ว' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='ยุติ' THEN 1 ELSE 0 END) as terminated_count,
        COUNT(*) as total
    FROM risks $date_where GROUP BY unit ORDER BY total DESC
")->fetchAll();
$statusUnits = array_column($statusSummary, 'unit');
$statusPending = array_column($statusSummary, 'pending');
$statusInProgress = array_column($statusSummary, 'in_progress');
$statusCompleted = array_column($statusSummary, 'completed');
$statusTerminated = array_column($statusSummary, 'terminated_count');
?>
<?php include 'includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    body { background: #f1f5f9; }
    .dashboard-header {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 40%, #1d4ed8 70%, #1e3a8a 100%);
        border-radius: 1.5rem;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 20px 35px -10px rgba(59, 130, 246, 0.5);
    }
    .stat-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(0,0,0,0.08); border-color: #cbd5e1; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .stat-label { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.5px; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem; }
    .stat-value { font-size: 2.2rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .section-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; }
    .section-divider { width: 4px; height: 28px; border-radius: 4px; }
    .chart-card { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .table-container { background: white; border-radius: 1rem; border: 1px solid #e2e8f0; padding: 1.5rem; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 2px solid #e2e8f0; }
    td { padding: 0.9rem 1rem; color: #334155; border-bottom: 1px solid #f1f5f9; }
    tr:last-child td { border-bottom: none; }
    @media print {
        .sidebar, .filter-form { display: none !important; }
        @page { size: A4 portrait; margin: 15mm; }
        body { background: white !important; }
        .stat-card, .chart-card, .table-container { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<div class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 p-6 overflow-y-auto">
        <div class="mb-8">
            <div class="dashboard-header">
                <h1 class="text-3xl font-bold mb-1">📊 ภาพรวมระบบ</h1>
                <p class="text-white/80 text-sm">ข้อมูล ณ วันที่ <?= date('d/m/Y') ?> | ศูนย์อนามัยที่ 8 อุดรธานี</p>
            </div>
        </div>

        <form method="GET" class="filter-form bg-white rounded-xl border border-gray-200 p-4 mb-8 flex flex-wrap items-end gap-4 shadow-sm">
            <div>
                <label class="block text-sm text-gray-500 mb-1">ตั้งแต่วันที่</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-sm text-gray-500 mb-1">ถึงวันที่</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm"><i class="fas fa-filter mr-1"></i> กรอง</button>
                <a href="dashboard.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm border border-gray-200"><i class="fas fa-times mr-1"></i> รีเซ็ต</a>
            </div>
        </form>

        <!-- การ์ดรวม + ผู้ใช้ -->
        <div class="grid grid-cols-2 gap-5 mb-6">
            <div class="stat-card">
                <div class="flex items-start justify-between"><div><div class="stat-label">ความเสี่ยงทั้งหมด</div><div class="stat-value"><?= number_format($totalRisks) ?></div></div><div class="stat-icon bg-blue-50 text-blue-600"><i class="fas fa-exclamation-triangle"></i></div></div>
            </div>
            <div class="stat-card">
                <div class="flex items-start justify-between"><div><div class="stat-label">ผู้ใช้งาน</div><div class="stat-value"><?= number_format($totalUsers) ?></div></div><div class="stat-icon bg-purple-50 text-purple-600"><i class="fas fa-users"></i></div></div>
            </div>
        </div>

        <!-- การ์ดระดับความเสี่ยง A - F เรียงลำดับ -->
    
        <!-- สรุปตามกลุ่มงาน -->
        <div class="mb-10">
            <div class="section-title"><span class="section-divider bg-indigo-500"></span>📋 สรุปตามกลุ่มงาน</div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="chart-card">
                    <h3 class="text-base font-semibold text-gray-700 mb-4"><i class="fas fa-chart-bar text-indigo-500 mr-2"></i>จำนวนเคสตามกลุ่มงาน</h3>
                    <div style="height: 360px;"><canvas id="groupChart"></canvas></div>
                    <p class="text-xs text-gray-400 mt-3">* เลื่อนเมาส์เพื่อดูประเภทที่พบบ่อย</p>
                </div>
                <div class="chart-card">
                    <h3 class="text-base font-semibold text-gray-700 mb-4"><i class="fas fa-tasks text-blue-500 mr-2"></i>สถานะการดำเนินการ</h3>
                    <div style="height: 360px;"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- ภาพรวมประเภท & ระดับ -->
        <div class="mb-10">
            <div class="section-title"><span class="section-divider bg-blue-500"></span>📈 ภาพรวมประเภทและระดับความรุนแรง</div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="chart-card">
                    <h3 class="text-base font-semibold text-gray-700 mb-4"><i class="fas fa-chart-pie text-blue-500 mr-2"></i>ประเภทความเสี่ยง</h3>
                    <div style="height: 360px;"><canvas id="riskTypeChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3 class="text-base font-semibold text-gray-700 mb-4"><i class="fas fa-chart-pie text-orange-500 mr-2"></i>ระดับความรุนแรง</h3>
                    <div style="height: 360px;"><canvas id="severityChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- รายการล่าสุด -->
        <div class="mb-8">
            <div class="section-title"><span class="section-divider bg-emerald-500"></span>📝 รายการล่าสุด</div>
            <div class="table-container">
                <table>
                    <thead><tr><th>กลุ่มงาน</th><th>ประเภท</th><th>ระดับ</th><th>สถานะ</th><th>วันที่</th><th>ผู้รายงาน</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['unit'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['risk_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['severity'] ?? '-') ?></td>
                                <td><span class="px-2 py-1 rounded-full text-xs font-medium <?= ($row['status'] == 'ดำเนินการแล้ว' ? 'bg-green-100 text-green-700' : ($row['status'] == 'กำลังดำเนินการ' ? 'bg-blue-100 text-blue-700' : ($row['status'] == 'ยุติ' ? 'bg-gray-100 text-gray-600' : 'bg-yellow-100 text-yellow-700'))) ?>"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                                <td class="whitespace-nowrap text-gray-500"><?= getThaiDate($row['created_at']) ?></td>
                                <td><?= htmlspecialchars($row['username'] ?? 'ไม่ระบุ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('groupChart'), {
            type: 'bar', data: { labels: <?= json_encode($groupUnits) ?>, datasets: [{ label: 'จำนวนเคส', data: <?= json_encode($groupTotals) ?>, backgroundColor: 'rgba(99,102,241,0.8)', borderColor: '#6366f1', borderWidth: 1, borderRadius: 6 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { afterLabel: ctx => 'ประเภทที่พบบ่อย: ' + <?= json_encode($groupTopTypes) ?>[ctx.dataIndex] } } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
        new Chart(document.getElementById('statusChart'), {
            type: 'bar', data: { labels: <?= json_encode($statusUnits) ?>, datasets: [
                { label: 'ยังไม่ดำเนินการ', data: <?= json_encode($statusPending) ?>, backgroundColor: '#fbbf24', borderRadius: 4 },
                { label: 'กำลังดำเนินการ', data: <?= json_encode($statusInProgress) ?>, backgroundColor: '#60a5fa', borderRadius: 4 },
                { label: 'ดำเนินการแล้ว', data: <?= json_encode($statusCompleted) ?>, backgroundColor: '#34d399', borderRadius: 4 },
                { label: 'ยุติ', data: <?= json_encode($statusTerminated) ?>, backgroundColor: '#cbd5e1', borderRadius: 4 }
            ] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25 } } }, scales: { x: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }, y: { stacked: true } } }
        });
        new Chart(document.getElementById('riskTypeChart'), {
            type: 'bar', data: { labels: <?= json_encode($riskLabels) ?>, datasets: [{ label: 'จำนวน', data: <?= json_encode($riskCounts) ?>, backgroundColor: 'rgba(59,130,246,0.7)', borderColor: '#3b82f6', borderWidth: 1, borderRadius: 6 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
        new Chart(document.getElementById('severityChart'), {
            type: 'pie', data: { labels: <?= json_encode($severityLabels) ?>, datasets: [{ data: <?= json_encode($severityCounts) ?>, backgroundColor: <?= json_encode($pieColors) ?>, borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25 } } } }
        });
    });
</script>
<?php include 'includes/footer.php'; ?>