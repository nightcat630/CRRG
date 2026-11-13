<?php
/**
 * 中型任务《封条》· 第 4 轮（Act II 后半：抢料、门事故、回基地工艺前置）
 *   ① 抢料事件：材料齐了之后，有人来抢（三选一，各有代价）
 *   ② C2 门事故：按「库存天数 × Act I 证据 × 巡检质量」分**轻／中／重**三档
 *   ③ 回基地交接 + 工艺前置（稳妥／激进／放弃 → 留给第 5 轮算 12 种结局）
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    /* 2026-10-14：《封条》已下线（委员长：整个不要了）——只关注册，函数与存档都留着 ✓
       恢复办法：把 missions/_retired.php 里的 crrg_retired_seal() 改成 return true ✓ */
    if (function_exists('crrg_retired_seal') && !crrg_retired_seal()) return;
    if (!function_exists('crrg_drill_register_mission')) return;
    crrg_drill_register_mission('m_mid_seal4', [
                'pass_rooms' => ['b4_main', 'b4_c2', 'b2b_tech', 'garage_maint', 'b2b_hall'],
        'name' => '封条', 'level' => '中', 'kind' => 'base', 'min_lv' => 3, 'hidden' => true, 'chain_no' => 4, 'chain_total' => 5, 'chain_next' => 'm_mid_seal5',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'deadline_h' => 96,
        'prompt' => '把料带回基地，处理那扇一直响的门。路上和门口都会有人拦你——怎么过，自己定。',
        'steps' => [
            ['flag' => 's4_抢料',     'text' => '把料护住（镇上或路上）'],
            ['flag' => 's4_料况确认', 'text' => '确认到手材料的完整度（完整或不全，都算确认）'],
            ['room' => 'garage_maint','text' => '回到基地车库'],
            ['flag' => 's4_交接',     'text' => '在技术研发室交接材料'],
            /* 2026-10-01 修（玩家卡"判定完门况任务没完成"✗）：规范名是 s4_事故分档 ✓，
               但早期过法写入的是 s4_事故定档 ✗ → 两种都认 ✓（旧存档不再白做 ✓） */
            ['flag' => 's4_事故分档', 'any' => ['s4_事故分档', 's4_事故定档'], 'text' => '判定 C2 那扇门的状况'],
            ['flag' => 's4_事故处理', 'text' => '处置事故（按档位）'],
            ['flag' => 'craft_选择',  'text' => '选定工艺：稳妥／激进／放弃'],
            ['room' => 'b2b_hall',    'text' => '回值班板，把第二幕收尾'],
        ],
        'reward' => ['credits' => 14, 'money' => 600], 'rep' => ['base' => 2],
        'settle_note' => '料在库里，门处理了（或者没有），工艺定了——剩下的是收尾。',
    ]);
}, 26);

