<?php
// 值班排班系统

// 岗位定义
define('CRRG_DUTY_ROLES', [
    'chief'    => ['name' => '执勤长',   'icon' => '🛡', 'desc' => '统筹当日一切事务'],
    'archivist'=> ['name' => '档案管理员','icon' => '', 'desc' => '档案审核与归档'],
    'comm'     => ['name' => '通讯联络员','icon' => '', 'desc' => '对外联络与信息传递'],
    'tech'     => ['name' => '技术保障员','icon' => '', 'desc' => '系统维护与技术支持'],
    'field'    => ['name' => '外勤值班',  'icon' => '', 'desc' => '现场处置与应急响应'],
]);

// 值班人员池（2026-09-29 改）：原先是"全部 WP 用户"，会把测试号/占位号排进公开值班表
//   ① 若设了「在编名单」选项 crrg_duty_pool_ids，只用名单里的人；
//   ② 否则用全体用户，但排除明显的测试/占位账号。
function crrg_get_duty_pool() {
    $ids = get_option('crrg_duty_pool_ids', []);
    if (is_array($ids) && $ids) {
        $out = [];
        foreach ($ids as $id) if ($u = get_userdata((int) $id)) $out[] = $u;
        if ($out) return $out;
    }
    $exclude = ['nightcat', 'nightcat1', 'alice', 'homelander', 'Togawa Sakiko'];
    $out = [];
    foreach (get_users(['orderby' => 'ID', 'order' => 'ASC']) as $u) {
        if (in_array($u->user_login, $exclude, true)) continue;
        if (preg_match('/^(playtest_|pt_)/', $u->user_login)) continue;      // 我的测试号
        $out[] = $u;
    }
    return $out;
}
/** 在编名单是否已设置 */
function crrg_duty_pool_source() {
    $ids = get_option('crrg_duty_pool_ids', []);
    return (is_array($ids) && $ids) ? 'manual' : 'auto-with-filter';
}

// 生成排班：从全员随机选5人
function crrg_generate_schedule() {
    $pool = crrg_get_duty_pool();
    if (count($pool) < 5) return false;
    
    $roles = array_keys(CRRG_DUTY_ROLES);
    $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    
    // 每周从全员随机抽7人（每人负责一周的排班轮转）
    $week = (int)date('W');
    $year = (int)date('Y');
    mt_srand($week * 53 + $year);
    $keys = array_rand($pool, min(7, count($pool)));
    if (!is_array($keys)) $keys = [$keys];
    shuffle($keys);
    $selected = [];
    foreach ($keys as $k) $selected[] = $pool[$k]->display_name;
    while (count($selected) < 5) $selected[] = $pool[array_rand($pool)]->display_name;
    mt_srand();
    
    $schedule = [];
    foreach ($days as $di => $day) {
        foreach ($roles as $ri => $role) {
            $idx = ($di * 5 + $ri) % count($selected);
            $schedule[$day][$role] = $selected[$idx];
        }
    }
    
    $data = [
        'week_start' => date('Y-m-d', strtotime('monday this week')),
        'generated' => current_time('mysql'),
        'schedule' => $schedule,
    ];
    update_option('crrg_duty_schedule', $data);
    /* 按周留档（2026-09-29）：排班表原先只存当周、生成即覆盖，导致"某人是否在岗"无法事后核验 */
    $hist = get_option('crrg_duty_history', []);
    if (!is_array($hist)) $hist = [];
    $hist[$data['week_start']] = ['generated' => $data['generated'], 'schedule' => $schedule];
    if (count($hist) > 26) { ksort($hist); $hist = array_slice($hist, -26, null, true); }   // 留 26 周
    update_option('crrg_duty_history', $hist, false);
    return $data;
}

// 获取当前排班
function crrg_get_schedule() {
    $data = get_option('crrg_duty_schedule', []);
    if (empty($data) || ($data['week_start'] ?? '') < date('Y-m-d', strtotime('monday this week'))) {
        $data = crrg_generate_schedule();
    }
    return $data;
}

// 获取今天值班
function crrg_get_today_duty() {
    $data = crrg_get_schedule();
    $today = date('D');
    return $data['schedule'][$today] ?? [];
}

// 手动触发排班（每周一0点调用）
add_action('crrg_weekly_duty', 'crrg_generate_schedule');

// 注册cron（如果还没注册）
if (!wp_next_scheduled('crrg_weekly_duty')) {
    wp_schedule_event(strtotime('next Monday 00:00'), 'weekly', 'crrg_weekly_duty');
}
