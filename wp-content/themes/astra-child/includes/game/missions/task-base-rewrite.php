<?php
/**
 * 基地任务 · 引擎版重写（2026-10-14）
 * ── 旧的 5 条一次性小任务（在 drill-missions-base.php 里）**已删除**，这里按新引擎重写 ──
 *   重写要求（委员长 2026-10-14）：阶段 / 线索→结论 / 多解法+检定+失败代价 /
 *   对话注入（老人物插支线·新人物分阶段）/ NPC 时段移动 / 世界自走 / 结算五路 / 善后
 *   原始风味、奖励数值、房间提示**照旧保留** ✓（搬的是结构，不是重写文案）
 *
 * 注意：5 条**日常**（m_dy_*）**故意留在旧格式** —— 它们是"循环型跑腿"，没有前因后果，
 *   硬套"阶段/线索/结论"会把它们写得比原来还假 ✗（判断记在 16 号文）
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_task_define')) return;
    $R = [
        'credits' => 0, 'money' => 0,
    ];

    /* ══════════ 1. 最后一班岗（康明远）══════════ */
    crrg_task_define('m_st_lastwatch', [
        'name' => '最后一班岗（康明远）', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
        'pass_rooms' => ['b4_buffer', 'b4_main', 'b4_guard', 'b2b_duty'],
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_duty', 'deadline_h' => 24,
        'prompt' => '康明远要调去别处，今晚是他最后一班岗。值班板挂了张临时单：替他巡一夜，天亮交班。',
        'steps' => [
            ['flag' => 'st1_接班', 'text' => '到作战值班室接班（点「接班」）', 'hint_room' => 'b2b_duty'],
            ['flag' => 'st1_巡夜', 'text' => '替他巡一趟 B4（点「巡夜」）', 'hint_room' => 'b4_main'],
            ['flag' => 'st1_交班', 'text' => '天亮回值班室交班（点「交班」）', 'hint_room' => 'b2b_duty'],
        ],
        'reward' => ['credits' => 6, 'money' => 200], 'rep' => ['base' => 2],
        'settle_note' => '他把钥匙串从腰上解下来，摊在桌上，让你自己挑。',

        'trigger' => ['room' => 'b2b_duty'],
        'stages' => [
            's1_接班' => ['name' => '接班', 'text' => '接过了他的班'],
            's2_巡夜' => ['name' => '巡夜', 'text' => '走完 B4 这一圈'],
            's3_交班' => ['name' => '交班', 'text' => '天亮交班'],
        ],
        'clues' => [
            'keypad' => ['title' => '钥匙串上少一把', 'point' => '他腰上那串钥匙，编号跳过一个——**那个格是空的**。',
                         'src' => '接班时看见的', 'where' => '作战值班室', 'to' => 'left'],
            'log' => ['title' => '巡夜记录的最后一行', 'point' => '他自己的巡检牌停在 B4 西头，日期是**上个月**。',
                      'src' => '值班室墙上的巡检牌', 'where' => 'B4 走廊的巡检牌', 'to' => 'left', 'link' => ['keypad']],
            'word' => ['title' => '他没说出口的那句', 'point' => '问到 B4 西头，他只说「你今晚自己看一眼」。',
                       'src' => '康明远', 'where' => '问康明远本人', 'to' => 'left', 'link' => ['keypad', 'log']],
        ],
        'concl' => [
            'left' => ['name' => '康明远留下了一件没交出去的事', 'need' => 2, 'of' => ['keypad', 'log', 'word'],
                       'text' => '钥匙少一把、巡检牌停在西头、他让你自己看——三处都指向同一个门。'],
        ],
        'paths' => [
            'walk' => ['name' => '照单巡完 B4（沿途留神）', 'type' => 'check',
                'check' => ['skill' => '侦查'],
                'cost' => ['minutes' => 40],
                'gain' => ['clues' => ['log'], 'stage' => 's2_巡夜', 'stage_text' => '巡完了 B4 这一圈'],
                'text' => ['走廊比白天静。你在西头停了一下——巡检牌上的日期不对。']],
            'ask' => ['name' => '直接问康明远', 'type' => 'npc',
                'need' => ['who' => 'kang', 'fav' => 0],
                'cost' => ['minutes' => 15],
                'gain' => ['clues' => ['word'], 'stage' => 's2_巡夜', 'stage_text' => '他松了口'],
                'text' => ['他把手插进兜里站了一会儿：「……你今晚自己看一眼。」']],
            'keys' => ['name' => '替他清点钥匙串', 'type' => 'check',
                'check' => ['skill' => '会计'],
                'cost' => ['minutes' => 20, 'expose' => 1],
                'gain' => ['clues' => ['keypad'], 'stage' => 's1_接班', 'stage_text' => '点清了钥匙'],
                'text' => ['编号连着排，只有一个格子是空的。']],
        ],
        'dlg' => [
            'kang' => ['ask_b4' => [
                'label' => '追问：B4 西头你今晚让我看什么',
                'stage' => 's2_巡夜',
                'node' => ['text' => ['他把巡更灯往桌上一放：「我签过的那块牌，日期没换。」',
                                      '「你替我换了它——别问为什么。」'],
                           'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                           'effect' => ['clue' => ['m_st_lastwatch', 'word']]],
            ]],
        ],
        'actors' => ['kang' => ['room' => 'b2b_duty', 'phases' => ['夜', '深夜']]],
        'beats' => [
            'dawn' => ['when' => ['clues' => ['log']],
                'do' => ['narrate' => ['（后半夜值班室的灯一直亮着，他在那儿写东西。）'], 'stage' => 's3_交班']],
        ],
        'outcome' => ['done' => [
            'personal' => ['credits' => 6, 'money' => 200, 'rep' => ['base' => 2], 'flags' => ['st1_交班']],
            'facts' => [['base.康明远的最后一班', true, 'once']],
            'actors' => ['kang' => ['fav' => 1, 'flag' => 'kang_欠你一次']],
            'world' => ['note' => '康明远调离前，把 B4 西头的事交给了夜班。'],
            'archive' => ['title' => '【任务档案】最后一班岗', 'type' => '事件', 'level' => 'operative'],
        ]],
        'cleanup' => true,
    ]);

    /* ══════════ 2. 第九份饭（食堂）══════════ */
    crrg_task_define('m_st_ninth', [
        'name' => '第九份饭（食堂）', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b1_canteen', 'deadline_h' => 48,
        'prompt' => '食堂每天出八份值班餐，今天多了第九份，没人认领。陈连生把单子递到值班板上：查清是谁的。',
        'steps' => [
            ['flag' => 'st2_点数', 'text' => '到食堂点数、看菜单', 'hint_room' => 'b1_canteen'],
            ['flag' => 'st2_加训', 'text' => '去体能训练室问有谁加训', 'hint_room' => 'b2f_gym'],
            ['flag' => 'st2_交差', 'text' => '回食堂交差', 'hint_room' => 'b1_canteen'],
        ],
        'reward' => ['credits' => 3, 'money' => 100], 'rep' => ['base' => 1],
        'settle_note' => '第九份饭的来历说清了。陈连生说下次别查了，他多蒸两个馒头就行。',

        'trigger' => ['room' => 'b1_canteen'],
        'stages' => [
            's1_点数' => ['name' => '点数', 'text' => '数了那九份饭'],
            's2_追人' => ['name' => '追人', 'text' => '知道是谁在吃'],
            's3_交差' => ['name' => '交差', 'text' => '回去说清楚'],
        ],
        'clues' => [
            'dish' => ['title' => '第九份多了一份荤', 'point' => '份量按八个人配的，只有那一份多了一块肉——**是给人留的**。',
                       'src' => '食堂窗口', 'where' => '食堂打饭窗口', 'to' => 'who'],
            'gym' => ['title' => '加训名单上没有他', 'point' => '体能室签到表里，最近常来的那个人**不在编制里**。',
                      'src' => '体能训练室', 'where' => '体能训练室的签到表', 'to' => 'who', 'link' => ['dish']],
            'chen' => ['title' => '陈连生含糊的那句', 'point' => '「多个馒头的事，你们别管。」——他手一直在擦已经干净的台面。',
                       'src' => '食堂师傅', 'where' => '问陈连生本人', 'to' => 'who', 'link' => ['dish', 'gym']],
        ],
        'concl' => [
            'who' => ['name' => '第九份是给一个"不在编"的人留的', 'need' => 2, 'of' => ['dish', 'gym', 'chen'],
                      'text' => '多一块肉、签到不认人、老陈不肯说——三处对上了。'],
        ],
        'paths' => [
            'count' => ['name' => '按份量算账', 'type' => 'check',
                'check' => ['skill' => '会计'],
                'cost' => ['minutes' => 20],
                'gain' => ['clues' => ['dish'], 'stage' => 's1_点数'],
                'text' => ['你把八份的配比和第九份对了一下：多出来的是**肉**。']],
            'gym'  => ['name' => '去体能室问加训的人', 'type' => 'check',
                'check' => ['skill' => '侦查'],
                'cost' => ['minutes' => 30],
                'gain' => ['clues' => ['gym'], 'stage' => 's2_追人'],
                'text' => ['签到表上那个名字，你翻遍值班表也找不到。']],
            'askchen' => ['name' => '直接问陈连生', 'type' => 'npc',
                'need' => ['who' => 'chen'],
                'cost' => ['minutes' => 10],
                'gain' => ['clues' => ['chen'], 'stage' => 's2_追人'],
                'text' => ['他把抹布拧了又拧：「……别问了行不行。」']],
        ],
        'dlg' => [
            'chen' => ['ask_ninth' => [
                'label' => '追问：第九份饭到底给谁',
                'stage' => 's2_追人',
                'node' => ['text' => ['他关了灶上的火，压低声音：「训练室那个，晚上才来。」',
                                      '「他自己不承认自己是这儿的人——我总得让他吃口热的。」'],
                           'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                           'effect' => ['clue' => ['m_st_ninth', 'chen']]],
            ]],
        ],
        'actors' => ['chen' => ['room' => 'b1_canteen', 'phases' => ['下午', '黄昏']]],
        'beats' => [
            'again' => ['when' => ['clues' => ['dish']],
                'do' => ['narrate' => ['（连着两天，第九份都放在同一个窗口，谁也没动。）']]],
        ],
        'outcome' => ['done' => [
            'personal' => ['credits' => 3, 'money' => 100, 'rep' => ['base' => 1], 'flags' => ['st2_交差']],
            'facts' => [['base.食堂的第九份饭', true, 'once']],
            'actors' => ['chen' => ['fav' => 1]],
            'world' => ['note' => '食堂多留了一份饭：给一个不肯登记的夜训人。'],
            'archive' => ['title' => '【任务档案】第九份饭', 'type' => '事件', 'level' => 'operative'],
        ]],
        'cleanup' => true,
    ]);

    /* ══════════ 3. 负三层的水声 ══════════ */
    crrg_task_define('m_st_water', [
        'name' => '负三层的水声', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
        'pass_rooms' => ['b3_entry', 'b3_water', 'b1_office'],
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b3_entry', 'deadline_h' => 96,
        'prompt' => '夜班反映 B3 主通道西段总听见水声，可地面是干的。值班板把这条挂成单子。',
        'steps' => [
            ['flag' => 'st3_听声', 'text' => '夜里去负三层主通道听一遍', 'hint_room' => 'b3_entry'],
            ['flag' => 'st3_关阀', 'text' => '进水务间关阀比对', 'hint_room' => 'b3_water'],
            /* 2026-10-14：**按动作实体对齐** —— crrg_base_mission_acts() 里 st3_旧档 挂在 b1_office ✓
               （我第一版改成 b1_archive ✗，被 verify-game「任务步骤」当场逮住 ✓） */
            ['flag' => 'st3_旧档', 'text' => '到文职办公室翻十年前的职员工牌档', 'hint_room' => 'b1_office'],
            ['flag' => 'st3_记录', 'text' => '回值班板写记录', 'hint_room' => 'b2b_hall'],
        ],
        'reward' => ['credits' => 5, 'money' => 80], 'rep' => ['base' => 2],
        'settle_note' => '记录写完了。水声的解释写了两行：一行是管道，一行不是。',

        'trigger' => ['rooms' => ['b3_entry', 'b3_water'], 'night' => true],
        'stages' => [
            's1_听声' => ['name' => '听声', 'text' => '听见了那阵水声'],
            's2_查明' => ['name' => '查明', 'text' => '知道水从哪来'],
            's3_记录' => ['name' => '记录', 'text' => '写进了当班记录'],
        ],
        'clues' => [
            'trace' => ['title' => '潮痕的走向', 'point' => '潮气从**西墙脚下**往外渗，不是从天花板滴的。',
                        'src' => 'B3 主通道（自己看的）', 'where' => 'B3 主通道西段', 'to' => 'who'],
            'valve' => ['title' => '关阀比对的结果', 'point' => '把西段阀关死，水声照旧——**水不是从主管来的**。',
                        'src' => '水务间', 'where' => 'B3 水务间', 'to' => 'who', 'link' => ['trace']],
            'ledger'=> ['title' => '缺页的维修单', 'point' => '上个月的维修单缺两页，编号跳号，缺的正好是西段。',
                        'src' => '后勤保管（老周）', 'where' => '后备仓库／管档案的人', 'to' => 'who', 'link' => ['trace', 'valve']],
        ],
        'concl' => [
            'who' => ['name' => '夜里有人用过这条水路', 'need' => 2, 'of' => ['trace', 'valve', 'ledger'],
                      'text' => '潮痕、关阀比对、缺页的单据——指向同一个人，用的是基地的水。'],
        ],
        'paths' => [
            'listen' => ['name' => '贴着墙听一遍', 'type' => 'check',
                'check' => ['skill' => '侦查'],
                'cost' => ['minutes' => 30],
                'gain' => ['clues' => ['trace'], 'stage' => 's1_听声', 'stage_text' => '看清了潮痕的来路'],
                'text' => ['你把手电贴着墙根，潮痕从西边一路渗过来。']],
            'valve' => ['name' => '进水务间关阀比对', 'type' => 'check',
                'check' => ['skill' => '机械维修'],
                'cost' => ['minutes' => 45, 'expose' => 1],
                'gain' => ['clues' => ['valve'], 'stage' => 's2_查明'],
                'gain_on_fail' => ['stage' => 's2_查明'],
                'text' => ['阀关死了，水声没停。你的手背上蹭了一道锈。']],
            'zhou' => ['name' => '找老周要上个月的维修单', 'type' => 'npc',
                'need' => ['who' => 'zhou'],
                'cost' => ['minutes' => 15],
                'gain' => ['clues' => ['ledger'], 'stage' => 's2_查明'],
                'text' => ['老周把台账摊开，手指停在跳号的那一页上。']],
        ],
        'newcomers' => [
            'tpl_nie' => ['name' => '老聂', 'role' => '水道工', 'room' => 'b3_water', 'phases' => ['上午', '下午'], 'entry' => 'n1',
                'nodes' => [
                    'n1' => ['text' => ['「你也是来看那声响的？」老聂把手电往西墙上一照。', '「这管子二十年前铺的，我熟。」'],
                        'choices' => [
                            ['label' => '问他：西边这一段谁动过', 'goto' => 'n2'],
                            ['label' => '把潮痕的走向说给他听', 'goto' => 'n3', 'cond' => ['flag' => 'clue_m_st_water_trace']],
                            ['label' => '先不打扰他', 'goto' => 'end']]],
                    'n2' => ['text' => ['他想了想：「上个月有个生面孔来借过工具，说是修管子。」', '「我问他哪个单位的，他没答。」'],
                        'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                        'effect' => ['clue' => ['m_st_water', 'valve']]],
                    'n3' => ['text' => ['他蹲下去摸墙根：「这儿是回水，不是漏水。有人夜里放过水。」',
                                         '「放水要开总阀——总阀在后勤那儿锁着。」'],
                        'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                        'effect' => ['clue' => ['m_st_water', 'ledger']]],
                    'end' => ['text' => ['他别好手电，接着巡管去了。'], 'choices' => []],
                ]],
        ],
        'dlg' => [
            'zhou' => ['ask_ledger' => [
                'label' => '追问：上个月的维修单',
                'stage' => 's2_查明',
                'cond' => ['flag' => 'clue_m_st_water_valve'],
                'node' => ['text' => ['老周把台账翻到上个月，手指停在跳号的地方：「两页……不在了。」',
                                      '「我那天下午不在，钥匙给过一个人。」'],
                           'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                           'effect' => ['clue' => ['m_st_water', 'ledger']]],
            ]],
        ],
        'actors' => ['zhou' => ['room' => 'b3_water', 'phases' => ['下午']]],
        'beats' => [
            'spread' => ['when' => ['clues' => ['trace']],
                'do' => ['narrate' => ['（西段的潮痕比昨天宽了一掌——夜里又走过一次水。）'], 'fact' => 'base.西段返潮加重']],
            'booked' => ['when' => ['concl' => 'who'],
                'do' => ['narrate' => ['（值班室把这条记进了本周的巡查重点。）'], 'stage' => 's3_记录']],
        ],
        'outcome' => ['done' => [
            'personal' => ['credits' => 5, 'money' => 80, 'rep' => ['base' => 2], 'flags' => ['st3_记录']],
            'facts' => [['base.西段水路查过了', true, 'once'], ['base.有人夜里用过水', true, 'once']],
            'actors' => ['zhou' => ['fav' => 1], 'tpl_nie' => ['fav' => 1]],
            'world' => ['note' => 'B3 西段的水路查清了：晚间有外人用过水。'],
            'archive' => ['title' => '【任务档案】负三层的水声', 'type' => '事件', 'level' => 'operative'],
        ]],
        'cleanup' => true,
    ]);

    /* ══════════ 4. 给我带包烟（周德海）══════════ */
    crrg_task_define('m_st_cigs', [
        'name' => '给我带包烟（周德海）', 'level' => '低', 'kind' => 'npc', 'min_lv' => 1,
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2f_armory', 'deadline_h' => 48,
        'prompt' => '老周支支吾吾，让你去军械维护室取个"东西"，别写单子上。他觉得这不是什么大事。',
        'steps' => [
            ['flag' => 'st4_取包', 'text' => '去军械维护室取他说的包裹', 'hint_room' => 'b2f_armory'],
            ['flag' => 'st4_问人', 'text' => '顺路问装备整备室的人是谁让他带的', 'hint_room' => 'b2f_gear'],
            ['flag' => 'st4_交包', 'text' => '把东西交给老周', 'hint_room' => 'b3_warehouse'],
        ],
        'reward' => ['credits' => 4, 'money' => 150], 'rep' => ['base' => 1],
        'settle_note' => '包裹里不是烟。老周收回去的时候耳朵有点红。',

        'trigger' => ['room' => 'b2f_armory'],
        'stages' => [
            's1_取包' => ['name' => '取包', 'text' => '拿到了那个包裹'],
            's2_问人' => ['name' => '问人', 'text' => '知道谁让他带的'],
            's3_交包' => ['name' => '交包', 'text' => '交回老周手里'],
        ],
        'clues' => [
            'weight' => ['title' => '包裹的分量不对', 'point' => '一只手能托住，可比一条烟沉——**里头是硬的**。',
                         'src' => '军械维护室（自己掂的）', 'where' => '军械维护室的取件格', 'to' => 'why'],
            'chit'   => ['title' => '取件格上的条子', 'point' => '登记条签的是别人的名字，笔迹却是老周的。',
                         'src' => '取件格', 'where' => '军械维护室的登记条', 'to' => 'why', 'link' => ['weight']],
            'gear'   => ['title' => '整备室的一句闲话', 'point' => '「他上礼拜也来拿过一次，没写单子。」',
                         'src' => '装备整备室', 'where' => '问装备整备室的人', 'to' => 'why', 'link' => ['weight', 'chit']],
        ],
        'concl' => [
            'why' => ['name' => '有人在用老周的名义调东西', 'need' => 2, 'of' => ['weight', 'chit', 'gear'],
                      'text' => '分量不对、条子代签、不是第一次——老周是被借了名字的人，也可能是同谋。'],
        ],
        'paths' => [
            'weigh' => ['name' => '在取件格前掂一掂、看条子', 'type' => 'check',
                'check' => ['skill' => '侦查'],
                'cost' => ['minutes' => 15],
                'gain' => ['clues' => ['weight', 'chit'], 'stage' => 's1_取包'],
                'text' => ['条子上的名字不是他的笔迹——你认得他签字时那个歪的横。']],
            'gear'  => ['name' => '顺路问整备室的人', 'type' => 'npc',
                'need' => ['who' => 'wei'],
                'cost' => ['minutes' => 20],
                'gain' => ['clues' => ['gear'], 'stage' => 's2_问人'],
                'text' => ['老魏笑了一声：「他上礼拜也来过，没写单子。」']],
            'blind' => ['name' => '不问，直接交给他', 'type' => 'quick',
                'cost' => ['minutes' => 10],
                'gain' => ['stage' => 's3_交包', 'stage_text' => '你把东西交了回去'],
                'ending' => 'blind',
                'text' => ['你什么也没问。老周接过去的时候，耳朵有点红。']],
        ],
        'dlg' => [
            'zhou' => ['ask_pack' => [
                'label' => '追问：包裹里到底是什么',
                'stage' => 's2_问人',
                'cond' => ['flag' => 'clue_m_st_cigs_chit'],
                'node' => ['text' => ['他把包裹往柜子里塞，塞了两次才塞进去。',
                                      '「……不是烟。是别人托我收的东西。」', '「别写单子，我记着你的情。」'],
                           'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                           'effect' => ['clue' => ['m_st_cigs', 'gear']]],
            ]],
            'wei' => ['ask_gear' => [
                'label' => '追问：老周上礼拜拿了什么',
                'node' => ['text' => ['老魏把手里的卡尺放下：「他自己不写单子，我有什么办法。」'],
                           'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                           'effect' => ['clue' => ['m_st_cigs', 'gear']]],
            ]],
        ],
        'beats' => [
            'again' => ['when' => ['clues' => ['chit']],
                'do' => ['narrate' => ['（取件格里又多了一张条子，同一个名字。）']]],
        ],
        'outcome' => [
            'done' => [
                'personal' => ['credits' => 4, 'money' => 150, 'rep' => ['base' => 1], 'flags' => ['st4_交包']],
                'facts' => [['base.老周替人收过东西', true, 'once']],
                'actors' => ['zhou' => ['fav' => 1, 'flag' => 'zhou_记你的情']],
                'world' => ['note' => '老周替人收了一件没登记的东西——这次被你看见了。'],
                'archive' => ['title' => '【任务档案】给我带包烟', 'type' => '事件', 'level' => 'operative'],
            ],
            'blind' => [
                'personal' => ['credits' => 3, 'money' => 150, 'rep' => ['base' => 1], 'flags' => ['st4_交包']],
                'facts' => [['base.老周替人收过东西', true, 'once']],
                'actors' => ['zhou' => ['fav' => 2]],
                'world' => ['note' => '你没问。老周记下了这一点。'],
                'archive' => ['title' => '【任务档案】给我带包烟（没问）', 'type' => '事件', 'level' => 'operative'],
            ],
        ],
        'cleanup' => true,
    ]);

    /* ══════════ 5. 值班表上多了一个名字（中型）══════════ */
    crrg_task_define('m_st_roster', [
        'name' => '值班表上多了一个名字', 'level' => '中', 'kind' => 'base', 'min_lv' => 3,
        'pass_rooms' => ['b1_archive', 'b2b_intel', 'b2b_comm', 'b2b_hall'],
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b1_archive', 'deadline_h' => 120,
        'prompt' => '本周值班表上多出一个岗位名：周四执勤长。名单里没人认领这个名字。请核对后写成记录。',
        'steps' => [
            ['flag' => 'st5_底稿', 'text' => '到档案室调值班表底稿与人员名录', 'hint_room' => 'b1_archive'],
            ['flag' => 'st5_比对', 'text' => '在情报分析室做交叉比对', 'hint_room' => 'b2b_intel'],
            ['flag' => 'st5_登记', 'text' => '查通讯室的登记簿', 'hint_room' => 'b2b_comm'],
            ['flag' => 'st5_记录', 'text' => '回值班板写记录', 'hint_room' => 'b2b_hall'],
        ],
        'reward' => ['credits' => 5, 'money' => 120], 'rep' => ['base' => 2],
        'settle_note' => '记录写成了《值班表异常人员项》。你把它挂在了待核实栏。',

        'trigger' => ['room' => 'b1_archive'],
        'stages' => [
            's1_底稿' => ['name' => '调底稿', 'text' => '把底稿调出来了'],
            's2_比对' => ['name' => '比对', 'text' => '在情报室对上了'],
            's3_登记' => ['name' => '查登记', 'text' => '翻了通讯室的登记簿'],
            's4_记录' => ['name' => '记录', 'text' => '写成了异常人员项'],
        ],
        'clues' => [
            'erased' => ['title' => '被涂掉两次的名字', 'point' => '底稿上同一个位置被涂过两次，第二遍用的是**另一种笔**。',
                         'src' => '档案室底稿', 'where' => '档案室的值班表底稿', 'to' => 'who'],
            'roster' => ['title' => '名录里少的那一页', 'point' => '人员名录按编号装订，中间**缺了一页**，编号跳号。',
                         'src' => '情报分析室比对', 'where' => '情报分析室（做交叉比对）', 'to' => 'who', 'link' => ['erased']],
            'book'   => ['title' => '登记簿上的笔迹', 'point' => '通讯室登记簿里，那个名字只出现过一次，**笔迹是值班室的人**。',
                         'src' => '通讯室登记簿', 'where' => '通讯室的登记簿', 'to' => 'who', 'link' => ['erased', 'roster']],
        ],
        'concl' => [
            'who' => ['name' => '"周四执勤长"是有人给自己留的位置', 'need' => 2, 'of' => ['erased', 'roster', 'book'],
                      'text' => '涂改、缺页、笔迹——有人想在排班表上先占一个位置，再让名字自然出现。'],
        ],
        'paths' => [
            'draft' => ['name' => '对照底稿与名录', 'type' => 'check',
                'check' => ['skill' => '图书馆使用'],
                'cost' => ['minutes' => 60],
                'gain' => ['clues' => ['erased'], 'stage' => 's1_底稿'],
                'text' => ['底稿上那处涂改，你对着灯看了很久。']],
            'cross' => ['name' => '在情报室做交叉比对', 'type' => 'check',
                'check' => ['skill' => '会计'],
                'cost' => ['minutes' => 50, 'expose' => 1],
                'gain' => ['clues' => ['roster'], 'stage' => 's2_比对'],
                'gain_on_fail' => ['stage' => 's2_比对'],
                'text' => ['编号一排一排对下来，中间那个缺口很扎眼。']],
            'book' => ['name' => '翻通讯室登记簿', 'type' => 'check',
                'check' => ['skill' => '侦查'],
                'cost' => ['minutes' => 40],
                'gain' => ['clues' => ['book'], 'stage' => 's3_登记'],
                'text' => ['那个名字只出现过一次，笔迹你认得。']],
            'ask' => ['name' => '问康明远排班是谁定的', 'type' => 'npc',
                'need' => ['who' => 'kang', 'fav' => 1],
                'cost' => ['minutes' => 20],
                'gain' => ['clues' => ['book'], 'stage' => 's2_比对'],
                'text' => ['康明远没抬头：「排班表……我交出去过一版。」']],
        ],
        'dlg' => [
            'kang' => ['ask_roster' => [
                'label' => '追问：周四执勤长是谁写的',
                'stage' => 's2_比对',
                'node' => ['text' => ['他终于把笔放下：「那一版不是我最后交的那一版。」',
                                      '「中间有人动过。」'],
                           'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                           'effect' => ['clue' => ['m_st_roster', 'book']]],
            ]],
        ],
        'beats' => [
            'again2' => ['when' => ['clues' => ['erased']],
                'do' => ['narrate' => ['（下周的排班表上，同一行又出现了那个岗位名。）']]],
            'checked' => ['when' => ['concl' => 'who'],
                'do' => ['narrate' => ['（值班室把这一项挂进了"待核实"。）'], 'stage' => 's4_记录']],
        ],
        'outcome' => ['done' => [
            'personal' => ['credits' => 5, 'money' => 120, 'rep' => ['base' => 2], 'flags' => ['st5_记录']],
            'facts' => [['base.值班表被改过', true, 'once']],
            'actors' => ['kang' => ['fav' => 1]],
            'world' => ['note' => '值班表上那个多出来的名字，被挂进了待核实栏。'],
            'archive' => ['title' => '【任务档案】值班表异常人员项', 'type' => '事件', 'level' => 'operative'],
        ]],
        'cleanup' => true,
    ]);
}, 32);
