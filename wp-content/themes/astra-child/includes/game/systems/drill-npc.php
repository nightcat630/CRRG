<?php
/**
 * 基地 NPC 与跑腿委托（2026-09-28）
 * 口径（委员长定）：
 *   1. NPC 有作息、会移动——按游戏时间的时段出现在不同房间（不固定蹲点）。
 *   2. 可对话（接进对话系统），可接简单任务。
 *   3. **基地内的跑腿委托只给钱、不给积分**；金额按难度。唯一例外是既有的「给情报处换台灯」（+4 积分）。
 */
if (!defined('ABSPATH')) exit;

/** 现在是什么时段（用于排班） */
function crrg_npc_phase() {
    if (!function_exists('crrg_world_clock')) return '上午';
    $h = crrg_world_clock()['h'];
    if ($h < 5)  return '深夜';
    if ($h < 8)  return '黎明';
    if ($h < 11) return '上午';
    if ($h < 13) return '正午';
    if ($h < 17) return '下午';
    if ($h < 19) return '黄昏';
    if ($h < 23) return '夜';
    return '深夜';
}

/** 班次分组：把七个时段压成四班，说话内容按班次不同 */
function crrg_npc_shift($phase = null) {
    $p = $phase ?: crrg_npc_phase();
    if (in_array($p, ['黎明', '上午'], true)) return '早班';
    if ($p === '正午') return '午间';
    if (in_array($p, ['下午', '黄昏'], true)) return '白班';
    return '夜班';
}
/** 各人按班次的闲话（2–3 句，口气不同） */
function crrg_npc_talk() {
    return [
        'kang' => [
            '早班' => ['「早上交班，昨晚没什么事——没事就是好事。」', '「排班表我刚贴上去，别撕。」'],
            '午间' => ['「趁热吃。我吃完就得回去盯屏。」', '「上午批了三份外勤单，都压着没批。」'],
            '白班' => ['「下午这段最安静，最不适合出事。」', '「通讯机别乱试频道，我这儿都记着。」'],
            '夜班' => ['「夜里我就守在这儿。有情况按铃。」', '「凌晨三点到四点，人最容易犯困，我喝浓茶。」'],
        ],
        'zhou' => [
            '早班' => ['「早上出货多，本子记得密。」', '「仓库里那点东西，我闭着眼都知道在哪。」'],
            '午间' => ['「先吃，吃完再点数。」', '「上个月的账还没核完。」'],
            '白班' => ['「下午来人少，正好盘库。」', '「米面要垫板子，潮了就不能吃。」'],
            '夜班' => ['「夜里我在配电间那边转一圈，怕跳闸。」', '「蓄电池组的温度得看着。」'],
        ],
        'chen' => [
            '早班' => ['「粥在锅里，咸菜在坛子里。」', '「六点半开早饭，过点没了。」'],
            '午间' => ['「三点开火，现在正是时候。」', '「今天有肉，别声张。」'],
            '白班' => ['「下午备明天的菜。刀不快了。」', '「我不去医疗室，是去拿胃药——别传。」'],
            '夜班' => ['「夜宵我做不了，锅都刷了。」', '「上回有人半夜翻冰箱，我记得是谁。」'],
        ],
        'han' => [
            '早班' => ['「早上脑子清楚，调试就趁这会儿。」', '「那台设备再响我就拆了它。」'],
            '午间' => ['「饭我打好了，坐。」', '「你们外勤的通讯机，坏了我修。」'],
            '白班' => ['「下午这段机器最稳，别打扰我。」', '「数据我核过三遍，你们别再问。」'],
            '夜班' => ['「夜里我才写得动代码。」', '「有情况叫我，我不睡。」'],
        ],
        'bai' => [
            '早班' => ['「早班的血压都偏高，你们自己心里有数。」', '「药在这儿，按登记领。」'],
            '午间' => ['「吃完别马上躺，走走。」', '「我不去食堂，就在这儿对付一口。」'],
            '白班' => ['「下午来做理疗的两个还没来。」', '「睡不着的人，多数是不肯说事。」'],
            '夜班' => ['「夜里我不关灯，谁有事推门就行。」', '「值班室的应急药箱我补过了。」'],
        ],
        'wei' => [
            '早班' => ['「上午我在军械室清点，你下午再来。」', '「领东西签字，写用途，规矩不是我定的。」'],
            '午间' => ['「吃完再干活，枪不差这半小时。」', '「上回有个新人把壁灯当台灯买，我笑了半天。」'],
            '白班' => ['「装备整备室下午有人，来吧。」', '「防护服面罩要看不许有划痕，划了就得换。」'],
            '夜班' => ['「夜里装备库锁两道，钥匙在我枕下。」', '「谁再半夜借绳子不还，我记名字。」'],
        ],
    ];
}

