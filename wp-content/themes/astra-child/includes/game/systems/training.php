<?php
/**
 * CRRG 演习 · 训练系统（systems/training.php，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："加入训练系统，通过各种方式，让玩家每一个属性和技能都有办法提升
 *          （还是像哑铃那样的两段检定）"
 *
 * **两段检定（照现有哑铃那套 ✓ 一字不改口径）**：
 *   第一段 D100 **须大于**当前值 → 越练越难 ✓
 *   第二段 D2：1 ＝ +1，2 ＝ 练到了但没长进 ✓
 *
 * **全覆盖**：9 个属性 ＋ **46 个技能**，每一项都至少有一条途径（`crrg_train_methods()`）
 *   · 特定途径（对口场地／器械／同伴 ✓ 效率高 ✓）
 *   · **兜底途径**：任何技能都能在训练室／宿舍对照手册自学 ✓（效率减半 ✓）
 *     —— 兜底是"每个技能都有办法"的保证 ✓ 门禁会逐项扫漏洞 ✓
 *
 * 代价：时间（**个人钟** ✓ 不碰世界钟 ✗）＋ 精力／饱食；每完成一次合约另得一次**免费机会**（沿用 `train_tokens` ✓）
 * 防刷：同一项**当天**每多练一次，第一段门槛 +5 ✓（第三次起很难过 ✓）
 */
if (!defined('ABSPATH')) exit;

