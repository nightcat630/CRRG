<?php
/**
 * CRRG 演习系统 · 内容（纯数据）· 基地店铺货架
 *
 * ⚠ 2026-10-14 事故与重建（河洛自曝，留档）：
 *   我在给物品扩充加货架时，插入点选错，**把本文件后半段（网购／宿舍／装备 三个货架）整段切掉了** ✗
 *   → `crrg_game_shop_stock_2/3/4()` 未定义 → **站点每个页面 fatal** ✗（线上中断约十分钟）
 *   9 月的整站备份里没有这个文件（该文件是 2026-09-29 Phase 1 才拆出来的 ✗），无法整体回滚。
 *   重建口径：**只用能核实的证据**——
 *     · 「网购」5 件：内容与价格来自改造前我在会话里打印过的原文 ✓（逐条对得上）
 *     · 「装备」8 件：**物品 id 全部取自现有 items 表** ✓（价格按同类物品估，**待委员长核对**）
 *     · 「宿舍」：家具类是**名字**不是物品 id（见 lint 的家具豁免 ✓），按现有宿舍系统口径重建，**待核对**
 *   → 之后本文件改为**只追加**：新货架一律写成独立函数再在末尾合并 ✓（不再往中间插）
 */
if (!defined('ABSPATH')) exit;

/* 原键：补给 */
function crrg_game_shop_stock_1() {
    return [
        'food_ration'     => ['n' => '压缩干粮', 'p' => 20,  'cur' => 'money', 'item' => 'food_ration'],
        'tool_flask'      => ['n' => '水壶',     'p' => 15,  'cur' => 'money', 'item' => 'tool_flask'],
        'med_firstaid'   => ['n' => '急救包',   'p' => 60,  'cur' => 'money', 'item' => 'med_firstaid'],
        'med_bandage'    => ['n' => '绷带',     'p' => 10,  'cur' => 'money', 'item' => 'med_bandage'],
        'med_stabilizer' => ['n' => '精神稳定剂', 'p' => 120, 'cur' => 'money', 'item' => 'med_stabilizer'],
        'tool_battery'    => ['n' => '备用电池', 'p' => 25,  'cur' => 'money', 'item' => 'tool_battery'],
        'tool_flashlight' => ['n' => '手电筒',   'p' => 80,  'cur' => 'money', 'item' => 'tool_flashlight'],
        'gear_mask'       => ['n' => '防尘口罩', 'p' => 40,  'cur' => 'money', 'item' => 'gear_mask'],
        'doc_pencil'     => ['n' => '铅笔',     'p' => 5,   'cur' => 'money', 'item' => 'doc_pencil'],
    ] + (function_exists('crrg_game_shop_stock_extra_food') ? array_filter((array) crrg_game_shop_stock_extra_food()) : [])
      + (function_exists('crrg_game_shop_stock_extra2_food') ? array_filter((array) crrg_game_shop_stock_extra2_food()) : []);
}

/* 原键：网购（5 件，价格照改造前原文 ✓） */
function crrg_game_shop_stock_2() {
    return [
        'doc_camera'   => ['n' => '相机',     'p' => 800, 'cur' => 'money', 'item' => 'doc_camera', 'd' => '外勤取证用，带闪光灯。'],
        'doc_recorder' => ['n' => '录音笔',   'p' => 300, 'cur' => 'money', 'item' => 'doc_recorder', 'd' => '最长连续录六小时。'],
        'tool_binocular'    => ['n' => '望远镜',   'p' => 500, 'cur' => 'money', 'item' => 'tool_binocular', 'd' => '10×42，观察哨用。'],
        'tool_gps'      => ['n' => '手持定位仪', 'p' => 650, 'cur' => 'money', 'item' => 'tool_gps', 'd' => '野外定点。'],
        'gear_raincoat' => ['n' => '雨衣',     'p' => 60,  'cur' => 'money', 'item' => 'gear_raincoat', 'd' => '一次性，两件装。'],
    ] + (function_exists('crrg_game_shop_stock_extra_tool') ? array_filter((array) crrg_game_shop_stock_extra_tool()) : [])
      + (function_exists('crrg_game_shop_stock_extra2_tool') ? array_filter((array) crrg_game_shop_stock_extra2_tool()) : []);
}

