<?php
/**
 * 全面真玩（low-level, 零预置）：tools/playtest-full.php
 * 阶段：车卡 → 训练考核 → 日常×5 → 晋升 D→C → 一次性×5 → 中型《封条》第一幕 → 系统逐个试
 * 只使用玩家能用的指令与面板动作（do_act/exec），输出「问题清单」
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$P = [];   // 问题
$gain = [];
function say($s) { echo $s . "\n"; }
function prob(&$P, $where, $what) { $P[] = sprintf('[%s] %s', $where, $what); }

require_once ABSPATH . 'wp-admin/includes/user.php';
$uname = 'pt_full_' . wp_rand(10000, 99999);
$uid = wp_insert_user(['user_login' => $uname, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
say("=== 全面真玩（新号 #$uid）===");
$ctx = crrg_drill_ctx($uid);
$S = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function play(&$S, $ctx, $uid, $cmd, $show = true) {
    $pc = crrg_drill_pc($uid);
    $ca = $ctx;   // ctx 是快照，每次重取
    $ctx2 = crrg_drill_ctx($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, $ctx2, $cmd, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if ($show) { say('  > ' . $cmd); foreach ((array) $l as $x) say('      ' . mb_substr($x, 0, 100)); }
    return $l;
}
function act(&$S, $ctx, $uid, $k, $id = '', $show = true) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if ($show) say('  · 点「' . $k . ($id ? ':' . $id : '') . '」→ ' . mb_substr(implode(' / ', (array) $r), 0, 84));
    return $r;
}
/* 精力低于 35 就回宿舍睡一觉（真实玩家的做法） */
function ensure_rest(&$S, $ctx, $uid, &$P) {
    $pc = crrg_drill_pc($uid);
    if ((float) ($pc['vitals']['energy'] ?? 100) >= 35) return;
    $home = 'b1_room';
    walk($S, $ctx, $uid, $home, $P);
    play($S, $ctx, $uid, '睡觉', false);
    $o = get_option('crrg_drill_clock');                       // 模拟真实等待：把游戏时钟前拨 8 小时
    update_option('crrg_drill_clock', ['real' => time(), 'game' => $o['game'] + 8 * 3600], false);
    play($S, $ctx, $uid, '起床', false);
    $pc = crrg_drill_pc($uid);
    say(sprintf('    （回宿舍睡了一觉：精力 %d ｜ 理智 %d）', (int) $pc['vitals']['energy'], (int) $pc['san']));
}
function walk(&$S, $ctx, $uid, $target, &$P) {
    $guard = 0;
    while (($S['room'] ?? '') !== $target && $guard++ < 40) {
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$S['room']])) { prob($P, '走路', '房间键不存在：' . $S['room']); return false; }
        $exits = [];
        foreach ((array) ($rooms[$S['room']]['x'] ?? []) as $label => $dest) {
            if (isset($rooms[$dest])) $exits[$label] = $dest;      // 只走本场景内的出口
        }
        $path = null;
        foreach ($exits as $label => $dest) if ($dest === $target) { $path = [$label]; break; }
        if ($path === null) {
            foreach ($exits as $label => $dest) {
                $q = [[$dest, [$label]]]; $vis = [$S['room'] => 1];
                while ($q) { [$cur, $acc] = array_shift($q); if (isset($vis[$cur])) continue; $vis[$cur] = 1;
                    if ($cur === $target) { $path = $acc; break 2; }
                    foreach ((array) ($rooms[$cur]['x'] ?? []) as $l2 => $d2) $q[] = [$d2, array_merge($acc, [$l2])]; }
            }
        }
        if ($path === null) { prob($P, '走路', sprintf('从 %s 走不到 %s（%d 步未达）', $S['room'], $target, $guard)); return false; }
        play($S, $ctx, $uid, '去 ' . $path[0], false);
    }
    if (($S['room'] ?? '') !== $target) { prob($P, '走路', sprintf('走了 %d 步仍未到 %s（现在 %s）', $guard, $target, $S['room'])); return false; }
    return true;
}
/* 只把时钟**向前**拨到当天 23:00（不倒退，避免伪造"长期不睡"） */
function night($on = true) {
    $o = get_option('crrg_drill_clock');
    if (!$on) return null;
    $target = (new DateTime(crrg_wdate('Y-m-d', $o['game']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
    if ($target <= $o['game']) return null;
    update_option('crrg_drill_clock', ['real' => time(), 'game' => $target], false);
    return $o;
}
/* 夜间动作之后必须睡觉（真实玩家行为），否则会因力竭掉理智 */
function sleep_off(&$S, $uid, $ctx) {
    $o = get_option('crrg_drill_clock');
    $r = play($S, $ctx, $uid, '睡觉', false);
    $r2 = play($S, $ctx, $uid, '起床', false);
    $pc = crrg_drill_pc($uid);
    if (!empty($r) && mb_strpos(implode(' ', (array) $r), '这儿') !== false) { /* 不在宿舍，先记一笔 */ }
    return $pc;
}

/* ═══ 1. 车卡 ═══ */
say('── 1. 车卡 ──');
foreach (['掷骰', '选职业 行动员', '自动分配', '完成'] as $c) play($S, $ctx, $uid, $c, false);
$pc = crrg_drill_pc($uid);
$pc['scene'] = 'base'; crrg_drill_pc_save($uid, $pc);
say(sprintf('  卡：%s ｜ 技能 %d 项 ｜ 生命 %d ｜ 理智 %d ｜ 积分 %d ｜ 钱 %d',
    crrg_drill_ctx($uid)['card'], count((array) $pc['skills']), (int) $pc['hp'], (int) $pc['san'], (int) $pc['credits'], (int) $pc['money']));

/* ═══ 2. 训练考核：完整走完 ═══ */
say('── 2. 训练考核（走完为止）──');
play($S, $ctx, $uid, '接受 m_train_01', false);
walk($S, $ctx, $uid, 'b2f_gear', $P);
$r = play($S, $ctx, $uid, '取 防护服', false);
say('  装备整备室「取 防护服」→ ' . mb_substr(implode(' ', (array) $r), 0, 60));
act($S, $ctx, $uid, 'equip', '防护服', false);
walk($S, $ctx, $uid, 'b4_c2', $P);
play($S, $ctx, $uid, '取样', false);
walk($S, $ctx, $uid, 'b2b_hall', $P);
$pc = crrg_drill_pc($uid);
$l = play($S, $ctx, $uid, '交任务', false);
say('  交任务 → ' . mb_substr(implode(' / ', (array) $l), 0, 90));
$pc = crrg_drill_pc($uid);
say(sprintf('  结果：积分 %d ｜ 钱 %d ｜ 完成 %d 项', (int) $pc['credits'], (int) $pc['money'], count((array) $pc['mission_done'])));
if (empty($pc['mission_done'])) prob($P, '训练考核', '走完却没收进 mission_done（或结算失败）');

/* ═══ 3. 日常 ×5 ═══ */
say('── 3. 日常任务 ×5 ──');
$dailies = [
    ['m_dy_watch',   [['mact', 'm_dy_watch|dy1_值', 'night'], ['walk', 'b2b_hall'], ['mact', 'm_dy_watch|dy1_归档', '']]],
    ['m_dy_patrol',  [['walk', 'b3_entry'], ['mact', 'm_dy_patrol|dy2_点1', ''], ['walk', 'b4_main'], ['mact', 'm_dy_patrol|dy2_点2', ''], ['walk', 'b2b_hall'], ['mact', 'm_dy_patrol|dy2_点3', '']]],
    ['m_dy_kitchen', [['walk', 'b1_canteen'], ['mact', 'm_dy_kitchen|dy3_顶班', ''], ['mact', 'm_dy_kitchen|dy3_收灶', '']]],
    ['m_dy_haul',    [['walk', 'garage_maint'], ['mact', 'm_dy_haul|dy4_领货', ''], ['mact', 'm_dy_haul|dy4_交单', '']]],
    ['m_dy_repair',  [['walk', 'b2b_tech'], ['mact', 'm_dy_repair|dy5_接单', ''], ['mact', 'm_dy_repair|dy5_交单', '']]],
];
foreach ($dailies as [$mid, $seq]) {
    ensure_rest($S, $ctx, $uid, $P);
    $before = crrg_drill_pc($uid); $b0 = [(int) $before['credits'], (int) $before['money']];
    $l = play($S, $ctx, $uid, '接受 ' . $mid, false);
    if (mb_strpos(implode(' ', (array) $l), '接不了') !== false || mb_strpos(implode(' ', (array) $l), '完成过') !== false) { prob($P, '日常', $mid . ' 接不上：' . mb_substr(implode(' ', (array) $l), 0, 50)); continue; }
    $restore = null;
    foreach ($seq as $step) { [$k, $id] = [$step[0], $step[1] ?? '']; $opt = $step[2] ?? '';
        if ($k === 'walk') { walk($S, $ctx, $uid, $id, $P); continue; }
        ensure_rest($S, $ctx, $uid, $P);
        if ($opt === 'night') $restore = night(true);
        $r = act($S, $ctx, $uid, $k, $id, false);
        if (mb_strpos(implode(' ', (array) $r), '这不是你手上的活儿') !== false) prob($P, '日常', $mid . '：' . $id . ' 提示"不是你手上的活儿"');
        if (mb_strpos(implode(' ', (array) $r), '夜里') !== false) prob($P, '日常', $mid . '：' . $id . ' 需夜间但当前判断为白天');
    }
    if ($restore) update_option('crrg_drill_clock', $restore, false);
    walk($S, $ctx, $uid, 'b2b_hall', $P);
    $pc = crrg_drill_pc($uid);
    say(sprintf('       [调试] 交任务前 位置=%s ｜ 主线=%s(%d) ｜ 日常=%s(%d)',
        $S['room'], $pc['mission'] ?: '空', (int) ($pc['mission_step'] ?? 0),
        $pc['mission2']['id'] ?? '空', (int) ($pc['mission2']['step'] ?? 0)));
    $l2 = play($S, $ctx, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    $d = [(int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1]];
    say(sprintf('  %-14s → 积分 +%d ｜ 钱 +%d ｜ %s', $mid, $d[0], $d[1], mb_substr(implode(' ', array_slice((array) $l2, 0, 2)), 0, 44)));
    if ($d[0] === 0 && $d[1] === 0) prob($P, '日常', $mid . ' 结算无收益：' . mb_substr(implode(' ', (array) $l2), 0, 60));
    $gain[$mid] = $d;
}

/* ═══ 4. 晋升 D→C ═══ */
say('── 4. 晋升 ──');
$pc = crrg_drill_pc($uid);
say(sprintf('  现在：积分 %d ｜ 卡级 %s', (int) $pc['credits'], crrg_drill_ctx($uid)['card']));
$l = play($S, $ctx, $uid, '晋升', false);
say('  「晋升」→ ' . mb_substr(implode(' / ', (array) $l), 0, 110));
$pc = crrg_drill_pc($uid);
if (crrg_drill_ctx($uid)['card'] === 'D') prob($P, '晋升', '积分已够但仍是 D 级？积分=' . (int) $pc['credits']);

/* ═══ 5. 一次性任务 ×5（需 C 级）═══ */
say('── 5. 一次性任务 ──');
foreach (['m_st_lastwatch', 'm_st_ninth', 'm_st_water', 'm_st_cigs', 'm_st_roster'] as $mid) {
    $pc = crrg_drill_pc($uid);
    if (!empty($pc['mission'])) play($S, $ctx, $uid, '放弃合约', false);
    $l = play($S, $ctx, $uid, '接受 ' . $mid, false);
    $ok = (crrg_drill_pc($uid)['mission'] ?? '') === $mid;
    say(sprintf('  %-16s %s ｜ %s', $mid, $ok ? '接上' : '被拦', mb_substr(implode(' ', array_slice((array) $l, 0, 2)), 0, 56)));
    if (!$ok) { prob($P, '一次性', $mid . ' 接不上：' . mb_substr(implode(' ', (array) $l), 0, 60)); continue; }
    /* 按该任务的当场动作把步骤点完 */
    $acts = function_exists('crrg_base_mission_acts') ? (crrg_base_mission_acts()[$mid] ?? []) : [];
    foreach ($acts as $room => $specs) {
        walk($S, $ctx, $uid, $room, $P);
        foreach ($specs as $sp) {
            $restore = null;
            if (!empty($sp[3])) $restore = night(true);
            act($S, $ctx, $uid, 'mact', $mid . '|' . $sp[1], false);
            if ($restore) { /* 夜间动作完成 */ }
        }
    }
    $pc = crrg_drill_pc($uid);
    $done = (int) ($pc['mission_step'] ?? 0);
    $total = count((array) (crrg_drill_mission($mid)['steps'] ?? []));
    say(sprintf('      进度 %d/%d', $done, $total));
    if ($done < $total) prob($P, '一次性', sprintf('%s 走完全部动作仍只有 %d/%d 步', $mid, $done, $total));
    if ($done >= $total) {
        walk($S, $ctx, $uid, 'b2b_hall', $P);
        $pc = crrg_drill_pc($uid);
        if (!empty($pc['mission'])) { $l2 = play($S, $ctx, $uid, '交任务', false); say('      结算 → ' . mb_substr(implode(' / ', (array) $l2), 0, 70)); }
    }
}

/* ═══ 6. 中型《封条》第一幕 ═══ */
say('── 6. 中型《封条》第一幕 ──');
$pc = crrg_drill_pc($uid);
if (!empty($pc['mission'])) play($S, $ctx, $uid, '放弃合约', false);
$l = play($S, $ctx, $uid, '接受 m_mid_seal', false);
$ok = (crrg_drill_pc($uid)['mission'] ?? '') === 'm_mid_seal';
say('  接单：' . ($ok ? '接上' : '被拦') . ' ｜ ' . mb_substr(implode(' ', array_slice((array) $l, 0, 2)), 0, 60));
if (!$ok) prob($P, '中型', '第一幕接不上：' . mb_substr(implode(' ', (array) $l), 0, 60));
else {
    $spots = ['b3_warehouse' => '封条', 'b4_main' => '封条', 'b4_c2' => '门', 'b4_obs' => '台账', 'b2b_tech' => '配方', 'b1_archive' => '采购'];
    foreach ($spots as $room => $kw) { walk($S, $ctx, $uid, $room, $P); $r = play($S, $ctx, $uid, '调查 ' . $kw, false); say(sprintf('  调查 %s @%s → %s', $kw, $room, mb_substr(implode(' ', array_slice((array) $r, 0, 2)), 0, 52))); }
    foreach ([['b3_warehouse', '周德海 就地取材'], ['b2b_tech', '韩雪 配方'], ['b4_main', '守卫 哪几扇'], ['b1_med', '白大夫 当年']] as [$room, $q]) {
        walk($S, $ctx, $uid, $room, $P); $r = play($S, $ctx, $uid, '细问 ' . $q, false);
        say(sprintf('  细问 %s → %s', $q, mb_substr(implode(' ', array_slice((array) $r, 0, 2)), 0, 48)));
    }
    walk($S, $ctx, $uid, 'b2b_hall', $P);
    act($S, $ctx, $uid, 'stagechoice', '压下', false);
    $pc = crrg_drill_pc($uid);
    say(sprintf('  决断后进度 %d/%d ｜ 封条库存 %s 天', (int) $pc['mission_step'],
        count((array) crrg_drill_mission('m_mid_seal')['steps']), $pc['seal']['stock_days'] ?? '?'));
    $l2 = play($S, $ctx, $uid, '交任务', false);
    say('  结算 → ' . mb_substr(implode(' / ', (array) $l2), 0, 80));
}

/* ═══ 7. 其它系统逐个试 ═══ */
say('── 7. 系统抽查 ──');
$pc = crrg_drill_pc($uid);
/* 商店 */
walk($S, $ctx, $uid, 'b3_warehouse', $P);
$shop = null;
foreach (crrg_drill_panels($S, crrg_drill_ctx($uid), $pc, $uid) as $pp) if (mb_strpos($pp['title'], '商店') === 0) $shop = $pp;
if ($shop && !empty($shop['items'])) {
    $first = $shop['items'][0];
    $r = act($S, $ctx, $uid, $first['k'], $first['id'] ?? '', false);
    say('  商店买「' . mb_substr($first['label'], 0, 16) . '」→ ' . mb_substr(implode(' ', (array) $r), 0, 60));
} else prob($P, '商店', '基地商超没有可买的条目');
/* 装备 */
$eq = act($S, $ctx, $uid, 'equip', '防护服', false);
say('  穿戴 防护服 → ' . mb_substr(implode(' ', (array) $eq), 0, 52));
/* 背包/状态 */
foreach (['背包', '状态', '分类 食品'] as $c) { $r = play($S, $ctx, $uid, $c, false); say(sprintf('  %s → %s', $c, mb_substr(implode(' / ', array_slice((array) $r, 0, 2)), 0, 54))); }
/* 鉴定 */
play($S, $ctx, $uid, '拿 封条', false);
walk($S, $ctx, $uid, 'b4_obs', $P);
$ap = act($S, $ctx, $uid, 'appraise', 'key_sigil', false);
say('  鉴定（观察室）→ ' . mb_substr(implode(' ', (array) $ap), 0, 60));
/* 宿舍睡觉 */
walk($S, $ctx, $uid, 'b1_room', $P);
$r = play($S, $ctx, $uid, '睡觉', false);
say('  宿舍「睡觉」→ ' . mb_substr(implode(' / ', (array) $r), 0, 60));
$r = play($S, $ctx, $uid, '起床', false);
say('  「起床」→ ' . mb_substr(implode(' / ', (array) $r), 0, 50));
/* 医疗室 */
walk($S, $ctx, $uid, 'b1_med', $P);
$r = play($S, $ctx, $uid, '治伤', false);
say('  医疗室「治伤」→ ' . mb_substr(implode(' / ', (array) $r), 0, 60));
/* 长途去镇上（要车或走路）*/
walk($S, $ctx, $uid, 'b1_tunnel', $P);
$r = play($S, $ctx, $uid, '长途 青石镇', false);
say('  「长途 青石镇」→ ' . mb_substr(implode(' / ', (array) $r), 0, 70));

/* ═══ 报告 ═══ */
say('');
say('════════ 全面真玩报告 ════════');
$pc = crrg_drill_pc($uid);
say(sprintf('结束：卡级 %s ｜ 积分 %d ｜ 钱 %d ｜ 完成 %d 项 ｜ 问题 %d 条',
    crrg_drill_ctx($uid)['card'], (int) $pc['credits'], (int) $pc['money'], count((array) $pc['mission_done']), count($P)));
foreach ($P as $i => $x) say('  ' . ($i + 1) . '. ' . $x);
if (!$P) say('  （无）');
delete_user_meta($uid, 'crrg_drill_pc'); delete_user_meta($uid, 'crrg_drill_state');
wp_delete_user($uid);
crrg_drill_set_scene('base');
