<?php
/**
 * 回收与拆除（2026-09-28）
 * 口径：**买错了有出路，但只能低价出手。**
 *   废品收购站（青石镇）　回收 40%
 *   后勤回收（负三层物资仓库）　回收 60%
 *   宿舍拆下（家具／装修）　返还 50%
 * 不予回收：与任务/镇物直接相关的关键物（时间车钥匙、封条、封印匣、样本盒、现场照片等）。
 */
if (!defined('ABSPATH')) exit;

/** 补充价目：走「专有动作」买的（商店表里没有价目行），按实际买入价记账 */
function crrg_trade_extra_prices() {
    return ['tool_prybar' => 90, 'tool_ladder' => 120, 'doc_film' => 40, 'med_sedative' => 150, 'gear_vest' => 80, 'tool_stone' => 2];
}
/** 废品底价：没有价目的拾取物按类给个象征价 */
function crrg_trade_junk_price($id) {
    $gear = ['record', 'comm', 'tool_monitor', 'gear_harness', 'tool_lamp', 'gear_hazmat', 'gear_helmet', 'weap_baton', 'weap_knife', 'weap_rifle', 'weap_shotgun', 'weap_spear', 'tool_prybar', 'tool_ladder', 'tool_rope_nylon'];
    $tool = ['tool_tape', 'tool_nails', 'tool_wire', 'tool_insultape', 'tool_steelbar', 'tool_shovel', 'glove', 'tool_battery', 'tool_candle', 'smoke_matches', 'tool_soap', 'tool_tissue'];
    if (in_array($id, $gear, true)) return 15;
    if (in_array($id, $tool, true)) return 10;
    return 5;
}

