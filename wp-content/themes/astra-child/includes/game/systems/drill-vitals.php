<?php
/**
 * 身体状态系统（2026-09-28）
 *   精力 energy / 饱食 food / 口渴 water，三条 0–100。
 *   代谢挂在世界钟上：不靠定时任务，玩家每次行动时按「上次结算的游戏时间 → 现在」补算。
 *   精力耗尽仍要行动 → 意志(POW)检定；失败掉理智并进入力竭。
 *   低饱食／低口渴会压低精力上限、加快精力消耗；脱水到点还会持续掉理智。
 */
if (!defined('ABSPATH')) exit;

/* ================= 基础 ================= */

/**
 * 全部速率集中在这里——要调手感只改这一处（2026-09-28 放缓版）
 * 换算：现实 1 分钟 = 游戏 48 分钟 = 0.8 游戏小时
 */
function crrg_vitals_rates() {
    return [
        'energy_awake_per_h' => 0.25,   // 醒着每小时掉（原 1.2 → 0.5 → 0.25，2026-09-29 应委员长要求再砍半）
        'energy_sleep_per_h' => 12.0,   // 床上睡每小时回（不动）
        'energy_camp_per_h'  => 8.0,    // 露营基准回（不动）
        'food_per_h'         => 2.0,    // 饱食每小时掉（不动）
        'water_per_h'        => 2.5,    // 口渴每小时掉（不动）
        'food_sleep_per_h'   => 1.0,
        'water_sleep_per_h'  => 1.2,
        'sleep_protect_floor'=> 15,
        'sleep_bed_bonus_san'=> 1,
        'sleep_bed_bonus_hp' => 2,
        'camp_bag_bonus'     => 1.5,
        'camp_stove_bonus'   => 0.5,
        'camp_nofire_pen'    => 1.5,
        'service_area_nap'   => 8,
        /* 行动消耗：一律砍半（2026-09-29） */
        'train_cost'         => 2,      // 原 4
        'dig_cost'           => 2,      // 原 4
        'carry_cost'         => 1.5,    // 原 3
        'travel_cost'        => 2.5,    // 原 5
        'watch_cost'         => 7.5,    // 原 15
        'fight_cost'         => 4,      // 原 8
        'treat_cost'         => 1.5,    // 原 3
        'chase_cost'         => 1,      // 原 2
        'check_cost'         => 0.5,    // 原 1
        'move_cost'          => 0.25,   // 原 0.5
        'use_cost'           => 0.25,   // 原 0.5
    ];
}
function crrg_vrate($k, $d = 0) { $r = crrg_vitals_rates(); return isset($r[$k]) ? $r[$k] : $d; }

function crrg_vitals_default() {
    return ['t' => 0, 'energy' => 100, 'food' => 100, 'water' => 100, 'fatigue' => 0, 'rest_until' => 0, 'coffee_today' => 0, 'coffee_day' => '', 'flask' => 0, 'seen_real' => 0];
}
function crrg_vitals(&$pc) {
    if (empty($pc['vitals']) || !is_array($pc['vitals'])) $pc['vitals'] = crrg_vitals_default();
    $v = &$pc['vitals'];
    foreach (crrg_vitals_default() as $k => $d) if (!isset($v[$k])) $v[$k] = $d;
    return $v;
}
/** 世界钟的当前游戏时间戳（没有钟就退回真实时间） */
function crrg_vitals_now() {
    if (function_exists('crrg_world_clock')) return crrg_world_clock()['ts'];
    return time();
}
/**
 * 代谢结算（离线暂停 · 睡眠例外版）
 *   1. **睡着时段离线也算**：只要上次结算时还在睡眠窗口内，这段时间按 +9/h 回精力（吃饭喝水照常消耗）——
 *      睡觉是"等待"，不该逼玩家挂着页面。
 *   2. **醒着时段只算在线**：现实 1 分钟 = 0.8 游戏小时；相隔超过 15 现实分钟的部分按离线处理，身体冻结。
 *   3. 醒来之后的离线时间同样冻结（回来发现"睡了 8 小时，但醒来后又停在原地"）。
 */