/** 基地常驻人员表：姓名 / 职务 / 作息（时段 → 房间） */
function crrg_npc_roster() {
    /* Phase 1：数据已搬到 includes/game/content/npcs.php（纯数据） */
    return (function_exists('crrg_game_npc_roster_data') ? crrg_game_npc_roster_data() : []);
}
function crrg_npc_room_now($id) {
    $R = crrg_npc_roster();
    if (!isset($R[$id])) return '';
    $p = crrg_npc_phase();
    return $R[$id]['sched'][$p] ?? '';
}
/** 此刻在某个房间里的人 */
function crrg_npc_at_room($room) {
    $out = [];
    foreach (crrg_npc_roster() as $id => $d) if (crrg_npc_room_now($id) === $room) $out[$id] = $d;
    return $out;
}
/** 跨场景查房间名（NPC 可能在别的场景里，不能只查玩家当前场景） */
function crrg_npc_room_name($room) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $scenes = array_keys((array) (crrg_drill_reg()['scenes'] ?? []));
        foreach ($scenes as $sc) {
            crrg_drill_set_scene($sc);
            foreach ((array) crrg_drill_scene_rooms() as $k => $v) $cache[$k] = $v['t'] ?? $k;
        }
        crrg_drill_set_scene('base');
        foreach ((array) crrg_drill_rooms() as $k => $v) if (!isset($cache[$k])) $cache[$k] = $v['t'] ?? $k;
    }
    return $cache[$room] ?? $room;
}
/** 每个 NPC 的专属话题（避免所有人都一套模板） */
function crrg_npc_extra_topics($id) {
    $M = [
        'han' => [
            ['技术问题', ['技术', '设备', '仪器'], ['她把手里的螺丝刀放下：「问吧，我这儿正拆着。」',
                                                    '「上次那批台灯的线路我改过——你们用着还行吧。」']],
            ['关于封条', ['封条', '纸', '配比'], ['「配比我算过。要真是掺铅，那就不能照抄。」']],
        ],
        'kang' => [
            ['值班表', ['排班', '值班表', '换班'], ['他从抽屉里摸出排班表：「下礼拜的，你要看？」',
                                                  '「我这儿缺人。你要是能顶两个夜班，我请你吃饭。」']],
            ['夜里的动静', ['夜里', '晚上', '动静'], ['「后半夜的动静我都记着。你要真想听，得有耐心。」']],
        ],
        'chen' => [
            ['食堂的账', ['菜单', '饭', '食材'], ['「八份值班餐，多一份我都得算。」',
                                                '「你们出外勤的回来晚，锅我给你们留着。」']],
            ['老周的烟', ['烟', '老周'], ['「他？他戒了三年了。那回之后就没碰过。」']],
        ],
        'zhou' => [
            ['库房的门道', ['库房', '备件', '领料'], ['「领料单我签，但超三件要室主任点头。」',
                                                     '「有些件，单子上没有，库里也不该有。」']],
            ['对不上的数', ['缺口', '对不上', '账'], ['他把算盘拨了一下：「单子上的数，你自己看。」']],
        ],
        'bai' => [
            ['体检', ['体检', '抽血', '指标'], ['「接触过配比的人，三个月查一次。」',
                                               '「你上次的指标我留着，别当没这回事。」']],
            ['当年的旧账', ['三年前', '当年', '旧事'], ['「那是三年前的事了。你要真想问，先跟康明远说一声。」']],
        ],
        'wei' => [
            ['车辆', ['车', '摩托', '越野'], ['「车况你自己摸过没有？别等抛在路上。」']],
            ['旧零件', ['零件', '备件', '旧料'], ['「旧电瓶留了两只，你要铅板就直接拆。」']],
        ],
        /* 镇上 */
        'town_coop' => [['供销社的货', ['进货', '货', '价'], ['「乡下的东西，价都在牌子上。」']]],
        'town_clinic' => [['病人的事', ['病人', '症状', '铅'], ['医生把本子合上：「有的病人不肯说。」']]],
        'town_barber' => [['镇上的门道', ['门道', '路子', '谁管事'], ['师傅把剪子放下：「你要听实在的？」']]],
    ];
    return $M[$id] ?? [];
}
/** 把在场 NPC 合成对话对象（供「问 XX」与面板使用） */
function crrg_npc_dlg_def($id) {
    $R = crrg_npc_roster();
    if (!isset($R[$id])) return null;
    $d = $R[$id];
    $shift = crrg_npc_shift();
    $talk = (crrg_npc_talk()[$id][$shift] ?? []);
    $topics = [];
    foreach ($talk as $i => $text) {
        $topics[] = ['id' => 'shift_' . $i, 'label' => '当班（' . $shift . '）' . ($i ? '·之二' : ''),
                     'q' => ['当班', '现在', '闲聊', '忙什么'], 'text' => [$text], 'once' => ($i > 0)];
    }
    $topics[] = ['id' => 'state', 'label' => '他看你一眼（近况）', 'q' => ['近况', '你看', '我', '听说'],
                 'dyn' => 'crrg_npc_line_state', 'text' => ['……'], 'once' => true];
    if (!empty($d['task'])) {
        $topics[] = ['id' => 'task', 'label' => '有没有活儿给我', 'q' => ['活', '任务', '委托', '活儿'],
                     'text' => ['「手头正好有一件，你接了我就省事。」', '（委托已挂到任务面板，回值班板签字结算。）']];
    }
    $topics[] = ['id' => 'move', 'label' => '你怎么老在换地方', 'q' => ['你怎么', '在哪', '换地方', '作息'],
                 'text' => ['「活儿在哪我在哪。」',
                            '「这个点儿，我在' . crrg_npc_room_name(crrg_npc_room_now($id)) . '。」']];
    foreach (crrg_npc_extra_topics($id) as [$label, $kw, $texts]) {
        $topics[] = ['id' => 'x_' . md5($label), 'label' => $label, 'q' => $kw, 'text' => $texts];
    }
    return ['name' => $d['n'], 'alias' => $d['alias'] ?? [], 'room' => crrg_npc_room_now($id),
            'topics' => $topics, 'npc' => $id];
}

