<?php
/**
 * 《封条》调查点引擎（2026-09-29｜游戏侧优化·survey 结构级合并）
 *   原状：seal / seal2 / seal3 各写一份 survey（67＋99＋45＝211 行 ✗），骨架相同：
 *         取房间表 → 匹配关键词 → 已查过？→ （可选）前置条件/检定 → 置旗标 → 输出文本 → （可选）后置钩子
 *   现版：骨架收敛到本引擎，各模块只提供**行数据**。
 *
 *   行格式（关联数组，全部可选字段除 kw/flag/texts）：
 *     'kw'     => '关键词'（与玩家输入互相包含即算命中）
 *     'flag'   => '主旗标'（置位后视为"已查过"）
 *     'texts'  => ['正文…'] 或 callable($s,$pc) → 文本（条件文本用闭包）
 *     'skill'  => '会计'（可选，检定技能；等级 ≥2 视为成功）
 *     'pen'    => 1（可选，惩罚骰）
 *     'extra'  => ['附带旗标…']（可选，成功时一并置位）
 *     'gate'   => ['rep' => ['town_qingshi', 10], 'alt_flag' => 'paper_绕过', 'refuse' => ['…']]（可选门槛）
 *     'lead'   => callable($s,$pc) → '前置一句'（可选）
 *     'post'   => '后置钩子函数名'（可选，返回追加文本）
 */
if (!defined('ABSPATH')) exit;

/** 跑一个调查点。命中并完成→返回文本数组；未命中/无此行→返回 null */
function crrg_seal_survey_engine(&$s, &$pc, $arg, array $T) {
    $room = $s['room'] ?? '';
    if (!isset($T[$room])) return null;
    foreach ((array) $T[$room] as $row) {
        $kw   = (string) ($row['kw'] ?? '');
        $flag = (string) ($row['flag'] ?? '');
        if ($kw === '') continue;                     // 允许无旗标行＝纯线索（可重复查看）
        if ($arg !== '' && mb_strpos($arg, $kw) === false && mb_strpos($kw, $arg) === false) continue;

        /* 已查过（无旗标行不做这步） */
        if ($flag !== '' && crrg_seal_has($s, $flag)) return crrg_seal_done_msg($s, $pc, $room, $flag, $arg);

        /* 前置钩子（2026-09-29）：可做副作用；返回数组＝就此结束，返回 null＝继续 */
        if (!empty($row['pre']) && is_callable($row['pre'])) {
            $preFn = $row['pre'];
            $preR = $preFn($s, $pc);
            if (is_array($preR)) return $preR;
        }

        /* 门槛（声望／替代旗标） */
        $gate = (array) ($row['gate'] ?? []);
        if ($gate) {
            $repNow = 0;
            if (!empty($gate['rep']) && function_exists('crrg_rep_of')) $repNow = (int) crrg_rep_of($pc, (string) $gate['rep'][0]);
            $alt = (string) ($gate['alt_flag'] ?? '');
            $passRep = empty($gate['rep']) || $repNow >= (int) $gate['rep'][1];
            if (!$passRep && ($alt === '' || !crrg_seal_has($s, $alt))) {
                $rf = $gate['refuse'] ?? ['（现在还查不了。）'];
                if (is_callable($rf)) $rf = $rf($s, $pc);        // 拒绝文本也可以是闭包（要带动态数值）
                return (array) $rf;
            }
        }

        /* 检定（可选） */
        $extra = [];
        if (!empty($row['skill'])) {
            $pen = (int) ($row['pen'] ?? 0);
            [$ok, $txt] = crrg_seal_roll($pc, (string) $row['skill'], $pen);
            $txt .= ($pen > 0 ? (string) ($row['pen_note'] ?? '') : '');   // 惩罚骰的说明（各行文案可能不同）
            if (!$ok) return [$txt, (string) ($row['fail_text'] ?? '（没弄明白——可以再来，或换个法子。）')];
            $extra[] = $txt;
        }

        $out = [];
        /* 前置一句（条件文本） */
        if (!empty($row['lead']) && is_callable($row['lead'])) {
            $leadFn = $row['lead'];
            $lead = (string) $leadFn($s, $pc);                    // 直接调用：保住按引用
            if ($lead !== '') $out[] = $lead;
        }
        $out = array_merge($out, $extra);
        $texts = $row['texts'] ?? [];
        if (is_callable($texts)) { $txFn = $texts; $texts = (array) $txFn($s, $pc); }   // 直接调用：保住按引用
        foreach ((array) $texts as $x) $out[] = $x;

        crrg_seal_set($s, $flag);
        foreach ((array) ($row['extra'] ?? []) as $x) crrg_seal_set($s, $x);

        /* 后置钩子（例如"三线合一"判定） */
        $post = $row['post'] ?? '';
        if ($post !== '' && (is_callable($post) || (is_string($post) && function_exists($post)))) {
            $postFn = $post;
            $out = array_merge($out, (array) $postFn($s, $pc));   // 直接调用：钩子需要按引用改状态
        }
        return $out;
    }
    return null;
}