/** 途径表：目标 => ['场地'=>[…], '需要'=>器械/物品, 'kind'=>'attr|skill', 'why'=>说明, 'gain'=>上限控制] */
function crrg_train_methods() {
    $M = [
        /* ══════ 属性（9 项，每一项都有办法）══════ */
        '力量' => ['kind' => 'attr', 'rooms' => ['b1_train'], 'need' => ['tool_dumbbell', 'tool_treadmill', 'tool_bar'],
                   'why' => '器械或徒手：把杠铃架上的片子数清楚，一组一组来。', 'cap' => 99],
        '体质' => ['kind' => 'attr', 'rooms' => ['b1_train'], 'need' => ['tool_treadmill', 'tool_bar'],
                   'why' => '跑步机／单杠：跑到出汗，肺里发烫。', 'cap' => 99],
        '敏捷' => ['kind' => 'attr', 'rooms' => ['b1_train', 'w_trail', 'w_gate'],
                   'need' => ['tool_treadmill', 'tool_bar', ''],
                   'why' => '折返跑、矮桩、林子里的小径上练脚下。', 'cap' => 99],
        '智力' => ['kind' => 'attr', 'rooms' => ['b1_archive', 'b1_office', 'b1_train'],
                   'need' => ['doc_book', 'doc_map_old', ''],
                   'why' => '读手册、做题、在纸上推演。脑子跟刀一样，越磨越快。', 'cap' => 99],
        '教育' => ['kind' => 'attr', 'rooms' => ['b1_archive', 'b1_office', 'b2b_comm'],
                   'need' => ['doc_book', 'doc_ledger', ''],
                   'why' => '抄档、校表、把一整套流程背下来。', 'cap' => 99],
        '意志' => ['kind' => 'attr', 'rooms' => ['b1_med', 'b2b_duty', 'b1_train'],
                   'need' => ['tool_flask', ''],
                   'why' => '静坐、数呼吸、把心里的东西放回架子上。', 'cap' => 99],
        '外貌' => ['kind' => 'attr', 'rooms' => ['b1_bath', 'tw_barber', 'bs_barber', 'hy_street'],
                   'need' => ['tool_soap', ''],
                   'why' => '理发、修面、把制服熨平——**有限**：最多比原样精神两分 ✓', 'cap_add' => 2],
        '体型' => ['kind' => 'attr', 'rooms' => ['b1_train', 'b1_canteen'],
                   'need' => ['food_ration', ''],
                   'why' => '吃与练配着来——**有限**：体型只挪一分两分 ✓（且很慢）', 'cap_add' => 2],
        '幸运' => ['kind' => 'attr', 'rooms' => ['b2b_hall', 'b1_canteen', 'hy_tree'],
                   'need' => [''],
                   'why' => '**运气不是练出来的**——但你把它做在别人身上：帮个忙、垫个钱，回头它自己找上你。（每次 +1 的机会极低 ✓ 上限很低 ✓）',
                   'cap_add' => 3, 'rare' => 20],

        /* ══════ 技能（对口途径；未列的技能走兜底 ✓）══════ */
        '射击' => ['kind' => 'skill', 'rooms' => ['b1_train'], 'need' => ['', 'weap_pistol'], 'why' => '靶位上一发一发地抠，弹壳捡回来数。'],
        '闪避' => ['kind' => 'skill', 'rooms' => ['b1_train'], 'need' => [''], 'why' => '跟着沙袋左右挪，脚下不许乱。'],
        '格斗' => ['kind' => 'skill', 'rooms' => ['b1_train'], 'need' => ['weap_baton', ''], 'why' => '和沙袋、也和人对练；挨一下记住一下。'],
        '急救' => ['kind' => 'skill', 'rooms' => ['b1_med'], 'need' => ['med_firstaid', 'med_bandage', ''], 'why' => '在假人身上练包扎，白大夫在旁边挑错。'],
        '医学' => ['kind' => 'skill', 'rooms' => ['b1_med'], 'need' => ['doc_book', 'med_antibiotic'], 'why' => '翻医书，把人体的图一条条记住。'],
        '心理分析' => ['kind' => 'skill', 'rooms' => ['b1_med', 'b2b_duty'], 'need' => [''], 'why' => '先分析自己：把这段时间做过的事一件件摆出来。'],
        '图书馆使用' => ['kind' => 'skill', 'rooms' => ['b1_archive', 'gz_lib', 'bs_library'], 'need' => [''], 'why' => '按编号找，按目录找，练到闭着眼也能摸到那一排。'],
        '侦查' => ['kind' => 'skill', 'rooms' => ['b1_train', 'b1_archive', 'w_stand'], 'need' => ['tool_flashlight', ''], 'why' => '找东西：把一枚钉子藏在屋里，让自己找出来。'],
        '聆听' => ['kind' => 'skill', 'rooms' => ['b1_train', 'b2b_duty'], 'need' => [''], 'why' => '闭着眼听：风、电流、脚步，一样样分清。'],
        '潜行' => ['kind' => 'skill', 'rooms' => ['w_trail', 'b1_cor_m'], 'need' => [''], 'why' => '在林子里走，脚先落脚跟再落掌。'],
        '追踪' => ['kind' => 'skill', 'rooms' => ['w_stand', 'w_hollow'], 'need' => [''], 'why' => '跟自己的脚印走，来回几趟，学会看土的软硬。'],
        '攀爬' => ['kind' => 'skill', 'rooms' => ['w_ridge', 'b1_train'], 'need' => ['tool_rope_hemp', ''], 'why' => '上绳、下绳、三点固定。'],
        '驾驶' => ['kind' => 'skill', 'rooms' => ['garage_maint', 'garage_exit'], 'need' => [''], 'why' => '在库区里绕桩，倒库、贴边、看后视镜。'],
        '机械维修' => ['kind' => 'skill', 'rooms' => ['garage_maint', 'b3_power'], 'need' => ['tool_wrench', 'tool_screwdriver'], 'why' => '拆一台废泵再装回去，多出来的螺丝不许有。'],
        '电气维修' => ['kind' => 'skill', 'rooms' => ['b3_power', 'b3_gen'], 'need' => ['tool_multimeter', 'tool_insultape'], 'why' => '对着线路图一条条量，量到心里有数。'],
        '计算机' => ['kind' => 'skill', 'rooms' => ['b2b_tech', 'b2b_comm'], 'need' => [''], 'why' => '敲命令，读日志，把报错一条条记下来。'],
        '会计' => ['kind' => 'skill', 'rooms' => ['b1_office', 'b3_warehouse'], 'need' => ['doc_ledger', ''], 'why' => '盘库、对账，把差出来的那一笔找出来。'],
        '说服' => ['kind' => 'skill', 'rooms' => ['b1_canteen', 'tw_noodle', 'hy_tree'], 'need' => [''], 'why' => '跟人聊天，试着把一句话说得让人愿意听。'],
        '魅惑' => ['kind' => 'skill', 'rooms' => ['b1_canteen', 'tw_main_c'], 'need' => [''], 'why' => '练眼神、练停顿——对着镜子也行。'],
        '话术' => ['kind' => 'skill', 'rooms' => ['b2b_hall', 'tw_coop'], 'need' => [''], 'why' => '把要说的话写成三段，先说结论。'],
        '威吓' => ['kind' => 'skill', 'rooms' => ['b1_train', 'tw_police'], 'need' => [''], 'why' => '站姿、语气、停顿，练到对方先眨眼。'],
        '母语' => ['kind' => 'skill', 'rooms' => ['b1_archive', 'b1_office'], 'need' => [''], 'why' => '写公文、改行文，把话说准。'],
        '历史' => ['kind' => 'skill', 'rooms' => ['b1_archive', 'gz_lib'], 'need' => ['doc_collection_clipping', ''], 'why' => '翻旧报，按年份串起来。'],
        '考古学' => ['kind' => 'skill', 'rooms' => ['b1_archive', 'bs_library'], 'need' => ['misc_shard', ''], 'why' => '看陶片、看断口，练分辨新旧。'],
        '法律' => ['kind' => 'skill', 'rooms' => ['b1_office', 'bs_countyhall'], 'need' => [''], 'why' => '读条文、抄格式，知道哪一条能站住脚。'],
        '外语' => ['kind' => 'skill', 'rooms' => ['b1_archive', 'gz_univ'], 'need' => ['doc_book', ''], 'why' => '背词、听录音，把一段话抄二十遍。'],
        '驾驶飞行器' => ['kind' => 'skill', 'rooms' => ['b1_train'], 'need' => [''], 'why' => '模拟器上练（手册上的图比设备多）。'],
        '生存' => ['kind' => 'skill', 'rooms' => ['w_hollow', 'w_gate'], 'need' => ['smoke_matches', 'tool_flask'], 'why' => '背风、生火、把水滤清。'],
        '伪装' => ['kind' => 'skill', 'rooms' => ['w_trail', 'b1_train'], 'need' => [''], 'why' => '抹泥、遮形、从别人眼皮底下走过。'],
        '爆炸物' => ['kind' => 'skill', 'rooms' => ['b1_train'], 'need' => ['ammo_smoke', ''], 'why' => '只练布置与撤除的次序，不练响。'],
        '撬锁' => ['kind' => 'skill', 'rooms' => ['b1_train'], 'need' => ['tool_pliers', ''], 'why' => '在废锁上找手劲，一下一下听。'],
        '照相' => ['kind' => 'skill', 'rooms' => ['b1_office', 'bs_photo'], 'need' => ['doc_camera', ''], 'why' => '练构图、练对焦，把一卷拍完。'],
        '美术' => ['kind' => 'skill', 'rooms' => ['b1_office', 'b1_train'], 'need' => ['doc_pencil', ''], 'why' => '画现场平面图，画到一眼能看懂。'],
        '表演' => ['kind' => 'skill', 'rooms' => ['b1_train', 'tw_stage'], 'need' => [''], 'why' => '对镜子演一段，要求自己不出戏。'],
        '心理暗示' => ['kind' => 'skill', 'rooms' => ['b1_med', 'b1_train'], 'need' => [''], 'why' => '练语速与停顿，让别人自己走到结论上。'],
        '聆听风声' => ['kind' => 'skill', 'rooms' => ['w_ridge', 'w_stand'], 'need' => [''], 'why' => '在脊线上坐下，把风里每一层声音分开。'],
    ];
    return $M;
}

