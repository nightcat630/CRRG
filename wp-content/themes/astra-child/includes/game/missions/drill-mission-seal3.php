<?php
/**
 * 中型任务《封条》· 第 3 轮（Act II 中段：徒弟、冒充者、暗账、夜间）
 *   四条新线：① 走掉的徒弟（田小满） ② 冒充者摊牌 ③ 信用社暗账 ④ 夜间上山（守夜）
 *   ＋ 第一起**时间压力事件**：老匠人中毒发作（由封条库存天数／游戏日推进触发）
 *   机制：crrg_seal_daily_decay()（每天扣 1 天库存）＋ crrg_seal3_events()（阈值事件）
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    /* 2026-10-14：《封条》已下线（委员长：整个不要了）——只关注册，函数与存档都留着 ✓
       恢复办法：把 missions/_retired.php 里的 crrg_retired_seal() 改成 return true ✓ */
    if (function_exists('crrg_retired_seal') && !crrg_retired_seal()) return;
    if (!function_exists('crrg_drill_register_mission')) return;
    crrg_drill_register_mission('m_mid_seal3', [
                'pass_rooms' => ['b4_main', 'tw_homes', 'tw_bank', 'tw_hillpath', 'tw_junk', 'tw_paper', 'tw_clinic'],
        'name' => '封条', 'level' => '中', 'kind' => 'npc', 'min_lv' => 3, 'hidden' => true, 'chain_no' => 3, 'chain_total' => 5, 'chain_next' => 'm_mid_seal4',
        'scene' => 'town', 'brief_room' => 'tw_townhall', 'brief_name' => '镇政府',
        'depart_room' => 'b2b_hall', 'entry_room' => 'tw_main_e',
        'deadline_h' => 96,
        'prompt' => '料不止你一个人要。盯上这条线的人不姓常，也不住在镇上——先弄清楚是谁在收、钱从哪来。',
        'steps' => [
            ['flag' => 's3_邻居',   'text' => '居民区：问出徒弟去了哪儿'],
            ['flag' => 's3_徒弟账', 'text' => '拿到徒弟的账（守夜／信用社／堵人）'],
            ['flag' => 's3_摊牌',   'text' => '和"县里来的人"摊牌'],
            ['flag' => 's3_暗账',   'text' => '信用社流水（需声望或镇文书的关系）'],
            ['flag' => 's3_守夜',   'text' => '后山守一夜'],
            ['flag' => 's3_中毒事件','text' => '处理老匠人中毒发作'],
            ['flag' => 's3_结论',   'text' => '把徒弟、冒充者、暗账三条并到一起'],
            ['room' => 'tw_townhall','text' => '回镇政府，准备回基地'],
        ],
        'reward' => ['credits' => 15, 'money' => 550], 'rep' => ['town_qingshi' => 3],
        'settle_note' => '你手上有账、有口供，还有一个等不起的人。',
    ]);
}, 25);

