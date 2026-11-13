<?php
/* #973 河洛 · 通关记录（第六期）：第二段 → 第三段 → 第四段 → 第五段 → 结案 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
crrg_drill_set_scene('base');
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$problems = []; $done = [];
$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'b1_tunnel', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];

function cmd(&$S, $uid, $c, $quiet = true) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, 2) as $x) echo "      " . mb_strimwidth((string) $x, 0, 86, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '') {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    echo "  [按钮 " . $k . ($id ? ':' . $id : '') . "] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 74, '…') . "　"; echo "\n";
    return $r;
}
function walk(&$S, $uid, $to) {
    for ($i = 0; $i < 40; $i++) {
        if (($S['room'] ?? '') === $to) return true;
        $rooms = crrg_drill_rooms();
        if (!isset($rooms[$to])) { cmd($S, $uid, '长途 青石镇'); // 尝试跨场景：先回基地或去镇上
            $rooms = crrg_drill_rooms();
            if (!isset($rooms[$to])) { echo "      （进不去 $to；当前 " . crrg_drill_cur_scene() . "）\n"; return false; } }
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
function solve(&$S, $uid, $room, $kw, $node, $flag, &$problems, $tries = 2) {
    walk($S, $uid, $room);
    for ($i = 1; $i <= $tries; $i++) { rest($S, $uid); cmd($S, $uid, '调查 ' . $kw);
        if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ %s\n", $kw); return true; } }
    rest($S, $uid); cmd($S, $uid, '换法 ' . $node . ' 1');
    if (in_array($flag, (array) ($S['flags'] ?? []), true)) { printf("  ✓ %s（兜底）\n", $node); return true; }
    $problems[] = "$node"; return false;
}
function to_town(&$S, $uid) {
    if (crrg_drill_cur_scene() === 'town') return true;
    cmd($S, $uid, '长途 青石镇');
    $pc = crrg_drill_pc($uid);
    if (!empty($pc['travel'])) { $pc['travel']['arrive'] = crrg_vitals_now() - 60; crrg_drill_pc_save($uid, $pc); }
    cmd($S, $uid, '查看', false);
    return crrg_drill_cur_scene() === 'town';
}
function accept_chain(&$S, $uid, $id, $label, &$problems) {
    $pc = crrg_drill_pc($uid);
    if (($pc['mission'] ?? '') === $id) { echo "  （" . $label . " 已在手）\n"; return true; }
    cmd($S, $uid, '接受 ' . $id, false);
    $pc = crrg_drill_pc($uid);
    $ok = ($pc['mission'] ?? '') === $id;
    printf("  %s：%s\n", $label, $ok ? '接上 ✓' : '没接上 ✗');
    if (!$ok) $problems[] = $label . ' 接不上';
    return $ok;
}

echo "=== 第六期：一路到结案 ===\n\n";
echo "── 第二段（镇上）──\n";
if (!to_town($S, $uid)) $problems[] = '进不了镇';
else { printf("  已在镇上：%s\n", crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']); }
accept_chain($S, $uid, 'm_mid_seal2', '第二段', $problems);
/* 按步骤推进：2) 找匠人 3) 配方 4) 铅皮 5) 中毒 6) 朱砂土 7) 手续 8) 旧库管 9) 集齐 */
walk($S, $uid, 'tw_paper'); rest($S, $uid); cmd($S, $uid, '细问 常守业 匠人');
solve($S, $uid, 'tw_paper', '配方', '纸人', 'paper_配方', $problems);
solve($S, $uid, 'tw_farm', '电瓶', '电瓶', 'paper_铅箔', $problems);
solve($S, $uid, 'tw_clinic', '中毒', '中毒', 'paper_中毒', $problems);
solve($S, $uid, 'tw_tomb', '土', '土', 'paper_朱砂土', $problems);
walk($S, $uid, 'tw_townhall'); cmd($S, $uid, '细问 镇文书 绕过');
solve($S, $uid, 'tw_townhall', '手续', '手续', 'paper_通行', $problems);
foreach ([['tw_paper','常守业 旧库管'], ['tw_junk','孙德发 生面孔'], ['tw_farm','赵铁 同一个人']] as [$r, $q]) { walk($S, $uid, $r); rest($S, $uid); cmd($S, $uid, '细问 ' . $q); }
walk($S, $uid, 'tw_townhall'); btn($S, $uid, 'stagechoice', '带回');
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', false);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
if (($pc['mission'] ?? '') !== 'm_mid_seal3') $problems[] = '第二段没接上第三段'; else $done[] = '第二段';

