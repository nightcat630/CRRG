<?php
/**
 * 中型任务《封条》· 第 2 轮（Act II 前半：镇上的纸）
 *   承接 Act I 的两条路线（上报／压下），在青石镇开五条线：
 *     ① 纸扎铺·老匠人（常守业）  ② 农机站·旧铅皮  ③ 卫生院·中毒  ④ 老陵·朱砂土  ⑤ 镇政府·通行
 *   两个检定关卡（说服谈匠人／医学判中毒）＋ 一个误导（"配方是我自己琢磨的"→ 其实是三年前的旧库管教的）
 *   集齐 4 条线 → 可进入 Act III 前置（就地处理 or 带回基地）
 *   新增：房间 tw_paper（纸扎铺）／tw_hillpath（后山岔路）；NPC 常守业／孙德发（废品站）／镇文书／赵铁（农机站）
 */
if (!defined('ABSPATH')) exit;

/* ── 新房间 ── */
add_action('init', function () {
    if (!function_exists('crrg_drill_scene_extend')) return;
    crrg_drill_scene_extend('town', [
        'tw_paper'    => ['t' => '纸扎铺', 'f' => '镇东', 'c' => 1,
            'd' => '门脸只有一间，挂满纸人纸马，风一吹整屋子都在响。柜台后面的人戴着袖套，手一直在抖。',
            'x' => ['主街' => 'tw_main_e', '后门（通后山）' => 'tw_hillpath']],
        'tw_hillpath' => ['t' => '后山岔路', 'f' => '镇外', 'c' => 2,
            'd' => '土路岔口，往上是老陵，往左是几条没人走的小径。夜里风从沟里上来，带着一股土腥味。',
            'x' => ['回纸扎铺' => 'tw_paper', '往老陵' => 'tw_tomb', '往镇上' => 'tw_main_e']],
    ], ['tw_paper' => [78, 44], 'tw_hillpath' => [74, 30]], []);
}, 22);

/* ── 新 NPC ── */
add_action('init', function () {
    if (!function_exists('crrg_dlg_register')) return;
    crrg_dlg_register('tw_paperman', ['name' => '常守业', 'room' => 'tw_paper',
        'look' => '五十多岁，袖套洗得发白，手指节粗大。说话时手会不受控地抖一下。',
        'topics' => []]);
    crrg_dlg_register('tw_junk_man', ['name' => '孙德发', 'room' => 'tw_junk',
        'look' => '废品站老板，围着围裙，一边拨算盘一边跟你说话。',
        'topics' => []]);
    crrg_dlg_register('tw_clerk', ['name' => '镇文书', 'room' => 'tw_townhall',
        'look' => '镇政府办公室里的年轻文书，桌上摊着一本登记簿。',
        'topics' => []]);
    crrg_dlg_register('tw_farm_man', ['name' => '赵铁', 'room' => 'tw_farm',
        'look' => '农机站的老师傅，手上抹着机油，正拆一只旧电瓶。',
        'topics' => []]);
}, 23);