/* ══════════ 库存按游戏日衰减 + 阈值事件 ══════════ */
function crrg_seal_daily_decay(&$pc) {
    if (!function_exists('crrg_seal_inventory')) return 0;
    crrg_seal_inventory($pc);
    $today = function_exists('crrg_wdate') ? crrg_wdate('Ymd', crrg_vitals_now()) : date('Ymd');
    /* 2026-09-29 改：按**在线游戏日**衰减（离线不计）——否则玩家外出几天，库存自己掉到 0 */
    if (!function_exists('crrg_played_sec')) return 0;
    $playedDay = (int) floor(crrg_played_sec($pc) * 48 / 86400);        // 在线折合的游戏日
    $last = array_key_exists('last_played_day', (array) $pc['seal']) ? (int) $pc['seal']['last_played_day'] : -1;
    $pc['seal']['last_played_day'] = $playedDay;
    if ($last < 0 || $playedDay <= $last) return 0;                     // 首次记录 / 同一日内
    $used = $playedDay - $last;
    /* 2026-10-01 修（玩家报"刚接任务库存就空了"✗）：
       原来一次能把**任意多天**一次扣掉 ✗（在线时长累计跳变／时钟异常时会瞬间清空 ✓），
       而且**不记进 `used`** ✗ → 账面上看不出来 ✓。现在：
         · 单次最多按 **2 天**计 ✓（跳变再大也只按 2 天算 ✓）
         · **照实记账** ✓（`used` 累加 ✓ 看得见 ✓） */
    $used = min(2, max(0, (int) $used));
    $pc['seal']['stock_days'] = max(0, (int) $pc['seal']['stock_days'] - $used);
    $pc['seal']['used'] = (int) ($pc['seal']['used'] ?? 0) + $used;
    return $used;
}
/** 阈值事件：库存 ≤6 → 老匠人倒下；≤3 → C2 门告警（留给第 4 轮） */
function crrg_seal3_events(&$s, &$pc, &$out = []) {
    if (!function_exists('crrg_seal_inventory')) return $out;
    $inv = crrg_seal_inventory($pc);
    $day = (string) $inv['stock_days'];
    if ((int) $inv['stock_days'] <= 6 && !crrg_seal_has($s, 's3_中毒事件')) {
        crrg_seal_set($s, 's3_中毒事件');
        $out[] = '※ 镇上传来消息：**常守业在铺子里晕倒了**。手抖得端不住浆糊碗，人是从柜台后面抬出来的。';
        $out[] = '（要处理：「处置中毒 送医」／「处置中毒 先问料」／「处置中毒 带回基地」。）';
    }
    if ((int) $inv['stock_days'] <= 3 && !crrg_seal_has($s, 's3_门告警')) {
        crrg_seal_set($s, 's3_门告警');
        $out[] = '※ 基地来电话：**C2 西头那扇门又一次松了**。值班室问你还差几天。';
        $out[] = '（这条留给下一阶段处理。）';
    }
    return $out;
}

/* ══════════ 第 3 轮：调查点 ══════════ */
function crrg_seal3_survey(&$s, &$pc, $arg) {
    $rows = [
        'tw_homes' => [
            ['kw' => '邻居', 'flag' => 's3_邻居', 'texts' => [
                '邻居把门开了一条缝：「田小满啊？早走了，说是去县里送货。」',
                '「他娘还在这儿住。药是他在外面挣的。」',
                '（线索：徒弟去了县里，靠送货挣钱。）']],
        ],
        'tw_bank' => [
            ['kw' => '流水', 'flag' => 's3_暗账',
             'gate' => ['rep' => ['town_qingshi', 10], 'alt_flag' => 'paper_绕过',
                'refuse' => function ($s2, $pc2) {
                    $rep = function_exists('crrg_rep_of') ? (int) crrg_rep_of($pc2, 'town_qingshi') : 0;
                    return ['柜员头也不抬：「查流水要有手续。」',
                            '（镇上声望不够（现 ' . $rep . '），也没有镇文书的关系——先在镇上攒点人情，或者先跟他聊出别的路子。）'];
                }],
             'lead' => function ($s2, $pc2) {
                $rep = function_exists('crrg_rep_of') ? (int) crrg_rep_of($pc2, 'town_qingshi') : 0;
                return $rep >= 10 ? '柜员认出你，没多问就把流水推了过来。' : '你提了镇文书的名字，柜员迟疑了一下，还是把本子翻开了。';
             },
             'texts' => [
                '柜员把流水推过来——今年有三笔大额现金进项，收款人一栏写着「田小满」。',
                '三笔的时间，和废品站收铅皮的日子只差一两天。',
                '（线索：暗账——徒弟是收款人，钱从"县里"来。）'],
             'post' => 'crrg_seal3_conclude'],
        ],
        'tw_hillpath' => [
            ['kw' => '车辙', 'flag' => 's3_车辙', 'texts' => [
                '摩托的车辙比上回深——驮过东西。',
                '往老陵方向的那一段，多了两处新脚印，鞋底纹路很细。',
                '（线索：有人持续在这条路上运东西。）']],
        ],
    ];
    return function_exists('crrg_seal_survey_engine') ? crrg_seal_survey_engine($s, $pc, $arg, $rows) : null;
}

