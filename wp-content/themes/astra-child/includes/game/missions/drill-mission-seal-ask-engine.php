<?php
/**
 * 「问 NPC」引擎（2026-09-29｜游戏侧优化·合并三份重复逻辑）
 *   原状：seal / seal2 / seal3 各写一份 npc_ask（52+45+41＝138 行），样板完全相同，只有数据表不同。
 *   现版：样板收敛到本引擎，各模块只提供**数据**。
 *   数据格式（统一）：[ '话题关键词', '主旗标', ['文本…'], ['附带旗标'…]（可省）,
 *                    好感门限（可省，int）, ['好感不足时的文本…']（可省） ]
 *   门限行为：好感 < 门限 → 用「门限文本」、**不置附带旗标**（主旗标照置，仍算"问过"）
 */
if (!defined('ABSPATH')) exit;

/** 跑一条"问 NPC"：命中→置旗标（含附带）、好感 +1、返回文本；话题不匹配或 NPC 不在表里→返回 null */
function crrg_seal_ask_engine(&$s, &$pc, $npc, $topic = '', array $Q = []) {
    if (!isset($Q[$npc])) return null;
    /* 用共享助手（2026-09-29）：语义只有一处 */
    if (!isset($pc['npc_fav']) || !is_array($pc['npc_fav'])) $pc['npc_fav'] = [];

    foreach ((array) $Q[$npc] as $row) {
        $kw      = (string) ($row[0] ?? '');
        $flag    = (string) ($row[1] ?? '');
        $texts   = (array)  ($row[2] ?? []);
        $extra   = (array)  ($row[3] ?? []);
        $needFav = (int)    ($row[4] ?? 0);
        $textsLow = (array) ($row[5] ?? []);
        if ($kw === '') continue;
        if ($topic !== '' && mb_strpos($topic, $kw) === false && mb_strpos($kw, $topic) === false) continue;
        if ($flag !== '' && crrg_seal_has($s, $flag)) return ['（这件事已经问过了。）'];
        /* 好感门限（2026-09-29）：不够 → 换文本、不给附带旗标 */
        if ($needFav > 0 && (int) ($pc['npc_fav'][$npc] ?? 0) < $needFav) {
            crrg_seal_set($s, $flag);
            $pc['npc_fav'][$npc] = (int) ($pc['npc_fav'][$npc] ?? 0) + 1;
            $out = $textsLow ?: $texts;
            $out[] = '（' . $npc . ' 好感 +1）';
            return $out;
        }
        crrg_seal_set($s, $flag);
        foreach ($extra as $x) crrg_seal_set($s, $x);
        $pc['npc_fav'][$npc] = (int) ($pc['npc_fav'][$npc] ?? 0) + 1;
        $out   = $texts;
        $out[] = '（' . $npc . ' 好感 +1）';
        return $out;
    }
    return null;
}

/* ── 《封条》线共用助手（2026-09-29：把 5 个函数里重复的闭包收敛到一处） ── */
function crrg_seal_has(&$s, $f) { return in_array($f, (array) ($s['flags'] ?? []), true); }
function crrg_seal_set(&$s, ...$flags) {
    if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
    foreach ($flags as $f) if ($f !== '' && !in_array($f, $s['flags'], true)) $s['flags'][] = $f;
}
/** 掷一次技能检定，返回 [是否成功(等级≥2), 文本] */
function crrg_seal_roll(&$pc, $skill, $pen = 0) {
    $val = max(1, function_exists('crrg_coc_skill_val') ? crrg_coc_skill_val($pc, $skill) : 1);
    $r = crrg_coc_check($skill, $val, 0, $pen);
    return [crrg_coc_level_rank($r['level']) >= 2, $skill . '：' . $r['text']];
}