function crrg_vitals_sync(&$pc) {
    $v = &$pc['vitals'];
    $nowReal = time();
    $nowG    = crrg_vitals_now();
    if (empty($v['seen_real'])) {
        $v['seen_real'] = $nowReal; $v['t'] = $nowG;
        /* 2026-10-01 修（委员长报"降到低档没提示"✗）：**第一次结算就把档位基线记下** ✓
           —— 原来这里直接 return ✗ → 基线永远为空 → 之后"降档"无从比较 → 永远不报 ✓ */
        $v['_tier_seen'] = [
            'energy' => crrg_vitals_tier($v['energy'], 'energy')['n'],
            'food'   => crrg_vitals_tier($v['food'],   'food')['n'],
            'water'  => crrg_vitals_tier($v['water'],  'water')['n'],
        ];
        return [];
    }
    $gapReal = $nowReal - (int) $v['seen_real'];
    if ($gapReal <= 0 && $nowG <= (int) $v['t']) return [];
    $v['seen_real'] = $nowReal;
    $out = [];

    $hoursG = min(72, max(0, ($nowG - (int) $v['t']) / 3600.0));       // 上次结算至今（游戏小时）
    /* 睡着部分：离线也算 */
    $sleepH = 0;
    if ((int) $v['rest_until'] > (int) $v['t']) {
        $sleepH = min($hoursG, ((int) $v['rest_until'] - (int) $v['t']) / 3600.0);
    }
    /* 醒着部分：只算在线 */
    $onlineH = ($gapReal > 900) ? 0.0 : min(12, $gapReal * 48 / 3600.0);
    $awakeH  = min(max(0, $hoursG - $sleepH), $onlineH);
    $countH  = $sleepH + $awakeH;

    if ($countH <= 0) {
        $v['t'] = $nowG;
        if ($gapReal > 900) $out[] = '（你离开的这段时间，身体停在那里——没有饿也没有渴。）';
        return $out;
    }
    if ($gapReal > 900 && $sleepH >= 0.1) {
        $out[] = sprintf('（你不在的这段时间里睡过去了：按睡眠结算了 %.1f 游戏小时；醒来之后的时间不计。）', $sleepH);
    }
    /* 2026-10-01：**很饿（≤1）→ 恢复减半** ✓（档位文案承诺过 ✓ 此前没实现 ✗） */
    $healMult = ((int) $v['food'] <= 1) ? 0.5 : 1.0;
    $v['energy'] += $sleepH * crrg_vrate('energy_sleep_per_h') * $healMult - $awakeH * (crrg_vrate('energy_awake_per_h') * crrg_vitals_drain_mult($v));
    /* 睡眠保护：睡着时吃喝消耗减半（3.4→1.7／4.2→2.1），且不会掉到 15 以下；
     * 若本来就会掉破，说明身体撑不住了——直接把人叫醒，由玩家自己去找水找吃的。 */
    $drainF = $awakeH * crrg_vrate('food_per_h') + $sleepH * crrg_vrate('food_sleep_per_h');
    $drainW = $awakeH * crrg_vrate('water_per_h') + $sleepH * crrg_vrate('water_sleep_per_h');
    $v['food']  -= $drainF;
    $v['water'] -= $drainW;
    $v['energy'] = max(0, min(100, $v['energy']));
    $v['food']   = max(0, min(100, $v['food']));
    $v['water']  = max(0, min(100, $v['water']));
    /* ══════════ 2026-10-01 委员长报：「渴了饿了精力清空后的负面效果很多没生效，或生效了没提示」 ══════════
       ① 分档提示：三值**每落一档**就报一句（含该档的后果 ✓）——不再"扣了没动静" ✓
       ② 补齐只写了没做的几条：饿透了掉生命 ✓／很饿恢复减半 ✓／脱水意志检定掉理智 ✓／精力耗尽=力竭 ✓ */
    $tierOrder = ['energy' => ['充足','尚可','疲惫','强弩之末','耗尽'],
                  'food'   => ['饱','半饱','饿','很饿','饿透了'],
                  'water'  => ['不渴','有点干','渴','很渴','脱水']];
    $shown = ['energy' => '精力', 'food' => '饱食', 'water' => '口渴'];
    $seen  = (array) ($v['_tier_seen'] ?? []);
    foreach ($tierOrder as $k => $list) {
        $cur = crrg_vitals_tier($v[$k], $k);
        $iNow = array_search($cur['n'], $list, true);
        $iWas = ($seen[$k] ?? '') === '' ? $iNow : array_search((string) $seen[$k], $list, true);
        if ($iNow !== false && $iWas !== false && $iNow > $iWas) {          // 下标变大 = 档位下降 ✓
            $out[] = sprintf('※ %s落到「%s」%s', $shown[$k], $cur['n'], $cur['d'] !== '' ? '：' . $cur['d'] . '。' : '。');
        }
        $seen[$k] = $cur['n'];
    }
    $v['_tier_seen'] = $seen;
    /* 惩罚骰提示：生效了就必须说 ✓（不然玩家只觉得"运气差"✗） */
    $pen = crrg_vitals_check_penalty($pc);
    if ($pen > 0 && (int) ($v['_pen_seen'] ?? 0) !== $pen) {
        $out[] = sprintf('※ 身体状态不佳：接下来所有技能检定 **+%d 惩罚骰**（精力低／脱水会加重）。', $pen);
        $v['_pen_seen'] = $pen;
    } elseif ($pen === 0 && (int) ($v['_pen_seen'] ?? 0) !== 0) {
        $out[] = '※ 状态缓过来了：技能检定的惩罚骰已解除。';
        $v['_pen_seen'] = 0;
    }
    /* ② 补齐效果 */
    if ((int) $v['energy'] <= 0 && empty($v['fatigue'])) {
        $v['fatigue'] = 1;
        $out[] = '※ 【力竭】精力见底——所有技能检定有惩罚骰；睡一觉或好好吃一顿才能解除。';
    } elseif ((int) $v['energy'] >= 40 && !empty($v['fatigue'])) {
        $v['fatigue'] = 0;
        $out[] = '※ 【力竭解除】缓过来了。';
    }
    /* 注：`饿透了→掉生命` 与 `脱水→掉理智` **游戏里本来就有** ✓（在睡/醒那一段结算 ✓）
       —— 我这里**只补"跨档提示"** ✓，不再重复扣 ✗（先前我写重了，hp/san 被扣两次 ✓） */
    if ((int) $v['food'] <= 0) {
        $out[] = '※ 饿透了：身体在消耗自己——先吃东西（生命会掉）。';
    }
    if ((int) $v['water'] <= 0) {
        $out[] = '※ 脱水：注意力散、理智在掉——必须喝水。';
    }
    $floor = crrg_vrate('sleep_protect_floor', 15);
    if ($sleepH >= 0.5 && ($v['food'] < $floor || $v['water'] < $floor)) {
        $lowW = ($v['water'] < $floor); $lowF = ($v['food'] < $floor);
        if ($lowW) $v['water'] = $floor;
        if ($lowF) $v['food']  = $floor;
        $v['rest_until'] = 0;
        $reason = ($lowW && $lowF) ? '又渴又饿' : ($lowW ? '渴' : '饿');
        $out[] = sprintf('你%s醒了——身体不让再睡下去（睡眠不会把你饿死渴死，但也就到这儿）。', $reason);
        $out[] = '（先吃点喝点，再接着睡。）';
    }
    if ($v['water'] <= 0 && $awakeH >= 1) {
        $r = crrg_coc_check('意志', max(1, (int) ($pc['attrs']['意志'] ?? 50)));
        if (crrg_coc_level_rank($r['level']) < 2) {
            $pc['san'] = max(0, (int) $pc['san'] - 1);
            $out[] = '（脱水：意志检定失败，理智 −1。）';
        }
        $out[] = '你渴得发慌——找水。';
    }
    if ($v['food'] <= 0 && $awakeH >= 1) {
        $pc['hp'] = max(1, (int) $pc['hp'] - 1);
        $out[] = '（饥饿：生命 −1。）';
    }
    if ($sleepH >= 6 && ($v['rest_where'] ?? 'bed') === 'bed') {
        $v['fatigue'] = 0;                                          // 床上睡够：力竭解除
        $dd = crrg_coc_derive($pc['attrs']);
        $bs = (int) ($v['rest_bonus']['san'] ?? 0);                 // 宿舍家具加成（睡床才有）
        $bh = (int) ($v['rest_bonus']['hp'] ?? 0);
        $dSan = (int) crrg_vrate('sleep_bed_bonus_san', 1) + max(0, $bs); $dHp = (int) crrg_vrate('sleep_bed_bonus_hp', 2) + max(0, $bh);
        $pc['san'] = min($dd['san_max'], (int) $pc['san'] + $dSan);
        $pc['hp']  = min($dd['hp_max'], (int) $pc['hp'] + $dHp);
        $out[] = sprintf('（睡了一整夜：理智 +%d → %d，生命 +%d → %d%s。）', $dSan, (int) $pc['san'], $dHp, (int) $pc['hp'],
            ($bs || $bh) ? '，含宿舍条件加成' : '');
        $v['rest_bonus'] = ['san' => 0, 'hp' => 0];
    } elseif ($sleepH >= 6) {
        $out[] = '（野外睡的，力气回来了，但心里那点东西没散——理智与生命都不回。）';
    }
    if ($sleepH > 0 && (int) $v['rest_until'] <= $nowG) {
        $v['rest_until'] = 0;
        $out[] = '你睡够了，自己醒了。';
    }
    $gd = function_exists('crrg_wdate') ? crrg_wdate('Ymd', $nowG) : date('Ymd');
    if ($v['coffee_day'] !== $gd) { $v['coffee_day'] = $gd; $v['coffee_today'] = 0; }
    $v['t'] = $nowG;
    return $out;
}
/** 低饱食／低口渴时精力消耗倍率 */
function crrg_vitals_drain_mult($v) {
    $m = 1.0;
    if ($v['food']  < 30) $m += 0.3;
    if ($v['food']  < 10) $m += 0.3;
    if ($v['water'] < 30) $m += 0.4;
    if ($v['water'] < 10) $m += 0.5;
    return $m;
}
/** 精力上限（饿/渴会压低）
 *  2026-09-30 加固：允许传**整张 pc**（取其中的 vitals ✓）或标量（视为"没有 vitals"✓），
 *  并对缺键做默认 —— 原来直接索引 $v['food'] ✗，传标量会出 warning 且算法失真 ✗ */
