<?php
/**
 * CRRG 演习 · 日历与日程（systems/calendar.php，第 2 轮，2026-10-14）
 * ------------------------------------------------------------
 * 委员长的口径（照抄）：
 *   · **不要年份、不要"第 X 天"** —— 后期可能过去几千年，写年份/第几天不合理 ✗
 *   · **只要月份和日份**；**跨年交替要做好**（12 月 → 1 月不能错乱）✓
 *
 * 做法：
 *   · **内部只有"累计日序"**（`crrg_cal_day()`）—— 直接取世界钟的 `day`（单调 +1，永不回头 ✓）
 *   · **显示只有月/日**：12 月 × 30 天 = 360 天一循环 →
 *       月 = floor(((日序-1) mod 360) / 30) + 1 ｜ 日 = ((日序-1) mod 30) + 1  ✓ 取模天然处理跨年 ✓
 *   · **星期**同样由日序取模（1 = 周一）→ 拨钟/改锚点都不会把星期搞乱 ✓
 *   · 排序、比较、日程全部**只认日序**（显示层永远不带年份/序号）✓
 *
 * 日程（世界自走的节拍）：点名 07:00 每日／例会 周一／演习日 周三／发薪 每月 1 日／轮休 周日
 *   → 到点提示走"每次动作顺手查一遍"（幂等：`cal_<日序>_<事件>`），**不另起定时任务** ✓
 */
if (!defined('ABSPATH')) exit;

const CRRG_CAL_MONTHS = 12;
const CRRG_CAL_DAYS   = 30;                       // 每月 30 天 → 一年 360 天
const CRRG_CAL_YEAR   = 360;

/** 累计日序（单调；取世界钟的 day ✓ 没有世界钟时退回 1） */
function crrg_cal_day($ts = null) {
    if ($ts === null && function_exists('crrg_world_clock')) {
        $c = crrg_world_clock();
        $d = (int) ($c['day'] ?? 0);
        if ($d > 0) return $d;
    }
    if ($ts !== null) {                            // 传入世界时刻：用同一口径算
        $o = get_option('crrg_drill_clock', null);
        if (is_array($o) && !empty($o['day0'])) {
            return (int) floor(($ts - (int) $o['day0']) / 86400) + max(1, (int) ($o['day0_day'] ?? 1));
        }
    }
    return 1;
}

/** 日序 → [月, 日]（**永远不返回年份** ✓） */
function crrg_cal_md($day = null) {
    $day = $day === null ? crrg_cal_day() : (int) $day;
    $n = (($day - 1) % CRRG_CAL_YEAR + CRRG_CAL_YEAR) % CRRG_CAL_YEAR;      // 负数也安全 ✓
    return [(int) floor($n / CRRG_CAL_DAYS) + 1, ($n % CRRG_CAL_DAYS) + 1];
}
/** 月/日 → 该"月日"在本循环内的序号（0..359；用于比较与排序 ✓） */
function crrg_cal_md_index($m, $d) {
    $m = max(1, min(CRRG_CAL_MONTHS, (int) $m)); $d = max(1, min(CRRG_CAL_DAYS, (int) $d));
    return ($m - 1) * CRRG_CAL_DAYS + ($d - 1);
}

/** 星期：由日序取模（1 = 周一 … 7 = 周日）✓ 与锚点无关，永远连续 */
function crrg_cal_weekday($day = null) {
    $day = $day === null ? crrg_cal_day() : (int) $day;
    return (($day - 1) % 7 + 7) % 7 + 1;
}
function crrg_cal_weekday_cn($day = null) {
    return ['一', '二', '三', '四', '五', '六', '日'][crrg_cal_weekday($day) - 1];
}

/** 时段（接世界钟；没有就按小时粗分） */
function crrg_cal_phase() {
    if (function_exists('crrg_world_clock')) { $c = crrg_world_clock(); return (string) ($c['phase'] ?? '白天'); }
    return '白天';
}
function crrg_cal_hour() {
    if (function_exists('crrg_world_clock')) { $c = crrg_world_clock(); return (int) ($c['h'] ?? 0); }
    return (int) crrg_wdate('G', time());
}

/** **唯一的口径**：现在是什么时候（月/日/周几/时段/日序）—— 显示层只许用这里 ✓ */
function crrg_cal_now() {
    $day = crrg_cal_day();
    [$m, $d] = crrg_cal_md($day);
    return ['day' => $day, 'month' => $m, 'dom' => $d, 'weekday' => crrg_cal_weekday($day),
            'weekday_cn' => crrg_cal_weekday_cn($day), 'hour' => crrg_cal_hour(), 'phase' => crrg_cal_phase()];
}
/** 一行：`8月12日 周三 · 上午`（**没有年份、没有第几天** ✓） */
function crrg_cal_text($day = null) {
    $day = $day === null ? crrg_cal_day() : (int) $day;
    [$m, $d] = crrg_cal_md($day);
    if ($day === crrg_cal_day()) return sprintf('%d月%d日 周%s · %s', $m, $d, crrg_cal_weekday_cn($day), crrg_cal_phase());
    return sprintf('%d月%d日 周%s', $m, $d, crrg_cal_weekday_cn($day));
}

