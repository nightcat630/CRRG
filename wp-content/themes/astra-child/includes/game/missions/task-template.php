<?php
/**
 * ══════════════════════════════════════════════════════════════════════
 *  任务模板（照抄这个文件就能加新任务）
 *  ── 2026-10-14 · engine/README.md 有完整字段说明 ──
 *
 *  用法：
 *    1. 复制本文件 → missions/task-<你的任务>.php
 *    2. 改 id / 名字 / 内容
 *    3. 在 functions.php 挂上 require（跟其它 missions 文件一排）
 *    4. 跑 `php tools/verify-task.php`（定义完整性门禁）
 *    5. 跑四件套 + `php tools/verify-bus.php`
 *
 *  ⚠ 四条纪律（门禁会查）：
 *    · 任务必须有 `stages` 与 `cleanup`（善后）；新人物/注入一律走 newcomers/dlg，别手写注册 ✓
 *    · 线索只写声明（clues），登记交给引擎 ✓ 别在两处各写一份 ✗
 *    · 时间压力的代价只算**个人在线钟**（cost.minutes）✗ 绝不碰世界钟 ✓
 *    · 玩家可见文案不写内部键（stage_/clue_ 这类）✓
 * ══════════════════════════════════════════════════════════════════════
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_task_define')) return;

    crrg_task_define('m_tpl_example', [
        /* ── ① 老系统字段（面板/结算/门禁都认）── */
        'name'    => '模板样例：任务名', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
        'scene'   => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'deadline_h'  => 72,
        'prompt'  => '一句话说清：谁、让你干什么、为什么。',
        'steps'   => [['flag' => 'tpl_step1', 'text' => '第一步（带 hint_room 就指路）', 'hint_room' => 'b3_water']],
        'reward'  => ['credits' => 4, 'money' => 100], 'rep' => ['base' => 1],
        'settle_note' => '结算时的一句人话。',

        /* ── ② 触发：怎么被拉起来（四类，够用）── */
        'trigger' => ['room' => 'b3_water'],            // 也可 rooms/fact/flags/night

        /* ── ③ 阶段（单调，只能往前走）── */
        'stages' => [
            's1_发现' => ['name' => '发现', 'text' => '玩家看见了什么'],
            's2_查明' => ['name' => '查明', 'text' => '弄清了来源'],
            's3_处置' => ['name' => '处置', 'text' => '把事办了'],
        ],

        /* ── ④ 线索（title/point/src/where/link/to）── */
        'clues' => [
            'a' => ['title' => '线索 A', 'point' => '一句话结论', 'src' => '从哪来的', 'where' => '该去哪类地方找', 'to' => 'main'],
            'b' => ['title' => '线索 B', 'point' => '一句话结论', 'src' => '…', 'where' => '…', 'to' => 'main', 'link' => ['a']],
            'c' => ['title' => '线索 C', 'point' => '一句话结论', 'src' => '…', 'where' => '…', 'to' => 'main', 'link' => ['a', 'b']],
        ],

        /* ── ⑤ 结论层（治"反网状"：线索 → 结论 → 行动）── */
        'concl' => [
            'main' => ['name' => '结论一句话', 'need' => 2, 'of' => ['a', 'b', 'c'],
                       'text' => '够了 2 条就成立，玩家看得见。'],
        ],

        /* ── ⑥ 多解法（至少两条；失败也要推进）── */
        'paths' => [
            'force' => ['name' => '硬来', 'type' => 'brute', 'hp' => 1,
                'check' => ['skill' => '机械维修'],
                'cost'  => ['minutes' => 40, 'expose' => 1],
                'gain'  => ['clues' => ['a'], 'stage' => 's2_查明'],
                'gain_on_fail' => ['stage' => 's2_查明'],          // fail forward ✓
                'text' => ['叙事一句。']],
            'soft' => ['name' => '换个角度', 'type' => 'check',
                'check' => ['skill' => '侦查', 'penalty' => 1],
                'cost'  => ['minutes' => 20],
                'gain'  => ['clues' => ['b'], 'stage' => 's2_查明']],
        ],

        /* ── ⑦ 任务期新人物（结束即失去对话 ✓）── */
        'newcomers' => [
            'tpl_worker' => ['name' => '老聂', 'role' => '水道工',
                'room' => 'b3_water', 'phases' => ['上午', '下午'],
                'entry' => 'n1',
                'nodes' => [
                    'n1' => ['text' => ['「你也是来看那声响的？」他把手电往管子上照。'],
                        'choices' => [
                            ['label' => '问他管线的事', 'goto' => 'n2'],
                            ['label' => '给他线索 B 看', 'goto' => 'n3',
                             'cond' => ['flag' => 'clue_m_tpl_example_b']],
                            ['label' => '先不聊', 'goto' => 'end'],
                        ]],
                    'n2' => ['text' => ['他指给你看一处旧补丁。'],
                        'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                        'effect' => ['clue' => 'a']],                  /* 引擎接手：给线索 ✓ */
                    'n3' => ['text' => ['「这个补丁……不是我打的。」'],
                        'choices' => [['label' => '（追问）', 'goto' => 'end']],
                        'effect' => ['clue' => 'c']],
                    'end' => ['text' => ['他回去干活了。'], 'choices' => []],
                ]],
        ],

        /* ── ⑧ 老人物插入对话（不改原树；可按阶段/线索开放）── */
        'dlg' => [
            'zhou' => ['ask_ledger' => [
                'label' => '追问：上个月的维修单',
                'stage' => 's2_查明',
                'cond'  => ['flag' => 'clue_m_tpl_example_b'],
                'node'  => ['text' => ['老周翻了半天：「有两页……不在了。」'],
                            'choices' => [['label' => '（记下来）', 'goto' => 'end']],
                            'effect' => ['clue' => 'c']],
            ]],
        ],

        /* ── ⑨ 任务期 NPC 时段移动（结束自动复原 ✓）── */
        'actors' => [
            'zhou' => ['room' => 'b3_water', 'phases' => ['下午']],     // 下午他在现场盯着
        ],

        /* ── ⑩ 世界自走节拍（不碰玩家也会变）── */
        'beats' => [
            'b1' => ['when' => ['clues' => ['a']],
                     'do' => ['narrate' => ['（走廊尽头的水声，今晚比昨晚响。）']]],
        ],

        /* ── ⑪ 结算产出：个人 / 公共 / NPC / 世界 / 归档 ── */
        'outcome' => ['done' => [
            'personal' => ['credits' => 5, 'money' => 120, 'rep' => ['base' => 2], 'flags' => ['tpl_done']],
            'facts'    => [['base.模板样例_办完了', true, 'once']],
            'actors'   => ['zhou' => ['fav' => 1]],
            'world'    => ['note' => '这条线办完了。'],
            'archive'  => ['title' => '【任务档案】模板样例', 'type' => '事件', 'level' => 'operative'],
        ]],

        /* ── ⑫ 善后（true ＝ 交给引擎：撤对话/撤新人/NPC 复原/留线索）── */
        'cleanup' => true,
    ]);
}, 30);
