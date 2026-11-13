<?php
/**
 * CRRG 演习 · 善后与痕迹（systems/cleanup.php，R9-⑥，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："现场清理与'留没留口子'（接关注度）"
 * 三样：
 *   · **留痕**：动过的东西会留下痕迹（`$s['trace_<room>'][] = 条目`）——撬过的锁、踩过的泥、少了的单据
 *   · **清理**：花时间清掉；不清就留着，**有人来查时会被翻出来**（→ 暴露 +1、公共事实）
 *   · **接关注度**：痕迹条数会抬"这间屋被注意"，并写公共事实（别的任务/对话可订阅 ✓）
 */
if (!defined('ABSPATH')) exit;

/** 留一道痕（幂等：同一条不重复） */
function crrg_trace_add(&$s, $room, $text, $task = '') {
    $room = (string) $room; $text = (string) $text;
    if ($room === '' || $text === '') return false;
    $key = 'trace_' . $room;
    $s[$key] = (array) ($s[$key] ?? []);
    $row = ['t' => $text, 'task' => (string) $task, 'at' => time()];
    foreach ($s[$key] as $r) if (($r['t'] ?? '') === $text) return false;      // 幂等 ✓
    $s[$key][] = $row;
    return true;
}
/** 这间屋现在留着几道痕 */
function crrg_trace_list(&$s, $room) { return (array) ($s['trace_' . (string) $room] ?? []); }
function crrg_trace_count(&$s, $room) { return count(crrg_trace_list($s, $room)); }

/**
 * 清理：一道痕花 10 分钟、过个检定（手艺/侦查，失败＝没清干净 ✓ 失败推进）
 * @return array 给玩家看的行
 */
function crrg_trace_clean(&$s, &$pc, $room, $which = '') {
    $list = crrg_trace_list($s, $room);
    if (!$list) return ['（这间屋没留下什么。）'];
    $idx = null;
    foreach ($list as $i => $r) { if ($which === '' || mb_strpos((string) $r['t'], (string) $which) !== false) { $idx = $i; break; } }
    if ($idx === null) return ['（没找到那道痕。）'];
    $out = ['你把动手的地方又摸了一遍。'];
    $skill = '侦查'; $val = function_exists('crrg_coc_skill_val') ? max(1, crrg_coc_skill_val($pc, $skill)) : 25;
    $r = crrg_coc_check($skill, $val); $out[] = $r['text'];
    $rank = function_exists('crrg_coc_level_rank') ? crrg_coc_level_rank($r['level']) : 2;
    if ($rank >= 2) {
        unset($s['trace_' . $room][$idx]);
        $s['trace_' . $room] = array_values((array) $s['trace_' . $room]);
        $out[] = '（清干净了。）';
    } else {
        $out[] = '（只弄平了一半——反而多了一道新痕。）';
        crrg_trace_add($s, $room, '被抹过又没抹干净的痕');
    }
    if (function_exists('crrg_evt_emit')) crrg_evt_emit('mission.beat', ['kind' => 'clean', 'room' => $room, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'cleanup']);
    return $out;
}

/** 有人来查（由任务/世界调用）：把还没清的痕翻出来 → 暴露 +1；够多就写公共事实 ✓ */
function crrg_trace_sweep(&$s, &$pc, $room, $task = '') {
    $n = crrg_trace_count($s, $room);
    if ($n <= 0) return [];
    $t = $task !== '' ? $task : 'scene';
    $s['expose_' . $t] = (int) ($s['expose_' . $t] ?? 0) + $n;
    $out = [sprintf('（这间屋留了 %d 处痕，被翻了出来。）', $n)];
    if (function_exists('crrg_fact_propose')) {
        crrg_fact_propose('base.现场留痕.' . $room, $n, ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], (int) ($pc['uid'] ?? 0));
    }
    return $out;
}
