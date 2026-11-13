<?php
/**
 * CRRG 演习系统 · 内容扩充 · 物品 第二批（2026-10-14 下午）
 * ------------------------------------------------------------
 * 委员长："再加，还是不够，物品太少。"
 * 口径与前一批相同：**每件都有作用**（能吃能喝能治病、能做饭、能修车、能收容、能换钱、能当线索）；
 * 写法照规矩：`cat` 必须是小类 id ✓；食品标 `food|ingredient` ✓；饮品标 `drink` ✓；
 * 身体效果追加进同一张表（`crrg_vitals_item_fx_extra2` ✓）；**只追加，不往中段插** ✗（今天栽过）。
 */
if (!defined('ABSPATH')) exit;

/** 第二批物品 */
function crrg_game_items_data_extra2() {
    return [

        /* ═══════ 食材 · 粮油 ═══════ */
        'food_vermicelli' => ['cat' => 'food_grain', 'n' => '粉条 一把', 'd' => '红薯粉，泡软了炖菜好吃。', 'ingredient' => true, 'take' => true],
        'food_tofu'       => ['cat' => 'food_grain', 'n' => '豆腐 两块', 'd' => '压得实，用布包着。', 'ingredient' => true, 'take' => true],
        'food_bean_sprout'=> ['cat' => 'food_grain', 'n' => '豆芽 一袋', 'd' => '自己发的，根须还长。', 'ingredient' => true, 'take' => true],
        'food_wood_ear'   => ['cat' => 'food_grain', 'n' => '木耳 一小包', 'd' => '干货，泡开能涨三倍。', 'ingredient' => true, 'take' => true],
        'food_lily_flower'=> ['cat' => 'food_grain', 'n' => '黄花菜', 'd' => '扎成小把，得泡透了才敢下锅。', 'ingredient' => true, 'take' => true],
        'food_peanut'     => ['cat' => 'food_grain', 'n' => '花生米 一袋', 'd' => '生的，可以炒也可以炸。', 'ingredient' => true, 'take' => true],

        /* ═══════ 调味 ═══════ */
        'food_pepper_corn'    => ['cat' => 'food_cond', 'n' => '花椒', 'd' => '小布袋装，捏一下满手是味。', 'ingredient' => true, 'take' => true],
        'food_star_anise'     => ['cat' => 'food_cond', 'n' => '八角', 'd' => '几颗，炖肉用。', 'ingredient' => true, 'take' => true],
        'food_cinnamon'       => ['cat' => 'food_cond', 'n' => '桂皮', 'd' => '一小段，卷的。', 'ingredient' => true, 'take' => true],
        'food_bean_paste'     => ['cat' => 'food_cond', 'n' => '豆瓣酱', 'd' => '陶罐装，表面一层油。', 'ingredient' => true, 'take' => true],
        'food_oyster_sauce'   => ['cat' => 'food_cond', 'n' => '蚝油', 'd' => '瓶口糊了一圈。', 'ingredient' => true, 'take' => true],
        'food_cooking_wine'   => ['cat' => 'food_cond', 'n' => '料酒', 'd' => '去腥用的，别当酒喝。', 'ingredient' => true, 'take' => true],
        'food_honey'          => ['cat' => 'food_cond', 'n' => '蜂蜜 一罐', 'd' => '罐底有点结晶。', 'ingredient' => true, 'take' => true],
        'food_brown_sugar'    => ['cat' => 'food_cond', 'n' => '红糖', 'd' => '结块的，掰开还是甜的。', 'ingredient' => true, 'take' => true],

        /* ═══════ 生鲜 · 肉蛋 ═══════ */
        'food_sausage'    => ['cat' => 'food_fresh', 'n' => '香肠 几根', 'd' => '风干的，切片能蒸。', 'ingredient' => true, 'take' => true],
        'food_liver'      => ['cat' => 'food_fresh', 'n' => '猪肝 一块', 'd' => '用报纸包着，还凉。', 'ingredient' => true, 'take' => true],
        'food_chicken'    => ['cat' => 'food_fresh', 'n' => '整鸡 一只', 'd' => '退了毛，冻得硬。', 'ingredient' => true, 'take' => true],
        'food_milk_fresh' => ['cat' => 'food_fresh', 'n' => '鲜奶 一桶', 'd' => '镇上捎来的，得当天喝。', 'food' => true, 'ingredient' => true, 'take' => true],
        'food_yam'        => ['cat' => 'food_fresh', 'n' => '山药 一截', 'd' => '带泥，切的时候手会痒。', 'ingredient' => true, 'take' => true],
        'food_radish'     => ['cat' => 'food_fresh', 'n' => '萝卜 几个', 'd' => '青皮的，能腌能炖。', 'ingredient' => true, 'take' => true],

        /* ═══════ 熟食成品（多为烹饪产物 ✓）═══════ */
        'food_baozi'      => ['cat' => 'food_ready', 'n' => '包子', 'd' => '肉馅的，笼屉里刚拿出来。', 'food' => true, 'take' => true],
        'food_huajuan'    => ['cat' => 'food_ready', 'n' => '花卷', 'd' => '拧成花，冷的也好吃。', 'food' => true, 'take' => true],
        'food_noodle_cold'=> ['cat' => 'food_ready', 'n' => '凉面', 'd' => '过过水，拌了酱油。', 'food' => true, 'take' => true],
        'food_stir_noodle'=> ['cat' => 'food_ready', 'n' => '炒面', 'd' => '油大，扛饿。', 'food' => true, 'take' => true],
        'food_braised_egg'=> ['cat' => 'food_ready', 'n' => '卤蛋 三枚', 'd' => '泡在酱色汤里。', 'food' => true, 'take' => true],
        'food_roast_chick'=> ['cat' => 'food_ready', 'n' => '烧鸡 半只', 'd' => '油纸包着，是镇上买的。', 'food' => true, 'take' => true],
        'food_curry_beef' => ['cat' => 'food_ready', 'n' => '干切牛肉', 'd' => '切成薄片，用盐和花椒压过。', 'food' => true, 'take' => true],
        'food_mo'         => ['cat' => 'food_ready', 'n' => '馍 两个', 'd' => '实心的，掰开泡汤正好。', 'food' => true, 'take' => true],
        'food_nang'       => ['cat' => 'food_ready', 'n' => '馕', 'd' => '面上压着花纹，放三天不坏。', 'food' => true, 'take' => true],
        'food_congee'     => ['cat' => 'food_ready', 'n' => '白粥 一缸', 'd' => '熬得稠，胃不舒服的时候喝。', 'food' => true, 'take' => true],

        /* ═══════ 零食 ═══════ */
        'food_biscuit'    => ['cat' => 'food_snack', 'n' => '饼干 一包', 'd' => '素饼，压得很实。', 'food' => true, 'take' => true],
        'food_cake_egg'   => ['cat' => 'food_snack', 'n' => '鸡蛋糕', 'd' => '用纸印的，甜得直接。', 'food' => true, 'take' => true],
        'food_melon_seed' => ['cat' => 'food_snack', 'n' => '瓜子 一袋', 'd' => '五香的，值班嗑。', 'food' => true, 'take' => true],
        'food_candy_hard' => ['cat' => 'food_snack', 'n' => '水果糖 几颗', 'd' => '玻璃纸包着，粘牙。', 'food' => true, 'take' => true],
        'food_instant_rich'=> ['cat' => 'food_snack', 'n' => '锅巴', 'd' => '袋装，嚼起来响。', 'food' => true, 'take' => true],

        /* ═══════ 饮品 ═══════ */
        'drink_soymilk'    => ['cat' => 'drink_hot', 'n' => '豆浆 一壶', 'd' => '刚磨的，还要放糖。', 'drink' => true, 'take' => true],
        'drink_chrysanthemum'=> ['cat' => 'drink_hot', 'n' => '菊花茶', 'd' => '几朵干花，泡开在杯底转。', 'drink' => true, 'take' => true],
        'drink_malt'       => ['cat' => 'drink_hot', 'n' => '麦乳精 一罐', 'd' => '铁罐，勺子舀出来冲。', 'drink' => true, 'ingredient' => true, 'take' => true],
        'drink_coffee_ins' => ['cat' => 'drink_hot', 'n' => '速溶咖啡 几袋', 'd' => '苦，提神。夜班用它顶。', 'drink' => true, 'take' => true],
        'drink_yogurt'     => ['cat' => 'drink_cold', 'n' => '酸奶 两瓶', 'd' => '玻璃瓶，盖纸扎着。', 'drink' => true, 'take' => true],
        'drink_salt_soda'  => ['cat' => 'drink_cold', 'n' => '盐汽水', 'd' => '出汗后喝这个。', 'drink' => true, 'take' => true],
        'drink_rice_wine'  => ['cat' => 'drink_alcohol', 'n' => '黄酒 一瓶', 'd' => '温一下好喝，也能做菜。', 'drink' => true, 'ingredient' => true, 'take' => true],
        'drink_erguotou'   => ['cat' => 'drink_alcohol', 'n' => '二锅头 一瓶', 'd' => '便宜，冲。夜里有它话就多。', 'drink' => true, 'take' => true],

        /* ═══════ 烟与嗜好 ═══════ */
        'smoke_pack'      => ['cat' => 'smoke_cig', 'n' => '香烟 一包', 'd' => '软盒，压扁了。', 'take' => true],
        'smoke_cigar'     => ['cat' => 'smoke_other', 'n' => '雪茄', 'd' => '一支，包着玻璃纸。谁放的不知道。', 'take' => true],
        'smoke_snuff'     => ['cat' => 'smoke_other', 'n' => '鼻烟 一小盒', 'd' => '锡盒，拧开有股凉味。', 'take' => true],
        'smoke_betel'     => ['cat' => 'smoke_other', 'n' => '槟榔', 'd' => '一小包，镇上有人爱嚼。', 'take' => true],
        'smoke_charcoal'  => ['cat' => 'smoke_fire', 'n' => '木炭 一袋', 'd' => '生的火最稳，就是慢。', 'take' => true],

        /* ═══════ 医疗 ═══════ */
        'med_bandaid'     => ['cat' => 'med_firstaid', 'n' => '创可贴 一片', 'd' => '纸包装，边上有点潮。', 'take' => true, 'use' => 'hp'],
        'med_alcohol_wab' => ['cat' => 'med_firstaid', 'n' => '酒精棉片', 'd' => '几片，撕开就能用。', 'take' => true, 'use' => 'hp'],
        'med_yunnan'      => ['cat' => 'med_drug', 'n' => '云南白药', 'd' => '小瓶，粉末。止血。', 'take' => true, 'use' => 'hp'],
        'med_cold'        => ['cat' => 'med_drug', 'n' => '感冒药 一板', 'd' => '说明书字很小。', 'take' => true, 'use' => 'hp'],
        'med_fever'       => ['cat' => 'med_drug', 'n' => '退烧药', 'd' => '两粒装，铝箔。', 'take' => true, 'use' => 'hp'],
        'med_stomach'     => ['cat' => 'med_drug', 'n' => '胃药', 'd' => '嚼着吃的那种，味道发苦。', 'take' => true, 'use' => 'hp'],
        'med_huoxiang'    => ['cat' => 'med_drug', 'n' => '藿香正气水', 'd' => '玻璃小瓶，一口闷——辣。中暑／受寒都用。', 'take' => true, 'use' => 'hp'],
        'med_fengyou'     => ['cat' => 'med_drug', 'n' => '风油精', 'd' => '绿瓶，抹太阳穴。', 'take' => true],
        'med_qingliang'   => ['cat' => 'med_drug', 'n' => '清凉油', 'd' => '铁盒，指甲抠出一点。', 'take' => true],
        'med_saline'      => ['cat' => 'med_inst', 'n' => '生理盐水 一瓶', 'd' => '冲洗伤口用。', 'take' => true],
        'med_tourniquet'  => ['cat' => 'med_inst', 'n' => '止血带', 'd' => '橡皮的，卷成一卷。', 'take' => true],
        'med_splint'      => ['cat' => 'med_inst', 'n' => '夹板 一副', 'd' => '铝条加绑带，骨折临时固定。', 'take' => true],
        'med_burn_cream'  => ['cat' => 'med_drug', 'n' => '烫伤膏', 'd' => '管装，挤出来是白的。', 'take' => true, 'use' => 'hp'],
        'med_glucose_iv'  => ['cat' => 'med_inst', 'n' => '葡萄糖注射液', 'd' => '玻璃安瓶，得会打才用得上。', 'take' => true],

        /* ═══════ 工具 ═══════ */
        'tool_screwdriver' => ['cat' => 'tool_repair', 'n' => '螺丝刀 一套', 'd' => '四支，盒子里少了一支。', 'take' => true],
        'tool_pliers'      => ['cat' => 'tool_repair', 'n' => '钳子', 'd' => '尖嘴的，柄上缠胶布。', 'take' => true],
        'tool_saw'         => ['cat' => 'tool_break', 'n' => '手锯', 'd' => '锯条有点锈，还能锯木板。', 'take' => true],
        'tool_hammer'      => ['cat' => 'tool_repair', 'n' => '锤子', 'd' => '羊角锤，锤头有磕痕。', 'take' => true],
        'tool_hacksaw'     => ['cat' => 'tool_break', 'n' => '钢锯', 'd' => '锯铁用的，配两根备用锯条。', 'take' => true],
        'tool_drill'       => ['cat' => 'tool_repair', 'n' => '电钻', 'd' => '线很长，钻头配了三种。', 'take' => true],
        'tool_grinder'     => ['cat' => 'tool_repair', 'n' => '角磨机', 'd' => '动静大，切钢管用。', 'take' => true],
        'tool_ziptie'      => ['cat' => 'tool_repair', 'n' => '扎带 一把', 'd' => '一次性的，捆东西最快。', 'take' => true],
        'tool_nylon_rope'  => ['cat' => 'tool_rope', 'n' => '尼龙绳 二十米', 'd' => '细的，承重比麻绳强。', 'take' => true],
        'tool_pulley'      => ['cat' => 'tool_rope', 'n' => '滑轮', 'd' => '单轮带钩，吊东西省力。', 'take' => true],
        'tool_headlamp'    => ['cat' => 'tool_light', 'n' => '头灯', 'd' => '松紧带勒额头，腾出手。', 'take' => true],
        'tool_glowstick'   => ['cat' => 'tool_light', 'n' => '荧光棒 两支', 'd' => '掰一下就亮，能撑一夜。', 'take' => true],
        'tool_rangefinder' => ['cat' => 'tool_survey', 'n' => '测距仪', 'd' => '激光的，读数比卷尺准。', 'take' => true],
        'tool_thermo_hum'  => ['cat' => 'tool_survey', 'n' => '温湿度计', 'd' => '表盘式的，挂在墙上那种。', 'take' => true],
        'tool_sound_meter' => ['cat' => 'tool_survey', 'n' => '声级计', 'd' => '能记最大值的便携款。', 'take' => true],
        'tool_compass'     => ['cat' => 'tool_survey', 'n' => '罗盘', 'd' => '军用款，指针有点晃。', 'take' => true],
        'tool_whistle'     => ['cat' => 'tool_life', 'n' => '哨子', 'd' => '金属的，一吹半座楼都听见。', 'take' => true],
        'tool_searchlight' => ['cat' => 'tool_light', 'n' => '探照灯', 'd' => '手提式，能吃电池。', 'take' => true],
        'tool_blowtorch'   => ['cat' => 'tool_repair', 'n' => '汽油喷灯', 'd' => '点着就是一束蓝火，烤冻管用。', 'take' => true],
        'tool_ladder'      => ['cat' => 'tool_repair', 'n' => '梯子 一把', 'd' => '铝合金，四折。', 'take' => true],

        /* ═══════ 穿戴 ═══════ */
        'gear_goggles'     => ['cat' => 'gear_face', 'n' => '护目镜', 'd' => '防溅的，镜片有划痕。', 'take' => true, 'worn' => true],
        'gear_earmuff'     => ['cat' => 'gear_head', 'n' => '隔音耳罩', 'd' => '戴上去世界立刻远了。', 'take' => true, 'worn' => true],
        'gear_cut_gloves'  => ['cat' => 'gear_hand', 'n' => '防割手套', 'd' => '钢丝内衬，摸刀不慌。', 'take' => true, 'worn' => true],
        'gear_coat'        => ['cat' => 'gear_body', 'n' => '棉大衣', 'd' => '厚，沉，夜里靠它。', 'take' => true, 'worn' => true],
        'gear_knee'        => ['cat' => 'gear_body', 'n' => '护膝', 'd' => '爬管廊的时候用得上。', 'take' => true, 'worn' => true],
        'gear_toolbelt'    => ['cat' => 'gear_carry', 'n' => '工具腰带', 'd' => '挂钳子挂手电，走起来叮当。', 'take' => true, 'worn' => true],
        'gear_vest_ref'    => ['cat' => 'gear_body', 'n' => '反光背心', 'd' => '夜里站在路边，车灯一打就看见。', 'take' => true, 'worn' => true],
        'gear_frost_mask'  => ['cat' => 'gear_face', 'n' => '防寒面罩', 'd' => '只露眼睛，呼吸在上面结霜。', 'take' => true, 'worn' => true],
        'gear_anti_static'=> ['cat' => 'gear_suit', 'n' => '防静电服', 'd' => '进有易燃气的房间前换。', 'take' => true, 'worn' => true],

        /* ═══════ 武器与弹药 ═══════ */
        'weap_shotgun'     => ['cat' => 'weap_fire', 'n' => '霰弹枪', 'd' => '登记在册，领用要签字。', 'take' => true],
        'weap_rifle'       => ['cat' => 'weap_fire', 'n' => '步枪', 'd' => '枪身有编号，膛线还好。', 'take' => true],
        'weap_crossbow'    => ['cat' => 'weap_heavy', 'n' => '弩', 'd' => '拉起来费力，安静。', 'take' => true],
        'weap_spray'       => ['cat' => 'weap_throw', 'n' => '辣椒喷雾', 'd' => '小罐，喷一脸能换十几秒。', 'take' => true, 'combat' => 'stun'],
        'weap_taser'       => ['cat' => 'weap_throw', 'n' => '电击器', 'd' => '要贴着用，声音很响。', 'take' => true, 'combat' => 'stun'],
        'ammo_rifle_556'   => ['cat' => 'ammo_rifle', 'n' => '步枪弹 十发', 'd' => '桥夹装着。', 'take' => true],
        'ammo_flare_gun'   => ['cat' => 'ammo_exp', 'n' => '照明弹 一发', 'd' => '打上天，亮半分钟。', 'take' => true],
        'ammo_smoke'       => ['cat' => 'ammo_exp', 'n' => '烟雾弹 一枚', 'd' => '拉环式，掩护撤退用。', 'take' => true],
        'ammo_training'    => ['cat' => 'ammo_special', 'n' => '训练弹 一盒', 'd' => '橡胶头，打人不致命。', 'take' => true],

        /* ═══════ 车辆配件 ═══════ */
        'veh_battery'      => ['cat' => 'veh_parts', 'n' => '蓄电池', 'd' => '沉，提手是绳的。亏电就得靠它。', 'take' => true],
        'veh_spark_plug'   => ['cat' => 'veh_parts', 'n' => '火花塞 四个', 'd' => '新的，纸盒里还有防潮纸。', 'take' => true],
        'veh_wiper'        => ['cat' => 'veh_parts', 'n' => '雨刷 一副', 'd' => '胶条还软。', 'take' => true],
        'veh_belt'         => ['cat' => 'veh_parts', 'n' => '皮带', 'd' => '备用，断了这根车就不走。', 'take' => true],
        'veh_filter'       => ['cat' => 'veh_parts', 'n' => '滤芯', 'd' => '机油滤和空滤各一个。', 'take' => true],
        'veh_tire_patch'   => ['cat' => 'veh_tool', 'n' => '补胎胶条', 'd' => '几根胶条加一支锥子。', 'take' => true],
        'veh_tow_rope'     => ['cat' => 'veh_tool', 'n' => '拖车绳', 'd' => '五米，两头带钩。', 'take' => true],
        'veh_chain'        => ['cat' => 'veh_tool', 'n' => '防滑链', 'd' => '一对，装后轮。', 'take' => true],
        'veh_triangle'     => ['cat' => 'veh_tool', 'n' => '三角牌', 'd' => '反光的，支架有点松。', 'take' => true],
        'veh_brake_fluid'  => ['cat' => 'veh_fuel', 'n' => '制动液 一瓶', 'd' => '别和防冻液弄混。', 'take' => true],

        /* ═══════ 资料情报 ═══════ */
        'doc_duty_log'     => ['cat' => 'doc_file', 'n' => '值班日志', 'd' => '厚本，写满了。翻到哪页都能看出是谁的班。', 'take' => true],
        'doc_patrol_sheet' => ['cat' => 'doc_note', 'n' => '巡检记录本', 'd' => '格子里要打钩，有人打得特别轻。', 'take' => true],
        'doc_repair_slip'  => ['cat' => 'doc_file', 'n' => '维修单 一份', 'd' => '三联式的，最后一联还留着。', 'take' => true],
        'doc_borrow_slip'  => ['cat' => 'doc_file', 'n' => '借阅单', 'd' => '签字栏空着，等着谁的名字。', 'take' => true],
        'doc_clipping'     => ['cat' => 'doc_file', 'n' => '剪报', 'd' => '从县报上剪下来的，边缘不齐。', 'take' => true],
        'doc_pen'          => ['cat' => 'doc_note', 'n' => '钢笔', 'd' => '灌墨水的，笔尖压得很开。', 'take' => true],
        'doc_ink'          => ['cat' => 'doc_note', 'n' => '墨水 一瓶', 'd' => '蓝黑，瓶底有沉淀。', 'take' => true],
        'doc_inkpad'       => ['cat' => 'doc_note', 'n' => '印泥', 'd' => '朱红的，盒盖上有指纹。', 'take' => true],
        'doc_receipt_book' => ['cat' => 'doc_note', 'n' => '收据本', 'd' => '撕了一半，存根还在。', 'take' => true],
        'doc_carbon'       => ['cat' => 'doc_note', 'n' => '复写纸', 'd' => '用过的，还能再压一次。', 'take' => true],
        'doc_calendar'     => ['cat' => 'doc_note', 'n' => '台历', 'd' => '撕到一半的，剩下那半被人折过角。', 'take' => true],
        'doc_glue'         => ['cat' => 'doc_note', 'n' => '胶水', 'd' => '小瓶，瓶口结了一层硬壳。', 'take' => true],

        /* ═══════ 关键物 · 收容 ═══════ */
        'key_lead_plate'   => ['cat' => 'key_sigil', 'n' => '铅板 一块', 'd' => '巴掌大，沉得不正常。垫在下面用。', 'take' => true],
        'key_wax_seal'     => ['cat' => 'key_sigil', 'n' => '封印蜡 一条', 'd' => '暗红，烧化了能压印。', 'take' => true],
        'key_cinnabar'     => ['cat' => 'key_sigil', 'n' => '朱砂 一小罐', 'd' => '粉末，写符用。沾手上洗不掉。', 'take' => true],
        'key_brush_maobi'  => ['cat' => 'key_sigil', 'n' => '毛笔', 'd' => '笔杆裂了，毛还聚得起来。', 'take' => true],
        'key_yellow_paper' => ['cat' => 'key_sigil', 'n' => '黄纸 一叠', 'd' => '裁好的，尺寸一致。', 'take' => true],
        'key_sample_bag'   => ['cat' => 'key_sample', 'n' => '取样袋 几个', 'd' => '自封口，袋上印着编号栏。', 'take' => true],
        'key_specimen_jar' => ['cat' => 'key_sample', 'n' => '标本瓶', 'd' => '玻璃，带磨口塞。', 'take' => true],
        'key_tag_gun'      => ['cat' => 'tool_survey', 'n' => '标签枪', 'd' => '打编号标签的，用起来"啪"一声。', 'take' => true],
        'key_lock_chain'   => ['cat' => 'key_door', 'n' => '链锁', 'd' => '一米长，锁头是新的。', 'take' => true],
        'key_door_plate'   => ['cat' => 'key_door', 'n' => '门牌 一块', 'd' => '拆下来的旧门牌，字被刮过。', 'take' => true],

        /* ═══════ 杂项 · 待鉴定 ═══════ */
        'misc_watch_old'   => ['cat' => 'misc_unknown', 'n' => '旧怀表', 'd' => '表壳磨得发亮，玻璃有裂。', 'take' => true,
                               'unknown' => true, 'appr' => 'science', 'id_note' => '旧怀表：机芯被人改过——**多了一根不属于原厂的轴**，而且不连任何指针。', 'id_flag' => '线索_怀表机芯'],
        'misc_shard'       => ['cat' => 'misc_unknown', 'n' => '陶片', 'd' => '一片，边是旧的。', 'take' => true,
                               'unknown' => true, 'appr' => 'occult', 'id_note' => '陶片：断口是新的，胎里夹着**极细的金属丝**——不是这个年代的工艺。', 'id_flag' => '线索_陶片夹丝'],
        'misc_hair'        => ['cat' => 'misc_unknown', 'n' => '头发 一撮', 'd' => '用纸包着，看着像从梳子上摘的。', 'take' => true,
                               'unknown' => true, 'appr' => 'medical', 'id_note' => '头发：毛囊完整，**不是自然脱落的**，是剪的——而且发色在根部和梢部不一样。', 'id_flag' => '线索_头发发色'],
        'misc_bead'        => ['cat' => 'misc_unknown', 'n' => '玻璃珠', 'd' => '一颗，混在别的东西里。', 'take' => true,
                               'unknown' => true, 'appr' => 'occult', 'id_note' => '玻璃珠：内部有**一个极小的空腔**，晃起来有东西在动——封进去的，不是气泡。', 'id_flag' => '线索_玻璃珠空腔'],
        'misc_wood_tag'    => ['cat' => 'misc_unknown', 'n' => '木牌', 'd' => '拇指大，穿孔挂过绳。', 'take' => true,
                               'unknown' => true, 'appr' => 'occult', 'id_note' => '木牌：刻的是**编号**，刻痕深浅一致——是模子压的，不是手刻的。', 'id_flag' => '线索_木牌编号'],
        'misc_cloth'       => ['cat' => 'misc_unknown', 'n' => '布片', 'd' => '深色，边缘烧过。', 'take' => true,
                               'unknown' => true, 'appr' => 'science', 'id_note' => '布片：纤维混纺，**其中一股是金属的**——织进去的时候就有的。', 'id_flag' => '线索_布片金属丝'],
        'misc_trade_junk'  => ['cat' => 'misc_trade', 'n' => '旧零件 一袋', 'd' => '铜的铝的混在一起，能卖。', 'take' => true],
        'misc_souv_pin'    => ['cat' => 'misc_souvenir', 'n' => '旧像章', 'd' => '背面别针在。', 'take' => true],
    ];
}