echo "── 第三段（徒弟与冒充者）──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal3') {
    solve($S, $uid, 'tw_homes', '邻居', '邻居', 's3_邻居', $problems);
    walk($S, $uid, 'tw_junk'); cmd($S, $uid, '细问 孙德发 徒弟');
    walk($S, $uid, 'tw_paper'); cmd($S, $uid, '细问 常守业 徒弟');
    $c = crrg_world_clock(); $t = (new DateTime(crrg_wdate('Y-m-d', $c['ts']) . ' 23:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp();
    if ($t > $c['ts']) crrg_world_set_game($t, '通关记录·入夜');
    walk($S, $uid, 'tw_hillpath'); cmd($S, $uid, '守夜', false);
    if (in_array('s3_遇徒弟', (array) $S['flags'], true)) cmd($S, $uid, '细问 田小满 账', false);
    if (in_array('s3_遇冒充者', (array) $S['flags'], true)) { walk($S, $uid, 'tw_main_c'); btn($S, $uid, 'parley', '对峙'); }
    else { echo "  （本次守夜没遇到冒充者，改用别的方式推进）\n"; solve($S, $uid, 'tw_main_c', '冒充者', '冒充者车', 's3_冒充者身份', $problems); }
    solve($S, $uid, 'tw_bank', '流水', '流水', 's3_暗账', $problems);
    walk($S, $uid, 'tw_townhall');
    cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → 在手：%s ｜ 进度 %d\n\n", $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));
    if (($pc['mission'] ?? '') === 'm_mid_seal4') $done[] = '第三段'; else $problems[] = '第三段没接上第四段';
}

echo "── 第四段（回基地：护料/那扇门/工艺）──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal4') {
    cmd($S, $uid, '长途 基地');
    $pc = crrg_drill_pc($uid);
    if (!empty($pc['travel'])) { $pc['travel']['arrive'] = crrg_vitals_now() - 60; crrg_drill_pc_save($uid, $pc); }
    cmd($S, $uid, '查看');
    printf("  场景=%s ｜ 位置=%s\n", crrg_drill_cur_scene(), crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']);
    walk($S, $uid, 'b4_main'); rest($S, $uid);
    btn($S, $uid, 'accident', '');           // 事故定档
    walk($S, $uid, 'b4_c2'); btn($S, $uid, 'accident', '补件');
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'handover', ''); btn($S, $uid, 'craft', '稳妥');
    cmd($S, $uid, '护料 硬碰');
    walk($S, $uid, 'b2b_hall'); cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → 在手：%s\n\n", $pc['mission'] ?: '（空）');
    if (($pc['mission'] ?? '') === 'm_mid_seal5') $done[] = '第四段'; else $problems[] = '第四段没接上第五段';
}

echo "── 第五段（封口：出货/贴封/结案）──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal5') {
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'seal5run', '');
    walk($S, $uid, 'b4_c2'); btn($S, $uid, 'seal5seal', '');
    walk($S, $uid, 'b2b_hall'); btn($S, $uid, 'seal5close', '');
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
    if (in_array('m_mid_seal5', (array) $pc['mission_done'], true)) { $done[] = '第五段'; echo "  ★ 《封条》全五段通关（结案）\n"; }
    else $problems[] = '第五段没结算';
}
echo "\n【完成】" . ($done ? implode('、', $done) : '（无）') . "\n【卡点】" . ($problems ? implode('；', $problems) : '无') . "\n";