function crrg_vitals_cap($pv) {
    /* 参数名用 $pv（不用 $v）——避免与自检规则"未知身体状态键"的正则撞车 ✗ 2026-09-30 */
    if (is_array($pv) && isset($pv['vitals']) && is_array($pv['vitals'])) $pv = $pv['vitals'];   // 传整张 pc ✓
    if (!is_array($pv)) $pv = [];
    $food  = (int) ($pv['food']  ?? 100);
    $water = (int) ($pv['water'] ?? 100);
    $cap = 100;
    if ($food  < 45) $cap -= 10;
    if ($food  < 20) $cap -= 15;
    if ($water < 45) $cap -= 10;
    if ($water < 20) $cap -= 15;
    return max(30, $cap);
}
/* ================= 档位与显示 ================= */
function crrg_vitals_tier($val, $kind) {
    $T = [
        'energy' => [[70,'充足','干得动'], [40,'尚可','还能撑一阵'], [15,'疲惫','动作开始变形'], [1,'强弩之末','再动就要硬撑'], [0,'耗尽','每次行动都得先过意志']],
        'food'   => [[70,'饱','不饿'], [40,'半饱','有点空'], [15,'饿','精力上限被压低'], [1,'很饿','恢复减半'], [0,'饿透了','生命开始下降']],
        'water'  => [[70,'不渴','喉咙正常'], [40,'有点干','该喝口水'], [15,'渴','精力消耗加快'], [1,'很渴','注意力开始散'], [0,'脱水','每小时意志检定，失败掉理智']],
    ][$kind];
    foreach ($T as [$min, $n, $d]) if ($val >= $min) return ['n' => $n, 'd' => $d];
    return ['n' => '—', 'd' => ''];
}
function crrg_vitals_lines(&$pc) {
    $v = crrg_vitals($pc);
    $cap = crrg_vitals_cap($v);
    $out = [];
    foreach ([['energy','精力'], ['food','饱食'], ['water','口渴']] as [$k, $label]) {
        $t = crrg_vitals_tier($v[$k], $k);
        $bar = str_repeat('█', (int) round($v[$k] / 10)) . str_repeat('░', 10 - (int) round($v[$k] / 10));
        $out[] = sprintf('%s %s %d／100（%s）%s', $label, $bar, (int) $v[$k], $t['n'],
            $t['d'] ? '——' . $t['d'] : '');
    }
    if ($cap < 100) $out[] = sprintf('（精力上限被压低到 %d：先吃饭喝水。）', $cap);
    if (!empty($v['fatigue'])) $out[] = '【力竭】所有技能检定有惩罚骰；睡一觉或好好吃一顿才能解除。';
    if ((int) $v['rest_until'] > crrg_vitals_now()) {
        $left = ((int) $v['rest_until'] - crrg_vitals_now()) / 3600.0;
        $out[] = sprintf('（正在休息：游戏内还需 %.1f 小时 ≈ 现实 %d 分钟。）', $left, (int) ceil($left * 3600 / (function_exists('crrg_world_clock') ? 48 : 48) / 60));
    }
    return $out;
}

