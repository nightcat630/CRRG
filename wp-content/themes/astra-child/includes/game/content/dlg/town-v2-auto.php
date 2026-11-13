<?php
/**
 * 对话 v2 · 由旧「纯话题」机械迁移的树（2026-09-30，tools/dlg-migrate.php 生成）
 * 说明：文本 var_export 原样保留 ✓；结构＝entry 列话题 → 话题节点（可一次性）→ 回 entry／收场 ✓
 *      含 need/closed/skill/dyn 的 NPC **不在**本文件（需手改 ✓）
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_dlg2_register')) return;

    /* 裁缝 */
    $t = array (
  'name' => '裁缝',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「帆布的结实，耐磨。你要下地干活就做帆布的。」',
        1 => '「样衣挂在墙上，自己看。钱先付一半。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「县里拉来的。前阵子路不好，车晚了两天。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't3' => 
    array (
      'text' => 
      array (
        0 => '「夜里出去的车？李家侄子常跑夜路，那是营生。」',
        1 => '「再就是收山货的，前阵子来过两趟，往西边去了。」',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（裁缝看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '做件什么（成衣）',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '做衣服',
            1 => '成衣',
            2 => '工装',
          ),
        ),
        1 => 
        array (
          'label' => '布从哪来',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '布',
            1 => '料子',
            2 => '哪来的',
          ),
        ),
        2 => 
        array (
          'label' => '谁家夜里出去过',
          'goto' => 't3',
          'kw' => 
          array (
            0 => '夜里',
            1 => '谁家',
            2 => '出门',
            3 => '车',
          ),
        ),
        3 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_tailor', $t);

    /* 修车师傅 */
    $t = array (
  'name' => '修车师傅',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「皮卡，减震废了。跑山路跑废的。」',
        1 => '「谁的车？山外拉货的。跟我打过招呼就放这儿了。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「往后山？车开不上去，坡太陡。走上去二十分钟。」',
        1 => '「那坡我十年不去了。现在除了看坟的老马，没人往那儿去。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't3' => 
    array (
      'text' => 
      array (
        0 => '「手电有，你要用就拿去。别给我摔了。」',
        1 => '（师傅把一支旧手电塞给你。）',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（修车师傅看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '这车怎么了',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '车',
            1 => '轮胎',
            2 => '怎么了',
          ),
        ),
        1 => 
        array (
          'label' => '去后山的路',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '后山',
            1 => '路',
            2 => '坡上',
          ),
        ),
        2 => 
        array (
          'label' => '能不能借个手电',
          'goto' => 't3',
          'kw' => 
          array (
            0 => '手电',
            1 => '工具',
            2 => '借',
          ),
        ),
        3 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_repairman', $t);

    /* 澡堂老板 */
    $t = array (
  'name' => '澡堂老板',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「十五块，泡到你自己不想泡。毛巾押金五块。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「还能说什么。地里的、厂里的、谁家闺女。」',
        1 => '「这两天说得最多的是后坡——有人说看见有人往上头搬东西。你自己听去吧。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（澡堂老板看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '澡堂的规矩',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '澡堂',
            1 => '规矩',
            2 => '多少钱',
          ),
        ),
        1 => 
        array (
          'label' => '池子里都在说什么',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '说什么',
            1 => '闲话',
            2 => '池子',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_bathman', $t);

    /* 兽医 */
    $t = array (
  'name' => '兽医',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「崴了蹄子。老赵家的，拉磨拉了大半辈子。」',
        1 => '「我这儿看的是牲口，人不行。人你上卫生院。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「后坡那片林子，早些年有野物。现在少了。」',
        1 => '「前阵子有人捡到过一截蹄印照片，模模糊糊的，说不清是啥。我看不像狗。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（兽医看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '这头驴',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '驴',
            1 => '牲口',
            2 => '怎么了',
          ),
        ),
        1 => 
        array (
          'label' => '坡上的野东西',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '野',
            1 => '坡上',
            2 => '痕迹',
            3 => '脚印',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_vet', $t);

    /* 磨坊主 */
    $t = array (
  'name' => '磨坊主',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「转。就是人少了，一天来不了几个。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「河不深，夏天有人下去捞鱼。往西走，坡根底下跟河湾挨着。」',
        1 => '「早些年发过一次水，把坡脚下的土冲开过。现在又长满了。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（磨坊主看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '磨坊还转吗',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '磨坊',
            1 => '石磨',
            2 => '还转吗',
          ),
        ),
        1 => 
        array (
          'label' => '河沿这一带',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '河沿',
            1 => '河',
            2 => '这一带',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_miller', $t);

    /* 供销社售货员 */
    $t = array (
  'name' => '供销社售货员',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「盐、酱油、电池。再就是蜡烛，前阵子停电。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「前街李家进贼那事，街面上都传。」',
        1 => '「丢的是书，别的没动。你要问这个，去问理发的老李，他嘴最碎。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（供销社售货员看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '最近什么好卖',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '好卖',
            1 => '生意',
            2 => '什么卖得好',
          ),
        ),
        1 => 
        array (
          'label' => '镇上的闲话',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '闲话',
            1 => '最近',
            2 => '听说',
            3 => '消息',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_seller', $t);

    /* 粮油店老板 */
    $t = array (
  'name' => '粮油店老板',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「米比去年贵两毛。油价也是。」',
        1 => '「上头收粮的时候说不准，压着不卖的都亏了。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「他？半个月来一趟，买得少。一个人吃不了多少。」',
        1 => '「去年秋天以后就没见着了。我还纳闷。」',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（粮油店老板看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '今年的行情',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '行情',
            1 => '米价',
            2 => '贵',
            3 => '多少钱',
          ),
        ),
        1 => 
        array (
          'label' => '李道生买过粮吗',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '李道生',
            1 => '买粮',
            2 => '那个人',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_grainman', $t);

    /* 面馆老板娘 */
    $t = array (
  'name' => '面馆老板娘',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「牛肉面。牛肉不多，给你多舀一勺汤。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「看坟的老马，隔三差五来。还有联防队那几个。」',
        1 => '「李道生也来过，一个人坐角落，边吃边看书。」',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（面馆老板娘看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '今天有什么',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '吃什么',
            1 => '菜单',
            2 => '今天',
          ),
        ),
        1 => 
        array (
          'label' => '常客都有谁',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '常客',
            1 => '谁来吃',
            2 => '熟客',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_noodle', $t);

    /* 信用社柜员 */
    $t = array (
  'name' => '信用社柜员',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「现在利息低。你要存款得带身份证。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「个人的不给查。要看你也得有关系。」',
        1 => '（她看了你一眼，把窗口的小牌子扶正了。）',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（信用社柜员看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '存取的事',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '存钱',
            1 => '取钱',
            2 => '利息',
          ),
        ),
        1 => 
        array (
          'label' => '这里能查旧账吗',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '查账',
            1 => '旧账',
            2 => '记录',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_teller', $t);

    /* 卫生院医生 */
    $t = array (
  'name' => '卫生院医生',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「感冒的多，秋里换季。」',
        1 => '「再就是睡不着的。赵家老太太，一宿一宿地坐着。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「睡不着多半是心里有事。药能顶一阵，不治根。」',
        1 => '「后坡底下住过的那家老太太，也是这个病。后来搬走了。」',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（卫生院医生看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '最近有什么病人',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '病人',
            1 => '看病',
            2 => '最近',
          ),
        ),
        1 => 
        array (
          'label' => '睡不着的人',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '睡不着',
            1 => '失眠',
            2 => '睡不好',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_doctor2', $t);

    /* 镇政府办事员 */
    $t = array (
  'name' => '镇政府办事员',
  'entry' => 'entry',
  'nodes' => 
  array (
    't1' => 
    array (
      'text' => 
      array (
        0 => '「带身份证和户口本。介绍信要单位盖章。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    't2' => 
    array (
      'text' => 
      array (
        0 => '「公墓归镇上。地是村上的，早年划出去一块。」',
        1 => '「你要看地籍图？……得走手续，你先说你要干什么。」',
      ),
      'once' => true,
      'choices' => 
      array (
        0 => 
        array (
          'label' => '（先问别的）',
          'goto' => '__entry',
        ),
      ),
    ),
    'entry' => 
    array (
      'text' => 
      array (
        0 => '（镇政府办事员看着你，等你开口。）',
      ),
      'choices' => 
      array (
        0 => 
        array (
          'label' => '要开什么证明',
          'goto' => 't1',
          'kw' => 
          array (
            0 => '证明',
            1 => '盖章',
            2 => '办事',
          ),
        ),
        1 => 
        array (
          'label' => '后坡归谁管',
          'goto' => 't2',
          'kw' => 
          array (
            0 => '后坡',
            1 => '公墓',
            2 => '归谁',
            3 => '土地',
          ),
        ),
        2 => 
        array (
          'label' => '（不问了）',
          'goto' => 'end',
        ),
      ),
    ),
  ),
);
    /* 把 '__entry' 换回 'entry'（生成时占位） */
    foreach ($t['nodes'] as &$nd) foreach ($nd['choices'] as &$ch) if (($ch['goto'] ?? '') === '__entry') $ch['goto'] = 'entry';
    unset($nd, $ch);
    crrg_dlg2_register('town_clerk', $t);
}, 24);
