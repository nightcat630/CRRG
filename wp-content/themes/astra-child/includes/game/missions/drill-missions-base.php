<?php
/**
 * 基地任务扩充包（2026-09-28）
 *   ⚠ 2026-10-14：**5 条一次性小任务已删除**，改在引擎版重写 —— 见 `missions/task-base-rewrite.php`
 *     （委员长："老任务该删的都删了，在新引擎以新要求重新写"）
 *   保留：5 条可重复日常（**循环型**，没有前因后果，硬套阶段/线索只会更假 ✗ —— 判断见 16 号文）
 *         1 条中型网状任务 m_mid_gap（并入下一批引擎迁移）
 * 依赖：任务系统（room/flag 步骤、repeat 冷却）、NPC 对话、声望、报告系统。
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () { if (function_exists('crrg_drill_register_mission')) crrg_base_missions_v2(); }, 20);

function crrg_base_missions_v2() {
    $M = [];

    /* ═══════════════ 一、一次性小任务（5） ═══════════════ */


    /* ═══════════════ 二、可重复日常（5，每游戏日一次，带随机） ═══════════════ */

    $M['m_dy_watch'] = [
        'name' => '日常：当班值守（夜）', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'repeat' => 'daily',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'prompt' => '每晚值班室都要有人。守到天亮，把当班记录写满一页。',
        'steps' => [
            ['flag' => 'dy1_值', 'text' => '夜里在作战值班室值守', 'hint_room' => 'b2b_duty'],
            ['flag' => 'dy1_归档', 'text' => '天亮回值班板归档', 'hint_room' => 'b2b_hall'],
        ],
        'reward' => ['credits' => 8, 'money' => 120], 'rep' => ['base' => 1], 'random' => 'dy_watch',
        'settle_note' => '当班记录归档。',
    ];
    $M['m_dy_patrol'] = [
        'name' => '日常：照单巡检', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
        'pass_rooms' => ['b4_buffer', 'b4_main'], 'repeat' => 'daily',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'prompt' => '按巡检单走三个点位，逐项打勾。老活，但没人愿意干。',
        'steps' => [
            ['flag' => 'dy2_点1', 'text' => '点位一：负三层主通道西段', 'hint_room' => 'b3_entry'],
            ['flag' => 'dy2_点2', 'text' => '点位二：收容区主走廊', 'hint_room' => 'b4_main'],
            ['flag' => 'dy2_点3', 'text' => '点位三：回值班板交单', 'hint_room' => 'b2b_hall'],
        ],
        'reward' => ['credits' => 8, 'money' => 140], 'rep' => ['base' => 1], 'random' => 'dy_patrol',
        'settle_note' => '巡检单交回去了。',
    ];
    $M['m_dy_kitchen'] = [
        'name' => '日常：替厨一班', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'repeat' => 'daily',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'prompt' => '陈连生手上有伤，让你顶一班灶。菜单他写好了，照做。',
        'steps' => [
            ['flag' => 'dy3_顶班', 'text' => '在食堂顶班（看菜单）', 'hint_room' => 'b1_canteen'],
            ['flag' => 'dy3_收灶', 'text' => '出完这一餐、收拾灶台', 'hint_room' => 'b1_canteen'],
        ],
        'reward' => ['credits' => 6, 'money' => 110], 'rep' => ['base' => 1], 'random' => 'dy_kitchen',
        'settle_note' => '灶台擦干净了。',
    ];
    $M['m_dy_haul'] = [
        'name' => '日常：出车拉货', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'repeat' => 'daily',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'prompt' => '车库有单子：把东西送到指定的地方，再把回单带回来。看车况与油量。',
        'steps' => [
            ['flag' => 'dy4_领货', 'text' => '到车库领货与单据', 'hint_room' => 'garage_maint'],
            ['flag' => 'dy4_交单', 'text' => '回到车库交回单', 'hint_room' => 'garage_maint'],
        ],
        'reward' => ['credits' => 8, 'money' => 200], 'rep' => ['base' => 1], 'random' => 'dy_haul',
        'settle_note' => '回单交车库，登记入册。',
    ];
    $M['m_dy_repair'] = [
        'name' => '日常：修修补补', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'repeat' => 'daily',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'prompt' => '报修单每天都有。技术研发室门口挂着板子，今天的故障写在上头。',
        'steps' => [
            ['flag' => 'dy5_接单', 'text' => '到技术研发室接报修单', 'hint_room' => 'b2b_tech'],
            ['flag' => 'dy5_交单', 'text' => '修完交单', 'hint_room' => 'b2b_tech'],
        ],
        'reward' => ['credits' => 8, 'money' => 160], 'rep' => ['base' => 1], 'random' => 'dy_repair',
        'settle_note' => '报修单签字归档。',
    ];

    /* ═══════════════ 三、中型网状任务（1） ═══════════════ */

    $M['m_mid_gap'] = [
        'name' => '中型：清单上少了一件', 'level' => '中', 'kind' => 'base', 'min_lv' => 3,
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'deadline_h' => 72,
        'prompt' => '季度盘点：仓库账上少了一件核心件——用于收容廊封条加固。三条线索分别在仓库、值班室、技术研发室。'
                  . '但线头不止三条：账目后面有动机，排班后面有假象，拆件后面有共犯。**3 游戏日内**处置，逾期自动退回。',
        'steps' => [
            ['flag' => '线索_账目',       'text' => '线头一：仓库账目（周德海）'],
            ['flag' => '线索_排班改动',   'text' => '线头二：值班表改动（康明远）'],
            ['flag' => '线索_拆件',       'text' => '线头三：拆件记录（韩雪）'],
            ['flag' => '线索_验证',       'text' => '验证：改动是谁下的、件是给谁用的'],
            ['flag' => '证据_足够',       'text' => '凑够旁证（医疗室／车库／收容廊守卫／值班记录 任选）'],
            ['flag' => '关键物_核心件',   'text' => '拿到那件核心件'],
            ['flag' => '阻挠_已处理',     'text' => '有人来打招呼——把这件事挡回去'],
            ['room' => 'b2b_hall',        'text' => '回值班板，下结论'],
        ],
        'reward' => ['credits' => 10, 'money' => 400], 'rep' => ['base' => 4],
        'settle_note' => '处置定了。清单上那一件，补上去了——或者被划掉了。',
    ];

    foreach ($M as $id => $def) crrg_drill_register_mission($id, $def);
}

