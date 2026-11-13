<?php
/**
 * CRRG 演习系统 · 内容扩充 · 物品（2026-10-14）
 * ------------------------------------------------------------
 * 委员长："多加点物品（**注意看文件要求**），各种类型的物品都要加，最好要有各种作用。"
 *
 * 照现有规矩写（见 content/items.php 与 taxonomy.php）：
 *   · 字段：`cat`（必须是小类 id）／`n` 名／`d` 描述／`take` 可携带
 *   · 可吃的标 `food`、原料标 `ingredient`、可喝的标 `drink`（**lint-names 会查一致性** ✗）
 *   · 有身体效果的走 `crrg_vitals_item_fx()`（本文件末尾**追加**，同一份真相 ✓）
 *   · 待鉴定的用 `unknown` ＋ `appr`（科学／神秘／医疗）＋ `id_note`／`id_flag` ✓
 *   · 战斗相关的用 `combat`（如 stun）✓
 *
 * 设计口径：**每一件都要有用**——能做饭、能治病、能修车、能收容、能换钱、能当线索载体 ✓
 * （不写"纯装饰"的物品 ✗ 那是背包垃圾。）
 */
if (!defined('ABSPATH')) exit;

/** 物品扩充包（由 content/items.php 在返回时合并 ✓） */
function crrg_game_items_data_extra() {
    return [

        /* ══════════ 食材 · 粮油（food_grain）══════════ */
        'food_noodle_dry' => ['cat' => 'food_grain', 'n' => '挂面 1kg', 'd' => '纸卷，扎着麻绳。', 'ingredient' => true, 'take' => true],
        'food_lard'       => ['cat' => 'food_grain', 'n' => '猪油 1 罐', 'd' => '搪瓷罐，凝着白霜——炒菜香。', 'ingredient' => true, 'take' => true],
        'food_dried_mush' => ['cat' => 'food_grain', 'n' => '干香菇', 'd' => '布袋装，泡开了能用三回。', 'ingredient' => true, 'take' => true],

        /* ══════════ 食材 · 生鲜（food_fresh）══════════ */
        'food_potato'     => ['cat' => 'food_fresh', 'n' => '土豆 一袋', 'd' => '带泥，十个上下。放得住。', 'ingredient' => true, 'take' => true],
        'food_cabbage'    => ['cat' => 'food_fresh', 'n' => '白菜 两棵', 'd' => '外帮有点冻伤，里头是好的。', 'ingredient' => true, 'take' => true],
        'food_onion'      => ['cat' => 'food_fresh', 'n' => '洋葱 几头', 'd' => '紫皮，辣眼睛。', 'ingredient' => true, 'take' => true],
        'food_tomato_can' => ['cat' => 'food_fresh', 'n' => '番茄罐头', 'd' => '铁皮罐，盖子上印着红番茄。', 'ingredient' => true, 'take' => true],
        'food_pork_cured' => ['cat' => 'food_fresh', 'n' => '腌肉 一块', 'd' => '挂在灶上熏过，切下来能炒一盘。', 'ingredient' => true, 'take' => true],
        'food_fish_salt'  => ['cat' => 'food_fresh', 'n' => '咸鱼 两条', 'd' => '盐味重，能下饭。', 'ingredient' => true, 'take' => true],
        'food_can_meat'   => ['cat' => 'food_fresh', 'n' => '午餐肉罐头', 'd' => '方形铁罐，开罐器在盖下。', 'food' => true, 'ingredient' => true, 'take' => true],

        /* ══════════ 调味（food_cond）══════════ */
        'food_vinegar'    => ['cat' => 'food_cond', 'n' => '陈醋', 'd' => '玻璃瓶，封着蜡。', 'ingredient' => true, 'take' => true],
        'food_sugar'      => ['cat' => 'food_cond', 'n' => '白糖 一袋', 'd' => '结块了，敲一敲还能用。', 'ingredient' => true, 'take' => true],
        'food_chili'      => ['cat' => 'food_cond', 'n' => '干辣椒', 'd' => '一小串，挂在灶边。', 'ingredient' => true, 'take' => true],
        'food_spice13'    => ['cat' => 'food_cond', 'n' => '十三香', 'd' => '塑料袋装，用了一半。', 'ingredient' => true, 'take' => true],
        'food_ginger'     => ['cat' => 'food_cond', 'n' => '姜 一块', 'd' => '老姜，切两片就够。', 'ingredient' => true, 'take' => true],
        'food_garlic'     => ['cat' => 'food_cond', 'n' => '蒜 一头', 'd' => '皮还紧。', 'ingredient' => true, 'take' => true],
        'food_yeast'      => ['cat' => 'food_cond', 'n' => '酵母 一小袋', 'd' => '发面用的，开封后得扎紧。', 'ingredient' => true, 'take' => true],

        /* ══════════ 熟食（food_ready）——多为**烹饪产物** ✓ ══════════ */
        'food_bun'        => ['cat' => 'food_ready', 'n' => '馒头', 'd' => '刚出锅的白面馒头，两个。', 'food' => true, 'take' => true],
        'food_stew'       => ['cat' => 'food_ready', 'n' => '炖菜', 'd' => '土豆和腌肉炖在一起，汤是浑的。', 'food' => true, 'take' => true],
        'food_noodle_bowl'=> ['cat' => 'food_ready', 'n' => '热汤面', 'd' => '面是挂面，汤里放了猪油和葱花。', 'food' => true, 'take' => true],
        'food_fried_rice'=> ['cat' => 'food_ready', 'n' => '蛋炒饭', 'd' => '锅气还在。', 'food' => true, 'take' => true],
        'food_pancake'    => ['cat' => 'food_ready', 'n' => '烙饼', 'd' => '两张，叠着放。', 'food' => true, 'take' => true],
        'food_dumpling'   => ['cat' => 'food_ready', 'n' => '饺子 一盘', 'd' => '煮好的，蘸醋吃。', 'food' => true, 'take' => true],
        'food_fish_rice'  => ['cat' => 'food_ready', 'n' => '咸鱼饭', 'd' => '咸鱼切碎压在饭上，顶饱。', 'food' => true, 'take' => true],
        'food_burnt'      => ['cat' => 'food_ready', 'n' => '糊了的东西', 'd' => '边缘发黑，闻着有点苦。还能吃，别挑。', 'food' => true, 'take' => true],

        /* ══════════ 饮品 ══════════ */
        'drink_boiled'    => ['cat' => 'drink_hot', 'n' => '开水 一壶', 'd' => '暖瓶里倒出来的，还冒气。', 'drink' => true, 'take' => true],
        'drink_tea_brick' => ['cat' => 'drink_hot', 'n' => '砖茶', 'd' => '掰一块能熬一锅，解腻。', 'drink' => true, 'ingredient' => true, 'take' => true],
        'drink_milk_powd' => ['cat' => 'drink_hot', 'n' => '奶粉 一袋', 'd' => '热水一冲就能喝。', 'drink' => true, 'ingredient' => true, 'take' => true],
        'drink_soda'      => ['cat' => 'drink_cold', 'n' => '汽水 两瓶', 'd' => '玻璃瓶，铁盖。', 'drink' => true, 'take' => true],
        'drink_beer'      => ['cat' => 'drink_alcohol', 'n' => '啤酒 四听', 'd' => '镇上买的，不冰。', 'drink' => true, 'take' => true],
        'drink_baijiu'    => ['cat' => 'drink_alcohol', 'n' => '白酒 一瓶', 'd' => '高度的。夜班暖手用，也能换东西。', 'drink' => true, 'take' => true],

        /* ══════════ 烟与火具 ══════════ */
        'smoke_tobacco'   => ['cat' => 'smoke_cig', 'n' => '旱烟 一撮', 'd' => '报纸包着，卷起来抽。', 'take' => true],
        'smoke_pipe'      => ['cat' => 'smoke_other', 'n' => '烟斗', 'd' => '木头的，咬嘴磨亮了。', 'take' => true],
        'smoke_matches'   => ['cat' => 'smoke_fire', 'n' => '火柴 一盒', 'd' => '磷头受潮过，得蹭两下。', 'take' => true],
        'smoke_stove'     => ['cat' => 'smoke_fire', 'n' => '煤油炉', 'd' => '罐体有点瘪，能烧。做饭要点火就靠它。', 'take' => true],

        /* ══════════ 厨具（tool_kitchen）——烹饪系统的关键 ✓ ══════════ */
        'tool_wok'        => ['cat' => 'tool_kitchen', 'n' => '铁锅', 'd' => '黑底，锅沿有一处豁口。', 'take' => true],
        'tool_cleaver'    => ['cat' => 'tool_kitchen', 'n' => '菜刀', 'd' => '刀背厚，切肉切菜都行。', 'take' => true],
        'tool_board'      => ['cat' => 'tool_kitchen', 'n' => '案板', 'd' => '柳木的，中间凹下去一块。', 'take' => true],
        'tool_bucket'     => ['cat' => 'tool_life', 'n' => '水桶', 'd' => '铁皮桶，提手缠了布。', 'take' => true],

        /* ══════════ 医疗 ══════════ */
        'med_painkiller'  => ['cat' => 'med_drug', 'n' => '止痛片', 'd' => '一板八粒，说明书写着"必要时服用"。', 'take' => true, 'use' => 'hp'],
        'med_antibiotic'  => ['cat' => 'med_drug', 'n' => '抗生素', 'd' => '小瓶装，需冷藏——现在只能常温放。', 'take' => true, 'use' => 'hp'],
        'med_iodine'      => ['cat' => 'med_firstaid', 'n' => '碘伏', 'd' => '棕色小瓶，棉签另装。', 'take' => true, 'use' => 'hp'],
        'med_glucose'     => ['cat' => 'med_drug', 'n' => '葡萄糖粉', 'd' => '冲水喝，低血糖的时候顶用。', 'take' => true],
        'med_sleep'       => ['cat' => 'med_psych', 'n' => '安眠药', 'd' => '七片。医生给失眠的人开的，有人攒着。', 'take' => true, 'use' => 'san'],
        'med_earplug'     => ['cat' => 'med_inst', 'n' => '耳塞', 'd' => '海绵的，一对。夜里隔音用。', 'take' => true],

        /* ══════════ 工具（各类都有用 ✓）══════════ */
        'tool_crowbar'    => ['cat' => 'tool_break', 'n' => '撬棍', 'd' => '一米长，一头是扁嘴。开箱撬门都靠它。', 'take' => true],
        'tool_wrench'     => ['cat' => 'tool_repair', 'n' => '活动扳手', 'd' => '最大开口三十二，修管子刚好。', 'take' => true],
        'tool_multimeter' => ['cat' => 'tool_survey', 'n' => '万用表', 'd' => '表笔有点裂，读数还准。查线路用。', 'take' => true],
        'tool_gas_mask'   => ['cat' => 'gear_face', 'n' => '防毒面具', 'd' => '猪嘴式，滤罐一盒。进有味的房间前戴上。', 'take' => true, 'worn' => true],
        'tool_hose'       => ['cat' => 'tool_repair', 'n' => '软管 五米', 'd' => '胶皮管，两端带卡箍。抽水／排气都行。', 'take' => true],
        'tool_cable'      => ['cat' => 'tool_power', 'n' => '电缆 十米', 'd' => '铜芯，外皮磨掉一段——还能用。', 'take' => true],
        'tool_candle_tall'=> ['cat' => 'tool_light', 'n' => '长蜡烛', 'd' => '四支，烧一夜没问题。', 'take' => true],
        'tool_axe'        => ['cat' => 'tool_break', 'n' => '斧子', 'd' => '柴斧，刃口卷了一点。', 'take' => true],

        /* ══════════ 穿戴防护 ══════════ */
        'gear_gloves'     => ['cat' => 'gear_hand', 'n' => '劳保手套', 'd' => '线加胶，抓东西不打滑。', 'take' => true, 'worn' => true],
        'gear_boots'      => ['cat' => 'gear_foot', 'n' => '胶靴', 'd' => '高筒的，进水也不渗。', 'take' => true, 'worn' => true],
        'gear_helmet'     => ['cat' => 'gear_head', 'n' => '安全帽', 'd' => '黄的，帽衬有点松。', 'take' => true, 'worn' => true],
        'gear_apron'      => ['cat' => 'gear_body', 'n' => '皮围裙', 'd' => '做饭和动刀的时候挡一下。', 'take' => true, 'worn' => true],

        /* ══════════ 武器与弹药 ══════════ */
        'weap_machete'    => ['cat' => 'weap_melee', 'n' => '砍刀', 'd' => '开山用的，刃口有豁。', 'take' => true],
        'weap_sling'      => ['cat' => 'weap_throw', 'n' => '弹弓', 'd' => '铁弓架，皮筋是新的。打石子。', 'take' => true],
        'ammo_9mm'        => ['cat' => 'ammo_pistol', 'n' => '9mm 弹 一盒', 'd' => '二十发，纸盒。领用要登记。', 'take' => true],
        'ammo_shotgun'    => ['cat' => 'ammo_shot', 'n' => '霰弹 六发', 'd' => '壳是红的，散装着。', 'take' => true],
        'ammo_flare'      => ['cat' => 'ammo_exp', 'n' => '信号弹 两支', 'd' => '拉环式的，夜里能照亮半条沟。', 'take' => true],

        /* ══════════ 车辆配件（车旅用 ✓）══════════ */
        'veh_tire'        => ['cat' => 'veh_parts', 'n' => '备胎', 'd' => '皮面还行，气是足的。', 'take' => true],
        'veh_oil'         => ['cat' => 'veh_parts', 'n' => '机油 一桶', 'd' => '四升装。跑长途前加。', 'take' => true],
        'veh_coolant'     => ['cat' => 'veh_parts', 'n' => '防冻液', 'd' => '两升。冬天不加会开锅。', 'take' => true],
        'veh_jack'        => ['cat' => 'veh_tool', 'n' => '千斤顶', 'd' => '机械式的，摇把有点弯。', 'take' => true],
        'veh_fuel_can'    => ['cat' => 'veh_fuel', 'n' => '汽油桶 20L', 'd' => '铁桶，封口垫圈还紧。', 'take' => true],

        /* ══════════ 资料情报（能当线索载体 ✓）══════════ */
        'doc_map_old'     => ['cat' => 'doc_map', 'n' => '旧地图', 'd' => '折痕深，背面有铅笔画过的线。', 'take' => true],
        'doc_ledger'      => ['cat' => 'doc_file', 'n' => '账本', 'd' => '前几页记的是煤，后面换了笔迹。', 'take' => true],
        'doc_letter'      => ['cat' => 'doc_file', 'n' => '信', 'd' => '信封没封口，信纸只有半张。', 'take' => true],
        'doc_film'        => ['cat' => 'doc_photo', 'n' => '胶卷', 'd' => '拍过的，没冲。', 'take' => true],
        'doc_photo_old'   => ['cat' => 'doc_photo', 'n' => '旧合影', 'd' => '黑白照，边角卷了。**队里少一个人**。', 'take' => true],

        /* ══════════ 关键物 · 收容/镇物相关（接收容流程 ✓）══════════ */
        'key_leadbox'     => ['cat' => 'key_sigil', 'n' => '铅衬匣', 'd' => '内壁衬铅的手提匣，锁扣是一次性的。', 'take' => true],
        'key_seal_chain'  => ['cat' => 'key_sigil', 'n' => '封印链', 'd' => '八节，每节有编号。少一节就废。', 'take' => true],
        'key_talisman'    => ['cat' => 'key_sigil', 'n' => '符纸', 'd' => '黄纸朱砂，字是反着写的。', 'take' => true],
        'key_ring_old'    => ['cat' => 'key_door', 'n' => '旧钥匙环', 'd' => '四把钥匙，只剩一把还能对上门。', 'take' => true],
        'key_meter_patch' => ['cat' => 'tool_survey', 'n' => '场强贴片', 'd' => '贴上去就会变色的纸片。比仪器糙，但便宜。', 'take' => true],

        /* ══════════ 杂项 · 待鉴定（**拿去鉴定的东西** ✓）══════════ */
        'misc_rubbing'    => ['cat' => 'misc_unknown', 'n' => '拓片', 'd' => '墨色还新，看不出拓的是什么。', 'take' => true,
                              'unknown' => true, 'appr' => 'occult', 'id_note' => '拓片：拓的是一处**门框上的刻痕**，不是文字；刻痕的深浅按"某种规律"重复。', 'id_flag' => '线索_拓片刻痕'],
        'misc_coin_old'   => ['cat' => 'misc_unknown', 'n' => '旧铜钱', 'd' => '边磨得很圆，像是被人长期捏着。', 'take' => true,
                              'unknown' => true, 'appr' => 'occult', 'id_note' => '旧铜钱：不是钱。背面被人从中间钻空，穿上过绳——**是个挂件，不是通货**。', 'id_flag' => ''],
        'misc_bone'       => ['cat' => 'misc_unknown', 'n' => '骨头', 'd' => '一截，像是什么的指节。', 'take' => true,
                              'unknown' => true, 'appr' => 'medical', 'id_note' => '骨头：人还是兽？切口是**工具切的**，不是咬的。', 'id_flag' => '线索_骨头切口'],
        'misc_mirror'     => ['cat' => 'misc_unknown', 'n' => '小镜子', 'd' => '手掌大，背面是塑料壳。', 'take' => true,
                              'unknown' => true, 'appr' => 'science', 'id_note' => '小镜子：镜面有一层极薄的涂层，**对紫外线反射异常**——不是普通镜子。', 'id_flag' => '线索_镜子涂层'],
        'misc_trade_brush'=> ['cat' => 'misc_trade', 'n' => '毛刷 一批', 'd' => '镇上收来的，能换钱。', 'take' => true],
        'misc_souv_badge' => ['cat' => 'misc_souvenir', 'n' => '旧徽章', 'd' => '背面别针断了。', 'take' => true],
    ];
}