/* ── Act II 任务（10 步） ── */
add_action('init', function () {
    /* 2026-10-14：《封条》已下线（委员长：整个不要了）——只关注册，函数与存档都留着 ✓
       恢复办法：把 missions/_retired.php 里的 crrg_retired_seal() 改成 return true ✓ */
    if (function_exists('crrg_retired_seal') && !crrg_retired_seal()) return;
    if (!function_exists('crrg_drill_register_mission')) return;
    crrg_drill_register_mission('m_mid_seal2', [
                'pass_rooms' => ['tw_paper', 'tw_farm', 'tw_clinic', 'tw_tomb', 'tw_townhall', 'tw_junk', 'tw_hillpath'],
        'name' => '封条', 'level' => '中', 'kind' => 'npc', 'min_lv' => 3, 'hidden' => true, 'chain_no' => 2, 'chain_total' => 5, 'chain_next' => 'm_mid_seal3',
        'scene' => 'town', 'brief_room' => 'tw_townhall', 'brief_name' => '镇政府',
        'depart_room' => 'b2b_hall', 'entry_room' => 'tw_main_e',
        'deadline_h' => 144,
        'prompt' => '要的材料在镇上。谁有、在哪儿、怎么换——自己排顺序。有的事得说通人，有的事得过手。',
        'steps' => [
            ['scene' => 'town',          'text' => '进镇（到青石镇即可：出发落在主街东段，长途落在镇口）'],
            ['flag' => 'paper_匠人',      'text' => '找到纸扎铺的老匠人'],
            ['flag' => 'paper_配方',      'text' => '谈出配方（说服／话术，或先带礼）'],
            ['flag' => 'paper_铅箔',      'text' => '解决铅皮来源（农机站旧电瓶）'],
            ['flag' => 'paper_中毒',      'text' => '判断中毒程度（医学／急救）'],
            ['flag' => 'paper_朱砂土',    'text' => '老陵取土（朱砂）'],
            ['flag' => 'paper_通行',      'text' => '在镇政府把手续走通（或绕过它）'],
            ['flag' => 'paper_旧库管',    'text' => '弄清配方真正的来路'],
            ['flag' => 'paper_集齐',      'text' => '五条线凑齐'],
            ['room' => 'tw_townhall',    'text' => '回镇政府，定第二幕的后半怎么走'],
        ],
        'reward' => ['credits' => 14, 'money' => 500], 'rep' => ['town_qingshi' => 4],
        'settle_note' => '你手上有了料和手续——也都欠着人情。',
    ]);
}, 24);