/** 兜底途径（**任何技能都能自学** ✓ 这是"全覆盖"的保证） */
function crrg_train_fallback($key, $kind) {
    return ['kind' => $kind, 'rooms' => ['b1_train', 'b1_room', 'b1_archive'],
            'need' => [''], 'fallback' => true, 'half' => true,
            'why' => '没有对口的场子，就照着手册自己练——慢一半，但练得动。'];
}

/** 该角色有哪些可练项（属性 ＋ 技能） */
function crrg_train_targets(&$pc) {
    $attrs = function_exists('crrg_coc_attr_names') ? (array) crrg_coc_attr_names() : [];
    $sk = (array) ($pc['skills'] ?? []);
    if (!$sk && function_exists('crrg_coc_skill_base')) {
        $sk = (array) crrg_coc_skill_base((array) ($pc['attrs'] ?? []));
    }
    return ['attr' => $attrs, 'skill' => array_keys($sk)];
}

/** 取某目标的途径（对口优先，没有就兜底 ✓） */
function crrg_train_method($key, $kind = '') {
    $M = crrg_train_methods();
    if (isset($M[$key])) return $M[$key];
    return crrg_train_fallback($key, $kind ?: 'skill');
}

/** 现在能不能练这一项：[bool, 说明] */
function crrg_train_can(&$s, &$pc, $key, $kind = '') {
    $m = crrg_train_method($key, $kind);
    $room = (string) ($s['room'] ?? '');
    if (!in_array($room, (array) $m['rooms'], true)) {
        return [false, sprintf('（练“%s”得去：%s。）', $key, implode('、', array_map(fn($r) => crrg_drill_rooms()[$r]['t'] ?? $r, (array) $m['rooms'])))];
    }
    /* 器械：need 里任一即可（'' 表示徒手也算 ✓） */
    $need = (array) $m['need'];
    /* 2026-10-14 修口径：**设施房间里的器械算公家的** ✓
       （训练室有哑铃和单杠、医务室有急救包、车库有工具、档案室有书 ✓ 不该要求自己带 ✗） */
    $facility = ['b1_train','b1_med','b1_archive','b1_office','garage_maint','b3_power','b3_gen',
                 'b2b_tech','b2b_comm','b1_bath','b1_canteen','b1_room'];
    if (in_array($room, $facility, true)) $need = array_merge([''], $need);
    if ($need && !in_array('', $need, true)) {
        $have = false;
        foreach ($need as $n) if (crrg_drill_own_count($pc, (string) $n) > 0) { $have = true; break; }
        /* 宿舍器械（哑铃/跑步机）也算 ✓ */
        if (!$have && function_exists('crrg_drill_dormfx')) {
            $fx = crrg_drill_dormfx($pc);
            foreach ((array) ($fx['train'] ?? []) as $t) if (mb_strpos((string) $key, (string) $t) !== false || mb_strpos((string) $t, (string) $key) !== false) $have = true;
        }
        if (!$have) return [false, sprintf('（练“%s”要器械：%s，或者去训练室用公家的。）', $key, implode('／', array_map(fn($x) => crrg_drill_item_name($x), $need)))];
    }
    /* 精力：练不动就是练不动 ✓ */
    $energy = (float) ($pc['vitals']['energy'] ?? 100);
    if ($energy < 15) return [false, '（精力见底了——先吃点、睡一觉再练。）'];
    return [true, ''];
}

