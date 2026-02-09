<?php
// cron/check_expiry.php - 自動檢查到期與堂數提醒
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';

use PHPMailer\PHPMailer\PHPMailer;
require dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';
require dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
require dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';

$db = (new Database())->getConnection();

// 1. 找出 7 天後即將到期的活躍課程
$target_date = date('Y-m-d', strtotime('+7 days'));
$stmt = $db->prepare("
    SELECT e.*, s.name, s.parent_email, c.course_name 
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'active' AND e.expiry_date = ?
");
$stmt->execute([$target_date]);
$expiring_soon = $stmt->fetchAll();

foreach ($expiring_soon as $item) {
    sendReminderEmail($item, "課程即將到期提醒", "您的課程預計於 7 天後 ({$target_date}) 到期，請留意剩餘堂數。");
}

// 2. 找出只剩 1 堂的課程
$stmt_last = $db->prepare("
    SELECT e.*, s.name, s.parent_email, c.course_name, c.session_limit
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'active' AND (c.session_limit - e.total_checkins) = 1
");
$stmt_last->execute();
$last_sessions = $stmt_last->fetchAll();

foreach ($last_sessions as $item) {
    sendReminderEmail($item, "課量剩餘提醒", "您的課程目前僅剩最後 <b>1</b> 堂，歡迎聯繫教練續約。");
}

function sendReminderEmail($data, $subject, $content) {
    if (empty($data['parent_email'])) return;
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS;
        $mail->Port = SMTP_PORT; $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($data['parent_email']);
        $mail->isHTML(true);
        $mail->Subject = "【{$subject}】學員：{$data['name']}";
        
        // 使用與繳費信一致的純白極簡風格
        $mail->Body = "
            <div style='background-color: #ffffff; padding: 50px 20px; font-family: sans-serif; color: #2c2c2c; max-width: 600px; margin: 0 auto;'>
                <h2 style='font-size: 22px; font-weight: 500; margin-bottom: 25px;'>{$subject}</h2>
                <p style='font-size: 16px; color: #555555; line-height: 1.8;'>{$content}</p>
                <div style='background-color: #f8f8f8; padding: 25px; border-radius: 8px; margin: 30px 0; border: 1px solid #eeeeee;'>
                    <table style='width: 100%; font-size: 14px; color: #333333;'>
                        <tr><td style='color:#999; padding:5px 0;'>學員姓名：</td><td>{$data['name']}</td></tr>
                        <tr><td style='color:#999; padding:5px 0;'>課程名稱：</td><td>{$data['course_name']}</td></tr>
                        <tr><td style='color:#999; padding:5px 0;'>剩餘堂數：</td><td>" . ($data['session_limit'] - $data['total_checkins']) . " 堂</td></tr>
                    </table>
                </div>
            </div>";
        $mail->send();
    } catch (Exception $e) {}
}