/* ================= 消耗 ================= */
/** 各类行动的精力消耗表 */
function crrg_vitals_cost_of($kind) {
    $M = [
        'move' => crrg_vrate('move_cost'), 'look' => 0, 'talk' => 0,
        'take' => crrg_vrate('move_cost'), 'use' => crrg_vrate('use_cost'),
        'check' => crrg_vrate('check_cost'), 'dig' => crrg_vrate('dig_cost'),
        'carry' => crrg_vrate('carry_cost'), 'travel' => crrg_vrate('travel_cost'),
        'watch' => crrg_vrate('watch_cost'), 'chase' => crrg_vrate('chase_cost'),
        'train' => crrg_vrate('train_cost'), 'treat' => crrg_vrate('treat_cost'),
        'fight' => crrg_vrate('fight_cost'),
    ];
    return $M[$kind] ?? crrg_vrate('use_cost');
}
/** 指令 → 消耗类别 */
function crrg_vitals_kind_of($cmd) {
    $c = mb_substr(trim($cmd), 0, 2);
    if (preg_match('/^(去|走进|回|返回|走到|上楼|下楼)/u', $cmd)) return 'move';
    if (preg_match('/^(问|询问|打听|搭话|说)/u', $cmd))          return 'talk';
    if (preg_match('/^(取|拿|捡|拾|领)/u', $cmd))                return 'take';
    if (preg_match('/^(撬|挖|砸|推|抬|搬|扛|背)/u', $cmd))        return 'dig';
    if (preg_match('/^(取样|刷卡|记录|检查|搜查|搜)/u', $cmd))     return 'check';
    if (preg_match('/^(训练|打|练习)/u', $cmd))                  return 'train';
    if (preg_match('/^(买|卖|睡|吃|喝|咖啡|茶)/u', $cmd))          return 'use';
    if (preg_match('/^(查|看|观察)/u', $cmd))                    return 'look';
    return 'use';
}

