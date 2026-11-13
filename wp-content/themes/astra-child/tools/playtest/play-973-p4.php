<?php
/* #973 河洛 · 通关记录（第四期）：第二段（镇上五条线）→ 结算 → 接第三段 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('base');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$problems = [];
$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'b1_tunnel', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function cmd(&$S, $uid, $c, $max = 3, $quiet = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, $max) as $x) echo "      " . mb_strimwidth((string) $x, 0, 88, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '') {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    echo "  [按钮] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 78, '…') . "　"; echo "\n";
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 40; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { echo "      （不在本场景：$to／当前 " . crrg_drill_cur_scene() . "）\n"; return false; }
        if (($S['room'] ?? '') === 'b4_buffer' && $to !== 'b4_buffer') { for ($k = 0; $k < 3; $k++) cmd($S, $uid, '刷卡', 1, true); continue; }
        $hint = crrg_drill_route_hint($S['room'] ?? '', $to);
        if ($hint === '' || mb_strpos($hint, '（') === 0) { echo "      （没路线：$hint）\n"; return false; }
        cmd($S, $uid, '去 ' . explode(' → ', $hint)[0], 0, true);
    }
    return ($S['room'] ?? '') === $to;
}
function rest_if_tired(&$S, $uid) {
    $pc = crrg_drill_pc($uid);
    if ((float) ($pc['vitals']['energy'] ?? 100) >= 40) return;
    if (crrg_drill_cur_scene() === 'base') { walk($S, $uid, 'b1_room'); cmd($S, $uid, '睡觉 8', 1); }
    else { cmd($S, $uid, '睡觉 8', 1); }
    $c = crrg_world_clock(); crrg_world_set_game($c['ts'] + 8 * 3600, '通关记录·睡眠');
    cmd($S, $uid, '起床', 1);
}
function solve(&$S, $uid, $room, $kw, $node, $flag, &$problems, $tries = 2) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= $tries; $i++) {
        rest_if_tired($S, $uid);
        cmd($S, $uid, '调查 ' . $kw, 2, true);
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ 调查 %-4s（第 %d 次）\n", $kw, $i); return true; }
    }
    rest_if_tired($S, $uid);
    cmd($S, $uid, '换法 ' . $node . ' 1', 2, true);
    if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ 换法 %-8s（兜底）\n", $node); return true; }
    $problems[] = "$node 没过"; printf("  ✗ %s 没过\n", $node); return false;
}

echo "=== 第四期：第二段（镇上）===\n";
$pc = crrg_drill_pc($uid);
printf("在手：%s ｜ 进度 %d\n\n", $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));

echo "── ① 先回基地、再去青石镇（走长途）──\n";
if (crrg_drill_cur_scene() !== 'base') { cmd($S, $uid, '撤离', 1, true); }
cmd($S, $uid, '长途 青石镇', 2, true);
$pc = crrg_drill_pc($uid);
if (!empty($pc['travel'])) { $pc['travel']['arrive'] = crrg_vitals_now() - 5; crrg_drill_pc_save($uid, $pc); }
cmd($S, $uid, '查看', 1, true);
printf("  场景＝%s ｜ 位置＝%s\n\n", crrg_drill_cur_scene(), crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']);

echo "── ② 五条线 ──\n";
foreach ([['tw_paper','配方','纸人','paper_配方'], ['tw_farm','电瓶','电瓶','paper_铅箔'],
          ['tw_clinic','中毒','中毒','paper_中毒'], ['tw_tomb','土','土','paper_朱砂土']] as [$room, $kw, $node, $flag]) {
    solve($S, $uid, $room, $kw, $node, $flag, $problems);
}
walk($S, $uid, 'tw_townhall');
cmd($S, $uid, '细问 镇文书 绕过', 1, true);
solve($S, $uid, 'tw_townhall', '手续', '手续', 'paper_通行', $problems);

echo "\n── ③ 三个问人 ──\n";
foreach ([['tw_paper','常守业 旧库管'], ['tw_junk','孙德发 生面孔'], ['tw_farm','赵铁 同一个人']] as [$room, $q]) {
    walk($S, $uid, $room); rest_if_tired($S, $uid);
    cmd($S, $uid, '细问 ' . $q, 1, true);
}
$pc = crrg_drill_pc($uid);
printf("\n  进度 %d／%d ｜ 配方%s 铅皮%s 中毒%s 朱砂土%s 手续%s ｜ 集齐%s\n", (int) $pc['mission_step'],
    count((array) crrg_drill_mission_any('m_mid_seal2')['steps']),
    in_array('paper_配方', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_铅箔', (array) $S['flags'], true) ? '✓' : '×',
    in_array('paper_中毒', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_朱砂土', (array) $S['flags'], true) ? '✓' : '×',
    in_array('paper_通行', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_集齐', (array) $S['flags'], true) ? '✓' : '×');

echo "\n── ④ 决断（带回基地）＋结算 ──\n";
walk($S, $uid, 'tw_townhall');
btn($S, $uid, 'stagechoice', '带回');
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', 4);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ **在手：%s**\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
if (($pc['mission'] ?? '') === 'm_mid_seal3') echo "  ✓ 自动接上第三段\n"; else $problems[] = '接力没接上第三段';
echo "\n【卡点】" . ($problems ? implode('；', $problems) : '无') . "\n";
