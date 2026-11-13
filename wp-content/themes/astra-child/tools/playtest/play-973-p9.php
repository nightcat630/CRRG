<?php
/* #973 河洛 · 通关记录（第九期·终局）：走完车库那一步 → 第四段结算 → 第五段 → 结案 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$pc0 = crrg_drill_pc($uid);
crrg_drill_set_scene($pc0['scene'] ?? 'base');
$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'b1_tunnel', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function cmd(&$S, $uid, $c, $quiet = true) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, 3) as $x) echo "      " . mb_strimwidth((string) $x, 0, 88, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '', $quiet = false) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  [按钮 " . $k . ($id ? ':' . $id : '') . "] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 76, '…') . "　"; echo "\n"; }
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 40; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { echo "      （进不去 $to）\n"; return false; }
        if (($S['room'] ?? '') === 'b4_buffer' && $to !== 'b4_buffer') { for ($k = 0; $k < 3; $k++) cmd($S, $uid, '刷卡'); continue; }
        $hint = crrg_drill_route_hint($S['room'] ?? '', $to);
        if ($hint === '' || mb_strpos($hint, '（') === 0) { echo "      （没路线：$hint）\n"; return false; }
        cmd($S, $uid, '去 ' . explode(' → ', $hint)[0]);
    }
    return ($S['room'] ?? '') === $to;
}
function rest(&$S, $uid) {
    $pc = crrg_drill_pc($uid);
    if ((float) ($pc['vitals']['energy'] ?? 100) >= 40) return;
    cmd($S, $uid, '睡觉 8');
    $c = crrg_world_clock(); crrg_world_set_game($c['ts'] + 8 * 3600, '通关记录·睡眠');
    cmd($S, $uid, '起床');
}

echo "=== 终局 ===\n";
$pc = crrg_drill_pc($uid);
printf("在手：%s ｜ 进度 %d／%d\n\n", $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0), count((array) crrg_drill_mission_any($pc['mission'] ?: 'm_mid_seal4')['steps']));

echo "── 第四段：补走「回车库」那一步 ──\n";
walk($S, $uid, 'garage_maint'); rest($S, $uid);
$pc = crrg_drill_pc($uid);
printf("  到车库后进度 %d／%d\n", (int) $pc['mission_step'], count((array) crrg_drill_mission_any('m_mid_seal4')['steps']));
walk($S, $uid, 'b2b_hall');
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', false);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');

echo "── 第五段：开工 → 贴封 → 结案 ──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal5') {
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'seal5run', '');
    walk($S, $uid, 'b4_c2');    btn($S, $uid, 'seal5seal', '');
    walk($S, $uid, 'b2b_hall'); btn($S, $uid, 'seal5close', '');
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
} else { printf("  （当前在手：%s）\n", $pc['mission'] ?: '（空）'); }

echo "\n════ 通关结算 ════\n";
$pc = crrg_drill_pc($uid);
$doneList = (array) $pc['mission_done'];
$chain = ['m_mid_seal' => '第一段', 'm_mid_seal2' => '第二段', 'm_mid_seal3' => '第三段', 'm_mid_seal4' => '第四段', 'm_mid_seal5' => '第五段'];
$got = [];
foreach ($chain as $cid => $nm) if (in_array($cid, $doneList, true)) $got[] = $nm;
printf("《封条》完成段落：%s\n", $got ? implode('、', $got) : '（无）');
printf("卡级 %s ｜ 演习权限 %s ｜ 积分 %d ｜ 钱 %d ｜ 完成合约共 %d 项\n", crrg_drill_ctx($uid)['card'],
    crrg_drill_clearance_name(max((int) ($pc['clearance'] ?? 0), (int) crrg_drill_ctx($uid)['lv'])),
    (int) $pc['credits'], (int) $pc['money'], count($doneList));
if (in_array('m_mid_seal5', $doneList, true)) echo "★ **《封条》全线通关（含结案报告）**\n";