/* ═══════════════ 随机结果（日常任务结算时掷） ═══════════════ */
function crrg_daily_outcome($key, &$pc, &$s) {
    $roll = function ($n = 100) { return mt_rand(1, $n); };
    $pick = function (array $xs) use ($roll) { return $xs[$roll(count($xs)) - 1]; };
    $O = function ($lines, $reward = null, $effects = [], $note = '') {
        return ['lines' => (array) $lines, 'reward' => (array) $reward, 'effects' => (array) $effects,
                'note' => $note ?: implode(' ', (array) $lines)];
    };
    switch ($key) {
        case 'dy_watch':
            $r = $roll(6);
            if ($r === 1) return $O(['当班记录：一页，什么都没发生。'], ['credits' => 2, 'money' => 60]);
            if ($r === 2) return $O(['半夜监测网络跳了三次读数，你抄下来了。'], ['credits' => 3, 'money' => 80], [['flag' => '线索_读数跳变']]);
            if ($r === 3) return $O(['凌晨一点有个内线电话，对方不说话，只喘气，四十秒后挂了。'], ['credits' => 2, 'money' => 60], [['flag' => '线索_无声电话'], ['san' => -1]]);
            if ($r === 4) return $O(['驻防区的人来借手电，登记完又还了两节电池。'], ['credits' => 2, 'money' => 70]);
            if ($r === 5) return $O(['警报误报：B3 水下探头。你按下复位，又自己去走廊看了一眼。'], ['credits' => 2, 'money' => 50], [['energy' => -3]]);
            return $O(['你把一个在消防通道里睡觉的人叫了起来——是白班的人。'], ['credits' => 4, 'money' => 100], [['rep' => ['base' => 1]]]);
        case 'dy_patrol':
            $spot = $pick(['负三层主通道西段', '收容区主走廊', 'B2 技术研发室门口', '仓库东侧货位']);
            $f = $pick([
                ['点位全部合格，漆线清楚，灭火器压力正常。', ['credits' => 3, 'money' => 80], []],
                ['有一盏灯不亮——这条线的灯管三个月没换过。', ['credits' => 3, 'money' => 80], [['flag' => '线索_线路老化']]],
                ['地上有积水，从墙缝渗的。你拍了照。', ['credits' => 3, 'money' => 80], [['flag' => '线索_渗水']]],
                ['B4 一扇门的封条松了半边。你顺手按了按——它已经没有粘性了。', ['credits' => 4, 'money' => 90], [['flag' => '线索_封条松了'], ['rep' => ['base' => 1]]]],
                ['门禁读卡器迟滞，同一张卡要贴两次。你写进单子。', ['credits' => 3, 'money' => 80], [['flag' => '线索_门禁漂移']]],
            ]);
            return $O(array_merge(['巡检路线：' . $spot . ' → 值班板。'], [$f[0]]), $f[1], $f[2]);
        case 'dy_kitchen':
            $menu = $pick(['西红柿打卤面', '炖菜配馒头', '大锅菜饭', '羊肉面片']);
            $q = $roll(100);
            if ($q <= 5)  return $O(['今天做的是' . $menu . '。你尝了口——咸得发苦。'], ['credits' => 1, 'money' => 30], [['rep' => ['base' => -1]]]);
            if ($q <= 60) return $O(['今天做的是' . $menu . '。收摊时锅底剩了半勺，说明还行。'], ['credits' => 2, 'money' => 60], [['rep' => ['base' => 1]]]);
            if ($q <= 90) return $O(['今天做的是' . $menu . '。有人添了第二次。'], ['credits' => 3, 'money' => 90], [['rep' => ['base' => 1]]]);
            return $O(['今天做的是' . $menu . '。晚饭前锅就空了——有人问明天还是不是你。'], ['credits' => 4, 'money' => 120], [['rep' => ['base' => 2]]]);
        case 'dy_haul':
            $jobs = [['李家坳', 8, 60], ['青石镇', 22, 140], ['白石县', 56, 260], ['柳河镇', 41, 200]];
            $j = $pick($jobs);
            $lines = ['货装好了。单子写的是：' . $j[0] . '，' . $j[1] . ' 公里。'];
            $eff = [['rep' => ['base' => 1]]];
            if ($roll(100) <= 30) { $lines[] = '回程走了段土路，车厢里哐了一路。'; $eff[] = ['wear' => 6]; }
            $lines[] = '回单交给车库，登记入册。';
            return $O($lines, ['credits' => 3, 'money' => $j[2]], $eff);
        case 'dy_repair':
            $faults = [
                ['水源间的加压泵', '机械维修', '泵体密封老化，你换了一圈垫片。'],
                ['B1 应急发电机', '电气维修', '接触器积碳。你刮干净，顺手紧固了端子。'],
                ['B3 照明回路', '电气维修', '两处接头进了潮气。你重做了绝缘。'],
                ['收容区门禁', '电气维修', '读卡器与主控时间不同步，你对了一次表。'],
            ];
            $f = $pick($faults);
            $skill = $f[1];
            $val = function_exists('crrg_coc_skill_val') ? max(1, crrg_coc_skill_val($pc, $skill)) : 30;
            $chk = crrg_coc_check($skill, $val);
            $ok = crrg_coc_level_rank($chk['level']) >= 2;
            $lines = ['报修单上写着：' . $f[0] . '。', $skill . '：' . $chk['text']];
            $lines[] = $ok ? $f[2] : '没弄好——你写了"待复检"，把工具收回箱子。';
            return $O($lines, ['credits' => $ok ? 3 : 1, 'money' => $ok ? 90 : 30], $ok ? [['rep' => ['base' => 1]]] : []);
    }
    return $O(['（没有特殊结果。）'], ['credits' => 1, 'money' => 0]);
}