/** 状态感知的一句话：看你在手的合约、你的卡级、基地近况 */
function crrg_npc_line_state($s, $pc) {
    $flags = (array) ($s['flags'] ?? []);
    $m = $pc['mission'] ?? '';
    $shift = crrg_npc_shift();
    $pick = [];
    if (in_array('lamp_delivered', $flags, true)) $pick[] = '「情报处那两盏台灯换上了，夜里看着舒服。听说为这个折腾半天。」';
    if (in_array('lamp_delivered', $flags, true)) $pick[] = '「台灯的事，老魏笑了你半天——他说新人都会踩这个坑。」';
    if ($m === 'm_chase_01') $pick[] = '「镇上那桩失窃？李承业来找过值班室问进展。别拖太久。」';
    if ($m === 'm_wild_01') $pick[] = '「野外那活儿小心点。档案上写着『请勿接近本体』，那不是客气话。」';
    if ($m === 'm_train_01') $pick[] = '「考核还没交？装备整备室借的东西记得还。」';
    if ($m && strpos($m, 'm_pm_') === 0) $pick[] = '「跑腿的活儿不丢人，先把本子上的名签了。」';
    if (($pc['credits'] ?? 0) >= 250) $pick[] = '「你这积分攒得比我年资还快。」';
    if ($shift === '夜班') $pick[] = '「这个点儿还转悠？去睡吧。」';
    if ($shift === '午间') $pick[] = '「先吃饭。事不会凉，饭会。」';
    if (!$pick) $pick[] = '「没什么特别的事。你要问什么自己开口。」';
    return [$pick[array_rand($pick)], '（他（她）说的是此刻的实情，不是套话。）'];
}

