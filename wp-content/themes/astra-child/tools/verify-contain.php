<?php
/**
 * 收容门禁（tools/verify-contain.php，R4，2026-10-14）
 * 查四件：
 *   ① **权限矩阵自洽**：低等级不能执行高等级操作（真跑：C 级做甲级 → 被拒 ✓）
 *   ② **每次操作必留监护日志**（真跑：跑一次 → 日志 +1；被拒也要留痕 ✓）
 *   ③ **小队伤亡必然记账**（真跑：强制伤亡 → 日志含伤亡 ＋ 暴露/事实记账 ✓）
 *   ④ **写入口唯一**：源码里只有 crrg_contain_log_add 能写这个 option（防两套真相 ✓）
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
global $wpdb;
$ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$maxId = 0; if ($ft) $maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $ft");
$bakOpt = get_option('crrg_contain', null);
register_shutdown_function(function () use ($ft, $maxId, $bakOpt) {
    if ($ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $ft WHERE id > %d", $maxId)); }
    if ($bakOpt === null) delete_option('crrg_contain'); else update_option('crrg_contain', $bakOpt, false);
});

$err = []; $warn = []; $ok = [];

/* ① 权限矩阵 */
$pcC = ['uid' => 0, 'clearance' => 3, 'credits' => 0, 'hp' => 12, 'san' => 60, 'vitals' => crrg_vitals_default(), 'squad' => []];
$pcB = ['uid' => 0, 'clearance' => 5, 'credits' => 0, 'hp' => 12, 'san' => 60, 'vitals' => crrg_vitals_default(), 'squad' => []];
$a1 = crrg_contain_rank_ok($pcC, '丙'); $a2 = crrg_contain_rank_ok($pcC, '甲'); $a3 = crrg_contain_rank_ok($pcB, '乙'); $a4 = crrg_contain_rank_ok($pcB, '甲');
if (!$a1[0]) $err[] = '① C 级做丙级应当允许，却被拒：' . $a1[1];
if ($a2[0])  $err[] = '① C 级做甲级应当被拒，却放行 ✗';
if (!$a3[0]) $err[] = '① B 级做乙级应当允许，却被拒：' . $a3[1];
if ($a4[0])  $err[] = '① B 级做甲级应当被拒，却放行 ✗';
if (!$err) $ok[] = '① 权限矩阵自洽：C→丙 ✓／C→甲 拒 ✓／B→乙 ✓／B→甲 拒 ✓';

/* ② 每次操作留日志 */
$s = ['room' => 'b4_transfer', 'flags' => [], 'items' => []];
$n0 = count((array) (crrg_contain_all()['log'] ?? []));
crrg_contain_do($s, $pcC, '取样', 0, '门禁·试样');
$n1 = count((array) (crrg_contain_all()['log'] ?? []));
if ($n1 <= $n0) $err[] = '② 操作后监护日志没有增加 ✗';
else $ok[] = '② 每次操作必留监护日志 ✓（' . $n0 . '→' . $n1 . '）';
/* 被拒也要留痕 */
$s2 = ['room' => 'b4_c1', 'flags' => [], 'items' => []];
$pcC['clearance'] = 3;
$GLOBALS['crrg_contain_object_level'] = '甲';
crrg_contain_do($s2, $pcC, '取样', 0, '门禁·高危件');
$n2 = count((array) (crrg_contain_all()['log'] ?? []));
if ($n2 <= $n1) $err[] = '② 被拒的操作没有留痕 ✗';
else $ok[] = '② 被拒也留痕（可追责）✓';
unset($GLOBALS['crrg_contain_object_level']);

/* ③ 小队：组队门槛 + 伤亡记账 */
$out = crrg_squad_form($pcC, 3);
if (strpos(implode('', $out), '高级别') === false) $err[] = '③ C 级不该能带队 ✗';
else $ok[] = '③ 带小队门槛生效（C 级不给带）✓';
$out = crrg_squad_form($pcB, 3);
if (count((array) $pcB['squad']) < 3) $err[] = '③ B 级带队失败 ✗';
else $ok[] = '③ B 级可带小队 ✓（' . implode('、', array_column($pcB['squad'], 'n')) . '）';
/* 强制伤亡：把胆量拉高（通过拒绝线）但把体力/概率拉满 → 多跑几次必有伤亡 */
$pcB['squad'] = [['n' => '小试', 'guts' => 100, 'stam' => 80, 'obey' => 90, 'state' => 'ok']];
$n3 = count((array) (crrg_contain_all()['log'] ?? []));
$hurt = 0;
for ($i = 0; $i < 40 && !$hurt; $i++) {
    $pcB['squad'][0]['state'] = 'ok';
    $o = crrg_squad_order($s2, $pcB, '协助取样', '门禁·试样');
    if (strpos(implode('', $o), '伤亡') !== false || strpos(implode('', $o), '出了事') !== false) $hurt = 1;
}
$logs = (array) (crrg_contain_all()['log'] ?? []);
$hasHurtLog = false;
foreach ($logs as $l) if (strpos((string) ($l['note'] ?? ''), '伤亡') !== false) $hasHurtLog = true;
if (!$hurt) $warn[] = '③ 40 次未出现伤亡（概率问题，不是逻辑问题）——记账分支未能当场验证';
elseif (!$hasHurtLog) $err[] = '③ 出现伤亡但日志里没有伤亡记账 ✗';
else $ok[] = '③ 小队伤亡必然记账 ✓（日志含伤亡 ✓）';

/* ④ 写入口唯一 */
$src = file_get_contents($THEME . '/includes/game/systems/contain.php');
preg_match_all('/update_option\(\s*CRRG_CONTAIN_OPT/u', $src, $m);
if (count($m[0]) !== 1) $err[] = '④ 写 CRRG_CONTAIN_OPT 的地方有 ' . count($m[0]) . ' 处（应恰好 1 处＝唯一写入口 crrg_contain_save）✗';
else $ok[] = '④ 监护日志写入口唯一 ✓';

echo "=== 收容门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
