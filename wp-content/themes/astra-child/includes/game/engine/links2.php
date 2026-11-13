<?php
/**
 * CRRG 演习 · 引擎对接层 · 第二批（engine/links2.php，R11，2026-10-14）
 * ------------------------------------------------------------
 * 补上 22 号文里"仍缺的五项"：
 *   ① `trigger.event`   —— **日程到点自动拉起任务**（点名／例会／演习日／发薪／轮休）✓
 *   ② `trigger.at_dorm` —— **宿舍／私人空间内触发**（只在宿舍房间成立 ✓）
 *   ③ `path.type='trade'` —— **交易型解法**（买进／卖出，价走交易系统 ✓）
 *   ④ `path.type='appraise'` —— **鉴定/估价作为线索来源**（送检 → 出结果 → 可能给线索 ✓）
 *   ⑤ **野外节点调查点** —— 地图节点上的"勘查"（与房间调查同构，但按节点 ✓）
 *
 * 纪律：全部**只读外部系统 + 落个人档**；交易/鉴定走各自系统的既有函数（不另写一份 ✗）
 */
if (!defined('ABSPATH')) exit;

/* ══════════ ① 日程事件 ══════════ */
function crrg_l2_event_ok(&$s, &$pc, $ev) {
    if (!function_exists('crrg_cal_on')) return false;
    return (bool) crrg_cal_on((string) $ev);
}

/* ══════════ ② 宿舍内 ══════════ */
function crrg_l2_at_dorm(&$s) {
    $room = (string) ($s['room'] ?? '');
    if ($room === '') return false;
    if (function_exists('crrg_dorm_rooms') && in_array($room, (array) crrg_dorm_rooms(), true)) return true;
    return (bool) ($s['at_dorm'] ?? false);
}

/* ══════════ ③ 交易型解法 ══════════ */
function crrg_l2_trade(&$s, &$pc, $task, $pid, array $path) {
    $out = [];
    $uid = (int) ($pc['uid'] ?? 0);
    $buy  = (string) ($path['buy'] ?? '');
    $sell = (string) ($path['sell'] ?? '');
    if ($buy !== '') {
        $price = function_exists('crrg_trade_price') ? (int) crrg_trade_price($buy) : (int) ($path['price'] ?? 0);
        if ($price <= 0) return ['（这东西没有价。）'];
        if ((int) ($pc['money'] ?? 0) < $price) return [sprintf('（要 %d 元，你不够。）', $price)];
        $pc['money'] = (int) $pc['money'] - $price;
        if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, $buy, 1);
        $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($buy) : $buy;
        $out[] = sprintf('（付了 %d 元，收下 %s。）', $price, $nm);
    }
    if ($sell !== '') {
        $price = function_exists('crrg_trade_junk_price') ? (int) crrg_trade_junk_price($sell) : (int) ($path['price'] ?? 0);
        $own = function_exists('crrg_drill_own_count') ? (int) crrg_drill_own_count($pc, $sell) : 0;
        if ($own <= 0) return ['（你手上没有这个。）'];
        if (function_exists('crrg_drill_bag_del_n')) crrg_drill_bag_del_n($pc, $sell, 1);
        $pc['money'] = (int) ($pc['money'] ?? 0) + $price;
        $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($sell) : $sell;
        $out[] = sprintf('（出手 %s，进 %d 元。）', $nm, $price);
    }
    foreach ((array) ($path['text'] ?? []) as $x) $out[] = (string) $x;
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'trade', 'task' => $task, 'path' => $pid, 'uid' => $uid], ['src' => 'engine.links2']);
    }
    return $out;
}

