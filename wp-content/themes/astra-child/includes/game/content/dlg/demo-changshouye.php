<?php
/**
 * 对话 v2 · 示范：常守业（纸扎匠）
 * ------------------------------------------------------------
 * 这棵树演示 v2 的全部要点，作者照这个写即可：
 *   · text     台词（数组，一行一句）
 *   · choices  选项：label（玩家看到的）＋ goto（跳到哪个节点）
 *   · cond     条件（声明式）：flag / not_flag / item / rep / fav / phase
 *   · set/give/fav/rep  效果（声明式）
 *   · once     一次性节点（问过就不再走）
 *   · 'end'    收场，清掉这次会话的状态
 *
 * 与旧系统的关系：旧话题（点击卡片）仍可用；这棵树是**替换写法**，逐个人物迁移即可。
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_dlg2_register')) return;

    crrg_dlg2_register('tw_paperman',   // NPC id（引擎也支持直接写人名 ✓）
        [
        'name'  => '常守业',
        'entry' => 'greet',
        'nodes' => [

            'greet' => [
                'text' => [
                    '老匠人把手里的竹篾放下，袖套往上推了推。',
                    '「要纸？」他看了一眼你的工装，「你们那边最近来的人不少。」',
                ],
                'choices' => [
                    ['label' => '说是自己用',   'goto' => 'self'],
                    ['label' => '问他最近给谁做过', 'goto' => 'who', 'cond' => ['flag' => 'paper_匠人']],
                    ['label' => '直接问配方',   'goto' => 'recipe', 'cond' => ['flag' => 'paper_配方']],
                    ['label' => '问他要不要卖点纸', 'goto' => 'open_trade'],
                    ['label' => '不问了',       'goto' => 'end'],
                ],
            ],

            'self' => [
                'text' => ['「自己用？」他把手在围裙上擦了擦，「行啊，你要哪种纸？」'],
                'choices' => [
                    ['label' => '要能贴着门的', 'goto' => 'recipe'],
                    ['label' => '随便看看',     'goto' => 'greet'],
                ],
            ],

            'who' => [
                'text' => [
                    '他没立刻答，先把门口那摞纸挪开。',
                    '「县里来过一回。人不进屋，在街口等。」',
                    '（**线索：收货的人从不进屋。**）',
                ],
                'once'  => true,
                'set'   => ['dlg_常_收料人'],
                'choices' => [
                    ['label' => '问那人什么样子', 'goto' => 'who2'],
                    ['label' => '记下来', 'goto' => 'greet'],
                ],
            ],

            'who2' => [
                'text' => [
                    '「穿你们那样的鞋。」他抬眼看你，「旧的，鞋帮磨白了。」',
                    '「我没问他是哪个单位的——问了，这生意就没了。」',
                ],
                'once' => true,
                'choices' => [['label' => '明白了', 'goto' => 'greet']],
            ],

            'recipe' => [
                'text' => [
                    '他沉默了一会儿，从柜台底下抽出一张发黄的纸，压在手心里。',
                    '「这方子我教出去过。」他说得很慢，「教的那个人，穿的也是你们的衣服。」',
                ],
                'set'   => ['dlg_常_配方'],
                'fav'   => ['常守业', 1],
                'choices' => [
                    ['label' => '问他那人是谁', 'goto' => 'recipe2'],
                    ['label' => '把纸抄下来（要人情）', 'goto' => 'recipe_take', 'cond' => ['fav' => ['常守业', 2]]],
                    ['label' => '先不拿', 'goto' => 'greet'],
                ],
            ],

            'recipe2' => [
                'text' => [
                    '「记不清脸了。只记得他伸手的样子——不像做这行的。」',
                    '（**线索：教过配方的人不是纸扎行出身。**）',
                ],
                'once' => true,
                'choices' => [['label' => '回到上面', 'goto' => 'recipe']],
            ],

            /* 从对话里**打开交易窗口**（演示）✓ */
            'open_trade' => [
                'text'    => ['「你要买？」他把柜台上的灰抹了抹，「我这儿就这些。」'],
                'open'    => 'trade:常守业',
                'choices' => [['label' => '看看货', 'goto' => 'end']],
            ],

            'recipe_take' => [
                'text' => [
                    '他把纸推过来，手还压着：「拿去。就说是我给的。」',
                    '「用的时候别省——省下的那点料，最后都还回去。」',
                ],
                'set'   => ['dlg_常_得配方'],
                'give'  => ['doc_notepad' => 1],
                'fav'   => ['常守业', 1],
                'once'  => true,
                'choices' => [['label' => '谢过他', 'goto' => 'end']],
            ],
        ],
    ]);
}, 22);
