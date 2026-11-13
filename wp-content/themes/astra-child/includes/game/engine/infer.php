<?php
/**
 * CRRG 演习 · 推论机制（engine/infer.php，R5，2026-10-14）
 * ------------------------------------------------------------
 * 委员长定的玩法（20 号文）：**不是"对不对"，是"你把它读成什么"** ✓
 *
 *   玩家输：`推论 涂改 名录`（两条线索，顺序随便）
 *   KP 输出：概况描述 ＋ 简单推理（**不说结论**）→ 所以（）1，… 2，…
 *   玩家输：`推论 涂改 名录 1` → **写进笔记**（"你读成了 1"）＋ **解锁那条路** ＋ **轨道计数**
 *
 * 四条硬规矩（门禁 verify-task.php 会查）：
 *   ① 一个槽位**至少 2 组**有效组合（玩家拿哪两条来都推得动 ✓）
 *   ② 两个读法**都要说得通**（作者不做诱导 ✗）
 *   ③ **选了就有路**：每个读法必须有 `unlock` 非空 ✓（不许"选了没事干"）
 *   ④ 读法累积成**轨道**（人说 / 岗位说）→ 决定第五幕能选哪些结局 ✓
 *
 * 纪律：轨道计数只落**个人档**（每个人的解释可以不同 ✓ 不写公共 ✗）；
 *       改读允许，但要**掉一次"记录可信度"**（expose+1）并在笔记里注"已改口" ✓。
 */
if (!defined('ABSPATH')) exit;

function &crrg_infer_reg() {
    $r = &crrg_drill_reg();
    if (!isset($r['infer'])) $r['infer'] = [];
    return $r['infer'];
}

/**
 * 定义一个推论槽位
 * @param array $def ['of'=>[['涂改','名录'],['涂改','登记簿']],   // 有效组合（≥2 组）
 *                    'text'=>['概况…','推理…'],                    // 不说结论 ✓
 *                    'options'=>[ 1=>['label'=>'…','track'=>'ren','unlock'=>['flags'=>[],'clues'=>[],'act'=>'']], 2=>[…] ]]
 */
function crrg_infer_def($task, $slot, array $def) {
    $R = &crrg_infer_reg();
    $R[$task][$slot] = $def + ['slot' => $slot, 'task' => $task];
    /* 顺手把两个读法登记进笔记台账（玩家做了选择才会出现在笔记里 ✓） */
    if (function_exists('crrg_note_register')) {
        foreach ((array) ($def['options'] ?? []) as $k => $o) {
            $title = '推断：' . ($o['label'] ?? (string) $k);
            $body  = (string) ($o['note'] ?? $def['note'] ?? '');
            crrg_note_register($task, crrg_infer_flag($task, $slot, $k), $title, $body, '推断');
        }
    }
    return true;
}

function crrg_infer_slots($task) { $R = &crrg_infer_reg(); return (array) ($R[$task] ?? []); }
function crrg_infer_flag($task, $slot, $opt) { return 'infer_' . $task . '_' . $slot . '_' . $opt; }

/** 把玩家输入的词解析成线索键（先精确、再标题包含 ✓ 与「换法」同风格） */
function crrg_infer_clue_key($task, $word) {
    $word = trim((string) $word);
    if ($word === '') return '';
    $clues = (array) (crrg_task($task)['clues'] ?? []);
    if (isset($clues[$word])) return $word;
    /* 2026-10-22：**短标签优先**（玩家打不出长标题 ✓） */
    foreach ($clues as $k => $d) {
        if ((string) ($d['tag'] ?? '') === $word) return (string) $k;
    }
    foreach ($clues as $k => $d) {
        $title = (string) ($d['title'] ?? '');
        if ($title !== '' && (mb_strpos($title, $word) !== false || mb_strpos($word, $title) !== false)) return (string) $k;
    }
    return '';
}

/** 命中槽位（顺序无关 ✓）；返回 [slot, def] */
function crrg_infer_find($task, $a, $b) {
    foreach (crrg_infer_slots($task) as $slot => $def) {
        foreach ((array) ($def['of'] ?? []) as $pair) {
            $p = array_map('strval', (array) $pair);
            if ((in_array((string) $a, $p, true) && in_array((string) $b, $p, true)) && $a !== $b) return [(string) $slot, $def];
        }
    }
    return ['', []];
}

/** 玩家已经选过的读法（'' ＝ 还没选） */
function crrg_infer_read(&$s, $task, $slot) {
    foreach ((array) (crrg_infer_slots($task)[$slot]['options'] ?? []) as $k => $o) {
        if (in_array(crrg_infer_flag($task, $slot, $k), (array) ($s['flags'] ?? []), true)) return (string) $k;
    }
    return '';
}

/* ══════════ 推论：出文案（KP 口吻，不说结论）══════════ */

function crrg_infer_try(&$s, &$pc, $task, $a, $b) {
    $ka = crrg_infer_clue_key($task, $a); $kb = crrg_infer_clue_key($task, $b);
    if ($ka === '' || $kb === '') return ['（没听懂是哪两条——先「笔记」看看手上有哪几条。）'];
    if ($ka === $kb) return ['（这是同一条。）'];
    $hasA = function_exists('crrg_clue_has') && crrg_clue_has($s, $task, $ka);
    $hasB = function_exists('crrg_clue_has') && crrg_clue_has($s, $task, $kb);
    if (!$hasA || !$hasB) return ['（这两条里有一条你还没拿到。）'];

    [$slot, $def] = crrg_infer_find($task, $ka, $kb);
    if ($slot === '') {
        /* 组合无效：KP 一句，**不惩罚、不指路** ✓ */
        $t1 = (string) ((crrg_task($task)['clues'][$ka]['title'] ?? $ka));
        $t2 = (string) ((crrg_task($task)['clues'][$kb]['title'] ?? $kb));
        return [sprintf('这两条放在一起，说不通。一条讲「%s」，一条讲「%s」。', $t1, $t2)];
    }

    $out = [];
    foreach ((array) ($def['text'] ?? []) as $x) $out[] = (string) $x;
    $out[] = '所以（）';
    foreach ((array) ($def['options'] ?? []) as $k => $o) {
        $out[] = sprintf('　%d，%s', (int) $k, (string) ($o['label'] ?? ''));
    }
    $out[] = sprintf('（用法：「推论 %s %s <序号>」把它定下来。）', $a, $b);
    return $out;
}

