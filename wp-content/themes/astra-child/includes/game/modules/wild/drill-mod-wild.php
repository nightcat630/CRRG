<?php
/**
 * 副本模组：野外观察（伪水仙）
 * 接入方式＝本文件 + functions.php 一行 require。以后加副本照抄这个骨架。
 * 场景 mod_wild：山体南侧出口外的林区。
 */
if (!defined('ABSPATH')) exit;

crrg_drill_register_scene('mod_wild', [
    'name' => '野外·祁连山南麓林区',
    'rooms' => [
        'w_gate' => ['t' => '山口（山体南侧出口）', 'f' => '野外', 'c' => 3,
            'd' => '出口开在坡面朝南的凹处，伪装网从里侧推开。风里有草味，远处是成片的针叶林。',
            'x' => ['林间小径' => 'w_trail']],
        'w_trail' => ['t' => '林间小径', 'f' => '野外', 'c' => 3,
            'd' => '踩出来的土道，两侧是落叶和倒木。地上有几处新踩的痕迹，深浅不一。',
            'x' => ['山口' => 'w_gate', '松林边缘' => 'w_stand', '凹地' => 'w_hollow']],
        'w_stand' => ['t' => '松林边缘', 'f' => '野外', 'c' => 3,
            'd' => '林子在这里稀下来，几棵松树的树皮颜色不太一样，摸上去比周围凉。',
            'x' => ['小径' => 'w_trail', '山脊' => 'w_ridge']],
        'w_ridge' => ['t' => '山脊观察点', 'f' => '野外', 'c' => 3,
            'd' => '脊线上有块平石，是现成的观察位。俯视整个凹地，视野最好。',
            'x' => ['松林边缘' => 'w_stand', '下坡' => 'w_hollow']],
        'w_hollow' => ['t' => '凹地', 'f' => '野外', 'c' => 3,
            'd' => '凹地中央是一小片空地，土层松软。北侧立着一株异常高大的树状物，秋末了，枝叶却还很密。',
            'x' => ['小径' => 'w_trail', '山脊' => 'w_ridge']],
    ],
    'pos' => ['w_gate' => [90, 10], 'w_trail' => [95, 30], 'w_stand' => [80, 50],
              'w_ridge' => [110, 55], 'w_hollow' => [95, 62]],
    'ents' => [
        'w_gate' => [['n' => '界桩', 'k' => 'item', 'c' => '查看 界桩']],
        'w_trail' => [['n' => '新踩的痕迹', 'k' => 'item', 'c' => '查看 痕迹'],
                      ['n' => '倒木', 'k' => 'item', 'c' => '查看 倒木']],
        'w_stand' => [['n' => '树皮异样的松树', 'k' => 'item', 'c' => '查看 松树']],
        'w_ridge' => [['n' => '现成的观察位', 'k' => 'item', 'c' => '查看 观察位'],
                      ['n' => '凹地全貌', 'k' => 'item', 'c' => '查看 凹地']],
        'w_hollow' => [['n' => '异常花状物', 'k' => 'item', 'c' => '查看 花状物'],
                       ['n' => '土层下的根系痕迹', 'k' => 'item', 'c' => '查看 根系'],
                       ['n' => '一只不怕人的动物', 'k' => 'item', 'c' => '查看 动物'],
                       ['n' => '取样点', 'k' => 'item', 'c' => '取样']],
    ],
]);

crrg_drill_register_mission('m_wild_01', [
    'name' => '野外个体观察（伪水仙）', 'level' => '中', 'scene' => 'mod_wild',
    'brief_room' => 'b2b_hall', 'brief_name' => '负二层指挥大厅',
    'depart_room' => 'garage_maint', 'entry_room' => 'w_gate',
    'prompt' => '档案 W-01-02-032：秋季开花、可伪装成常见林木、捕食靠诱饵个体。目标：确认凹地个体、记录花状物、取回土壤样本。',
    'steps' => [
        ['room' => 'w_stand', 'text' => '抵达松林边缘，比对树皮异常'],      // 原写 w_edge（该房间不存在），已更正
        ['room' => 'w_ridge', 'text' => '登上山脊观察点，俯瞰凹地'],
        ['flag' => 'san_flower', 'text' => '记录异常花状物（触发理智检定）'],  // 实际触发是「查看 花状物」，改用旗标
        ['flag' => 'sample_wild', 'text' => '在凹地取回土壤与根系样本'],
        ['room' => 'b2b_hall','text' => '回指挥大厅提交观察记录'],
    ],
    'reward' => ['credits' => 45, 'money' => 2600],
    'settle_note' => '样本转镇物研究室；记录归入档案。按收容措施，发现野生个体须上报——请勿接近本体。',
]);