/* ══════════ Act II：调查点（沿用「调查 <关键词>」） ══════════ */
function crrg_seal2_survey(&$s, &$pc, $arg) {
    /* 2026-09-29 合并三份 survey：数据由旧实现**逐字提取**生成（禁止手抄）
       · 手续行保留三段逻辑：无路→回话；绕过→声望 −1；文本按路线分支（pre/texts 闭包） */
    $rows = [
        'tw_paper' => [
            ['kw' => '配方', 'flag' => 'paper_配方', 'skill' => '说服', 'fail_text' => '（没弄明白——可以再来，或换个法子。）',
             'texts' => array (
  0 => '老匠人从柜台底下抽出一张发黄的纸，压在手心里不给你：「你要这个干什么。」',
  1 => '你把基地那张半页纸摊开。他看了很久，手抖了一下。',
  2 => '「……这是我教出去的。」',
  3 => '（配方到手：朱砂、明矾、铅箔、糯米浆，比例按他试的。）',
)],
            ['kw' => '纸人', 'flag' => '', 'fail_text' => '（没弄明白——可以再来，或换个法子。）',
             'texts' => array (
  0 => '满屋的纸人纸马，有一匹纸马的肚子里塞着铅箔——不是做法事的做法。',
  1 => '（他也在自己做：用农机站的旧铅皮。）',
)],
        ],
        'tw_farm' => [
            ['kw' => '电瓶', 'flag' => 'paper_铅箔', 'skill' => '机械维修', 'fail_text' => '（没弄明白——可以再来，或换个法子。）',
             'texts' => array (
  0 => '赵铁把一只旧电瓶壳撬开：「铅板都在这里，你要几片就说。」',
  1 => '「常师傅上个月来要过两回。我说这东西有毒，他说他要的不是这个。」',
  2 => '（铅皮来源解决：农机站旧电瓶。）',
)],
        ],
        'tw_clinic' => [
            ['kw' => '中毒', 'flag' => 'paper_中毒', 'skill' => '医学', 'fail_text' => '（没弄明白——可以再来，或换个法子。）', 'pen' => 1, 'pen_note' => '　［镇上条件差 +1］',
             'texts' => array (
  0 => '医生把老匠人的手翻过来给你看：指甲根有一条淡淡的青灰线。',
  1 => '「铅。已经进去了。他不肯查，说没时间。」',
  2 => '（中毒程度：慢性，需停手并及时处理，否则三个月内会出大问题。）',
)],
        ],
        'tw_tomb' => [
            ['kw' => '土', 'flag' => 'paper_朱砂土', 'fail_text' => '（没弄明白——可以再来，或换个法子。）',
             'texts' => array (
  0 => '陵后的坡上有一层红土，抓一把在手里发涩——是朱砂，不是铁锈。',
  1 => '你挖了两捧，包进报纸里。',
  2 => '（朱砂土到手：老陵后坡。）',
)],
        ],
        'tw_townhall' => [
            ['kw' => '手续', 'flag' => 'paper_通行',
             'pre' => function (&$s2, &$pc2) {
                if (!crrg_seal_has($s2, 'seal_路线_上报') && !crrg_seal_has($s2, 'paper_绕过')) {
                    return array (
  0 => '镇文书翻了翻登记簿：「外单位来镇上采买，要有介绍信。」',
  1 => '（你手上没有。他摊手：「那我也没法子。」）',
  2 => '（——先找人问出别的路子。）',
);   // 没路：原文回话，不置旗标（逐字）
                }
                if (!crrg_seal_has($s2, 'seal_路线_上报') && crrg_seal_has($s2, 'paper_绕过')) {
                    crrg_rep_add($pc2, 'town_qingshi', -1, '《封条》：走了镇公所的暗账');
                }
                return null;
             },
             'texts' => function ($s2, $pc2) {
                if (crrg_seal_has($s2, 'seal_路线_上报')) return array (
  0 => '镇文书翻了翻登记簿：「外单位来镇上采买，要有介绍信。」',
  1 => '你把值班室那份材料递过去。他看了一眼抬头，盖了章。',
  2 => '（通行：手续走通（正规）。）',
);
                if (crrg_seal_has($s2, 'paper_绕过'))     return array (
  0 => '镇文书翻了翻登记簿：「外单位来镇上采买，要有介绍信。」',
  1 => '你没递介绍信，只说了一句"联社那边"。他停了两秒，从抽屉里抽出一张空白收据。',
  2 => '（通行：走了暗账——**镇公所记你一笔，镇上声望 −1**。）',
);
                return array (
  0 => '镇文书翻了翻登记簿：「外单位来镇上采买，要有介绍信。」',
  1 => '你手上没有介绍信。他摊手：「那我也没法子。」',
  2 => '（通行：卡住了。得先跟镇文书聊出别的路子。）',
);
             }],
        ],
        'tw_junk' => [
            ['kw' => '铅', 'flag' => 'paper_回收记录', 'fail_text' => '（没弄明白——可以再来，或换个法子。）',
             'texts' => array (
  0 => '孙德发把一摞旧账本推给你：「铅皮这东西，今年收了三回，两回是同一个人卖的。」',
  1 => '「还有一回，是个生面孔，说话不像本地人。」',
  2 => '（旁证：**镇上还有第二方在收同样的材料**。）',
)],
        ],
        'tw_hillpath' => [
            ['kw' => '痕迹', 'flag' => 'paper_夜行者', 'fail_text' => '（没弄明白——可以再来，或换个法子。）',
             'texts' => array (
  0 => '岔路上有新压的摩托车辙，往老陵方向。胎花很细——不是镇上人骑的那种。',
  1 => '旁边的草被踩倒过一片，像有人在那儿站了很久。',
  2 => '（旁证：**有人在夜里上过山，且不是镇上人**。）',
)],
        ],
    ];
    $rows = array_map('crrg_seal2_survey_post', $rows);
    return function_exists('crrg_seal_survey_engine') ? crrg_seal_survey_engine($s, $pc, $arg, $rows) : null;
}
/* 每行加后置钩子（五条线集齐判定） */

function crrg_seal2_survey_post($rs) {
    foreach ($rs as &$r) $r['post'] = 'crrg_seal2_check_complete';
    return $rs;
}

function crrg_seal2_check_complete(&$s, &$pc) {
    $need = ['paper_配方', 'paper_铅箔', 'paper_中毒', 'paper_朱砂土', 'paper_通行'];
    $has = array_intersect($need, (array) ($s['flags'] ?? []));
    $out = [];
    if (count($has) >= 4 && !in_array('paper_集齐', (array) ($s['flags'] ?? []), true)) {
        if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
        $s['flags'][] = 'paper_集齐';
        $out[] = '五条线凑到一张纸上：配方、铅皮、朱砂土、中毒的手，还有一份（或一份假的）手续。';
        $out[] = '（第二幕前半完成——可以回镇政府定后半。）';
    }
    return $out;
}

