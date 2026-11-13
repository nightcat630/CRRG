<?php
/**
 * CRRG 演习 · 理智与疯狂（systems/madness.php，第 1 轮，2026-10-14）
 * ------------------------------------------------------------
 * 委员长口径：**尽量按 CoC（克苏鲁的呼唤 7e）还原** ✓
 *
 * CoC 7e 里这套规则的骨架（我们照做）：
 *   ① **理智检定**：掷 D100 对抗当前 SAN —— 成功按"成功档"掉、失败按"失败档"掉（骰式写法 `1/1D4`、`1D3/1D10`）
 *   ② **一次性损失 ≥ 5** → **临时性疯狂**：当场发作（1D10 回合／直到本场景结束），内容掷 **1D10 发作表**
 *   ③ **一天内累计损失 ≥ 当前 SAN 的 1/5** → **不定性疯狂**：掷 1D10 抽**长期症状**（恐惧症／躁狂），长期生效
 *   ④ **恢复**：每周一次**心理分析**检定成功 → 恢复 1D3 点；休养（院内）另有缓慢恢复
 *   ⑤ **恐惧症／躁狂**：相关检定 **+1 惩罚骰**，并在面板上可见 ✓
 *
 * 我们自己加的两样（游戏化）：
 *   · **目击阈值**：镇物／尸体／超自然现象**首次**目击过一次理智检定 ✓（幂等：`seen_<key>`，同一件东西只过一次）
 *   · **面板可见**：理智值／临时疯狂剩几个动作／症状清单／下次治疗时间 ✓
 *
 * 纪律：**掉得下去，就得回得来** ✗（门禁 verify-madness.php 会查：恢复途径必须在 ✓）
 */
if (!defined('ABSPATH')) exit;

/* ══════════ 一、表（照 CoC 走；文面是我们自己的语气）══════════ */

/** 临时性疯狂发作表（1D10） */
function crrg_mad_fit_table() {
    return [
        1  => ['昏厥', '眼前一黑，你失去了这一段时间。醒来时人在原地，姿势变了。'],
        2  => ['尖叫', '你先听见自己的声音，才意识到那是自己在叫。'],
        3  => ['逃跑', '脚比脑子快。你回过神时已经跑出去一段，方向是背光的那侧。'],
        4  => ['僵直', '你钉在原地，手还保持着刚才的姿势，指节发白。'],
        5  => ['攻击最近的人', '你朝离得最近的那个人过去了——不是恨，是本能。'],
        6  => ['狂笑', '你笑了。笑得停不下来，听着不像自己。'],
        7  => ['自言自语', '你开始对着空气解释，一句一句，很清楚。'],
        8  => ['失忆', '这一段被抹掉了。你手上有东西，不记得什么时候拿的。'],
        9  => ['迷信行为', '你反复做同一个动作——数、拍、摸门框，直到心里踏实。'],
        10 => ['晕眩与呕吐', '世界开始旋转。你扶着墙，胃里翻上来。'],
    ];
}

/** 长期症状：恐惧症（1D10） */
function crrg_mad_phobia_table() {
    return [
        1  => ['黑暗恐惧', '关灯的房间你不肯久留。'],
        2  => ['密闭恐惧', '电梯、柜子、管道——门一合你就数秒。'],
        3  => ['高处恐惧', '能看见落差的地方，腿会先软。'],
        4  => ['水恐惧', '不是怕水，是怕水下的东西。'],
        5  => ['血恐惧', '见血会手抖，处理伤口时会慢一拍。'],
        6  => ['人群恐惧', '人一多你就想着出口在哪。'],
        7  => ['声音恐惧', '安静里突然的响动，你会整个人绷住。'],
        8  => ['镜子恐惧', '反光的东西你会绕开。'],
        9  => ['空旷恐惧', '开阔地让你无处可躲，你总想找墙。'],
        10 => ['被注视恐惧', '你开始相信，有人一直在看着你。'],
    ];
}

