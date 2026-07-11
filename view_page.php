<?php
define('ACCESS_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/functions.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    die('ไม่พบหน้าเว็บ');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        die('ไม่พบหน้าเว็บ');
    }
    
    $page_title = htmlspecialchars($page['title']);
    $page_content = $page['content'];
    
    $settings = [];
    $stmt = $pdo->query("SELECT * FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $site_name = $settings['site_name'] ?? 'Risk Management';
    $site_logo = $settings['site_logo'] ?? 'assets/default-logo.png';
    
} catch (Exception $e) {
    die('เกิดข้อผิดพลาด');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> • <?= htmlspecialchars($site_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f1f5f9; min-height: 100vh; }
        .content-wrapper { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .content-card { background: white; border-radius: 1.5rem; padding: 3rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .content-card img { max-width: 100%; height: auto; border-radius: 0.75rem; margin: 1rem 0; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #6366f1; text-decoration: none; font-weight: 600; margin-bottom: 1.5rem; transition: all 0.3s; }
        .back-link:hover { transform: translateX(-5px); }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> กลับแดชบอร์ด</a>
        <div class="content-card">
            <h1 class="text-3xl font-bold text-gray-800 mb-6"><?= $page_title ?></h1>
            <div class="prose prose-lg max-w-none"><?= $page_content ?></div>
        </div>
    </div>
</body>
</html>