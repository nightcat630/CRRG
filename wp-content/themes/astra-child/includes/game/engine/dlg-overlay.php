<?php
/**
 * CRRG 演习 · 对话注入（engine/dlg-overlay.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 委员长要的三件，都在这里：
 *   ① **新人物**：任务专用 NPC（位置/时段/对话树），**任务结束即失去对话** ✓
 *   ② **老人物插入**：往既有 NPC 的树里**插一条支线**（不改原树 ✓），可**按阶段/线索**生效 ✓
 *   ③ **结束回收**：`crrg_dlg_release($task)` 一撤全撤 ✓
 *
 * 设计要点：
 *   · 注入**不碰原树**：`crrg_dlg_overlay_apply()` 返回合并后的**新数组** ✓（原树永远干净 ✓）
 *   · 注入的节点 id 一律加前缀 `t<task>_`，**防撞名** ✓
 *   · 新人物不进 dlg2 注册表，**只活在覆盖层**里 → 结束后自然消失 ✓（"失去对话"是结构性的，不靠人记得删 ✓）
 *   · 生效条件走**同一套 cond**（flag / not_flag / all_flags / item / rep …）＋ `stage`（任务阶段）✓
 */
if (!defined('ABSPATH')) exit;

function &crrg_dlg_ovr() {
    $r = &crrg_drill_reg();
    if (!isset($r['dlg_ovr'])) $r['dlg_ovr'] = [];
    return $r['dlg_ovr'];
}

/**
 * 往**老人物**树里插一条支线
 * @param array $def ['id'=>节点名,'stage'=>'s1','cond'=>[…],'from'=>'hub|',  'label'=>'追问：那扇门',
 *                    'node'=>['text'=>[…],'choices'=>[…]], 'priority'=>10, 'once'=>true]
 */
function crrg_dlg_inject($task, $npc, array $def) {
    $O = &crrg_dlg_ovr();
    $O[$npc]['inject'][] = $def + ['task' => $task, 'on' => true];
    return true;
}

/**
 * 注册一个**任务专用新人物**（只在任务期存在）
 * @param array $def ['name'=>'老聂','role'=>'水道工','room'=>'b3_water','phases'=>['上午','下午'],
 *                    'entry'=>'n1','nodes'=>[…], 'why'=>'任务：水声']
 */
function crrg_dlg_newcomer($task, $npcId, array $def) {
    $O = &crrg_dlg_ovr();
    $O[$npcId]['newcomer'] = $def + ['task' => $task, 'id' => $npcId, 'on' => true];
    /* 位置也走 actors 覆盖（同一条善后线 ✓） */
    if (function_exists('crrg_actor_ovr')) {
        $Ov = &crrg_actor_ovr();
        $Ov[$npcId][] = ['task' => $task, 'room' => (string) ($def['room'] ?? ''), 'phases' => (array) ($def['phases'] ?? []),
                         'why' => (string) ($def['why'] ?? ''), 'on' => true, 'newcomer' => true,
                         'name' => (string) ($def['name'] ?? ''), 'role' => (string) ($def['role'] ?? '')];
    }
    return true;
}

/** 这个任务期里，这位（新人/老人）是否还在场 */
function crrg_dlg_actor_alive($task, $npc) {
    $O = &crrg_dlg_ovr();
    if (!empty($O[$npc]['newcomer'])) {
        $c = $O[$npc]['newcomer'];
        return ($c['task'] ?? '') === $task && !empty($c['on']);
    }
    return true;                                        // 老人物一直在 ✓
}