/* ═══════════════ 中型任务：调查点与三种处置 ═══════════════ */
/** 调查点：房间 → 关键词 → [旗标, 文本…]（含检定关卡与支线） */
function crrg_mid_gap_survey(&$s, &$pc, $arg) {
    $room = $s['room'] ?? '';
    /* 主线三条，各带一个检定关卡 */
    $checks = [
        'b3_warehouse' => ['账' => ['skill' => '会计', 'flag' => '线索_账目',
            'ok'   => ['你把手写台账和大系统的入库记录逐条对齐。', '少的那一件：三月入库有、出库栏空——但领用单上盖了章，章是真的。', '（线索一：账目对不上，且有人走了正规领用手续。）'],
            'fail' => ['数字太多了，你越对越乱。', '（没看出名堂，可以再来，或者找人帮忙。）']]],
        'b2b_duty'     => ['排班' => ['skill' => '侦查', 'flag' => '线索_排班改动',
            'ok'   => ['排班表压在玻璃板下。改动用的笔和原本不是一支，笔压更重。', '被改的不是名字，是把一个名字后面的括号划掉了——括号里写的是"休假"。', '（线索二：有人在替班记录上做了手脚。）'],
            'fail' => ['玻璃板下的纸翻了两遍，你只看出"改过"，看不出改了什么。']]],
        'b2b_tech'     => ['拆件' => ['skill' => '电气维修', 'flag' => '线索_拆件',
            'ok'   => ['拆件记录最后三行是铅笔写的，没签名。', '三行里两行划掉，第三行：核心件 ×1 —— 用于 B4 加固。', '（线索三：件是被拆走的，理由写的是"加固"。）'],
            'fail' => ['型号签上的字太小，你认不全——得懂行的人来看。']]],
    ];
    if (isset($checks[$room])) {
        foreach ($checks[$room] as $kw => $c) {
            if ($arg !== '' && mb_strpos($arg, $kw) === false && mb_strpos($kw, $arg) === false) continue;
            if (crrg_seal_has($s, $c['flag'])) return ['（这条你已经查过了。）'];
            $val = max(1, crrg_coc_skill_val($pc, $c['skill']));
            $r = crrg_coc_check($c['skill'], $val);
            if (crrg_coc_level_rank($r['level']) >= 2) { crrg_seal_set($s, $c['flag']); return array_merge($c['ok'], [$c['skill'] . '：' . $r['text']]); }
            return array_merge([$c['skill'] . '：' . $r['text']], $c['fail']);
        }
    }
    /* 验证：识破"排班改动"的真假（要有线索二） */
    if ($room === 'garage_maint' && (mb_strpos($arg, '里程') !== false || mb_strpos($arg, '车') !== false)) {
        if (!crrg_seal_has($s, '线索_排班改动') && !crrg_seal_has($s, '线索_拆件')) return ['（你手上还没有能核的东西。）'];
        if (crrg_seal_has($s, '线索_验证')) return ['（核过了。）'];
        crrg_seal_set($s, '线索_验证');
        return ['车库的出车登记簿上，那三个夜里各有一条出车记录，目的地都写"B4 转运"。',
                '签字的人不是值班员，是一个你在这个月排班表上见过的名字。',
                '（验证：排班表上的"休假"是编的，那三个夜里有人上过班——且用公车去过 B4。）'];
    }
    /* 支线四：动机与旁证（每条 +1 证据，攒够 4 条 → 证据_足够） */
    $side = [
        'b1_med'     => ['病历' => ['证据_病历', ['白大夫把登记本推给你，指着两行字：「他爱人在县医院，化疗第三个疗程。」',
                                                  '「他没报困难补助。上个月我劝过——他说排班不能空。」', '（旁证一：动机。）']]],
        'b4_main'    => ['守卫' => ['证据_守卫', ['收容廊的守卫挠头：「B4 那几扇门，上个月就有一扇合不严。」',
                                                  '「报修单我交过，走到技术研发室就没了下文——然后我就看见有人拿件来加固。」', '（旁证二：需求真实存在。）']]],
        'b1_archive' => ['记录' => ['证据_记录', ['你翻了上个月的夜班签字联：有三个夜里的签名是同一个笔迹，写得很轻。',
                                                  '对应的岗位栏写着"替班"，没有姓名。', '（旁证三：替班没入册。）']]],
        'b2b_intel'  => ['比对' => ['证据_比对', ['情报分析室的人帮你把领用单与出车记录叠在一张表上：',
                                                  '时间、车次、目的地全对得上。这不是临时起意，是有计划的。', '（旁证四：不是一时冲动。）']]],
    ];
    if (isset($side[$room])) {
        foreach ($side[$room] as $kw => $d) {
            if ($arg !== '' && mb_strpos($arg, $kw) === false && mb_strpos($kw, $arg) === false) continue;
            if (crrg_seal_has($s, $d[0])) return ['（这条已经问过了。）'];
            crrg_seal_set($s, $d[0]);
            $n = count(preg_grep('/^证据_/', (array) $s['flags']));
            $out = array_slice($d, 1);
            if ($n >= 4) { crrg_seal_set($s, '证据_足够'); $out[] = '（旁证够了——这件事不是一个人的事。）'; }
            return $out;
        }
    }
    return null;
}