/** 第二批吃喝/用药效果 */
function crrg_vitals_item_fx_extra2() {
    return [
        'food_milk_fresh' => ['food' => 12, 'water' => 16, 'why' => '鲜奶'],
        'food_baozi'     => ['food' => 30, 'water' => 4, 'why' => '包子'],
        'food_huajuan'   => ['food' => 26, 'why' => '花卷'],
        'food_noodle_cold'=> ['food' => 32, 'water' => 6, 'why' => '凉面'],
        'food_stir_noodle'=> ['food' => 38, 'energy' => 6, 'why' => '炒面'],
        'food_braised_egg'=> ['food' => 18, 'why' => '卤蛋'],
        'food_roast_chick'=> ['food' => 36, 'energy' => 8, 'why' => '烧鸡'],
        'food_curry_beef' => ['food' => 26, 'energy' => 6, 'why' => '干切牛肉'],
        'food_mo'         => ['food' => 24, 'why' => '馍'],
        'food_nang'       => ['food' => 28, 'why' => '馕'],
        'food_congee'     => ['food' => 20, 'water' => 18, 'san' => 1, 'why' => '白粥（暖胃）'],
        'food_biscuit'    => ['food' => 16, 'why' => '饼干'],
        'food_cake_egg'   => ['food' => 18, 'energy' => 5, 'why' => '鸡蛋糕'],
        'food_melon_seed' => ['food' => 6, 'san' => 1, 'why' => '瓜子（解闷）'],
        'food_candy_hard' => ['food' => 4, 'energy' => 4, 'why' => '水果糖'],
        'food_instant_rich'=> ['food' => 14, 'why' => '锅巴'],
        'drink_soymilk'   => ['water' => 22, 'food' => 8, 'why' => '豆浆'],
        'drink_chrysanthemum'=> ['water' => 20, 'san' => 1, 'why' => '菊花茶'],
        'drink_malt'      => ['water' => 18, 'food' => 10, 'energy' => 6, 'why' => '麦乳精'],
        'drink_coffee_ins'=> ['energy' => 22, 'water' => -4, 'why' => '速溶咖啡'],
        'drink_yogurt'    => ['water' => 16, 'food' => 8, 'why' => '酸奶'],
        'drink_salt_soda' => ['water' => 24, 'energy' => 4, 'why' => '盐汽水'],
        'drink_rice_wine' => ['water' => 8, 'san' => 1, 'energy' => 4, 'why' => '黄酒（温一下）'],
        'drink_erguotou'  => ['water' => 4, 'san' => 2, 'energy' => 8, 'why' => '二锅头（冲）'],
        'smoke_pack'      => ['energy' => 5, 'water' => -4, 'why' => '抽根烟'],
        'smoke_cigar'     => ['energy' => 6, 'san' => 1, 'why' => '雪茄'],
        'med_bandaid'     => ['hp' => 1, 'why' => '创可贴'],
        'med_alcohol_wab' => ['hp' => 1, 'why' => '酒精棉片'],
        'med_yunnan'      => ['hp' => 5, 'why' => '云南白药'],
        'med_cold'        => ['hp' => 3, 'why' => '感冒药'],
        'med_fever'       => ['hp' => 3, 'why' => '退烧药'],
        'med_stomach'     => ['hp' => 2, 'why' => '胃药'],
        'med_huoxiang'    => ['hp' => 3, 'why' => '藿香正气水'],
        'med_burn_cream'  => ['hp' => 3, 'why' => '烫伤膏'],
    ];
}