/** 长期症状：躁狂（1D10） */
function crrg_mad_mania_table() {
    return [
        1  => ['洁癖', '你必须洗手。次数不重要，干净才重要。'],
        2  => ['强迫清点', '你会数东西：门、灯、子弹、台阶。数错就得重来。'],
        3  => ['失眠', '躺下就是一片黑暗，你宁愿别躺。'],
        4  => ['强迫记录', '发生过的事你要写下来。不写，它就不算结束。'],
        5  => ['疑心', '别人的每一句话你都过一遍——他在指什么？'],
        6  => ['强迫复检', '同一件事你要查三遍。第三遍才算数。'],
        7  => ['危险癖', '你必须站得更近一点，才能看清。'],
        8  => ['收集癖', '你会把那件东西捡回来，虽然知道不该。'],
        9  => ['语速过快', '话赶话，别人插不进来。'],
        10 => ['末日计日', '你开始默数：还剩多少天。'],
    ];
}

/* ══════════ 二、骰式与理智检定 ══════════ */

/** 解析骰式："1/1D4"（成功/失败）｜"5"（固定）｜"1D3/1D10" */
function crrg_mad_parse_expr($expr) {
    $expr = trim((string) $expr);
    if (strpos($expr, '/') === false) return [(string) $expr, (string) $expr];
    $p = explode('/', $expr, 2);
    return [trim($p[0]), trim($p[1])];
}
/** 掷一个骰式（支持 3、1D4、2D6） */
function crrg_mad_roll($dice) {
    $dice = strtoupper(trim((string) $dice));
    if ($dice === '' || $dice === '0') return 0;
    if (preg_match('/^(\d*)D(\d+)$/', $dice, $m)) {
        $n = (int) ($m[1] === '' ? 1 : $m[1]); $f = (int) $m[2]; $s = 0;
        for ($i = 0; $i < $n; $i++) $s += mt_rand(1, $f);
        return $s;
    }
    return (int) $dice;
}

/** 理智值（= 卡上的 san ✓）；上限 99 */
function crrg_san_of(&$pc) { return max(0, min(99, (int) ($pc['san'] ?? 0))); }

/**
 * **理智检定**（CoC 7e 标准做法）
 * @param string $expr 骰式（成功档/失败档），例 '1/1D4'
 * @return array 给玩家看的行 + 机读结果
 */
function crrg_san_check(&$pc, $expr, $why = '', array $opt = []) {
    $san = crrg_san_of($pc);
    [$okDice, $failDice] = crrg_mad_parse_expr($expr);
    $roll = mt_rand(1, 100);
    $ok = ($roll <= $san);                                   // CoC：≤ SAN 为成功 ✓
    $dice = $ok ? $okDice : $failDice;
    $loss = crrg_mad_roll($dice);
    if (!empty($opt['no_loss_on_success']) && $ok) $loss = 0;
    $out = [];
    $out[] = sprintf('理智检定：D100＝%d ／ %s（当前理智 %d）', $roll, $ok ? '成功' : '失败', $san);
    if ($loss > 0) {
        $pc['san'] = max(0, $san - $loss);
        $out[] = sprintf('理智 −%d → %d。', $loss, crrg_san_of($pc));
    } else {
        $out[] = '理智没有损失。';
    }
    crrg_mad_log($pc, ['t' => time(), 'why' => (string) $why, 'expr' => (string) $expr, 'roll' => $roll,
                       'ok' => $ok, 'loss' => $loss, 'san' => crrg_san_of($pc), 'day' => crrg_mad_day()]);
    if ($loss > 0) $out = array_merge($out, crrg_mad_after_loss($pc, $loss, $why));
    return array_merge($out, []);
}

