<?php
/**
 * 中型任务《封条》· 第 1 轮（Act I：松的封条）
 *   立案（3 入口）→ 6 个调查点（2 个带检定关卡）→ 4 条线索 → 4 个 NPC → 幕末抉择
 *   承轴：接《清单上少了一件》的钩子（hook_封条不够 ／ hook_备件流程提案）
 *   约定：旗标前缀 seal_（主线）；库存以游戏日计（crrg_seal_inventory）
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    /* 2026-10-14：《封条》已下线（委员长：整个不要了）——只关注册，函数与存档都留着 ✓
       恢复办法：把 missions/_retired.php 里的 crrg_retired_seal() 改成 return true ✓ */
    if (function_exists('crrg_retired_seal') && !crrg_retired_seal()) return;
    if (!function_exists('crrg_drill_register_mission')) return;
    crrg_drill_register_mission('m_mid_seal', [
                'pass_rooms' => ['b2b_hall', 'b3_warehouse', 'b4_main', 'b4_c2', 'b4_obs', 'b2b_tech', 'b1_archive', 'b1_med'],
        'name' => '封条', 'level' => '中', 'kind' => 'base', 'min_lv' => 3, 'chain_no' => 1, 'chain_total' => 5, 'chain_next' => 'm_mid_seal2',
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'deadline_h' => 72,
        'prompt' => '康明远把一张皱掉的报修单推给你：C2 西头那扇门，这个月第三次。',
        'intro' => [
            '〔场景〕你刚进指挥大厅，康明远就站起来了。他没说话，先把一张纸推过来。',
            '报修单：负四层 C2 西头收容廊门体闭合不严。报修人：夜班。经办意见一栏写着四个字——设备老化、暂缓。',
            '「这个月第三次。」他说，「三次都是这扇门，三次都是这四个字。」',
            '他把笔帽扣上：「库存我去数过了，数不明白。你替我办一件事——搞清楚我们手上还剩多少、哪几扇门在松、三年前那批封条为什么停的采购。」',
            '「三天。」他看着你，「三天以后我要在日报上写点东西。写什么，你定。」',
            '（从哪儿下手都行：去物资仓库盘库存，或者先到收容廊看一眼那扇门。同一件事不止一条路——面板上有「别的法子」。）',
        ],
        'steps' => [
            ['room' => 'b2b_hall',         'text' => '在值班板立案'],
            ['flag' => 'seal_库存盘点',    'text' => '盘点：库里封条存量（会计或图书馆使用）'],
            ['flag' => 'seal_门况普查',    'text' => '普查：哪几扇门在松（B4 主走廊／C2 收容廊）'],
            ['flag' => 'seal_用量台账',    'text' => '查台账：观察室的封条领用记录'],
            ['flag' => 'seal_自制配方',    'text' => '找到私自补件的痕迹与配方残页（科学／电气维修）'],
            ['flag' => 'seal_三年档案',    'text' => '翻三年前的采购档案（为什么被砍）'],
            ['flag' => 'seal_来源指向镇上','text' => '把四条线索对到一起——材料来自镇上'],
            ['flag' => 'seal_幕末决断',    'text' => '第一幕决断：上报，还是压下'],
        ],
        'reward' => ['credits' => 12, 'money' => 300], 'rep' => ['base' => 3],
        'settle_note' => '第一件事了了。你把结论折好，放进值班袋。',
    ]);
}, 21);

/** 接单时初始化：库存 + 立案旗标（由任务系统的 accept 钩子调用） */
function crrg_seal_on_accept(&$s, &$pc, $id) {
    if ($id !== 'm_mid_seal') return;
    crrg_seal_inventory($pc);
    if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
    foreach (['seal_立案'] as $f) if (!in_array($f, $s['flags'], true)) $s['flags'][] = $f;
}

/* ══════════ 封条库存（游戏日计，影响后续事故概率） ══════════ */
function crrg_seal_inventory(&$pc) {
    if (!isset($pc['seal']) || !is_array($pc['seal'])) {
        $pc['seal'] = ['stock_days' => 11, 'used' => 0, 'craft' => '', 'stage' => 1, 'day0' => crrg_wdate('Ymd', crrg_vitals_now())];
    }
    return $pc['seal'];
}
function crrg_seal_use(&$pc, $days = 1, $note = '') {
    crrg_seal_inventory($pc);
    $pc['seal']['stock_days'] = max(0, (int) $pc['seal']['stock_days'] - (int) $days);
    $pc['seal']['used'] = (int) ($pc['seal']['used'] ?? 0) + (int) $days;
    return $pc['seal']['stock_days'];
}

