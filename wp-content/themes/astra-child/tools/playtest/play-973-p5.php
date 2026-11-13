<?php
/* #973 河洛 · 通关记录（第五期）：进镇 → 第二段收尾+结算 → 第三段（镇上）→ 接第四段 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('region');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$problems = [];
$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'r_qingshi', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function cmd(&$S, $uid, $c, $max = 2, $quiet = false) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, $max) as $x) echo "      " . mb_strimwidth((string) $x, 0, 86, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '', $quiet = false) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  [按钮] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 76, '…') . "　"; echo "\n"; }
    return $r;
}
function walk(&$S, $uid, $to, $enterTown = false) {
    for ($i = 0; $i < 40; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) {
            /* 目标不在本场景：若目标是镇上的房间，先找"进镇"的出口 */
            $exits = (array) ($rooms[$S['room'] ?? ''] ?? [])['x'] ?? [];
            $done = false;
            foreach ($exits as $lb => $dest) { if (strpos($dest, 'tw_') === 0) { cmd($S, $uid, '去 ' . $lb, 0, true); $done = true; break; } }
            if (!$done) { echo "      （进不去：$to；当前 " . crrg_drill_cur_scene() . "，出口 " . implode('、', array_keys($exits)) . "）\n"; return false; }
            continue;
        }
        $hint = crrg_drill_route_hint($S['room'] ?? '', $to);
        if ($hint === '' || mb_strpos($hint, '（') === 0) { echo "      （没路线：$hint）\n"; return false; }
        cmd($S, $uid, '去 ' . explode(' → ', $hint)[0], 0, true);
    }
    return ($S['room'] ?? '') === $to;
}
function rest_if_tired(&$S, $uid) {
    $pc = crrg_drill_pc($uid);
    if ((float) ($pc['vitals']['energy'] ?? 100) >= 40) return;
    cmd($S, $uid, '睡觉 8', 1, true);
    $c = crrg_world_clock(); crrg_world_set_game($c['ts'] + 8 * 3600, '通关记录·睡眠');
    cmd($S, $uid, '起床', 1, true);
}
function solve(&$S, $uid, $room, $kw, $node, $flag, &$problems, $tries = 2) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= $tries; $i++) {
        rest_if_tired($S, $uid);
        cmd($S, $uid, '调查 ' . $kw, 2, true);
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ 调查 %-4s\n", $kw); return true; }
    }
    rest_if_tired($S, $uid);
    cmd($S, $uid, '换法 ' . $node . ' 1', 2, true);
    if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ 换法 %-10s（兜底）\n", $node); return true; }
    $problems[] = "$node 没过"; printf("  ✗ %s\n", $node); return false;
}
function night(&$S, $uid) {
    $c = crrg_world_clock();
    $t = (new DateTime(crrg_wdate('Y-m-d', $c['ts']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
    if ($t > $c['ts']) crrg_world_set_game($t, '通关记录·入夜');
}

echo "=== 第五期：进镇 → 第二段收尾 → 第三段 ===\n";
$pc = crrg_drill_pc($uid);
printf("起点：场景=%s 房间=%s ｜ 在手=%s 进度 %d\n\n", crrg_drill_cur_scene(), $S['room'], $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));

echo "── ① 进镇 ──\n";
walk($S, $uid, 'tw_main_w');
printf("  场景=%s ｜ 位置=%s\n\n", crrg_drill_cur_scene(), crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']);

echo "── ② 中毒事件（路上触发的）──\n";
if (in_array('s3_中毒事件', (array) $S['flags'], true) && !in_array('s3_中毒处理', (array) $S['flags'], true)) {
    walk($S, $uid, 'tw_paper');
    btn($S, $uid, 'poison', '送医');
} else { echo "  （未触发或已处理）\n"; }

echo "\n── ③ 第二段收尾：朱砂土 ＋ 三个问人 ＋ 决断 ──\n";
solve($S, $uid, 'tw_tomb', '土', '土', 'paper_朱砂土', $problems);
foreach ([['tw_paper','常守业 旧库管'], ['tw_junk','孙德发 生面孔'], ['tw_farm','赵铁 同一个人']] as [$room, $q]) {
    walk($S, $uid, $room); rest_if_tired($S, $uid);
    cmd($S, $uid, '细问 ' . $q, 1, true);
}
$pc = crrg_drill_pc($uid);
printf("  进度 %d／%d ｜ 集齐 %s\n", (int) $pc['mission_step'], count((array) crrg_drill_mission_any('m_mid_seal2')['steps']),
    in_array('paper_集齐', (array) $S['flags'], true) ? '✓' : '×');
walk($S, $uid, 'tw_townhall');
btn($S, $uid, 'stagechoice', '带回');
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', 4);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ **在手：%s**\n\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
if (($pc['mission'] ?? '') === 'm_mid_seal3') echo "  ✓ 自动接上第三段\n\n"; else $problems[] = '第二段没接上第三段';

echo "── ④ 第三段：邻居／徒弟账／摊牌／暗账／守夜 ──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal3') {
    solve($S, $uid, 'tw_homes', '邻居', '邻居', 's3_邻居', $problems);
    walk($S, $uid, 'tw_junk'); cmd($S, $uid, '细问 孙德发 徒弟', 1, true);
    walk($S, $uid, 'tw_paper'); cmd($S, $uid, '细问 常守业 徒弟', 1, true);
    /* 守夜（夜间）遇徒弟 → 拿账 */
    night($S, $uid);
    walk($S, $uid, 'tw_hillpath');
    cmd($S, $uid, '守夜', 3);
    if (in_array('s3_遇徒弟', (array) $S['flags'], true)) { cmd($S, $uid, '细问 田小满 账', 2, true); }
    else { solve($S, $uid, 'tw_hillpath', '车辙', '车辙', 's3_车辙', $problems); }
    /* 信用社暗账（已有 paper_绕过 → 可查） */
    solve($S, $uid, 'tw_bank', '流水', '流水', 's3_暗账', $problems);
    /* 摊牌 */
    if (in_array('s3_遇冒充者', (array) $S['flags'], true)) {
        walk($S, $uid, 'tw_main_c'); btn($S, $uid, 'parley', '对峙');
    } else { echo "  （本次守夜没遇到冒充者——摊牌留待下次）\n"; }
    $pc = crrg_drill_pc($uid);
    printf("\n  第三段进度 %d／%d ｜ 旗标：%s\n", (int) $pc['mission_step'], count((array) crrg_drill_mission_any('m_mid_seal3')['steps']),
        implode('、', array_slice(preg_grep('/^s3_/', (array) $S['flags']), 0, 10)));
}
echo "\n【卡点】" . ($problems ? implode('；', $problems) : '无') . "\n";