function crrg_seal3_nightwatch(&$s, &$pc) {
    $clock = function_exists('crrg_world_clock') ? crrg_world_clock() : ['night' => false, 'phase' => ''];
    if (empty($clock['night'])) return ['（守夜要等入夜。现在是' . ($clock['phase'] ?? '白天') . '。）'];
    crrg_seal_set($s, 's3_守夜');
    $wx = function_exists('crrg_world_weather') ? crrg_world_weather('town')['k'] : 'clear';
    $pen = in_array($wx, ['rain', 'storm'], true) ? 1 : 0;
    $out = ['你在岔路口找了个背风的位置蹲下，把外衣领子立起来。'];
    if ($pen) $out[] = '（下着雨——今晚的观察检定 +1 惩罚骰。）';
    $val = max(1, crrg_coc_skill_val($pc, '聆听'));
    $r = crrg_coc_check('聆听', $val, 0, $pen);
    $out[] = '聆听：' . $r['text'];
    if (crrg_coc_level_rank($r['level']) < 2) {
        $pc['vitals']['energy'] = max(0, (float) $pc['vitals']['energy'] - 6);
        $out[] = '后半夜只有风。你什么也没等到，天快亮时手脚都麻了。（精力 −6）';
        if (function_exists('crrg_seal_use')) crrg_seal_use($pc, 1, '守夜耗时');
        return $out;
    }
    /* 成功：按已有旗标决定遇到谁 */
    if (crrg_seal_has($s, 's3_邻居') && !crrg_seal_has($s, 's3_徒弟账')) {
        crrg_seal_set($s, 's3_遇徒弟');
        $out[] = '摩托灯从坡下上来。骑车的人在岔口停下，熄了火——是田小满。';
        $out[] = '（可以「细问 田小满 账」，或「交涉」处理。）';
    } elseif (crrg_seal_has($s, 's3_徒弟账') && !crrg_seal_has($s, 's3_摊牌')) {
        crrg_seal_set($s, 's3_遇冒充者');
        $out[] = '坡下传来第二辆车的动静。这次不是摩托——是台小货车，没开灯，滑到陵前停了。';
        $out[] = '（有人从车上下来，穿夹克。可以「细问 冒充者 身份」，或直接「交涉」。)';
    } else {
        $out[] = '你等到后半夜，只看见一辆拉粪的拖拉机。';
    }
    if (function_exists('crrg_seal_use')) crrg_seal_use($pc, 1, '守夜耗时');
    return $out;
}

/* ══════════ 摊牌（三种处理） ══════════ */
/** 任何改变第三段旗标的动作之后都该跑一次结论判定（2026-09-29 修：
 *  原先只有"调查/细问"会调用它，走"交涉摊牌"就会永远差一步） */