/* ══════════ Act I：调查点总表（房间 + 关键词 → 检定／旗标／文本） ══════════ */
function crrg_seal_survey(&$s, &$pc, $arg) {
    /* 2026-09-29 合并三份 survey：数据由旧实现**逐字提取**生成（禁止手抄） */
    $FT = '（没弄明白，可以再来，或者找人一起看。）';
    $rows = [
        'b3_warehouse' => [
            ['kw' => '封条', 'flag' => 'seal_库存盘点', 'skill' => '会计', 'fail_text' => $FT,
             'texts' => array (
  0 => '你把封条的入库与领用逐笔对。账面存量与实物差了七张。',
  1 => '按现有用量推：**只够 11 天**。',
  2 => '（线索：库存缺口。）',
)],
        ],
        'b4_main' => [
            ['kw' => '封条', 'flag' => 'seal_门况普查', 'fail_text' => $FT,
             'texts' => array (
  0 => '你沿收容廊逐扇看过去。十一条廊门，五扇的封条边缘起了毛。',
  1 => '最里面那扇（C2 西头）用指头一按，就陷下去——它已经没有粘性了。',
  2 => '（线索：门况普查，C2 西头最差。）',
)],
        ],
        'b4_c2' => [
            ['kw' => '门', 'flag' => 'seal_私补痕迹', 'fail_text' => $FT,
             'texts' => array (
  0 => '这扇门比记录上旧。门框内侧有四道重叠的贴痕——有人反复补过。',
  1 => '补的手法不专业：上缘对不齐，胶是黄色的，不是基地发的那种。',
  2 => '（线索：**有人私自补过件。**）',
)],
        ],
        'b4_obs' => [
            ['kw' => '台账', 'flag' => 'seal_用量台账', 'skill' => '图书馆使用', 'fail_text' => $FT,
             'texts' => array (
  0 => '领用台账记得很规矩。但你注意到：**每月月底那三次领用，签收栏都是空的**。',
  1 => '金额不大，正好够补一扇门。',
  2 => '（线索：月底三次无签收领用。）',
)],
        ],
        'b2b_tech' => [
            ['kw' => '配方', 'flag' => 'seal_自制配方', 'skill' => '科学', 'fail_text' => $FT,
             'texts' => array (
  0 => '废纸篓最底下压着半页手写配方，被水泡过：**朱砂、明矾、铅箔、糯米浆**。',
  1 => '旁边一行小字：「比例别按书上，按我试的。」',
  2 => '（线索：土法配方残页——**含铅，长期接触伤身**。）',
)],
        ],
        'b1_archive' => [
            ['kw' => '采购', 'flag' => 'seal_三年档案', 'fail_text' => $FT,
             'texts' => array (
  0 => '三年前有一份《镇物耗材续购申请》，金额一栏被红笔划掉，旁边写：**非必要支出，暂缓**。',
  1 => '申请人是当时的库管，签字人一栏空着。',
  2 => '（线索：采购被砍，签字人空缺。）',
)],
        ],
    ];

$rows = array_map(function($rs){ foreach($rs as &$r){$r['post']='crrg_seal_check_dossier';} return $rs; }, $rows);
    return function_exists('crrg_seal_survey_engine') ? crrg_seal_survey_engine($s, $pc, $arg, $rows) : null;
}

