<?php
/** CRRG 演习系统 · 内容（纯数据）· 物品分类表（Phase 1，自 drill-item-taxonomy.php 原样搬出） */
if (!defined('ABSPATH')) exit;

function crrg_game_tax_major_data() {
    return [
        'tool'  => ['n' => '工具器械', 'pfx' => 'tool_',  'order' => 10, 'd' => '照明、绳索、破拆、通讯、检测、封存、能源、野营、维修、日用'],
        'med'   => ['n' => '医疗用品', 'pfx' => 'med_',   'order' => 20, 'd' => '急救耗材、药品、精神类、器械'],
        'food'  => ['n' => '食品',     'pfx' => 'food_',  'order' => 30, 'd' => '主食干粮、粮油、调味、生鲜、熟食、零食'],
        'drink' => ['n' => '饮品',     'pfx' => 'drink_', 'order' => 40, 'd' => '饮水、热饮、冷饮、酒类'],
        'smoke' => ['n' => '烟与火具', 'pfx' => 'smoke_', 'order' => 50, 'd' => '卷烟、火具、其他嗜好品'],
        'weap'  => ['n' => '武器',     'pfx' => 'weap_',  'order' => 60, 'd' => '火器、近战、投掷、重型'],
        'ammo'  => ['n' => '弹药',     'pfx' => 'ammo_',  'order' => 70, 'd' => '手枪弹、步枪弹、霰弹、爆炸物、特殊弹'],
        'gear'  => ['n' => '穿戴防护', 'pfx' => 'gear_',  'order' => 80, 'd' => '躯干、头部、手部、足部、面部、携行、全身'],
        'key'   => ['n' => '关键物',   'pfx' => 'key_',   'order' => 90, 'd' => '门禁、样本、镇物相关、任务信物、车钥匙'],
        'doc'   => ['n' => '资料情报', 'pfx' => 'doc_',   'order' => 100, 'd' => '笔记、影像、档案、地图、记录设备'],
        'veh'   => ['n' => '车辆配件', 'pfx' => 'veh_',   'order' => 110, 'd' => '备件、燃油、车用工具、车证、载具本体'],
        'misc'  => ['n' => '杂项',     'pfx' => '',       'order' => 120, 'd' => '交易品、纪念品、待鉴定物'],
    ];
}

