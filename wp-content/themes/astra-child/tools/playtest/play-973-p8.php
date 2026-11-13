<?php
/* #973 河洛 · 通关记录（第八期·终）：回基地 → 第四段结算 → 第五段 → 结案 */
require "/www/wwwroot/crrg_moebius_com_114/wp-load.php";
$uid = 973;
$CLOCK0 = get_option('crrg_drill_clock', null);
register_shutdown_function(function () use ($CLOCK0) { if (is_array($CLOCK0)) update_option('crrg_drill_clock', $CLOCK0, false); echo "（世界钟已还原）\n"; });
$problems = []; $done = [];
$pc0 = crrg_drill_pc($uid);
crrg_drill_set_scene($pc0['scene'] ?? 'base');            // 以卡里记录的场景为准
$st = crrg_drill_state($uid);
$S = ['room' => $st['room'] ?? 'tw_townhall', 'items' => [], 'flags' => (array) ($st['flags'] ?? []), 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
/* 先跑一次查看：触发房间自愈（场景/房间不一致时自动纠正） */
$pc1 = crrg_drill_pc($uid);
[$S, $lz, $pc1] = crrg_drill_exec($S, crrg_drill_ctx($uid), '查看', $pc1, $uid);
crrg_drill_pc_save($uid, $pc1); crrg_drill_save($uid, $S);
printf("（自愈后：场景=%s ｜ 位置=%s）\n", crrg_drill_cur_scene(), crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']);

function cmd(&$S, $uid, $c, $quiet = true) {
    $pc = crrg_drill_pc($uid);
    [$S, $l, $pc] = crrg_drill_exec($S, crrg_drill_ctx($uid), $c, $pc, $uid);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  > " . $c . "\n"; foreach (array_slice((array) $l, 0, 2) as $x) echo "      " . mb_strimwidth((string) $x, 0, 86, '…') . "\n"; }
    return $l;
}
function btn(&$S, $uid, $k, $id = '', $quiet = true) {
    $pc = crrg_drill_pc($uid);
    $r = crrg_drill_do_act($S, $pc, crrg_drill_ctx($uid), $uid, $k, $id);
    crrg_drill_pc_save($uid, $pc); crrg_drill_save($uid, $S);
    if (!$quiet) { echo "  [按钮 " . $k . ($id ? ':' . $id : '') . "] → "; foreach (array_slice((array) $r, 0, 2) as $x) echo mb_strimwidth((string) $x, 0, 74, '…') . "　"; echo "\n"; }
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

echo "=== 第八期（终）：回基地 → 第四段 → 第五段 → 结案 ===\n";
$pc = crrg_drill_pc($uid);
printf("在手：%s\n\n", $pc['mission'] ?: '（空）');

echo "── ① 从汽车站坐基地车回基地 ──\n";
walk($S, $uid, 'tw_station');
$r = btn($S, $uid, 'travel', 'base:credits', false);
printf("  场景=%s ｜ 位置=%s\n\n", crrg_drill_cur_scene(), crrg_drill_rooms()[$S['room']]['t'] ?? $S['room']);

echo "── ② 第四段结算（值班板）──\n";
walk($S, $uid, 'b2b_hall');
// 补漏：若料不完整/事故没处置，先补齐
if (in_array('s4_料_缺', (array) $S['flags'], true)) { cmd($S, $uid, '护料 让步', false); }
$b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
cmd($S, $uid, '交任务', false);
$pc = crrg_drill_pc($uid);
printf("  → +%d 分 / +%d 元 ｜ 在手：%s（进度 %d）\n\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1],
    $pc['mission'] ?: '（空）', (int) ($pc['mission_step'] ?? 0));
if (($pc['mission'] ?? '') === 'm_mid_seal5') $done[] = '第四段';
elseif (($pc['mission'] ?? '') !== 'm_mid_seal4') $problems[] = '第四段状态异常';

echo "── ③ 第五段：开工 → 贴封 → 结案 ──\n";
if (($pc['mission'] ?? '') === 'm_mid_seal5') {
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'seal5run', '', false);
    walk($S, $uid, 'b4_c2');    btn($S, $uid, 'seal5seal', '', false);
    walk($S, $uid, 'b2b_hall'); btn($S, $uid, 'seal5close', '', false);
    $b = crrg_drill_pc($uid); $b0 = [(int) $b['credits'], (int) $b['money']];
    cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → +%d 分 / +%d 元 ｜ 在手：%s\n", (int) $pc['credits'] - $b0[0], (int) $pc['money'] - $b0[1], $pc['mission'] ?: '（空）');
    if (in_array('m_mid_seal5', (array) $pc['mission_done'], true)) { $done[] = '第五段'; echo "\n  ★★★ 恭喜——**《封条》五段全部通关（结案）**\n"; }
    else { $problems[] = '第五段没结算'; printf("  （进度 %d／%d）\n", (int) ($pc['mission_step'] ?? 0), count((array) crrg_drill_mission_any('m_mid_seal5')['steps'])); }
} elseif (($pc['mission'] ?? '') === 'm_mid_seal4') {
    echo "  （第四段还没结算，先补齐必要步骤）\n";
    walk($S, $uid, 'b2b_tech'); btn($S, $uid, 'handover', ''); btn($S, $uid, 'craft', '稳妥');
    walk($S, $uid, 'b2b_hall'); cmd($S, $uid, '交任务', false);
    $pc = crrg_drill_pc($uid);
    printf("  → 在手：%s\n", $pc['mission'] ?: '（空）');
}

echo "\n── ④ 结案后的账 ──\n";
$pc = crrg_drill_pc($uid);
printf("  卡级 %s ｜ 演习权限 %s ｜ 积分 %d ｜ 钱 %d ｜ 完成合约 %d 项\n", crrg_drill_ctx($uid)['card'],
    crrg_drill_clearance_name(max((int) ($pc['clearance'] ?? 0), (int) crrg_drill_ctx($uid)['lv'])),
    (int) $pc['credits'], (int) $pc['money'], count((array) $pc['mission_done']));
$rep = get_posts(['post_type' => 'post', 'post_status' => 'any', 'posts_per_page' => 3, 'meta_key' => 'crrg_record_status', 'meta_value' => 'seal_close_report']);
printf("  结案报告（private）：%s\n", $rep ? ('已生成 ' . count($rep) . ' 份，最新「' . $rep[0]->post_title . '」') : '（未生成）');
echo "\n【完成】" . ($done ? implode('、', $done) : '（无）') . "\n【卡点】" . ($problems ? implode('；', $problems) : '无') . "\n";
