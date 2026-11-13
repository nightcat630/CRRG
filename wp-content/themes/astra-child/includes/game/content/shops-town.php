<?php
/**
 * CRRG 演习系统 · 内容（纯数据）· 镇上商店（青石镇各店的货架）
 * 2026-09-29 Phase 1：自 includes/drill-mod-town.php **原样搬出**（程序提取，零手抄）。
 */
if (!defined('ABSPATH')) exit;

function crrg_game_town_shops_data() {
    return [
        'town_coop'  => ['n' => '青石供销社', 'room' => 'tw_coop',  'cats' => ['杂货', '粮油', '食品'],
            'items' => ['tool_thermos', 'tool_mug', 'smoke_matches', 'tool_candle', 'tool_tissue', 'tool_soap', 'tool_flashlight', 'tool_battery', 'gear_raincoat', 'food_salt', 'food_soy'],
            'keep' => '玻璃柜台后面，价目表压在玻璃下。日用杂货和油盐酱醋都在这儿。'],
        'town_grain' => ['n' => '粮油店',     'room' => 'tw_grain', 'cats' => ['粮油'],
            'items' => ['food_rice', 'food_flour', 'food_oil', 'food_soy', 'food_salt', 'food_eggs'],
            'keep' => '米面油都有，论斤称。柜台角上还摆着一篮鸡蛋。'],
        'town_clinic' => ['n' => '卫生院药房', 'room' => 'tw_clinic', 'cats' => ['药品'],
            'items' => ['med_bandage', 'med_firstaid', 'sleeper'],
            'keep' => '药房窗口，值班医生管着钥匙。处方药要登记。'],
        'town_hw'    => ['n' => '五金店',     'room' => 'tw_hardware', 'cats' => ['工具', '日用'],
            'items' => ['tool_nails', 'tool_wire', 'tool_tape', 'tool_steelbar', 'tool_shovel', 'tool_insultape', 'tool_rope_nylon', 'glove', 'tool_battery'],
            'keep' => '钉子铁丝按号卖，工具挂在里墙。'],
        'town_gas'   => ['n' => '加油站便利店', 'room' => 'tw_gas', 'cats' => ['便利店'],
            'items' => ['drink_mineral', 'food_instant', 'smoke_cig', 'tool_lighter', 'tool_battery'],
            'keep' => '货架不大，都是路上要用的。'],
    ];
}

/* 注册进中枢（优先级 21） */
add_action('init', function () {
    if (!function_exists('crrg_game_register_many')) return;
    crrg_game_register_many('shop', [
        'crrg_drill_town_shops' => crrg_game_town_shops_data(),
    ], ['module' => 'core', 'file' => __FILE__]);
}, 21);
