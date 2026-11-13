<?php
/* #973 河洛 · 通关记录（重跑·上）：第一段 → 自动接力 → 镇上第二段前四条线 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('base');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$problems = [];

/* 只重置本号的《封条》进度（自己的测试号） */
$pc = crrg_drill_pc($uid);
$pc['mission'] = ''; $pc['mission_step'] = 0;
$pc['mission_done'] = array_values(array_diff((array) ($pc['mission_done'] ?? []), ['m_mid_seal','m_mid_seal2','m_mid_seal3','m_mid_seal4','m_mid_seal5']));
$pc['seal'] = null;
crrg_drill_pc_save($uid, $pc);
$st = crrg_drill_state($uid);
$flags = array_values(array_filter((array) ($st['flags'] ?? []), fn($f) => strpos($f, 'seal_') !== 0 && strpos($f, 'pending_') !== 0));
$st['flags'] = $flags; crrg_drill_save($uid, $st);
$S = ['room' => $st['room'] ?? 'b1_tunnel', 'items' => [], 'flags' => $flags, 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function cmd(&$S, $uid, $c, $max = 3, $quiet = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, $max) as $x) echo "      " . mb_strimwidth((string) $x, 0, 90, '…') . "\n"; }
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
        if (!isset($rooms[$to])) { echo "      （目标不在本场景：$to）\n"; return false; }
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
    walk($S, $uid, 'b1_room'); cmd($S, $uid, '睡觉 8', 1);
    $c = crrg_world_clock(); crrg_world_set_game($c['ts'] + 8 * 3600, '通关记录·睡眠');
    cmd($S, $uid, '起床', 1);
}
function solve(&$S, $uid, $room, $kw, $node, $flag, &$problems, $tries = 2) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= $tries; $i++) {
        rest_if_tired($S, $uid);
        $l = cmd($S, $uid, '调查 ' . $kw, 2, true);
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  > 调查 %-6s → 成功（第 %d 次）\n", $kw, $i); return true; }
    }
    rest_if_tired($S, $uid);
    $l = cmd($S, $uid, '换法 ' . $node . ' 1', 2, true);
    if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  > 换法 %-10s（兜底）→ 成功\n", $node); return true; }
    $problems[] = "$node 没过"; printf("  > 换法 %-10s → 失败\n", $node);
    return false;
}

echo "=== 通关记录（重跑·上）===\n";
printf("起点：%s 级 ｜ 积分 %d ｜ 钱 %d ｜ 已重置《封条》进度\n\n", crrg_drill_ctx($uid)['card'], (int) $pc['credits'], (int) $pc['money']);

echo "── 第一段 · 接单 ──\n";
walk($S, $uid, 'b2f_gear');
cmd($S, $uid, '取 防护服', 1, true);
cmd($S, $uid, '穿戴 防护服', 1, true);
walk($S, $uid, 'b2b_hall');
cmd($S, $uid, '接受 m_mid_seal', 3);
$pc = crrg_drill_pc($uid);
printf("  → 在手 %s ｜ 进度 %d／%d ｜ 封条库存 %s 天\n\n", $pc['mission'], (int) $pc['mission_step'],
    count((array) crrg_drill_mission_any('m_mid_seal')['steps']), $pc['seal']['stock_days'] ?? '?');

echo "── 第一段 · 六调查 ＋ 四问人 ──\n";
foreach ([['b3_warehouse','封条','封条','seal_库存盘点'], ['b4_main','封条','门况复核','seal_门况普查'],
          ['b4_c2','门','私补','seal_私补痕迹'], ['b4_obs','台账','台账','seal_用量台账'],
          ['b2b_tech','配方','配方比样','seal_自制配方'], ['b1_archive','采购','采购','seal_三年档案']] as [$room, $kw, $node, $flag]) {
    solve($S, $uid, $room, $kw, $node, $flag, $problems);
}
foreach ([['b3_warehouse','周德海 就地取材'], ['b2b_tech','韩雪 配方'], ['b4_main','守卫 哪几扇'], ['b1_med','白大夫 当年']] as [$room, $q]) {
    walk($S, $uid, $room); rest_if_tired($S, $uid);
    cmd($S, $uid, '细问 ' . $q, 1, true);
}
$pc = crrg_drill_pc($uid);
printf("  线索：%s\n  进度 %d／%d\n", implode('、', preg_grep('/^seal_(库存|门况|私补|用量|自制|三年|来源)/', (array) $S['flags'])),
    (int) $pc['mission_step'], count((array) crrg_drill_mission_any('m_mid_seal')['steps']));

echo "\n── 第一段 · 决断＋结算（看接力）──\n";
walk($S, $uid, 'b2b_hall');
btn($S, $uid, 'stagechoice', '压下');
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', 5);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ **在手：%s**\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
if (($pc['mission'] ?? '') === 'm_mid_seal2') echo "  ✓ 自动接力成功\n"; else $problems[] = '接力未生效';

echo "\n── 第二段 · 长途去青石镇 ──\n";
cmd($S, $uid, '长途 青石镇', 2, true);
$pc = crrg_drill_pc($uid);
if (!empty($pc['travel'])) { $pc['travel']['arrive'] = crrg_vitals_now() - 5; crrg_drill_pc_save($uid, $pc); }
crrg_drill_set_scene('town');
$S['room'] = 'tw_main_w'; crrg_drill_save($uid, $S);
echo "  已到 " . (crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']) . "\n";

echo "\n── 第二段 · 镇上四条线 ──\n";
foreach ([['tw_paper','配方','纸人','paper_配方'], ['tw_farm','电瓶','电瓶','paper_铅箔'],
          ['tw_clinic','中毒','中毒','paper_中毒'], ['tw_tomb','土','土','paper_朱砂土']] as [$room, $kw, $node, $flag]) {
    solve($S, $uid, $room, $kw, $node, $flag, $problems);
}
walk($S, $uid, 'tw_townhall');
cmd($S, $uid, '细问 镇文书 绕过', 1, true);
solve($S, $uid, 'tw_townhall', '手续', '手续', 'paper_通行', $problems);
$pc = crrg_drill_pc($uid);
printf("\n  进度 %d／%d ｜ 镇上线：配方%s 铅皮%s 中毒%s 朱砂土%s 手续%s ｜ 集齐%s\n", (int) $pc['mission_step'],
    count((array) crrg_drill_mission_any('m_mid_seal2')['steps']),
    in_array('paper_配方', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_铅箔', (array) $S['flags'], true) ? '✓' : '×',
    in_array('paper_中毒', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_朱砂土', (array) $S['flags'], true) ? '✓' : '×',
    in_array('paper_通行', (array) $S['flags'], true) ? '✓' : '×', in_array('paper_集齐', (array) $S['flags'], true) ? '✓' : '×');
echo "\n【卡点】" . ($problems ? implode('；', $problems) : '无') . "\n";
