<?php
/* 收尾验证（一次跑全）：数据干净 / 节点数 / 过法数 / 与 standard() 一致 / 抽样可玩 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-14：**验证器不许留副作用** —— 跑一遍任务/推进器会经引擎落事实、写 option ✗
   （与 verify-bus.php、snapshot-game.php 同一套做法：跑前记水位，跑完精确回收 ✓） */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_bt_state','crrg_duty','crrg_contain','crrg_duty_roster','crrg_phone_life','crrg_arch_index','crrg_travel_pending'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});


$fail = 0;
/* ① 数据里还有没有"字符串当路径" */
$bad = [];
foreach (crrg_seal_paths() as $n => $l) {
    if (!is_array($l)) { $bad[] = $n; continue; }
    foreach ($l as $p) if (!is_array($p)) { $bad[] = $n; break; }
}
printf("① 源数据异常节点：%s\n", $bad ? ('✗ ' . count($bad) . ' 个：' . implode('、', $bad)) : '无 ✓');
if ($bad) $fail++;

/* ② 节点数 / 过法数 */
$all = crrg_seal_paths_full();
$cnt = 0; foreach ($all as $l) $cnt += count($l);
printf("② 节点 %d（期望 52）｜ 过法 %d（清理前 256）\n", count($all), $cnt);
if (count($all) !== 52) $fail++;

/* ③ 每个节点都要有"可执行"的过法（flag + 至少一条路） */
$noPath = [];
foreach ($all as $n => $l) {
    if (!$l) { $noPath[] = $n; continue; }
    foreach ($l as $p) if (empty($p['flag'])) { $noPath[] = $n; break; }
}
printf("③ 缺过法/缺旗标的节点：%s\n", $noPath ? ('✗ ' . implode('、', $noPath)) : '无 ✓');
if ($noPath) $fail++;

/* ④ 无类型错（每条路都要有 type 和文本） */
$noType = [];
foreach ($all as $n => $l) foreach ($l as $p) if (empty($p['type']) || !isset($p['text'])) { $noType[] = $n; break; }
printf("④ 结构不完整的节点：%s\n", $noType ? ('✗ ' . implode('、', $noType)) : '无 ✓');
if ($noType) $fail++;

/* ⑤ 抽样：换法真的能走（挑一个原来"junk 且无定义"的节点） */
crrg_drill_set_scene('base');
$pc = ['attrs' => ['体质' => 50, '意志' => 50, '教育' => 50, '智力' => 50], 'skills' => ['侦查' => 60],
       'san' => 50, 'hp' => 12, 'npc_fav' => [], 'vitals' => crrg_vitals_default(), 'items' => [], 'money' => 100];
foreach ([['b4_main', '守卫'], ['tw_junk', '铅'], ['b4_main', '事故定档']] as [$room, $node]) {
    $S = ['room' => $room, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
    $r = crrg_seal_try($S, $pc, $node, '1');
    $ok = is_array($r) && $r && mb_strpos(implode(' ', $r), '没有这条法子') === false;
    printf("⑤ %s / 换法 1 → %s ｜ %s\n", $node, mb_strimwidth(trim(preg_replace('/\s+/', ' ', implode(' ', (array) $r))), 0, 46, '…'), $ok ? '✓' : '✗');
    if (!$ok) $fail++;
}

/* ⑥ 线索内容仍在（那是游戏内容，不该受影响） */
printf("⑥ 线索内容抽查：守卫 %d 字 ｜ 体检随访 %d 字\n",
    mb_strlen(implode('', (array) crrg_seal_clue_text('守卫'))), mb_strlen(implode('', (array) crrg_seal_clue_text('体检随访'))));
/* ⑦ 现场现象仍在 */
printf("⑦ 现场现象抽查：%s\n", mb_strimwidth(crrg_seal_spot_text('守卫'), 0, 40, '…'));

echo $fail === 0 ? "\n=== 全部通过 ✓ ===\n" : "\n=== 有 $fail 项未过 ✗ ===\n";
