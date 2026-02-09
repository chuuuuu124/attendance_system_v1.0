<?php
// config.php - 系統唯一來源
date_default_timezone_set('Asia/Taipei');

// 資料庫配置
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'attendance_system');
define('DB_USER', 'chuu');
define('DB_PASS', '88888888');

// 業務邏輯配置 (修改這裡，全系統同步)
define('MAX_SESSIONS', 10);     // 一期 10 堂課
define('VALID_MONTHS', 4);      // 4 個月效期
define('COOLDOWN_MINUTES', 5);  // 簽到冷卻時間 5 分鐘

// 路徑配置
define('BASE_URL', 'http://localhost/attendance_system/');

// config.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'retep940426@gmail.com');
define('SMTP_FROM_NAME', '新竹桌球教室'); 
define('SMTP_PASS', 'benfkgqbnammyxib');