/** 不掷骰的固定损失（剧情/事件用） */
function crrg_san_loss(&$pc, $n, $why = '') {
    $n = max(0, (int) $n);
    if ($n <= 0) return [];
    $pc['san'] = max(0, crrg_san_of($pc) - $n);
    $out = [sprintf('理智 −%d → %d。', $n, crrg_san_of($pc))];
    crrg_mad_log($pc, ['t' => time(), 'why' => (string) $why, 'expr' => (string) $n, 'roll' => 0,
                       'ok' => null, 'loss' => $n, 'san' => crrg_san_of($pc), 'day' => crrg_mad_day()]);
    return array_merge($out, crrg_mad_after_loss($pc, $n, $why));
}
function crrg_san_gain(&$pc, $n, $why = '') {
    $n = max(0, (int) $n);
    $pc['san'] = min(99, crrg_san_of($pc) + $n);
    if ($n > 0 && function_exists('crrg_evt_emit')) crrg_evt_emit('mission.beat', ['kind' => 'san_up', 'n' => $n, 'why' => $why, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'madness']);
    return $n > 0 ? [sprintf('理智 +%d → %d。', $n, crrg_san_of($pc))] : [];
}

/** 今天的"日"键（暂时用世界钟的日期；等第 2 轮日历做好再换口径 ✓） */
function crrg_mad_day() {
    if (function_exists('crrg_world_clock')) { $c = crrg_world_clock(); return (string) ($c['ymd'] ?? date('Ymd')); }
    return date('Ymd');
}
function crrg_mad_log(&$pc, array $row) {
    $pc['san_log'] = (array) ($pc['san_log'] ?? []);
    array_unshift($pc['san_log'], $row);
    $pc['san_log'] = array_slice($pc['san_log'], 0, 40);
    /* 当日累计（CoC 的"一天内"） */
    $d = (string) ($row['day'] ?? crrg_mad_day());
    $acc = (array) ($pc['san_day'] ?? []);
    if (($acc['day'] ?? '') !== $d) $acc = ['day' => $d, 'loss' => 0];
    $acc['loss'] = (int) $acc['loss'] + (int) ($row['loss'] ?? 0);
    $pc['san_day'] = $acc;
}

/* ══════════ 三、疯狂判定（CoC 两条阈值）══════════ */

/** 掉完理智之后的判定：① 单次 ≥5 → 临时疯狂 ② 当日累计 ≥ 当前SAN/5 → 不定疯狂 */
function crrg_mad_after_loss(&$pc, $loss, $why = '') {
    $out = [];
    if ($loss >= 5) $out = array_merge($out, crrg_mad_temp($pc, $why));
    $san = crrg_san_of($pc);
    $acc = (int) (($pc['san_day']['loss'] ?? 0));
    $thr = max(1, (int) floor($san / 5));
    if ($san > 0 && $acc >= $thr) $out = array_merge($out, crrg_mad_indef($pc, $why, true));
    return $out;
}

/** 临时性疯狂（CoC）：当场发作 1D10 回合；我们记"还剩几个动作" ✓ */
function crrg_mad_temp(&$pc, $why = '') {
    if (($pc['mad']['temp_until'] ?? 0) > 0) return [];                  // 已经在发作里 ✓ 幂等
    $t = crrg_mad_fit_table();
    $roll = mt_rand(1, 10);
    $fit = $t[$roll] ?? ['发作', '你有一瞬间不像你自己。'];
    $acts = 1 + (int) floor(crrg_mad_roll('1D10') / 3);                  // 1D10 回合 → 折算成 1–4 个动作
    $pc['mad']['temp_until'] = $acts;
    $pc['mad']['temp'][] = ['roll' => $roll, 'name' => $fit[0], 'at' => time()];
    $pc['mad']['temp'] = array_slice((array) $pc['mad']['temp'], -5);
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'madness_temp', 'name' => $fit[0], 'roll' => $roll, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'madness']);
    }
    return [
        '〔临时性疯狂〕' . $fit[0],
        $fit[1],
        sprintf('（接下来约 %d 个动作，你不完全归自己管——细化判断与精细操作都会打折。）', $acts),
    ];
}

