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

// 值班人员池：所有用户
function crrg_get_duty_pool() {
    return get_users(['orderby'=>'ID', 'order'=>'ASC']);
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
