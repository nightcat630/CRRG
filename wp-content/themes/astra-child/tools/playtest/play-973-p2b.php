<?php
/* #973 河洛 · 通关记录（第二期·续）：第一段走完（技能低→用「别的法子」） */
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
    echo "  [按钮] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 84, '…') . "　"; echo "\n";
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 30; $i++) {
        if (($S['room'] ?? '') === $to) return true;
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
/* 关键：先试「调查」两次；不行就走「别的法子」里不需要掷骰的那条（找人/花钱/绕路） */
function solve(&$S, $uid, $room, $kw, $node, $flag, &$problems) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= 2; $i++) {
        rest_if_tired($S, $uid);
        $l = cmd($S, $uid, '调查 ' . $kw, 2, true);
        printf("  > 调查 %s → %s\n", $kw, mb_strimwidth(trim(preg_replace('/\s+/', ' ', implode(' ', (array) $l))), 0, 70, '…'));
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) return true;
    }
    foreach ((array) (crrg_seal_paths_full()[$node] ?? []) as $i => $p) {
        if ($p['type'] === 'check') continue;                     // 只挑不需要掷骰的
        if ($p['type'] === 'npc' && (int) ($pc_fav = 0) === 0) { /* 好感可能不够，试一次看提示 */ }
        rest_if_tired($S, $uid);
        $l = cmd($S, $uid, '换法 ' . $node . ' ' . ($i + 1), 3, true);
        printf("  > 换法 %s %d（%s）→ %s\n", $node, $i + 1, $p['n'], mb_strimwidth(trim(preg_replace('/\s+/', ' ', implode(' ', (array) $l))), 0, 62, '…'));
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) return true;
    }
    $problems[] = "调查点「$node」没走通（$flag）";
    return false;
}

echo "=== 第二期·续：第一段走完 ===\n";
echo "── ① 领装备（已补货）──\n";
walk($S, $uid, 'b2f_gear');
cmd($S, $uid, '取 防护服', 1);
cmd($S, $uid, '穿戴 防护服', 1);

echo "\n── ② 六个调查点（技能低 → 走别的法子）──\n";
$need = [['b3_warehouse','封条','封条','seal_库存盘点'], ['b4_main','封条','门况复核','seal_门况普查'],
         ['b4_c2','门','私补','seal_私补痕迹'], ['b4_obs','台账','台账','seal_用量台账'],
         ['b2b_tech','配方','配方比样','seal_自制配方'], ['b1_archive','采购','采购','seal_三年档案']];
foreach ($need as [$room, $kw, $node, $flag]) {
    $ok = solve($S, $uid, $room, $kw, $node, $flag, $problems);
    echo "      " . ($ok ? '✓ 拿到' : '✗ 没拿到') . " " . $flag . "\n";
}
echo "\n── ③ 四个问人 ──\n";
foreach ([['b3_warehouse','周德海 就地取材'], ['b2b_tech','韩雪 配方'], ['b4_main','守卫 哪几扇'], ['b1_med','白大夫 当年']] as [$room, $q]) {
    walk($S, $uid, $room); rest_if_tired($S, $uid);
    $l = cmd($S, $uid, '细问 ' . $q, 2, true);
    printf("  > 细问 %s → %s\n", $q, mb_strimwidth(trim(preg_replace('/\s+/', ' ', implode(' ', (array) $l))), 0, 70, '…'));
}
$pc = crrg_drill_pc($uid);
printf("\n  线索：%s\n  进度 %d／%d\n", implode('、', preg_grep('/^seal_(库存|门况|私补|用量|自制|三年|来源)/', (array) $S['flags'])), (int) $pc['mission_step'], count((array) crrg_drill_mission('m_mid_seal')['steps']));

echo "\n── ④ 决断（压下）＋结算 ──\n";
walk($S, $uid, 'b2b_hall');
btn($S, $uid, 'stagechoice', '压下');
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', 5);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ **在手：%s**（进度 %d）\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));
if (($pc['mission'] ?? '') !== 'm_mid_seal2') $problems[] = '第一段结算后没自动接上第二段';
echo "\n【卡点】" . ($problems ? implode('；', $problems) : "无") . "\n";
