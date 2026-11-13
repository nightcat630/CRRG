<?php
/**
 * CRRG 演习 · 结算与善后（engine/outcome.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 委员长要的"任务结束后对该账号的 NPC 和世界产生的结果"，全在这一层：
 *
 *   outcome = [
 *     'personal' => ['credits'=>6,'money'=>200,'rep'=>['base'=>2],'items'=>['x'=>1],'flags'=>[…]],
 *     'facts'    => [ ['base.水泵房修好了', true, 'once'], … ],      // 公共后果（进事实表 ✓）
 *     'actors'   => ['zhou'=>['rep'=>1,'fav'=>1,'say'=>'老周记住了你']],// 对 NPC 的结果
 *     'world'    => ['note'=>'B3 的水声停了'],                        // 世界侧留痕（写事实/日志）
 *     'archive'  => ['title'=>'…','type'=>'事件','level'=>'operative'], // 归档成档案（可被后续任务引用）
 *   ]
 *
 * 善后（cleanup）**必做三件**：
 *   ① 撤对话注入与临时新人物（`crrg_dlg_release` ✓）
 *   ② NPC 位置复原（`crrg_actor_release` ✓）
 *   ③ 把线索**留在台账**（不删 ✓ 玩家以后还能翻）
 */
if (!defined('ABSPATH')) exit;

/** 结算产出 */
function crrg_task_outcome_apply(&$s, &$pc, $task, $result = 'done') {
    $t = crrg_task($task);
    if (!$t) return false;
    $o = (array) ($t['outcome'][$result] ?? $t['outcome']['done'] ?? []);
    $uid = (int) ($pc['uid'] ?? 0);
    $out = [];

    /* ① 个人后果 */
    $p = (array) ($o['personal'] ?? []);
    if (!empty($p['credits'])) $pc['credits'] = (int) ($pc['credits'] ?? 0) + (int) $p['credits'];
    if (!empty($p['money']))   $pc['money']   = (int) ($pc['money'] ?? 0) + (int) $p['money'];
    foreach ((array) ($p['rep'] ?? []) as $where => $n) {
        if (function_exists('crrg_rep_add')) crrg_rep_add($pc, (string) $where, (int) $n, '任务结算：' . ($t['id'] ?? ''));
    }
    foreach ((array) ($p['items'] ?? []) as $item => $n) {
        if (function_exists('crrg_drill_give')) crrg_drill_give($pc, (string) $item, (int) $n);
    }
    foreach ((array) ($p['flags'] ?? []) as $f) if (!in_array($f, (array) $s['flags'], true)) $s['flags'][] = (string) $f;

    /* ② 公共后果（进事实表 → 别的任务/对话可以订阅 ✓） */
    foreach ((array) ($o['facts'] ?? []) as $f) {
        $f = (array) $f;
        if (empty($f[0]) || !function_exists('crrg_fact_propose')) continue;
        [$w, $msg] = crrg_fact_propose((string) $f[0], $f[1] ?? true,
            ['mode' => $f[2] ?? 'once', 'scope' => $f[3] ?? 'base', 'need' => $f[4] ?? 2], $uid);
        if ($w && function_exists('crrg_evt_emit')) crrg_evt_emit('world.fact', ['k' => $f[0]], ['src' => 'engine.outcome']);
    }

    /* ③ 对 NPC 的结果（人情/声望/以后见面的态度） */
    foreach ((array) ($o['actors'] ?? []) as $npc => $d) {
        $d = (array) $d;
        if (!empty($d['fav']) && function_exists('crrg_npc_fav_add')) crrg_npc_fav_add($pc, (string) $npc, (int) $d['fav']);
        elseif (!empty($d['fav'])) { $pc['npc_fav'][$npc] = (int) ($pc['npc_fav'][$npc] ?? 0) + (int) $d['fav']; }
        if (!empty($d['flag'])) { $s['flags'][] = (string) $d['flag']; }        // 例：npc_周德海_欠你一次
    }

    /* ④ 归档（能被后续任务引用 ✓；写不进也不影响结算 ✓） */
    $a = (array) ($o['archive'] ?? []);
    if ($a && function_exists('wp_insert_post') && $uid > 0) {
        $pid = wp_insert_post(['post_title' => (string) ($a['title'] ?? ('【任务档案】' . ($t['name'] ?? $task))),
            'post_content' => (string) ($a['body'] ?? ('结果：' . $result . "\n任务：" . ($t['name'] ?? $task))),
            'post_status' => 'private', 'post_type' => 'post'], true);
        if (!is_wp_error($pid)) {
            update_post_meta($pid, 'crrg_report_type_name', (string) ($a['type'] ?? '事件'));
            update_post_meta($pid, 'crrg_access_level', (string) ($a['level'] ?? 'operative'));
            update_post_meta($pid, 'crrg_task_archive', $task);
        }
    }

    /* ⑤ 世界侧留痕 */
    if (!empty($o['world']['note']) && function_exists('crrg_fact_add')) {
        crrg_fact_add('world.note.' . $task, (string) $o['world']['note'], 'base', $uid);
    }
    return true;
}

