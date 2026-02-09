<?php
// student_dashboard.php - 成就感視覺強化版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { header("Location: index.php"); exit; }
require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$student_id = $_SESSION['user_id'];

// 抓取課程與各課簽到日期
$stmt = $db->prepare("SELECT e.*, c.course_name, c.session_limit FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? AND e.status != 'expired'");
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>我的課程進度</title>
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --accent: #8E9775; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; padding: 40px 20px; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; }
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 60px; border-bottom: 1px solid #E8E4E1; padding-bottom: 20px; }
        
        .course-card { background: #FFF; padding: 40px; margin-bottom: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .course-title { font-size: 20px; color: var(--ink); margin-bottom: 30px; font-weight: 500; display: flex; justify-content: space-between; }
        
        /* 學生端方格：更大、更具引導性 */
        .progress-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
        .box { 
            aspect-ratio: 1; 
            background: #F9F9F9; 
            border-radius: 6px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            color: #DDD;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .box.filled { background: var(--accent); color: #FFF; transform: scale(1.02); }
        .box .num { font-size: 12px; margin-bottom: 5px; font-weight: 300; }
        .box .date { font-size: 10px; opacity: 0.9; font-weight: 400; }
        
        .footer-info { display: flex; justify-content: space-between; margin-top: 30px; font-size: 13px; color: #888; border-top: 1px solid #F5F5F5; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <div style="font-size: 14px; color: #888;">歡迎回來, <span style="color:var(--ink); font-weight:500;"><?= htmlspecialchars($_SESSION['student_name']) ?></span></div>
            <a href="logout.php" style="font-size: 12px; color: #D9534F; text-decoration: none;">安全登出</a>
        </div>

        <?php foreach ($courses as $c): 
            // 抓取此課程的簽到日期
            $l_stmt = $db->prepare("SELECT sequence_no, DATE(checkin_time) as date FROM checkins WHERE enrollment_id = ?");
            $l_stmt->execute([$c['id']]);
            $dates = $l_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        ?>
        <div class="course-card">
            <div class="course-title">
                <?= htmlspecialchars($c['course_name']) ?>
                <span style="font-size: 14px; color: var(--accent);">完成度 <?= round(($c['total_checkins']/$c['session_limit'])*100) ?>%</span>
            </div>
            
            <div class="progress-grid">
                <?php for($i=1; $i<=$c['session_limit']; $i++): ?>
                    <?php $is_filled = isset($dates[$i]); ?>
                    <div class="box <?= $is_filled ? 'filled' : '' ?>">
                        <span class="num"><?= $i ?></span>
                        <?php if($is_filled): ?>
                            <span class="date"><?= date('m/d', strtotime($dates[$i])) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="footer-info">
                <span>目前進度：<?= $c['total_checkins'] ?> ／ <?= $c['session_limit'] ?> 堂</span>
                <span>有效期至：<?= $c['expiry_date'] ?: '第一堂課簽到後起算' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>