/* 模组专属的查看与检定（挂到统一查看函数前） */
function crrg_drill_mod_look($s, $pc, $arg, &$lines, &$flags) {
    $room = $s['room'] ?? '';
    $add = function ($t) use (&$lines) { $lines[] = $t; };

    if (mb_strpos($arg, '松树') !== false && $room === 'w_stand') {
        $r = crrg_coc_check('侦查', max(1, crrg_coc_skill_val($pc, '侦查')));
        $add($r['text']);
        if (crrg_coc_level_rank($r['level']) >= 2) {
            $add('凑近看：树皮上有一条纵向的深色分支，位置比其他树低，像是从土里长上来的。');
            $add('（与档案中「根茎均有一条黑色分支」的描述相符。）');
            $flags[] = 'w_bark';
        } else {
            $add('看不出明显差别，只觉得这几棵树的树皮偏凉。');
        }
        return true;
    }
    if (mb_strpos($arg, '花状物') !== false && $room === 'w_hollow') {
        $add('那株树状物中段裂开一处，露出肉质的褶皱——不是花，是一朵「像花的东西」，直径接近一米。');
        $add('花心深处有几粒黑色硬质颗粒。');
        if (!in_array('san_flower', (array) $flags, true)) {
            $flags[] = 'san_flower';
            $add('');
            $add(crrg_coc_san_check($pc, '1/1d4', '近距离看清异常花状物'));
        }
        return true;
    }
    if (mb_strpos($arg, '动物') !== false && $room === 'w_hollow') {
        $add('一只松鼠坐在离你不到两米的树根上，不躲也不叫。它的动作比正常松鼠慢半拍，眼睛一直朝着同一个方向。');
        $add('（档案：诱饵个体 W-01-02-32-001 能模拟动物习性并融入族群。）');
        return true;
    }
    if (mb_strpos($arg, '根系') !== false && $room === 'w_hollow') {
        $add('土层松软处能看到几条粗根，往北侧那株树的方向收拢；表层土有被翻动过的痕迹。');
        return true;
    }
    if (mb_strpos($arg, '痕迹') !== false && $room === 'w_trail') {
        $r = crrg_coc_check('追踪', max(1, crrg_coc_skill_val($pc, '追踪')));
        $add($r['text']);
        $add(crrg_coc_level_rank($r['level']) >= 2
            ? '痕迹杂乱但都朝向凹地：小的蹄印、鸟爪，还有一串明显大得多的脚印，方向一致。'
            : '踩痕乱成一团，看不出走向。');
        return true;
    }
    return false;
}

/* 模组专属取样：凹地的土壤与根系样本 */
function crrg_drill_mod_sample($s, $pc, &$lines, &$flags, &$pc_items) {
    if (($s['room'] ?? '') !== 'w_hollow') return false;
    if (in_array('sample_wild', (array) $flags, true)) { $lines[] = '凹地的样本已经取过一份。'; return true; }
    $lines[] = '用取样管取了表层土与一段细根，装进密封盒，标签写：凹地北侧、树状物外缘三米。';
    $lines[] = '密封盒收进背包。';
    $flags[] = 'sample_wild';                                  // 按地点单独立旗，不与 C2 混用
    $pc_items[] = 'key_sample_soil';                               // 真正发东西（以前只印字）
    $lines[] = '（得到「土壤与根系样本」，可交回基地或从邮电所寄回。）';
    return true;
}

/* ================= 任务分型：新手训练（基地·只接一次）＋ NPC 委托 ================= */
crrg_drill_register_mission('m_train_01', [
    'coexist' => true,          // 课目不算值班单：可与别的合约并存（侧槽）
    'pass_rooms' => ['b4_buffer', 'b4_c2', 'b4_obs'],   // 见习通行（与 crrg_drill_trainee_pass 一致）
    'name' => '新手训练考核（C2 巡检修习）', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'once' => true,
    'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
    'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
    'prompt' => '跟着演习脚本走一遍：领任务 → 装备整备室领防护服 → 专用楼梯下负四层 → 三道闸刷卡 → C2 取样 → 回指挥大厅交报告。只接一次，做完不再挂出。',
    'steps' => [
        ['flag' => 'gear', 'hint_room' => 'b2f_gear',   'text' => '到装备整备室领 T3 防护服'],
        ['flag' => 'gates',  'text' => '过三道气密闸，抵达观察室', 'hint_room' => 'b4_buffer'],
        ['flag' => 'record', 'text' => '在 C2 收容廊取样', 'hint_room' => 'b4_c2'],
        ['room' => 'b2b_hall', 'text' => '回指挥大厅交报告'],
    ],
    'reward' => ['credits' => 8, 'money' => 400],
    'settle_note' => '训练记录归档。做完全套，才有资格接野外的活儿。',
]);
crrg_drill_register_mission('m_npc_01', [
    'name' => '私人委托：给情报处换一批台灯', 'level' => '低', 'kind' => 'npc', 'min_lv' => 1,
    'scene' => 'base', 'brief_room' => 'b2b_duty', 'brief_name' => '作战值班室',
    'depart_room' => 'b2b_duty', 'entry_room' => 'b2b_duty',
    'prompt' => '值班小队抱怨夜班的灯太刺眼。去基地商超买两盏冷白台灯，送到情报分析室，再回值班室签字。',
    'steps' => [
        ['have' => ['lamp_cold' => 2], 'text' => '备两盏冷白台灯（商超有售；买多买错都能退）'],
        ['flag' => 'lamp_delivered',   'text' => '把台灯交到情报分析室'],
        ['room' => 'b2b_duty',         'text' => '回作战值班室签字'],
    ],
    'reward' => ['credits' => 4, 'money' => 200],
    'settle_note' => '小队把旧灯收了，说比原来那盏舒服。',
]);
function crrg_drill_npc_missions() {
    $out = [
        'b2b_duty'    => ['值班小队' => ['m_npc_01']],
        'tw_li_house' => ['李承业' => ['m_chase_01']],   // 追书人：委托人
    ];
    /* 基地常驻人员：委托挂在他此刻所在的房间 */
    if (function_exists('crrg_npc_roster')) {
        foreach (crrg_npc_roster() as $id => $d) {
            if (empty($d['task'])) continue;
            $room = function_exists('crrg_npc_room_now') ? crrg_npc_room_now($id) : '';
            if ($room === '') continue;
            $out[$room][$d['n']] = array_merge((array) ($out[$room][$d['n']] ?? []), [$d['task']]);
        }
    }
    return $out;
}

/* 注册观察钩子 */
crrg_drill_add_look_hook('crrg_drill_mod_look');