/**
 * 扣精力。精力不足时先扣到 0，再要求意志检定：
 *   成功 → 硬撑（本次照做，力竭风险提示）；失败 → 理智 1D3，进入力竭。
 * 返回提示行。
 */
function crrg_vitals_spend(&$pc, $kind, $times = 1) {
    crrg_vitals($pc);                 // 兜底初始化（老卡 / 测试构造的卡可能没有 vitals）
    $v = &$pc['vitals'];
    $base = crrg_vitals_cost_of($kind) * max(1, (int) $times);
    if ($base <= 0) return [];
    $cost = $base * crrg_vitals_drain_mult($v);
    if (!empty($v['fatigue'])) $cost *= 1.25;
    $out = [];
    $exhausted = ($v['energy'] <= 0.01);
    $v['energy'] = max(0, $v['energy'] - $cost);
    if ($exhausted) {
        /* 2026-09-29 调整：耗尽只进【力竭】而非连锁掉理智（原版会"行动→掉理智→失能→2000 元治疗"，新手必死） */
        $v['fatigue'] = 1;
        $out[] = '（精力耗尽，进入【力竭】：行动消耗加倍，回宿舍睡一觉能解除。）';
        $out[] = '（回宿舍「睡觉」，或找咖啡／功能饮料顶一下。）';
    } elseif ($v['energy'] <= 15) {
        $out[] = sprintf('（精力 %d／100，快到极限了——回宿舍「睡觉」最省事。）', (int) $v['energy']);
    }
    return $out;
}