/* ══════════ Act II 幕末：就地处理 or 带回基地 ══════════ */
function crrg_seal2_stage_choice(&$s, &$pc, $key) {
    /* 2026-10-01 修（玩家报"五条线全齐却打不上勾、决断说没凑齐"✗，死锁 ✓）：
       `paper_集齐` 原来**只挂在"调查"的后置钩子**上 ✗；
       而第 4/5 条线常常是**细问/别处**拿到的 ✓ → 钩子没触发 → 旗标没发 ✗ → 偏偏决断又被它卡着 ✓
       → 这里**先自愈一次**（够 4 条就把旗标补上 ✓），再进门槛判断 ✓ */
    if (function_exists('crrg_seal2_check_complete')) crrg_seal2_check_complete($s, $pc);
    return crrg_seal_choice_engine($s, $pc, $key, [
        'prompt'   => '（第二幕决断：「决断 就地」把配方留给镇上，或「决断 带回」把配方收进基地。）',
        'fallback' => '（「决断 就地」或「决断 带回」。）',
        'gate_first' => true,                                   // 旧实现先查门槛、后查"已定过"
        'gate'     => ['need' => ['paper_集齐'], 'refuse' => ['（五条线还没凑齐。）']],
        'once'     => ['flags' => ['paper_路线_就地', 'paper_路线_带回'],
                       'done'  => function ($s2) {
                           return ['（第二幕的走向已经定过了——' . (crrg_seal_has($s2, 'paper_路线_就地') ? '配方留给了镇上' : '配方带回了基地') . '。要改，只能在结案时承担后果。）'];
                       }],
        'options'  => [
            ['match' => '就地', 'flags' => ['paper_路线_就地'],
             'rep' => ['town_qingshi', 5, '《封条》第二幕：配方留镇上'],
             'texts' => ['你把配方抄了一份给常守业，写上用法与剂量，又把替代材料的思路留给了卫生院。',
                         '「以后有人来找你，你让他先来找我。」你说。',
                         '（镇上声望 +5 ｜ 路线：就地——镇上会自己补，但你欠着这份人情。）']],
            ['match' => '带回', 'flags' => ['paper_路线_带回'],
             'rep' => ['town_qingshi', 1, '《封条》第二幕：配方收回基地'], 'stock' => [1, '第二幕取料'],
             'texts' => ['你把配方、铅皮与朱砂土一起收进箱子，锁好。',
                         '临走前，常守业只说了句：「手抖得厉害了。」',
                         '（镇上声望 +1 ｜ 路线：带回——基地能自产，但镇上还缺药。）']],
        ],
    ]);
}

function crrg_seal2_stage_lines(&$s, $pc) {
    $f = (array) ($s['flags'] ?? []);
    $need = ['paper_配方' => '配方', 'paper_铅箔' => '铅皮', 'paper_中毒' => '中毒', 'paper_朱砂土' => '朱砂土', 'paper_通行' => '手续'];
    $got = []; $miss = [];
    foreach ($need as $k => $n) { if (in_array($k, $f, true)) $got[] = $n; else $miss[] = $n; }
    return [sprintf('第二幕 · 镇上的纸 ｜ 已得：%s ｜ 缺：%s', $got ? implode('、', $got) : '（无）', $miss ? implode('、', $miss) : '（齐）'),
            '（调查：配方／纸人／电瓶／中毒／土／铅／手续／痕迹；细问：常守业／孙德发／镇文书／赵铁）'];
}

/* 双向连接（2026-09-29 修）：我加的两个镇上房间原先只有"出"没有"进"，
   导致纸扎铺成了单行道（第二段必须去那儿）。这里补上主街侧的入口。 */
