<?php
// student_edit.php - 表單拆分、視覺優化與郵件內容強化版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

require_once 'config.php';
require_once 'classes/Database.php';

// 引入 PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/PHPMailer/Exception.php';
require 'vendor/PHPMailer/PHPMailer.php';
require 'vendor/PHPMailer/SMTP.php';

$db = (new Database())->getConnection();
$id = $_GET['id'] ?? null;
$message = "";

// 1. 抓取學員資料
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { die("找不到該學員"); }

// 2. 獲取課程清單與報名狀況
$stmt_enrolled = $db->prepare("
    SELECT e.*, c.course_name, c.session_limit 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE e.student_id = ? 
    ORDER BY (CASE WHEN e.status='active' THEN 1 ELSE 2 END), e.id DESC
");
$stmt_enrolled->execute([$id]);
$enrollments = $stmt_enrolled->fetchAll();

$all_courses = $db->query("SELECT * FROM courses WHERE status = 'active' ORDER BY id DESC")->fetchAll();

// 3. 處理表單邏輯
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $db->beginTransaction();

        if ($action === 'update_info') {
            // --- 邏輯 A：更新基本資料 ---
            $name   = $_POST['name'];
            $sid    = $_POST['student_id'];
            $uid    = $_POST['card_uid'] ?: null; 
            $bday   = $_POST['birthday'] ?: null;
            $school = $_POST['school'];
            $gender = $_POST['gender']; 
            $phone  = $_POST['phone'];
            $p_email = $_POST['parent_email'] ?: null;

            $sql = "UPDATE students SET name=?, student_id=?, card_uid=?, birthday=?, school=?, gender=?, phone=?, parent_email=? WHERE id=?";
            $db->prepare($sql)->execute([$name, $sid, $uid, $bday, $school, $gender, $phone, $p_email, $id]);
            $message = "✅ 個人資料已更新";

        } elseif ($action === 'bind_course') {
            // --- 邏輯 B：綁定新課程 ---
            $bind_course_id = $_POST['bind_course_id'] ?? null;
            $is_paid = isset($_POST['is_paid']);

            if ($bind_course_id) {
                $c_stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
                $c_stmt->execute([$bind_course_id]);
                $course_info = $c_stmt->fetch();
                
                // 插入新課程包
                $db->prepare("INSERT INTO enrollments (student_id, course_id, status, expiry_date, total_checkins) VALUES (?, ?, 'active', NULL, 0)")
                   ->execute([$id, $bind_course_id]);

                // 若勾選繳費，寄送通知信
                if ($is_paid && !empty($student['parent_email'])) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = SMTP_PORT; $mail->CharSet = 'UTF-8';
                        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
                        $mail->addAddress($student['parent_email']);
                        $mail->isHTML(true);
                        $mail->Subject = "【繳費成功通知】學員：{$student['name']}";
                        
                        $mail->Body = "
                            <div style='background-color: #ffffff; padding: 50px 20px; font-family: \"Noto Sans TC\", sans-serif; color: #2c2c2c; max-width: 600px; margin: 0 auto;'>
                                <h2 style='font-size: 24px; font-weight: 500; margin-bottom: 25px; color: #1a1a1a; letter-spacing: 1px;'>繳費確認通知</h2>
                                <p style='font-size: 16px; color: #555555; line-height: 1.8;'>
                                    您好，系統已確認收到 <b>{$student['name']}</b> 的課程費用。
                                </p>
                                <div style='background-color: #f8f8f8; padding: 30px; border-radius: 8px; margin: 35px 0; border: 1px solid #eeeeee;'>
                                    <table style='width: 100%; border-collapse: collapse; color: #333333; font-size: 15px;'>
                                        <tr><td style='padding: 10px 0; color: #999999; width: 100px;'>課程名稱：</td><td style='padding: 10px 0; font-weight: 500;'>{$course_info['course_name']}</td></tr>
                                        <tr><td style='padding: 10px 0; color: #999999;'>課程堂數：</td><td style='padding: 10px 0;'>共 {$course_info['session_limit']} 堂</td></tr>
                                        <tr><td style='padding: 10px 0; color: #999999;'>繳費日期：</td><td style='padding: 10px 0;'>" . date('Y-m-d') . "</td></tr>
                                        <tr><td style='padding: 10px 0; color: #999999;'>課程期限：</td><td style='padding: 10px 0;'>首堂課起算 " . VALID_MONTHS . " 個月</td></tr>
                                    </table>
                                </div>
                                <p style='color: #aaaaaa; font-size: 12px; text-align: center; margin-top: 50px;'>本郵件由系統自動發出。</p>
                            </div>";
                        $mail->send();
                    } catch (Exception $e) { /* 靜默處理 */ }
                }
                $message = "✅ 課程綁定成功";
            }
        }

        $db->commit();
        if ($action === 'bind_course') {
            header("Location: student_edit.php?id=$id&msg=success");
            exit;
        }
    } catch (Exception $e) { 
        $db->rollBack(); 
        $message = "❌ 錯誤：" . $e->getMessage(); 
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯學員 - <?= htmlspecialchars($student['name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #E8E4E1; --accent: #2c2c2c; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; display: flex; margin: 0; }
        .sidebar { width: 240px; background: #2C2C2C; height: 100vh; color: #FFF; padding: 40px 30px; position: fixed; box-sizing: border-box; }
        .sidebar h2 { font-size: 16px; font-weight: 500; letter-spacing: 3px; margin: 0 0 40px 0; }
        .sidebar .back-link { display: block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .main { margin-left: 240px; flex: 1; padding: 60px; box-sizing: border-box; }
        .flex-container { display: flex; gap: 50px; align-items: flex-start; }
        .card { background: #FFF; padding: 40px; border: 1px solid var(--border); border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px; }
        
        h3 { font-size: 18px; font-weight: 400; color: #2C2C2C; margin: 0 0 30px 0; border-bottom: 2px solid #F1F1F1; padding-bottom: 15px; }
        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 12px; color: #AAA; margin-bottom: 8px; letter-spacing: 1px; }
        input, select { width: 100%; border: none; border-bottom: 1px solid #EEE; padding: 12px 0; outline: none; font-size: 15px; background: transparent; transition: 0.3s; color: var(--ink); }
        input:focus, select:focus { border-bottom-color: #000; }
        
        .btn-submit { background: var(--ink); color: #FFF; border: none; padding: 15px; width: 100%; cursor: pointer; letter-spacing: 2px; font-size: 13px; margin-top: 15px; transition: 0.3s; }
        .btn-submit:hover { opacity: 0.8; }
        
        .course-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-radius: 4px; margin-bottom: 10px; text-decoration: none; color: inherit; transition: 0.3s; border: 1px solid #F9F9F9; }
        .course-item.active { background: #FDFDFB; border-left: 3px solid var(--accent); }
        .course-item.history { background: #F9F9F9; color: #AAA; filter: grayscale(1); opacity: 0.7; }
        .course-item:hover { background: #F0F0F0; transform: translateY(-1px); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>STUDENT EDIT</h2>
        <a href="student_management.php" class="back-link">← 返回名單</a>
    </div>

    <div class="main">
        <?php if($message || isset($_GET['msg'])): ?>
            <div style="margin-bottom: 25px; font-size: 14px; color: var(--accent);">
                <?= $message ?: "✅ 操作成功" ?>
            </div>
        <?php endif; ?>

        <div class="flex-container">
            <div style="flex: 1;">
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">
                    <div class="card">
                        <h3>個人基本資料</h3>
                        <div class="form-group"><label>卡片感應</label><input type="text" name="card_uid" value="<?= htmlspecialchars($student['card_uid'] ?? '') ?>" autocomplete="off"></div>
                        <div class="form-group"><label>姓名</label><input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required></div>
                        <div class="form-group"><label>學號</label><input type="text" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>" required></div>
                        <div class="form-group">
                            <label>性別</label>
                            <select name="gender">
                                <option value="M" <?= $student['gender']=='M'?'selected':'' ?>>男</option>
                                <option value="F" <?= $student['gender']=='F'?'selected':'' ?>>女</option>
                            </select>
                        </div>
                        <div class="form-group"><label>就讀學校</label><input type="text" name="school" value="<?= htmlspecialchars($student['school']) ?>"></div>
                        <div class="form-group"><label>聯絡電話</label><input type="text" name="phone" value="<?= htmlspecialchars($student['phone']) ?>"></div>
                        <div class="form-group"><label>家長 Email</label><input type="email" name="parent_email" value="<?= htmlspecialchars($student['parent_email']) ?>"></div>
                        <div class="form-group"><label>出生年月日</label><input type="date" name="birthday" value="<?= $student['birthday'] ?>"></div>
                        <button type="submit" class="btn-submit">儲存變更資料</button>
                    </div>
                </form>
            </div>

            <div style="flex: 1.2;">
                <form method="POST">
                    <input type="hidden" name="action" value="bind_course">
                    <div class="card">
                        <h3>綁定新課程</h3>
                        <select name="bind_course_id" required>
                            <option value="">-- 請選擇欲加入的課程 --</option>
                            <?php foreach ($all_courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?> (<?= $c['session_limit'] ?>堂)</option>
                            <?php endforeach; ?>
                        </select>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--ink); margin-top:20px; cursor:pointer;">
                            <input type="checkbox" name="is_paid" style="width:auto; border:1px solid #CCC;"> 勾選表示已完成繳費 (將寄送通知信)
                        </label>
                        <button type="submit" class="btn-submit" style="background: var(--accent);">確認綁定課程包</button>
                    </div>
                </form>

                <div class="card">
                    <h3>課程包管理 (點擊查看明細)</h3>
                    <?php foreach ($enrollments as $en): ?>
                        <a href="student_course_detail.php?id=<?= $en['id'] ?>" class="course-item <?= $en['status'] === 'active' ? 'active' : 'history' ?>">
                            <div>
                                <div style="font-weight:500;"><?= htmlspecialchars($en['course_name']) ?></div>
                                <div style="font-size:12px; opacity:0.7;">進度: <?= $en['total_checkins'] ?> / <?= $en['session_limit'] ?> 堂</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:11px; opacity:0.6;"><?= $en['status']==='active'?'進行中':'已完成' ?></div>
                                <i class="fas fa-chevron-right" style="font-size:12px; margin-top:5px;"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>