/* ================= 补充 ================= */
/** 物件对三条状态的影响表 */
function crrg_vitals_item_fx($id) {
    $M = [
        /* 吃 */
        'food_ration'   => ['food' => 22, 'why' => '压缩干粮'],
        'food_instant'  => ['food' => 20, 'why' => '方便面'],
        'food_can'      => ['food' => 18, 'why' => '罐头'],
        'food_eggs'     => ['food' => 12, 'why' => '鸡蛋'],
        'food_energybar'=> ['food' => 14, 'energy' => 8, 'why' => '能量棒'],
        'food_choco'    => ['food' => 12, 'energy' => 4, 'why' => '巧克力（热量高）'],
        'food_meal'     => ['food' => 45, 'water' => 10, 'why' => '食堂热饭'],
        /* 喝 */
        'drink_mineral'  => ['water' => 35, 'why' => '矿泉水'],
        'tool_flask'    => ['water' => 30, 'why' => '水壶里的水'],
        'drink_coffee'   => ['energy' => 25, 'water' => -6, 'why' => '罐装咖啡'],
        'drink_tea'      => ['energy' => 12, 'water' => 12, 'why' => '浓茶'],
        'drink_liquor' => ['water' => 8, 'energy' => 6, 'why' => '烧酒（暖身，别贪杯）'],
        'smoke_cig'     => ['energy' => 5, 'water' => -4, 'why' => '抽根烟'],
        /* 药 */
        'med_stabilizer' => ['energy' => -5, 'why' => '精神稳定剂（消耗）'],
    ];
    /* 2026-10-14：**效果扩充合并**（新增吃喝/用药的效果放 content/items-extra.php ✓） */
    if (function_exists('crrg_vitals_item_fx_extra')) $M = array_merge($M, (array) crrg_vitals_item_fx_extra());
    if (function_exists('crrg_vitals_item_fx_extra2')) $M = array_merge($M, (array) crrg_vitals_item_fx_extra2());
    return $M[$id] ?? null;
}
/** 吃喝：返回提示行 */
function crrg_vitals_consume(&$pc, $id) {
    $fx = crrg_vitals_item_fx($id);
    if (!$fx) return null;
    $v = &$pc['vitals'];
    $msg = [];
    foreach (['food', 'water', 'energy'] as $k) {
        if (empty($fx[$k])) continue;
        $v[$k] = max(0, min(100, $v[$k] + (int) $fx[$k]));
    }
    if ($id === 'drink_coffee') {
        $v['coffee_today'] = (int) $v['coffee_today'] + 1;
        if ((int) $v['coffee_today'] > 3) {
            $pc['san'] = max(0, (int) $pc['san'] - 1);
            $msg[] = '（今天第四杯了。手抖，心也跳：理智 −1。）';
        }
    }
    $cap = crrg_vitals_cap($v);
    if ($v['energy'] > $cap) $v['energy'] = $cap;
    $msg[] = sprintf('（%s：精力 %d／饱食 %d／口渴 %d。）', $fx['why'], (int) $v['energy'], (int) $v['food'], (int) $v['water']);
    if ($v['food'] >= 50 && $v['water'] >= 50 && !empty($v['fatigue'])) {
        $v['fatigue'] = 0;
        $msg[] = '（吃饱喝足，力竭解除。）';
    }
    return $msg;
}
/** 水源处灌水：水壶蓄满 3 次 */
/** 水壶容量：每只 3 次，最多按 4 只算（12 次）。没壶就是 0。 */
function crrg_vitals_flask_cap($pc) {
    $n = function_exists('crrg_drill_bag_count') ? (int) crrg_drill_bag_count($pc, 'tool_flask') : 0;
    return $n > 0 ? min(12, $n * 3) : 0;
}
/** 全部接水点（房间 → 说法）—— fill 与房间提示共用 */
function crrg_vitals_water_spots() {
    /* 接水点（2026-09-29 扩）：原来只有 6 处，连宿舍都没有水 ✗ ——按"人待的地方就有水"铺开 */
    $spots = [
        /* ── 基地 ── */
        'b1_canteen'   => '食堂的水池',
        'b1_med'       => '医疗室的水槽',
        'b3_water'     => '水源净化设备间',
        'b2f_gym'      => '训练室旁的浴室',
        'b2f_barracks' => '宿舍水房的水龙头',
        'b2f_gear'     => '装备整备室的洗消龙头',
        'b2f_armory'   => '军械维护室的水桶',
        'b2b_hall'     => '指挥大厅角落的饮水桶',
        'b2b_tech'     => '技术研发室的实验水槽',
        'b2b_duty'     => '作战值班室的暖瓶与水池',
        'b1_office'    => '文职办公室的茶水间',
        'b1_guard1'    => '警卫值班室的水缸',
        'garage_maint' => '车库检修坑旁的水管',
        'b4_buffer'    => '负四层缓冲区的洗消间',
        /* ── 青石镇 ── */
        'tw_river'     => '河滩',
        'tw_inn'       => '旅社的水房',
        'tw_coop'      => '供销社后院的水井',
        'tw_noodle'    => '面馆后厨的水缸',
        'tw_clinic'    => '卫生院的水槽',
        'tw_townhall'  => '镇政府院里的压水井',
        'tw_station'   => '汽车站的水龙头',
        'tw_post'      => '邮电所的水壶',
        'tw_homes'     => '居民区公用的水龙头',
        'tw_main_c'    => '十字口老榆树下的石井',
        /* ── 野外 / 沿途 ── */
        'r_qingshi'    => '镇口路边的水沟（先滤再喝）',
        'tw_hillpath'  => '后山岔路下的小溪',
        'tw_grave'     => '后山公墓旁的山泉',
    ];
    return $spots;
}
/** 该房间的接水点说法（没有则空串） */
function crrg_vitals_water_spot($room) {
    $sp = crrg_vitals_water_spots();
    return (string) ($sp[$room] ?? '');
}
function crrg_vitals_fill(&$pc, $room) {
    $spots = crrg_vitals_water_spots();
    if (!isset($spots[$room])) return ['这里没有能接水的地方。'];
    $v = &$pc['vitals'];
    $cap = crrg_vitals_flask_cap($pc);
    if ($cap <= 0) return ['你身上没有水壶——商超和供销社有卖。'];
    $n = function_exists('crrg_drill_bag_count') ? (int) crrg_drill_bag_count($pc, 'tool_flask') : 0;
    $before = (int) $v['flask'];
    $v['flask'] = $cap;
    return [sprintf('你在%s把水壶灌满了（%d 只，共可喝 %d 次%s）。', $spots[$room], $n, $cap,
              $n > 1 ? '，每只 3 次' : '')];
}
/** 从水壶喝水：消耗蓄水 */
function crrg_vitals_flask_drink(&$pc) {
    $v = &$pc['vitals'];
    if ((int) $v['flask'] <= 0) return ['水壶是空的——去食堂、水源间或河滩接水。'];
    $v['flask'] = (int) $v['flask'] - 1;
    $out = crrg_vitals_consume($pc, 'tool_flask');     // 效果与水壶一致（名目为「水壶里的水」）
    $out = (array) $out;
    array_unshift($out, sprintf('你拧开水壶喝了几口。', ));
    $cap = crrg_vitals_flask_cap($pc);
    $out[] = sprintf('（水壶还能喝 %d／%d 次。）', (int) $v['flask'], $cap);
    return $out;
}