/* ══════════ ① 抢料事件 ══════════ */
function crrg_seal4_rob(&$s, &$pc, $mode = '') {
    if (crrg_seal_has($s, 's4_抢料')) { crrg_seal_set($s, 's4_料况确认'); return ['（这事已经过去了。）']; }
    $ready = crrg_seal_has($s, 'paper_配方') && (crrg_seal_has($s, 'paper_铅箔') || crrg_seal_has($s, 'paper_朱砂土'));
    if (!$ready) return ['（你手上的料还不值得别人来抢。）'];
    if ($mode === '') {
        return ['天快黑的时候，两辆摩托从坡下上来，把路口堵了。',
                '下来的不是田小满。领头的那个，就是穿夹克的。',
                '「把纸给我，我让你回基地。」他说，「不然今晚谁都不好走。」',
                '（「护料 硬碰」／「护料 让步」／「护料 开车跑」。）'];
    }
    if (strpos($mode, '硬碰') !== false) {
        $val = max(1, crrg_coc_skill_val($pc, '格斗'));
        $r = crrg_coc_check('格斗', $val);
        $out = ['你把料箱往身后一挪，先动的手。', '格斗：' . $r['text']];
        if (crrg_coc_level_rank($r['level']) >= 2) {
            crrg_seal_set($s, 's4_抢料'); crrg_seal_set($s, 's4_料况确认'); crrg_seal_set($s, 's4_料完整'); crrg_seal_set($s, 's4_硬碰成功');
            $out[] = '对方架不住你，扶起摩托退了。料一片没少。';
            $out[] = '（脖子上被划了一道——回基地要处理一下。）';
            $pc['hp'] = max(1, (int) $pc['hp'] - 1);
        } else {
            crrg_seal_set($s, 's4_抢料'); crrg_seal_set($s, 's4_料况确认');
            $pc['hp'] = max(0, (int) $pc['hp'] - crrg_coc_dice('1d3'));
            $out[] = '你被压在雪里，料箱摔开了。配方那页丢了一半。';
            $out[] = '（生命 −' . '若干' . ' ｜ **材料不再完整**。）';
            crrg_seal_set($s, 's4_料_缺');
            if (function_exists('crrg_rep_add')) crrg_rep_add($pc, 'base', -1, '《封条》：料被抢走一半');
        }
        return $out;
    }
    if (strpos($mode, '让步') !== false) {
        crrg_seal_set($s, 's4_抢料');
        crrg_seal_set($s, 's4_料况确认'); crrg_seal_set($s, 's4_料_缺'); crrg_seal_set($s, 's4_让步');
        crrg_rep_add($pc, 'town_qingshi', -1, '《封条》：把料分了出去');
        return ['你把铅皮和一半的纸推给他。',
                '「聪明。」他把东西塞进后座，骑车走了。',
                '（材料不完整 ｜ 但你和料都完整地回了基地 ｜ 镇上传出"你跟那人做过交易"。）'];
    }
    if (strpos($mode, '开车跑') !== false) {
        if (function_exists('crrg_travel_mode') && crrg_travel_mode($pc) === '徒步') return ['（你是走来的，跑不了。）'];
        crrg_seal_set($s, 's4_抢料'); crrg_seal_set($s, 's4_料况确认'); crrg_seal_set($s, 's4_料完整'); crrg_seal_set($s, 's4_开车跑');
        $out = ['你上车、挂挡、一脚油门从两台摩托中间挤过去。',
                '后视镜里他们追了不到两公里就停了——他们的车不适合跑山路。'];
        if (function_exists('crrg_travel_veh')) {
            $m = crrg_travel_mode($pc); $vv = crrg_travel_veh($pc, $m);
            $vv['wear'] = min(100, (float) $vv['wear'] + 8); crrg_travel_save_veh($pc, $m, $vv);
            $out[] = '（车损 +8）：底盘蹭了一下，回去得看看。料一片没少。';
        }
        return $out;
    }
    return ['（「护料 硬碰」／「护料 让步」／「护料 开车跑」。）'];
}

