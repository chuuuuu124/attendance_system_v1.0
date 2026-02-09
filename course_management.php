<?php
// course_management.php - 全域點擊跳轉、極簡黑標與強化懸停版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php'; 
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$message = "";

// 1. 處理課程新增邏輯
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = $_POST['course_name'];
    $coach = $_POST['coach_name'];
    $type = $_POST['course_type'];
    $limit = $_POST['session_limit'];
    $months = $_POST['valid_months'];
    $time = ($type === 'scheduled') ? $_POST['start_time'] : null;
    $days = ($type === 'scheduled' && isset($_POST['days'])) ? implode(',', $_POST['days']) : null;

    try {
        $sql = "INSERT INTO courses (course_name, coach_name, course_type, start_time, days_of_week, session_limit, valid_months, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        $db->prepare($sql)->execute([$name, $coach, $type, $time, $days, $limit, $months]);
        $message = "課程已成功建立";
    } catch (Exception $e) { $message = "❌ 錯誤：" . $e->getMessage(); }
}

// 2. 處理課程刪除邏輯
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $check = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'active'");
        $check->execute([$delete_id]);
        $active_count = $check->fetchColumn();

        if ($active_count > 0) {
            throw new Exception("無法刪除：尚有 {$active_count} 名學員正在進行此課程");
        }

        $stmt = $db->prepare("UPDATE courses SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: course_management.php?msg=deleted");
        exit;
    } catch (Exception $e) { $message = "❌ " . $e->getMessage(); }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $message = "課程已成功刪除";

$courses = $db->query("SELECT * FROM courses WHERE status = 'active' ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>課程配置 - 卓球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --danger: #D9534F; --accent: #8E9775; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .sidebar .back-link:hover { color: #FFF; }
        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .flex-container { display: flex; gap: 50px; align-items: flex-start; }
        .card { background: #FFF; padding: 40px; border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        h3 { font-size: 18px; font-weight: 400; margin: 0 0 30px 0; color: #2C2C2C; border-bottom: 2px solid #F1F1F1; padding-bottom: 15px; }
        
        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 12px; color: #AAA; margin-bottom: 8px; letter-spacing: 1px; }
        
        /* 輸入欄位焦點：底線變黑色 */
        input, select { width: 100%; border: none; border-bottom: 1px solid #EEE; padding: 12px 0; outline: none; font-size: 15px; color: #2C2C2C; background: transparent; transition: border-color 0.3s; }
        input:focus, select:focus { border-bottom-color: #000; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 10px; font-size: 12px; color: #AAA; font-weight: 400; letter-spacing: 1px; border-bottom: 1px solid #F1F1F1; }
        
        /* 課程列樣式：強化懸停效果 */
        .course-row { cursor: pointer; transition: all 0.2s ease; position: relative; }
        .course-row:hover { 
            background: #F0F0F0; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            transform: translateY(-2px);
            z-index: 1;
        }
        
        td { padding: 20px 10px; font-size: 15px; color: #2C2C2C; border-bottom: 1px solid #F9F9F9; vertical-align: middle; }
        
        .btn-zen { background: #2C2C2C; color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; letter-spacing: 2px; font-size: 13px; margin-top: 20px; transition: 0.3s; }
        .btn-zen:hover { background: #444; }
        
        /* 刪除按鈕樣式 */
        .btn-trash { color: #CCC; text-decoration: none; font-size: 16px; transition: 0.3s; padding: 10px; }
        .btn-trash:hover { color: var(--danger); }
        
        .tag-coach { font-size: 13px; color: #666; background: #F1F1F1; padding: 4px 8px; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>COURSE MANAGEMENT</h2>
        <a href="admin_dashboard.php" class="back-link">← 儀表板</a>
    </div>

    <div class="main">
        <?php if($message): ?>
            <div style="margin-bottom: 25px; padding: 15px; background: #F4F6F0; border-left: 4px solid #8E9775; font-size: 14px; color: #556B2F;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="flex-container">
            <div class="card" style="flex: 1;">
                <h3>建立新課程</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>課程名稱</label>
                        <input type="text" name="course_name" required placeholder="例：個人班(周一)">
                    </div>
                    <div class="form-group">
                        <label>指導教練</label>
                        <input type="text" name="coach_name" required placeholder="例：周教練">
                    </div>
                    <div class="form-group">
                        <label>課程類型</label>
                        <select name="course_type" onchange="toggleSchedule(this.value)">
                            <option value="scheduled">定時排課 (固定時間)</option>
                            <option value="general">通用課程 (不限時間)</option>
                        </select>
                    </div>
                    <div id="schedule_fields">
                        <div class="form-group"><label>上課時段</label><input type="time" name="start_time" step="1800"></div>
                        <div class="form-group">
                            <label>重複星期</label>
                            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top: 10px;">
                                <?php foreach(['Mon'=>'一','Tue'=>'二','Wed'=>'三','Thu'=>'四','Fri'=>'五','Sat'=>'六','Sun'=>'日'] as $en=>$zh): ?>
                                    <label style="font-size:13px; color: #555; cursor: pointer; display: flex; align-items: center;">
                                        <input type="checkbox" name="days[]" value="<?= $en ?>" style="width: auto; margin-right: 5px;"> <?= $zh ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex:1;"><label>堂數上限</label><input type="number" name="session_limit" value="10"></div>
                        <div class="form-group" style="flex:1;"><label>有效月數</label><input type="number" name="valid_months" value="4"></div>
                    </div>
                    <button type="submit" class="btn-zen">建立課程配置</button>
                </form>
            </div>

            <div style="flex: 2;">
                <div class="card">
                    <h3>現有課程配置</h3>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 25%;">課程名稱</th>
                                <th style="width: 20%;">教練</th>
                                <th style="width: 15%;">時段</th>
                                <th style="width: 20%;">重複日</th>
                                <th style="width: 10%;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                            <tr class="course-row" onclick="window.location.href='course_edit.php?id=<?= $c['id'] ?>'">
                                <td style="font-weight: 500;"><?= htmlspecialchars($c['course_name']) ?></td>
                                <td><span class="tag-coach"><?= htmlspecialchars($c['coach_name'] ?? '未指定') ?></span></td>
                                <td><?= $c['start_time'] ? substr($c['start_time'], 0, 5) : '<span style="color:#AAA">不限</span>' ?></td>
                                <td style="color: #666; font-size: 13px;">
                                    <?= $c['days_of_week'] ? str_replace(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],['一','二','三','四','五','六','日'], $c['days_of_week']) : '<span style="color:#AAA">通用</span>' ?>
                                </td>
                                <td>
                                    <a href="?delete_id=<?= $c['id'] ?>" class="btn-trash" 
                                       onclick="event.stopPropagation(); return confirm('確定要刪除「<?= htmlspecialchars($c['course_name']) ?>」嗎？')">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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