function crrg_seal_stage_choice(&$s, &$pc, $key) {
    return crrg_seal_choice_engine($s, $pc, $key, [
        'prompt'   => '（第一幕决断：「决断 上报」或「决断 压下」。）',
        'fallback' => '（第一幕决断：「决断 上报」或「决断 压下」。）',
        'gate'     => ['need' => ['seal_库存盘点', 'seal_门况普查', 'seal_私补痕迹', 'seal_用量台账', 'seal_自制配方', 'seal_三年档案', 'seal_来源指向镇上'],
                        'bypass_on' => ['压'],
                        'refuse' => function ($s2, $pc2, $miss2) {
                            return array_merge(['证据还不齐。'], ['（缺：' . implode('、', $miss2) . '）']);
                        }],
        'once'     => ['flags' => ['seal_路线_上报', 'seal_路线_压下'],
                       'done'  => function ($s2) {
                           return ['（第一幕的结论已经交上去了——' . (crrg_seal_has($s2, 'seal_路线_上报') ? '走的是上报' : '走的是压下') . '。要改只能等结案时认账。）'];
                       }],
        'options'  => [
            ['match' => '上报', 'flags' => ['seal_幕末决断', 'seal_路线_上报'],
             'rep' => ['base', 4, '《封条》第一幕：上报'],
             'texts' => ['你把四条线索与那半页配方一起交上去，附了一句"采购暂缓不合理"。',
                         '当天下午，值班室把封条存量列进了日报——但没有多余的人手。',
                         '（基地声望 +4 ｜ 路线：上报——第二幕你会缺人少车，但基地信你。）']],
            ['match' => '压下', 'flags' => ['seal_幕末决断', 'seal_路线_压下', 'hook_背着一条账'],
             'rep' => ['base', 1, '《封条》第一幕：压下'], 'stock' => [1, '第一幕自行补件'],
             'texts' => ['你把材料收进自己的柜子，跟值班室说"还在查"。',
                         '你从库里领了一张封条，先把 C2 西头那扇补上——**库存减 1 天**。',
                         '（基地声望 +1 ｜ 路线：压下——第二幕你能调车调人，但账上多一笔。）']],
        ],
    ]);
}

function crrg_seal_stage_lines($s, $pc) {
    $inv = crrg_seal_inventory($pc);
    $flags = (array) ($s['flags'] ?? []);
    $clues = count(array_intersect(['seal_库存盘点','seal_门况普查','seal_私补痕迹','seal_用量台账','seal_自制配方','seal_三年档案'], $flags));
    return [sprintf('第一幕 · 松的封条 ｜ 线索 %d/5 ｜ 封条存量 %d 天', $clues, (int) $inv['stock_days']),
            '（调查：封条／门／台账／配方／采购；问人：周德海／韩雪／守卫／白大夫；决断：上报 or 压下）'];
}

function crrg_seal_npc_ask(&$s, &$pc, $npc, $topic = '') {
    /* 表内仍用 $f（白大夫那类"好感门限"话题需要运行时值）——所以表与 $f 都在本函数里，
       交给引擎的只是**转换后的行**（2026-09-29 合并三份重复逻辑·零风险版） */
    $f = (int) ($pc['npc_fav'][$npc] ?? 0);
    $Q = [
        '周德海' => ['就地取材' => [
            ['他沉默了一会儿：「库里那几扇门，我不补，十一天以后出事算谁的。」',
             '「配方是我从镇上抄的。纸扎铺的老师傅教我的。」',
             '（他把"镇上"两个字说得很轻。）'],
            'seal_npc_周德海', ['seal_来源指向镇上']]],
        '韩雪' => ['配方' => [
            ['韩雪看了一眼那半页纸，脸色就变了：「铅箔？他知道这东西伤身吗？」',
             '「我们实验室有替代方案，但要走流程——流程要三个月。」',
             '（她记下配方，说要去查替代材料。）'],
            'seal_npc_韩雪', ['seal_替代方案在研']]],
        '守卫' => ['哪几扇' => [
            ['收容廊的守卫掰着手指给你数：「主走廊五扇，C2 西头一扇——那扇最麻烦。」',
             '「上个月我巡到那儿，听见里头有动静。报上去，没人接。」'],
            'seal_npc_守卫', ['seal_已知危门']]],
        '白大夫' => ['当年' => [
            $f >= 2
                ? ['白大夫叹了口气：「三年前 C1 廊出过一次事。就是因为封条过期。」',
                   '「那次撤了两个人：一个调离，一个退休。采购就是那时候被砍的。」',
                   '（旧账：三年前的 C1 事故。）']
                : ['白大夫把本子合上：「那是三年前的事了。你要真想问，先跟康明远说一声。」',
                   '（她不想跟一个生人讲旧账——先攒点人情再来。）'],
            'seal_npc_白大夫', $f >= 2 ? ['seal_三年事故'] : []]],
    ];
    if (!isset($Q[$npc])) return null;
    $rows = [];
    foreach ((array) $Q[$npc] as $kw => $row) {
        $rows[$npc][] = [$kw, (string) ($row[1] ?? ''), (array) ($row[0] ?? []), (array) ($row[2] ?? [])];
    }
    return function_exists('crrg_seal_ask_engine') ? crrg_seal_ask_engine($s, $pc, $npc, $topic, $rows) : null;
}
