<?php
// test_mail.php
require_once 'config.php';

// 引入 PHPMailer 檔案
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/PHPMailer/Exception.php';
require 'vendor/PHPMailer/PHPMailer.php';
require 'vendor/PHPMailer/SMTP.php';

$mail = new PHPMailer(true);

try {
    // 伺服器設定
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // 收件人設定
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_USER); // 先寄給自己測試

    // 郵件內容
    $mail->isHTML(true);
    $mail->Subject = '卓球教室系統 - SMTP 連線測試';
    $mail->Body    = '這是一封測試郵件，看到這封信代表你的 <b>PHPMailer + Gmail SMTP</b> 設定成功！';

    echo "<h3>正在嘗試發送測試郵件...</h3>";
    $mail->send();
    echo "<h3 style='color: green;'>✅ 測試成功！請檢查你的 Gmail 收件匣。</h3>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 測試失敗！</h3>";
    echo "錯誤訊息: {$mail->ErrorInfo}";
}