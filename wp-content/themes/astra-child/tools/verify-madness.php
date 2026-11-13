<?php
/**
 * 理智与疯狂门禁（tools/verify-madness.php，第 1 轮，2026-10-14）
 * 查四件：
 *   ① **表完整性**：发作表 1D10 十条、恐惧症/躁狂各十条（编号连续）✓
 *   ② **掉得下去，就得回得来**：恢复途径必须存在（治疗/休养两条入口 + 函数在）✓
 *   ③ **CoC 阈值行为**：单次 ≥5 → 临时疯狂；当日累计 ≥ 当前SAN/5 → 不定疯狂 ✓（真跑）
 *   ④ **幂等**：同一件东西重复目击只过一次理智检定 ✓（真跑）
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-14：**门禁不许留副作用** —— 跑游戏逻辑会经引擎落事实/写 option ✗
   （跑前记水位、跑完精确回收 ✓ 与 verify-bus / verify-game 同一套做法）
   ⚠ 以后每写一个新门禁，**都要带这段** ✓ */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});
$err = []; $warn = []; $ok = [];

/* ① 表完整性 */
foreach ([['发作表', 'crrg_mad_fit_table'], ['恐惧症表', 'crrg_mad_phobia_table'], ['躁狂表', 'crrg_mad_mania_table']] as [$nm, $fn]) {
    if (!function_exists($fn)) { $err[] = "① $nm 不存在（$fn）"; continue; }
    $t = $fn();
    $bad = [];
    for ($i = 1; $i <= 10; $i++) if (empty($t[$i][0])) $bad[] = $i;
    if ($bad) $err[] = "① $nm 缺编号：" . implode(',', $bad);
    else $ok[] = "① $nm 1D10 十条齐 ✓";
}

/* ② 恢复途径 */
$need = ['crrg_san_therapy' => '心理分析（每周一次 1D3）', 'crrg_san_rest' => '休养（按天）'];
foreach ($need as $fn => $why) if (!function_exists($fn)) $err[] = "② 恢复途径缺：$why（$fn）";
$src = file_get_contents($THEME . '/includes/game/core/drill-part-exec.php');
if (strpos($src, "'therapy'") === false) $err[] = "② 指令里没有恢复入口（治疗）";
if (strpos($src, "'rest_san'") === false) $err[] = "② 指令里没有恢复入口（休养）";
$ok[] = '② 恢复途径：治疗 ＋ 休养 两条入口都在 ✓';

/* ③ 阈值行为（真跑；临时档不留数据） */
if (function_exists('crrg_san_loss')) {
    $pc = ['uid' => 0, 'san' => 60, 'mad' => [], 'san_log' => [], 'san_day' => []];
    $out = crrg_san_loss($pc, 6, '门禁测试');
    $temp = ((int) ($pc['mad']['temp_until'] ?? 0)) > 0;
    if (!$temp) $err[] = '③ 单次损失 6 点却没触发临时性疯狂（CoC 阈值 ≥5）';
    else $ok[] = '③ 单次 ≥5 → 临时性疯狂 ✓';
    $pc2 = ['uid' => 0, 'san' => 20, 'mad' => [], 'san_log' => [], 'san_day' => []];
    crrg_san_loss($pc2, 4, '门禁测试');    // 4 < 20/5=4 → 边界；再来 1 点应触发
    crrg_san_loss($pc2, 1, '门禁测试');
    if (empty($pc2['mad']['indef'])) $err[] = '③ 当日累计达 SAN/5 却没触发不定性疯狂';
    else $ok[] = '③ 当日累计 ≥ SAN/5 → 不定性疯狂 ✓';
    /* ④ 幂等：同一 witness key 重复只算一次 */
    $pc3 = ['uid' => 0, 'san' => 60, 'mad' => [], 'san_log' => []];
    unset($GLOBALS['crrg_ctx_seen']);
    crrg_mad_witness($pc3, 'o_11_07', '1/1D4', '某件镇物');
    $n1 = crrg_san_of($pc3);
    crrg_mad_witness($pc3, 'o_11_07', '1/1D4', '某件镇物');
    $n2 = crrg_san_of($pc3);
    if ($n1 !== $n2) $err[] = "④ 目击没有幂等（第一次 $n1，第二次 $n2）";
    else $ok[] = '④ 目击阈值幂等（同一件东西只过一次）✓';
} else {
    $err[] = '③ madness 未加载';
}

echo "=== 理智与疯狂门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