/** 不定性疯狂（CoC）：抽长期症状（恐惧症/躁狂） */
function crrg_mad_indef(&$pc, $why = '', $oncePerDay = false) {
    $day = crrg_mad_day();
    if ($oncePerDay && (($pc['mad']['indef_day'] ?? '') === $day)) return [];   // 同日只抽一次 ✓ 幂等
    $ph = crrg_mad_phobia_table(); $ma = crrg_mad_mania_table();
    $kind = mt_rand(1, 2) === 1 ? 'phobia' : 'mania';
    $roll = mt_rand(1, 10);
    $row = $kind === 'phobia' ? ($ph[$roll] ?? ['恐惧', '你怕上了什么。']) : ($ma[$roll] ?? ['躁狂', '你非做不可。']);
    $pc['mad']['sym'][] = ['kind' => $kind, 'roll' => $roll, 'name' => $row[0], 'at' => time(), 'day' => $day];
    $pc['mad']['sym'] = array_slice((array) $pc['mad']['sym'], -8);
    $pc['mad']['indef'] = true; $pc['mad']['indef_day'] = $day;
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'madness_indef', 'name' => $row[0], 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'madness']);
    }
    return [
        '〔不定性疯狂〕一段时间的累计损失压过了你。',
        sprintf('症状（%s）：%s', $kind === 'phobia' ? '恐惧症' : '躁狂', $row[0]),
        $row[1],
        '（相关的事，你往后都会手抖一下；面板上记着它。）',
    ];
}

/* ══════════ 四、恢复（CoC：每周一次心理分析 1D3）══════════ */

const CRRG_MAD_WEEK_KEY = 'mad_therapy_day';

/** 心理治疗：每周一次，心理分析检定成功 → 恢复 1D3 */
function crrg_san_therapy(&$pc, $weekTag = '') {
    $weekTag = $weekTag ?: crrg_mad_day();
    if (($pc[CRRG_MAD_WEEK_KEY] ?? '') === $weekTag) return ['（这周已经做过一次了——下周再来。）'];
    $pc[CRRG_MAD_WEEK_KEY] = $weekTag;
    $skill = '心理分析';
    $val = function_exists('crrg_coc_skill_val') ? max(1, crrg_coc_skill_val($pc, $skill)) : 20;
    $r = crrg_coc_check($skill, $val);
    $out = ['你坐下来，把这段时间的事从头讲了一遍。', $r['text']];
    if (function_exists('crrg_coc_level_rank') && crrg_coc_level_rank($r['level']) >= 2) {
        $n = crrg_mad_roll('1D3');
        $out = array_merge($out, crrg_san_gain($pc, $n, '心理分析'));
        /* 治好一条症状（按最旧的一条）✓ */
        $sym = (array) ($pc['mad']['sym'] ?? []);
        if ($sym) {
            $gone = array_shift($sym);
            $pc['mad']['sym'] = array_values($sym);
            $out[] = sprintf('（你把「%s」这件事说出来了——它轻了一点。）', $gone['name'] ?? '那件事');
        }
        if (empty($pc['mad']['sym'])) $pc['mad']['indef'] = false;
    } else {
        $out[] = '（这一回没谈进去。下次再来。）';
    }
    return $out;
}

/** 休养：院内静养，每个游戏日恢复 1 点（缓慢，且要时间 ✓） */
function crrg_san_rest(&$pc, $days = 1) {
    $days = max(1, (int) $days);
    $n = $days;
    return array_merge(['（这几天你按医嘱躺着，什么也没干。）'], crrg_san_gain($pc, $n, '休养'));
}

/* ══════════ 五、目击阈值（同一件东西只过一次 ✓ 幂等）══════════ */

function crrg_mad_witness(&$pc, $key, $expr, $name = '', $why = '') {
    $flag = 'seen_' . preg_replace('/[^a-z0-9_]/i', '_', (string) $key);
    if (function_exists('crrg_seal_has')) { /* 兼容：旗标走个人档由调用方传 $s */ }
    if (!empty($GLOBALS['crrg_ctx_seen'][$flag])) return [];             // 同一请求内幂等
    $GLOBALS['crrg_ctx_seen'][$flag] = true;
    $out = [];
    if ($name !== '') $out[] = sprintf('（你第一次真正看清：%s。）', $name);
    return array_merge($out, crrg_san_check($pc, $expr, $why ?: ('目击：' . $name)));
}