/** 阻力：查到一定深度，周德海来找你（在仓库或走廊） */
function crrg_mid_gap_pressure(&$s, &$pc, $mode = '') {
    $flags = (array) ($s['flags'] ?? []);
    $core = count(array_intersect(['线索_账目', '线索_排班改动', '线索_拆件', '线索_验证'], $flags));
    if ($core < 3) return ['（还没查到有人需要跟你打招呼的程度。）'];
    if (in_array('阻挠_已处理', $flags, true)) return ['（话已经说过了。）'];
    $setFlag = function ($f) use (&$s) { if (!is_array($s['flags'] ?? null)) $s['flags'] = []; if (!in_array($f, $s['flags'], true)) $s['flags'][] = $f; };
    if ($mode === '') {
        return ['周德海在走廊尽头等你。他没穿工装，手里捏着一支烟，没点。',
                '「那件东西是我拿的。」他说得很直接，「账我自己补，你当没看见。」',
                '（怎么回他：「交涉 收下人情」／「交涉 拒绝」／「交涉 套话」。）'];
    }
    if ($mode === '收下人情') {
        crrg_npc_fav_add($pc, '周德海', 2); $setFlag('阻挠_已处理'); $setFlag('人情_周德海');
        return ['你把话咽了回去：「我什么都没看见。」', '他点点头，把烟塞回口袋，走了。',
                '（他记你这份人情。封条这件事，他会自己想办法。）'];
    }
    if ($mode === '拒绝') {
        crrg_npc_fav_add($pc, '周德海', -2); $setFlag('阻挠_已处理'); $setFlag('立场_不留情');
        return ['「我按规矩办。」你说。', '他看了你三秒钟，笑了一下，说行——然后走了。',
                '（这件事没有回旋了。）'];
    }
    if ($mode === '套话') {
        $val = max(1, crrg_coc_skill_val($pc, '说服'));
        $r = crrg_coc_check('说服', $val);
        $out = ['你问他：「为什么不用正规报修？」', '说服：' . $r['text']];
        if (crrg_coc_level_rank($r['level']) >= 2) {
            crrg_npc_fav_add($pc, '周德海', 1); $setFlag('阻挠_已处理'); $setFlag('证据_动机口供');
            $out[] = '「报修单交上去，走到第三道就变成“设备老化、暂缓”。」他把烟捏扁了。';
            $out[] = '「门合不严的那几天，我值夜班。」';
            $out[] = '（拿到口供——他承认的是"为什么"，不是"拿了什么"。）';
            $n = count(preg_grep('/^证据_/', (array) $s['flags']));
            if ($n >= 4) { $setFlag('证据_足够'); $out[] = '（旁证够了。）'; }
        } else {
            $out[] = '他摇头：「你别问了。」然后走了。';
            $out[] = '（话没套出来，但他记着你在探。）';
            crrg_npc_fav_add($pc, '周德海', -1); $setFlag('阻挠_已处理');
        }
        return $out;
    }
    return ['（用法：「交涉 收下人情」／「交涉 拒绝」／「交涉 套话」。）'];
}

