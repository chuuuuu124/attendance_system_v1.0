<?php
// admin_dashboard.php (輕量化同步版)
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }
require_once 'config.php';
require_once 'classes/Database.php';
$db = (new Database())->getConnection();

// 初始數據抓取
$stats = [
    'students' => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'today' => $db->query("SELECT COUNT(*) FROM checkins WHERE DATE(checkin_time) = CURDATE()")->fetchColumn(),
    'active' => $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn()
];
$recent = $db->query("
    SELECT s.name, c.checkin_time, cr.course_name, c.sequence_no 
    FROM checkins c
    JOIN enrollments e ON c.enrollment_id = e.id
    JOIN students s ON e.student_id = s.id
    JOIN courses cr ON e.course_id = cr.id
    ORDER BY c.checkin_time DESC LIMIT 15
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理後台 - 新竹桌球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #8E9775; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        
        /* 側邊欄：統一 240px */
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 20px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 400; letter-spacing: 3px; margin-bottom: 50px; text-align: left; padding-left: 15px; }
        
        .menu-item { display: block; padding: 15px; color: #BBB; text-decoration: none; font-size: 14px; margin-bottom: 10px; border-radius: 4px; transition: 0.3s; }
        .menu-item:hover { color: #FFF; background: rgba(255,255,255,0.05); }
        
        .main { margin-left: 240px; flex: 1; padding: 50px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 50px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-bottom: 50px; }
        .stat-card { background: #FFF; padding: 30px; border: 1px solid var(--border); border-radius: 4px; }
        .stat-val { font-size: 24px; color: var(--ink); margin-bottom: 5px; }
        .stat-lab { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        
        table { width: 100%; border-collapse: collapse; background: #FFF; border: 1px solid var(--border); }
        th { text-align: left; padding: 15px; font-size: 13px; color: #888; font-weight: 400; border-bottom: 1px solid #F1F1F1; }
        td { padding: 15px; font-size: 14px; color: var(--ink); border-bottom: 1px solid #F1F1F1; }
        #sync-status { font-size: 11px; color: var(--accent); margin-left: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>ADMIN CENTER</h2>
        <a href="checkin_board.php" class="menu-item"><i class="fas fa-desktop me-2"></i> 今日簽到看板</a>
        <a href="student_management.php" class="menu-item"><i class="fas fa-users me-2"></i> 學生資料管理</a>
        <a href="course_management.php" class="menu-item"><i class="fas fa-book me-2"></i> 課程進度設定</a>
        <a href="schedule_overview.php" class="menu-item"><i class="fas fa-calendar-alt me-2"></i>  球桌使用一覽表</a>
        <a href="logout.php" class="menu-item" style="margin-top: 50px;"><i class="fas fa-sign-out-alt me-2"></i> 登出</a>
    </div>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 22px; font-weight: 400;">今日概況 <span id="sync-status">● 同步中</span></h1>
            <div style="font-size: 13px; color: #888;"><?= date('Y.m.d') ?></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-val" id="s-today"><?= $stats['today'] ?></div><div class="stat-lab">今日簽到人次</div></div>
            <div class="stat-card"><div class="stat-val" id="s-students"><?= $stats['students'] ?></div><div class="stat-lab">註冊學生數</div></div>
            <div class="stat-card"><div class="stat-val" id="s-active"><?= $stats['active'] ?></div><div class="stat-lab">進行中課程包</div></div>
        </div>

        <h3 style="font-size: 16px; font-weight: 400; margin-bottom: 20px;">最近簽到動態</h3>
        <table>
            <thead><tr><th>時間</th><th>學生姓名</th><th>課程名稱</th><th>堂數進度</th></tr></thead>
            <tbody id="recent-list">
                <?php foreach ($recent as $r): ?>
                <tr><td><?= date('H:i', strtotime($r['checkin_time'])) ?></td><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['course_name']) ?></td><td>第 <?= $r['sequence_no'] ?> 次</td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        let localVersion = "0";

        async function checkVersion() {
            try {
                const res = await fetch('api/get_version.php');
                const data = await res.json();
                
                // 比對版本，若不同則更新內容
                if (data.version !== localVersion) {
                    localVersion = data.version;
                    refreshData();
                }
            } catch (e) { document.getElementById('sync-status').innerText = "● 連線異常"; }
        }

        async function refreshData() {
            const res = await fetch('api/get_dashboard_updates.php');
            const d = await res.json();
            if (d.success) {
                document.getElementById('s-students').innerText = d.stats.students;
                document.getElementById('s-today').innerText = d.stats.today;
                document.getElementById('s-active').innerText = d.stats.active;

                document.getElementById('recent-list').innerHTML = d.recent.map(r => `
                    <tr>
                        <td>${r.checkin_time}</td>
                        <td>${r.name}</td>
                        <td>${r.course_name}</td>
                        <td>第 ${r.sequence_no} 次</td>
                    </tr>
                `).join('');
            }
        }

        setInterval(checkVersion, 1000); // 每秒輕量檢查一次
    </script>
</body>
</html>