/** 善后：撤对话/撤新人/复原 NPC 位置（**必做** ✓ 门禁查 cleanup 声明） */
function crrg_task_cleanup(&$s, &$pc, $task, $result = 'done') {
    $n = 0;
    if (function_exists('crrg_dlg_release')) $n += (int) crrg_dlg_release($task);   // ① 对话回收 + ② 位置复原
    if (function_exists('crrg_actor_release')) crrg_actor_release($task);
    $s['flags'][] = 'cleanup_' . $task;                                            // 记一笔：已善后（幂等）
    /* 全局开关：写进事实表 → **跨请求、跨玩家**一致（"任务结束失去对话"才真的成立 ✓） */
    if (function_exists('crrg_fact_add')) crrg_fact_add('world.ovr_off.' . $task, true, 'base', (int) ($pc['uid'] ?? 0));
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'cleanup', 'task' => $task, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.outcome']);
    }
    return true;
}

/** 节拍（beats）：局面自己往前走 —— 由 crrg_task_pump 每次动作调 ✓（幂等） */
function crrg_task_beats_run(&$s, &$pc, $task) {
    $t = crrg_task($task);
    if (!$t || crrg_task_stage($s, $task) === '') return [];
    $out = [];
    foreach ((array) $t['beats'] as $bid => $b) {
        $flag = 'beat_' . $task . '_' . $bid;
        if (in_array($flag, (array) $s['flags'], true)) continue;                  // 幂等 ✓
        $ok = true;
        foreach ((array) ($b['when']['flags'] ?? []) as $f) if (!in_array($f, (array) $s['flags'], true)) $ok = false;
        foreach ((array) ($b['when']['clues'] ?? []) as $cl) if (function_exists('crrg_clue_has') && !crrg_clue_has($s, $task, $cl)) $ok = false;
        if (!empty($b['when']['concl']) && function_exists('crrg_concl_has') && !crrg_concl_has($s, $task, (string) $b['when']['concl'])) $ok = false;
        if (!empty($b['when']['fact']) && function_exists('crrg_fact_count') && crrg_fact_count((string) $b['when']['fact'], 'base') <= 0) $ok = false;
        if (!$ok) continue;
        $s['flags'][] = $flag;
        foreach ((array) ($b['do']['narrate'] ?? []) as $x) $out[] = (string) $x;
        if (!empty($b['do']['stage']) && function_exists('crrg_task_stage_set')) crrg_task_stage_set($s, $pc, $task, (string) $b['do']['stage']);
        if (!empty($b['do']['fact']) && function_exists('crrg_fact_add')) crrg_fact_add((string) $b['do']['fact'], true, 'base', 0);
        if (function_exists('crrg_evt_emit')) {
            crrg_evt_emit('mission.beat', ['kind' => 'world', 'task' => $task, 'beat' => $bid, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.beats']);
        }
    }
    return $out;
}
