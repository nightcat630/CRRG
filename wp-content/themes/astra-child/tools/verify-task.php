<?php
/**
 * CRRG 演习 · 任务定义门禁（tools/verify-task.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 查的是"作者手滑"和"半截活"（都踩过）：
 *   ① 阶段与善后：每个任务要有 stages（至少 1）与 cleanup ✓
 *   ② 线索闭环：clues 里每条都被 concl.of 引用 ≥1 次（"没有归处的线索"＝散点 ✗）
 *      且每条 concl.of 引用的线索**必须存在** ✓
 *   ③ 解法闭环：paths ≥ 2（多解法 ✓）；引用到的 clue/stage 必须存在 ✓
 *   ④ 人物闭环：newcomers/dlg/actors 引用的人要么在名册、要么在同任务 newcomers 里 ✓
 *   ⑤ 房间闭环：paths/actors/newcomers 引用的房间键必须存在 ✓（防"指到没有的房间"）
 *   ⑥ 文案卫生：玩家可见文案里不得出现内部键（stage_ / clue_ / concl_ / beat_）✓
 *   ⑦ 时间纪律：paths 的 cost 里不许出现"推世界钟"的字段 ✓（个人在线钟才是允许的）
 *
 * 用法：php tools/verify-task.php
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-14：**门禁不许留痕**（统一守卫：跑前记水位，跑完精确回收）✓ */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});

$err = [];

/** 2026-10-22：房间是否存在 —— **全场景**查（任务常在镇上/县城，不在当前场景） */
if (!function_exists('vt_room_exists')) {
    function vt_room_exists($rk) {
        $rk = (string) $rk;
        if ($rk === '') return true;
        if (function_exists('crrg_drill_rooms')) {
            $r = crrg_drill_rooms();
            if (isset($r[$rk])) return true;
        }
        $R = &crrg_drill_reg();
        foreach ((array) ($R['scenes'] ?? []) as $sc) if (isset($sc['rooms'][$rk])) return true;
        return false;
    }
}

 $warn = []; $ok = [];
/* 2026-10-22 新增 ⑤：**没接任务不许有交互点** —— 引擎必须提供门控，且未接任务时取出来是空 */
if (!function_exists('crrg_task_ents') || !function_exists('crrg_task_active')) {
    $err[] = '⑤ 引擎缺任务交互点门控（crrg_task_ents / crrg_task_active）';
} else {
    $s0 = ['room' => 'b2b_tech', 'flags' => [], 'items' => []];
    $pc0 = ['uid' => 0, 'name' => '（门禁）', 'vitals' => crrg_vitals_default(), 'items' => [], 'rep' => [], 'npc_fav' => []];
    $leak = crrg_task_ents($s0, $pc0, 'b2b_tech');
    if (!empty($leak)) $err[] = '⑤ 没接任务，却有 ' . count((array) $leak) . ' 个交互点漏出来';
    else $ok[] = '⑤ 未接任务时无任何任务交互点（门控生效）';
}

$tasks = function_exists('crrg_tasks') ? crrg_tasks() : [];
if (!$tasks) { echo "✗ 引擎没加载（crrg_tasks 为空）\n"; exit(1); }
$rooms = function_exists('crrg_drill_rooms') ? array_keys(crrg_drill_rooms()) : [];
$roster = function_exists('crrg_actor_roster') ? crrg_actor_roster() : [];

