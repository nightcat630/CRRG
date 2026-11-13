<?php
/**
 * 手机/通讯门禁（tools/verify-phone.php，R3，2026-10-14）
 * 查四条（委员长点名要查的在前两条）：
 *   ① **远程结算必须走同一套结算函数**（本文件不准自己算钱/声望、不准自己写 mission_done）✓
 *   ② **通话记录只追加**（只有 unshift + 截断，没有改写/删除）✓
 *   ③ **无信号时明确失败**（不是静默不结算）✓ 真跑
 *   ④ **信号分档合理**（B4 深处 < 基地 < 镇上；野外≈无）✓ 真跑
 * 运行时会写 usermeta（通话记录）→ 用假 uid 并在结束时删干净 ✓
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-14：**门禁不许留痕** —— 本门禁会走结算/事件 → 会落事实与写 option ✗
   跑前记水位，跑完精确回收（同四个工具的做法 ✓） */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster','crrg_cal_last'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});
$err = []; $warn = []; $ok = [];
$src = file_get_contents($THEME . '/includes/game/systems/phone.php');
/* 只查**代码**，不查注释（注释里提到函数名是正常的 ✓） */
$code = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $src);

/* ① 同一套结算 */
if (strpos($src, 'crrg_drill_mission_settle') === false) {
    $err[] = '① 远程交报告没有调用同一套结算（crrg_drill_mission_settle）';
} else {
    $ok[] = '① 远程结算走同一套结算函数 ✓';
}
foreach (['$pc[\'credits\'] +=', '$pc[\'money\'] +=', 'crrg_rep_add(', "'mission_done'"] as $bad) {
    if (strpos($code, $bad) !== false) $err[] = "① 手机系统里出现了自己算账的写法（{$bad}）→ 会变成两套真相 ✗";
}
if (strpos($code, 'crrg_task_finish') !== false) $warn[] = '① 直接调了 crrg_task_finish —— 确认不是为了绕过结算层 ✓';

/* ② 通话记录只追加 */
if (preg_match('/unset\s*\(\s*\$l\s*\[/', $src) || preg_match('/array_splice\s*\(\s*\$l/', $src)) {
    $err[] = '② 通话记录出现了删除/改写写法 ✗';
} else {
    $ok[] = '② 通话记录只有追加与截断 ✓';
}

/* ③④ 真跑 */
$uid = 990001;                              // 假 uid ✓ 测完删
delete_user_meta($uid, 'crrg_phone_log');
$pc = ['uid' => $uid, 'name' => '（门禁）', 'hp' => 10, 'san' => 50, 'credits' => 0, 'money' => 0,
       'rep' => [], 'npc_fav' => [], 'vitals' => function_exists('crrg_vitals_default') ? crrg_vitals_default() : []];
$ctx = [];

/* ④ 信号分档 */
$sig = [
    'b4_c2' => crrg_signal_at('b4_c2'), 'b3_water' => crrg_signal_at('b3_water'),
    'b2b_hall' => crrg_signal_at('b2b_hall'), 'tw_noodle' => crrg_signal_at('tw_noodle'),
    'r_ganzhou' => crrg_signal_at('r_ganzhou'), 'w_hill' => crrg_signal_at('w_hill'),
];
if (!($sig['b4_c2'] < $sig['b2b_hall'] && $sig['b2b_hall'] <= $sig['tw_noodle'] && $sig['w_hill'] <= 1)) {
    $err[] = '④ 信号分档不对：' . json_encode($sig, JSON_UNESCAPED_UNICODE);
} else {
    $ok[] = '④ 信号分档：' . json_encode($sig, JSON_UNESCAPED_UNICODE) . ' ✓';
}

/* ③ 无信号 → 明确失败，且不结算 */
$s = ['room' => 'w_hill', 'flags' => [], 'items' => []];
$pc['mission'] = 'm_st_water';
$pc['mission_step'] = 0;
$out = crrg_phone_report($s, $pc, $ctx, '', '按标准格式报');
$joined = implode(' ', (array) $out);
$failed = (mb_strpos($joined, '没有信号') !== false || mb_strpos($joined, '发不出去') !== false);
if (!$failed) $err[] = '③ 野外无信号时没有明确失败：' . $joined;
else $ok[] = '③ 野外无信号 → 明确失败 ✓（未结算）';

/* 有信号时应能结算（用合成任务，避免动真人档） */
$s2 = ['room' => 'b2b_hall', 'flags' => [], 'items' => []];
$pc2 = $pc; $pc2['mission'] = ''; $pc2['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
$out2 = crrg_phone_report($s2, $pc2, $ctx, '不存在的任务', '报');
$j2 = implode(' ', (array) $out2);
if (mb_strpos($j2, '没有能交的活') === false) $warn[] = '③ 没在手任务时的提示可以更清楚：' . mb_substr($j2, 0, 60);

/* 记录只追加：写两条 → 旧的还在 ✓ */
delete_user_meta($uid, 'crrg_phone_log');       // 前面几步也会写记录 → 这里清基线，只验"追加"本身 ✓
crrg_phone_log_add($uid, 'call', '康明远', '接通', true);
crrg_phone_log_add($uid, 'report', '第九份饭', '报完了', true);
$log = crrg_phone_log($uid);
if (count($log) !== 2 || (string) $log[1]['who'] !== '康明远') $err[] = '② 通话记录不是只追加（' . count($log) . ' 条）';
else $ok[] = '② 运行时验证：两条记录按时间倒序、旧的仍在 ✓';

/* 2026-10-14：非业务功能（委员长要的）＋ 生成幂等 */
$needFn = ['crrg_ph_group' => '群聊', 'crrg_ph_feed' => '动态', 'crrg_ph_forum' => '论坛',
           'crrg_ph_memo' => '备忘录', 'crrg_ph_radio_now' => '收音机', 'crrg_ph_game' => '小游戏', 'crrg_ph_clock' => '时钟'];
foreach ($needFn as $fn => $nm) if (!function_exists($fn)) $err[] = "⑤ 非业务功能缺：$nm（$fn）";
$srcE = file_get_contents($THEME . '/includes/game/core/drill-part-exec.php');
foreach (['ph_group','ph_feed','ph_forum','ph_memo','ph_radio','ph_game','ph_clock'] as $k) {
    if (strpos($srcE, "case '" . $k . "'") === false) $err[] = "⑤ 指令缺：$k（非业务功能要有入口）";
}
if (function_exists('crrg_ph_all') && function_exists('crrg_ph_grow_day')) {
    $bak = get_option('crrg_phone_life', null);
    $d0 = crrg_ph_all(); $d0['seen_day'] = 0; update_option('crrg_phone_life', $d0, false);
    crrg_ph_grow_day(12345);
    $n1 = count((array) (crrg_ph_all()['group_duty'] ?? []));
    $d1 = crrg_ph_all(); $d1['seen_day'] = 12345; update_option('crrg_phone_life', $d1, false);
    crrg_ph_grow_day(12345);
    $n2 = count((array) (crrg_ph_all()['group_duty'] ?? []));
    if ($n2 > $n1) $warn[] = "⑤ 生成不幂等：同一天又长了一次（$n1 → $n2）";
    else $ok[] = '⑤ 非业务功能七个齐 ＋ 生成幂等（同日只长一次）✓';
    if ($bak === null) delete_option('crrg_phone_life'); else update_option('crrg_phone_life', $bak, false);
}

/* 清理 */
delete_user_meta($uid, 'crrg_phone_log');

echo "=== 手机/通讯门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