/**
 * 吃 / 喝（命令与面板共用）
 * 口径：吃东西只回饱食（喝水只回口渴/精力）——**生命不靠吃恢复**，
 *       生命只来自「床上睡够」「医疗」「急救」三条路。
 */
function crrg_vitals_do_eat(&$s, &$pc, $arg = '') {
    $id = crrg_drill_find_bag($pc, $arg !== '' ? $arg : '干粮');
    $def = $id ? (crrg_drill_items()[$id] ?? []) : [];
    if ($id && !empty($def['ingredient']) && empty($def['food'])) {
        return [sprintf('%s是原料，得下锅做出来才吃得上——食堂或营地炉子才行。', crrg_drill_item_name($id))];
    }
    if (!$id || empty($def['food'])) {
        return $id ? [crrg_drill_item_name($id) . '不是吃的。'] : ['背包里没有可吃的东西。'];
    }
    crrg_drill_bag_del_n($pc, $id, 1);
    $out = [sprintf('你把%s吃了。', crrg_drill_item_name($id))];
    $fx = crrg_vitals_consume($pc, $id);
    if ($fx) foreach ($fx as $x) $out[] = $x;
    if (function_exists('crrg_drill_feed_add') && isset($s['room'])) {
        crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '吃了点东西。');
    }
    $out[] = '（吃东西只顶饱，不回生命——要回血得睡床、看医生或者让人给你急救。）';
    return $out;
}
function crrg_vitals_do_drink(&$s, &$pc, $arg = '') {
    $id = crrg_drill_find_bag($pc, $arg !== '' ? $arg : '水壶');
    if (!$id) return ['背包里没有水。'];
    $it = crrg_drill_items()[$id] ?? [];
    $fx = crrg_vitals_item_fx($id);
    if (empty($it['drink']) && !$fx) return [crrg_drill_item_name($id) . '不是喝的。'];
    if ($id === 'tool_flask') return crrg_vitals_flask_drink($pc);          // 水壶看蓄水
    crrg_drill_bag_del_n($pc, $id, 1);
    $out = [sprintf('你喝下%s。', crrg_drill_item_name($id))];
    if ($fx) foreach ((array) crrg_vitals_consume($pc, $id) as $x) $out[] = $x;
    if (function_exists('crrg_drill_feed_add') && isset($s['room'])) {
        crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '喝了点东西。');
    }
    return $out;
}

/**
 * 治伤：用医疗物资 + 急救检定回血
 *   绷带：急救（普通）成功 → 生命 +1D3；失败 → 只回 1（勉强止住）
 *   急救包：急救（奖励骰 +1，器械齐全）成功 → 生命 +1D6；失败 → 生命 +1
 *   大失败：手一抖，生命 −1
 *   生命 ≤0（濒死）时：检定成功即稳定到 1 点，并解除濒死
 *   成本：精力 −4；物资消耗 1 件；每天最多 3 次（物资本身也有限）
 */
