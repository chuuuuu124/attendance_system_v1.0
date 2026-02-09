<?php
// logout.php - 清除 Session 並重新定向
session_start();

// 1. 清空所有 Session 變數
$_SESSION = array();

// 2. 如果要徹底銷毀 Session，也建議刪除客戶端對應的 Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 銷毀伺服器上的 Session 檔案
session_destroy();

// 4. 重定向回到登入頁面
header("Location: index.php?msg=logged_out");
exit;
?>