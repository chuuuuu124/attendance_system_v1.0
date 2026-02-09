<?php
// index.php
session_start();
require_once 'config.php';
require_once 'classes/Database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // 1. 驗證管理員
    $stmt = $db->prepare("SELECT id, username FROM admins WHERE username = ? AND password = ?");
    $stmt->execute([$user, $pass]);
    if ($admin = $stmt->fetch()) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['role'] = 'admin';
        header("Location: admin_dashboard.php");
        exit;
    }

    // 2. 驗證學生 (帳號為學號)
    $stmt = $db->prepare("SELECT id, name, student_id FROM students WHERE student_id = ? AND password = ?");
    $stmt->execute([$user, $pass]);
    if ($student = $stmt->fetch()) {
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['student_name'] = $student['name'];
        $_SESSION['role'] = 'student';
        header("Location: student_dashboard.php");
        exit;
    }
    $error = '帳號或密碼不正確';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新竹桌球教室 - 系統登入</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --accent: #8E9775; --border: #E8E4E1; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; color: var(--ink); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-box { background: #FFF; padding: 50px 40px; border-radius: 2px; box-shadow: 0 20px 50px rgba(0,0,0,0.02); width: 100%; max-width: 380px; border: 1px solid var(--border); }
        h1 { font-size: 20px; font-weight: 400; text-align: center; letter-spacing: 4px; margin-bottom: 40px; color: var(--ink); }
        .form-group { margin-bottom: 25px; }
        input { width: 100%; border: none; border-bottom: 1px solid var(--border); padding: 12px 0; font-size: 15px; outline: none; transition: 0.3s; background: transparent; }
        input:focus { border-bottom-color: var(--accent); }
        .btn-submit { width: 100%; background: var(--ink); color: #FFF; border: none; padding: 15px; font-size: 14px; letter-spacing: 2px; cursor: pointer; transition: 0.4s; margin-top: 20px; }
        .btn-submit:hover { opacity: 0.8; }
        .error { color: #D9534F; font-size: 13px; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>新竹桌球教室</h1>
        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="ACCOUNT / STUDENT ID" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="PASSWORD" required>
            </div>
            <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
            <button type="submit" class="btn-submit">LOG IN</button>
        </form>
    </div>
</body>
</html>