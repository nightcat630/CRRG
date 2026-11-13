<?php
/* #973 河洛 · 通关记录（第七期）：第三段收尾 → 第四段 → 第五段 → 结案 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$problems = []; $done = [];
crrg_drill_set_scene('town');
$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'tw_gate', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function cmd(&$S, $uid, $c, $quiet = true) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, 2) as $x) echo "      " . mb_strimwidth((string) $x, 0, 84, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '', $quiet = false) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  [按钮 " . $k . ($id ? ':' . $id : '') . "] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 72, '…') . "　"; echo "\n"; }
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 40; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { echo "      （进不去 $to；当前 " . crrg_drill_cur_scene() . "）\n"; return false; }
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
function night_now() {
    $c = crrg_world_clock();
    $t = (new DateTime(crrg_wdate('Y-m-d', $c['ts']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
    if ($t > $c['ts']) crrg_world_set_game($t, '通关记录·入夜');
    return crrg_world_clock()['night'];
}
function solve(&$S, $uid, $room, $kw, $node, $flag, &$problems, $tries = 3) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= $tries; $i++) { rest($S, $uid); cmd($S, $uid, '调查 ' . $kw);
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ %s\n", $kw); return true; } }
    rest($S, $uid); cmd($S, $uid, '换法 ' . $node . ' 1');
    if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ %s（兜底）\n", $node); return true; }
    $problems[] = $node; printf("  ✗ %s\n", $node); return false;
}

echo "=== 第七期：第三段收尾 → 结案 ===\n";
$pc = crrg_drill_pc($uid);
printf("在手：%s ｜ 进度 %d\n\n", $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));

if (($pc['mission'] ?? '') === 'm_mid_seal3') {
    echo "── 第三段收尾 ──\n";
    /* 摊牌：守夜遇到冒充者（可能需要几夜） */
    $met = in_array('s3_遇冒充者', (array) $S['flags'], true) || in_array('s3_冒充者身份', (array) $S['flags'], true);
    for ($try = 1; $try <= 3 && !$met; $try++) {
        night_now();
        walk($S, $uid, 'tw_hillpath'); cmd($S, $uid, '守夜', false);
        $met = in_array('s3_遇冒充者', (array) $S['flags'], true) || in_array('s3_冒充者身份', (array) $S['flags'], true);
        printf("  守夜第 %d 夜：%s\n", $try, $met ? '遇到人 ✓' : '空等');
        if (!$met) { $c = crrg_world_clock(); crrg_world_set_game($c['ts'] + 8 * 3600, '通关记录·过夜'); }
    }
    if ($met) {
        if (in_array('s3_遇冒充者', (array) $S['flags'], true)) { walk($S, $uid, 'tw_main_c'); btn($S, $uid, 'parley', '对峙'); }
        else { solve($S, $uid, 'tw_main_c', '冒充者', '冒充者车', 's3_冒充者身份', $problems); }
    } else { $problems[] = '没遇到冒充者'; }
    if (in_array('s3_中毒事件', (array) $S['flags'], true) && !in_array('s3_中毒处理', (array) $S['flags'], true)) {
        echo "  中毒事件：\n"; walk($S, $uid, 'tw_paper'); btn($S, $uid, 'poison', '送医');
    }
    $pc = crrg_drill_pc($uid);
    printf("  进度 %d／%d ｜ 旗标 %s\n", (int) $pc['mission_step'], count((array) crrg_drill_mission_any('m_mid_seal3')['steps']),
        implode('、', array_slice(preg_grep('/^s3_/', (array) $S['flags']), 0, 12)));
    walk($S, $uid, 'tw_townhall');
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
    if (($pc['mission'] ?? '') === 'm_mid_seal4') $done[] = '第三段'; else $problems[] = '第三段没接上第四段';
}

echo "── 第四段（回基地：护料／那扇门／工艺）──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal4') {
    cmd($S, $uid, '长途 基地');
    $pc = crrg_drill_pc($uid);
    if (!empty($pc['travel'])) { $pc['travel']['arrive'] = crrg_vitals_now() - 60; crrg_drill_pc_save($uid, $pc); }
    cmd($S, $uid, '查看', false);
    printf("  场景=%s ｜ 位置=%s\n", crrg_drill_cur_scene(), crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']);
    walk($S, $uid, 'b2b_tech');
    cmd($S, $uid, '取 防护服'); cmd($S, $uid, '穿戴 防护服');
    walk($S, $uid, 'b4_main'); rest($S, $uid); btn($S, $uid, 'accident', '');
    walk($S, $uid, 'b4_c2'); btn($S, $uid, 'accident', '补件');
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'handover', ''); btn($S, $uid, 'craft', '稳妥');
    cmd($S, $uid, '护料 硬碰', false);
    walk($S, $uid, 'b2b_hall'); cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → 在手：%s\n\n", $pc['mission'] ?: '（空）');
    if (($pc['mission'] ?? '') === 'm_mid_seal5') $done[] = '第四段'; else $problems[] = '第四段没接上第五段';
}

echo "── 第五段（封口：出货／贴封／结案）──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal5') {
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'seal5run', '', false);
    walk($S, $uid, 'b4_c2'); btn($S, $uid, 'seal5seal', '', false);
    walk($S, $uid, 'b2b_hall'); btn($S, $uid, 'seal5close', '', false);
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
    if (in_array('m_mid_seal5', (array) $pc['mission_done'], true)) { $done[] = '第五段'; echo "  ★★★ 《封条》五段全部通关（结案）\n"; }
    else $problems[] = '第五段没结算';
}
echo "\n【本期完成】" . ($done ? implode('、', $done) : '（无）') . "\n【卡点】" . ($problems ? implode('；', $problems) : '无') . "\n";