function crrg_game_tax_minor_data() {
    return [
        /* 工具器械 */
        'tool_light'   => ['n' => '照明',         'p' => 'tool', 'order' => 11, 'd' => '手电、营地灯、荧光棒'],
        'tool_power'   => ['n' => '能源电池',     'p' => 'tool', 'order' => 12, 'd' => '电池、充电宝、发电机'],
        'tool_rope'    => ['n' => '绳索攀爬',     'p' => 'tool', 'order' => 13, 'd' => '绳索、绳梯、挂钩'],
        'tool_break'   => ['n' => '破拆挖掘',     'p' => 'tool', 'order' => 14, 'd' => '撬棍、工兵铲、钢钎、锤'],
        'tool_comm'    => ['n' => '通讯',         'p' => 'tool', 'order' => 15, 'd' => '对讲机、卫星电话、信号弹'],
        'tool_survey'  => ['n' => '检测测绘',     'p' => 'tool', 'order' => 16, 'd' => '检测仪、定位仪、望远镜、卷尺'],
        'tool_cont'    => ['n' => '封存收容',     'p' => 'tool', 'order' => 17, 'd' => '封印匣、收纳袋、标记带'],
        'tool_camp'    => ['n' => '野营',         'p' => 'tool', 'order' => 18, 'd' => '帐篷、睡袋、炉子、水壶'],
        'tool_repair'  => ['n' => '维修工具',     'p' => 'tool', 'order' => 19, 'd' => '扳手、胶带、铁丝、铁钉、机油'],
        'tool_life'    => ['n' => '生活日用',     'p' => 'tool', 'order' => 20, 'd' => '缸、瓶、纸巾、肥皂、针线'],
        'tool_kitchen' => ['n' => '厨具炊具',     'p' => 'tool', 'order' => 21, 'd' => '锅、刀叉、饭盒、暖水瓶'],
        'tool_clean'   => ['n' => '清洁洗护',     'p' => 'tool', 'order' => 22, 'd' => '洗涤、刷子、消毒水'],
        /* 医疗 */
        'med_firstaid'=> ['n' => '急救耗材',     'p' => 'med',  'order' => 21, 'd' => '绷带、纱布、止血带'],
        'med_drug'     => ['n' => '药品',         'p' => 'med',  'order' => 22, 'd' => '抗生素、止痛、镇静剂'],
        'med_psych'    => ['n' => '精神类药物',   'p' => 'med',  'order' => 23, 'd' => '稳定剂、安眠药、抗抑郁'],
        'med_inst'     => ['n' => '医疗器械',     'p' => 'med',  'order' => 24, 'd' => '听诊器、注射器、手术包'],
        /* 食品 */
        'food_staple'  => ['n' => '主食干粮',     'p' => 'food', 'order' => 31, 'd' => '干粮、饼干、能量棒'],
        'food_grain'   => ['n' => '粮油',         'p' => 'food', 'order' => 32, 'd' => '米、面、油、豆'],
        'food_cond'    => ['n' => '调味',         'p' => 'food', 'order' => 33, 'd' => '酱油、盐、醋、糖'],
        'food_fresh'   => ['n' => '生鲜蛋奶',     'p' => 'food', 'order' => 34, 'd' => '蛋、奶、蔬菜、肉'],
        'food_ready'   => ['n' => '熟食热餐',     'p' => 'food', 'order' => 35, 'd' => '食堂饭、面馆、盒饭'],
        'food_snack'   => ['n' => '零食',         'p' => 'food', 'order' => 36, 'd' => '糖果、坚果、蜜饯'],
        /* 饮品 */
        'drink_water'  => ['n' => '饮水',         'p' => 'drink','order' => 41, 'd' => '矿泉水、净化水、桶装水'],
        'drink_hot'    => ['n' => '热饮',         'p' => 'drink','order' => 42, 'd' => '茶、咖啡、热水'],
        'drink_cold'   => ['n' => '冷饮',         'p' => 'drink','order' => 43, 'd' => '汽水、酸奶、冰袋'],
        'drink_alcohol'=> ['n' => '酒类',         'p' => 'drink','order' => 44, 'd' => '白酒、啤酒、药酒'],
        /* 烟与火具 */
        'smoke_cig'    => ['n' => '卷烟',         'p' => 'smoke','order' => 51, 'd' => '卷烟、烟丝、雪茄'],
        'smoke_fire'   => ['n' => '火具',         'p' => 'smoke','order' => 52, 'd' => '火柴、打火机、火石'],
        'smoke_other'  => ['n' => '其他嗜好品',   'p' => 'smoke','order' => 53, 'd' => '槟榔、酒糖、口香糖'],
        /* 武器 */
        'weap_fire'    => ['n' => '火器',         'p' => 'weap', 'order' => 61, 'd' => '手枪、步枪、霰弹枪'],
        'weap_melee'   => ['n' => '近战',         'p' => 'weap', 'order' => 62, 'd' => '匕首、警棍、军刺'],
        'weap_throw'   => ['n' => '投掷',         'p' => 'weap', 'order' => 63, 'd' => '飞刀、燃烧瓶、雷管'],
        'weap_heavy'   => ['n' => '重型',         'p' => 'weap', 'order' => 64, 'd' => '机枪、榴弹发射器、特种器材'],
        /* 弹药 */
        'ammo_pistol'  => ['n' => '手枪弹',       'p' => 'ammo', 'order' => 71, 'd' => '9mm 等'],
        'ammo_rifle'   => ['n' => '步枪弹',       'p' => 'ammo', 'order' => 72, 'd' => '5.56／7.62 等'],
        'ammo_shot'    => ['n' => '霰弹',         'p' => 'ammo', 'order' => 73, 'd' => '12 号等'],
        'ammo_exp'     => ['n' => '爆炸物',       'p' => 'ammo', 'order' => 74, 'd' => '炸药、雷管、引信'],
        'ammo_special' => ['n' => '特殊弹',       'p' => 'ammo', 'order' => 75, 'd' => '信号弹、催泪弹、标记弹'],
        /* 穿戴 */
        'gear_body'    => ['n' => '躯干防护',     'p' => 'gear', 'order' => 81, 'd' => '护具、防刺背心'],
        'gear_head'    => ['n' => '头部防护',     'p' => 'gear', 'order' => 82, 'd' => '头盔、安全帽'],
        'gear_hand'    => ['n' => '手部',         'p' => 'gear', 'order' => 83, 'd' => '手套、护腕'],
        'gear_foot'    => ['n' => '足部',         'p' => 'gear', 'order' => 84, 'd' => '登山靴、护腿'],
        'gear_face'    => ['n' => '面部呼吸',     'p' => 'gear', 'order' => 85, 'd' => '口罩、面罩、滤罐'],
        'gear_carry'   => ['n' => '携行具',       'p' => 'gear', 'order' => 86, 'd' => '背包、携行具、腰包'],
        'gear_suit'    => ['n' => '全身防护',     'p' => 'gear', 'order' => 87, 'd' => '防护服、雨衣、隔离衣'],
        /* 关键物 */
        'key_door'     => ['n' => '门禁钥匙',     'p' => 'key',  'order' => 91, 'd' => '门禁卡、钥匙、密码条'],
        'key_sample'   => ['n' => '样本',         'p' => 'key',  'order' => 92, 'd' => '土壤、组织、水体样本'],
        'key_sigil'    => ['n' => '镇物相关',     'p' => 'key',  'order' => 93, 'd' => '符纸、封印物、镇物碎片'],
        'key_task'     => ['n' => '任务信物',     'p' => 'key',  'order' => 94, 'd' => '委托物、信物、证物'],
        'key_vehicle'  => ['n' => '车辆钥匙',     'p' => 'key',  'order' => 95, 'd' => '车钥匙、启动卡'],
        /* 资料 */
        'doc_note'     => ['n' => '笔记记录',     'p' => 'doc',  'order' => 101, 'd' => '笔记本、便签、记录板'],
        'doc_photo'    => ['n' => '影像',         'p' => 'doc',  'order' => 102, 'd' => '照片、胶卷、存储卡'],
        'doc_file'     => ['n' => '档案文书',     'p' => 'doc',  'order' => 103, 'd' => '报告、卷宗、报纸'],
        'doc_map'      => ['n' => '地图图纸',     'p' => 'doc',  'order' => 104, 'd' => '地图、草图、线路图'],
        'doc_device'   => ['n' => '记录设备',     'p' => 'doc',  'order' => 105, 'd' => '相机、录音笔、测量仪'],
        /* 车辆配件 */
        'veh_parts'    => ['n' => '备件',         'p' => 'veh',  'order' => 111, 'd' => '轮胎、皮带、灯泡、保险丝'],
        'veh_fuel'     => ['n' => '燃油',         'p' => 'veh',  'order' => 112, 'd' => '汽油桶、柴油、机油'],
        'veh_tool'     => ['n' => '车用工具',     'p' => 'veh',  'order' => 113, 'd' => '千斤顶、拖车绳、搭电线'],
        'veh_docs'     => ['n' => '车证证件',     'p' => 'veh',  'order' => 114, 'd' => '行驶证、通行证、派车单'],
        'veh_body'     => ['n' => '载具本体',     'p' => 'veh',  'order' => 115, 'd' => '自行车、摩托、越野车'],
        /* 杂项 */
        'misc_trade'   => ['n' => '交易品',       'p' => 'misc', 'order' => 121, 'd' => '可回收、可寄卖的东西'],
        'misc_souvenir'=> ['n' => '纪念品',       'p' => 'misc', 'order' => 122, 'd' => '带不出手但舍不得扔'],
        'misc_unknown' => ['n' => '待鉴定',       'p' => 'misc', 'order' => 123, 'd' => '来历不明，需要人看'],
    ];
}

add_action('init', function () {
    if (!function_exists('crrg_game_register_many')) return;
    crrg_game_register_many('text', ['tax_major' => crrg_game_tax_major_data(), 'tax_minor' => crrg_game_tax_minor_data()], ['module' => 'core', 'file' => __FILE__]);
}, 21);
