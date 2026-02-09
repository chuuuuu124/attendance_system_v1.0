<?php
// course_edit.php - 修正資料表對接與防呆版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$message = "";
$course_id = $_GET['id'] ?? null;

if (!$course_id) { header("Location: course_management.php"); exit; }

// 1. 處理更新邏輯
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $name = $_POST['course_name'];
        $coach = $_POST['coach_name'];
        $type = $_POST['course_type'];
        $limit = $_POST['session_limit'];
        $months = $_POST['valid_months'];
        $time = ($type === 'scheduled') ? $_POST['start_time'] : null;
        $days = ($type === 'scheduled' && isset($_POST['days'])) ? implode(',', $_POST['days']) : null;

        $stmt = $db->prepare("UPDATE courses SET 
            course_name = ?, coach_name = ?, course_type = ?, 
            start_time = ?, days_of_week = ?, session_limit = ?, valid_months = ? 
            WHERE id = ?");
        $stmt->execute([$name, $coach, $type, $time, $days, $limit, $months, $course_id]);
        
        $message = "✅ 課程資訊已更新";
    } catch (Exception $e) {
        $message = "❌ 更新失敗：" . $e->getMessage();
    }
}

// 2. 抓取課程資料
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) { echo "查無此課程"; exit; }

$current_days = $course['days_of_week'] ? explode(',', $course['days_of_week']) : [];

// 3. 修正：將 users 修改為正確的 students 資料表
$stmt_students = $db->prepare("
    SELECT s.name, s.student_id, e.total_checkins, e.expiry_date, e.status
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    WHERE e.course_id = ? AND e.status = 'active'
    ORDER BY e.expiry_date ASC
");
$stmt_students->execute([$course_id]);
$students = $stmt_students->fetchAll();

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯課程 - <?= htmlspecialchars($course['course_name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #8E9775; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .sidebar .back-link:hover { color: #FFF; }
        
        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .header-area { margin-bottom: 40px; }
        .breadcrumb { font-size: 13px; color: #888; margin-bottom: 10px; }
        .breadcrumb a { text-decoration: none; color: #888; }
        .page-title { font-size: 24px; font-weight: 500; color: #2C2C2C; margin: 0; }

        .flex-container { display: flex; gap: 40px; align-items: flex-start; }
        .card { background: #FFF; padding: 40px; border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        
        h3 { font-size: 18px; font-weight: 400; margin: 0 0 30px 0; color: #2C2C2C; border-bottom: 2px solid #F1F1F1; padding-bottom: 15px; }
        
        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 12px; color: #AAA; margin-bottom: 8px; letter-spacing: 1px; }
        input, select { width: 100%; border: none; border-bottom: 1px solid #EEE; padding: 12px 0; outline: none; font-size: 15px; color: #2C2C2C; background: transparent; transition: border-color 0.3s; }
        input:focus, select:focus { border-bottom-color: #2C2C2C; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 10px; font-size: 12px; color: #AAA; font-weight: 400; letter-spacing: 1px; border-bottom: 1px solid #F1F1F1; }
        td { padding: 15px 10px; font-size: 14px; color: #2C2C2C; border-bottom: 1px solid #F9F9F9; vertical-align: middle; }
        
        .progress-bar { background: #F1F1F1; height: 6px; width: 100px; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 10px; }
        .progress-fill { height: 100%; background: var(--accent); }
        .progress-text { font-size: 12px; color: #888; }

        .btn-zen { background: #2C2C2C; color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; letter-spacing: 2px; font-size: 13px; margin-top: 20px; transition: background 0.3s; }
        .btn-zen:hover { background: #444; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>COURSE EDIT</h2>
        <a href="course_management.php" class="back-link">← 返回列表</a>
    </div>

    <div class="main">
        <div class="header-area">
            <div class="breadcrumb"><a href="course_management.php">課程管理</a> / 編輯課程</div>
            <h1 class="page-title"><?= htmlspecialchars($course['course_name']) ?></h1>
        </div>

        <?php if($message): ?>
            <div style="margin-bottom: 25px; padding: 15px; background: #F4F6F0; border-left: 4px solid #8E9775; font-size: 14px; color: #556B2F;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="flex-container">
            <div class="card" style="flex: 1;">
                <h3>課程設定</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    
                    <div class="form-group">
                        <label>課程名稱</label>
                        <input type="text" name="course_name" value="<?= htmlspecialchars($course['course_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>指導教練</label>
                        <input type="text" name="coach_name" value="<?= htmlspecialchars($course['coach_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>課程類型</label>
                        <select name="course_type" onchange="toggleSchedule(this.value)">
                            <option value="scheduled" <?= $course['course_type'] == 'scheduled' ? 'selected' : '' ?>>定時排課</option>
                            <option value="general" <?= $course['course_type'] == 'general' ? 'selected' : '' ?>>通用課程</option>
                        </select>
                    </div>

                    <div id="schedule_fields">
                        <div class="form-group">
                            <label>上課時段</label>
                            <input type="time" name="start_time" step="1800" value="<?= $course['start_time'] ?>">
                        </div>
                        <div class="form-group">
                            <label>重複星期</label>
                            <div style="display:flex; flex-wrap:wrap; gap:15px; margin-top: 10px;">
                                <?php foreach(['Mon'=>'一','Tue'=>'二','Wed'=>'三','Thu'=>'四','Fri'=>'五','Sat'=>'六','Sun'=>'日'] as $en=>$zh): ?>
                                    <label style="font-size:14px; color: #555; cursor: pointer; display: flex; align-items: center;">
                                        <input type="checkbox" name="days[]" value="<?= $en ?>" 
                                            <?= in_array($en, $current_days) ? 'checked' : '' ?>
                                            style="width: auto; margin-right: 6px;"> <?= $zh ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex:1;">
                            <label>堂數上限</label>
                            <input type="number" name="session_limit" value="<?= $course['session_limit'] ?>">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>有效月數</label>
                            <input type="number" name="valid_months" value="<?= $course['valid_months'] ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-zen">更新課程資訊</button>
                </form>
            </div>

            <div class="card" style="flex: 1.2;">
                <h3>本課程現有學員 (<?= count($students) ?>人)</h3>
                <?php if(count($students) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>姓名</th>
                                <th>進度</th>
                                <th>到期日</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <?php 
                                // 修正：防呆計算，避免除以零
                                $limit = ($course['session_limit'] > 0) ? $course['session_limit'] : 1;
                                $percent = ($s['total_checkins'] / $limit) * 100;
                                if ($percent > 100) $percent = 100;
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($s['name']) ?></div>
                                    <div style="font-size: 12px; color: #AAA;"><?= htmlspecialchars($s['student_id']) ?></div>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?= $s['total_checkins'] ?>/<?= $course['session_limit'] ?></span>
                                </td>
                                <td style="color: #666;">
                                    <?= $s['expiry_date'] ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; color: #AAA; padding: 40px 0;">
                        尚無學員報名此課程
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSchedule(type) {
            document.getElementById('schedule_fields').style.display = (type === 'general') ? 'none' : 'block';
        }
        toggleSchedule(document.querySelector('select[name="course_type"]').value);
    </script>
</body>
</html>