/* ══════════ ④ 鉴定/估价 → 线索 ══════════ */
function crrg_l2_appraise(&$s, &$pc, $task, $pid, array $path) {
    $id = (string) ($path['item'] ?? '');
    if ($id === '') return ['（没说鉴定什么。）'];
    $out = [];
    if (function_exists('crrg_appraise')) {
        $r = (array) crrg_appraise($s, $pc, $id);
        foreach ($r as $x) if (is_string($x)) $out[] = $x;
    } else {
        $out[] = '（鉴定系统没加载。）';
    }
    /* 出结果后：按解法声明给线索（"这条东西替你说了一句话" ✓） */
    foreach ((array) ($path['gain']['clues'] ?? []) as $ck) {
        if (function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, (string) $ck)) {
            $d = (array) (crrg_task($task)['clues'][$ck] ?? []);
            $out[] = '〔线索〕' . ($d['title'] ?? $ck) . '　' . (string) ($d['point'] ?? '');
        }
    }
    return $out;
}

/* ══════════ ⑤ 野外/地图节点上的调查点 ══════════ */

/** 注册一个节点调查点：crrg_node_survey_def('r_ganzhou','井','…') */
function crrg_node_survey_def($node, $key, array $def) {
    $R = &crrg_drill_reg();
    $R['node_survey'][(string) $node][(string) $key] = $def + ['key' => $key, 'node' => $node];
    return true;
}
function crrg_node_surveys($node) {
    $R = &crrg_drill_reg();
    return (array) ($R['node_survey'][(string) $node] ?? []);
}
/** 当前人在哪个地图节点（车旅/野外时由行程/位置给出 ✓） */
function crrg_node_here(&$pc, &$s) {
    if (!empty($pc['travel']['dest'])) return (string) $pc['travel']['dest'];
    if (!empty($GLOBALS['crrg_travel_node'])) return (string) $GLOBALS['crrg_travel_node'];
    $room = (string) ($s['room'] ?? '');
    /* 2026-10-14：**从房间反查节点** —— 周边地点的房间定义里带 `node` 字段 ✓
       （到站后行程记录已清空 ✗，不这么做就"认不出自己在哪儿"，勘查点会是 0 ✓ 委员长今天要的就是这条链） */
    if (function_exists('crrg_drill_scene_rooms')) {
        $rm = (array) crrg_drill_scene_rooms();
        $nd = (string) ($rm[$room]['node'] ?? '');
        if ($nd !== '') return $nd;
    }
    return $room;                                       // 房间键也能当节点用 ✓（镇上的屋子）
}
/** 勘查（玩家侧）：按关键词/顺序找调查点 → 叙事 + 可能给线索 ✓ */
function crrg_node_survey(&$s, &$pc, $arg = '') {
    $node = crrg_node_here($pc, $s);
    $list = crrg_node_surveys($node);
    if (!$list) return ['（这一带没什么可看的。）'];
    $arg = trim((string) $arg);
    $hit = null;
    foreach ($list as $k => $d) {
        if ($arg === '' || mb_strpos((string) $k, $arg) !== false || mb_strpos((string) ($d['n'] ?? ''), $arg) !== false) { $hit = [$k, $d]; break; }
    }
    if (!$hit) return ['（这里没有「' . $arg . '」这样的地方。能看的有：' . implode('、', array_map(fn($d) => (string) ($d['n'] ?? ''), $list)) . '。）'];
    [$k, $d] = $hit;
    $flag = 'node_seen_' . substr(md5($node . '|' . $k), 0, 8);
    $out = [];
    if (!in_array($flag, (array) ($s['flags'] ?? []), true)) {
        $s['flags'][] = $flag;
        foreach ((array) ($d['t'] ?? []) as $x) $out[] = (string) $x;
        if (!empty($d['clue_task']) && !empty($d['clue']) && function_exists('crrg_clue_gain')) {
            if (crrg_clue_gain($s, $pc, (string) $d['clue_task'], (string) $d['clue'])) {
                $out[] = '〔线索〕' . (string) ($d['clue_title'] ?? $d['clue']);
            }
        }
        if (function_exists('crrg_evt_emit')) crrg_evt_emit('mission.beat', ['kind' => 'survey_node', 'node' => $node, 'key' => $k, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.links2']);
    } else {
        $out[] = (string) ($d['again'] ?? '（你刚才看过这里了。）');
    }
    return $out;
}
