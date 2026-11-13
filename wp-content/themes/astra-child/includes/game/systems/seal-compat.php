<?php
/**
 * 封条系统的**兼容层**（2026-10-22）
 * ------------------------------------------------------------
 * 背景：委员长确认《封条》那条任务线作废 → 我停用了 6 个 seal 文件。
 * 但**别的系统还在用它们导出的两个基础函数**：
 *   · `crrg_seal_has()` —— 对话系统里判"这个话题问过没有"（`drill-dialogue.php`）
 *   · `crrg_seal_set()` —— 同上，置旗标
 * 停掉之后 → 登录用户打开演习页会 `Call to undefined function` → **500**。
 *
 * 所以这里**只保留这两个函数**（它们本身只是旗标读写，与任务内容无关），
 * 任务线本体（问/调查/决断三套引擎、seal3/4/5 剧情）继续停用 ✓
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('crrg_seal_has')) {
    /** 个人档里有没有这个旗标（原本是封条线的工具，现在是通用的旗标读） */
    function crrg_seal_has(&$s, $f) {
        return in_array((string) $f, (array) ($s['flags'] ?? []), true);
    }
}

if (!function_exists('crrg_seal_set')) {
    /** 置旗标（幂等） */
    function crrg_seal_set(&$s, ...$flags) {
        $f = (array) ($s['flags'] ?? []);
        foreach ($flags as $x) {
            $x = (string) $x;
            if ($x !== '' && !in_array($x, $f, true)) $f[] = $x;
        }
        $s['flags'] = $f;
        return true;
    }
}
