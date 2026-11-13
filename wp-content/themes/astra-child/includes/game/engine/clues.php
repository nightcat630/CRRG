<?php
/**
 * CRRG 演习 · 线索系统（engine/clues.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 治的是 15 号文里那条**最要命**的病：55 条线索**零登记**、玩家拿到线索无处回看、不知和谁有关。
 *
 * 三件事：
 *   ① 线索**定义即登记**：`crrg_clue_add()` 会把线索登记进**笔记台账**（notes.php）→ 玩家「笔记 <任务>」能看
 *   ② 线索**带边**：每条线索写清 `src`（从哪来）/ `link`（和哪几条有关）/ `to`（归到哪个结论）
 *   ③ 结论层（CLUE → CONCLUSION → ACTION）：`crrg_concl_check()` 够了 N 条就把结论置位 ✓
 *      **网要有边，边就是"线索→结论"** ✗ 没有结论层的"网"就是散点 ✓
 */
if (!defined('ABSPATH')) exit;

/** 登记一条线索（作者在任务定义里写 clues，引擎自动登记；也可单独调） */
function crrg_clue_add($task, $key, array $def) {
    $R = &crrg_drill_reg();
    $R['clue'][$task][$key] = $def + ['key' => $key, 'task' => $task];
    /* 顺手登记进笔记台账（一份真相：玩家侧的「笔记」直接读它 ✓） */
    if (function_exists('crrg_note_register')) {
        $title = (string) ($def['title'] ?? $key);
        $text  = (string) ($def['note'] ?? $def['point'] ?? '');
        $group = (string) ($def['group'] ?? '线索');
        /* 边也写进正文（玩家看得见"它和谁有关"✓） */
        if (!empty($def['link'])) {
            $names = [];
            foreach ((array) $def['link'] as $lk) {
                $d = $R['clue'][$task][$lk] ?? null;
                if ($d) $names[] = (string) ($d['title'] ?? $lk);
            }
            if ($names) $text = rtrim($text, '。') . '。' . '（关联：' . implode('、', $names) . '）';
        }
        crrg_note_register($task, 'clue_' . $task . '_' . $key, $title, $text, $group);
    }
    return true;
}

/** 批量登记任务里所有的线索（定义任务时调一次） */
function crrg_clues_install($task) {
    $t = crrg_task($task);
    if (!$t) return 0;
    $n = 0;
    foreach ((array) $t['clues'] as $key => $def) { crrg_clue_add($task, $key, (array) $def); $n++; }
    return $n;
}

/** 玩家**拿到**一条线索：置旗标 + 冲结论 + 提示（幂等） */
function crrg_clue_gain(&$s, &$pc, $task, $key) {
    $flag = 'clue_' . $task . '_' . $key;
    if (in_array($flag, (array) ($s['flags'] ?? []), true)) return false;    // 幂等 ✓
    $s['flags'][] = $flag;
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'clue', 'task' => $task, 'clue' => $key, 'uid' => (int) ($pc['uid'] ?? 0)],
                      ['src' => 'engine.clues']);
    }
    crrg_concl_check($s, $pc, $task);
    return true;
}

/** 有没有这条线索 */
function crrg_clue_has(&$s, $task, $key) {
    return in_array('clue_' . $task . '_' . $key, (array) ($s['flags'] ?? []), true);
}

/**
 * 结论层：每条结论声明"要哪几条线索、够几条就成立"
 *   concl = ['结案方向' => ['need' => 2, 'of' => ['a','b','c'], 'text' => '…']]
 */
function crrg_concl_check(&$s, &$pc, $task) {
    $t = crrg_task($task);
    if (!$t) return [];
    $got = [];
    foreach ((array) $t['concl'] as $cid => $c) {
        $flag = 'concl_' . $task . '_' . $cid;
        if (in_array($flag, (array) ($s['flags'] ?? []), true)) continue;    // 已成立 ✓
        $of = (array) ($c['of'] ?? []);
        $have = 0;
        foreach ($of as $k) if (crrg_clue_has($s, $task, $k)) $have++;
        $need = max(1, (int) ($c['need'] ?? 2));
        if ($have >= $need) {
            $s['flags'][] = $flag;
            $got[] = $cid;
            if (function_exists('crrg_evt_emit')) {
                crrg_evt_emit('mission.beat', ['kind' => 'conclusion', 'task' => $task, 'concl' => $cid, 'uid' => (int) ($pc['uid'] ?? 0)],
                              ['src' => 'engine.clues']);
            }
        }
    }
    return $got;
}

/** 结论是否成立 */
function crrg_concl_has(&$s, $task, $cid) {
    return in_array('concl_' . $task . '_' . $cid, (array) ($s['flags'] ?? []), true);
}

/** 「线索板」：已得 / 未得 / 关联（面板与「笔记」都能用 ✓） */
function crrg_clue_board(&$s, $pc, $task) {
    $t = crrg_task($task);
    if (!$t) return ['没有这个任务。'];
    $out = [];
    $got = 0; $all = 0;
    foreach ((array) $t['clues'] as $key => $d) {
        $all++;
        if (!crrg_clue_has($s, $task, $key)) continue;
        $got++;
        $line = '· ' . ($d['title'] ?? $key);
        if (!empty($d['to']))    $line .= '　→ 归入结论「' . ($t['concl'][$d['to']]['name'] ?? $d['to']) . '」';
        if (!empty($d['src']))   $line .= '　（来源：' . $d['src'] . '）';
        $out[] = $line;
    }
    if (!$out) return ['（还没记下任何线索。）'];
    array_unshift($out, sprintf('线索 %d/%d：', $got, $all));
    /* 未得的线索只给"去哪类地方找"的提示，不剧透 ✓ */
    $miss = [];
    foreach ((array) $t['clues'] as $key => $d) {
        if (crrg_clue_has($s, $task, $key)) continue;
        if (!empty($d['where'])) $miss[] = $d['where'];
    }
    if ($miss) $out[] = '（还没着落的：' . implode('、', array_unique($miss)) . '——去这些地方看看。）';
    foreach ((array) $t['concl'] as $cid => $c) {
        if (crrg_concl_has($s, $task, $cid)) {
            $out[] = '【结论】' . ($c['name'] ?? $cid) . '：' . (string) ($c['text'] ?? '');
        }
    }
    return $out;
}
