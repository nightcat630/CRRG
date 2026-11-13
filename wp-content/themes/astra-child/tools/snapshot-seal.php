<?php
/* 行为快照：对 seal/seal2/seal3 的 survey / ask / stage_choice 跑一批固定调用，把"输出+旗标+好感"落成 JSON
 * 用法：php tools/snapshot-seal.php before.json | after.json   （然后 diff）
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* ── 钉钟（2026-09-29）：快照必须可复现。世界钟按 48 倍速走，昼夜会影响用例结果，
   所以每次跑之前把游戏时间钉到**同一时刻**（写 option：real=now, game=固定值）。
   注意：正确的钉法是直接写 option；crrg_world_set_origin() 的第一个参数是**现实锚点**，钉不了游戏时刻。 ── */
/* ── 跑完必须还原（2026-10-09 修）：原来只钉**不还** ✗ → 全站世界钟被留在测试值上，
   自检次次报「世界钟锚点被拨前」✓；还原时按"从锚点起算"把 game 补齐（否则还原本身＝拨钟 ✗） ── */
if (function_exists('crrg_world_clock') && defined('CRRG_CLOCK_OPT')) {
    $__clockSaved = get_option(CRRG_CLOCK_OPT, null);
    $pinGame = strtotime('2026-09-29 10:00:00');          // 固定：第 3 天 · 上午（避开时段边界）
    update_option(CRRG_CLOCK_OPT, ['real' => time(), 'game' => $pinGame,
        'day0' => $pinGame - 2 * 86400, 'day0_day' => 3], false);
    register_shutdown_function(function () use ($__clockSaved) {
        if (!is_array($__clockSaved) || empty($__clockSaved['game'])) return;
        $scale = defined('CRRG_TIME_SCALE') ? (int) CRRG_TIME_SCALE : 48;
        $o = $__clockSaved;
        $o['game'] = (int) $o['game'] + (int) round((time() - (int) ($o['real'] ?? time())) * $scale);
        $o['real'] = time();
        update_option(CRRG_CLOCK_OPT, $o, false);
    });
}

$outFile = $argv[1] ?? '/tmp/snap.json';
/** 归一化：把随机骰点抹平，保证前后可比 */
function snap_norm($v) {
    if (is_array($v)) { $o = []; foreach ($v as $k => $x) $o[$k] = snap_norm($x); return $o; }
    if (!is_string($v)) return $v;
    $v = preg_replace('/D100＝\d+/u', 'D100＝X', $v);
    $v = preg_replace('/（技能 \d+%）/u', '（技能 N%）', $v);
    return $v;
}

/* 覆盖三个模块的调查点关键词（从各自的数据里取，不手写） */
$rooms = [
    'seal'  => ['b3_warehouse' => ['封条', '库存'], 'b4_main' => ['门'], 'b4_c2' => ['门', '私补'],
                'b4_obs' => ['台账'], 'b2b_tech' => ['配方'], 'b1_archive' => ['采购']],
    'seal2' => ['tw_paper' => ['配方', '纸人'], 'tw_farm' => ['电瓶'], 'tw_clinic' => ['中毒'],
                'tw_tomb' => ['土'], 'tw_townhall' => ['手续'], 'tw_junk' => ['铅'], 'tw_hillpath' => ['痕迹', '车辙']],
    'seal3' => ['tw_homes' => ['邻居'], 'tw_bank' => ['流水'], 'tw_hillpath' => ['车辙'], 'tw_main_c' => ['冒充者']],
];
$flagSets = [[], ['seal_库存盘点'], ['paper_配方', 'paper_铅箔'], ['seal_路线_上报'], ['paper_绕过'], ['s3_邻_已查']];
$snap = [];
foreach ($rooms as $mod => $map) {
    $sfn = 'crrg_' . ($mod === 'seal' ? '' : $mod . '_') . 'survey';
    if (!function_exists($sfn)) continue;
    foreach ($map as $room => $kws) {
        foreach ($kws as $kw) {
            foreach ($flagSets as $fi => $flags) {
                $S = ['room' => $room, 'items' => [], 'flags' => $flags, 'steps' => 0, 'gates' => 0];
                $pc = ['attrs' => ['体质' => 50, '意志' => 50, '教育' => 50, '智力' => 50],
                       'skills' => ['侦查' => 60, '会计' => 60, '急救' => 60, '图书馆使用' => 60, '科学' => 60],
                       'san' => 50, 'hp' => 12, 'npc_fav' => [], 'items' => [], 'vitals' => crrg_vitals_default()];
                mt_srand(20260929);
                $r = $sfn($S, $pc, $kw);
                $r = snap_norm($r);
                $snap["survey|$mod|$room|$kw|$fi"] = [
                    'out' => $r, 'flags' => $S['flags'], 'fav' => $pc['npc_fav'],
                ];
            }
        }
    }
}
/* ask：三模块 × 全部 NPC×话题 × 两种好感 */
$askData = ['crrg_seal2_ask_data', 'crrg_seal3_ask_data'];
foreach ($askData as $fn) {
    if (!function_exists($fn)) continue;
    foreach ((array) call_user_func($fn) as $npc => $rows) {
        foreach ((array) $rows as $row) {
            foreach ([0, 2] as $fav) {
                $S = ['room' => 'x', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0];
                $pc = ['npc_fav' => [$npc => $fav], 'skills' => []];
                $afn = 'crrg_' . str_replace(['_ask_data', 'crrg_'], '', $fn) . '_npc_ask';
                mt_srand(20260929);
                $r = $afn($S, $pc, $npc, (string) ($row[0] ?? ''));
                $r = snap_norm($r);
                $snap["ask|$npc|" . ($row[0] ?? '') . "|$fav"] = ['out' => $r, 'flags' => $S['flags'], 'fav' => $pc['npc_fav']];
            }
        }
    }
}
file_put_contents($outFile, json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("快照 %d 条 → %s\n", count($snap), $outFile);
