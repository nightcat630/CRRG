<?php
/**
 * CRRG 演习系统 · 内容（纯数据）· 商店定义与货品总表（基地商店、货品分档价格）
 * 2026-09-29 Phase 1：自 includes/drill-economy.php **原样搬出**（程序提取，零手抄）。
 */
if (!defined('ABSPATH')) exit;

function crrg_game_shops_data() {
    $base = [
        'garage_exchange' => ['n' => '车库调剂处', 'room' => 'garage_maint', 'cats' => ['车辆'],
            'keep' => '车库西侧隔出的一间小屋，窗口后面挂着一板车钥匙。'],
        'canteen' => ['n' => '食堂小卖部',     'room' => 'b1_canteen',    'cats' => ['食品', '药品', '粮油'],       'keep' => '食堂窗口旁边，铁皮柜改的。'],
        'super'   => ['n' => '基地商超',       'room' => 'b3_warehouse',  'cats' => ['食品', '日用', '药品', '家具', '装修', '杂货', '户外', '工具'], 'keep' => '仓库隔出来的一角，货架上贴着价签。'],
        'duty'    => ['n' => '勤务司兑换处',   'room' => 'b2f_gear',      'cats' => ['装备', '镇物'],       'keep' => '整备室靠里那张桌子，登记簿摊着。'],
        'gas_store' => ['n' => '加油站便利店', 'room' => 'tw_gas',        'cats' => ['便利店'],            'keep' => '加油机旁边的一间小玻璃房，货架上东西不多。'],
        'online'  => ['n' => '宿舍电脑·网购',  'room' => 'b1_room',       'cats' => ['网购'],              'keep' => '要先把电脑买进宿舍。', 'need' => 'pc'],
    ];
    if (function_exists('crrg_game_shops_extra')) $base = array_merge($base, (array) crrg_game_shops_extra());
    if (function_exists('crrg_game_shops_extra_gz')) $base = array_merge($base, (array) crrg_game_shops_extra_gz());
    return $base;}