/** 动态登记：让对话系统认得「此刻在场」的人 */
function crrg_npc_sync_dialogue() {
    foreach (crrg_npc_roster() as $id => $d) {
        $def = crrg_npc_dlg_def($id);
        if ($def) crrg_dlg_register('npc_' . $id, $def);   // room 会随时段变化，由 crrg_dlg_npcs_at 动态判定
    }
}

/* ================= 跑腿委托（只给钱） ================= */
function crrg_npc_missions_reg() {
    /* 口径：跑腿单**一律挂在值班板**（固定点位），三步固定——取件 → 送达 → 回值班板签字。
     * NPC 会走动，所以不把接单房间绑在他身上：他只是"派活的人"，交接在值班板。 */
    $mk = function ($name, $prompt, $pick, $send, $money, $note, $sendText = '送到') {
        return ['name' => $name, 'level' => '低', 'kind' => 'npc', 'min_lv' => 1,
                'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
                'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
                'prompt' => $prompt,
                'steps' => [
                    ['flag' => 'pm_picked_' . $name, 'text' => '从值班板取下单子'],
                    ['room' => $send,      'text' => $sendText . (crrg_drill_rooms()[$send]['t'] ?? $send)],
                    ['room' => 'b2b_hall', 'text' => '回值班板签字'],
                ],
                'reward' => ['credits' => 0, 'money' => $money], 'settle_note' => $note];
    };
    return [
        'm_pm_duty'  => $mk('跑腿：把夜班排班表送到作战值班室',
            '康明远把新排的夜班表挂在值班板上。取下来送到作战值班室，值班小队签完字再拿回值班板归档。',
            'b2b_hall', 'b2b_duty', 50, '值班小队在表上签了字，单子归了档。'),
        'm_pm_stock' => $mk('跑腿：替后勤清点仓库备件',
            '老周请人盘一遍备件：在物资仓库点清、把单子送到负三层主通道西段的值班台，再回值班板归档。',
            'b3_warehouse', 'b3_entry', 80, '单子交回，后勤照数核了：数都对。'),
        'm_pm_meal'  => $mk('跑腿：把午饭送到体能训练室',
            '陈师傅的午饭得趁热送：到食堂取保温桶，送到体能训练室，空桶拿回值班板。',
            'b1_canteen', 'b2f_gym', 60, '桶还回来了，账当场结。'),
        'm_pm_repair'=> $mk('跑腿：把检修单送到军械维护室',
            '韩雪的检修单：到技术研发室取，送到军械维护室签字，签字联带回值班板。',
            'b2b_tech', 'b2f_armory', 70, '签字联交回，韩雪那边已经录进系统。'),
        'm_pm_med'   => $mk('跑腿：把体检表送到文职办公室',
            '白大夫的体检表：到医疗室取，送到文职办公室入档，回值班板销账。',
            'b1_med', 'b1_office', 60, '文职办公室盖了收讫章，值班板销了账。'),
        'm_pm_gear'  => $mk('跑腿：把 12 号装备箱提到装备整备室',
            '老魏的箱子：到物资仓库提 12 号装备箱，送到装备整备室开箱验过，回值班板签字。',
            'b3_warehouse', 'b2f_gear', 120, '老魏开箱验过，箱子入库。这件算重活。'),
    ];
}

crrg_npc_sync_dialogue();
foreach (crrg_npc_missions_reg() as $mid => $m) crrg_drill_register_mission($mid, $m);

