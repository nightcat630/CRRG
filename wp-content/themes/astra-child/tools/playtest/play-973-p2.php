<?php
/* #973 河洛 · 玩家视角通关记录（第二期）：中型《封条》第一段 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('base');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });

$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'b1_tunnel', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
$problems = [];
function cmd(&$S, $uid, $c, $max = 3, $quiet = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, $max) as $x) echo "      " . mb_strimwidth((string) $x, 0, 92, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '') {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    echo "  [按钮] "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 86, '…') . "　";
    echo "\n";
    return $r;
}
function walk(&$S, $uid, $to, $show = false) {
    for ($i = 0; $i < 30; $i++) {
        if (($S['room'] ?? '') === $to) { if ($show) echo "      → 到了 " . (crrg_drill_rooms()[$to]['t'] ?? $to) . "\n"; return true; }
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { echo "      （目标不在本场景）\n"; return false; }
        $hint = crrg_drill_route_hint($S['room'] ?? '', $to);
        if ($hint === '' || mb_strpos($hint, '（') === 0) { echo "      （没路线：$hint）\n"; return false; }
        cmd($S, $uid, '去 ' . explode(' → ', $hint)[0], 0, true);
    }
    return ($S['room'] ?? '') === $to;
}
function rest_if_tired(&$S, $uid) {
    $pc = crrg_drill_pc($uid);
    if ((float) ($pc['vitals']['energy'] ?? 100) >= 40) return;
    walk($S, $uid, 'b1_room');
    cmd($S, $uid, '睡觉 8', 1);
    $c = crrg_world_clock(); crrg_world_set_game($c['ts'] + 8 * 3600, '通关记录·睡眠');
    cmd($S, $uid, '起床', 1);
}
/* 调查类：失败可再来（玩家就是这么干的） */
function probe(&$S, $uid, $room, $kw, $flag, $tries = 6) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= $tries; $i++) {
        rest_if_tired($S, $uid);
        $l = cmd($S, $uid, '调查 ' . $kw, 2, true);
        $t = implode(' ', (array) $l);
        printf("  > 调查 %s（第 %d 次）→ %s\n", $kw, $i, mb_strimwidth(trim(preg_replace('/\s+/', ' ', $t)), 0, 78, '…'));
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) return true;
    }
    return false;
}

echo "=== 第二期：中型《封条》第一段 ===\n";
printf("入场：%s 级 ｜ 积分 %d ｜ 钱 %d ｜ 封条库存 %s 天\n\n", crrg_drill_ctx($uid)['card'],
    (int) crrg_drill_pc($uid)['credits'], (int) crrg_drill_pc($uid)['money'],
    crrg_seal_inventory(crrg_drill_pc($uid))['stock_days'] ?? '?');

echo "── ① 领装备（进负四层要穿 T3）──\n";
walk($S, $uid, 'b2f_gear');
cmd($S, $uid, '取 防护服', 1);
cmd($S, $uid, '穿戴 防护服', 1);

echo "\n── ② 接《封条》（看开场）──\n";
walk($S, $uid, 'b2b_hall');
$l = cmd($S, $uid, '接受 m_mid_seal', 12);
$pc = crrg_drill_pc($uid);
printf("  → 在手：%s ｜ 进度 %d／%d\n\n", $pc['mission'] ?: '（空）', (int) $pc['mission_step'],
    count((array) crrg_drill_mission('m_mid_seal')['steps']));

echo "── ③ 六个调查点 ──\n";
$spots = [['b3_warehouse','封条','seal_库存盘点'], ['b4_main','封条','seal_门况普查'], ['b4_c2','门','seal_私补痕迹'],
          ['b4_obs','台账','seal_用量台账'], ['b2b_tech','配方','seal_自制配方'], ['b1_archive','采购','seal_三年档案']];
foreach ($spots as [$room, $kw, $flag]) {
    $ok = probe($S, $uid, $room, $kw, $flag);
    if (!$ok) $problems[] = "调查 $kw 未拿到 $flag";
}
echo "\n── ④ 四个问人 ──\n";
foreach ([['b3_warehouse','周德海 就地取材'], ['b2b_tech','韩雪 配方'], ['b4_main','守卫 哪几扇'], ['b1_med','白大夫 当年']] as [$room, $q]) {
    walk($S, $uid, $room); rest_if_tired($S, $uid);
    $l = cmd($S, $uid, '细问 ' . $q, 2, true);
    printf("  > 细问 %s → %s\n", $q, mb_strimwidth(trim(preg_replace('/\s+/', ' ', implode(' ', (array) $l))), 0, 76, '…'));
}
$pc = crrg_drill_pc($uid);
printf("\n  线索旗标：%s\n  进度 %d／%d\n", implode('、', preg_grep('/^seal_(库存|门况|私补|用量|自制|三年|来源)/', (array) $S['flags'])),
    (int) $pc['mission_step'], count((array) crrg_drill_mission('m_mid_seal')['steps']));

echo "\n── ⑤ 决断 ──\n";
walk($S, $uid, 'b2b_hall');
btn($S, $uid, 'stagechoice', '压下');
$pc = crrg_drill_pc($uid);
printf("  进度 %d／%d ｜ 封条库存 %s 天 ｜ 路线旗标 %s\n", (int) $pc['mission_step'],
    count((array) crrg_drill_mission('m_mid_seal')['steps']), $pc['seal']['stock_days'] ?? '?',
    implode('、', preg_grep('/^seal_路线/', (array) $S['flags'])));

echo "\n── ⑥ 结算（应自动接第二段）──\n";
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', 6);
$pc = crrg_drill_pc($uid);
printf("  → 收益 +%d 分 / +%d 元 ｜ **自动接手：%s**（进度 %d）\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1],
    $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));
if (($pc['mission'] ?? '') !== 'm_mid_seal2') $problems[] = '第一段结算后没有自动接上第二段';
echo "\n【卡点】" . ($problems ? implode('；', $problems) : "无\n");
