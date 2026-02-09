<?php
// schedule_overview.php - 深色格線版
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }
require_once 'config.php';
require_once 'classes/Database.php';

$db = (new Database())->getConnection();

// 1. 抓取固定排課資料
$query = "
    SELECT s.name as student_name, c.course_name, c.coach_name, c.start_time, c.days_of_week
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'active' AND c.course_type = 'scheduled' AND c.status = 'active'
    ORDER BY c.start_time ASC
";
$schedules = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

$week_map = ['Mon' => '星期一', 'Tue' => '星期二', 'Wed' => '星期三', 'Thu' => '星期四', 'Fri' => '星期五', 'Sat' => '星期六', 'Sun' => '星期日'];
$days = array_keys($week_map);

// 2. 產生時間段 08:00 ~ 22:00
$time_slots = [];
for ($h = 8; $h < 22; $h++) {
    foreach (['00', '30'] as $m) {
        $start = sprintf("%02d:%s", $h, $m);
        $end_h = ($m === '30') ? $h + 1 : $h;
        $end_m = ($m === '30') ? '00' : '30';
        $end = sprintf("%02d:%s", $end_h, $end_m);
        $time_slots[] = ['start' => $start, 'display' => "$start ~ $end"];
    }
}

// 3. 6桌位並行邏輯
$matrix = [];
$slot_usage = []; 
foreach ($schedules as $sch) {
    $course_days = explode(',', $sch['days_of_week']);
    $start_t = substr($sch['start_time'], 0, 5);
    $is_group = ($sch['coach_name'] === '團體班');
    $span = $is_group ? 3 : 2;

    foreach ($course_days as $d) {
        $target_slot = 1;
        for ($s = 1; $s <= 6; $s++) {
            $conflict = false;
            $current_idx = -1;
            foreach($time_slots as $idx => $ts) if($ts['start'] == $start_t) { $current_idx = $idx; break; }
            if ($current_idx !== -1) {
                for ($i = 0; $i < $span; $i++) {
                    $check_time = $time_slots[$current_idx + $i]['start'] ?? null;
                    if ($check_time && isset($slot_usage[$d][$check_time][$s])) { $conflict = true; break; }
                }
            }
            if (!$conflict) { $target_slot = $s; break; }
        }
        $matrix[$d][$start_t][$target_slot] = $sch;
        $current_idx = -1;
        foreach($time_slots as $idx => $ts) if($ts['start'] == $start_t) { $current_idx = $idx; break; }
        for ($i = 0; $i < $span; $i++) {
            $occ_time = $time_slots[$current_idx + $i]['start'] ?? null;
            if ($occ_time) $slot_usage[$d][$occ_time][$target_slot] = true;
        }
    }
}

$rendered_occupied = []; 
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>球桌使用一覽表</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* 修改處 1: 將 --border 顏色加深為 #B0B0B0 (中灰色) */
        :root { --bg: #F9F9F7; --ink: #2C2C2C; --border: #B0B0B0; --accent: #000000; }
        body { background: var(--bg); font-family: 'Noto Sans TC', sans-serif; margin: 0; padding: 40px; }
        
        .header-nav { margin-bottom: 30px; }
        .back-link { display: inline-block; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .back-link:hover { color: var(--ink); }

        .main-content { max-width: 1600px; margin: 0 auto; }
        h3 { font-size: 22px; font-weight: 400; color: #2C2C2C; margin: 0 0 30px 0; border-bottom: 2px solid #F1F1F1; padding-bottom: 15px; }

        /* 表格整體邊框使用新的深色變數 */
        table { width: 100%; border-collapse: collapse; background: #FFF; table-layout: fixed; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        /* 表頭邊框使用新的深色變數 */
        th { background: #F1F1F1; color: #333; font-weight: 500; font-size: 12px; height: 50px; border: 1px solid var(--border); }
        .time-col { width: 130px; background: #FDFDFB; color: #666; font-size: 11px; text-align: center; font-weight: 500; }

        /* 修改處 2: 將單元格的上下橫線改用深色變數 */
        td { border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 1px; height: 50px; text-align: center; }
        /* 每天之間的分界線使用新的深色變數 */
        .day-boundary-left { border-left: 1px solid var(--border) !important; }

        .course-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 94%; width: 94%; margin: 0 auto; border-radius: 4px; }
        .lesson-normal { background: #F1F4EA; border: 1px solid #DCE3CD; }
        .lesson-group { background: #FFF9E1; border: 1px solid #F3E9B5; }

        .coach-circle { 
            width: 22px; height: 22px; border: 1px solid var(--ink); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; background: #FFF; margin-bottom: 1px;
        }
        .line-connector { width: 1px; height: 6px; background: var(--ink); margin: 1px 0; }
        .student-name { font-size: 12px; font-weight: 500; color: var(--ink); }
        .group-label { font-size: 18px; font-weight: 700; color: #333333; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header-nav">
            <a href="admin_dashboard.php" class="back-link">← 儀表板</a>
        </div>
        
        <h3>球桌使用一覽表</h3>
        
        <table>
            <thead>
                <tr>
                    <th class="time-col">時間段</th>
                    <?php foreach ($week_map as $label) echo "<th colspan='6'>$label</th>"; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($time_slots as $idx => $slot): ?>
                <tr>
                    <td class="time-col"><?= $slot['display'] ?></td>
                    <?php foreach ($days as $day): ?>
                        <?php for ($s = 1; $s <= 6; $s++): ?>
                            <?php 
                            if (isset($rendered_occupied[$day][$slot['start']][$s])) continue;

                            $is_day_start = ($s === 1);
                            $border_class = $is_day_start ? 'day-boundary-left' : '';

                            if (isset($matrix[$day][$slot['start']][$s])): 
                                $sch = $matrix[$day][$slot['start']][$s];
                                $is_group = ($sch['coach_name'] === '團體班');
                                $span = $is_group ? 3 : 2;
                                
                                for ($i = 0; $i < $span; $i++) {
                                    $t = $time_slots[$idx + $i]['start'] ?? null;
                                    if ($t) $rendered_occupied[$day][$t][$s] = true;
                                }
                            ?>
                                <td rowspan="<?= $span ?>" class="<?= $border_class ?>">
                                    <div class="course-wrapper <?= $is_group ? 'lesson-group' : 'lesson-normal' ?>">
                                        <?php if($is_group): ?>
                                            <div class="group-label">團</div>
                                        <?php else: ?>
                                            <div class="coach-circle"><?= mb_substr($sch['coach_name'], 0, 1, 'UTF-8') ?></div>
                                            <div class="line-connector"></div>
                                            <div class="student-name"><?= mb_substr($sch['student_name'], 0, 1, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php else: ?>
                                <td class="<?= $border_class ?>"></td>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>