/* ══════════ 日程表 ══════════ */

/** 事件表：一周/一月里的固定日程 ✓（改日程只改这一处 —— 一份真相） */
function crrg_cal_events() {
    return [
        'roll'   => ['n' => '点名', 'when' => ['daily' => true, 'hour' => 7],
                     't' => '值班室点名。到不了的要有人替你报。'],
        'meet'   => ['n' => '例会', 'when' => ['weekday' => 1, 'hour' => 9],
                     't' => '周例会，各组长汇报。'],
        'drill'  => ['n' => '演习日', 'when' => ['weekday' => 3, 'hour' => 10],
                     't' => '演习日。训练室与外围同时开科目。'],
        'pay'    => ['n' => '发薪', 'when' => ['dom' => 1, 'hour' => 12],
                     't' => '发薪日，后勤室排队。'],
        'off'    => ['n' => '轮休', 'when' => ['weekday' => 7, 'hour' => 8],
                     't' => '轮休日。该歇的歇。'],
    ];
}

/** 某事件在给定日序上成不成立（只认月/日/星期 ✓） */
function crrg_cal_on($ev, $day = null) {
    $evs = crrg_cal_events();
    if (!isset($evs[$ev])) return false;
    $day = $day === null ? crrg_cal_day() : (int) $day;
    [$m, $d] = crrg_cal_md($day);
    $w = (array) ($evs[$ev]['when'] ?? []);
    if (!empty($w['daily'])) return true;
    if (!empty($w['weekday']) && crrg_cal_weekday($day) !== (int) $w['weekday']) return false;
    if (!empty($w['dom']) && (int) $d !== (int) $w['dom']) return false;
    if (!empty($w['month']) && (int) $m !== (int) $w['month']) return false;
    return !empty($w['weekday']) || !empty($w['dom']) || !empty($w['month']);
}

/** 旧接口兼容： crrg_cal_on_day($ev, $m, $d) —— 是不是"某月某日" */
function crrg_cal_on_day($ev, $m, $d, $day = null) {
    $day = $day === null ? crrg_cal_day() : (int) $day;
    [$cm, $cd] = crrg_cal_md($day);
    return ((int) $m === $cm && (int) $d === $cd);
}

/**
 * 下一次这个事件在几天后（含今天；今天但已过点 → 顺延到下次成立）
 * @return array ['in' => 几天后, 'day' => 那个日序, 'hour' => 触发小时]
 */
function crrg_cal_next($ev, $day = null) {
    $evs = crrg_cal_events();
    if (!isset($evs[$ev])) return null;
    $day = $day === null ? crrg_cal_day() : (int) $day;
    $hour = (int) ((array) ($evs[$ev]['when'] ?? []))['hour'] ?? 0;
    for ($i = 0; $i <= CRRG_CAL_YEAR; $i++) {
        $d = $day + $i;
        if (!crrg_cal_on($ev, $d)) continue;
        if ($i === 0 && crrg_cal_hour() >= $hour) continue;      // 今天已经过了点
        return ['in' => $i, 'day' => $d, 'hour' => $hour];
    }
    return null;
}

/** 今日日程（给指令/面板用） */
function crrg_cal_today_lines() {
    $now = crrg_cal_now();
    $out = [sprintf('〔今日〕%s', crrg_cal_text())];
    $hits = 0;
    foreach (crrg_cal_events() as $id => $e) {
        if (!crrg_cal_on($id)) continue;
        $hits++;
        $h = (int) ((array) $e['when'])['hour'];
        $out[] = sprintf('　· %s %02d:00 —— %s', $e['n'], $h, $e['t']);
    }
    if (!$hits) $out[] = '　· 今天没有固定日程。';
    $out[] = '（日子只按月/日记；没有年份。日程到点会说一声。）';
    return $out;
}

/* ══════════ 到点提示（挂"每次动作"，幂等 ✓ 不另起定时任务 ✗）══════════ */

function crrg_cal_pump(&$s, &$pc, &$out = null) {
    $day = crrg_cal_day(); $h = crrg_cal_hour();
    $lines = [];
    foreach (crrg_cal_events() as $id => $e) {
        if (!crrg_cal_on($id, $day)) continue;
        $at = (int) ((array) $e['when'])['hour'];
        if ($h < $at) continue;
        $flag = 'cal_' . $day . '_' . $id;
        if (in_array($flag, (array) ($s['flags'] ?? []), true)) continue;    // 幂等 ✓
        $s['flags'][] = $flag;
        $lines[] = sprintf('〔%s〕%s', crrg_cal_text($day), $e['n']);
        $lines[] = '（' . $e['t'] . '）';
    }
    if ($lines && is_array($out)) foreach ($lines as $x) $out[] = $x;
    return $lines;
}

/** 2026-10-19 补：手机「时间」页在用这个函数（原来不存在 ✗ 日程永远是空） */
function crrg_cal_events_today() {
    $out = [];
    foreach (crrg_cal_events() as $id => $e) {
        if (!crrg_cal_on($id)) continue;
        $h = (int) ((array) $e['when'])['hour'];
        $out[] = sprintf('%s %02d:00 —— %s', $e['n'], $h, $e['t']);
    }
    return $out;
}