/* ================= D 级可做的「有内容」任务（给积分，但不多） ================= */
function crrg_npc_dlevel_missions() {
    /* Phase 1：数据已搬到 includes/game/content/npcs.php（纯数据） */
    return (function_exists('crrg_game_npc_missions_data') ? crrg_game_npc_missions_data() : []);
}
function crrg_npc_fav_of($pc, $name) { return (int) ($pc['npc_fav'][$name] ?? 0); }
function crrg_npc_fav_tier($v) {
    $v = (int) $v;
    if ($v < 0) return '冷淡';
    if ($v === 0) return '生分';
    if ($v <= 2) return '客气';
    if ($v <= 5) return '熟';
    return '交情';
}
function crrg_npc_fav_add(&$pc, $name, $d = 1) {
    if ($name === '') return 0;
    if (!isset($pc['npc_fav']) || !is_array($pc['npc_fav'])) $pc['npc_fav'] = [];
    $pc['npc_fav'][$name] = (int) ($pc['npc_fav'][$name] ?? 0) + (int) $d;
    return $pc['npc_fav'][$name];
}
/** 该委托属于哪个 NPC（跑腿委托结算时给好感） */
function crrg_npc_by_task($taskId) {
    foreach (crrg_npc_roster() as $id => $d) if (($d['task'] ?? '') === $taskId) return $d['n'] ?? '';
    return '';
}
/** 「人情」一行（给状态/笔记用） */
function crrg_npc_fav_lines($pc) {
    $fav = (array) ($pc['npc_fav'] ?? []);
    if (!$fav) return ['人情：（还没跟谁打过交道。）'];
    $out = ['人情：'];
    foreach ($fav as $nm => $v) $out[] = sprintf('　%s %+d（%s）', $nm, (int) $v, crrg_npc_fav_tier((int) $v));
    return $out;
}

/* ================= 统一"谁在场"（2026-09-29） =================
 * 两套来源合并：
 *   ① 基地 NPC：按作息表决定在哪
 *   ② 镇上与其它对话 NPC：注册时写死 room（crrg_dlg_all()）
 * 之前只有 ①，所以镇上 NPC（理发师傅／裁缝／售货员／常守业…）**在哪都不显示**，
 * 对他们「细问」也会被判"不在场"。
 */
function crrg_npc_here($room) {
    $out = [];
    if (function_exists('crrg_npc_at_room') && function_exists('crrg_npc_roster')) {
        foreach ((array) crrg_npc_at_room($room) as $id => $d) {
            $nm = (string) ($d['n'] ?? $id);
            if ($nm !== '') $out[$nm] = ['role' => (string) ($d['role'] ?? ''), 'src' => 'sched'];
        }
    }
    if (function_exists('crrg_dlg_all')) {
        foreach ((array) crrg_dlg_all() as $id => $d) {
            if (($d['room'] ?? '') !== $room) continue;
            $nm = (string) ($d['name'] ?? $id);
            if ($nm === '' || isset($out[$nm])) continue;
            $out[$nm] = ['role' => (string) ($d['role'] ?? '镇民'), 'src' => 'dlg'];
        }
    }
    return $out;
}
/** 「在场：X（职务）、Y」一行（没有则空串） */
function crrg_npc_here_line($room, $s = null) {
    /* 2026-10-01：改为**统一来源**（名册 ＋ 对话表 ＋ 任务人物 ✓）——原来只查名册 ✗ */
    if (function_exists('crrg_npc_present_line')) return crrg_npc_present_line($room, $s);
    $here = crrg_npc_here($room);
    if (!$here) return '';
    $p = [];
    foreach ($here as $nm => $d) $p[] = $nm . ($d['role'] !== '' ? '（' . $d['role'] . '）' : '');
    return '在场：' . implode('、', $p);
}

/**
 * 在场的人·**统一来源**（2026-10-01 修：原来只查基地名册 ✗ → 镇上 NPC 与任务人物都不显示）
 *   ① 基地名册（作息驱动 ✓）
 *   ② **对话注册表**（镇上 NPC、以及以后任何 crrg_dlg_register 新加的人 ✓）
 *   ③ **任务人物**（旗标驱动 ✓：如《封条》第三段守夜遇到的「田小满」）
 * @param string $room 房间键
 * @param array|null $s 玩法状态（含 flags ✓）；不传则只看 ①②
 * @return array 名称 => ['role'=>角色, 'src'=>来源]
 */