/* ══════════ ② C2 门事故：分档判定 ══════════ */
function crrg_seal4_grade(&$s, &$pc) {
    $stock = function_exists('crrg_seal_inventory') ? (int) crrg_seal_inventory($pc)['stock_days'] : 9;
    $proof = 0;
    foreach (['seal_门况普查', 'seal_私补痕迹', '线索_封条松了', 'seal_用量台账'] as $f) if (crrg_seal_has($s, $f)) $proof++;
    $reported = crrg_seal_has($s, 'seal_路线_上报');
    $score = $stock + $proof * 2 + ($reported ? 3 : 0) + (crrg_seal_has($s, 's4_料完整') ? 2 : 0);
    if ($score >= 20) return ['轻', $score];
    if ($score >= 13) return ['中', $score];
    return ['重', $score];
}
function crrg_seal4_accident(&$s, &$pc) {
    if (crrg_seal_has($s, 's4_事故分档')) return ['（事故已经定档了。）'];
    [$grade, $score] = crrg_seal4_grade($s, $pc);
    crrg_seal_set($s, 's4_事故分档');
    if ($grade === '轻') {
        crrg_seal_set($s, 'acc_轻');
        return ['C2 西头那扇门在凌晨三点响了一声，值班员按规程先贴了备用封条。',
                '等你回到基地，门已经临时加固住了。**没有人受伤。**',
                sprintf('（判定：轻 ｜ 评分 %d＝库存＋巡检证据＋是否上报＋材料完整度）', $score),
                '（处置：「事故 补件」把新料用上去。 ）'];
    }
    if ($grade === '中') {
        crrg_seal_set($s, 'acc_中');
        return ['你还没进车库，就听见对讲机在叫：**C2 西头的门撑开了半尺**。',
                '值班员用手把门推回去，被卡了一下——手背缝了三针。廊里那股味散到主走廊。',
                sprintf('（判定：中 ｜ 评分 %d）', $score),
                '（处置：「事故 补件」／「事故 封闭」／「事故 上报」——三选一，各有代价。）'];
    }
    crrg_seal_set($s, 'acc_重');
    return ['C2 西头那扇门**开了**。',
            '里面那件东西沿着主走廊走了十七米，停在警卫值班室门口。',
            '值班员按了紧急程序，把整条廊封了。**有两个人进了医疗室**，其中一个是白大夫。',
            sprintf('（判定：重 ｜ 评分 %d）', $score),
            '（处置：「事故 补件」／「事故 封闭」／「事故 上报」——三选一，各有代价。）'];
}
function crrg_seal4_accident_handle(&$s, &$pc, $mode) {
    if (!crrg_seal_has($s, 's4_事故分档')) return ['（先去收容廊看看那扇门。）'];
    if (crrg_seal_has($s, 's4_事故处理')) return ['（已经处置过了。）'];
    $grade = crrg_seal_has($s, 'acc_重') ? '重' : (crrg_seal_has($s, 'acc_中') ? '中' : '轻');
    if ($mode === '') return ['（「事故 补件」／「事故 封闭」／「事故 上报」。）'];
    if (strpos($mode, '补件') !== false) {
        if (crrg_seal_has($s, 's4_料_缺')) return ['（你的料不完整——补不上这一扇。只能选封闭或上报。）'];
        crrg_seal_set($s, 's4_事故处理'); crrg_seal_set($s, 'acc_处理_补件');
        if (function_exists('crrg_seal_use')) crrg_seal_use($pc, 2, '事故补件');
        crrg_rep_add($pc, 'base', 3, '《封条》：用新料补门');
        return ['你按老匠人的配比（或基地配方）重新做了一扇的料，当着两个人的面贴上去。',
                '按上去的那一刻，廊里安静下来了。',
                '（基地声望 +3 ｜ 封条库存 −2 天 ｜ 工艺材料消耗一部分。）'];
    }
    if (strpos($mode, '封闭') !== false) {
        crrg_seal_set($s, 's4_事故处理'); crrg_seal_set($s, 'acc_处理_封闭');
        crrg_rep_add($pc, 'base', 1, '《封条》：封闭收容廊');
        return ['你把那条廊整段封了：水泥、钢板、焊条。三天之内谁也别想进去。',
                '（基地声望 +1 ｜ 但**那条廊里的东西还在里面**，后面要另想办法。）'];
    }
    if (strpos($mode, '上报') !== false) {
        crrg_seal_set($s, 's4_事故处理'); crrg_seal_set($s, 'acc_处理_上报');
        crrg_rep_add($pc, 'base', $grade === '重' ? -2 : 1, '《封条》：事故上报');
        return ['你写了事故报告：时间、门况、值班记录、处置过程。',
                $grade === '重'
                    ? '上面批了"' . '严肃追责' . '"四个字。值班室那天的人被叫去谈话。'
                    : '上面批了"加强巡检"。',
                '（' . ($grade === '重' ? '基地声望 −2' : '基地声望 +1') . ' ｜ 报告入档。）'];
    }
    return ['（「事故 补件」／「事故 封闭」／「事故 上报」。）'];
}