/** 关键物：核心件（只在技术研发室，且已有拆件线索时可取） */
function crrg_mid_gap_take_key(&$s, &$pc) {
    if (!in_array('线索_拆件', (array) ($s['flags'] ?? []), true)) return ['（你还没有那份拆件记录，不知道去哪个柜子找。）'];
    if (in_array('关键物_核心件', (array) ($s['flags'] ?? []), true)) return ['（那件东西已经在你身上了。）'];
    if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
    $s['flags'][] = '关键物_核心件';
    return ['最下层的抽屉里，一个油纸包。拆开：金属件，一掌长，边缘是新磨的。',
            '（拿到关键物：核心件。）'];
}
/** 中型任务的处置选项（面板与指令共用） */
function crrg_mid_gap_choices($pc) {
    return [
        'A' => ['n' => '按纪律上报（写在值班记录里）', 'd' => '干净、快、但有人要担责。'],
        'B' => ['n' => '压下，自己帮着补上（需 镇物封条 ×1）', 'd' => '没人担责，你出东西。'],
        'C' => ['n' => '找韩雪一起写成《设备老化报告》走流程', 'd' => '慢，但把洞写进制度。'],
        'D' => ['n' => '什么都不做，把单子退回值班板', 'd' => '这一件就这么没了。'],
    ];
}
function crrg_mid_gap_resolve(&$s, &$pc, $key) {
    $key = strtoupper(substr(trim($key), 0, 1));
    $flags = (array) ($s['flags'] ?? []);
    $has = fn($f) => in_array($f, $flags, true);
    $need = ['线索_账目', '线索_排班改动', '线索_拆件', '线索_验证', '阻挠_已处理', '关键物_核心件'];
    $miss = array_values(array_diff($need, array_intersect($need, $flags)));
    if ($key === '') return ['（处置方式：「处置 A」上报 ／ B 压下并补 ／ C 走流程 ／ D 退回。）'];
    if ($miss && $key !== 'D') return array_merge(['还不到下结论的时候。'], ['（缺：' . implode('、', $miss) . '）']);
    $proof = count(preg_grep('/^证据_/', $flags));
    $soft  = $has('人情_周德海');
    $hard  = $has('立场_不留情');
    $setF = function ($f) use (&$s) { if (!is_array($s['flags'] ?? null)) $s['flags'] = []; if (!in_array($f, $s['flags'], true)) $s['flags'][] = $f; };
    $fav  = function ($npc, $d) use (&$pc) { if (!isset($pc['npc_fav']) || !is_array($pc['npc_fav'])) $pc['npc_fav'] = []; $pc['npc_fav'][$npc] = (int) ($pc['npc_fav'][$npc] ?? 0) + $d; };
    switch ($key) {
        case 'A':
            $setF('结局_上报');
            crrg_npc_fav_add($pc, '周德海', -3); crrg_npc_fav_add($pc, '韩雪', 1);
            if ($proof >= 4) {
                $setF('结局_上报_轻');
                crrg_rep_add($pc, 'base', 8, '中型任务：上报（证据完整）');
                $pc['credits'] = (int) $pc['credits'] + 12; $pc['money'] = (int) $pc['money'] + 500;
                return ['你把三份记录、领用单、出车登记与四份旁证一起交上去。',
                        '处理结果是「违规领用、情节轻微、记过」——他被留下了，代价是今年不能评优。',
                        '白大夫那天路过你身边，说了句「他爱人还在县医院」。',
                        '（基地声望 +8 ｜ +12 积分 ｜ +500 元 ｜ 旗标：结局_上报·轻）'];
            }
            $setF('结局_上报_重');
            crrg_rep_add($pc, 'base', 6, '中型任务：上报（证据不足）');
            $pc['credits'] = (int) $pc['credits'] + 10; $pc['money'] = (int) $pc['money'] + 400;
            return ['你只交了手上有的一半——三条线索加那件东西，旁证没凑齐。',
                    '程序照走。他被调离了收容廊，去了后勤。走之前把工牌交了。',
                    '（基地声望 +6 ｜ +10 积分 ｜ +400 元 ｜ 旗标：结局_上报·重）'];
        case 'B':
            if (crrg_drill_bag_count($pc, 'key_sigil') <= 0) return ['（没有可贴的封条——这条路走不通。想要别的走法就选 A 或 C。）'];
            crrg_drill_bag_del_n($pc, 'key_sigil', 1);
            $setF('结局_私下补件'); crrg_npc_fav_add($pc, '周德海', 3); $setF('钩子_封条不够');
            $rep = $proof >= 4 ? 3 : 2;
            crrg_rep_add($pc, 'base', $rep, '中型任务：压下并补上');
            $pc['credits'] = (int) $pc['credits'] + 6; $pc['money'] = (int) $pc['money'] + 200;
            $out = ['你把单子撤了，夜里带着封条去了 B4，把那扇门重新加固了一遍。',
                    '周德海后来自己把账平了。他跟你说话时，语气像同事，不像欠债的。'];
            if ($proof >= 4) $out[] = '（你留了自己那份旁证没扔——不是防他，是防下一次。）';
            $out[] = '（基地声望 +' . $rep . ' ｜ +6 积分 ｜ +200 元 ｜ 消耗封条 ×1 ｜ 旗标：结局_私下补件）';
            return $out;
        case 'C':
            $setF('结局_走流程');
            crrg_npc_fav_add($pc, '韩雪', 2);
            $tip = $has('人情_周德海') || $has('证据_动机口供')
                 ? '报告里，你和他都同意把"备件申请流程"这一条单列出来。'
                 : '报告里只有型号、缺口与风险，没有名字。';
            crrg_rep_add($pc, 'base', 5, '中型任务：走流程');
            $pc['credits'] = (int) $pc['credits'] + 9; $pc['money'] = (int) $pc['money'] + 320;
            $setF('钩子_备件流程提案');
            return ['你和韩雪把三条线索、旁证和型号整理成《设备老化与备件缺口报告》。',
                    $tip,
                    '一周后，值班板的“备件加急”一栏多了一张新表格——这张表是从你们那份报告里长出来的。',
                    '（基地声望 +5 ｜ +9 积分 ｜ +320 元 ｜ 旗标：结局_走流程）'];
        case 'D':
            $setF('结局_退回');
            return ['你把单子原样退回值班板，写上"证据不足"。',
                    '清单上那一件，还是空着。',
                    '（回收站里那扇门的封条，还是松的。）'];
    }
    return ['（处置方式：A 上报 ／ B 压下并补 ／ C 写报告 ／ D 退回。）'];
}