add_action('init', function () {
    if (!function_exists('crrg_drill_scene_extend')) return;
    /* 注意：scene_extend 的 'x' 是**整体替换**——必须写全，否则会把原有出口覆盖掉（2026-09-29 踩过） */
    crrg_drill_scene_extend('town', [
        'tw_main_e' => ['x' => ['主街·中段' => 'tw_main_c', '汽车站' => 'tw_station', '邮电所' => 'tw_post',
                                '新华书店' => 'tw_book', '照相馆' => 'tw_photo',
                                '纸扎铺' => 'tw_paper', '后山岔路' => 'tw_hillpath']],
        'tw_main_c' => ['x' => ['主街·西段' => 'tw_main_w', '主街·东段' => 'tw_main_e', '北巷' => 'tw_alley_n',
                                '南巷' => 'tw_alley_s', '五金店' => 'tw_hardware', '信用社' => 'tw_bank',
                                '旅社' => 'tw_inn', '后山方向' => 'tw_hillpath']],
        'tw_tomb'   => ['x' => ['后山岔路' => 'tw_hillpath', '回镇上' => 'tw_main_e']],
    ], [], []);
}, 31);
/* 修复（2026-09-29）：我之前用 scene_extend 补出口时把三间房的 t／d 覆盖没了
   （主街中段/东段成了 null；老陵则一直没有房间本体）。这里把完整定义补回。
   注：scene_extend 现已改为按房间**深合并**，日后补 'x' 不会再抹掉其它字段。 */
add_action('init', function () {
    if (!function_exists('crrg_drill_scene_extend')) return;
    crrg_drill_scene_extend('town', [
        'tw_main_c' => ['t' => '主街·中段（十字口）', 'f' => '镇上', 'c' => 1,
            'd' => '全镇的中心。路口有棵老榆树，树下摆着两张石凳。北巷通卫生院与镇政府，南巷通居民区与河沿。'],
        'tw_main_e' => ['t' => '主街·东段', 'f' => '镇上', 'c' => 1,
            'd' => '街面比西段宽些，停着两辆长途车。汽车站的售票窗口朝街开，邮电所和新华书店对门。'],
        'tw_tomb' => ['t' => '老陵', 'f' => '镇外', 'c' => 2,
            'd' => '镇北坡上的老坟地，土是红的。石碑倒了一半，草里能看见翻过的痕迹——有人来挖过。',
            'x' => ['后山岔路' => 'tw_hillpath', '回镇上' => 'tw_main_e']],
    ], [], []);
}, 32);

/* ── 问 NPC：数据 + 薄包装（2026-09-29 合并三份重复逻辑） ── */
function crrg_seal2_ask_data() {
    return [
            '常守业' => [
                ['匠人', 'paper_匠人', ['「纸扎这行，我做了三十年。」他把袖套往上推了推。',
                                        '「去年冬天有人找我，说基地那边缺封条，问我还有没有老法子。」',
                                        '（他顿了顿：「我没问他是哪个单位的。」）']],
                ['徒弟', 'paper_徒弟', ['「有个徒弟。」他指了指里屋，「去年走了，说这行不挣钱。」',
                                        '「配方他也抄了一份。我拦不住。」']],
                ['旧库管', 'paper_旧库管', ['你说起三年前那份被划掉的《镇物耗材续购申请》。',
                                            '他盯着你看了一会儿：「那个人后来来过镇上。」',
                                            '「他教了我半手，说"以后有人来找你，你就说是你自己琢磨的"。」',
                                            '（**误导解除：配方是基地旧库管带出来的。**）']],
            ],
            '孙德发' => [
                ['生面孔', 'paper_生面孔', ['孙德发把嗓门压低了：「那人不讲价，只要铅皮，还问我镇上哪儿有朱砂。」',
                                            '「穿的鞋挺干净，不像跑车的。」']],
            ],
            '镇文书' => [
                ['绕过', 'paper_绕过', ['镇文书把声音放轻：「真要采买，也不是非得走我们这儿。」',
                                        '「联社那边有明账，也有暗账。你们要是自己谈，别让派出所看见。」',
                                        '（给出绕过手续的路子——但有代价。）']],
            ],
            '赵铁' => [
                ['同一个人', 'paper_同一个人', ['「收铅皮的是常师傅。」赵铁擦了擦手。',
                                                '「但上个月那个人，穿夹克，说自己是县里的。我也给他称过。」']],
            ],
    ];
}
function crrg_seal2_npc_ask(&$s, &$pc, $npc, $topic = '') {
    return function_exists('crrg_seal_ask_engine') ? crrg_seal_ask_engine($s, $pc, $npc, $topic, crrg_seal2_ask_data()) : null;
}