/* 原键：宿舍（家具是**名字**不是物品 id ✓ 见 lint 的家具豁免） ⚠ 重建，待委员长核对 */
function crrg_game_shop_stock_3() {
    return [
        'dorm_bed_sheet' => ['n' => '床单被罩', 'p' => 120, 'cur' => 'money', 'dorm' => '床单被罩', 'd' => '换一套，睡得踏实一点。'],
        'dorm_kettle'    => ['n' => '电热壶',   'p' => 180, 'cur' => 'money', 'dorm' => '电热壶',   'd' => '烧水用。夜里也能弄碗热的。'],
        'dorm_desk_lamp' => ['n' => '台灯',     'p' => 90,  'cur' => 'money', 'dorm' => '台灯',     'd' => '床头看书，不吵别人。'],
        'dorm_rug'       => ['n' => '旧地毯',   'p' => 70,  'cur' => 'money', 'dorm' => '旧地毯',   'd' => '铺上不硌脚。'],
    ];
}

/* 原键：装备（id 全部取自现有 items 表 ✓；价格按同类估 ⚠ 重建，待委员长核对） */
function crrg_game_shop_stock_4() {
    return [
        'weap_baton'    => ['n' => '警棍',     'p' => 120,  'cur' => 'money', 'item' => 'weap_baton'],
        'weap_knife'    => ['n' => '匕首',     'p' => 80,   'cur' => 'money', 'item' => 'weap_knife'],
        'weap_pistol'   => ['n' => '手枪',     'p' => 1200, 'cur' => 'money', 'item' => 'weap_pistol', 'd' => '领用要登记 ✓'],
        'ammo_9mm'      => ['n' => '9mm 弹 一盒', 'p' => 60, 'cur' => 'money', 'item' => 'ammo_9mm'],
        'gear_harness'  => ['n' => '镇物携行具', 'p' => 150, 'cur' => 'money', 'item' => 'gear_harness'],
        'gear_vest'     => ['n' => '防刺背心',  'p' => 220,  'cur' => 'money', 'item' => 'gear_vest'],
        'gear_helmet'   => ['n' => '安全帽',    'p' => 60,   'cur' => 'money', 'item' => 'gear_helmet'],
        'tool_crowbar'  => ['n' => '撬棍',      'p' => 120,  'cur' => 'money', 'item' => 'tool_crowbar'],
    ];
}

/** 2026-10-14：**扩充货架**（只追加，不往中间插 ✓ 事故教训） */
function crrg_game_shop_stock_extra_food() {
    return [
        'food_potato'     => ['n' => '土豆 一袋',   'p' => 30,  'cur' => 'money', 'item' => 'food_potato'],
        'food_cabbage'    => ['n' => '白菜 两棵',   'p' => 18,  'cur' => 'money', 'item' => 'food_cabbage'],
        'food_eggs'       => ['n' => '鸡蛋 三十枚', 'p' => 45,  'cur' => 'money', 'item' => 'food_eggs'],
        'food_noodle_dry' => ['n' => '挂面 1kg',    'p' => 22,  'cur' => 'money', 'item' => 'food_noodle_dry'],
        'food_lard'       => ['n' => '猪油 一罐',   'p' => 28,  'cur' => 'money', 'item' => 'food_lard'],
        'food_salt'       => ['n' => '食盐',        'p' => 6,   'cur' => 'money', 'item' => 'food_salt'],
        'food_yeast'      => ['n' => '酵母 一小袋', 'p' => 5,   'cur' => 'money', 'item' => 'food_yeast'],
        'food_can_meat'   => ['n' => '午餐肉罐头',  'p' => 40,  'cur' => 'money', 'item' => 'food_can_meat'],
        'drink_boiled'    => ['n' => '开水 一壶',   'p' => 3,   'cur' => 'money', 'item' => 'drink_boiled'],
        'drink_soda'      => ['n' => '汽水 两瓶',   'p' => 10,  'cur' => 'money', 'item' => 'drink_soda'],
        'drink_beer'      => ['n' => '啤酒 四听',   'p' => 24,  'cur' => 'money', 'item' => 'drink_beer'],
    ];
}
function crrg_game_shop_stock_extra_tool() {
    return [
        'tool_wok'      => ['n' => '铁锅',      'p' => 90,  'cur' => 'money', 'item' => 'tool_wok',     'd' => '做饭的家伙。'],
        'tool_cleaver'  => ['n' => '菜刀',      'p' => 55,  'cur' => 'money', 'item' => 'tool_cleaver'],
        'tool_board'    => ['n' => '案板',      'p' => 30,  'cur' => 'money', 'item' => 'tool_board'],
        'smoke_stove'   => ['n' => '煤油炉',    'p' => 160, 'cur' => 'money', 'item' => 'smoke_stove',  'd' => '有火才能做饭。'],
        'smoke_matches' => ['n' => '火柴 一盒', 'p' => 2,   'cur' => 'money', 'item' => 'smoke_matches'],
        'tool_wrench'   => ['n' => '活动扳手',  'p' => 85,  'cur' => 'money', 'item' => 'tool_wrench'],
        'gear_gloves'   => ['n' => '劳保手套',  'p' => 25,  'cur' => 'money', 'item' => 'gear_gloves'],
        'gear_boots'    => ['n' => '胶靴',      'p' => 70,  'cur' => 'money', 'item' => 'gear_boots'],
    ];
}

