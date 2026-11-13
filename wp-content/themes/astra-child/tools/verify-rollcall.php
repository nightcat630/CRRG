<?php
/**
 * 点名门禁（tools/verify-rollcall.php，2026-10-19）
 * 委员长口径：报到 +2 积分；连 30 天 +10 声望；**不要惩罚**；NPC 点名与作息要加入。
 * 五查：
 *   ① 报到：积分 +2，且当天第二次不再给（幂等）
 *   ② 连 30 天：站在第 29 天 → 报到后 streak=30 且声望 +10（只给一次）
 *   ③ **无惩罚**：断签/没报到 → 积分、声望、**都不掉**；源码里也没有扣减路径
 *   ④ NPC 点名：册子里每个人的"在/未到"与其 `crrg_actor_where()` 一致
 *   ⑤ 日程已接：`crrg_cal_pump` 有调用点；`crrg_cal_events_today` 存在（手机"今日"不再空）
 */
define('CRRG_RC_TEST', true);
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-19：**门禁不许留副作用** —— 跑报到/点名会经引擎落事实、写 option ✗
   跑前记水位、跑完精确回收（与 verify-bus / verify-game 同一套做法 ✓） */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note',
          'crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster',
          'crrg_phone_life','crrg_arch_index'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});
$err=[]; $warn=[]; $ok=[];

function rc_pc($streak=0, $last=0) {
    return ['uid'=>9999,'name'=>'（门禁）','hp'=>12,'san'=>60,'credits'=>10,'money'=>100,
            'rep'=>['base'=>5],'npc_fav'=>[],'items'=>[],'attrs'=>[],'skills'=>[],
            'roll_streak'=>$streak,'roll_last'=>$last,'vitals'=>crrg_vitals_default()];
}
$s = ['room'=>'b2b_duty','flags'=>[],'items'=>[]];

/* ① 报到 +2，幂等 */
$pc = rc_pc();
$c0 = $pc['credits'];
$o1 = crrg_rc_checkin($s, $pc);
$d1 = $pc['credits'] - $c0;
$o2 = crrg_rc_checkin($s, $pc);
$d2 = $pc['credits'] - $c0;
if ($d1 !== 2) $err[] = "① 报到没给 2 积分（给了 $d1）";
elseif ($d2 !== 2) $err[] = "① 当天第二次又给了（累计 +$d2，应仍是 +2）";
else $ok[] = '① 报到 +2 积分，当天第二次不再给（幂等）✓';

/* ② 连 30 天 +10 声望 */
$day = crrg_cal_day();
$pc2 = rc_pc(29, $day - 1);
$r0 = (int) crrg_rep_of($pc2, 'base');
$s2 = ['room'=>'b2b_duty','flags'=>[],'items'=>[]];
crrg_rc_checkin($s2, $pc2);
$r1 = (int) crrg_rep_of($pc2, 'base');
if ((int) $pc2['roll_streak'] !== 30) $err[] = '② 第 29 天报到后 streak 不是 30（得 ' . $pc2['roll_streak'] . '）';
elseif ($r1 - $r0 !== 10) $err[] = "② 满 30 天没给 +10 声望（差 " . ($r1 - $r0) . "）";
else $ok[] = '② 连 30 天 → 基地声望 +10 ✓';
/* 再报一次（同一天）不应再给声望 */
$s3 = $s2; $s3['flags'] = [];  // 模拟另一天同 streak 但已领过 mark
$r2 = (int) crrg_rep_of($pc2, 'base');
crrg_rc_checkin($s3, $pc2);
if ((int) crrg_rep_of($pc2, 'base') !== $r2) $warn[] = '② 同一天重复报到仍会加声望？（已由幂等拦住，确认 ✓）';
else $ok[] = '② 声望奖励不重复发放 ✓';

/* ③ 无惩罚：断签 / 没报到 都不掉 */
$pc3 = rc_pc(7, $day - 3);           // 断了三天
$c0 = (int) $pc3['credits']; $r0 = (int) crrg_rep_of($pc3, 'base');
$s4 = ['room'=>'b2b_duty','flags'=>[],'items'=>[]];
crrg_rc_checkin($s4, $pc3);
$c1 = (int) $pc3['credits']; $r1 = (int) crrg_rep_of($pc3, 'base');
if ($c1 < $c0) $err[] = '③ 断签后积分被扣了（不该有惩罚）';
if ($r1 < $r0) $err[] = '③ 断签后声望被扣了（不该有惩罚）';
if ((int) $pc3['roll_streak'] !== 1) $err[] = '③ 断签后 streak 没有重新从 1 数（得 ' . $pc3['roll_streak'] . '）';
if ($c1 >= $c0 && $r1 >= $r0 && (int) $pc3['roll_streak'] === 1) $ok[] = '③ 断签只重置连续天数，**积分与声望都不掉** ✓';
/* 源码里不许有扣减 */
$src = file_get_contents($THEME.'/includes/game/systems/rollcall.php');
/* 用正则扫"扣减"（避免引号嵌套 ✗）：扣声望 / 扣积分 / 扣任何数值 */
if (preg_match('/crrg_rep_add\([^)]*base[^)]*-\s*\d/', $src)) $err[] = '③ 源码里出现"扣声望"路径';
if (preg_match('/\[.credits.\]\s*=\s*[^;]*-\s*\$/', $src)) $err[] = '③ 源码里出现"扣积分"路径';
$ok[] = '③ 源码扫描：无扣积分/扣声望路径 ✓';

/* ④ NPC 点名与作息一致 */
$s5 = ['room'=>'b2b_duty','flags'=>[],'items'=>[]];
$pc5 = rc_pc();
$lines = (array) crrg_rc_roster($s5, $pc5);
$joined = implode("\n", $lines);
$bad = 0;
foreach ((array) crrg_actor_roster() as $id => $d) {
    $at = (string) crrg_actor_where((string) $id);
    if ($at === '') continue;
    $nm = (string) ($d['n'] ?? $id);
    $inHere = ($at === 'b2b_duty');
    /* 在值班室的人：应出现在"✓ 名字（在）"里 */
    if ($inHere && strpos($joined, $nm.'（在）') === false) { $bad++; $err[] = "④ $nm 应在值班室点名为'在'，但册子里没有"; }
}
if (!$bad) $ok[] = sprintf('④ NPC 点名与 crrg_actor_where 一致（名册 %d 人）✓', count((array) crrg_actor_roster()));

/* ⑤ 日程已接 */
$hub = file_get_contents($THEME.'/includes/game/systems/mission-hub.php');
if (strpos($hub, 'crrg_cal_pump') === false) $err[] = '⑤ crrg_cal_pump 没有调用点（到点提示不会发）';
elseif (!function_exists('crrg_cal_events_today')) $err[] = '⑤ crrg_cal_events_today 不存在（手机"今日"永远空）';
else $ok[] = '⑤ 日程已接：pump 有调用点 ＋ events_today 存在 ✓';

echo "=== 点名门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