/** 注入项是否生效（阶段 + cond 都过） */
function crrg_dlg_inject_live(&$s, &$pc, array $def, $task) {
    if (empty($def['on'])) return false;
    /* 任务已善后（全局开关写在事实表里 ✓ 跨请求/跨玩家一致）→ 一律不生效 ✓ */
    if (function_exists('crrg_fact_count') && crrg_fact_count('world.ovr_off.' . $task, 'base') > 0) return false;
    if (!empty($def['stage']) && function_exists('crrg_task_stage')) {
        $order = array_keys((array) (crrg_task($task)['stages'] ?? []));
        $cur = crrg_task_stage($s, $task);
        $ci = $cur === '' ? -1 : array_search($cur, $order, true);
        $ni = array_search((string) $def['stage'], $order, true);
        if ($ni === false || $ci === false || $ni > $ci) return false;   // 还没走到那一步 ✓
    }
    if (!empty($def['cond']) && function_exists('crrg_dlg2_ok')) {
        if (!crrg_dlg2_ok($s, $pc, (array) $def['cond'])) return false;
    }
    if (!empty($def['clue']) && function_exists('crrg_clue_has')) {
        if (!crrg_clue_has($s, $task, (string) $def['clue'])) return false;
    }
    return true;
}

/**
 * **唯一的树合并入口**（dialogue2.php 的 crrg_dlg2_tree 在拿到 $s/$pc 时调它 ✓）
 * 返回合并后的新树；原树不动 ✓
 */
function crrg_dlg_overlay_apply($npc, $tree, &$s, &$pc) {
    $O = &crrg_dlg_ovr();
    if (empty($O[$npc])) return $tree;

    /* ① 新人物：树完全来自覆盖层 */
    if (!empty($O[$npc]['newcomer'])) {
        $c = $O[$npc]['newcomer'];
        if (empty($c['on'])) return ['nodes' => [], 'entry' => ''];     // 已回收 → 没有对话 ✓
        if (function_exists('crrg_fact_count') && crrg_fact_count('world.ovr_off.' . ($c['task'] ?? ''), 'base') > 0) {
            return ['nodes' => [], 'entry' => ''];                      // 任务善后 → 新人物消失 ✓
        }
        return ['npc' => $npc, 'entry' => (string) ($c['entry'] ?? array_key_first((array) $c['nodes'])),
                'nodes' => (array) ($c['nodes'] ?? []), 'newcomer' => true, 'name' => (string) ($c['name'] ?? '')];
    }

    /* ② 老人物：插支线 */
    $list = (array) ($O[$npc]['inject'] ?? []);
    if (!$list) return $tree;
    usort($list, fn($a, $b) => (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0));
    $tree['nodes'] = (array) ($tree['nodes'] ?? []);
    foreach ($list as $def) {
        if (!crrg_dlg_inject_live($s, $pc, $def, (string) ($def['task'] ?? ''))) continue;
        $nid = 't' . ($def['task'] ?? 'x') . '_' . (string) ($def['id'] ?? count($tree['nodes']));
        if (isset($tree['nodes'][$nid])) continue;                        // 幂等 ✓
        $tree['nodes'][$nid] = (array) ($def['node'] ?? []);
        $from = (string) ($def['from'] ?? '');
        if ($from === '' || !isset($tree['nodes'][$from])) {
            /* 没指定挂点：挂在 entry 上（没有 entry 就挂在第一个节点） */
            $from = (string) ($tree['entry'] ?? array_key_first($tree['nodes']));
        }
        if ($from !== '' && isset($tree['nodes'][$from])) {
            $tree['nodes'][$from]['choices'] = (array) ($tree['nodes'][$from]['choices'] ?? []);
            $tree['nodes'][$from]['choices'][] = ['label' => (string) ($def['label'] ?? '（追问）'),
                                                  'goto' => $nid, 'cond' => (array) ($def['cond'] ?? [])];
        }
    }
    return $tree;
}

/** 善后：撤掉这个任务的全部注入与新人（"任务结束失去对话" ✓） */
function crrg_dlg_release($task) {
    $O = &crrg_dlg_ovr();
    $n = 0;
    foreach ($O as $npc => $d) {
        if (!empty($d['newcomer']) && ($d['newcomer']['task'] ?? '') === $task) {
            $O[$npc]['newcomer']['on'] = false; $n++;
        }
        foreach ((array) ($d['inject'] ?? []) as $i => $def) {
            if (($def['task'] ?? '') === $task) { $O[$npc]['inject'][$i]['on'] = false; $n++; }
        }
    }
    if (function_exists('crrg_actor_release')) crrg_actor_release($task);
    return $n;
}
