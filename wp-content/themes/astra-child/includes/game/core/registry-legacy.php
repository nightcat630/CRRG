<?php
/**
 * 自 includes/drill-hub.php 搬出的函数（2026-09-30）
 */
if (!defined('ABSPATH')) exit;

/* ================= 注册表 ================= */
function &crrg_drill_reg() {
    static $r = null;
    if ($r === null) $r = ['scenes' => [], 'missions' => [], 'shop' => [], 'cur' => 'base'];
    return $r;
}
function crrg_drill_register_scene($id, $def) { $r = &crrg_drill_reg(); $r['scenes'][$id] = $def; }
function crrg_drill_register_mission($id, $def) { $r = &crrg_drill_reg(); $r['missions'][$id] = $def + ['id' => $id]; }
function crrg_drill_register_shop($cat, $items) { $r = &crrg_drill_reg(); foreach ($items as $k => $v) $r['shop'][$cat][$k] = $v; }

function crrg_drill_set_scene($id) { $r = &crrg_drill_reg(); $r['cur'] = $id ?: 'base'; }
function crrg_drill_cur_scene() { $r = &crrg_drill_reg(); return $r['cur'] ?: 'base'; }
function crrg_drill_scene($id = null) {
    $r = &crrg_drill_reg();
    $id = $id ?: crrg_drill_cur_scene();
    return $r['scenes'][$id] ?? ($id === 'base' ? ['name' => '祁连山南麓总基地'] : null);
}
function crrg_drill_scene_rooms() { $s = crrg_drill_scene(); return $s['rooms'] ?? []; }
function crrg_drill_scene_pos()   { $s = crrg_drill_scene(); return $s['pos']   ?? []; }
function crrg_drill_scene_ents()  { $s = crrg_drill_scene(); return $s['ents']  ?? []; }
function crrg_drill_missions()   { $r = &crrg_drill_reg(); return $r['missions']; }
function crrg_drill_shop()       { $r = &crrg_drill_reg(); return $r['shop']; }

/* ================= 角色卡扩展字段 ================= */
function crrg_drill_pc_defaults($pc) {
    return $pc + ['credits' => 0, 'money' => 300, 'clearance' => 0, 'scene' => 'base',
                  'mission' => '', 'mission_step' => 0, 'mission_done' => [], 'train_tokens' => 0,
                  'vehicle' => '',
                  'dorm' => ['desc' => '', 'furn' => [], 'last' => '', 'deco' => []],
                  'log' => []];
}
/** 演习权限：职务给的基础卡级，与用积分买到的权限取高 */
function crrg_drill_clearance_name($lv) { return [1 => 'D', 3 => 'C', 5 => 'B', 6 => 'A', 7 => 'S'][$lv] ?? 'D'; }
function crrg_drill_apply_clearance(&$ctx, $pc) {
    $buy = (int) ($pc['clearance'] ?? 0);
    if ($buy > (int) $ctx['lv']) { $ctx['lv'] = $buy; $ctx['card'] = crrg_drill_clearance_name($buy); }
}

/* ================= 商店 ================= */
function crrg_drill_shop_seed() {
    // 类别 => [商品键 => [名称, 价格, 货币, 说明, 物品id(可选)]]
    crrg_drill_register_shop('补给', crrg_game_shop_stock_1());
    crrg_drill_register_shop('网购', crrg_game_shop_stock_2());
    crrg_drill_register_shop('宿舍', crrg_game_shop_stock_3());
    crrg_drill_register_shop('装备', crrg_game_shop_stock_4());
}
crrg_drill_shop_seed();

/* 商品在哪买：房间 → 可用的商店类别 */
function crrg_drill_shop_at($room) {
    return [
        'b3_warehouse'   => ['补给'],
        'b2f_gear'       => ['补给', '装备'],
        'b3_field_gear'  => ['补给', '装备'],
        'b3_armory'      => ['装备'],
        'b1_dorm'        => ['宿舍'],
        'b1_room'        => ['宿舍', '网购'],
        'b1_office'      => ['网购'],
        'b2b_tech'       => ['网购'],
    ][$room] ?? (function_exists('crrg_shop_at_extra') ? ((array) crrg_shop_at_extra())[$room] ?? [] : []);
}

/* ================= 晋升（消耗积分，只动演习权限） ================= */
function crrg_drill_promotions() {
    return [3 => 30, 5 => 250, 6 => 800];   // 目标卡级 => 积分（2026-09-28 上调；A 级封顶，若将来加更高档，按 2000 起步）
}

/* ================= 合约（任务） ================= */
/** 累计"在线时长"（现实秒）——离线不计（2026-09-29）
 *  规则：两次动作间隔 ≤ 15 分钟算在线；超过就视为"中间离开了"，那一段不计。
 *  用途：任务期限、库存衰减、每日一次的刷新都按它算，**玩家离线时计时暂停**。
 *  换算：在线 1 现实秒 = 48 游戏秒（与世界钟同速），所以"在线 1 现实分钟 = 48 游戏分钟"。 */
function crrg_played_sec(&$pc) {
    $now = time();
    $seen = (int) ($pc['played_seen'] ?? 0);
    $acc  = (int) ($pc['played_real'] ?? 0);
    if ($seen > 0) {
        $gap = $now - $seen;
        if ($gap > 0 && $gap <= 900) $acc += $gap;          // 在线：累加
        // gap > 900：中间离线，不加
    }
    $pc['played_seen'] = $now;
    $pc['played_real'] = $acc;
    return $acc;
}
/** 已在线时长换算成"游戏小时"（与世界钟同速） */
function crrg_played_game_hours(&$pc) { return crrg_played_sec($pc) * 48 / 3600.0; }
/**
 * 安全执行一条游戏指令（2026-09-29｜游戏侧优化·状态读写纪律）
 *   背景：`crrg_drill_exec($s, ...)` 的 `$s` 是**按值传**的——调用方必须用**返回的 $s** 覆盖并保存，
 *   否则写在 `$s` 里的旗标会丢；而 `$pc` 是按引用，照改不误 → 就会出现"只加了钱/好感、不记状态"的刷子。
 *   本函数把"跑一次指令 + 保存状态"收敛到一处，任何内部改调用都走它。
 *   @return array 输出行（文本）
 */
function crrg_game_exec(&$s, &$pc, $ctx, $uid, $raw) {
    if (!function_exists('crrg_drill_exec')) return ['游戏引擎未加载。'];
    $r = crrg_drill_exec($s, $ctx, (string) $raw, $pc, $uid);
    if (is_array($r)) {
        $s  = $r[0] ?? $s;
        $pc = $r[2] ?? $pc;
        if (function_exists('crrg_drill_save'))    crrg_drill_save($uid, $s);
        if (function_exists('crrg_drill_pc_save')) crrg_drill_pc_save($uid, $pc);
        return (array) ($r[1] ?? []);
    }
    return ['（这条指令没跑起来。）'];
}

function crrg_world_from_real($real) {
    if (!function_exists('crrg_world_clock')) return '';
    $o = get_option('crrg_drill_clock', null);
    if (!is_array($o) || empty($o['real'])) return '';
    $g = (int) $o['game'] + (int) round(((int) $real - (int) $o['real']) * CRRG_TIME_SCALE);
    return function_exists('crrg_wdate') ? crrg_wdate('m-d H:i', $g) : date('m-d H:i', $g);
}
