<?php
/**
 * 笔记系统（自 drill-hub.php 搬出）
 */
if (!defined('ABSPATH')) exit;

/* ==================================================================
 * 笔记（线索台账）2026-09-28
 *   模组把自己产出的旗标登记成「笔记条目」；玩家用「笔记 [任务]」查看
 *   已经拿到的条目（没拿到的不会出现），并可看到取得时的游戏时间。
 * ================================================================== */
function crrg_notes_reg() {
    $r = &crrg_drill_reg();
    if (!isset($r['notes'])) $r['notes'] = [];
    return $r['notes'];
}function crrg_note_register($mission, $flag, $title, $text, $group = '线索') {
    $r = &crrg_drill_reg();
    if (!isset($r['notes'])) $r['notes'] = [];
    $r['notes'][$mission][$flag] = ['t' => $title, 'd' => $text, 'g' => $group];
}function crrg_notes_of($mission) {
    $r = &crrg_drill_reg();
    return $r['notes'][$mission] ?? [];
}
/** 由现实时间戳换算游戏时间（用于标注「何时记下的」） *//** 「笔记」指令：列出某任务已收集的情报（按组排列） */
function crrg_note_lines(&$s, $pc, $query) {
    $query = trim((string) $query);
    $missions = crrg_drill_missions();
    $target = '';
    if ($query !== '') {
        foreach ($missions as $mid => $m) {
            if ($mid === $query || mb_strpos($m['name'], $query) !== false || mb_strpos($query, $m['name']) !== false) { $target = $mid; break; }
        }
        if (!$target && mb_strpos($query, '追书') !== false) $target = 'm_chase_01';
        if (!$target) return ['没有叫「' . $query . '」的任务。'];
    }
    /* 没写任务：列出有笔记可看的（在手优先） */
    if ($target === '') {
        $have = [];
        foreach ($missions as $mid => $m) {
            $n = 0;
            foreach (crrg_notes_of($mid) as $flag => $note) if (in_array($flag, (array) ($s['flags'] ?? []), true)) $n++;
            if ($n > 0) $have[] = $m['name'] . '（' . $n . ' 条）';
        }
        if (!$have) return ['还没有记下什么。'];
        $out = ['有笔记的任务：' . implode('、', $have)];
        $out[] = '用法：「笔记 ' . (crrg_drill_mission($pc['mission'] ?? '')['name'] ?? '追书人') . '」。';
        return $out;
    }
    $m = $missions[$target];
    /* 2026-10-14（R5）：笔记里显示【推断】（你把它读成了几 ✓ 没想通的不剧透 ✓） */
    $infer = function_exists('crrg_infer_note_lines') ? (array) crrg_infer_note_lines($s, $target) : [];
    $log = (array) ($s['flaglog'] ?? []);
    $got = [];
    foreach (crrg_notes_of($target) as $flag => $note) {
        if (!in_array($flag, (array) ($s['flags'] ?? []), true)) continue;
        $got[$note['g'] ?? '线索'][] = $note + ['flag' => $flag, 'at' => $log[$flag] ?? 0];
    }
    if (!$got) return ['〔' . $m['name'] . '〕笔记还是空的——你还没查到什么。'];
    $out = ['〔' . $m['name'] . ' · 笔记〕'];
    foreach ($got as $group => $items) {
        $out[] = '── ' . $group . ' ──';
        foreach ($items as $it) {
            $when = $it['at'] ? '（' . crrg_world_from_real($it['at']) . '）' : '';
            $out[] = '· ' . $it['t'] . $when;
            $out[] = '　' . $it['d'];
        }
    }
    if ($infer) { $lines = array_merge((array) ($lines ?? []), $infer); }
    return $out;
}
