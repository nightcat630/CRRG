<?php
/**
 * 地方声望系统（2026-09-28）
 * 口径（委员长定）：
 *   1. **按地点独立**——基地、青石镇、以及后续每个城市各有一套；互不换算。
 *   2. **来源是选择与结局**：中小型模组中途的决定、大型模组的收尾，都写进对应地点的声望。
 *   3. **高有好处**：折扣、特殊选项、额外情报、任务门槛放行；**低有代价**：加价、被拒、任务不派。
 * 数据结构：pc['rep'][<loc>] = 整数（-100..100）；pc['rep_log'][] = [t, loc, delta, why] 留痕可查。
 */
if (!defined('ABSPATH')) exit;

/** 可影响声望的地点表（后续加城市就往这里加） */
function crrg_rep_places() {
    return [
        'base'          => ['n' => '青山基地', 'short' => '基地'],
        'town_qingshi'  => ['n' => '青石镇（甘肃）', 'short' => '青石镇'],
    ];
}
/** 声望分档 */
function crrg_rep_tiers() {
    return [
        ['min' =>  50, 'k' => 'insider', 'n' => '自己人', 'd' => '你的名字在这儿是好使的。', 'off' => 10],
        ['min' =>  25, 'k' => 'trusted', 'n' => '受信',   'd' => '办事的人认得你，愿意通融。', 'off' => 5],
        ['min' =>  10, 'k' => 'known',   'n' => '熟脸',   'd' => '有人跟你点头，话能多说两句。', 'off' => 0],
        ['min' =>   0, 'k' => 'neutral', 'n' => '新面孔', 'd' => '没人特别注意你。', 'off' => 0],
        ['min' => -29, 'k' => 'cold',    'n' => '冷淡',   'd' => '有人看见你就把话咽回去。', 'off' => -10],
        ['min' => -100, 'k' => 'hostile', 'n' => '讨嫌',  'd' => '镇上有人背后指你。', 'off' => -20],
    ];
}
function crrg_rep_of($pc, $loc) { return (int) ($pc['rep'][$loc] ?? 0); }
function crrg_rep_tier($pc, $loc) {
    $v = crrg_rep_of($pc, $loc);
    foreach (crrg_rep_tiers() as $t) if ($v >= $t['min']) return $t;
    $all = crrg_rep_tiers(); return end($all);
}
/** 该地点的价格修正（百分比，正数=打折，负数=加价） */
function crrg_rep_price_mod($pc, $loc) { $t = crrg_rep_tier($pc, $loc); return (int) $t['off']; }
/** 写入声望（并留痕） */
function crrg_rep_add(&$pc, $loc, $delta, $why = '') {
    if ($delta === 0) return 0;
    $delta = (int) $delta;
    $cur = crrg_rep_of($pc, $loc);
    $new = max(-100, min(100, $cur + $delta));
    if (!isset($pc['rep']) || !is_array($pc['rep'])) $pc['rep'] = [];
    $pc['rep'][$loc] = $new;
    if (!isset($pc['rep_log']) || !is_array($pc['rep_log'])) $pc['rep_log'] = [];
    $pc['rep_log'][] = ['t' => current_time('mysql'), 'loc' => $loc, 'd' => $delta, 'why' => $why, 'now' => $new];
    if (count($pc['rep_log']) > 60) $pc['rep_log'] = array_slice($pc['rep_log'], -60);
    return $new;
}
/** 一行式说明，用于结算与面板 */
function crrg_rep_line($pc, $loc) {
    $p = crrg_rep_places()[$loc] ?? ['n' => $loc, 'short' => $loc];
    $t = crrg_rep_tier($pc, $loc);
    return sprintf('%s：%d（%s）', $p['short'], crrg_rep_of($pc, $loc), $t['n']);
}
/** 「声望」指令 */
function crrg_rep_lines(&$pc, $arg = '') {
    $arg = trim((string) $arg);
    $out = [];
    if ($arg !== '') {
        $loc = '';
        foreach (crrg_rep_places() as $k => $p) if (mb_strpos($p['n'], $arg) !== false || mb_strpos($p['short'], $arg) !== false || $arg === $k) { $loc = $k; break; }
        if ($loc === '') return ['没有叫「' . $arg . '」的地方。'];
        $p = crrg_rep_places()[$loc]; $t = crrg_rep_tier($pc, $loc);
        $out[] = sprintf('〔%s · 声望 %d〕%s', $p['n'], crrg_rep_of($pc, $loc), $t['n']);
        $out[] = '　' . $t['d'];
        if ($t['off'] > 0)      $out[] = '　（当地买卖打折 ' . $t['off'] . '%。）';
        elseif ($t['off'] < 0)  $out[] = '　（当地买卖加价 ' . abs($t['off']) . '%。）';
        $log = array_values(array_filter((array) ($pc['rep_log'] ?? []), fn($r) => ($r['loc'] ?? '') === $loc));
        if ($log) {
            $out[] = '　—— 变更记录 ——';
            foreach (array_slice(array_reverse($log), 0, 8) as $r) {
                $out[] = sprintf('　%s　%+d → %d　%s', mb_substr($r['t'], 5, 11), (int) $r['d'], (int) $r['now'], $r['why'] ?? '');
            }
        }
        return $out;
    }
    $out[] = '〔地方声望〕';
    foreach (crrg_rep_places() as $k => $p) {
        $t = crrg_rep_tier($pc, $k);
        $out[] = sprintf('　%s：%d（%s）%s', $p['n'], crrg_rep_of($pc, $k), $t['n'],
            $t['off'] > 0 ? '（买卖 -' . $t['off'] . '%）' : ($t['off'] < 0 ? '（买卖 +' . abs($t['off']) . '%）' : ''));
    }
    $out[] = '（按地点各自独立。要明细：「声望 青石镇」。）';
    return $out;
}
/** 某地点的折扣后价格 */
function crrg_rep_apply_price($pc, $loc, $price) {
    $mod = crrg_rep_price_mod($pc, $loc);
    if ($mod === 0) return (int) $price;
    return max(1, (int) round($price * (100 - $mod) / 100));
}
/** 任务是否因声望被拒（mission 里写 'min_rep' => ['town_qingshi' => 25]） */
function crrg_rep_mission_gate($pc, $mission) {
    foreach ((array) ($mission['min_rep'] ?? []) as $loc => $need) {
        if (crrg_rep_of($pc, $loc) < (int) $need) {
            $p = crrg_rep_places()[$loc]['short'] ?? $loc;
            return '这活儿不在你手上——' . $p . '那边要声望 ' . (int) $need . '（你现在 ' . crrg_rep_of($pc, $loc) . '）。';
        }
    }
    return '';
}