function crrg_seal3_after_change(&$s, &$pc, array $out) {
    return array_merge($out, (array) crrg_seal3_conclude($s, $pc));
}
function crrg_seal3_parley(&$s, &$pc, $mode) {
    if (!crrg_seal_has($s, 's3_遇冒充者') && !crrg_seal_has($s, 's3_冒充者身份')) return ['（还没到摊牌的时候——先弄清楚他是谁。）'];
    if (crrg_seal_has($s, 's3_摊牌')) return ['（这件事已经处理过了。）'];
    if ($mode === '') return ['（「交涉 报案」／「交涉 对峙」／「交涉 假料」。）'];
    if (strpos($mode, '报案') !== false) {
        crrg_seal_set($s, 's3_摊牌'); crrg_seal_set($s, 'foe_报了警');
        crrg_rep_add($pc, 'town_qingshi', 2, '《封条》：向派出所报案');
        return ['你走到镇上打了个电话。派出所的人来了，把车和人都扣在陵前。',
                '录口供时，那个穿夹克的一句话都不说，只在登记表上写了个外地地址。',
                '（镇上声望 +2 ｜ 但**派出所从此记得有一批"封条"从镇上过手**。）'];
    }
    if (strpos($mode, '对峙') !== false) {
        $val = max(1, crrg_coc_skill_val($pc, '恐吓'));
        $r = crrg_coc_check('恐吓', $val);
        $out = ['你堵在车前，把门禁卡亮给他看：「县文保没有这号单位。」', '恐吓：' . $r['text']];
        if (crrg_coc_level_rank($r['level']) >= 2) {
            crrg_seal_set($s, 's3_摊牌'); crrg_seal_set($s, 'foe_被吓退'); crrg_seal_set($s, 's3_口供');
            $out[] = '他把手从口袋里拿出来，笑了：「行，你厉害。」';
            $out[] = '「不过我劝你一句——你们基地缺的不止封条。」';
            $out[] = '（口供：他知道基地缺什么，说明**内部有人给他递过话**。）';
        } else {
            crrg_seal_set($s, 's3_摊牌'); crrg_seal_set($s, 'foe_反被看穿');
            $out[] = '他一点都没慌，反倒往前站了半步：「你一个人？」';
            $out[] = '（没压住他。他记住了你的脸。）';
        }
        return $out;
    }
    if (strpos($mode, '假料') !== false) {
        crrg_seal_set($s, 's3_摊牌'); crrg_seal_set($s, 'foe_用假料');
        crrg_rep_add($pc, 'town_qingshi', 1, '《封条》：卖了一批假料');
        return crrg_seal3_after_change($s, $pc, ['你把配方改了比例，掺上大半的糯米浆，装了二十斤给他。',
                '他看了成色，付了钱，连夜拉走了。',
                '（镇声望 +1 ｜ 但他迟早会发现——**这条线会在下一阶段回头找你**。）']);
    }
    return ['（「交涉 报案」／「交涉 对峙」／「交涉 假料」。）'];
}

/* ══════════ 中毒事件处理 ══════════ */
function crrg_seal3_poison(&$s, &$pc, $mode) {
    if (!crrg_seal_has($s, 's3_中毒事件')) return ['（镇上还没出这件事。）'];
    if (crrg_seal_has($s, 's3_中毒处理')) return ['（已经处理过了。）'];
    if ($mode === '') return ['（「处置中毒 送医」／「处置中毒 先问料」／「处置中毒 带回基地」。）'];
    if (strpos($mode, '送医') !== false) {
        if ((int) $pc['money'] < 50) return ['（你身上不够 50 元挂号费。）'];
        $pc['money'] = (int) $pc['money'] - 50;
        crrg_seal_set($s, 's3_中毒处理'); crrg_seal_set($s, 'poison_已送医');
        crrg_rep_add($pc, 'town_qingshi', 3, '《封条》：送老匠人就医');
        return ['你把常守业背到卫生院，垫了挂号费。医生说：先排铅，得停手。',
                '「铺子我给他看着。」孙德发在旁边说。（钱 −50，镇声望 +3）'];
    }
    if (strpos($mode, '先问料') !== false) {
        crrg_seal_set($s, 's3_中毒处理'); crrg_seal_set($s, 'poison_先问料');
        crrg_rep_add($pc, 'town_qingshi', -2, '《封条》：先问配方');
        return ['你趁他半清醒，把配比的几个数问清了。',
                '他答得很利索——像是早背熟了。',
                '（配方细节拿到 ｜ 但镇上会有人知道你先问的是料，不是人。）'];
    }
    if (strpos($mode, '带回') !== false) {
        if (function_exists('crrg_travel_mode') && crrg_travel_mode($pc) === '徒步') return ['（徒步带不了人——先想办法弄辆车。）'];
        crrg_seal_set($s, 's3_中毒处理'); crrg_seal_set($s, 'poison_带回基地');
        crrg_rep_add($pc, 'town_qingshi', -1, '《封条》：把人带走');
        return ['你把常守业扶上车，说基地有设备能查。他没反对，只说了一句：「铺子别锁。」',
                '（镇声望 −1 ｜ 基地会给他做检查，但**镇上少了个能补件的人**。）'];
    }
    return ['（「处置中毒 送医/先问料/带回基地」。）'];
}

