<?php
/**
 * 低级别真实游玩（playtest）：全新卡 + 只用玩家指令 + 不预置任何状态
 * 用法：php tools/playtest-low.php
 * 输出：逐步日志 +「问题清单」（跑不通/卡住/没入口/信息缺失处）
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$log = [];
$problems = [];
function say($s) { global $log; $log[] = $s; echo $s . "\n"; }
function note(&$arr, $where, $what) { $arr[] = sprintf('[%s] %s', $where, $what); }

/* ── 0. 全新账号（临时用户），什么都没预置 ── */
require_once ABSPATH . 'wp-admin/includes/user.php';
$uname = 'playtest_' . wp_rand(10000, 99999);
$uid = wp_insert_user(['user_login' => $uname, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
if (is_wp_error($uid)) { echo "建号失败\n"; exit; }
say(sprintf("=== 低级别真实游玩（新号 #%d / %s）===", $uid, $uname));
$ctx = crrg_drill_ctx($uid);
$pc = crrg_drill_pc($uid);
say(sprintf('初始：卡级 %s ｜ 身份 %s ｜ ready=%s ｜ 积分 %d ｜ 钱 %d ｜ 包里 %d 件',
    $ctx['card'], $ctx['rank_name'], !empty($pc['ready']) ? '是' : '否',
    (int) ($pc['credits'] ?? 0), (int) ($pc['money'] ?? 0), count((array) ($pc['items'] ?? []))));

/* ── 工具：以「玩家」身份执行一条指令 ── */
$S = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
function play(&$S, $ctx, $uid, $cmd, $silent = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, $ctx, $cmd, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$silent) { say(sprintf('  > %s', $cmd)); foreach ((array) $l as $x) say('      ' . mb_substr($x, 0, 96)); }
    return $l;
}

/* ── 1. 车卡 ── */
say('── 1. 车卡 ──');
foreach (['掷骰', '选职业 行动员', '自动分配', '完成'] as $c) play($S, $ctx, $uid, $c);
$pc = crrg_drill_pc($uid);
$pc['scene'] = 'base'; crrg_drill_pc_save($uid, $pc);
$ctx = crrg_drill_ctx($uid);
say(sprintf('车卡完成：ready=%s ｜ 技能 %d 项 ｜ 生命 %d ｜ 理智 %d ｜ 积分 %d ｜ 钱 %d',
    !empty($pc['ready']) ? '是' : '否', count((array) $pc['skills']), (int) $pc['hp'], (int) $pc['san'],
    (int) $pc['credits'], (int) $pc['money']));

/* ── 2. 站在起点，先看能干什么 ── */
say('── 2. 起点（B1 主入口隧道）──');
$x = play($S, $ctx, $uid, '查看');
say('  可用出口/指令是否清楚：' . (implode('', (array) $x) !== '' ? '有输出' : '**无输出**'));

/* ── 3. 去指挥大厅值班板接训练考核 ── */
say('── 3. 走到指挥大厅接训练考核 ──');
/* 按真实出口走：隧道 → 防爆门 → 主走廊 → 中段 → 东段 → …（用出口名） */
function walk_to(&$S, $ctx, $uid, $target, &$problems) {
    $seen = []; $steps = 0;
    while (($S['room'] ?? '') !== $target && $steps++ < 12) {
        $rooms = crrg_drill_rooms();
        $exits = (array) ($rooms[$S['room']]['x'] ?? []);
        if (!$exits) { $problems[] = '[走路] ' . ($rooms[$S['room']]['t'] ?? $S['room']) . ' 没有任何出口'; return false; }
        /* 简单 BFS：找一条能到 target 的路径 */
        $path = null;
        foreach ($exits as $label => $dest) {
            if ($dest === $target) { $path = [$label, $dest]; break; }
        }
        if ($path === null) {
            foreach ($exits as $label => $dest) {
                $q = [[$dest, [$label]]]; $vis = [$S['room'] => 1];
                while ($q) {
                    [$cur, $acc] = array_shift($q);
                    if ($cur === $target) { $path = [$acc, $cur]; break 2; }
                    if (isset($vis[$cur])) continue;
                    $vis[$cur] = 1;
                    foreach ((array) ($rooms[$cur]['x'] ?? []) as $l2 => $d2) $q[] = [$d2, array_merge($acc, [$l2])];
                }
            }
        }
        if ($path === null) { $problems[] = '[走路] 从 ' . ($S['room'] ?? '?') . ' 到 ' . $target . ' 找不到路'; return false; }
        [$acc, $dest] = $path;
        $first = is_array($acc) ? $acc[0] : $acc;
        play($S, $ctx, $uid, '去 ' . $first, true);
    }
    return ($S['room'] ?? '') === $target;
}
walk_to($S, $ctx, $uid, 'b2b_hall', $problems);
$pc = crrg_drill_pc($uid);
say(sprintf('  当前房间：%s', (crrg_drill_rooms()[$S['room']]['t'] ?? $S['room'])));
$l = play($S, $ctx, $uid, '接受 m_train_01');
$pc = crrg_drill_pc($uid);
if (empty($pc['mission'])) note($problems, '接任务', '训练考核没能接上（返回：' . mb_substr(implode(' ', (array) $l), 0, 40) . '）');

/* ── 4. 照任务提示走训练流程 ── */
say('── 4. 按任务提示走（不预置任何东西）──');
$m = crrg_drill_mission($pc['mission'] ?? '');
if ($m) {
    foreach ($m['steps'] as $st) say('  步骤：' . ($st['text'] ?? ''));
}
/* 训练考核的真实流程（按提示：领任务 → 整备室领防护服 → 下 B4 → 取样 → 回指挥大厅交） */
$route = [['取 防护服', 'b2f_gear'], ['去 三层闸', 'b4_buffer'], ['取样', 'b4_c2'], ['交任务', 'b2b_hall']];
foreach ($route as [$verb, $room]) {
    if (mb_strpos($verb, '取') === 0) { walk_to($S, $ctx, $uid, $room, $problems); }
    $l = play($S, $ctx, $uid, $verb);
    $pc = crrg_drill_pc($uid);
    say(sprintf('      状态：在手 %s ｜ 进度 %d ｜ 位置 %s', $pc['mission'] ?: '无', (int) ($pc['mission_step'] ?? 0),
        crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']));
    $t = implode(' ', (array) $l);
    if (mb_strpos($t, '没有') !== false || mb_strpos($t, '还差') !== false || mb_strpos($t, '不知道') !== false)
        note($problems, '训练流程', $verb . ' @' . (crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']) . ' → ' . mb_substr($t, 0, 50));
    if ($room !== 'b2b_hall' && mb_strpos($verb, '取') !== 0) { /* 下一站 */ }
}

/* ── 5. 看结算后拿到什么 ── */
$pc = crrg_drill_pc($uid);
say(sprintf('── 5. 训练结果：积分 %d ｜ 钱 %d ｜ 卡级 %s ｜ 完成合约 %d 项',
    (int) $pc['credits'], (int) $pc['money'], crrg_drill_ctx($uid)['card'], count((array) ($pc['mission_done'] ?? []))));

/* ── 6. 以 D 级身份试着接新任务（应被权限拦住 → 记录门槛体验）── */
say('── 6. 试接新任务（低级别玩家的真实体验）──');
foreach (['m_st_lastwatch', 'm_dy_watch', 'm_mid_seal', 'm_wild_01'] as $mid) {
    $pc = crrg_drill_pc($uid);
    if (!empty($pc['mission'])) play($S, $ctx, $uid, '放弃合约', true);
    $l = play($S, $ctx, $uid, '接受 ' . $mid);
    $pc = crrg_drill_pc($uid);
    $ok = (($pc['mission'] ?? '') === $mid) || (($pc['mission2']['id'] ?? '') === $mid);
    say(sprintf('      → %s：%s', $mid, $ok ? '接上了' : '被拦（' . mb_substr(implode(' ', array_slice((array) $l, 0, 2)), 0, 60) . '）'));
    if (!$ok) note($problems, '门槛', $mid . ' 被拦：' . mb_substr(implode(' ', (array) $l), 0, 70));
}

/* ── 7. 走一条能做的（日常：当班值守，需夜间）── */
say('── 7. 走一条日常（当班值守）──');
$pc = crrg_drill_pc($uid);
if (!empty($pc['mission'])) play($S, $ctx, $uid, '放弃合约', true);
$l = play($S, $ctx, $uid, '接受 m_dy_watch');
$pc = crrg_drill_pc($uid);
if ((($pc['mission'] ?? '') === 'm_dy_watch') || (($pc['mission2']['id'] ?? '') === 'm_dy_watch')) {
    say('  接上了。现在试着在白天直接做（应被拦）与夜间做（应可做）：');
    $S['room'] = 'b2b_duty';
    $r1 = crrg_drill_do_act($S, $pc, $ctx, $uid, 'mact', 'm_dy_watch|dy1_值');
    say('      白天点「夜里值守」→ ' . mb_substr(implode(' ', (array) $r1), 0, 50));
    $o = get_option('crrg_drill_clock'); $save = $o;
    update_option('crrg_drill_clock', ['real' => time(), 'game' => (new DateTime(crrg_wdate('Y-m-d', $o['game']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp()], false);
    crrg_drill_set_scene('base');
    $pc = crrg_drill_pc($uid);
    $r2 = crrg_drill_do_act($S, $pc, $ctx, $uid, 'mact', 'm_dy_watch|dy1_值');
    say('      夜间点「夜里值守」→ ' . mb_substr(implode(' ', (array) $r2), 0, 50) . ' ｜ 进度 ' . (int) $pc['mission_step']);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    walk_to($S, $ctx, $uid, 'b2b_hall', $problems);
    $pc = crrg_drill_pc($uid);
    $r3 = crrg_drill_do_act($S, $pc, $ctx, $uid, 'mact', 'm_dy_watch|dy1_归档');
    say('      白天回值班板点「天亮归档」→ ' . mb_substr(implode(' ', (array) $r3), 0, 46) . ' ｜ 进度 ' . (int)($pc['mission2']['step'] ?? -1));
    crrg_drill_pc_save($uid, $pc);
    $before = ['c' => (int) $pc['credits'], 'm' => (int) $pc['money']];
    [$mm, $ll] = crrg_drill_mission_settle($pc, $ctx, $uid);
    say('      结算 → ' . mb_substr(implode(' / ', (array) $ll), 0, 90));
    say(sprintf('      日常收益：积分 +%d ｜ 钱 +%d', (int) $pc['credits'] - $before['c'], (int) $pc['money'] - $before['m']));
    if (empty($ll) || mb_strpos(implode(' ', (array) $ll), '结算不了') !== false)
        note($problems, '日常任务', '当班值守走完却结算不了');
    update_option('crrg_drill_clock', $save, false);
} else {
    note($problems, '日常任务', '当班值守接不上');
}

/* ── 8. 收尾：把问题清单打出来 ── */
say('');
say('════════ 低级别游玩报告 ════════');
$pc = crrg_drill_pc($uid);
say(sprintf('结束时：积分 %d ｜ 钱 %d ｜ 库存(若有) %s ｜ 完成 %d 项',
    (int) $pc['credits'], (int) $pc['money'], isset($pc['seal']['stock_days']) ? $pc['seal']['stock_days'] . ' 天' : '无',
    count((array) ($pc['mission_done'] ?? []))));
say('发现的问题（' . count($problems) . ' 条）：');
foreach ($problems as $i => $p) say('  ' . ($i + 1) . '. ' . $p);
if (!$problems) say('  （无）');

/* 清理 */
delete_user_meta($uid, 'crrg_drill_pc');
delete_user_meta($uid, 'crrg_drill_state');
wp_delete_user($uid);
crrg_drill_set_scene('base');