/** 把随机结果的效果套到卡上（reward 由调用方合并） */
function crrg_daily_apply_effects(&$pc, &$s, array $eff, array &$out) {
    foreach ($eff as $e) {
        foreach ((array) $e as $k => $v) {
            if ($k === 'rep' && function_exists('crrg_rep_add')) { foreach ((array) $v as $loc => $d) crrg_rep_add($pc, $loc, (int) $d, '日常任务'); }
            elseif ($k === 'san') { $pc['san'] = max(0, (int) $pc['san'] + (int) $v); }
            elseif ($k === 'energy') { $pc['vitals']['energy'] = max(0, (float) $pc['vitals']['energy'] + (float) $v); }
            elseif ($k === 'wear' && function_exists('crrg_travel_veh')) { $m = crrg_travel_mode($pc); $vv = crrg_travel_veh($pc, $m); $vv['wear'] = min(100, (float) $vv['wear'] + (float) $v); crrg_travel_save_veh($pc, $m, $vv); }
            elseif ($k === 'flag') { if (!is_array($s['flags'] ?? null)) $s['flags'] = []; if (!in_array($v, $s['flags'], true)) $s['flags'][] = (string) $v; }
        }
    }
}

/* ══════════ 当场动作（给新任务以明确入口） ══════════ */
function crrg_base_mission_acts() {
    /* 结构：任务id => 房间 => [ [按钮文字, 旗标, [文本…], 需夜间?], … ]（同一房间可放多个） */
    return [
        'm_st_lastwatch' => [
            'b2b_duty' => [
                ['接班（从康明远手里接过钥匙串）', 'st1_接班',
                 ['康明远把钥匙串从腰上解下来，一件件点给你：「一号仓、廊门、还有这个——备件柜。」',
                  '「今晚我不在。你按规程走就行。」']],
                ['交班（天亮交接）', 'st1_交班',
                 ['天亮时他回来了，没直接进来，先站在门口看了看那本记录。',
                  '「写得比我细。」他说。']],
            ],
            'b4_main' => [
                ['巡夜（替他走一趟收容区）', 'st1_巡夜',
                 ['你沿着收容区主走廊走。十一条门后有十一声安静，只有 C2 西头那扇在轻轻响。',
                  '你在值班记录上写下：正常。']],
            ],
        ],
        'm_st_ninth' => [
            'b1_canteen' => [
                ['点数、看菜单', 'st2_点数',
                 ['今天的菜单贴在窗口上：西红柿打卤面。值班餐八份，锅里是九份。',
                  '「多的这份没人认。」陈连生把单子推给你。']],
                ['回食堂交差', 'st2_交差',
                 ['你把结论说给陈连生听。他点点头：「下回我多蒸两个馒头就行。」']],
            ],
            'b2f_gym' => [
                ['问有谁加训', 'st2_加训',
                 ['体能训练室里有两个人加训，都说没多点餐。',
                  '「你问问值班室——夜班是不是多了一个人。」']],
            ],
        ],
        'm_st_water' => [
            'b3_entry' => [
                ['听水声（需夜间）', 'st3_听声',
                 ['脚步声在管道里来回撞。你停下来，屏住呼吸。',
                  '——有水声，很轻，是从西段**那面墙后**传来的；可地面是干的。',
                  '（墙那边就是水源净化设备间——先去那儿比对读数。）'], 1],
            ],
            'b3_water' => [
                ['关阀比对读数', 'st3_关阀',
                 ['你把水源间的主阀关了半圈，读数立刻变了：水压正常、流量正常。',
                  '「管道没问题。」你在心里说，「那就是别的东西。」']],
            ],
            'b1_office' => [
                ['翻十年前工牌档', 'st3_旧档',
                 ['十年前那批职员的工牌档里，有一张背面写着字：「别让他们把水断掉。」',
                  '签名栏是一个已经注销的编号。']],
            ],
            'b2b_hall' => [
                ['写记录（交差）', 'st3_记录',
                 ['记录写完了：一行是管道，一行不是。', '值班员看了一眼，没问第二句。']],
            ],
        ],
        'm_st_cigs' => [
            'b2f_armory' => [
                ['取包裹', 'st4_取包',
                 ['军械维护室的人从柜子底下摸出一个油纸包：「老周让你来拿的？」',
                  '「别写单子上。」他补了一句。']],
            ],
            'b2f_gear' => [
                ['问是谁让带的', 'st4_问人',
                 ['整备室的老魏看了一眼包裹：「这东西不是给老周的。」',
                  '「他替别人带的——C2 那边按规矩要供点东西。」']],
            ],
            'b3_warehouse' => [
                ['交给老周', 'st4_交包', ['老周接过去的时候耳朵有点红。', '「下次我自己去。」他说。']],
            ],
        ],
        'm_st_roster' => [
            'b1_archive' => [
                ['调值班表底稿', 'st5_底稿',
                 ['底稿上写着周四执勤长的名字被涂掉了两次。涂得很干净，但纸背有压痕。']],
            ],
            'b2b_intel' => [
                ['交叉比对', 'st5_比对',
                 ['情报分析室的人把底稿与人员名录叠在一起：那个人**从头到尾不在名录里**。']],
            ],
            'b2b_comm' => [
                ['查通讯登记', 'st5_登记',
                 ['通讯室的登记簿上，那个岗位在三个夜里各通过一次内线，通话都在两分钟以内。']],
            ],
            'b2b_hall' => [
                ['写记录（交差）', 'st5_记录',
                 ['你写下《值班表异常人员项》，把它挂进待核实栏。', '值班员看了一眼那行字，什么也没说。']],
            ],
        ],
        'm_dy_watch' => [
            'b2b_duty' => [['夜里值守', 'dy1_值', ['你拉开椅子坐下，把当班记录翻到新的一页。'], 1]],
            'b2b_hall' => [['天亮归档', 'dy1_归档', ['你把记录归档，签了名。']]],
        ],
        'm_dy_patrol' => [
            'b3_entry' => [['点位一打勾', 'dy2_点1', ['点位一：漆线清楚，无积水。']]],
            'b4_main'  => [['点位二打勾', 'dy2_点2', ['点位二：廊门贴痕正常，灭火器压力正常。']]],
            'b2b_hall' => [['回值班板交单', 'dy2_点3', ['巡检单交回去了。']]],
        ],
        'm_dy_kitchen' => [
            'b1_canteen' => [
                ['顶班看菜单', 'dy3_顶班', ['菜单挂在窗口：今天这一餐，你来。']],
                ['收拾灶台', 'dy3_收灶', ['灶台擦干净了。']],
            ],
        ],
        'm_dy_haul' => [
            'garage_maint' => [
                ['领货与单据', 'dy4_领货', ['车库把货与回单一起给你，顺手看了眼油表。']],
                ['交回单', 'dy4_交单', ['回单交车库，登记入册。']],
            ],
        ],
        'm_dy_repair' => [
            'b2b_tech' => [
                ['接报修单', 'dy5_接单', ['报修板上挂着今天的单子，故障大类写在最上面。']],
                ['修完交单', 'dy5_交单', ['报修单签字归档。']],
            ],
        ],
    ];
}
