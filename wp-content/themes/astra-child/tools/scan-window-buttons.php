<?php
/* 扫描：所有窗口里的按钮，逐个真跑 do_act，找出"没接上动作"的 k（2026-10-01）
 * 用法：php tools/scan-window-buttons.php
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

do_action('init');

function ck_pc() {
    $pc = crrg_coc_new_pc(); $d = crrg_coc_derive($pc['attrs'] ?? []);
    $pc['hp'] = $d['hp_max']; $pc['san'] = $d['san_max']; $pc['mp'] = $d['mp_max']; $pc['ready'] = true;
    $pc['vitals'] = crrg_vitals_default(); $pc['items'] = ['food_ration','tool_flask','med_firstaid'];
    $pc['money'] = 500; $pc['credits'] = 50; $pc['npc_fav'] = ['常守业' => 2];
    $pc['mission'] = 'm_mid_seal2'; $pc['mission_step'] = 3;
    return $pc;
}
$args = ['dlg' => 'dlg:town_noodle', 'trade' => 'trade:常守业', 'shop' => 'shop:town_coop', 'help' => 'help', 'here' => 'here'];
$rooms = ['b2b_hall' => 'base', 'b1_canteen' => 'base', 'b3_warehouse' => 'base', 'b1_room' => 'base',
          'garage_maint' => 'base', 'tw_coop' => 'town', 'tw_noodle' => 'town', 'tw_paper' => 'town', 'b2f_gear' => 'base'];
$bad = []; $okK = []; $total = 0;
foreach (crrg_ui_win_all() as $id => $w) {
    foreach ($rooms as $room => $sc) {
        crrg_drill_set_scene($sc);
        $S = ['room' => $room, 'items' => [], 'flags' => ['paper_匠人','paper_配方','paper_通行','paper_集齐'], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
        $pc = ck_pc(); $ctx = crrg_drill_ctx(999);
        $wid = $args[$id] ?? $id;
        $b = crrg_ui_win_build($wid, $S, $ctx, $pc, 999);
        if (!is_array($b)) continue;
        foreach ((array) ($b['items'] ?? []) as $it) {
            $k = (string) ($it['k'] ?? '');
            if ($k === '' || in_array($k, ['noop','divider','win','cmd'], true)) continue;   // 这几种不经过 do_act ✓
            $total++;
            $pc2 = ck_pc();
            ob_start();
            $res = crrg_drill_do_act($S, $pc2, $ctx, 999, $k, (string) ($it['id'] ?? ''));
            ob_end_clean();
            $txt = is_array($res) ? implode(' ', $res) : (string) $res;
            if (mb_strpos($txt, '没接上动作') !== false || mb_strpos($txt, '未知操作') !== false) {
                $bad[$k][] = $id . '@' . $room . '（' . mb_strimwidth((string) ($it['label'] ?? ''), 0, 22, '…') . '）';
            } else $okK[$k] = 1;
        }
    }
}
printf("检查窗口按钮 %d 个（去重 k：%d 个正常）\n", $total, count($okK));
if (!$bad) { echo "没接上动作的：无 ✓\n"; exit(0); }
echo "✗ 没接上动作的 k：\n";
foreach ($bad as $k => $where) printf("  %-18s ×%d  例：%s\n", $k, count($where), $where[0]);
printf("\n合计 %d 个 k 没接上（涉及 %d 处按钮）\n", count($bad), array_sum(array_map('count', $bad)));
