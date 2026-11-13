<?php
/* #973 河洛 · 玩家视角通关记录（第一期）：车卡 → 日常×5 → 晋升 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('base');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });

$S = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
$st = crrg_drill_state($uid);
if (!empty($st['room'])) $S['room'] = $st['room'];
$S['flags'] = (array) ($st['flags'] ?? []);

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
    foreach (array_slice((array) $r, 0, 3) as $x) echo "      " . mb_strimwidth((string) $x, 0, 90, '…') . "\n";
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 30; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { echo "      （目标不在本场景：$to）\n"; return false; }
        $hint = crrg_drill_route_hint($S['room'] ?? '', $to);
        if ($hint === '' || mb_strpos($hint, '（') === 0) { echo "      （没路线：{$S['room']} → $to：$hint）\n"; return false; }
        $lb = explode(' → ', $hint)[0];
        cmd($S, $uid, '去 ' . $lb, 0);
        echo "      · 到 " . ($rooms[$S['room']]['t'] ?? $S['room']) . "\n";
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

echo "=== 第一期：#973 河洛，从零开始 ===\n";
echo "初始：" . crrg_drill_ctx($uid)['card'] . " 级 ｜ 积分 " . (int) crrg_drill_pc($uid)['credits'] . " ｜ 钱 " . (int) crrg_drill_pc($uid)['money'] . "\n\n";

echo "── 车卡 ──\n";
foreach (['掷骰', '选职业 情报分析员', '自动分配', '完成'] as $c) { cmd($S, $uid, $c, 2); }
$pc = crrg_drill_pc($uid); $pc['scene'] = 'base'; crrg_drill_pc_save($uid, $pc);
printf("  卡：%s ｜ 关键技能：侦查 %d／图书馆使用 %d／会计 %d／说服 %d ｜ 钱 %d\n\n",
    crrg_drill_ctx($uid)['card'], crrg_coc_skill_val($pc, '侦查'), crrg_coc_skill_val($pc, '图书馆使用'),
    crrg_coc_skill_val($pc, '会计'), crrg_coc_skill_val($pc, '说服'), (int) $pc['money']);

echo "── 一轮日常（五条）──\n";
$plan = [
  'm_dy_patrol'  => [['walk','b3_entry'],['mact','m_dy_patrol|dy2_点1'],['walk','b4_main'],['mact','m_dy_patrol|dy2_点2'],['walk','b2b_hall'],['mact','m_dy_patrol|dy2_点3']],
  'm_dy_kitchen' => [['walk','b1_canteen'],['mact','m_dy_kitchen|dy3_顶班'],['mact','m_dy_kitchen|dy3_收灶']],
  'm_dy_haul'    => [['walk','garage_maint'],['mact','m_dy_haul|dy4_领货'],['mact','m_dy_haul|dy4_交单']],
  'm_dy_repair'  => [['walk','b2b_tech'],['mact','m_dy_repair|dy5_接单'],['mact','m_dy_repair|dy5_交单']],
];
foreach ($plan as $mid => $steps) {
    rest_if_tired($S, $uid);
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    cmd($S, $uid, '接受 ' . $mid, 1);
    foreach ($steps as [$k, $v]) {
        if ($k === 'walk') { walk($S, $uid, $v); rest_if_tired($S, $uid); }
        else btn($S, $uid, 'mact', $v);
    }
    walk($S, $uid, 'b2b_hall');
    cmd($S, $uid, '交任务', 2);
    $pc = crrg_drill_pc($uid);
    printf("  → %s：+%d 分 / +%d 元（现有 %d 分）\n\n", $mid, (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], (int) $pc['credits']);
}
echo "── 晋升 ──\n";
cmd($S, $uid, '晋升', 3);
$pc = crrg_drill_pc($uid);
printf("\n第一期结束：%s 级 ｜ 积分 %d ｜ 钱 %d ｜ 背包 %d 件\n", crrg_drill_ctx($uid)['card'], (int) $pc['credits'], (int) $pc['money'], count((array) $pc['items']));
