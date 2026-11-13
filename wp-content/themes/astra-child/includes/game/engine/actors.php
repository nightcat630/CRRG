<?php
/**
 * CRRG 演习 · 任务人物与"时间段移动"（engine/actors.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 两件事：
 *   ① **统一口径**：NPC 现在在哪 → `crrg_actor_where()` 一律走这里（读名册自带的 `sched` 作息 ✓）
 *   ② **任务期覆盖**：任务可以让某人在某段时间待在别处（"老周这几天在 B3 蹲着"✓），
 *      并且**善后必须复原**（cleanup 里调 `crrg_actor_release()` ✓ 门禁会查）
 *
 * 多人注意：覆盖只按**世界时刻**判定（共享）✓；不写玩家档、不推世界钟 ✓
 */
if (!defined('ABSPATH')) exit;

/** 名册（老内容里的 sched：黎明/上午/正午/下午/黄昏/夜/深夜 → 房间） */
function crrg_actor_roster() {
    if (function_exists('crrg_game_npc_roster_data')) return (array) crrg_game_npc_roster_data();
    return [];
}

/** 当前时段名（与世界钟同源；没有世界钟时退回"上午"） */
function crrg_actor_phase() {
    if (function_exists('crrg_world_clock')) {
        $c = crrg_world_clock();
        if (!empty($c['phase'])) return (string) $c['phase'];
    }
    return '上午';
}

/** 名册里的"默认在哪" */
function crrg_actor_home($npc) {
    $r = crrg_actor_roster();
    if (!isset($r[$npc])) return '';
    $ph = crrg_actor_phase();
    $sched = (array) ($r[$npc]['sched'] ?? []);
    return (string) ($sched[$ph] ?? (reset($sched) ?: ''));
}

/* ══════════ 任务期覆盖（append-only 语义：只加"从某时刻起"的记录，不改过去） ══════════ */

function &crrg_actor_ovr() {
    $r = &crrg_drill_reg();
    if (!isset($r['actor_ovr'])) $r['actor_ovr'] = [];
    return $r['actor_ovr'];
}

/**
 * 任务期把某个 NPC 挪到别处（可按世界时段限定）
 * @param array $opt ['room'=>'b3_water', 'phases'=>['上午','下午'], 'why'=>'任务：水声']
 */
function crrg_actor_override($task, $npc, array $opt) {
    if (!isset(crrg_actor_roster()[$npc])) return false;                // 认得的 NPC 才允许（防手滑）
    $O = &crrg_actor_ovr();
    $O[$npc][] = ['task' => $task, 'room' => (string) ($opt['room'] ?? ''), 'phases' => (array) ($opt['phases'] ?? []),
                  'why' => (string) ($opt['why'] ?? ''), 'on' => true];
    return true;
}

/** 任务善后：撤掉它留下的所有覆盖 ✓ */
function crrg_actor_release($task) {
    $O = &crrg_actor_ovr();
    $n = 0;
    foreach ($O as $npc => $list) {
        foreach ($list as $i => $o) {
            if (($o['task'] ?? '') === $task) { $O[$npc][$i]['on'] = false; $n++; }
        }
    }
    return $n;
}

/** **唯一入口**：某人现在在哪（任务覆盖优先于作息） */
function crrg_actor_where($npc) {
    $ph = crrg_actor_phase();
    $O = &crrg_actor_ovr();
    foreach (array_reverse((array) ($O[$npc] ?? [])) as $o) {           // 后写的优先
        if (empty($o['on'])) continue;
        /* 任务善后 → 覆盖失效（NPC 回自己的作息）✓ 开关在事实表里，跨请求一致 ✓ */
        if (function_exists('crrg_fact_count') && !empty($o['task'])
            && crrg_fact_count('world.ovr_off.' . $o['task'], 'base') > 0) continue;
        if (empty($o['room'])) continue;
        if ($o['phases'] && !in_array($ph, (array) $o['phases'], true)) continue;
        return (string) $o['room'];
    }
    return crrg_actor_home($npc);
}

/** 这个人在不在某房间（任务/对话里常用） */
function crrg_actor_at($npc, $room) { return crrg_actor_where($npc) === $room; }

/** 现在这间屋里有哪些 NPC（面板/事件可用） */
function crrg_actors_here($room = null) {
    $out = [];
    foreach (crrg_actor_roster() as $npc => $d) {
        $w = crrg_actor_where($npc);
        if ($w === '') continue;
        if ($room === null || $w === $room) $out[$npc] = $w;
    }
    return $out;
}
