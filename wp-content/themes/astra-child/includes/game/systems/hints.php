<?php
/**
 * CRRG 演习 · 提示档（systems/hints.php，R9-④，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："提示档（关/轻/强）：卡住时的降级帮助 —— **单独一层**，不许把"去 X"写进正文 ✗"
 * 口径（硬）：
 *   · 正文/线索里**永远不写"去 X"** ✗ —— 提示只从这一层出 ✓
 *   · **关**：什么都不给（默认）
 *   · **轻**：给方向（"这间屋里还有你没碰过的东西"）
 *   · **强**：给候选（最多 3 个：房间/物件/人）
 *   · 强度存在个人档（`$pc['hint']`），闸门在玩家手里 ✓
 */
if (!defined('ABSPATH')) exit;

function crrg_hint_level(&$pc) { return max(0, min(2, (int) ($pc['hint'] ?? 0))); }
function crrg_hint_set(&$pc, $lv) {
    $map = ['关' => 0, '轻' => 1, '强' => 2, '0' => 0, '1' => 1, '2' => 2];
    $k = (string) $lv;
    if (!isset($map[$k])) return ['（提示档只有：关／轻／强。）'];
    $pc['hint'] = $map[$k];
    return [sprintf('（提示档：%s。）', ['关', '轻', '强'][$map[$k]])];
}
function crrg_hint_cn(&$pc) { return ['关', '轻', '强'][crrg_hint_level($pc)]; }

/**
 * 「提示」：按档给帮助。
 * @param string $task 在手任务 id（可为空）
 * @return array 给玩家看的行（**这些行不进正文，也绝不写进任务文案** ✗ ✓）
 */
function crrg_hint_now(&$s, &$pc, $task = '') {
    $lv = crrg_hint_level($pc);
    if ($lv === 0) return ['（提示档是关的。要看的话：「提示档 轻」或「提示档 强」。）'];
    $out = [];
    $room = (string) ($s['room'] ?? '');

    /* 轻：只说"这间屋里还有东西没碰" */
    if ($lv === 1) {
        $todo = [];
        if (function_exists('crrg_seal_paths') && $room !== '') {
            foreach ((array) crrg_seal_paths((string) ($s['room'] ?? '')) as $k => $v) { $todo[] = $k; }
        }
        if ($todo) $out[] = '（这间屋里还有你没碰过的东西。慢慢来。）';
        elseif ($task !== '' && function_exists('crrg_clue_board')) {
            $out[] = '（手册里还有没想通的地方——把线索再排一排。）';
        } else {
            $out[] = '（眼下这间屋没什么可查的了。换个地方，或者去问问人。）';
        }
        return $out;
    }

    /* 强：给候选（最多 3 个） */
    $cand = [];
    if ($task !== '' && function_exists('crrg_task')) {
        $t = crrg_task($task);
        foreach ((array) ($t['clues'] ?? []) as $k => $d) {
            if (function_exists('crrg_clue_has') && crrg_clue_has($s, $task, $k)) continue;
            $w = (string) ($d['where'] ?? '');
            if ($w !== '') $cand[$w] = true;
        }
    }
    if (!$cand && function_exists('crrg_seal_paths') && $room !== '') {
        foreach ((array) crrg_seal_paths($room) as $k => $v) $cand[$k] = true;
    }
    $out[] = '（现在能试的几个方向：）';
    $i = 0;
    foreach (array_keys($cand) as $c) { $out[] = '　· ' . $c; if (++$i >= 3) break; }
    if ($i === 0) $out[] = '　· 换个房间看看，或者找个人问一句。';
    return $out;
}
