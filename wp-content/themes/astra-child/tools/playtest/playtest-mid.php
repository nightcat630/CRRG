<?php
/**
 * 新号跑中型《封条》全五轮：tools/playtest-mid.php
 *   新号 → 车卡 → 一轮日常攒够 30 分 → 晋升 C 级 → m_mid_seal → seal2 → seal3 → seal4 → seal5
 * 说明：世界钟是全局的，本脚本**只在必要时前拨到夜间**，结束后**原样还原**。
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$P = [];
function say($s) { echo $s . "\n"; }
function prob(&$P, $w, $t) { $P[] = '[' . $w . '] ' . $t; }

/* 记录并还原世界钟 */
$CLOCK0 = get_option('crrg_drill_clock', null);
$BACKUP0 = get_option('crrg_drill_clock_backup', null);
register_shutdown_function(function () use ($CLOCK0, $BACKUP0) {
    if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false);
    if (is_array($BACKUP0)) update_option('crrg_drill_clock_backup', $BACKUP0, false);
    echo "（世界钟已还原）\n";
});

$uname = 'pt_mid_' . wp_rand(10000, 99999);
$uid = wp_insert_user(['user_login' => $uname, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
say('=== 新号跑中型《封条》（#' . $uid . '）===');
$S = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function play(&$S, $uid, $cmd, $show = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $cmd, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if ($show) { say('  > ' . $cmd); foreach ((array) $l as $x) say('      ' . mb_substr($x, 0, 92)); }
    return $l;
}
function act(&$S, $uid, $k, $id = '', $show = false) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if ($show) say('  · «' . $k . '» → ' . mb_substr(implode(' / ', (array) $r), 0, 84));
    return $r;
}
function walk(&$S, $uid, $target, &$P) {
    /* 用游戏自己的路线指引走（比自定义 BFS 靠谱） */
    for ($g = 0; $g < 30; $g++) {
        if (($S['room'] ?? '') === $target) return true;
        $hint = function_exists('crrg_drill_route_hint') ? crrg_drill_route_hint($S['room'] ?? '', $target) : '';
        if ($hint === '' || mb_strpos($hint, '（') === 0) { prob($P, '走路', '没路线：' . ($S['room'] ?? '?') . ' → ' . $target . '（' . $hint . '）'); return false; }
        $first = explode(' → ', $hint)[0];
        play($S, $uid, '去 ' . $first, false);
        if (($S['room'] ?? '') === $last = $S['room'] && false) { }
    }
    if (($S['room'] ?? '') !== $target) { prob($P, '走路', '走了 30 步仍未到 ' . $target . '（现在 ' . ($S['room'] ?? '?') . '）'); return false; }
    return true;
}
/** 夜间：只前拨到当天 23:00（不改日期） */
function to_night() {
    $c = crrg_world_clock();
    $t = (new DateTime(crrg_wdate('Y-m-d', $c['ts']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
    if ($t > $c['ts']) crrg_world_set_game($t, 'playtest-mid 夜间');
}
function rest_if_tired(&$S, $uid, &$P) {
    $pc = crrg_drill_pc($uid);
    if ((float) ($pc['vitals']['energy'] ?? 100) >= 40) return;
    if (!walk($S, $uid, 'b1_room', $P)) return;
    play($S, $uid, '睡觉', false);
    $c = crrg_world_clock();
    crrg_world_set_game($c['ts'] + 8 * 3600, 'playtest-mid 睡眠');
    play($S, $uid, '起床', false);
}
/** 反复尝试某调查点，直到拿到旗标（新号技能低，设计上允许重试） */
function survey_until(&$S, $uid, $room, $kw, $flag, &$P, $tries = 6) {
    walk($S, $uid, $room, $P);
    for ($i = 0; $i < $tries; $i++) {
        rest_if_tired($S, $uid, $P);
        $r = play($S, $uid, '调查 ' . $kw, false);
        $pc = crrg_drill_pc($uid);
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) { say(sprintf('  ✓ 调查 %s（第 %d 次）', $kw, $i + 1)); return true; }
    }
    prob($P, '调查', $room . ' 的「' . $kw . '」试了 ' . $tries . ' 次仍未拿到 ' . $flag);
    return false;
}

/* ── 1. 车卡 ── */
foreach (['掷骰', '选职业 行动员', '自动分配', '完成'] as $c) play($S, $uid, $c, false);
$pc = crrg_drill_pc($uid); $pc['scene'] = 'base'; crrg_drill_pc_save($uid, $pc);
say(sprintf('车卡：%s ｜ 积分 %d ｜ 钱 %d ｜ 关键技能 侦查 %d／说服 %d／会计 %d',
    crrg_drill_ctx($uid)['card'], (int) $pc['credits'], (int) $pc['money'],
    crrg_coc_skill_val($pc, '侦查'), crrg_coc_skill_val($pc, '说服'), crrg_coc_skill_val($pc, '会计')));

/* ── 2. 做一轮日常攒够 30 分（真实路径） ── */
say('── 攒积分：一轮日常 ──');
$tot = [0, 0];
foreach ([
    ['m_dy_patrol',  [['walk', 'b3_entry'], ['act', 'dy2_点1'], ['walk', 'b4_main'], ['act', 'dy2_点2'], ['walk', 'b2b_hall'], ['act', 'dy2_点3']]],
    ['m_dy_kitchen', [['walk', 'b1_canteen'], ['act', 'dy3_顶班'], ['act', 'dy3_收灶']]],
    ['m_dy_haul',    [['walk', 'garage_maint'], ['act', 'dy4_领货'], ['act', 'dy4_交单']]],
    ['m_dy_repair',  [['walk', 'b2b_tech'], ['act', 'dy5_接单'], ['act', 'dy5_交单']]],
] as [$mid, $seq]) {
    rest_if_tired($S, $uid, $P);
    $l = play($S, $uid, '接受 ' . $mid, false);
    if (mb_strpos(implode(' ', (array) $l), '接不了') !== false) { prob($P, '日常', $mid . ' 接不了'); continue; }
    foreach ($seq as [$k, $v]) {
        if ($k === 'walk') walk($S, $uid, $v, $P);
        else { rest_if_tired($S, $uid, $P); act($S, $uid, 'mact', self_mact_id($uid, $mid, $v)); }
    }
    walk($S, $uid, 'b2b_hall', $P);
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    $l2 = play($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    $dc = (int) $pc['credits'] - $b0[0]; $dm = (int) $pc['money'] - $b0[1];
    $tot[0] += $dc; $tot[1] += $dm;
    say(sprintf('  %-14s +%-3d 分 / +%-4d 元', $mid, $dc, $dm));
    if (!$dc && !$dm) prob($P, '日常', $mid . ' 结算无收益：' . mb_substr(implode(' ', array_slice((array) $l2, 0, 2)), 0, 40));
}
say(sprintf('  合计 +%d 分 / +%d 元 ｜ 现有积分 %d', $tot[0], $tot[1], (int) crrg_drill_pc($uid)['credits']));

/* 由 flag 反查短码 id */
function self_mact_id($uid, $mid, $flag) {
    foreach ((array) (crrg_base_mission_acts()[$mid] ?? []) as $room => $specs) foreach ((array) $specs as $sp) if ($sp[1] === $flag) return 'a' . substr(md5($mid . '|' . $sp[1]), 0, 10);
    /* 日常表里是 dy2_点1 这类，直接用短码 */
    $map = ['dy2_点1' => 'm_dy_patrol|dy2_点1', 'dy2_点2' => 'm_dy_patrol|dy2_点2', 'dy2_点3' => 'm_dy_patrol|dy2_点3',
            'dy3_顶班' => 'm_dy_kitchen|dy3_顶班', 'dy3_收灶' => 'm_dy_kitchen|dy3_收灶',
            'dy4_领货' => 'm_dy_haul|dy4_领货', 'dy4_交单' => 'm_dy_haul|dy4_交单',
            'dy5_接单' => 'm_dy_repair|dy5_接单', 'dy5_交单' => 'm_dy_repair|dy5_交单'];
    return isset($map[$flag]) ? $map[$flag] : $flag;
}

/* ── 3. 晋升到 C ── */
say('── 晋升 ──');
play($S, $uid, '晋升', true);
$lv = crrg_drill_ctx($uid);
say('  现在的演习权限：' . $lv['card']);
if ($lv['card'] === 'D') prob($P, '晋升', '积分够但仍是 D 级');

/* ── 4. 中型 第一幕 ── */
say('── 中型第一幕：松的封条 ──');
walk($S, $uid, 'b2b_hall', $P);
$l = play($S, $uid, '接受 m_mid_seal', false);
if ((crrg_drill_pc($uid)['mission'] ?? '') !== 'm_mid_seal') { prob($P, '中型', '第一幕接不上：' . mb_substr(implode(' ', (array) $l), 0, 50)); }
else {
    survey_until($S, $uid, 'b3_warehouse', '封条', 'seal_库存盘点', $P);
    survey_until($S, $uid, 'b4_main', '封条', 'seal_门况普查', $P);
    survey_until($S, $uid, 'b4_c2', '门', 'seal_私补痕迹', $P);
    survey_until($S, $uid, 'b4_obs', '台账', 'seal_用量台账', $P);
    survey_until($S, $uid, 'b2b_tech', '配方', 'seal_自制配方', $P);
    survey_until($S, $uid, 'b1_archive', '采购', 'seal_三年档案', $P);
    foreach ([['b3_warehouse', '周德海 就地取材'], ['b2b_tech', '韩雪 配方'], ['b4_main', '守卫 哪几扇'], ['b1_med', '白大夫 当年']] as [$room, $q]) {
        walk($S, $uid, $room, $P); rest_if_tired($S, $uid, $P);
        play($S, $uid, '细问 ' . $q, false);
    }
    walk($S, $uid, 'b2b_hall', $P);
    $r = act($S, $uid, 'stagechoice', '压下');
    $pc = crrg_drill_pc($uid);
    say(sprintf('  决断后 %d/%d 步 ｜ 封条库存 %s 天', (int) $pc['mission_step'], count((array) crrg_drill_mission('m_mid_seal')['steps']), $pc['seal']['stock_days'] ?? '?'));
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    play($S, $uid, '交任务', true);
    $pc = crrg_drill_pc($uid);
    say(sprintf('  第一幕结算：+%d 分 / +%d 元', (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1]));
}

/* ── 5. 中型 第二幕（镇上） ── */
say('── 中型第二幕：镇上的纸 ──');
$l = play($S, $uid, '长途 青石镇', false);
$pc = crrg_drill_pc($uid);
if (!empty($pc['travel'])) { $pc['travel']['arrive'] = crrg_vitals_now() - 5; crrg_drill_pc_save($uid, $pc); }
crrg_drill_set_scene('town');
$S['room'] = 'tw_main_e'; crrg_drill_save($uid, $S);
$l = play($S, $uid, '接受 m_mid_seal2', false);
if ((crrg_drill_pc($uid)['mission'] ?? '') !== 'm_mid_seal2') { prob($P, '中型', '第二幕接不上：' . mb_substr(implode(' ', (array) $l), 0, 50)); }
else {
    survey_until($S, $uid, 'tw_paper', '配方', 'paper_配方', $P);
    survey_until($S, $uid, 'tw_farm', '电瓶', 'paper_铅箔', $P);
    survey_until($S, $uid, 'tw_clinic', '中毒', 'paper_中毒', $P);
    survey_until($S, $uid, 'tw_tomb', '土', 'paper_朱砂土', $P);
    /* 手续：先问出暗账路子（无介绍信时） */
    walk($S, $uid, 'tw_townhall', $P);
    play($S, $uid, '细问 镇文书 绕过', false);
    survey_until($S, $uid, 'tw_townhall', '手续', 'paper_通行', $P);
    foreach ([['tw_paper', '常守业 旧库管'], ['tw_junk', '孙德发 生面孔'], ['tw_farm', '赵铁 同一个人']] as [$room, $q]) {
        walk($S, $uid, $room, $P); play($S, $uid, '细问 ' . $q, false);
    }
    $pc = crrg_drill_pc($uid);
    say(sprintf('  镇上线：配方%s 铅皮%s 中毒%s 朱砂土%s 手续%s ｜ 集齐=%s ｜ 镇声望 %d',
        in_array('paper_配方', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_铅箔', (array) $S['flags'], true) ? '✓' : '×',
        in_array('paper_中毒', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_朱砂土', (array) $S['flags'], true) ? '✓' : '×',
        in_array('paper_通行', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_集齐', (array) $S['flags'], true) ? '✓' : '×',
        crrg_rep_of($pc, 'town_qingshi')));
    walk($S, $uid, 'tw_townhall', $P);
    act($S, $uid, 'stagechoice', '带回');
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    play($S, $uid, '交任务', true);
    $pc = crrg_drill_pc($uid);
    say(sprintf('  第二幕结算：+%d 分 / +%d 元', (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1]));
    if (crrg_rep_of($pc, 'town_qingshi') < 5) prob($P, '第二幕', '镇声望偏低（' . crrg_rep_of($pc, 'town_qingshi') . '），第三幕的信用社门槛可能过不去');
}

/* ── 6. 收尾报告 ── */
crrg_drill_set_scene('base');
$pc = crrg_drill_pc($uid);
say('');
say('════════ 新号跑中型 · 报告 ════════');
say(sprintf('卡级 %s ｜ 积分 %d ｜ 钱 %d ｜ 完成 %d 项 ｜ 问题 %d 条',
    crrg_drill_ctx($uid)['card'], (int) $pc['credits'], (int) $pc['money'], count((array) $pc['mission_done']), count($P)));
foreach ($P as $i => $x) say('  ' . ($i + 1) . '. ' . $x);
if (!$P) say('  （无）');
delete_user_meta($uid, 'crrg_drill_pc'); delete_user_meta($uid, 'crrg_drill_state');
wp_delete_user($uid);