/** 三条并到一起 → s3_结论 */
function crrg_seal3_conclude(&$s, &$pc) {
    $need = ['s3_徒弟账', 's3_摊牌', 's3_暗账'];
    $ok = count(array_intersect($need, (array) ($s['flags'] ?? [])));
    $out = [];
    if ($ok >= 3 && !crrg_seal_has($s, 's3_结论')) {
        if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
        $s['flags'][] = 's3_结论';
        $out[] = '账、口供、流水摆在一起：**冒充者通过田小满买配方与料，钱走的是信用社的暗账。**';
        $out[] = '而他知道"基地缺的不止封条"——**镇上这条线的另一头，在基地里面。**';
        $out[] = '（第三轮结论成立——可以回镇政府准备返程。）';
    }
    return $out;
}

function crrg_seal3_stage_lines(&$s, $pc) {
    $f = (array) ($s['flags'] ?? []);
    $need = ['s3_邻居' => '邻居', 's3_徒弟账' => '徒弟账', 's3_摊牌' => '摊牌', 's3_暗账' => '暗账',
             's3_守夜' => '守夜', 's3_中毒事件' => '中毒事件', 's3_结论' => '结论'];
    $got = []; $miss = [];
    foreach ($need as $k => $n) { if (in_array($k, $f, true)) $got[] = $n; else $miss[] = $n; }
    $inv = function_exists('crrg_seal_inventory') ? crrg_seal_inventory($pc) : ['stock_days' => 0];
    return [sprintf('第三回合 · 徒弟与冒充者 ｜ 已办：%s ｜ 待办：%s ｜ 封条库存 %d 天',
        $got ? implode('、', $got) : '（无）', $miss ? implode('、', $miss) : '（齐）', (int) $inv['stock_days']),
        '（调查：邻居／流水／车辙；细问：孙德发/常守业/田小满/冒充者 ｜ 守夜 ｜ 交涉 报案/对峙/假料 ｜ 处置中毒 送医/先问料/带回基地）'];
}

/* ── 问 NPC：数据 + 薄包装（2026-09-29 合并三份重复逻辑） ── */
function crrg_seal3_ask_data() {
    return [
        '孙德发' => [
            ['徒弟', 's3_废品站徒弟', ['「那小子来过几回，跟一个穿夹克的一起。」',
                                       '「他不收钱，只拿料。我说你小子图啥，他说家里等钱用。」']],
        ],
        '常守业' => [
            ['徒弟', 's3_匠人徒弟', ['「小满手比我稳。」他咳了两声。',
                                     '「我教他的时候就说，这东西伤手。他说他不怕。」',
                                     '（他把里屋门推开一条缝，里面空的。）']],
            ['收料的人', 's3_匠人收料人', ['「来过两回。头一回说自己是县文化馆的，第二回说是收购站的。」',
                                           '「我说你名头怎么老换，他就笑。」']],
        ],
        '田小满' => [
            ['账', 's3_徒弟账', ['他蹲在门槛上，半天没说话。最后从兜里摸出一叠纸：「你拿去吧。」',
                                 '「我娘在县医院。他们给我现钱。」',
                                 '（账本到手：三笔现金，与信用社流水对得上。**徒弟是供货的一环。**）']],
            ['回来', 's3_劝回', ['「那我娘呢？」他问。', '（这个问题你答不上来。）']],
        ],
        '冒充者' => [
            ['身份', 's3_冒充者身份', ['他笑得挺自然：「我是县里的，管文保这一块。」',
                                       '你注意到他鞋底很干净，但裤脚上有老陵那边的红土。']],
        ],
    ];
}
function crrg_seal3_npc_ask(&$s, &$pc, $npc, $topic = '') {
    return function_exists('crrg_seal_ask_engine') ? crrg_seal_ask_engine($s, $pc, $npc, $topic, crrg_seal3_ask_data()) : null;
}