foreach ($tasks as $id => $t) {
    /* 2026-10-14（R6）：**桩**只提醒、不做结构性硬判 ✗（桩的内容由后续轮次填 ✓）
       —— 否则"链条不能断"与"多解法是硬要求"会互相打架 */
    $isStub = !empty($t['stub']);
    if (!empty($t['stub'])) $warn[] = "⏳ [$id] 还是**桩**（内容待填）——按总纲由后续轮次补 ✓";

    /* ① stages / cleanup */
    if (empty($t['stages'])) $err[] = "① [$id] 没有 stages（阶段是进度的一份真相）";
    if (empty($t['cleanup'])) $warn[] = "① [$id] cleanup 为假 —— 确认不需要善后（默认应交给引擎）";

    /* ② 线索闭环 */
    $clues = array_keys((array) $t['clues']);
    $used = [];
    foreach ((array) $t['concl'] as $cid => $c) {
        foreach ((array) ($c['of'] ?? []) as $k) {
            $used[$k] = 1;
            if (!in_array($k, $clues, true)) $err[] = "② [$id] 结论「{$cid}」引用了不存在的线索：$k";
        }
        $need = (int) ($c['need'] ?? 2);
        if ($need > count((array) ($c['of'] ?? []))) $err[] = "② [$id] 结论「{$cid}」要 $need 条，却只列了 " . count((array) ($c['of'] ?? [])) . " 条";
        if (count((array) ($c['of'] ?? [])) < 3) $warn[] = "② [$id] 结论「{$cid}」只有 " . count((array) ($c['of'] ?? [])) . " 条来源（侦探设计建议 ≥3 ✓）";
    }
    /* 2026-10-14（R5）：**"归处"可以是结论，也可以是推论槽位** ✓
       （委员长把"结论"换成了"推论"，线索的边就挂在 `infer[槽位].of` 上 ✓） */
    foreach ((array) ($t['infer'] ?? []) as $slot => $def) {
        foreach ((array) ($def['of'] ?? []) as $pair) foreach ((array) $pair as $k) $used[(string) $k] = 1;
    }
    foreach ($clues as $k) {
        if (empty($used[$k])) {
            if (!$isStub) $err[] = "② [$id] 线索「{$k}」既不在任何结论、也不在任何推论组合里 → 是散点不是链 ✗";
        }
    }

    /* ③ 解法闭环 */
    if (!$isStub && count((array) $t['paths']) < 2) $err[] = "③ [$id] 解法少于 2 条（多解法是硬要求）";
    foreach ((array) $t['paths'] as $pid => $p) {
        foreach ((array) ($p['gain']['clues'] ?? []) as $k) if (!in_array($k, $clues, true)) $err[] = "③ [$id] 解法「{$pid}」给了不存在的线索：$k";
        foreach ((array) ($p['gain_on_fail']['clues'] ?? []) as $k) if (!in_array($k, $clues, true)) $err[] = "③ [$id] 解法「{$pid}」失败分支给了不存在的线索：$k";
        if (!empty($p['gain']['stage']) && !isset($t['stages'][(string) $p['gain']['stage']])) $err[] = "③ [$id] 解法「{$pid}」跳到不存在的阶段：" . $p['gain']['stage'];
        if (!empty($p['fail_stage']) && !isset($t['stages'][(string) $p['fail_stage']])) $err[] = "③ [$id] 解法「{$pid}」失败阶段不存在：" . $p['fail_stage'];
    }

    /* ④ 人物闭环 */
    $mine = array_keys((array) $t['newcomers']);
    foreach (array_keys((array) $t['newcomers']) as $npc) {
        $n = (array) $t['newcomers'][$npc];
        if (empty($n['name'])) $err[] = "④ [$id] 新人物「{$npc}」没写 name（面板上会显示空）";
        if (empty($n['nodes'])) $err[] = "④ [$id] 新人物「{$npc}」没有对话树（那要他做什么？）";
        /* 2026-10-22：**房间要在全场景里查**（任务常在镇上/县城，不在当前场景 ✗） */
        if (!empty($n['room'])) {
            $rk = (string) $n['room'];
            if (!vt_room_exists($rk)) $err[] = "⑤ [$id] 新人物「{$npc}」的房间不存在：" . $rk;
        }
    }
    foreach (array_keys((array) $t['dlg']) as $npc) {
        if (!isset($roster[(string) $npc]) && !in_array((string) $npc, $mine, true)) $err[] = "④ [$id] dlg 注入的目标「{$npc}」既不在名册、也不是本任务新人物";
    }
    foreach ((array) $t['actors'] as $npc => $d) {
        if (!isset($roster[(string) $npc]) && !in_array((string) $npc, $mine, true)) $err[] = "④ [$id] actors 里的「{$npc}」不在名册";
        $rm = (string) ((array) $d)['room'];
        if ($rm !== '' && !vt_room_exists($rm)) $err[] = "⑤ [$id] actors 给「{$npc}」指的房间不存在：$rm";
    }

    /* ⑥ 文案卫生（扫定义里的字符串字面量） */
    $dump = json_encode($t, JSON_UNESCAPED_UNICODE);
    foreach (['stage_', 'clue_', 'concl_', 'beat_', 'cleanup_'] as $bad) {
        if (strpos($dump, $bad) !== false && strpos($bad, 'clue_') !== 0) {
            $warn[] = "⑥ [$id] 定义里出现了内部键前缀「{$bad}」——确认它不在玩家可见文案里 ✓";
        }
    }

    /* ⑥″ 键名白名单（P1.9，2026-10-14）：**写错字段名＝静默失效** ✗（踩过"写了没做"的坑） */
    $okNeed = ['flags','not_flags','item','n','money','rep','lv','who','fav','vitals','hour','weather','travel_node','players_here','tasks','duty_vacant','duty_is','roster_has','san_ok','no_trace','shop_at','traveling','team_role','gear_ok','infer_lean','contain_level','signal'];
    $okCost = ['minutes','expose','flag','san','hp','energy','food','water','money','items','rep','fav','sleep','trace','contain_log'];
    $okGain = ['clues','stage','stage_text','items','money','rep','fav','flags','fact','vitals','sleep','narrate','concl','duty_row','phone_post','contain_log'];
    foreach ((array) $t['paths'] as $pid => $p) {
        foreach (['need' => $okNeed, 'cost' => $okCost, 'gain' => $okGain] as $slot => $ok) {
            foreach (array_keys((array) ($p[$slot] ?? [])) as $k) {
                if (!in_array((string) $k, $ok, true)) {
                    $err[] = "⑥″ [$id] 解法「{$pid}」的 $slot 里有引擎不认的键「{$k}」→ 会静默失效 ✗（白名单见 engine/README.md）";
                }
            }
        }
        if (($p['type'] ?? '') === 'travel' && empty($p['dest'])) $err[] = "⑥″ [$id] 行程解法「{$pid}」没写 dest ✗";
        if (($p['type'] ?? '') === 'travel' && !empty($p['dest']) && function_exists('crrg_travel_destinations')
            && !isset(crrg_travel_destinations()[(string) $p['dest']])) {
            $err[] = "⑥″ [$id] 行程解法「{$pid}」的 dest 不是已知目的地：" . $p['dest'];
        }
        foreach ((array) ($p['need']['tasks'] ?? []) as $needTid) {
            if (!function_exists('crrg_task') || !crrg_task((string) $needTid)) {
                $err[] = "⑥″ [$id] 解法「{$pid}」要求的前置任务不存在：" . $needTid;
            }
        }
    }
    if (!empty($t['outcome']['on_expire']) && empty($t['legacy_deadline'])) {
        $m = function_exists('crrg_drill_mission') ? crrg_drill_mission($id) : null;
        if (empty($m['deadline_h'])) $warn[] = "⑥″ [$id] 写了 on_expire 但没写 deadline_h → 永远不会触发 ✓";
    }

    /* ⑥‴ 推论槽位（R5，2026-10-14）：① ≥2 组有效组合 ② 每个读法必须有 unlock（选了就有路 ✓） */
    foreach ((array) ($t['infer'] ?? []) as $slot => $def) {
        $of = (array) ($def['of'] ?? []);
        if (count($of) < 2) $err[] = "⑥‴ [$id] 推论槽位「{$slot}」只有 " . count($of) . " 组有效组合 ✗（至少 2 组，不是唯一解）";
        if (count($of) < 3) $warn[] = "⑥‴ [$id] 推论槽位「{$slot}」有效组合 " . count($of) . " 组（建议 ≥3 组，玩家拿哪两条都能推）";
        if (empty($def['text'])) $err[] = "⑥‴ [$id] 推论槽位「{$slot}」没写 text（概况＋推理，不说结论 ✓）";
        $opts = (array) ($def['options'] ?? []);
        if (count($opts) < 2) $err[] = "⑥‴ [$id] 推论槽位「{$slot}」少于两个读法 ✗";
        foreach ($opts as $k => $o) {
            $u = (array) ($o['unlock'] ?? []);
            if (empty($u['flags']) && empty($u['clues'])) $err[] = "⑥‴ [$id] 槽位「{$slot}」读法 $k 没有 unlock ✗（选了必须有事发生）";
            if (empty($o['label'])) $err[] = "⑥‴ [$id] 槽位「{$slot}」读法 $k 没有 label ✗";
        }
        /* 组合里的线索必须存在 ✓ */
        foreach ($of as $pair) {
            foreach ((array) $pair as $ck) if (!in_array((string) $ck, array_keys((array) $t['clues']), true)) {
                $err[] = "⑥‴ [$id] 槽位「{$slot}」的有效组合引用了不存在的线索：「{$k}」";
            }
        }
    }

    /* ⑥′ 对抗解法的纪律（P2.5，2026-10-14） */
    $hasFight = false; $hasSoft = false;
    foreach ((array) $t['paths'] as $pid => $p) {
        $ty = (string) ($p['type'] ?? '');
        if ($ty === 'fight') {
            $hasFight = true;
            if (empty($p['on_win']))  $err[] = "⑥′ [$id] 对抗解法「{$pid}」没写 on_win（赢了要改变什么？✗）";
            if (empty($p['on_lose'])) $err[] = "⑥′ [$id] 对抗解法「{$pid}」没写 on_lose（输了要留下什么？✗）";
            if (empty($p['npcs']) && empty($p['allow_default_npcs'])) {
                $warn[] = "⑥′ [$id] 对抗解法「{$pid}」没写 npcs——会用房内默认对手，确认是有意的 ✓";
            }
        } else {
            $hasSoft = true;
        }
    }
    if ($hasFight && !$hasSoft) $err[] = "⑥′ [$id] 只有对抗解法 ✗ —— 每个对抗点都必须留 ≥1 条非战斗解法（防「只能打」）";

    /* ⑦ 时间纪律 */
    foreach ((array) $t['paths'] as $pid => $p) {
        foreach ((array) ($p['cost'] ?? []) as $k => $v) {
            if (in_array($k, ['world', 'world_ts', 'clock', 'world_minutes'], true)) $err[] = "⑦ [$id] 解法「{$pid}」想推世界钟（{$k}）✗ —— 只允许个人在线钟（minutes）";
        }
    }
    $ok[] = sprintf('[%s] 阶段 %d ｜线索 %d ｜结论 %d ｜解法 %d ｜新人物 %d ｜注入 %d',
        $id, count((array) $t['stages']), count($clues), count((array) $t['concl']),
        count((array) $t['paths']), count($mine), count((array) $t['dlg']));
}

echo "=== 任务定义门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：任务 %d 个 ｜ 错误 %d ｜ 提醒 %d\n", count($tasks), count($err), count($warn));
exit($err ? 1 : 0);