/* ══════════ ③ 工艺前置（第三幕的入口） ══════════ */
function crrg_seal4_craft(&$s, &$pc, $key = '') {
    if (crrg_seal_has($s, 'craft_选择')) return ['（工艺已经选过了。）'];
    if (!crrg_seal_has($s, 's4_交接')) return ['（先把材料交给技术研发室。）'];
    if ($key === '') return ['（「工艺 稳妥」／「工艺 激进」／「工艺 放弃」。）'];
    if (strpos($key, '稳妥') !== false) {
        crrg_seal_set($s, 'craft_选择'); crrg_seal_set($s, 'craft_稳妥');
        return ['韩雪把老匠人的配方与实验室方案并排放着：「按我的来，三天，能出一批合格的。」',
                '「代价是这三天我别的不干。」',
                '（工艺：稳妥 —— 三天后交货，期间韩雪不可用；结局偏"无事"。）'];
    }
    if (strpos($key, '激进') !== false) {
        crrg_seal_set($s, 'craft_选择'); crrg_seal_set($s, 'craft_激进');
        return ['周德海把他那半手教给了你：「别按书上，按我试的。今晚就能用。」',
                '（工艺：激进 —— 当晚可用，但**接触者要过体质检定**，且有中毒风险。）'];
    }
    if (strpos($key, '放弃') !== false) {
        crrg_seal_set($s, 'craft_选择'); crrg_seal_set($s, 'craft_放弃'); crrg_seal_set($s, 'hook_特批等待');
        return ['你把材料封存，填了一份《特殊耗材特批申请》。',
                '（工艺：放弃自产 —— 走审批，等 7 游戏日；期间库存继续掉。）'];
    }
    return ['（「工艺 稳妥」／「工艺 激进」／「工艺 放弃」。）'];
}
/** 交接材料
 *  2026-10-01 修（玩家卡死 ✗）：原来**只认材料旗标**（paper_配方/铅箔/朱砂土 ✗）——
 *  而这三样会在第三幕按天消耗（库存归零 ✗）→ 走到第四幕时旗标已没 ✗ → 永远"手上没有可交的材料"✗。
 *  现在补两条回退：① 已交过 ✓ ② **第二幕确实走完**（材料在第二幕已登记 ✓）→ 视为材料到位 ✓
 */
function crrg_seal4_handover(&$s, &$pc) {
    if (crrg_seal_has($s, 's4_交接')) return ['（材料已经交过了。）'];
    $got = []; $viaDone = false;
    foreach (['paper_配方' => '配方', 'paper_铅箔' => '铅皮', 'paper_朱砂土' => '朱砂土'] as $f => $n) if (crrg_seal_has($s, $f)) $got[] = $n;
    if (!$got) {
        /* 回退：第二幕（m_mid_seal2）已完成 → 材料在那一幕已经取到手并登记过 ✓ */
        $done = (array) ($pc['mission_done'] ?? []);
        if (in_array('m_mid_seal2', $done, true) || in_array('m_mid_seal3', $done, true)) {
            $viaDone = true;
            $got = ['配方', '铅皮', '朱砂土'];
            crrg_seal_set($s, 's4_料_补齐');       // 记一笔：按"已在第二幕登记"补的 ✓
        }
    }
    if (!$got) return ['（你手上没有可交的材料。）',
                       '（材料在**第二幕**取到手；若那一段没走完，先去镇上把材料凑齐。）'];
    crrg_seal_set($s, 's4_交接');
    return ['技术研发室的人把材料一件件登记、称重、编号。',
            ($viaDone ? '（你在第二幕已经交过一次，这里按记录续上：' : '（入库：') . implode('、', $got)
                      . (crrg_seal_has($s, 's4_料_缺') ? '　**（不完整）**' : '　（完整）') . '）',
            '韩雪抬头问你：「按哪个方案做？」（「工艺 稳妥／激进／放弃」。）'];
}
function crrg_seal4_stage_lines(&$s, $pc) {
    $f = (array) ($s['flags'] ?? []);
    $need = ['s4_抢料' => '护料', 's4_交接' => '交接', 's4_事故分档' => '事故定档', 's4_事故处理' => '事故处置', 'craft_选择' => '工艺'];
    $got = []; $miss = [];
    foreach ($need as $k => $n) { if (in_array($k, $f, true)) $got[] = $n; else $miss[] = $n; }
    $stock = function_exists('crrg_seal_inventory') ? (int) crrg_seal_inventory($pc)['stock_days'] : 0;
    $acc = in_array('acc_重', $f, true) ? '重' : (in_array('acc_中', $f, true) ? '中' : (in_array('acc_轻', $f, true) ? '轻' : '未定'));
    return [sprintf('第四轮 · 抢料与那扇门 ｜ 已办：%s ｜ 待办：%s ｜ 库存 %d 天 ｜ 事故 %s',
        $got ? implode('、', $got) : '（无）', $miss ? implode('、', $miss) : '（齐）', $stock, $acc),
        '（护料 硬碰/让步/开车跑 ｜ 事故 补件/封闭/上报 ｜ 工艺 稳妥/激进/放弃）'];
}