/** 商品的原始价格（按物品 id 或商品名查） */
function crrg_trade_price($key) {
    foreach (crrg_drill_goods() as $items) {
        foreach ($items as $gid => $g) {
            $item = $g['item'] ?? $gid;
            if ($item === $key || ($g['n'] ?? '') === $key || $gid === $key) {
                return [(int) ($g['p'] ?? 0), ($g['cur'] ?? 'money')];
            }
        }
    }
    $extra = crrg_trade_extra_prices();
    if (isset($extra[$key])) return [(int) $extra[$key], 'money'];
    $items = crrg_drill_items();
    if (isset($items[$key]) && !empty($items[$key]['take'])) return [crrg_trade_junk_price($key), 'money'];   // 拾取物：废品底价
    return [0, 'money'];
}
/** 取名：先查物品表，再查商品表（装修/家具是商品，不在背包里） */
function crrg_trade_name($key) {
    $items = crrg_drill_items();
    if (isset($items[$key])) return $items[$key]['n'];
    foreach (crrg_drill_goods() as $list) foreach ($list as $gid => $g) {
        if ($gid === $key || ($g['item'] ?? '') === $key) return $g['n'];
    }
    return crrg_drill_item_name($key);
}
/** 不予回收的关键物 */
function crrg_trade_blocked() {
    return ['key_timekey', 'sigil', 'key_sealbox', 'key_sample', 'doc_photos', 'key_idcard'];
}
function crrg_trade_rate($where) {
    return ['junk' => 0.4, 'logistics' => 0.6, 'dorm' => 0.5][$where] ?? 0.5;
}
/** 卖出一件背包物品 */
function crrg_trade_sell(&$pc, $id, $where = 'junk', &$s = null) {
    $key = in_array($id, crrg_trade_blocked(), true);
    if ($key) {                                   // 关键物：只废品站收、只给 1 元、要走一步确认
        if ($where !== 'junk') return ['这东西后勤不收——跟任务和收容有关。真要出手，只有镇上废品站敢收。'];
        if (crrg_drill_bag_count($pc, $id) <= 0) return ['背包里没有这样东西。'];
        $flag = '确认卖_' . $id;
        if ($s !== null && !in_array($flag, (array) ($s['flags'] ?? []), true)) {
            if ($s !== null && $flag) { $s['flags'][] = $flag; }
            return [sprintf('老板掂了掂「%s」，抬眼看你：「这东西来路不正吧。一块钱，卖了别回头找我。」', crrg_drill_item_name($id)),
                    '（再点一次「卖」就成交——这可能会影响手头的任务。）'];
        }
        crrg_drill_bag_del_n($pc, $id, 1);
        $pc['money'] = (int) $pc['money'] + 1;
        if (function_exists('crrg_rep_from_junk')) crrg_rep_from_junk($pc);
        if ($s !== null) $s['flags'] = array_values(array_diff((array) $s['flags'], [$flag]));
        return [sprintf('你把「%s」留在废品站的秤台上，拿了一块钱。', crrg_drill_item_name($id)),
                sprintf('（现有 %d 积分 ／ %d 元。）', (int) $pc['credits'], (int) $pc['money'])];
    }
    if (crrg_drill_bag_count($pc, $id) <= 0) return ['背包里没有这样东西。'];
    [$price, $cur] = crrg_trade_price($id);
    if ($price <= 0) return ['没有价目记录，收不了。'];
    $gain = max(1, (int) floor($price * crrg_trade_rate($where)));
    crrg_drill_bag_del_n($pc, $id, 1);
    if ($cur === 'credits') $pc['credits'] = (int) $pc['credits'] + $gain;
    else                     $pc['money']   = (int) $pc['money'] + $gain;
    $whereName = ['junk' => '废品收购站', 'logistics' => '后勤回收窗口', 'dorm' => '宿舍' ][$where] ?? '回收处';
    return [sprintf('把%s交给%s，拿了 %d %s（原价 %d，折价 %d%%）。',
        crrg_trade_name($id), $whereName, $gain, $cur === 'credits' ? '积分' : '元', $price,
        (int) (crrg_trade_rate($where) * 100)),
        sprintf('（现有 %d 积分 ／ %d 元。）', (int) $pc['credits'], (int) $pc['money'])];
}
/** 拆下宿舍里的一件装修（同名的都退掉） */
function crrg_trade_unsell_deco(&$pc, $slot) {
    $name = $pc['dorm']['deco'][$slot] ?? '';
    if (!$name) return ['这个位置没装东西。'];
    $units = 1;
    $stack = (array) ($pc['dorm']['deco_items'] ?? []);
    $n = count(array_keys($stack, $name, true));
    if ($n > 0) $units = $n;
    [$price, $cur] = crrg_trade_price($name);
    $gain = max(1, (int) floor($price * 0.5)) * $units;
    unset($pc['dorm']['deco'][$slot]);
    $pc['dorm']['deco_items'] = array_values(array_diff($stack, [$name]));
    $pc['money'] = (int) $pc['money'] + $gain;
    return [sprintf('你拆下%d 件「%s」，连同墙面痕迹一起处理掉。', $units, $name),
            sprintf('（返还 %d 元，折价 50%%。现有 %d 元。）', $gain, (int) $pc['money'])];
}
/** 卖掉宿舍里的一件家具 */
function crrg_trade_unsell_furn(&$pc, $name) {
    $furn = (array) ($pc['dorm']['furn'] ?? []);
    $idx = array_search($name, $furn, true);
    if ($idx === false) return ['宿舍里没有这件家具。'];
    unset($furn[$idx]);
    $pc['dorm']['furn'] = array_values($furn);
    [$price, $cur] = crrg_trade_price($name);
    $gain = max(1, (int) floor($price * 0.5));
    $pc['money'] = (int) $pc['money'] + $gain;
    return [sprintf('你把「%s」搬了出去，等在门口的后勤车拉走。', $name),
            sprintf('（返还 %d 元，折价 50%%。现有 %d 元。）', $gain, (int) $pc['money'])];
}
/** 面板用：某处能回收什么（背包物品） */
function crrg_trade_offers($pc, $where) {
    $out = [];
    foreach (crrg_drill_bag_counts($pc) as $id => $n) {
        if (in_array($id, crrg_trade_blocked(), true)) {
            if ($where === 'junk') $out[] = ['label' => sprintf('卖 %s ×%d（关键物·废品站只给 1 元）', crrg_drill_item_name($id), $n),
                                             'k' => 'sell', 'id' => $where . '|' . $id];
            continue;
        }
        [$price, $cur] = crrg_trade_price($id);
        if ($price <= 0) continue;
        $gain = max(1, (int) floor($price * crrg_trade_rate($where)));
        $out[] = ['label' => sprintf('卖 %s ×%d（+%d %s）', crrg_trade_name($id), $n, $gain, $cur === 'credits' ? '积分' : '元'),
                  'k' => 'sell', 'id' => $where . '|' . $id];
    }
    return $out;
}

/* ── 交付：给情报处换台灯（面板按钮与文字指令共用同一实现） ── */
function crrg_deliver_lamps(&$s, &$pc) {
    if (($s['room'] ?? '') !== 'b2b_intel') return ['台灯要交到情报分析室。'];
    $have = crrg_drill_own_count($pc, 'lamp_cold');
    if (in_array('lamp_delivered', (array) ($s['flags'] ?? []), true)) return ['两个位置上的台灯已经装好了。'];
    if ($have < 2) return ['情报处要两盏冷白台灯，你手上有 ' . $have . ' 盏。'];
    $left = 2;
    $items = (array) ($pc['dorm']['deco_items'] ?? []);
    while ($left > 0 && ($i = array_search('冷白台灯', $items, true)) !== false) { unset($items[$i]); $items = array_values($items); $left--; }
    $pc['dorm']['deco_items'] = $items;
    if ($left > 0) crrg_drill_bag_del_n($pc, 'lamp_cold', $left);
    if (($pc['dorm']['deco']['灯'] ?? '') === '冷白台灯') unset($pc['dorm']['deco']['灯']);
    if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
    $s['flags'][] = 'lamp_delivered';
    crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '给情报处换了两盏台灯。');
    return ['你把两盏冷白台灯搬到情报分析室，替下原来那两盏。',
            '情报员抬头看了一眼，说这下夜里看屏幕不那么刺了。',
            '（台灯已交付。回作战值班室找值班小队签字。）'];
}
