<?php
/**
 * 从头到尾真玩（连续流程）：tools/playtest-run.php
 *   A 车卡 → B 训练考核 → C 日常×5 → D 晋升 D→C → E 一次性×5 → F 中型《封条》第一幕 → G 系统抽查 → H 镇上
 * 只用玩家指令/面板动作；不预置任何属性、技能、金钱、旗标（时间与体力按真实等待处理）
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$P = []; $LOG = []; $MONEY0 = 0;
function say($s) { echo $s . "\n"; }
function prob(&$P, $w, $t) { $P[] = sprintf('[%s] %s', $w, $t); }
require_once ABSPATH . 'wp-admin/includes/user.php';

$uname = 'pt_run_' . wp_rand(10000, 99999);
$uid = wp_insert_user(['user_login' => $uname, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
say("=== 从头到尾真玩（新号 #$uid）===");
$S = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function C($uid) { return crrg_drill_ctx($uid); }
function play(&$S, $uid, $cmd, $show = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, C($uid), $cmd, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if ($show) { say('  > ' . $cmd); foreach ((array) $l as $x) say('      ' . mb_substr($x, 0, 96)); }
    return $l;
}
function act(&$S, $uid, $k, $id = '', $show = true) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, C($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if ($show) say('  · «' . $k . ($id ? ':' . $id : '') . '» → ' . mb_substr(implode(' / ', (array) $r), 0, 84));
    return $r;
}
/** 只在本场景内走；必要时绕路 */
function walk(&$S, $uid, $target, &$P) {
    $g = 0;
    while (($S['room'] ?? '') !== $target && $g++ < 40) {
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$S['room']])) { prob($P, '走路', '房间不存在：' . $S['room']); return false; }
        if (!isset($rooms[$target])) { prob($P, '走路', '目标不在本场景：' . $target . '（当前 ' . crrg_drill_cur_scene() . '）'); return false; }
        $exits = [];
        foreach ((array) ($rooms[$S['room']]['x'] ?? []) as $label => $dest) if (isset($rooms[$dest])) $exits[$label] = $dest;
        if (!$exits) { prob($P, '走路', '死胡同（无本场景出口）：' . $S['room']); return false; }
        $path = null;
        if (isset($exits[$target])) $path = [$target];
        foreach ($exits as $label => $dest) if ($dest === $target) { $path = [$label]; break; }
        if ($path === null) {
            foreach ($exits as $label => $dest) {
                $q = [[$dest, [$label]]]; $vis = [$S['room'] => 1];
                while ($q) { [$cur, $acc] = array_shift($q); if (isset($vis[$cur])) continue; $vis[$cur] = 1;
                    if ($cur === $target) { $path = $acc; break 2; }
                    foreach ((array) ($rooms[$cur]['x'] ?? []) as $l2 => $d2) if (isset($rooms[$d2])) $q[] = [$d2, array_merge($acc, [$l2])]; }
            }
        }
        if ($path === null) { prob($P, '走路', sprintf('本场景内从 %s 到 %s 无路', $S['room'], $target)); return false; }
        play($S, $uid, '去 ' . $path[0], false);
    }
    return ($S['room'] ?? '') === $target;
}
function to_night(&$S, $uid) {
    $o = get_option('crrg_drill_clock');
    $t = (new DateTime(crrg_wdate('Y-m-d', $o['game']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
    if ($t > $o['game']) update_option('crrg_drill_clock', ['real' => time(), 'game' => $t], false);
}
function rest_if_tired(&$S, $uid, &$P, $force = false) {
    $pc = crrg_drill_pc($uid);
    $e = (float) ($pc['vitals']['energy'] ?? 100);
    if (!$force && $e >= 40) return;
    if (!walk($S, $uid, 'b1_room', $P)) return;
    play($S, $uid, '睡觉', false);
    $o = get_option('crrg_drill_clock');
    update_option('crrg_drill_clock', ['real' => time(), 'game' => $o['game'] + 8 * 3600], false);
    play($S, $uid, '起床', false);
    $pc = crrg_drill_pc($uid);
    say(sprintf('    （宿舍睡了一觉 → 精力 %d ｜ 理智 %d）', (int) $pc['vitals']['energy'], (int) $pc['san']));
}
/** 交任务（先确保在值班板） */
function settle_at_board(&$S, $uid, &$P) {
    walk($S, $uid, 'b2b_hall', $P);
    $pc = crrg_drill_pc($uid);
    $b = [(int) $pc['credits'], (int) $pc['money']];
    $l = play($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    return [(int) $pc['credits'] - $b[0], (int) $pc['money'] - $b[1], $l];
}

/* ═════ A. 车卡 ═════ */
say('── A. 车卡 ──');
foreach (['掷骰', '选职业 行动员', '自动分配', '完成'] as $c) play($S, $uid, $c, false);
$pc = crrg_drill_pc($uid); $pc['scene'] = 'base'; crrg_drill_pc_save($uid, $pc);
$MONEY0 = (int) $pc['money'];
say(sprintf('  卡 %s ｜ 技能 %d 项 ｜ 生命 %d ｜ 理智 %d ｜ 积分 %d ｜ 钱 %d',
    C($uid)['card'], count((array) $pc['skills']), (int) $pc['hp'], (int) $pc['san'], (int) $pc['credits'], (int) $pc['money']));

/* ═════ B. 训练考核 ═════ */
say('── B. 训练考核 ──');
walk($S, $uid, 'b2b_hall', $P);
play($S, $uid, '接受 m_train_01', false);
walk($S, $uid, 'b2f_gear', $P);
$r = play($S, $uid, '取 防护服', false);
say('  取防护服 → ' . mb_substr(implode(' ', (array) $r), 0, 56));
if (mb_strpos(implode(' ', (array) $r), '没有可以取') !== false) prob($P, '训练考核', '装备整备室仍取不到防护服');
play($S, $uid, '穿戴 防护服', false);
walk($S, $uid, 'b4_c2', $P);
$r = play($S, $uid, '取样', false);
say('  C2 取样 → ' . mb_substr(implode(' ', (array) $r), 0, 56));
[$dc, $dm, $l] = settle_at_board($S, $uid, $P);
say(sprintf('  交报告 → 积分 +%d ｜ 钱 +%d ｜ %s', $dc, $dm, mb_substr(implode(' ', array_slice((array) $l, 0, 2)), 0, 50)));
if (empty(crrg_drill_pc($uid)['mission_done'])) prob($P, '训练考核', '走完未入档');

/* ═════ C. 日常 ×5 ═════ */
say('── C. 日常任务 ×5 ──');
$D = [
    'm_dy_watch'   => [['night', ''], ['walk', 'b2b_duty'], ['mact', 'm_dy_watch|dy1_值'], ['walk', 'b2b_hall'], ['mact', 'm_dy_watch|dy1_归档']],
    'm_dy_patrol'  => [['walk', 'b3_entry'], ['mact', 'm_dy_patrol|dy2_点1'], ['walk', 'b4_main'], ['mact', 'm_dy_patrol|dy2_点2'], ['walk', 'b2b_hall'], ['mact', 'm_dy_patrol|dy2_点3']],
    'm_dy_kitchen' => [['walk', 'b1_canteen'], ['mact', 'm_dy_kitchen|dy3_顶班'], ['mact', 'm_dy_kitchen|dy3_收灶']],
    'm_dy_haul'    => [['walk', 'garage_maint'], ['mact', 'm_dy_haul|dy4_领货'], ['mact', 'm_dy_haul|dy4_交单']],
    'm_dy_repair'  => [['walk', 'b2b_tech'], ['mact', 'm_dy_repair|dy5_接单'], ['mact', 'm_dy_repair|dy5_交单']],
];
$tot = [0, 0];
foreach ($D as $mid => $steps) {
    rest_if_tired($S, $uid, $P);
    $l = play($S, $uid, '接受 ' . $mid, false);
    if (mb_strpos(implode(' ', (array) $l), '接不了') !== false || mb_strpos(implode(' ', (array) $l), '完成过') !== false) { prob($P, '日常', $mid . ' 接不上'); continue; }
    $pre = null;
    foreach ($steps as [$k, $v]) {
        if ($k === 'night') { to_night($S, $uid); continue; }
        if ($k === 'walk')  { walk($S, $uid, $v, $P); continue; }
        if ($k === 'mact')  { rest_if_tired($S, $uid, $P); $r = act($S, $uid, 'mact', $v, false);
            if (mb_strpos(implode(' ', (array) $r), '不是你手上的活儿') !== false) prob($P, '日常', $mid . ' 动作被拒：' . $v); }
    }
    [$dc, $dm, $l2] = settle_at_board($S, $uid, $P);
    $tot[0] += $dc; $tot[1] += $dm;
    say(sprintf('  %-14s 积分 +%-3d 钱 +%-4d ｜ %s', $mid, $dc, $dm, mb_substr(implode(' ', array_slice((array) $l2, 0, 2)), 0, 42)));
    if (!$dc && !$dm) prob($P, '日常', $mid . ' 结算无收益');
}
say(sprintf('  ［日常合计］积分 +%d ｜ 钱 +%d', $tot[0], $tot[1]));

/* ═════ D. 晋升 ═════ */
say('── D. 晋升 ──');
$pc = crrg_drill_pc($uid);
say(sprintf('  当前 积分 %d ｜ 卡级 %s', (int) $pc['credits'], C($uid)['card']));
$l = play($S, $uid, '晋升', true);
if (C($uid)['card'] === 'D' && (int) $pc['credits'] >= 30) prob($P, '晋升', '积分够却没升上去');

/* ═════ E. 一次性 ×5 ═════ */
say('── E. 一次性任务 ×5 ──');
foreach (['m_st_lastwatch', 'm_st_ninth', 'm_st_water', 'm_st_cigs', 'm_st_roster'] as $mid) {
    rest_if_tired($S, $uid, $P);
    $pc = crrg_drill_pc($uid);
    if (!empty($pc['mission'])) play($S, $uid, '放弃合约', false);
    $l = play($S, $uid, '接受 ' . $mid, false);
    if ((crrg_drill_pc($uid)['mission'] ?? '') !== $mid) { prob($P, '一次性', $mid . ' 接不上：' . mb_substr(implode(' ', (array) $l), 0, 52)); continue; }
    $acts = crrg_base_mission_acts()[$mid] ?? [];
    foreach ($acts as $room => $specs) {
        walk($S, $uid, $room, $P);
        foreach ($specs as $sp) {
            if (!empty($sp[3])) to_night($S, $uid);
            rest_if_tired($S, $uid, $P);
            act($S, $uid, 'mact', $mid . '|' . $sp[1], false);
        }
    }
    $pc = crrg_drill_pc($uid);
    $done = (int) ($pc['mission_step'] ?? 0); $total = count((array) (crrg_drill_mission($mid)['steps'] ?? []));
    [$dc, $dm, $l2] = settle_at_board($S, $uid, $P);
    say(sprintf('  %-16s %d/%d ｜ 积分 +%-3d 钱 +%-4d ｜ %s', $mid, $done, $total, $dc, $dm, mb_substr(implode(' ', array_slice((array) $l2, 0, 2)), 0, 36)));
    if ($done < $total) prob($P, '一次性', sprintf('%s 只走到 %d/%d', $mid, $done, $total));
}

/* ═════ F. 中型《封条》第一幕 ═════ */
say('── F. 中型《封条》第一幕 ──');
rest_if_tired($S, $uid, $P, true);
$pc = crrg_drill_pc($uid);
if (!empty($pc['mission'])) play($S, $uid, '放弃合约', false);
$l = play($S, $uid, '接受 m_mid_seal', false);
if ((crrg_drill_pc($uid)['mission'] ?? '') === 'm_mid_seal') {
    foreach (['b3_warehouse' => '封条', 'b4_main' => '封条', 'b4_c2' => '门', 'b4_obs' => '台账', 'b2b_tech' => '配方', 'b1_archive' => '采购'] as $room => $kw) {
        walk($S, $uid, $room, $P); rest_if_tired($S, $uid, $P);
        $r = play($S, $uid, '调查 ' . $kw, false);
        say(sprintf('  调查 %-4s → %s', $kw, mb_substr(implode(' ', array_slice((array) $r, 0, 2)), 0, 52)));
    }
    foreach ([['b3_warehouse', '周德海 就地取材'], ['b2b_tech', '韩雪 配方'], ['b4_main', '守卫 哪几扇'], ['b1_med', '白大夫 当年']] as [$room, $q]) {
        walk($S, $uid, $room, $P);
        $r = play($S, $uid, '细问 ' . $q, false);
        say(sprintf('  细问 %-12s → %s', $q, mb_substr(implode(' ', array_slice((array) $r, 0, 2)), 0, 46)));
    }
    walk($S, $uid, 'b2b_hall', $P);
    act($S, $uid, 'stagechoice', '压下', false);
    $pc = crrg_drill_pc($uid);
    say(sprintf('  决断（压下）→ 进度 %d/%d ｜ 封条库存 %s 天', (int) $pc['mission_step'],
        count((array) crrg_drill_mission('m_mid_seal')['steps']), $pc['seal']['stock_days'] ?? '?'));
    [$dc, $dm, $l2] = settle_at_board($S, $uid, $P);
    say(sprintf('  第一幕结算 → 积分 +%d ｜ 钱 +%d ｜ %s', $dc, $dm, mb_substr(implode(' ', array_slice((array) $l2, 0, 2)), 0, 46)));
} else prob($P, '中型', '第一幕接不上：' . mb_substr(implode(' ', (array) $l), 0, 60));

/* ═════ G. 系统抽查（基地） ═════ */
say('── G. 系统抽查（基地）──');
$pc = crrg_drill_pc($uid);
walk($S, $uid, 'b3_warehouse', $P);
$shop = null;
foreach (crrg_drill_panels($S, C($uid), $pc, $uid) as $pp) if (mb_strpos($pp['title'], '商店') === 0) $shop = $pp;
if ($shop && !empty($shop['items'])) { $f = $shop['items'][0]; $r = act($S, $uid, $f['k'], $f['id'] ?? '', false); say('  商店：' . mb_substr($f['label'], 0, 18) . ' → ' . mb_substr(implode(' ', (array) $r), 0, 48)); }
else prob($P, '商店', '商超无条目');
foreach (['背包', '状态'] as $c) { $r = play($S, $uid, $c, false); say(sprintf('  %s → %s', $c, mb_substr(implode(' / ', array_slice((array) $r, 0, 2)), 0, 50))); }
walk($S, $uid, 'b1_med', $P);
$r = play($S, $uid, '治伤', false);
say('  医疗室治伤 → ' . mb_substr(implode(' / ', (array) $r), 0, 54));
walk($S, $uid, 'b4_obs', $P);
$r = act($S, $uid, 'appraise', 'key_sigil', false);
say('  观察室鉴定 → ' . mb_substr(implode(' ', (array) $r), 0, 54));
walk($S, $uid, 'b1_room', $P);
rest_if_tired($S, $uid, $P, true);

/* ═════ H. 长途去镇上 ═════ */
say('── H. 长途去青石镇 ──');
$r = play($S, $uid, '长途 青石镇', false);
say('  ' . mb_substr(implode(' / ', (array) $r), 0, 76));
$pc = crrg_drill_pc($uid);
$pc['travel']['arrive'] = crrg_vitals_now() - 5;      // 模拟路上时间已过（真实玩家会等）
crrg_drill_pc_save($uid, $pc);
$S['room'] = 'tw_main_w'; crrg_drill_save($uid, $S);
crrg_drill_set_scene('town');
$r = play($S, $uid, '查看', false);
say('  到镇上 → ' . mb_substr(implode(' / ', array_slice((array) $r, 0, 2)), 0, 60));
foreach ([['tw_coop', '供销社'], ['tw_clinic', '卫生院'], ['tw_job', '劳务点']] as [$room, $nm]) {
    if (!isset(crrg_drill_rooms()[$room])) { prob($P, '镇上', '房间不存在：' . $room); continue; }
    walk($S, $uid, $room, $P);
    $r = play($S, $uid, '查看', false);
    say(sprintf('  %s → %s', $nm, mb_substr(implode(' / ', array_slice((array) $r, 0, 1)), 0, 60)));
}
/* 回基地 */
$r = play($S, $uid, '长途 基地', false);
say('  回程 → ' . mb_substr(implode(' / ', (array) $r), 0, 60));
crrg_drill_set_scene('base');

/* ═════ 报告 ═════ */
$pc = crrg_drill_pc($uid);
say('');
say('════════ 全程真玩报告 ════════');
say(sprintf('卡级 %s ｜ 积分 %d ｜ 钱 %d（起始 %d）｜ 完成合约 %d 项 ｜ 问题 %d 条',
    C($uid)['card'], (int) $pc['credits'], (int) $pc['money'], $MONEY0, count((array) $pc['mission_done']), count($P)));
foreach ($P as $i => $x) say('  ' . ($i + 1) . '. ' . $x);
if (!$P) say('  （无）');
delete_user_meta($uid, 'crrg_drill_pc'); delete_user_meta($uid, 'crrg_drill_state');
wp_delete_user($uid);