/** 今天练了几次这一项（防刷 ✓ 个人档按日记 ✓） */
function crrg_train_today(&$pc, $key) {
    $day = function_exists('crrg_cal_day') ? (int) crrg_cal_day() : (int) floor(time() / 86400);
    $t = (array) ($pc['train_log'] ?? []);
    if ((int) ($t['day'] ?? 0) !== $day) return 0;
    return (int) ($t['n'][$key] ?? 0);
}
function crrg_train_mark(&$pc, $key) {
    $day = function_exists('crrg_cal_day') ? (int) crrg_cal_day() : (int) floor(time() / 86400);
    $t = (array) ($pc['train_log'] ?? []);
    if ((int) ($t['day'] ?? 0) !== $day) $t = ['day' => $day, 'n' => []];
    $t['n'][$key] = (int) ($t['n'][$key] ?? 0) + 1;
    $pc['train_log'] = $t;
}

/**
 * **练一次**（两段检定，照哑铃那套 ✓）
 * @return array 给玩家看的行
 */
function crrg_train_do(&$s, &$pc, $key, $kind = '') {
    $targets = crrg_train_targets($pc);
    if ($kind === '') {
        $kind = in_array($key, (array) $targets['attr'], true) ? 'attr' : 'skill';
    }
    if ($kind === 'attr' && !in_array($key, (array) $targets['attr'], true)) return ['（没有这个属性。）'];
    if ($kind === 'skill' && !in_array($key, (array) $targets['skill'], true)) return ['（这张卡上没有这个技能。）'];

    [$ok, $why] = crrg_train_can($s, $pc, $key, $kind);
    if (!$ok) return [$why];

    $m = crrg_train_method($key, $kind);
    $cur = (int) crrg_coc_skill_val($pc, $key);
    $times = crrg_train_today($pc, $key);
    /* 第一段：D100 **须大于**当前值（越练越难 ✓）；当天每多练一次 +5 ✓ */
    $bar = $cur + $times * 5;
    $roll = function_exists('crrg_coc_roll100') ? crrg_coc_roll100() : mt_rand(1, 100);
    $out = [];
    $out[] = sprintf('〔训练·%s〕', $key);
    if (!empty($m['why'])) $out[] = $m['why'];
    $out[] = sprintf('第一段：D100＝%d，须大于 %d 才算练到。', $roll, $bar);
    if ($times > 0) $out[] = sprintf('（今天这是第 %d 次——同一个动作练多了就不长进了。）', $times + 1);

    /* 时间账：个人钟 ✓ 不碰世界钟 ✗ */
    $mins = !empty($m['half']) ? 50 : 30;
    if (function_exists('crrg_vitals_now') && isset($pc['vitals'])) $pc['vitals']['t'] = crrg_vitals_now() - $mins * 60;
    $pc['vitals']['energy'] = max(0, (float) ($pc['vitals']['energy'] ?? 100) - 6);
    $pc['vitals']['food']   = max(0, (float) ($pc['vitals']['food'] ?? 100) - 2);
    crrg_train_mark($pc, $key);

    if ($roll <= $bar) {
        $out[] = '没过。器械白上了，人出了一身汗。';
        $out[] = sprintf('（耗了约 %d 分钟；精力 −6、饱食 −2。）', $mins);
        return array_merge($out, ['（明天再来。练不出来的日子也是练。）']);
    }
    $out[] = '过了。第二段——';
    /* 第二段：D2（1 加、2 不长进 ✓） */
    $d2 = mt_rand(1, 2);
    if (!empty($m['rare']) && mt_rand(1, 100) > (int) $m['rare']) {
        $out[] = 'D2＝2，练到了，但没长进。';
    } elseif ($d2 === 2) {
        $out[] = 'D2＝2，练到了，但没长进。';
    } else {
        /* 上限：属性按 cap_add（外貌/体型/幸运有限 ✓）；技能封顶 99 */
        $capOk = true;
        if ($kind === 'attr' && isset($m['cap_add'])) {
            $base = (int) ($pc['attrs_base'][$key] ?? 0);
            if ($base <= 0) $pc['attrs_base'] = (array) ($pc['attrs_base'] ?? []) + [$key => $cur];
            $base = (int) ($pc['attrs_base'][$key] ?? $cur);
            if ($cur >= $base + (int) $m['cap_add']) $capOk = false;
        }
        if (!$capOk) {
            $out[] = sprintf('D2＝1——可这个已经到头了（%s 只能到 %d）。', $key, (int) ($m['cap_add'] ?? 0) + $cur);
        } else {
            if ($kind === 'attr') { $pc['attrs'][$key] = min((int) ($m['cap'] ?? 99), $cur + 1); }
            else { $pc['skills'][$key] = min(99, $cur + 1); }
            $out[] = sprintf('D2＝1——%s +1 → %d。', $key, (int) crrg_coc_skill_val($pc, $key));
        }
    }
    $out[] = sprintf('（耗了约 %d 分钟；精力 −6、饱食 −2。）', $mins);
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'train', 'key' => $key, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'training']);
    }
    return $out;
}

/** 列出**现在能练什么**（不能练的说为什么 ✓） */
function crrg_train_menu(&$s, &$pc) {
    $t = crrg_train_targets($pc);
    $out = ['〔能练的〕（在训练室／档案室／医务室／车库／野外各有对口 ✓）'];
    $rows = [];
    foreach (['attr' => '属性', 'skill' => '技能'] as $kind => $cn) {
        foreach ((array) $t[$kind] as $k) {
            $v = (int) crrg_coc_skill_val($pc, $k);
            [$ok, $why] = crrg_train_can($s, $pc, $k, $kind);
            $m = crrg_train_method($k, $kind);
            $rows[] = [$ok, sprintf('%s%s %s＝%d%s', $ok ? '　' : '✗', $cn, $k, $v,
                $ok ? '' : '　' . $why)];
        }
    }
    usort($rows, fn($a, $b) => $b[0] <=> $a[0]);
    foreach ($rows as $r) $out[] = $r[1];
    $out[] = '（练：「训练 <名字>」；比如「训练 力量」「练 侦查」。）';
    return $out;
}