/** 2026-10-14 下午：**第二批货架**（只追加在文件末尾 ✓ 绝不往中段插） */
function crrg_game_shop_stock_extra2_food() {
    return [
        'food_vermicelli' => ['n' => '粉条 一把',   'p' => 12, 'cur' => 'money', 'item' => 'food_vermicelli'],
        'food_tofu'       => ['n' => '豆腐 两块',   'p' => 8,  'cur' => 'money', 'item' => 'food_tofu'],
        'food_bean_sprout'=> ['n' => '豆芽 一袋',   'p' => 5,  'cur' => 'money', 'item' => 'food_bean_sprout'],
        'food_wood_ear'   => ['n' => '木耳 一小包', 'p' => 15, 'cur' => 'money', 'item' => 'food_wood_ear'],
        'food_peanut'     => ['n' => '花生米 一袋', 'p' => 14, 'cur' => 'money', 'item' => 'food_peanut'],
        'food_sausage'    => ['n' => '香肠 几根',   'p' => 26, 'cur' => 'money', 'item' => 'food_sausage'],
        'food_chicken'    => ['n' => '整鸡 一只',   'p' => 60, 'cur' => 'money', 'item' => 'food_chicken'],
        'food_radish'     => ['n' => '萝卜 几个',   'p' => 6,  'cur' => 'money', 'item' => 'food_radish'],
        'food_pepper_corn'=> ['n' => '花椒',        'p' => 4,  'cur' => 'money', 'item' => 'food_pepper_corn'],
        'food_star_anise' => ['n' => '八角',        'p' => 4,  'cur' => 'money', 'item' => 'food_star_anise'],
        'food_bean_paste' => ['n' => '豆瓣酱',      'p' => 12, 'cur' => 'money', 'item' => 'food_bean_paste'],
        'food_cooking_wine'=> ['n' => '料酒',       'p' => 9,  'cur' => 'money', 'item' => 'food_cooking_wine'],
        'food_honey'      => ['n' => '蜂蜜 一罐',   'p' => 35, 'cur' => 'money', 'item' => 'food_honey'],
        'food_brown_sugar'=> ['n' => '红糖',        'p' => 8,  'cur' => 'money', 'item' => 'food_brown_sugar'],
        'drink_soymilk'   => ['n' => '豆浆 一壶',   'p' => 6,  'cur' => 'money', 'item' => 'drink_soymilk'],
        'drink_malt'      => ['n' => '麦乳精 一罐', 'p' => 28, 'cur' => 'money', 'item' => 'drink_malt'],
        'drink_coffee_ins'=> ['n' => '速溶咖啡 几袋','p' => 15,'cur' => 'money', 'item' => 'drink_coffee_ins'],
        'drink_salt_soda' => ['n' => '盐汽水',      'p' => 5,  'cur' => 'money', 'item' => 'drink_salt_soda'],
        'food_biscuit'    => ['n' => '饼干 一包',   'p' => 7,  'cur' => 'money', 'item' => 'food_biscuit'],
        'food_nang'       => ['n' => '馕',          'p' => 6,  'cur' => 'money', 'item' => 'food_nang'],
    ];
}
function crrg_game_shop_stock_extra2_tool() {
    return [
        'tool_screwdriver' => ['n' => '螺丝刀 一套', 'p' => 45,  'cur' => 'money', 'item' => 'tool_screwdriver'],
        'tool_pliers'      => ['n' => '钳子',        'p' => 38,  'cur' => 'money', 'item' => 'tool_pliers'],
        'tool_saw'         => ['n' => '手锯',        'p' => 65,  'cur' => 'money', 'item' => 'tool_saw'],
        'tool_hammer'      => ['n' => '锤子',        'p' => 30,  'cur' => 'money', 'item' => 'tool_hammer'],
        'tool_ziptie'      => ['n' => '扎带 一把',   'p' => 8,   'cur' => 'money', 'item' => 'tool_ziptie'],
        'tool_nylon_rope'  => ['n' => '尼龙绳 二十米','p' => 40, 'cur' => 'money', 'item' => 'tool_nylon_rope'],
        'tool_headlamp'    => ['n' => '头灯',        'p' => 95,  'cur' => 'money', 'item' => 'tool_headlamp'],
        'tool_glowstick'   => ['n' => '荧光棒 两支', 'p' => 10,  'cur' => 'money', 'item' => 'tool_glowstick'],
        'tool_compass'     => ['n' => '罗盘',        'p' => 120, 'cur' => 'money', 'item' => 'tool_compass'],
        'tool_whistle'     => ['n' => '哨子',        'p' => 12,  'cur' => 'money', 'item' => 'tool_whistle'],
        'tool_thermo_hum'  => ['n' => '温湿度计',    'p' => 70,  'cur' => 'money', 'item' => 'tool_thermo_hum'],
        'gear_goggles'     => ['n' => '护目镜',      'p' => 45,  'cur' => 'money', 'item' => 'gear_goggles'],
        'gear_earmuff'     => ['n' => '隔音耳罩',    'p' => 60,  'cur' => 'money', 'item' => 'gear_earmuff'],
        'gear_cut_gloves'  => ['n' => '防割手套',    'p' => 55,  'cur' => 'money', 'item' => 'gear_cut_gloves'],
        'gear_coat'        => ['n' => '棉大衣',      'p' => 180, 'cur' => 'money', 'item' => 'gear_coat'],
        'gear_vest_ref'    => ['n' => '反光背心',    'p' => 35,  'cur' => 'money', 'item' => 'gear_vest_ref'],
        'med_bandaid'      => ['n' => '创可贴 一片', 'p' => 1,   'cur' => 'money', 'item' => 'med_bandaid'],
        'med_cold'         => ['n' => '感冒药 一板', 'p' => 12,  'cur' => 'money', 'item' => 'med_cold'],
        'med_stomach'      => ['n' => '胃药',        'p' => 14,  'cur' => 'money', 'item' => 'med_stomach'],
        'med_fengyou'      => ['n' => '风油精',      'p' => 6,   'cur' => 'money', 'item' => 'med_fengyou'],
        'doc_duty_log'     => ['n' => '值班日志',    'p' => 25,  'cur' => 'money', 'item' => 'doc_duty_log'],
        'doc_pen'          => ['n' => '钢笔',        'p' => 40,  'cur' => 'money', 'item' => 'doc_pen'],
        'doc_ink'          => ['n' => '墨水 一瓶',   'p' => 8,   'cur' => 'money', 'item' => 'doc_ink'],
        'smoke_pack'       => ['n' => '香烟 一包',   'p' => 18,  'cur' => 'money', 'item' => 'smoke_pack'],
        'smoke_charcoal'   => ['n' => '木炭 一袋',   'p' => 22,  'cur' => 'money', 'item' => 'smoke_charcoal'],
        'veh_battery'      => ['n' => '蓄电池',      'p' => 420, 'cur' => 'money', 'item' => 'veh_battery'],
        'veh_spark_plug'   => ['n' => '火花塞 四个', 'p' => 90,  'cur' => 'money', 'item' => 'veh_spark_plug'],
        'veh_tow_rope'     => ['n' => '拖车绳',      'p' => 110, 'cur' => 'money', 'item' => 'veh_tow_rope'],
        'veh_triangle'     => ['n' => '三角牌',      'p' => 35,  'cur' => 'money', 'item' => 'veh_triangle'],
    ];
}
