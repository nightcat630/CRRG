<?php
/**
 * 《在册》· 第三幕：夜班（missions/task-zaice-act3.php，R6，2026-10-14）
 * ------------------------------------------------------------
 * 规格：17 号文（第三幕）／19 号文（KP 口吻）／20 号文（推论 v2）／21 号文（战斗：**输了不是死，是被排进班表**）
 * 一幕一个问题：**「它值夜班的时候，到底在干什么？」**
 * 本幕**有一处对抗点**：空制服（type='fight'，带 on_win/on_lose ✓）
 *   —— 同时留三条不打的解法：**报班／绕货运电梯／关灯** ✓（门禁会查"每个对抗点必须还有非战斗解法"）
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_task_define')) return;

    crrg_task_define('m_zaice3', [
        'name' => '在册 · 三：夜班', 'level' => '中', 'kind' => 'base', 'min_lv' => 1,
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b4_main', 'deadline_h' => 96,
        'prompt' => '夜里的 B4 有别人在走。巡检牌被签过了，笔迹不是你的；有几扇门开过，门禁里没有记录。',
        'reward' => ['credits' => 6, 'money' => 90], 'rep' => ['base' => 2],
        'settle_flag' => 'zaice3_verdict',
        'chain_no' => 3, 'chain_total' => 5, 'chain_next' => 'm_zaice4',

        'trigger' => ['flags' => ['stage_m_zaice2_s4_那个人']],

        'stages' => [
            's1_巡检' => ['name' => '巡检', 'text' => '你看见了它的路线'],
            's2_监控' => ['name' => '监控', 'text' => '你在录像里找到了它'],
            's3_开门' => ['name' => '开门', 'text' => '你知道它开过哪扇门'],
            's4_撞上' => ['name' => '撞上', 'text' => '你在走廊里和它错身'],
        ],

        'clues' => [
            '签到' => ['title' => '签好的巡检牌',
                'point' => '每一格都签到了，笔迹和值班室那块同一只手；比在编的人签得还全。',
                'src' => 'B4 走廊 · 巡检牌', 'where' => 'B4 走廊的巡检牌', 'to' => '在干什么'],
            '路线' => ['title' => '它走的路线比规程多一段',
                'point' => '规程到西头就结束，它的签名多出一格——那格对应的是**检修井**。',
                'src' => 'B4 · 巡检牌背面', 'where' => 'B4 巡检牌背面', 'to' => '在干什么', 'link' => ['签到']],
            '录像' => ['title' => '监控里的那个影子',
                'point' => '02:11 的画面里有一团比周围暗的东西，经过镜头时**挡住了两秒**，可走道上没有人。',
                'src' => '作战值班室 · 监控', 'where' => '作战值班室（监控机位）', 'to' => '在干什么', 'link' => ['签到']],
            '笔迹' => ['title' => '笔迹比对',
                'point' => '两个月的巡检记录，笔压、收笔、间距都对得上——同一只手，一笔没抖。',
                'src' => '档案室 · 旧巡检记录', 'where' => '档案室（旧巡检记录）', 'to' => '在干什么', 'link' => ['录像']],
            '门禁' => ['title' => '门禁日志里的 0.4 秒',
                'point' => '两条进门的记录只隔 0.4 秒——两个人一起进的速度，可画面里只有一双脚。',
                'src' => '门禁日志', 'where' => '作战值班室（门禁日志）', 'to' => '开哪扇门', 'link' => ['录像']],
            '那扇门' => ['title' => '那扇空屋子的门',
                'point' => '门后不存货、不住人；可它进去待了很久，出来时门是轻轻带上的——不是风，是手。',
                'src' => 'B4 · 西头那扇门', 'where' => 'B4 西头那扇门（空屋子）', 'to' => '开哪扇门', 'link' => ['门禁', '路线']],
        ],

        'infer' => [
            '在干什么' => [
                'hint' => '它在夜班里做什么',
                'of' => [['签到', '录像'], ['签到', '笔迹'], ['录像', '笔迹'], ['路线', '签到']],
                'text' => [
                    '你把巡检牌和监控机位摆在一起看。',
                    '它的每一格都签到了——比在编的人签得还全；录像里那团暗影经过镜头时挡住两秒，走道上没有人。'
                    . '而规程到西头就结束，它的签名却多出一格：那一格对着检修井。',
                    '一个人夜里上班，签满每一格，是怕漏掉什么；它多签的那一格，是它**真正想去的**。',
                ],
                'options' => [
                    1 => ['label' => '它在值夜班——而且它比我们任何一个人都认真', 'track' => 'gangwei',
                          'note' => '你在巡检牌那多出来的一格上画了圈。',
                          'unlock' => ['flags' => ['zaice_蹲它', 'zaice_门禁'], 'clues' => ['门禁']]],
                    2 => ['label' => '它在找什么——巡检是它的掩护', 'track' => 'ren',
                          'note' => '你把检修井那三个字描了一遍。',
                          'unlock' => ['flags' => ['zaice_检修井', 'zaice_那扇门'], 'clues' => ['路线']]],
                ],
            ],
            '开哪扇门' => [
                'hint' => '它开的是哪扇门',
                'of' => [['门禁', '录像'], ['门禁', '笔迹'], ['路线', '门禁'], ['那扇门', '门禁'], ['那扇门', '录像'], ['那扇门', '路线']],
                'text' => [
                    '门禁日志上两条进门记录只隔 0.4 秒——两个人一起走的速度；画面里只有一双脚。',
                    '那扇门后面是空的：不存货、不住人。可它在里面待了很久，出来的时候，'
                    . '门是**轻轻带上的**——不是风，是手。',
                ],
                'options' => [
                    1 => ['label' => '它进去不是为了拿东西，是为了看看', 'track' => 'gangwei',
                          'note' => '你在那扇门的编号下画了一道横线。',
                          'unlock' => ['flags' => ['zaice_那扇门', 'zaice_带班'], 'clues' => ['那扇门']]],
                    2 => ['label' => '门后有它认的东西——它在守着什么', 'track' => 'ren',
                          'note' => '你在门上记了一行：别焊。',
                          'unlock' => ['flags' => ['zaice_守门', 'zaice_焊门'], 'clues' => ['门禁']]],
                ],
            ],
        ],

        'paths' => [
            'n_board' => ['name' => '夜里去 B4 看巡检牌', 'type' => 'check',
                'check' => ['skill' => '侦查'], 'cost' => ['minutes' => 40, 'san' => 1],
                'gain' => ['clues' => ['签到'], 'stage' => 's1_巡检',
                           'narrate' => ['（牌上每一格都签过了。你的名字不在上面。）']],
                'gain_on_fail' => ['clues' => ['签到'], 'stage' => 's1_巡检', 'cost_on_fail' => ['minutes' => 40, 'san' => 1]]],
            'n_back' => ['name' => '翻巡检牌背面', 'type' => 'check',
                'check' => ['skill' => '侦查'], 'cost' => ['minutes' => 20],
                'gain' => ['clues' => ['路线'], 'stage' => 's1_巡检'], 'gain_on_fail' => ['clues' => ['路线']]],
            'n_cam' => ['name' => '调监控机位', 'type' => 'check',
                'check' => ['skill' => '侦查'], 'cost' => ['minutes' => 50, 'expose' => 1],
                'gain' => ['clues' => ['录像'], 'stage' => 's2_监控'], 'gain_on_fail' => ['clues' => ['录像'], 'stage' => 's2_监控']],
            'n_bio' => ['name' => '比对两个月的巡检笔迹', 'type' => 'check',
                'check' => ['skill' => '会计'], 'cost' => ['minutes' => 60],
                'gain' => ['clues' => ['笔迹'], 'stage' => 's2_监控'], 'gain_on_fail' => ['clues' => ['笔迹']]],
            'n_log' => ['name' => '调门禁日志', 'type' => 'check',
                'check' => ['skill' => '侦查'], 'cost' => ['minutes' => 30, 'expose' => 1],
                'gain' => ['clues' => ['门禁'], 'stage' => 's3_开门'], 'gain_on_fail' => ['clues' => ['门禁'], 'stage' => 's3_开门']],
            'n_door' => ['name' => '去看那扇门（空屋子）', 'type' => 'check',
                'check' => ['skill' => '听'], 'cost' => ['minutes' => 45, 'san' => 1],
                'gain' => ['clues' => ['那扇门'], 'stage' => 's3_开门'],
                'gain_on_fail' => ['clues' => ['那扇门'], 'cost_on_fail' => ['minutes' => 45, 'san' => 1]]],

            /* ── 不打的解法一：**报班**（它认这个）── */
            'n_report' => ['name' => '在对讲机里报一遍自己的班次', 'type' => 'check',
                'check' => ['skill' => '说服'], 'cost' => ['minutes' => 10],
                'gain' => ['stage' => 's4_撞上', 'flags' => ['zaice_报班过'],
                           'narrate' => ['（你报完班次，那件制服停了一下，慢慢靠回了衣架。）']],
                'gain_on_fail' => ['stage' => 's4_撞上', 'cost_on_fail' => ['minutes' => 10, 'san' => 1]]],
            /* ── 不打的解法二：**绕路**（多花时间，少一条线索）── */
            'n_detour' => ['name' => '改走货运电梯那一侧', 'type' => 'check',
                'check' => ['skill' => '导航'], 'cost' => ['minutes' => 30],
                'gain' => ['stage' => 's4_撞上', 'flags' => ['zaice_绕过'],
                           'narrate' => ['（你绕了一圈回到值班室。这一趟什么也没看见——你有点庆幸。）']],
                'gain_on_fail' => ['stage' => 's4_撞上', 'cost_on_fail' => ['minutes' => 30]]],
            /* ── 不打的解法三：**关灯**（它看不见你，但灯后来自已亮）── */
            'n_lights' => ['name' => '把这条廊的照明拉掉', 'type' => 'check',
                'check' => ['skill' => '电工'], 'cost' => ['minutes' => 25, 'expose' => 1],
                'gain' => ['stage' => 's4_撞上', 'flags' => ['zaice_关灯过'], 'fact' => ['base.B4灯自己亮了', true, 'once'],
                           'narrate' => ['（灯灭了。你在黑里走过去，听见衣架那边轻轻响了一下。）']],
                'gain_on_fail' => ['stage' => 's4_撞上', 'flags' => ['zaice_关灯过'], 'cost_on_fail' => ['minutes' => 25, 'expose' => 1, 'san' => 1]]],

            /* ── 对抗点：空制服（**赢了掉线索，输了被排进班表**）── */
            'n_fight' => ['name' => '动手（它挡在走廊中间）', 'type' => 'fight',
                'on_win'  => ['clues' => ['那扇门'], 'stage' => 's4_撞上', 'flags' => ['zaice_打过制服'],
                              'san' => 1, 'expose' => 1,
                              'fact' => ['base.B4夜班出过事', true, 'once'],
                              'narrate' => ['（制服落在地上，是空的。你把它捡起来的时候，袖口还温着。）']],
                'on_lose' => ['flags' => ['enlist_m_zaice3', 'zaice_被排班'],
                              'fact' => ['base.有人被排进了班表', true, 'once'],
                              'narrate' => ['（你醒在自己床上。终端里多了一条通知：本周四 执勤长——已认领。）',
                                            '（名字是你的。）']]],
        ],

        'beats' => [
            'again' => ['when' => ['clues' => ['签到']],
                'do' => ['narrate' => ['（第二天早上，巡检牌上那一格又签好了。）'], 'fact' => 'base.B4巡检牌自己签好']],
            'clock1' => ['when' => ['clues' => ['录像']],
                'do' => ['narrate' => ['（今晚 02:11，同一台机位又暗了两秒。）']]],
        ],

        'outcome' => ['done' => [
            'personal' => ['credits' => 6, 'money' => 90, 'rep' => ['base' => 2], 'flags' => ['zaice3_verdict']],
            'facts' => [['base.在册_它在值夜班', true, 'once'], ['base.在册_那扇门后面是空的', true, 'once']],
            'actors' => ['kang' => ['fav' => 1]],
            'world' => ['note' => 'B4 的夜班有人在值：签得比谁都全，多走一格。'],
            'archive' => ['title' => '【任务档案】在册·夜班', 'type' => '事件', 'level' => 'operative'],
        ]],
        'cleanup' => true,
    ]);
}, 35);
