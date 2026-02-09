<?php
// student_course_detail.php - 修正 FETCH_KEY_PAIR 錯誤版本
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }
require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$enroll_id = $_GET['id'] ?? null;

// 1. 處理刪除邏輯
if (isset($_GET['delete_log_id'])) {
    $log_id = $_GET['delete_log_id'];
    try {
        $db->beginTransaction();
        $db->prepare("DELETE FROM checkins WHERE id = ?")->execute([$log_id]);
        $db->prepare("UPDATE enrollments SET total_checkins = total_checkins - 1, status = 'active', is_completed = 0 WHERE id = ?")
           ->execute([$enroll_id]);
        
        // 更新版本標籤 (讓 Dashboard 同步更新)
        if (file_exists('api/last_update.txt')) {
            file_put_contents('api/last_update.txt', time());
        }

        $db->commit();
        header("Location: student_course_detail.php?id=$enroll_id");
        exit;
    } catch (Exception $e) { $db->rollBack(); die("錯誤: " . $e->getMessage()); }
}

// 2. 抓取基本資料
$stmt = $db->prepare("SELECT e.*, c.course_name, c.session_limit, s.name as student_name FROM enrollments e JOIN courses c ON e.course_id = c.id JOIN students s ON e.student_id = s.id WHERE e.id = ?");
$stmt->execute([$enroll_id]);
$en = $stmt->fetch();

// 3. 抓取所有簽到紀錄 (包含 id, sequence_no, date)
$stmt_logs = $db->prepare("SELECT id, sequence_no, DATE(checkin_time) as date FROM checkins WHERE enrollment_id = ? ORDER BY sequence_no ASC");
$stmt_logs->execute([$enroll_id]);
$all_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

// 4. 手動建立 [第幾堂 => 日期] 的對照表，供上方方格顯示
$checkin_dates = [];
foreach ($all_logs as $log) {
    $checkin_dates[$log['sequence_no']] = $log['date'];
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>課程進度細節</title>
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #8E9775;}
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; padding: 60px 20px; color: var(--ink); }
        .card { max-width: 800px; margin: 0 auto; background: #FFF; padding: 50px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        
        .header-meta { font-size: 13px; color: #AAA; margin-bottom: 10px; display: block; text-decoration: none; }
        .title { font-size: 24px; font-weight: 500; margin: 0 0 40px 0; border-bottom: 2px solid var(--bg); padding-bottom: 20px; }
        
        .progress-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 50px; }
        .box { 
            aspect-ratio: 1 / 0.7; 
            background: #F5F5F3; 
            border-radius: 4px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            transition: 0.3s;
            border: 1px solid transparent;
        }
        .box.filled { background: var(--accent); color: #FFF; border-color: var(--accent); }
        .box .num { font-size: 11px; opacity: 0.5; margin-bottom: 4px; }
        .box.filled .num { opacity: 0.8; }
        .box .date { font-size: 10px; font-weight: 400; letter-spacing: 0.5px; }

        .log-section { margin-top: 60px; border-top: 1px solid var(--border); padding-top: 30px; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .log-table td { padding: 15px 10px; border-bottom: 1px solid var(--bg); }
        .btn-del { color: #D9534F; font-size: 12px; text-decoration: none; padding: 5px 10px; border: 1px solid #F2DEDE; border-radius: 4px; transition: 0.3s; }
        .btn-del:hover { background: #D9534F; color: #FFF; }
    </style>
</head>
<body>
    <div class="card">
        <a href="student_edit.php?id=<?= $en['student_id'] ?>" class="header-meta">← 返回學員管理</a>
        <h1 class="title"><?= htmlspecialchars($en['student_name']) ?> ／ <?= htmlspecialchars($en['course_name']) ?></h1>
        
        <div class="progress-grid">
            <?php for($i=1; $i<=$en['session_limit']; $i++): ?>
                <?php $has_date = isset($checkin_dates[$i]); ?>
                <div class="box <?= $has_date ? 'filled' : '' ?>">
                    <span class="num"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></span>
                    <?php if($has_date): ?>
                        <span class="date"><?= str_replace('-', '/', substr($checkin_dates[$i], 2)) ?></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <div class="log-section">
            <h3 style="font-weight: 400; font-size: 16px; margin-bottom: 20px;">簽到歷史紀錄</h3>
            <table class="log-table">
                <?php 
                // 將陣列反轉顯示最新的在上面
                foreach (array_reverse($all_logs) as $r): ?>
                <tr>
                    <td>第 <?= $r['sequence_no'] ?> 堂簽到</td>
                    <td style="color: #888;"><?= $r['date'] ?></td>
                    <td style="text-align: right;">
                        <a href="?id=<?= $enroll_id ?>&delete_log_id=<?= $r['id'] ?>" class="btn-del" onclick="return confirm('刪除後堂數會退回，確定嗎？')">刪除紀錄</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>