/* ══════════ 推论：定下读法（写笔记 ＋ 轨道 ＋ 解锁）══════════ */

function crrg_infer_choose(&$s, &$pc, $task, $a, $b, $opt) {
    $ka = crrg_infer_clue_key($task, $a); $kb = crrg_infer_clue_key($task, $b);
    [$slot, $def] = ($ka !== '' && $kb !== '') ? crrg_infer_find($task, $ka, $kb) : ['', []];
    if ($slot === '') return ['（先把两条线索凑对，再来定。）'];
    $opt = (string) $opt;
    $options = (array) ($def['options'] ?? []);
    if (!isset($options[$opt])) return ['（没有这个读法。）'];

    $prev = crrg_infer_read($s, $task, $slot);
    $flag = crrg_infer_flag($task, $slot, $opt);
    $o = (array) $options[$opt];
    $out = [];

    if ($prev === $opt) return ['（你已经把它读成这个了——笔记里记着。）'];
    if ($prev !== '') {
        /* 改读：允许，但要付"记录可信度"的代价 ✓ 旧笔记保留并注已改口 */
        $s['expose_' . $task] = (int) ($s['expose_' . $task] ?? 0) + 1;
        $out[] = '（你在记录上改了口——同一件事，两种说法。经手的人会记得你改过。）';
        $s['flags'][] = 're_read_' . $task . '_' . $slot;
    }
    if (!in_array($flag, (array) $s['flags'], true)) $s['flags'][] = $flag;

    /* 轨道计数：只落个人档 ✓ */
    $track = (string) ($o['track'] ?? '');
    if ($track !== '') {
        $k = 'track_' . $task . '_' . $track;
        $pc[$k] = (int) ($pc[$k] ?? 0) + 1;
    }
    /* 解锁：写旗标（内容侧用 need.flags 接 ✓ 一处真相） */
    foreach ((array) ($o['unlock']['flags'] ?? []) as $f) if (!in_array($f, (array) $s['flags'], true)) $s['flags'][] = (string) $f;
    foreach ((array) ($o['unlock']['clues'] ?? []) as $ck) {
        if (function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, (string) $ck)) {
            $d = (array) (crrg_task($task)['clues'][$ck] ?? []);
            $out[] = '〔线索〕' . ($d['title'] ?? $ck) . '　' . (string) ($d['point'] ?? '');
        }
    }
    $out[] = '〔推断〕' . (string) $o['label'];
    if (!empty($o['note'])) $out[] = (string) $o['note'];
    $out[] = sprintf('（笔记里记下了这一条：你把它读成了 %d。）', (int) $opt);
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'infer', 'task' => $task, 'slot' => $slot, 'opt' => $opt,
                                       'track' => $track, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.infer']);
    }
    return $out;
}

/* ══════════ 轨道读数（第五幕按它开结局 ✓）══════════ */

function crrg_infer_tracks(&$pc, $task) {
    $out = [];
    foreach (['ren' => '人说', 'gangwei' => '岗位说'] as $k => $cn) $out[$k] = (int) ($pc['track_' . $task . '_' . $k] ?? 0);
    return $out;
}
/** 主轨道：'ren' / 'gangwei' / 'mixed'（对半或改口多）/ ''（没做过推论 ✓ 兜底结局） */
function crrg_infer_lean(&$pc, $task) {
    $t = crrg_infer_tracks($pc, $task);
    $sum = array_sum($t);
    if ($sum <= 0) return '';
    if ($t['ren'] === $t['gangwei']) return 'mixed';
    return $t['ren'] > $t['gangwei'] ? 'ren' : 'gangwei';
}

/** 笔记里的【推断】块（还没做过的槽位只显示"还差一步"，不剧透 ✓） */
function crrg_infer_note_lines(&$s, $task) {
    $slots = crrg_infer_slots($task);
    if (!$slots) return [];
    $out = [];
    foreach ($slots as $slot => $def) {
        $read = crrg_infer_read($s, $task, $slot);
        if ($read === '') {
            $out[] = sprintf('· （%s 这一条还没想通。）', (string) ($def['hint'] ?? '有一件事'));
            continue;
        }
        $o = (array) ($def['options'][$read] ?? []);
        $out[] = sprintf('· 你把它读成了 %s：%s', $read, (string) ($o['label'] ?? ''));
        if (in_array('re_read_' . $task . '_' . $slot, (array) $s['flags'], true)) $out[] = '　（这一条你改过口。）';
    }
    return array_merge(['【推断】'], $out);
}

/** 跨幕汇总：把一条链上各幕的轨道合起来看（第五幕按它开结局 ✓） */
function crrg_infer_lean_chain(&$pc, array $tasks) {
    $ren = 0; $gw = 0;
    foreach ($tasks as $t) {
        $x = crrg_infer_tracks($pc, (string) $t);
        $ren += (int) ($x['ren'] ?? 0); $gw += (int) ($x['gangwei'] ?? 0);
    }
    if ($ren + $gw <= 0) return '';
    if ($ren === $gw) return 'mixed';
    return $ren > $gw ? 'ren' : 'gangwei';
}
