<?php
// student_management.php - 獨立學校欄位與黑色視覺強化版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();
$message = "";

// 1. 處理註冊邏輯 (維持不變)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $sid    = $_POST['student_id'];
    $name   = $_POST['name'];
    $uid    = $_POST['card_uid'] ?: null;
    $bday   = $_POST['birthday'] ?: null;
    $school = $_POST['school'];
    $gender = $_POST['gender'];
    $phone  = $_POST['phone'];
    $p_email = $_POST['parent_email'] ?: null;
    $password = $bday ? date('Ymd', strtotime($bday)) : $sid;

    try {
        if ($uid) {
            $check = $db->prepare("SELECT name FROM students WHERE card_uid = ?");
            $check->execute([$uid]);
            if ($check->fetch()) throw new Exception("此卡片已被其他學生使用");
        }
        $sql = "INSERT INTO students (student_id, name, birthday, school, gender, phone, parent_email, card_uid, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([$sid, $name, $bday, $school, $gender, $phone, $p_email, $uid, $password]);
        $message = "註冊成功";
    } catch (Exception $e) { $message = "❌ 錯誤：" . $e->getMessage(); }
}

// 2. 獲取學員名單
$sql_list = "
    SELECT s.*, 
    (SELECT CONCAT(e.total_checkins, ' / ', c.session_limit) 
     FROM enrollments e 
     JOIN courses c ON e.course_id = c.id 
     WHERE e.student_id = s.id AND e.status = 'active' 
     LIMIT 1) as progress
    FROM students s 
    WHERE s.status = 'active' 
    ORDER BY s.id DESC
";
$students = $db->query($sql_list)->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>學員管理 - 卓球教室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* 色調統一改為黑色 */
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #000000; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .sidebar .back-link:hover { color: #FFF; }

        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .flex-container { display: flex; gap: 50px; align-items: flex-start; }
        
        .card { background: #FFF; padding: 40px; border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        h3 { font-size: 18px; font-weight: 400; color: #2C2C2C; margin: 0 0 30px 0; border-bottom: 2px solid #F1F1F1; padding-bottom: 15px; }
        
        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 12px; color: #AAA; margin-bottom: 8px; letter-spacing: 1px; }
        
        input, select { width: 100%; border: none; border-bottom: 1px solid #EEE; padding: 12px 0; outline: none; font-size: 15px; background: transparent; transition: 0.3s; }
        input:focus, select:focus { border-bottom-color: #000; }
        
        .list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .search-container { position: relative; }
        .search-input { border: 1px solid var(--border); padding: 8px 12px 8px 35px; border-radius: 4px; font-size: 13px; outline: none; width: 200px; transition: 0.3s; background: #FDFDFB; }
        .search-input:focus { border-color: #000; background: #FFF; width: 250px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #AAA; font-size: 12px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 10px; font-size: 13px; color: #AAA; font-weight: 400; border-bottom: 1px solid #F1F1F1; }
        
        .student-row { cursor: pointer; transition: all 0.2s ease; border-radius: 4px; }
        .student-row:hover { 
            background: #F0F0F0; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            transform: translateY(-2px); 
        }
        
        td { padding: 20px 10px; font-size: 15px; color: #2C2C2C; border-bottom: 1px solid #F9F9F9; }
        .progress-text { font-size: 13px; font-weight: 500; color: var(--accent); }
        .no-progress { font-size: 13px; color: #CCC; }
        .btn-submit { background: var(--ink); color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; letter-spacing: 2px; font-size: 13px; margin-top: 15px; }
        
        /* 獨立欄位學校字體調整 */
        .school-text { font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>STUDENT MANAGEMENT</h2>
        <a href="admin_dashboard.php" class="back-link">← 儀表板</a>
    </div>

    <div class="main">
        <?php if($message): ?>
            <div style="margin-bottom: 25px; padding: 12px; background: #F5F5F5; border-left: 4px solid #000; font-size: 14px; color: var(--ink);">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="flex-container">
            <div class="card" style="flex: 1.1;">
                <h3>快速註冊</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group"><label>卡片感應</label><input type="text" name="card_uid" id="card_uid" autocomplete="off" autofocus></div>
                    <div class="form-group"><label>姓名</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>學號</label><input type="text" name="student_id" required></div>
                    <div class="form-group">
                        <label>性別</label>
                        <select name="gender"><option value="M">男</option><option value="F">女</option></select>
                    </div>
                    <div class="form-group"><label>就讀學校</label><input type="text" name="school"></div>
                    <div class="form-group"><label>聯絡電話</label><input type="text" name="phone"></div>
                    <div class="form-group"><label>家長 Email</label><input type="email" name="parent_email"></div>
                    <div class="form-group"><label>出生年月日</label><input type="date" name="birthday"></div>
                    <button type="submit" class="btn-submit">完成註冊學員</button>
                </form>
            </div>

            <div style="flex: 2.1;">
                <div class="list-header">
                    <h3 style="margin: 0;">學員名單</h3>
                    <div class="search-container">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="studentSearch" class="search-input" placeholder="搜尋姓名或學號...">
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%;">姓名</th>
                            <th style="width: 20%;">就讀學校</th>
                            <th style="width: 20%;">課程進度</th> 
                            <th style="width: 40%;">電話 / Email</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <?php foreach ($students as $s): ?>
                        <tr class="student-row" onclick="window.location.href='student_edit.php?id=<?= $s['id'] ?>'">
                            <td class="student-name">
                                <div style="font-weight: 500;"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="student-id" style="font-size: 11px; color: #AAA;"><?= htmlspecialchars($s['student_id']) ?></div>
                            </td>
                            <td class="school-text">
                                <?= htmlspecialchars($s['school'] ?: '-') ?>
                            </td>
                            <td>
                                <?php if ($s['progress']): ?>
                                    <span class="progress-text"><?= htmlspecialchars($s['progress']) ?> 堂</span>
                                <?php else: ?>
                                    <span class="no-progress">尚未綁定</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 14px;"><?= htmlspecialchars($s['phone'] ?: '-') ?></div>
                                <div style="font-size: 11px; color: #AAA;"><?= htmlspecialchars($s['parent_email'] ?: '無 Email') ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const cardInput = document.getElementById('card_uid');
        cardInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementsByName('name')[0].focus();
            }
        });

        const searchInput = document.getElementById('studentSearch');
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');

            rows.forEach(row => {
                const name = row.querySelector('.student-name').textContent.toLowerCase();
                const sid = row.querySelector('.student-id').textContent.toLowerCase();
                const school = row.querySelector('.school-text').textContent.toLowerCase();
                if (name.includes(filter) || sid.includes(filter) || school.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>