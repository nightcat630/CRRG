<?php
/**
 * 《封条》决断引擎（2026-09-29｜游戏侧优化·stage_choice 合并）
 *   原状：seal / seal2 各写一份 stage_choice（29＋26 行 ✗），形状完全一致：
 *         提示语 → 前置门槛 → **只能定一次** → 选项（置旗＋加声望＋可选扣库存＋文本）→ 兜底
 *   为什么并：今天那个「可反复点、每次加声望」的刷子 ✗ 就出在这个形状里（闸门/门槛/施加效应）——
 *            并成一处，等于把风险逻辑收敛到单点。
 *
 *   $cfg 结构：
 *     'prompt'   => 无 key 时的提示文本
 *     'fallback' => key 不认识时的提示文本
 *     'gate'     => ['need' => ['旗标…'], 'bypass_on' => ['选项子串…'], 'refuse' => ['…']]（可选）
 *     'once'     => ['flags' => ['旗标A','旗标B'], 'done' => callable($s) → 文本]（可选）
 *     'options'  => [
 *          ['match' => '上报', 'flags' => ['旗标…'], 'rep' => ['base', 4, '来由'],
 *           'stock' => [1, '来由'], 'texts' => ['…']],
 *          …
 *     ]
 */
if (!defined('ABSPATH')) exit;

function crrg_seal_choice_engine(&$s, &$pc, $key, array $cfg) {
    $prompt   = (string) ($cfg['prompt']   ?? '（还没到定的时候。）');
    $fallback = (string) ($cfg['fallback'] ?? $prompt);

    /* ① 提示 */
    if ($key === '' || $key === null) return [$prompt];

    /* ② / ③ 门槛与"只能定一次"——先后顺序按模块原状（seal：先 once；seal2：先 gate） */
    $once = (array) ($cfg['once'] ?? []);
    $gate = (array) ($cfg['gate'] ?? []);

    $runOnce = function () use (&$s, $once) {
        foreach ((array) ($once['flags'] ?? []) as $f) {
            if (crrg_seal_has($s, $f)) {
                $doneFn = $once['done'] ?? null;
                return is_callable($doneFn) ? (array) $doneFn($s) : ['（这一步已经定过了。）'];
            }
        }
        return null;
    };
    $runGate = function () use (&$s, &$pc, $key, $gate) {
        if (!$gate) return null;
        foreach ((array) ($gate['bypass_on'] ?? []) as $pat) if (mb_strpos((string) $key, $pat) !== false) return null;
        $need = (array) ($gate['need'] ?? []);
        $miss = array_values(array_diff($need, array_intersect($need, (array) ($s['flags'] ?? []))));
        if (!$miss) return null;
        $rf = $gate['refuse'] ?? ['（条件不够。）'];
        if (is_callable($rf)) $rf = $rf($s, $pc, $miss);       // 拒绝文本可动态生成（如"列出还缺哪几项"）
        return (array) $rf;
    };

    if (!empty($cfg['gate_first'])) {
        if ($r = $runGate()) return $r;
        if ($r = $runOnce()) return $r;
    } else {
        if ($r = $runOnce()) return $r;
        if ($r = $runGate()) return $r;
    }

    /* ④ 选项 */
    foreach ((array) ($cfg['options'] ?? []) as $op) {
        $match = (string) ($op['match'] ?? '');
        if ($match === '' || mb_strpos((string) $key, $match) === false) continue;
        foreach ((array) ($op['flags'] ?? []) as $f) crrg_seal_set($s, $f);
        if (!empty($op['rep']))   crrg_rep_add($pc, (string) $op['rep'][0], (int) $op['rep'][1], (string) ($op['rep'][2] ?? ''));
        if (!empty($op['stock']) && function_exists('crrg_seal_use')) crrg_seal_use($pc, (int) $op['stock'][0], (string) ($op['stock'][1] ?? ''));
        return (array) ($op['texts'] ?? []);
    }
    return [$fallback];
}
