<?php
// checkin_board.php - 今日排課到課監控
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>排課到課監控 - 新竹桌球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* 對齊學員管理與課程管理的視覺風格 */
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #8E9775; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        
        /* 側邊欄：統一極簡風格 */
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .sidebar .back-link:hover { color: #FFF; }

        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        
        .flex-container { display: flex; gap: 40px; align-items: flex-start; }
        .column { flex: 1; background: #FFF; border: 1px solid var(--border); border-radius: 4px; padding: 30px; min-height: 600px; }
        
        h3 { font-size: 16px; font-weight: 400; margin: 0 0 25px 0; display: flex; align-items: center; gap: 10px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 10px; font-size: 12px; color: #AAA; font-weight: 400; border-bottom: 1px solid #F1F1F1; }
        td { padding: 18px 10px; font-size: 14px; color: var(--ink); border-bottom: 1px solid #F9F9F9; }
        
        .time-badge { font-size: 11px; color: var(--accent); background: #F4F5F0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>TODAY COURSE MANAGEMENT</h2>
        <a href="admin_dashboard.php" class="back-link">← 儀表板</a>
    </div>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 22px; font-weight: 400; margin:0;">今日學員到課狀況</h1>
            <div style="font-size: 13px; color: #AAA;">
                <?= date('Y.m.d') ?> <span id="sync-status" style="margin-left:10px; color:var(--accent);">● 監控中</span>
            </div>
        </div>

        <div class="flex-container">
            <div class="column">
                <h3><span class="status-dot" style="background: var(--accent);"></span> 今日已到課</h3>
                <table id="table-checked">
                    <thead>
                        <tr><th>學生 / 課程</th><th>簽到時間</th><th>進度</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="column">
                <h3 style="color: #AAA;"><span class="status-dot" style="background: #EEE;"></span> 今日預計到課</h3>
                <table id="table-uncheck">
                    <thead>
                        <tr><th>學生 / 課程</th><th>目前累計</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function fetchStatus() {
            try {
                const response = await fetch('api/get_today_status.php');
                const res = await response.json();
                if (res.success) {
                    renderTable('table-checked', res.checked, true);
                    renderTable('table-uncheck', res.uncheck, false);
                }
            } catch (e) { document.getElementById('sync-status').innerText = "● 連線中斷"; }
        }

        function renderTable(id, data, isChecked) {
            const tbody = document.querySelector(`#${id} tbody`);
            tbody.innerHTML = data.length ? "" : '<tr><td colspan="3" style="text-align:center; color:#CCC; padding:40px;">目前無排課資料</td></tr>';
            
            data.forEach(item => {
                const row = document.createElement('tr');
                if (isChecked) {
                    row.innerHTML = `
                        <td><strong>${item.name}</strong><br><small style="color:#AAA">${item.course_name}</small></td>
                        <td><span class="time-badge">${item.time}</span></td>
                        <td style="font-size:11px; color:#AAA;">第 ${item.sequence_no} / ${item.session_limit} 堂</td>`;
                } else {
                    row.innerHTML = `
                        <td><strong>${item.name}</strong><br><small style="color:#AAA">${item.course_name}</small></td>
                        <td style="font-size:11px; color:#AAA;">已上: ${item.total_checkins} / ${item.session_limit}</td>`;
                }
                tbody.appendChild(row);
            });
        }

        setInterval(fetchStatus, 3000); 
        fetchStatus();
    </script>
</body>
</html>