function crrg_npc_present($room, $s = null) {
    $room = (string) $room;
    $out = [];
    if (function_exists('crrg_npc_here')) {
        foreach ((array) crrg_npc_here($room) as $nm => $d) {
            $out[(string) $nm] = ['role' => (string) ($d['role'] ?? ''), 'src' => 'roster'];
        }
    }
    if (function_exists('crrg_dlg_npcs_at')) {
        foreach ((array) crrg_dlg_npcs_at($room) as $id => $d) {
            $nm = (string) ($d['name'] ?? $id);
            if ($nm === '' || isset($out[$nm])) continue;
            $out[$nm] = ['role' => (string) ($d['role'] ?? ''), 'src' => 'dlg'];
        }
    }
    /* 任务人物：**旗标到位 + 该任务仍在手** 才算在场 ✓（2026-10-01 修）
       —— 原来只看旗标 ✗：守夜做过一次后旗标长期保留 ✗ → 任务交了、人还在 ✓（玩家报"没任务怎么还有田小满"✓）
       表：旗标 => [任务id, 房间 => [人名, 角色说明]] */
    /* 表在**统一 NPC 模块**里（`crrg_npc_visits()` ✓）——一处维护，两边生效 ✓ */
    $FLAG_NPC = function_exists('crrg_npc_visits') ? (array) crrg_npc_visits() : [];
    $flags = is_array($s) ? (array) ($s['flags'] ?? []) : [];
    $cur   = is_array($s) ? (string) (($s['mission'] ?? '') ?: ($s['mission2']['id'] ?? '')) : '';
    foreach ($FLAG_NPC as $flag => $spec) {
        if (!in_array($flag, $flags, true)) continue;
        $needMission = (string) ($spec[0] ?? '');
        if ($needMission !== '' && $cur !== $needMission) continue;      // 任务不在手 → 不再显示 ✓
        foreach ((array) ($spec[1] ?? []) as $r => $who) {
            if ($r !== $room) continue;
            $out[(string) $who[0]] = ['role' => (string) ($who[1] ?? ''), 'src' => 'mission'];
        }
    }
    return $out;
}
/** 「在场：…」一行（同一来源 ✓） */
function crrg_npc_present_line($room, $s = null) {
    $here = crrg_npc_present($room, $s);
    if (!$here) return '';
    $p = [];
    foreach ($here as $nm => $d) $p[] = $nm . ($d['role'] !== '' ? '（' . $d['role'] . '）' : '');
    return '在场：' . implode('、', $p);
}

/**
 * 帮忙（2026-10-01 加 ✓）：可重复的提好感方式 —— 玩家报「一次性的跑腿做完就再也刷不了好感」✗
 *  规则：**必须同房间**（在场 ✓）＋ **同一人每游戏日一次** ✓ ＋ **+1 好感** ✓
 *  为什么这么定：好感该**慢慢攒**（一天一点 ✓），不该靠刷 ✗；也给"一次委托做完"的人留了后续路 ✓
 */
function crrg_npc_help_day($pc) {
    if (function_exists('crrg_world_clock')) { $c = crrg_world_clock(); return (int) ($c['day'] ?? 0); }
    return (int) floor(time() / 86400);
}
function crrg_npc_help_done_today($pc, $npc) {
    return (int) (($pc['npc_help_last'][(string) $npc] ?? 0)) === crrg_npc_help_day($pc);
}
function crrg_npc_help(&$s, &$pc, $npc) {
    $npc = trim((string) $npc);
    if ($npc === '') return ['（帮谁？「帮忙 韩雪」。）'];
    /* ① 得当面：用统一在场判定 ✓ */
    $room = (string) ($s['room'] ?? '');
    $here = function_exists('crrg_npc_present') ? (array) crrg_npc_present($room, $s) : [];
    $hit = '';
    foreach (array_keys($here) as $nm) if ($nm === $npc || mb_strpos((string) $nm, $npc) !== false) { $hit = (string) $nm; break; }
    if ($hit === '') return [sprintf('（%s 不在这儿——帮忙得当面。先看看「互动」里谁在。）', $npc)];
    if (crrg_npc_help_done_today($pc, $hit)) return [sprintf('（今天已经帮过 %s 了——一天一次，明天再来。）', $hit)];
    $pc['npc_help_last'][$hit] = crrg_npc_help_day($pc);
    $nv = crrg_npc_fav_add($pc, $hit, 1);
    return [sprintf('你搭了把手。%s 说了句「记着」。', $hit),
            sprintf('（%s 好感 +1 → %d：%s）', $hit, $nv, crrg_npc_fav_tier($nv))];
}