/* ══════════ 吃喝/用药的**实际效果**（追加进同一张表 ✓ 一份真相）══════════ */
/** 吃喝/用药的效果扩充（由 crrg_vitals_item_fx() 合并 ✓） */
function crrg_vitals_item_fx_extra() {
    return [
        'food_can_meat'  => ['food' => 20, 'why' => '午餐肉罐头'],
        'food_potato'    => ['food' => 14, 'why' => '生土豆（垫肚子）'],
        'food_cabbage'   => ['food' => 10, 'water' => 6, 'why' => '白菜'],
        'food_bun'       => ['food' => 26, 'water' => 4, 'why' => '馒头'],
        'food_stew'      => ['food' => 42, 'water' => 14, 'energy' => 6, 'why' => '炖菜（热的）'],
        'food_noodle_bowl' => ['food' => 38, 'water' => 16, 'energy' => 5, 'why' => '热汤面'],
        'food_fried_rice'  => ['food' => 40, 'energy' => 8, 'why' => '蛋炒饭'],
        'food_pancake'   => ['food' => 28, 'energy' => 4, 'why' => '烙饼'],
        'food_dumpling'  => ['food' => 36, 'water' => 6, 'energy' => 6, 'why' => '饺子'],
        'food_fish_rice' => ['food' => 34, 'water' => -4, 'why' => '咸鱼饭（咸）'],
        'food_burnt'     => ['food' => 12, 'san' => -1, 'why' => '糊了的东西（能吃）'],
        'drink_boiled'   => ['water' => 32, 'why' => '开水'],
        'drink_tea_brick'=> ['water' => 26, 'energy' => 8, 'why' => '砖茶'],
        'drink_milk_powd'=> ['water' => 24, 'food' => 8, 'why' => '冲开的奶粉'],
        'drink_soda'     => ['water' => 20, 'energy' => 3, 'why' => '汽水'],
        'drink_beer'     => ['water' => 12, 'san' => 1, 'energy' => -2, 'why' => '啤酒'],
        'drink_baijiu'   => ['water' => 6, 'san' => 2, 'energy' => 6, 'why' => '白酒（暖，也糊脑子）'],
        'med_painkiller' => ['hp' => 4, 'why' => '止痛片'],
        'med_antibiotic' => ['hp' => 6, 'why' => '抗生素'],
        'med_iodine'     => ['hp' => 2, 'why' => '碘伏'],
        'med_glucose'    => ['energy' => 18, 'water' => 6, 'why' => '葡萄糖'],
        'med_sleep'      => ['san' => 3, 'energy' => -6, 'why' => '安眠药（睡得着，醒得慢）'],
    ];
}