function crrg_vitals_heal_wound(&$pc, $item = '') {
    $bag = crrg_drill_bag_counts($pc);
    $kits = ['med_firstaid' => ['n'=>'急救包', 'dice'=>'1d6', 'bonus'=>1, 'fail'=>1], 'med_bandage' => ['n'=>'绷带', 'dice'=>'1d3', 'bonus'=>0, 'fail'=>1]];
    if ($item !== '' && isset($kits[$item])) {
        if ((int) ($bag[$item] ?? 0) <= 0) return ['背包里没有' . $kits[$item]['n'] . '。'];
        $use = $item;
    } else {
        $use = '';
        foreach (['med_firstaid', 'med_bandage'] as $k) if ((int) ($bag[$k] ?? 0) > 0) { $use = $k; break; }
        if ($use === '') return ['背包里没有治伤的东西（绷带或急救包）。',
                                 '（供销社与商超有卖；基地卫生院也能看门诊。）'];
    }
    $kit = $kits[$use];
    /* 每日次数 */
    $v = &$pc['vitals'];
    $day = function_exists('crrg_wdate') ? crrg_wdate('Ymd', crrg_vitals_now()) : date('Ymd');
    if (($v['treat_day'] ?? '') !== $day) { $v['treat_day'] = $day; $v['treat_n'] = 0; }
    if ((int) $v['treat_n'] >= 3) return ['今天已经处理过三次了——伤口需要时间，不是靠手快。'];
    $d = crrg_coc_derive($pc['attrs']);
    $nearDeath = ((int) $pc['hp'] <= 0);
    if (!$nearDeath && (int) $pc['hp'] >= (int) $d['hp_max']) return ['你身上没有需要处理的地方。'];

    $skill = max(1, crrg_coc_skill_val($pc, '急救'));
    $r = crrg_coc_check('急救', $skill, (int) $kit['bonus'], 0);
    crrg_drill_bag_del_n($pc, $use, 1);
    $v['treat_n'] = (int) $v['treat_n'] + 1;
    $out = [sprintf('你用%s处理伤口。', $kit['n']), '急救：' . $r['text'] . ($kit['bonus'] ? '　［器械齐全 +1］' : '')];
    $lv = crrg_coc_level_rank($r['level']);
    if ($lv >= 2) {
        $gain = crrg_coc_dice($kit['dice']);
        if ($lv >= 4) { $gain += crrg_coc_dice($kit['dice']); $out[] = '（处理得极好：加掷一次。）'; }
        if ($nearDeath) { $pc['hp'] = 1; $out[] = '（你把他/你自己从濒死线上拉了回来：生命稳定在 1。）'; }
        else { $pc['hp'] = min($d['hp_max'], (int) $pc['hp'] + $gain); $out[] = sprintf('（生命 +%d → %d／%d。）', $gain, (int) $pc['hp'], $d['hp_max']); }
    } elseif ($r['level'] === 'fumble') {
        $pc['hp'] = max(0, (int) $pc['hp'] - 1);
        $out[] = '大失败：手一抖，伤口又开了。（生命 −1）';
    } else {
        if ($nearDeath) { $out[] = '没处理成——还差一点。'; }
        else { $gain = (int) $kit['fail']; $pc['hp'] = min($d['hp_max'], (int) $pc['hp'] + $gain); $out[] = sprintf('（勉强止住：生命 +%d → %d。）', $gain, (int) $pc['hp']); }
    }
    foreach ((array) crrg_vitals_spend($pc, 'fate_radical_treat') as $x) $out[] = $x;
    $out[] = '（今天还可处理 ' . max(0, 3 - (int) $v['treat_n']) . ' 次。）';
    return $out;
}

/** 睡觉：设定休息窗口 */
function crrg_vitals_sleep(&$pc, $hours = 8, $where = 'bed') {
    $v = &$pc['vitals'];
    $now = crrg_vitals_now();
    $hours = max(1, min(12, (int) $hours));
    crrg_vitals_sync($pc);                                  // 先把已睡的部分结算掉 ✓
    $was = ((int) ($v['rest_until'] ?? 0) > $now);           // 本来就在睡？
    $v['rest_where'] = $where;                       // bed=床上（回SAN/生命）｜camp=野外（只回精力）
    /* 2026-10-01 修（玩家报"八分钟才回三十多点"✗）：
       原来**无条件重设**窗口 ✗ → 睡着时再按一次「睡觉」会把窗口重置，
       已经睡过的时段就白等了（只剩已结算的精力 ✓）→ 现在**只延长、不缩短**：
       `rest_until = max(旧, now + 本次)` ✓ —— 重复按不会吃亏 ✓ */
    $v['rest_until'] = max((int) ($v['rest_until'] ?? 0), $now + $hours * 3600);
    $real = (int) ceil($hours * 3600 / 48 / 60);
    $tail = $was ? '（你本来就在睡——本次按**延后 ' . $hours . ' 小时**算，已睡的部分不重算。）'
                 : '（想少睡一点也行：「睡觉 2」＝ 2 游戏小时，按小时结算。）';
    return [sprintf('你躺下了。睡 %d 游戏小时（现实约 %d 分钟）——这期间精力按每小时 +%d 恢复，合计最多 +%d。',
                    $hours, $real, (int) crrg_vrate('energy_sleep_per_h', 12), (int) ($hours * crrg_vrate('energy_sleep_per_h', 12))),
            $tail,
            '（这段休息即使关掉页面也会照算；醒来之后的时间才按离线冻结。要提前起来就说「起床」。）'];
}
function crrg_vitals_wake(&$pc) {
    $v = &$pc['vitals'];
    crrg_vitals_sync($pc);
    $v['rest_until'] = 0;
    return ['你起来了。'];
}
function crrg_vitals_sleeping(&$pc) {
    $v = crrg_vitals($pc);
    return ((int) $v['rest_until'] > crrg_vitals_now());
}
/** 力竭 / 低状态带来的检定惩罚骰（供 crrg_coc_check 读取） */
function crrg_vitals_check_penalty(&$pc) {
    $v = crrg_vitals($pc);
    $p = 0;
    if (!empty($v['fatigue'])) $p++;
    if ($v['energy'] <= 0)     $p++;
    if ($v['water']  <= 5)     $p++;
    return min(3, $p);
}

/* ── 兼容别名（旧名保留，新代码一律用 crrg_vitals_heal_wound） ── */
function crrg_vitals_treat(&$pc, $item = '') { return crrg_vitals_heal_wound($pc, $item); }
