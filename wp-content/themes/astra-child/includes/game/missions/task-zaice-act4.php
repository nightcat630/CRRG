<?php
/**
 * 《在册》第四幕 · 名字进人（missions/task-zaice-act4.php，R7，2026-10-14）
 * ── 幕四：它开始要人。有人开始值夜、白天记不住。 ──
 * 玩家界面不出 steps、不出"结论" ✓ 只有缘起 ＋ 笔记（线索/推断）
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_task_define')) return;

    crrg_task_define('m_zaice4', [
        'name' => '在册 · 四：名字进人', 'level' => '中', 'kind' => 'base', 'min_lv' => 1, 'hidden' => true,
        'chain_no' => 4, 'chain_total' => 5, 'chain_next' => 'm_zaice5',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b1_med', 'deadline_h' => 120,
        'pass_rooms' => ['b1_med', 'b2b_duty', 'b1_archive', 'b4_main', 'b4_c2'],
        'prompt' => '夜班那个人请了病假。白大夫的体检单只有一行不对：他记得的夜班，比实际上多一天。',
        'steps' => [],
        'reward' => ['credits' => 8, 'money' => 200], 'rep' => ['base' => 3],
        'settle_note' => '你把他从班表上摘了下来——用的不是橡皮。',

        'trigger' => ['room' => 'b1_med', 'flags' => ['stage_m_zaice3_s4_撞上']],
        'stages' => [
            's1_体检' => ['name' => '体检', 'text' => '你看见了那张体检单'],
            's2_追问' => ['name' => '追问', 'text' => '你问到了他自己不承认的事'],
            's3_旧档' => ['name' => '旧档', 'text' => '十年前也发生过一次'],
            's4_抉择' => ['name' => '抉择', 'text' => '你得决定先救谁'],
        ],

        'clues' => [
            '体检' => ['title' => '多出来的那一天',
                'point' => '睡眠时长正常、指标正常。只有一项不对：**他记得的夜班，比实际上多一天**。',
                'src' => '白大夫的体检单', 'where' => '医疗室的体检单', 'to' => '吸'],
            'he_says' => ['title' => '他记得每一个巡检点',
                'point' => '问他夜里做了什么，他能一处一处背出来，连走廊尽头的声级计读数都记得。',
                'src' => '问他本人', 'where' => '作战值班室（他白天也待在那儿）', 'to' => '吸', 'link' => ['体检']],
            'second' => ['title' => '班表上的第二个名字',
                'point' => '周四那一行下面，多了一个名字。**是他的。写得很轻，像是怕人看见。**',
                'src' => '值班表', 'where' => '值班板／作战值班室', 'to' => '吸', 'link' => ['体检', 'he_says']],
            'old'  => ['title' => '十年前的旧档',
                'point' => '同样的记录，十年前有过一次。结局那栏写着三个字：**调离**。',
                'src' => '档案室旧档', 'where' => '档案室（旧档柜）', 'to' => '吸', 'link' => ['second']],
            'bai'  => ['title' => '白大夫见过第二个',
                'point' => '「我十年里见过第二个这样的。第一个后来调离了——档案上写的是调离。」',
                'src' => '白大夫', 'where' => '医疗室', 'to' => '吸', 'link' => ['old']],
            'roster2' => ['title' => '编制里已经有他的名字',
                'point' => '人员名录里，他那一栏的编号**和"执勤长"那一行连号**。',
                'src' => '人员名录', 'where' => '档案室', 'to' => '吸', 'link' => ['second', 'old']],
            'hand' => ['title' => '他夜里签的字在变',
                'point' => '今晚他签的巡检牌，起笔那一顿，和登记簿上那行字一模一样。',
                'src' => '夜里的巡检牌', 'where' => 'B4 走廊的巡检牌', 'to' => '吸', 'link' => ['he_says']],
            'counting' => ['title' => '他在数东西',
                'point' => '你看见他数门、数灯、数台阶。数错了就重来。问他数什么，他说"数清楚才轮得到我睡"。',
                'src' => '看着他', 'where' => '作战值班室／走廊', 'to' => '吸', 'link' => ['hand']],
        ],
        'concl' => [
            '吸' => ['name' => '这个位子会把人吸进去', 'need' => 2, 'of' => ['体检', 'he_says', 'second', 'old', 'bai', 'roster2', 'hand', 'counting'],
                    'text' => '两条对上了：班表在长他的名字，旧档里那个人被"调离"了。'],
        ],

        /* 推论槽位：两种读法（第五幕按它分叉 ✓） */
        'infer' => [
            'who' => [
                'hint' => '他到底算不算自己人',
                'of' => [['second', 'hand'], ['second', 'counting'], ['hand', 'counting']],
                'text' => "班表上那行字很轻，像是怕人看见；可他夜里签的字，起笔那一顿跟登记簿上一模一样。\n"
                        . "要是有人替它写，就不会连顿笔都学；要是它自己长出来的，也不会怕人看见。",
                'options' => [
                    1 => ['label' => '是它把他拉进来了——他还在里面挣', 'track' => 'gangwei',
                          'unlock' => ['flags' => ['zaice_它拉人'], 'clues' => ['counting']], 'note' => '你现在信：这个位子在抓人。'],
                    2 => ['label' => '是他自己不肯回来——他喜欢那个岗位', 'track' => 'ren',
                          'unlock' => ['flags' => ['zaice_他自己要'], 'clues' => ['hand']], 'note' => '你现在信：他在往那边靠。'],
                ],
            ],
        ],

        'paths' => [
            'ask_bai' => ['name' => '找白大夫看那张单子', 'type' => 'npc',
                'need' => ['who' => 'bai'],
                'check' => ['skill' => '医学'],
                'cost' => ['minutes' => 30],
                'gain' => ['clues' => ['体检', 'bai'], 'stage' => 's1_体检', 'stage_text' => '你拿到了体检单'],
                'gain_on_fail' => ['clues' => ['bai'], 'stage' => 's1_体检'],
                'text' => ['她把单子推过来的时候，手指压在"夜班时长"那一栏上。']],
            'ask_him' => ['name' => '问他夜里到底做了什么', 'type' => 'check',
                'need' => ['flags' => ['stage_m_zaice4_s1_体检']],
                'check' => ['skill' => '说服'],
                'cost' => ['minutes' => 40, 'expose' => 1],
                'gain' => ['clues' => ['he_says'], 'stage' => 's2_追问'],
                'gain_on_fail' => ['clues' => ['counting'], 'stage' => 's2_追问'],
                'text' => ['他答得很顺。顺到你数了一下——他背了十一个巡检点，而这条廊只有十个。']],
            'old_files' => ['name' => '翻十年前的旧档', 'type' => 'check',
                'check' => ['skill' => '图书馆使用'],
                'cost' => ['minutes' => 60],
                'gain' => ['clues' => ['old', 'roster2'], 'stage' => 's3_旧档'],
                'gain_on_fail' => ['clues' => ['old'], 'stage' => 's3_旧档'],
                'text' => ['同一栏位、同一种字体，只是年份不一样。结局那栏写着：调离。']],
            'watch' => ['name' => '在走廊看着他值完一班', 'type' => 'check',
                'need' => ['hour' => [22, 5]],
                'check' => ['skill' => '侦查'],
                'cost' => ['minutes' => 90, 'san' => 1],
                'gain' => ['clues' => ['hand', 'counting'], 'stage' => 's2_追问'],
                'gain_on_fail' => ['clues' => ['hand']],
                'text' => ['他走得很稳。每一处都签，签完退半步看一眼——那是值班员的习惯，不是他的。']],
            'fix_row' => ['name' => '在班表上替他写"已换班"', 'type' => 'quick',
                'need' => ['flags' => ['stage_m_zaice4_s2_追问']],
                'cost' => ['minutes' => 20],
                'gain' => ['stage' => 's4_抉择', 'flags' => ['zaice4_swapped'], 'narrate' => ['（你按格式写了。写的时候手很稳。）']],
                'text' => ['值班表上他的那一行，被你换成了"已换班"。']],
            'brawl_him' => ['name' => '动手把他抬去医务室', 'type' => 'fight', 'nonlethal' => true,
                'need' => ['flags' => ['stage_m_zaice4_s2_追问']],
                'on_win' => ['clues' => ['hand'], 'stage' => 's4_抉择', 'flags' => ['zaice4_carried'], 'expose' => 1,
                             'narrate' => ['你把他按住了。他不还手，只是一直在说"我还要交班"。']],
                'on_lose' => ['flags' => ['enlist_m_zaice4'], 'narrate' => ['你被打倒在走廊上。醒来的时候，值班室的灯亮着。']],
                'text' => ['他往门那边退，手还护着巡检牌。']],
        ],
        'beats' => [
            'more' => ['when' => ['clues' => ['second']],
                'do' => ['narrate' => ['（今晚的巡检牌上，他的字更像值班员一分。）'], 'fact' => 'base.他的字在变']],
            'bai_warn' => ['when' => ['concl' => '吸'],
                'do' => ['narrate' => ['（白大夫把那张单子收进了抽屉，压在别的东西下面。）'], 'stage' => 's4_抉择']],
        ],
        'outcome' => ['done' => [
            'personal' => ['credits' => 8, 'money' => 200, 'rep' => ['base' => 3]],
            'facts' => [['base.十年前也发生过一次', true, 'once']],
            'actors' => ['bai' => ['fav' => 1]],
            'world' => ['note' => '体检单上多出来的那一天，被写进了待核实。'],
            'archive' => ['title' => '【任务档案】在册·四：名字进人', 'type' => '事件', 'level' => 'operative'],
        ]],
        'cleanup' => true,
    ]);
}, 36);