/* ══════════ 六、症状对判定的影响（CoC：相关检定 +1 惩罚骰）══════════ */

/** 这条症状跟这个技能/场景相不相关（关键词法；粗糙但够用 ✓） */
function crrg_mad_sym_hits(&$pc, $skill = '', $tag = '') {
    $hits = [];
    foreach ((array) ($pc['mad']['sym'] ?? []) as $s) {
        $n = (string) ($s['name'] ?? '');
        $map = [
            '黑暗恐惧' => ['侦查', '夜', 'dark'], '密闭恐惧' => ['潜行', '管道', '柜'],
            '高处恐惧' => ['攀爬', '梯'], '水恐惧' => ['游泳', '水'], '血恐惧' => ['急救', '医学'],
            '人群恐惧' => ['说服', '人群'], '声音恐惧' => ['聆听', '听'], '镜子恐惧' => ['侦查', '反光'],
            '空旷恐惧' => ['野外', '追踪'], '被注视恐惧' => ['潜行', '藏'],
            '洁癖' => ['医学', '急救'], '强迫清点' => ['会计', '库存'], '失眠' => ['夜'],
            '强迫记录' => ['图书馆使用', '档案'], '疑心' => ['说服', '话'], '强迫复检' => ['侦查', '档案'],
            '危险癖' => ['侦查'], '收集癖' => ['物品'], '语速过快' => ['说服'], '末日计日' => ['日历'],
        ];
        foreach ((array) ($map[$n] ?? []) as $kw) {
            if ($kw !== '' && ((string) $skill === $kw || ($tag !== '' && mb_strpos((string) $tag, $kw) !== false)
                || mb_strpos($n, (string) $skill) !== false)) { $hits[] = $n; break; }
        }
    }
    return array_unique($hits);
}

/** 给 coc_check 叠的惩罚骰（在引擎/指令里只要设全局，就自动生效 ✓ 同身体那套） */
function crrg_mad_penalty(&$pc, $skill = '', $tag = '') {
    $hits = crrg_mad_sym_hits($pc, $skill, $tag);
    if (($pc['mad']['temp_until'] ?? 0) > 0) return 1;                    // 临时疯狂期间：精细活打折 ✓
    return $hits ? 1 : 0;
}

/* ══════════ 七、每次动作的收尾（计时/复位）══════════ */

function crrg_mad_tick(&$pc) {
    if (($pc['mad']['temp_until'] ?? 0) > 0) $pc['mad']['temp_until'] = max(0, (int) $pc['mad']['temp_until'] - 1);
    /* 换日：当日累计归零 ✓（CoC 的"一天内"） */
    $d = crrg_mad_day();
    if (($pc['san_day']['day'] ?? '') !== $d) $pc['san_day'] = ['day' => $d, 'loss' => 0];
    return true;
}

/* ══════════ 八、面板 ══════════ */
function crrg_mad_panel(&$pc) {
    $san = crrg_san_of($pc);
    $line = sprintf('理智 %d／99', $san);
    if (($pc['mad']['temp_until'] ?? 0) > 0) $line .= sprintf('　〔临时性疯狂：还剩 %d 个动作〕', (int) $pc['mad']['temp_until']);
    $sym = (array) ($pc['mad']['sym'] ?? []);
    if ($sym) {
        $names = array_map(fn($s) => (string) ($s['name'] ?? ''), $sym);
        $line .= '　症状：' . implode('、', array_slice($names, 0, 3));
    }
    $out = [$line];
    if ($san <= 20) $out[] = '（理智已经很低了——去医务室谈谈，或者躺几天。）';
    if (($pc['san_day']['loss'] ?? 0) >= 1) $out[] = sprintf('（今天已经掉了 %d 点。）', (int) $pc['san_day']['loss']);
    return $out;
}