function crrg_game_goods_data() {
    $base = [
        '户外' => [
            'tool_tent' => ['n' => '帐篷', 'p' => 150, 'cur' => 'money', 'item' => 'tool_tent', 'd' => '扎营必备'],
            'tool_sleepbag' => ['n' => '睡袋', 'p' => 80, 'cur' => 'money', 'item' => 'tool_sleepbag', 'd' => '扎营睡得好'],
            'tool_stove' => ['n' => '野营炉', 'p' => 90, 'cur' => 'money', 'item' => 'tool_stove', 'd' => '可烧水热饭'],
            'tool_jerrycan' => ['n' => '备用油桶 (20L)', 'p' => 60, 'cur' => 'money', 'item' => 'tool_jerrycan', 'd' => '空桶；在加油站可加满'],
            'tool_flask' => ['n' => '水壶', 'p' => 45, 'cur' => 'money', 'item' => 'tool_flask', 'd' => '可在水源处灌水（水房、食堂、泉眼）'],
        ],


        '食品' => [
            'drink_liquor' => ['n' => '烧酒',     'p' => 15, 'cur' => 'money', 'item' => 'drink_liquor', 'd' => '一斤装；也能当人情送'],
            'drink_tea'       => ['n' => '浓茶',     'p' => 6,  'cur' => 'money', 'item' => 'drink_tea',       'd' => '精力 +12，口渴 +12。'],
            'food_energybar' => ['n' => '能量棒',   'p' => 15, 'cur' => 'money', 'item' => 'food_energybar', 'd' => '饱食 +14，精力 +8。'],
            'food_meal'      => ['n' => '食堂热饭', 'p' => 8,  'cur' => 'money', 'item' => 'food_meal',      'd' => '饱食 +45、口渴 +10。'],
            'food_ration'   => ['n' => '压缩干粮', 'p' => 35,  'cur' => 'money', 'item' => 'food_ration', 'd' => '一块顶一顿。'],
            'can'      => ['n' => '罐头',     'p' => 28,  'cur' => 'money', 'item' => 'food_can',    'd' => '开罐器另配。'],
            'drink_coffee'   => ['n' => '罐装咖啡', 'p' => 35,  'cur' => 'money', 'item' => 'drink_coffee', 'd' => '值夜班用。'],
            'choco'    => ['n' => '巧克力',   'p' => 22,  'cur' => 'money', 'item' => 'food_choco',  'd' => '热量高，压得住手抖。'],
        ],
        '日用' => [
            'tool_battery'  => ['n' => '备用电池', 'p' => 12, 'cur' => 'money', 'item' => 'tool_battery'],
            'doc_pencil'   => ['n' => '铅笔',     'p' => 3,  'cur' => 'money', 'item' => 'doc_pencil'],
            'notepad'  => ['n' => '笔记本',   'p' => 12, 'cur' => 'money', 'item' => 'doc_notepad'],
            'glove'    => ['n' => '劳保手套', 'p' => 35, 'cur' => 'money', 'item' => 'gear_gloves'],
            'gear_raincoat' => ['n' => '雨衣',     'p' => 45, 'cur' => 'money', 'item' => 'gear_raincoat'],
            'gear_mask'     => ['n' => '防尘口罩', 'p' => 120, 'cur' => 'money', 'item' => 'gear_mask'],
        ],
        '粮油' => [
            'food_rice'   => ['n' => '大米 10kg', 'p' => 65, 'cur' => 'money', 'item' => 'food_rice', 'd' => '编织袋装，本地米。'],
            'food_flour'  => ['n' => '面粉 5kg',  'p' => 28, 'cur' => 'money', 'item' => 'food_flour', 'd' => '普通中筋粉。'],
            'food_oil'    => ['n' => '菜籽油 5L', 'p' => 90, 'cur' => 'money', 'item' => 'food_oil', 'd' => '铁桶装，压盖的。'],
            'food_soy'    => ['n' => '酱油',      'p' => 12, 'cur' => 'money', 'item' => 'food_soy', 'd' => '玻璃瓶，半斤装。'],
            'food_salt'   => ['n' => '食盐',      'p' => 3,  'cur' => 'money', 'item' => 'food_salt', 'd' => '加碘盐，一袋。'],
            'food_eggs'   => ['n' => '鸡蛋 30 枚', 'p' => 35, 'cur' => 'money', 'item' => 'food_eggs', 'd' => '纸托盘，有两个可换。'],
        ],
        '杂货' => [
            'tool_thermos' => ['n' => '暖水瓶',   'p' => 45, 'cur' => 'money', 'item' => 'tool_thermos', 'd' => '铁壳，瓶胆是新的。'],
            'tool_mug'     => ['n' => '搪瓷缸',   'p' => 15, 'cur' => 'money', 'item' => 'tool_mug', 'd' => '缸身印着红字标语。'],
            'smoke_matches' => ['n' => '火柴',     'p' => 2,  'cur' => 'money', 'item' => 'smoke_matches'],
            'tool_candle'  => ['n' => '蜡烛',     'p' => 6,  'cur' => 'money', 'item' => 'tool_candle', 'd' => '一包四支。'],
            'tool_tissue'  => ['n' => '卷纸',     'p' => 18, 'cur' => 'money', 'item' => 'tool_tissue'],
            'tool_soap'    => ['n' => '洗衣皂',   'p' => 5,  'cur' => 'money', 'item' => 'tool_soap'],
            'tool_flashlight' => ['n' => '手电筒', 'p' => 45, 'cur' => 'money', 'item' => 'tool_flashlight'],
        ],
        '工具' => [
            'tool_nails'   => ['n' => '铁钉 1kg', 'p' => 15, 'cur' => 'money', 'item' => 'tool_nails'],
            'tool_wire'    => ['n' => '铁丝 10m', 'p' => 25, 'cur' => 'money', 'item' => 'tool_wire'],
            'tool_tape'    => ['n' => '卷尺',     'p' => 25, 'cur' => 'money', 'item' => 'tool_tape'],
            'tool_steelbar'   => ['n' => '钢钎',     'p' => 60, 'cur' => 'money', 'item' => 'tool_steelbar', 'd' => '一米二，头部有敲打痕。'],
            'tool_shovel'  => ['n' => '工兵铲',   'p' => 180, 'cur' => 'money', 'item' => 'tool_shovel', 'd' => '可折叠，带帆布套。'],
            'tool_insultape'   => ['n' => '绝缘胶带', 'p' => 8,  'cur' => 'money', 'item' => 'tool_insultape'],
            'tool_rope_nylon'    => ['n' => '麻绳 10m', 'p' => 20, 'cur' => 'money', 'item' => 'tool_rope_nylon'],
        ],
        '便利店' => [
            'drink_mineral' => ['n' => '矿泉水',   'p' => 3,  'cur' => 'money', 'item' => 'drink_mineral'],
            'food_instant' => ['n' => '方便面',   'p' => 6,  'cur' => 'money', 'item' => 'food_instant'],
            'smoke_cig'     => ['n' => '卷烟',     'p' => 25, 'cur' => 'money', 'item' => 'smoke_cig'],
            'tool_lighter' => ['n' => '打火机',   'p' => 2,  'cur' => 'money', 'item' => 'tool_lighter'],
        ],
        '药品' => [
            'med_bandage'    => ['n' => '绷带',       'p' => 15,  'cur' => 'money', 'item' => 'med_bandage'],
            'med_firstaid'   => ['n' => '急救包',     'p' => 260,  'cur' => 'money', 'item' => 'med_firstaid', 'use' => 'hp'],
            'med_stabilizer' => ['n' => '精神稳定剂', 'p' => 900, 'cur' => 'money', 'item' => 'med_stabilizer', 'use' => 'san'],
            'sleeper'    => ['n' => '安眠药',     'p' => 55,  'cur' => 'money', 'item' => 'med_sleeper', 'd' => '宿舍休整时用，理智恢复 +1。'],
        ],
        '装备' => [
            'tool_lamp'    => ['n' => '战术手电',   'p' => 5, 'cur' => 'credits', 'item' => 'tool_lamp'],
            'weap_baton'   => ['n' => '警棍',       'p' => 8, 'cur' => 'credits', 'item' => 'weap_baton'],
            'gear_harness' => ['n' => '镇物携行具', 'p' => 10, 'cur' => 'credits', 'item' => 'gear_harness'],
            'weap_pistol'  => ['n' => '手枪',       'p' => 15, 'cur' => 'credits', 'item' => 'weap_pistol'],
            'gear_hazmat'    => ['n' => 'T3 防护服',  'p' => 20, 'cur' => 'credits', 'item' => 'gear_hazmat'],
            'tool_monitor' => ['n' => '便携式检测仪', 'p' => 25, 'cur' => 'credits', 'item' => 'tool_monitor'],
        ],
        '镇物' => [
            'key_sealbox' => ['n' => '小型封印匣', 'p' => 30, 'cur' => 'credits', 'item' => 'key_sealbox', 'd' => '登记制耗材。'],
            'sigil'   => ['n' => '镇物封条',   'p' => 6,  'cur' => 'credits', 'item' => 'key_sigil', 'd' => '一次性，封门用。'],
        ],
        '家具' => [
            'bed_hard' => ['n' => '单人铁床', 'p' => 650,  'cur' => 'money', 'dorm' => '单人铁床', 'fx' => ['san' => 1]],
            'bed_soft' => ['n' => '弹簧床',   'p' => 3800, 'cur' => 'money', 'dorm' => '弹簧床',   'fx' => ['san' => 3, 'hp' => 1]],
            'shelf'    => ['n' => '书架',     'p' => 480,  'cur' => 'money', 'dorm' => '书架',     'fx' => ['skill' => ['图书馆使用' => 1]]],
            'sofa'     => ['n' => '单人沙发', 'p' => 1600,  'cur' => 'money', 'dorm' => '单人沙发', 'fx' => ['san' => 1]],
            'tv'       => ['n' => '电视',     'p' => 2800, 'cur' => 'money', 'dorm' => '电视',     'fx' => ['san' => 2], 'd' => '看两集就困了。'],
            'pc'       => ['n' => '电脑',     'p' => 6500, 'cur' => 'money', 'dorm' => '电脑',     'unlock' => 'pc', 'd' => '解锁：宿舍网购、档案检索。'],
            'dumbbell' => ['n' => '哑铃',     'p' => 450,  'cur' => 'money', 'dorm' => '哑铃',     'fx' => ['train' => '力量']],
            'treadmil' => ['n' => '跑步机',   'p' => 3200, 'cur' => 'money', 'dorm' => '跑步机',   'fx' => ['train' => '体质']],
            'fridge'   => ['n' => '小冰箱',   'p' => 1300,  'cur' => 'money', 'dorm' => '小冰箱'],
            'teaset'   => ['n' => '茶具',     'p' => 220,  'cur' => 'money', 'dorm' => '茶具'],
            'humid'    => ['n' => '加湿器',   'p' => 420,  'cur' => 'money', 'dorm' => '加湿器'],
            'speaker'  => ['n' => '音响',     'p' => 2200,  'cur' => 'money', 'dorm' => '音响',     'fx' => ['san' => 1]],
            'rug'      => ['n' => '地毯',     'p' => 700,  'cur' => 'money', 'dorm' => '地毯'],
        ],
        '装修' => [
            'wall_grey' => ['n' => '墙纸·水泥灰', 'p' => 150, 'cur' => 'money', 'deco' => ['墙' => '水泥灰墙面，接缝齐整。']],
            'wall_warm' => ['n' => '墙纸·米色',   'p' => 190, 'cur' => 'money', 'deco' => ['墙' => '米色墙面，看着不那么冷。'], 'fx' => ['san' => 1]],
            'lamp_warm' => ['n' => '暖光壁灯',    'p' => 260, 'cur' => 'money', 'deco' => ['灯' => '一盏暖光壁灯，光压低。'], 'fx' => ['san' => 1]],
            'lamp_cold' => ['n' => '冷白台灯',    'p' => 190, 'cur' => 'money', 'deco' => ['灯' => '冷白台灯，写字够亮。']],
            'poster'    => ['n' => '挂画',        'p' => 320, 'cur' => 'money', 'deco' => ['画' => '一幅祁连山口的挂画。']],
        ],
        '车辆' => [
            'bike'  => ['n' => '自行车',   'p' => 600,  'cur' => 'money',   'vehicle' => '自行车',   'd' => '基地内代步，出勤少走两段路。'],
            'moto'  => ['n' => '越野摩托', 'p' => 120,  'cur' => 'credits', 'vehicle' => '越野摩托', 'd' => '能上山：出勤免车费，镇上往返免费。'],
            'jeep'  => ['n' => '越野车',   'p' => 300,  'cur' => 'credits', 'vehicle' => '越野车',   'd' => '能上山，可捎两名队员；出勤与镇上往返全免。'],
        ],
        '网购' => [
            'doc_camera'   => ['n' => '相机',       'p' => 4800, 'cur' => 'money', 'item' => 'doc_camera'],
            'doc_recorder' => ['n' => '录音笔',     'p' => 650, 'cur' => 'money', 'item' => 'doc_recorder'],
            'tool_binocular'    => ['n' => '望远镜',     'p' => 1400, 'cur' => 'money', 'item' => 'tool_binocular'],
            'tool_gps'      => ['n' => '手持定位仪', 'p' => 2600, 'cur' => 'money', 'item' => 'tool_gps'],
            'sup'      => ['n' => '综合补给包', 'p' => 520, 'cur' => 'money', 'bundle' => true, 'd' => '干粮、水、绷带、电池各一份。'],
        ],
    ];
    if (function_exists('crrg_game_goods_all_extra')) $base = array_merge($base, (array) crrg_game_goods_all_extra());
    return $base;}

/* 注册进中枢（优先级 21） */
add_action('init', function () {
    if (!function_exists('crrg_game_register_many')) return;
    crrg_game_register_many('shop', [
        'crrg_drill_shops' => crrg_game_shops_data(),
        'crrg_drill_goods' => crrg_game_goods_data(),
    ], ['module' => 'core', 'file' => __FILE__]);
}, 21);
