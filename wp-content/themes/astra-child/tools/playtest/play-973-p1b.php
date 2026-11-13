<?php
/* #973 河洛 · 玩家视角通关记录（第一期补完）：当班值守 → 晋升 C */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('base');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });

$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'b1_tunnel', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
function cmd(&$S, $uid, $c, $max = 3) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    echo "  > " . $c . "\n";
    foreach (array_slice((array) $l, 0, $max) as $x) echo "      " . mb_strimwidth((string) $x, 0, 96, '…') . "\n";
    return $l;
}
function btn(&$S, $uid, $k, $id = '') {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    echo "  [按钮 " . $k . ($id ? ":$id" : "") . "]\n";
    foreach (array_slice((array) $r, 0, 2) as $x) echo "      " . mb_strimwidth((string) $x, 0, 90, '…') . "\n";
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 30; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { echo "      （目标不在本场景）\n"; return false; }
        $hint = crrg_drill_route_hint($S['room'] ?? '', $to);
        if ($hint === '' || mb_strpos($hint, '（') === 0) { echo "      （没路线：$hint）\n"; return false; }
        cmd($S, $uid, '去 ' . explode(' → ', $hint)[0], 0);
    }
    return ($S['room'] ?? '') === $to;
}

echo "=== 第一期补完：当班值守（夜间）→ 晋升 ===\n\n";
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
echo "── 接日常「当班值守」──\n";
cmd($S, $uid, '接受 m_dy_watch', 2);
walk($S, $uid, 'b2b_duty');
// 拨到夜里（只向前）
$c = crrg_world_clock();
$t = (new DateTime(crrg_wdate('Y-m-d', $c['ts']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
if ($t > $c['ts']) crrg_world_set_game($t, '通关记录·入夜');
echo "  （天色：" . crrg_world_clock()['phase'] . "）\n";
btn($S, $uid, 'mact', 'm_dy_watch|dy1_值');
walk($S, $uid, 'b2b_hall');
btn($S, $uid, 'mact', 'm_dy_watch|dy1_归档');
cmd($S, $uid, '交任务', 3);
$pc = crrg_drill_pc($uid);
printf("  → 当班值守：+%d 分 / +%d 元（现有 %d 分）\n\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], (int) $pc['credits']);

echo "── 晋升 ──\n";
cmd($S, $uid, '晋升', 3);
$pc = crrg_drill_pc($uid);
printf("\n第一期完成：%s 级 ｜ 积分 %d ｜ 钱 %d ｜ 可用：一次性任务与《封条》\n", crrg_drill_ctx($uid)['card'], (int) $pc['credits'], (int) $pc['money']);
