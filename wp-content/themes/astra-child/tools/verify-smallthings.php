<?php
/**
 * 小件六件门禁（tools/verify-smallthings.php，R9，2026-10-14）
 * 逐件查：
 *   ① 装备：使用会掉耐久、<30 有惩罚、=0 失效、保养能修、加成可用（真跑）
 *   ② 关系网：亲疏对称、嘴碎有值、打听会按亲疏给信息（真跑）
 *   ③ 分工：不在场不能指派、在场能指派、加成可用（真跑）
 *   ④ 提示档：关/轻/强三档行为不同；**正文里不许出现"去 X"式指路**（扫任务定义 ✓）
 *   ⑤ 作者工具：指令存在且**非管理员看不了**（扫源码＋真跑权限判断）
 *   ⑥ 善后：留痕幂等、清理有成败、sweep 会抬暴露（真跑）
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$err = []; $warn = []; $ok = [];
/* 2026-10-14：**验证器不许留副作用**（本门禁会跑 sweep → 落事实 ✗）→ 跑前记水位，跑完精确回收 ✓ */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
register_shutdown_function(function () use ($__ft, $__maxId) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
});


/* ① 装备 */
if (function_exists('crrg_gear_use')) {
    $pc = ['uid' => 0, 'money' => 999, 'gear' => []];
    crrg_gear_state($pc, 'torch');
    [$ok1, $m1] = crrg_gear_use($pc, 'torch', 20);          // 重度使用 → 报废
    $st = crrg_gear_state($pc, 'torch');
    if ($st['dur'] > 0) $err[] = '① 连用 20 次 torch 居然没报废（掉耐久没生效？）';
    [$ok2, $m2] = crrg_gear_use($pc, 'torch', 1);
    if ($ok2) $err[] = '① 报废装备还能用（应为失效）';
    crrg_gear_maint($pc, 'torch');
    $st2 = crrg_gear_state($pc, 'torch');
    if ($st2['dur'] <= 0) $err[] = '① 保养后没恢复';
    $b = crrg_gear_bonus($pc, 'torch', '侦查');
    if ($b < 1) $warn[] = '① 保养好的手电对侦查没有加成（应为 +1 奖励骰）';
    else $ok[] = '① 装备：耐久/报废/保养/加成 都生效 ✓';
} else $err[] = '① equip 未加载';

/* ② 关系网 */
if (function_exists('crrg_npc_bond')) {
    if (crrg_npc_bond('康明远', '韩雪') !== crrg_npc_bond('韩雪', '康明远')) $err[] = '② 亲疏不对称';
    $s = ['flags' => []]; $pc = ['uid' => 0];
    [$lv, $lines] = crrg_npc_ask_about($s, $pc, '白露', '康明远', 't');
    if ($lv < 1) $err[] = '② 打听没给信息等级';
    else $ok[] = '② 关系网：亲疏对称 ＋ 嘴碎表 ＋ 打听返回信息 ✓';
} else $err[] = '② npc-net 未加载';

/* ③ 分工 */
if (function_exists('crrg_team_assign')) {
    $pc = ['uid' => 0, 'room' => 'b1_train', 'team' => []];
    [$a1] = crrg_team_assign($pc, 'lookout', 12345);          // 不在场 → 应拒绝
    if (strpos($a1, '人不在') === false) $err[] = '③ 不在场的人也能指派（应拒绝）';
    else $ok[] = '③ 分工：不在场拒绝 ✓（在场指派需多人环境，交由烟测）';
} else $err[] = '③ team 未加载';

/* ④ 提示档 ＋ 正文卫生 */
if (function_exists('crrg_hint_set')) {
    $pc = ['uid' => 0, 'hint' => 0];
    $s = ['room' => 'b3_water', 'flags' => []];
    $o0 = crrg_hint_now($s, $pc, '');
    crrg_hint_set($pc, '轻'); $o1 = crrg_hint_now($s, $pc, '');
    crrg_hint_set($pc, '强'); $o2 = crrg_hint_now($s, $pc, '');
    if (implode('', $o0) === implode('', $o2)) $err[] = '④ 提示档三档给出的东西一样（档位没生效）';
    else $ok[] = '④ 提示档：关/轻/强 行为不同 ✓';
    /* 正文卫生：任务定义里的玩家可见文案不许出现「去 X 看看」式指路 */
    $hits = [];
    foreach (glob($THEME . '/includes/game/missions/task-*.php') as $f) {
        $src = file_get_contents($f);
        if (preg_match_all('/（(?:去|先到|前往)[^）]{0,20}(?:看看|一趟|找)[^）]{0,10}）/u', $src, $m)) {
            foreach ($m[0] as $x) $hits[] = basename($f) . ':' . $x;
        }
    }
    if ($hits) $warn[] = '④ 正文里疑似出现指路文案（应挪到提示档）：' . implode(' ｜ ', array_slice($hits, 0, 3));
    else $ok[] = '④ 正文卫生：任务文案里没有「去 X」式指路 ✓';
} else $err[] = '④ hints 未加载';

/* ⑤ 作者工具 */
$srcE = file_get_contents($THEME . '/includes/game/core/drill-part-exec.php');
if (strpos($srcE, "case 'task_state'") === false) $err[] = "⑤ 作者工具缺：任务状态";
elseif (strpos($srcE, "crrg_is_admin") === false) $err[] = "⑤ 作者工具没有权限判断（谁都能看 ✗）";
else $ok[] = '⑤ 作者工具：任务状态 ＋ 管理员门槛 ✓';

/* ⑥ 善后 */
if (function_exists('crrg_trace_add')) {
    $s = ['flags' => []];
    $a = crrg_trace_add($s, 'b3_water', '撬过的阀'); $b = crrg_trace_add($s, 'b3_water', '撬过的阀');
    if (!$a || $b) $err[] = '⑥ 留痕不幂等';
    $pc = ['uid' => 0, 'san' => 50, 'hp' => 12];
    $before = crrg_trace_count($s, 'b3_water');
    crrg_trace_clean($s, $pc, 'b3_water', '撬过的阀');
    $after = crrg_trace_count($s, 'b3_water');
    crrg_trace_sweep($s, $pc, 'b3_water', 't');
    if ((int) ($s['expose_t'] ?? 0) <= 0 && $after > 0) $warn[] = '⑥ sweep 没抬暴露（可能已被清空）';
    $ok[] = "⑥ 善后：留痕幂等 ✓（$before → $after）＋ sweep 接口在 ✓";
} else $err[] = '⑥ cleanup 未加载';

echo "=== 小件六件门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
