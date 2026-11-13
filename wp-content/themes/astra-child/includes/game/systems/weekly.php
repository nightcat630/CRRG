<?php
/**
 * CRRG 演习 · 天气的机制影响 ＋ 周报审查（systems/weekly.php，R10，2026-10-14）
 * ------------------------------------------------------------
 * ① **天气不只是观感** ✓（委员长要的"机制影响"）：
 *    · 雨／雪／雾 → 侦查·追踪·驾驶 **惩罚骰**；夜雾额外**掉理智**（很轻，1 点/次，幂等）
 *    · 雨天土路 → **车旅抛锚率↑**（挂进车旅那套）
 *    · 接入点：与身体/症状同一处（coc_check 的全局惩罚）→ **一处接入，全检定生效** ✓
 * ② **周报/委员会审查**：每游戏周一次；自动汇总本周（完成的活／暴露／痕迹／症状／出勤）→ 玩家写什么、瞒什么
 *    · 瞒报（有暴露/痕迹却不写）→ **后果**：关注度 +1、基地声望 −1、写进公共事实 ✓
 *    · 交过就留痕（周序幂等 ✓ 一周只算一次）
 */
if (!defined('ABSPATH')) exit;

/* ══════════ ① 天气的机制影响 ══════════ */

/** 天气对检定的惩罚骰（0/1） */
function crrg_weather_pen($skill = '', $tag = '') {
    /* 2026-10-14 修：`crrg_world_weather()` 返回的是**数组** ['k'=>…,'n'=>…,'d'=>…] ✗（我先当成字符串了 ✓） */
    if (!function_exists('crrg_world_weather')) return 0;
    $w = crrg_world_weather('base');
    $k = is_array($w) ? (string) ($w['k'] ?? '') : (string) $w;
    if ($k === '') return 0;
    $hard = ['rain', 'rainy', 'snow', 'fog', 'storm'];
    if (!in_array($k, $hard, true)) return 0;
    $aff = ['侦查', '聆听', '追踪', '驾驶', '潜行', '攀爬', '射击', '导航'];
    foreach ($aff as $a) if ($a === (string) $skill) return 1;
    if ($tag !== '' && (mb_strpos($tag, '野外') !== false || mb_strpos($tag, '路上') !== false)) return 1;
    return 0;
}

/** 天气说明（给玩家看的一句，别让它变成隐形惩罚 ✓） */
function crrg_weather_note() {
    if (!function_exists('crrg_world_weather')) return '';
    $w = crrg_world_weather('base');
    $k = is_array($w) ? (string) ($w['k'] ?? '') : (string) $w;
    $n = is_array($w) ? (string) ($w['n'] ?? '') : '';
    if (!in_array($k, ['rain', 'rainy', 'snow', 'fog', 'storm'], true)) return '';
    return sprintf('（%s——视线、脚底、耳朵都不如平时。）', $n !== '' ? $n : '天气不好');
}

/** 夜雾：每次动作最多掉 1 点理智（幂等：按日 + 天气 ✓） */
function crrg_weather_san_tick(&$pc, &$s) {
    if (!function_exists('crrg_world_weather')) return [];
    $w = crrg_world_weather('base');
    $k = is_array($w) ? (string) ($w['k'] ?? '') : (string) $w;
    if ($k !== 'fog') return [];
    $h = function_exists('crrg_task_fact_hour') ? (int) crrg_task_fact_hour() : (int) date('G');
    if ($h < 22 && $h > 5) return [];                                     // 只有夜里 ✓
    $day = function_exists('crrg_cal_day') ? (int) crrg_cal_day() : (int) date('z');
    $key = 'fog_' . $day;
    if (in_array($key, (array) ($s['flags'] ?? []), true)) return [];      // 幂等：一夜只一次 ✓
    $s['flags'][] = $key;
    $out = [];
    if (function_exists('crrg_san_loss')) $out = array_merge(['（夜雾里，你总觉得路尽头站着谁。）'], (array) crrg_san_loss($pc, 1, '夜雾'));
    return $out;
}

