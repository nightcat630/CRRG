<?php
/**
 * 《在册》第五幕 · 编制（missions/task-zaice-act5.php，R7，2026-10-14）
 * ── 结局按**轨道**开（20 号文映射表）──
 *   人说（ren）      → 顶替 ／ 划净
 *   岗位说（gangwei）→ 入册 ／ 焊死
 *   混着选（mixed）  → 追加 报上去
 *   没做过推论（''） → **只给兜底：挂起**
 * 每个结局都有代价 ✓ 不设全赢 ✗；结算走引擎 outcome；善后撤对话与新人 ✓
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_task_define')) return;
    $CHAIN = ['m_zaice1', 'm_zaice2', 'm_zaice3', 'm_zaice4'];

    crrg_task_define('m_zaice5', [
        'name' => '在册 · 五：编制', 'level' => '中', 'kind' => 'base', 'min_lv' => 1, 'hidden' => true,
        'chain_no' => 5, 'chain_total' => 5,
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall', 'deadline_h' => 168,
        'pass_rooms' => ['b2b_hall', 'b2b_duty', 'b1_archive', 'b4_main', 'b4_c2'],
        'prompt' => '值班室把这件事挂上了最后一次会签。表在桌上摊着，笔也摆好了——你签什么，它就成什么。',
        'steps' => [],
        'reward' => ['credits' => 12, 'money' => 300], 'rep' => ['base' => 4],
        'settle_note' => '值班表收进了档案袋。袋口那条线，是两个人一起贴的。',

        'trigger' => ['room' => 'b2b_hall', 'flags' => ['stage_m_zaice4_s4_抉择']],
        'stages' => [
            's1_会签' => ['name' => '会签', 'text' => '笔在桌上，表在你面前'],
        ],

        'clues' => [
            'blank' => ['title' => '留白的那一格',
                'point' => '周四那一行还空着。纸面比别处干净——**是被人擦过的**。',
                'src' => '值班表', 'where' => '值班板', 'to' => 'end'],
            'pen' => ['title' => '桌上的笔',
                'point' => '钢笔是值班室那支，笔帽朝外摆着。摆笔的人知道你会坐下。',
                'src' => '值班室', 'where' => '作战值班室', 'to' => 'end'],
            'manila' => ['title' => '档案袋口',
                'point' => '旁边的档案袋口贴了一半，另一半留着——**要两个人一起贴**。',
                'src' => '值班室', 'where' => '值班室桌上', 'to' => 'end'],
        ],
        'concl' => [
            'end' => ['name' => '这一页要由你落笔', 'need' => 1, 'of' => ['blank', 'pen', 'manila'], 'text' => '签什么，它就是什么。'],
        ],
        'infer' => [
            'final' => [
                'hint' => '这到底算什么',
                'of' => [['blank', 'pen'], ['blank', 'manila'], ['pen', 'manila']],
                'text' => "笔摆好了，袋子留了一半口。\n这两样东西摆在一起的时候，意思只有一个：**他们要你给一个说法**——给一个能写进册子、以后谁都能翻到的说法。",
                'options' => [
                    1 => ['label' => '它是一个岗位——收容它，给它一个受控的位置', 'track' => 'gangwei', 'unlock' => ['flags' => ['zaice_定在册']], 'note' => '你把这件事定成：在册。'],
                    2 => ['label' => '这是一群人在替一个人打掩护', 'track' => 'ren', 'unlock' => ['flags' => ['zaice_定人事']], 'note' => '你把这件事定成：人事。'],
                ],
            ],
        ],

        /* ── 结局（按轨道开；不符合轨道的路会给出明确理由 ✓）── */
        'paths' => [
            'ru' => ['name' => '登记为收容物（O-11-07「岗位」）', 'type' => 'quick',
                'need' => ['infer_lean' => [$CHAIN, 'gangwei'], 'flags' => ['stage_m_zaice5_s1_会签']],
                'cost' => ['minutes' => 30],
                'gain' => ['stage' => 's1_会签', 'flags' => ['zaice_end_ru'], 'narrate' => [
                    '你把编号写在收容册上：**O-11-07「岗位」**。',
                    '从今天起，那一行不再是"待认领"，而是"在册"。',
                    '值班表照旧每周排一行——只是从此**要两个人签字**：你，和一个不在的人。',
                ], 'fact' => ['base.O-11-07_在册', true, 'once']],
                'ending' => 'ru'],

            'han' => ['name' => '把整条编制关掉（焊死）', 'type' => 'quick',
                'need' => ['infer_lean' => [$CHAIN, 'gangwei'], 'flags' => ['stage_m_zaice5_s1_会签']],
                'cost' => ['minutes' => 60, 'expose' => 1],
                'gain' => ['flags' => ['zaice_end_han'], 'narrate' => [
                    '你把 B4 那间屋子从编制上划掉，门焊上了。',
                    '**那扇门以后不开。** 被它碰过的人，记忆也回不来。',
                    '值班表干净了，只是 B4 西段从此少一间屋。',
                ], 'fact' => ['base.B4_西段封了', true, 'once']],
                'ending' => 'han'],

            'ding' => ['name' => '找个人顶上去（顶替）', 'type' => 'quick',
                'need' => ['infer_lean' => [$CHAIN, 'ren'], 'flags' => ['stage_m_zaice5_s1_会签']],
                'cost' => ['minutes' => 40, 'san' => 1],
                'gain' => ['flags' => ['zaice_end_ding'], 'narrate' => [
                    '你在那一行填了一个名字——一个在编、但已经不在场的人。',
                    '表上完整了。**那一行的名字，不属于你要找的那个人。**',
                    '值班室的灯还亮着。',
                ], 'fact' => ['base.那一行有了名字', true, 'once']],
                'ending' => 'ding'],

            'jing' => ['name' => '把人从册上划净', 'type' => 'quick',
                'need' => ['infer_lean' => [$CHAIN, 'ren'], 'flags' => ['stage_m_zaice5_s1_会签']],
                'cost' => ['minutes' => 45, 'san' => 1],
                'gain' => ['flags' => ['zaice_end_jing'], 'narrate' => [
                    '你把那一行划净，连他的名字一起。',
                    '第二天值班表很干净，干净得像从来没多过谁。',
                    '只是**档案柜里少了一页**——你记得原来它在哪儿。',
                ], 'fact' => ['base.值班表干净了', true, 'once']],
                'ending' => 'jing'],

            'bao' => ['name' => '连同两种解释一起报上去', 'type' => 'quick',
                'need' => ['infer_lean' => [$CHAIN, 'mixed'], 'flags' => ['stage_m_zaice5_s1_会签']],
                'cost' => ['minutes' => 50],
                'gain' => ['flags' => ['zaice_end_bao'], 'narrate' => [
                    '你把两种解释都写进报告：一个岗位，或者一群人。',
                    '交上去的时候，你听见自己的声音很平。',
                    '**你交出去了一份没人能核实的报告。它还在册。**',
                ], 'fact' => ['base.在册_已上报', true, 'once']],
                'ending' => 'bao'],

            'gua' => ['name' => '什么都不签，把这一项挂起', 'type' => 'quick',
                'need' => ['flags' => ['stage_m_zaice5_s1_会签']],
                'cost' => ['minutes' => 10],
                'gain' => ['flags' => ['zaice_end_gua'], 'narrate' => [
                    '你把笔放回原处，那一格留给下一班。',
                    '**你没看懂它——它继续在册。**',
                    '轮到你值夜的时候，表上你的名字后面，还空着一个位置。',
                ]],
                'ending' => 'gua'],
        ],

        'beats' => [
            'sit' => ['when' => ['clues' => ['blank']],
                'do' => ['narrate' => ['（值班室里很静。桌上的笔没动，但你知道它在等你。）']]],
        ],

        'outcome' => [
            'ru' => ['personal' => ['credits' => 12, 'money' => 300, 'rep' => ['base' => 4], 'flags' => ['zaice_done_ru']],
                     'facts' => [['base.O-11-07_在册', true, 'once']],
                     'world' => ['note' => '第 7 个名字从"待认领"改成"在册"：O-11-07「岗位」。'],
                     'archive' => ['title' => '【任务档案】在册 · 结局：入册', 'type' => '收容', 'level' => 'operative']],
            'han' => ['personal' => ['credits' => 10, 'money' => 260, 'rep' => ['base' => 2], 'flags' => ['zaice_done_han']],
                      'facts' => [['base.B4_西段封了', true, 'once']],
                      'world' => ['note' => '编制上划掉一间屋，门焊死了。'],
                      'archive' => ['title' => '【任务档案】在册 · 结局：焊死', 'type' => '事件', 'level' => 'operative']],
            'ding' => ['personal' => ['credits' => 8, 'money' => 200, 'rep' => ['base' => 1], 'flags' => ['zaice_done_ding'], 'san' => -1],
                       'facts' => [['base.那一行有了名字', true, 'once']],
                       'world' => ['note' => '那一行填了一个不在场的人。'],
                       'archive' => ['title' => '【任务档案】在册 · 结局：顶替', 'type' => '事件', 'level' => 'operative']],
            'jing' => ['personal' => ['credits' => 8, 'money' => 200, 'rep' => ['base' => 1], 'flags' => ['zaice_done_jing'], 'san' => -1],
                       'facts' => [['base.值班表干净了', true, 'once']],
                       'world' => ['note' => '值班表划净了，档案柜少了一页。'],
                       'archive' => ['title' => '【任务档案】在册 · 结局：划净', 'type' => '事件', 'level' => 'operative']],
            'bao' => ['personal' => ['credits' => 9, 'money' => 220, 'rep' => ['base' => 2], 'flags' => ['zaice_done_bao']],
                      'facts' => [['base.在册_已上报', true, 'once']],
                      'world' => ['note' => '连同两种解释一起报上去了。'],
                      'archive' => ['title' => '【任务档案】在册 · 结局：上报', 'type' => '报告', 'level' => 'head']],
            'gua' => ['personal' => ['credits' => 4, 'money' => 100, 'rep' => ['base' => 0], 'flags' => ['zaice_done_gua']],
                      'facts' => [['base.在册_挂起', true, 'once']],
                      'world' => ['note' => '那一项挂起了，留给下一班。'],
                      'archive' => ['title' => '【任务档案】在册 · 结局：挂起', 'type' => '事件', 'level' => 'operative']],
            /* 期限到点：什么都没签，值班室自己划掉 */
            'on_expire' => ['narrate' => ['（会签窗口过了。值班室自己把那一项划掉了——第二天表上什么都没多。）'],
                            'fact' => ['base.在册_逾期自行划掉', true]],
        ],
        'cleanup' => true,
    ]);
}, 36);