/* ══════════ ② 周报 ══════════ */

/** 本周序号（按日历周 ✓ 无年份口径） */
function crrg_week_no() {
    $day = function_exists('crrg_cal_day') ? (int) crrg_cal_day() : (int) floor(time() / 86400);
    return (int) floor($day / 7);
}

/** 本周汇总（自动统计，供玩家参考 ✓ 一份真相：都从个人档/事实读） */
function crrg_week_digest(&$s, &$pc) {
    $done = array_slice((array) ($pc['mission_done'] ?? []), -6);
    $exp = 0; foreach ((array) $s as $k => $v) if (strpos((string) $k, 'expose_') === 0) $exp += (int) $v;
    /* 痕迹按房间存 ✓ → 汇总所有房间（`crrg_trace_count(&$s,$room)` 要房间参数 ✗ 我一开始只传了一个） */
    $tr = 0;
    if (function_exists('crrg_trace_count') && function_exists('crrg_drill_rooms')) {
        foreach (array_keys((array) crrg_drill_rooms()) as $rm) $tr += (int) crrg_trace_count($s, (string) $rm);
    }
    $sym = (array) ($pc['mad']['sym'] ?? []);
    return [
        '完成' => $done ? implode('、', $done) : '（没有）',
        '暴露' => (string) $exp,
        '痕迹' => (string) $tr,
        '症状' => $sym ? implode('、', array_map(fn($x) => (string) ($x['name'] ?? ''), $sym)) : '（无）',
    ];
}

/** 该不该交周报了（一周一次 ✓ 幂等） */
function crrg_week_due(&$s, $pc) {
    $wk = crrg_week_no();
    return !in_array('week_rpt_' . $wk, (array) ($s['flags'] ?? []), true);
}

/** 交周报：$claim 里写了什么；漏写暴露/痕迹 = **瞒报** ✓ */
function crrg_week_submit(&$s, &$pc, $claim = '') {
    $wk = crrg_week_no();
    $flag = 'week_rpt_' . $wk;
    if (in_array($flag, (array) $s['flags'], true)) return ['（这一周的报表已经交过了。）'];
    $d = crrg_week_digest($s, $pc);
    $s['flags'][] = $flag;
    /* 2026-10-14 修（委员长）：**不许出现"第 N 周/第 N 天"这种累计数字** ✗ ——
       本来写成「第 37 周」，几千年以后就是"第十几万周"，不合理 ✓ → 改成**收报日的月日** ✓ */
    $head = function_exists('crrg_cal_text') ? crrg_cal_text() : date('n月j日');
    $out = ['〔周报 · ' . $head . '〕'];
    $out[] = '　完成：' . $d['完成'];
    $out[] = '　暴露：' . $d['暴露'] . '　痕迹：' . $d['痕迹'] . '　症状：' . $d['症状'];
    $lied = false;
    $claim = trim((string) $claim);
    if ($claim !== '') $out[] = '　你写的：' . $claim;
    if (($d['暴露'] !== '0' || $d['痕迹'] !== '0') && ($claim === '' || (mb_strpos($claim, '暴露') === false && mb_strpos($claim, '痕迹') === false))) {
        $lied = true;
    }
    if ($lied) {
        $s['expose_weekly'] = (int) ($s['expose_weekly'] ?? 0) + 1;
        if (function_exists('crrg_rep_add')) crrg_rep_add($pc, 'base', -1, '周报瞒报');
        if (function_exists('crrg_fact_add')) crrg_fact_add('base.周报对不上.' . (int) ($pc['uid'] ?? 0), true, 'base', (int) ($pc['uid'] ?? 0));
        $out[] = '（委员会那边核了一遍：对不上。）';
        $out[] = '（基地声望 −1；这件事记进了账。）';
    } else {
        $out[] = '（交上去了。没人回话——这算好结果。）';
    }
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'weekly', 'week' => $wk, 'lied' => $lied, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'weekly']);
    }
    return $out;
}
