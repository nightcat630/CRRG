<?php
/**
 * CRRG 演习 · 收容流程与小队（systems/contain.php，R4，2026-10-14）
 * ------------------------------------------------------------
 * 委员长口径：**按等级职位**——低等级执行低等级任务，**高等级可以带小队**（无名低等级 NPC）协助 ✓
 *
 * 三样东西：
 *   ① **收容等级 × 执行权限**：收容物分 **甲/乙/丙**（甲最危险）；执行权限按**卡级**——
 *      D(1)/C(3) → 只能做**丙**级 ｜ B(5) → 乙级 ｜ A(6)/S(7) → 甲级 ✓（与 `crrg_drill_card_name()` 同源）
 *   ② **五种操作**（每个都带 **2 条解法**＋代价：时间/暴露/理智）：取样／试验／转移／封存解封／等级评定
 *      · 每次操作**必须留监护日志**（只追加 ✓）——日志既是记录，也是可被追责的账 ✓
 *      · 操作会**抬暴露**（有人注意到你）；高风险操作可能**污染/受伤/掉理智**
 *   ③ **小队**：高等级可带 3–5 名**无名低等级 NPC**（每人 胆量/体力/听话）
 *      · 下令（把风／搬运／警戒／协助取样）；**胆量低会拒绝** ✓；**会伤亡**（写进日志＋抬暴露 ✓）
 *
 * 与其他系统：接理智（madness：取样失败掉 SAN ✓）、接车旅（转移＝押运一趟 ✓ 可选）、接事实表（公共后果 ✓）
 */
if (!defined('ABSPATH')) exit;

const CRRG_CONTAIN_OPT = 'crrg_contain';

/* ══════════ 一、等级与权限 ══════════ */

/** 收容等级（甲最危险） */
function crrg_contain_levels() {
    return [
        '甲' => ['rank' => 3, 'card' => 6, 'd' => '会主动伤人的／体量不可控的。进廊即过理智，必须两人以上。'],
        '乙' => ['rank' => 2, 'card' => 5, 'd' => '不会主动追，但接触有代价（污染／记忆／手脚不听使唤）。'],
        '丙' => ['rank' => 1, 'card' => 3, 'd' => '躺着不动的那一类。可以一个人办，但要有装备。'],
    ];
}
/** 这张卡现在能碰哪一级（返回允许的最高等级键） */
function crrg_contain_allow(&$pc) {
    $lv = 0;
    if (function_exists('crrg_drill_eff_lv')) $lv = (int) crrg_drill_eff_lv($pc);
    $lv = max($lv, (int) ($pc['clearance'] ?? 0));
    if ($lv >= 6) return '甲';
    if ($lv >= 5) return '乙';
    if ($lv >= 3) return '丙';
    return '';
}
function crrg_contain_rank_ok(&$pc, $level) {
    $allow = crrg_contain_allow($pc);
    if ($allow === '') return [false, '（你还没到能动手的级别——先去值班板接基础的活。）'];
    $r = ['丙' => 1, '乙' => 2, '甲' => 3];
    if (($r[$level] ?? 9) > ($r[$allow] ?? 0)) {
        return [false, sprintf('（不够级别：这是**%s级**收容物，你的卡只到**%s级**。要走审批，或者找高等级带。）', $level, $allow)];
    }
    return [true, ''];
}

/* ══════════ 二、五种操作（各 2 条解法 ✓ 多解法）══════════ */

function crrg_contain_ops() {
    return [
        '取样' => ['level_hint' => '丙', 'd' => '取一点回来检测。', 'cost' => ['minutes' => 40, 'expose' => 1],
            'sols' => [
                ['n' => '按规程取（稳）', 'check' => ['skill' => '侦查', 'bonus' => 1], 'risk' => 1, 'd' => '慢，但不容易脏手。'],
                ['n' => '伸手快取（险）', 'check' => ['skill' => '敏捷'], 'risk' => 3, 'd' => '快。出事也快。'],
            ]],
        '试验' => ['level_hint' => '乙', 'd' => '在台上试它。', 'cost' => ['minutes' => 120, 'expose' => 2],
            'sols' => [
                ['n' => '按既有方法做（保守）', 'check' => ['skill' => '科学'], 'risk' => 1, 'd' => '得不出新东西，但不会更糟。'],
                ['n' => '加大剂量（激进）', 'check' => ['skill' => '科学', 'penalty' => 1], 'risk' => 4, 'd' => '能问出新东西——也可能把它招醒。'],
            ]],
        '转移' => ['level_hint' => '乙', 'd' => '把它挪个地方（押运）。', 'cost' => ['minutes' => 180, 'expose' => 2],
            'travel' => true,
            'sols' => [
                ['n' => '夜里走（避人）', 'check' => ['skill' => '潜行'], 'risk' => 2, 'd' => '路上清净，出事没人搭手。'],
                ['n' => '白天走（挂牌）', 'check' => ['skill' => '说服'], 'risk' => 1, 'd' => '名正言顺，但记录留得清清楚楚。'],
            ]],
        '封存' => ['level_hint' => '丙', 'd' => '贴封条、落锁、签字。', 'cost' => ['minutes' => 30, 'expose' => 1],
            'sols' => [
                ['n' => '照旧例办（快）', 'risk' => 1, 'd' => '按老规矩填表贴条。'],
                ['n' => '逐项复核（慢）', 'check' => ['skill' => '会计'], 'risk' => 0, 'd' => '一条一条对，慢但不出错。'],
            ]],
        '等级评定' => ['level_hint' => '乙', 'd' => '把它定成几级——写正式结论。', 'cost' => ['minutes' => 90, 'expose' => 1],
            'sols' => [
                ['n' => '按现场证据定', 'check' => ['skill' => '侦查', 'bonus' => 1], 'risk' => 1, 'd' => '拿看得见的东西说话。'],
                ['n' => '按上级意见定', 'risk' => 1, 'd' => '省事。以后出事，签字的是你。'],
            ]],
    ];
}

/* ══════════ 三、监护日志（**只追加** ✓ 也是可追责的账）══════════ */

function crrg_contain_all() { return (array) get_option(CRRG_CONTAIN_OPT, []); }
/** **唯一**写入口（门禁查的就是它：别处不许 update_option ✗ 防两套真相） */
function crrg_contain_save(array $all) { update_option(CRRG_CONTAIN_OPT, $all, false); return $all; }
/** **唯一**的日志写入口（门禁会查：别的地方不许直接写这个 option ✓） */
function crrg_contain_log_add(array $row, $uid = 0) {
    $all = crrg_contain_all();
    $all['log'] = (array) ($all['log'] ?? []);
    $row = $row + ['t' => time(), 'by' => (int) $uid, 'at' => (function_exists('crrg_fact_now') ? crrg_fact_now() : time())];
    array_unshift($all['log'], $row);
    $all['log'] = array_slice($all['log'], 0, 200);            // 只保留最近 200 条（旧的不删语义：见门禁注）
    crrg_contain_save($all);
    return $row;
}
function crrg_contain_log($target = '', $limit = 10) {
    $all = crrg_contain_all();
    $out = [];
    foreach ((array) ($all['log'] ?? []) as $r) {
        if ($target !== '' && (string) ($r['target'] ?? '') !== $target) continue;
        $out[] = sprintf('%s ｜ %s ｜ %s ｜ %s',
            function_exists('crrg_wdate') && !empty($r['at']) ? crrg_wdate('m/d H:i', (int) $r['at']) : date('m/d H:i', (int) ($r['t'] ?? time())),
            (string) ($r['target'] ?? '—'), (string) ($r['op'] ?? '—'), (string) ($r['note'] ?? ''));
        if (count($out) >= $limit) break;
    }
    return $out;
}

/** 等级评定落账（同样走唯一入口 ✓） */
function crrg_contain_rating_set($target, $level, $uid = 0) {
    $all = crrg_contain_all();
    $all['rating'][$target ?: '(未命名)'] = ['level' => (string) $level, 'by' => (int) $uid, 'at' => time()];
    crrg_contain_save($all);
    return true;
}

/* ══════════ 四、执行一次操作 ══════════ */

/**
 * @param string $target 对象名（自由文本，如 "O-11-07 岗位"）
 * @return array 给玩家的行
 */
function crrg_contain_do(&$s, &$pc, $op, $solIdx = 0, $target = '') {
    $ops = crrg_contain_ops();
    if (!isset($ops[$op])) return ['（没有这个操作。可做的：' . implode('、', array_keys($ops)) . '）'];
    $def = $ops[$op];
    $uid = (int) ($pc['uid'] ?? 0);
    /* 权限：默认按该操作的"门槛等级"判（对象等级可另由任务传入 ✓） */
    $level = (string) ($def['level_hint'] ?? '丙');
    if (!empty($GLOBALS['crrg_contain_object_level'])) $level = (string) $GLOBALS['crrg_contain_object_level'];
    [$ok, $why] = crrg_contain_rank_ok($pc, $level);
    if (!$ok) {
        crrg_contain_log_add(['target' => $target, 'op' => $op, 'note' => '被拒：级别不够', 'level' => $level], $uid);
        return [$why];
    }
    $sol = $def['sols'][max(0, (int) $solIdx)] ?? $def['sols'][0];
    $out = [sprintf('〔%s · %s〕%s', $op, $sol['n'], (string) ($sol['d'] ?? ''))];

    /* 检定（可无） */
    $grade = 'clean';
    if (!empty($sol['check'])) {
        $sk = (string) $sol['check']['skill'];
        $val = function_exists('crrg_coc_skill_val') ? max(1, crrg_coc_skill_val($pc, $sk)) : 30;
        $r = crrg_coc_check($sk, $val, (int) ($sol['check']['bonus'] ?? 0), (int) ($sol['check']['penalty'] ?? 0));
        $out[] = $r['text'];
        $rank = function_exists('crrg_coc_level_rank') ? crrg_coc_level_rank($r['level']) : 0;
        $grade = $rank >= 3 ? 'clean' : ($rank >= 2 ? 'cost' : 'fail');
    }
    /* 代价：时间 / 暴露（个人账 ✓ 不碰世界钟） */
    $cost = (array) ($def['cost'] ?? []);
    if ($grade === 'fail') { $cost['minutes'] = (int) ($cost['minutes'] ?? 0) * 2; $cost['expose'] = (int) ($cost['expose'] ?? 0) + 1; }
    if (function_exists('crrg_task_cost_apply')) {
        $out = array_merge($out, crrg_task_cost_apply($s, $pc, 'contain_' . $op, $cost));
    } else {
        if (!empty($cost['minutes'])) $out[] = sprintf('（耗了约 %d 分钟。）', (int) $cost['minutes']);
        if (!empty($cost['expose']))  { $s['expose_contain'] = (int) ($s['expose_contain'] ?? 0) + (int) $cost['expose']; $out[] = '（有人注意到你动过它。）'; }
    }

    /* 事故与理智（风险越高越可能） */
    $risk = (int) ($sol['risk'] ?? 1) + ($grade === 'fail' ? 2 : 0);
    $accident = false;
    if ($risk > 0 && mt_rand(1, 100) <= min(70, $risk * 12)) {
        $accident = true;
        $out[] = '（出了岔子：' . ($op === '取样' ? '样本污染了，手套上有一层灰。' :
                   ($op === '试验' ? '台上的东西动了一下——不是风。' :
                   ($op === '转移' ? '箱子里的东西在颠簸时撞了两下。' : '封条贴歪了，边角翘起来。'))) . '）';
        if (function_exists('crrg_san_check')) $out = array_merge($out, (array) crrg_san_check($pc, '0/1D3', $op . '（事故）'));
    }

    /* 结果 */
    $note = $accident ? '有事故' : '顺利';
    if ($op === '等级评定') {
        crrg_contain_rating_set($target, $level, $uid);
        $out[] = sprintf('（这一件在台账上被定为**%s级**。）', $level);
    }
    /* **每次操作必写日志** ✓（门禁会查） */
    crrg_contain_log_add(['target' => $target ?: '(未命名)', 'op' => $op, 'sol' => (string) $sol['n'],
                          'note' => $note, 'grade' => $grade, 'level' => $level], $uid);
    if (function_exists('crrg_fact_propose') && $accident) {
        crrg_fact_propose('base.收容事故.' . $op, ['uid' => $uid], ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], $uid);
    }
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'contain', 'op' => $op, 'target' => $target, 'accident' => $accident ? 1 : 0], ['src' => 'contain']);
    }
    return $out;
}

/* ══════════ 五、小队（无名低等级 NPC）══════════ */

/** 组队：带 n 人（3–5；高等级才能带 ✓） */
function crrg_squad_form(&$pc, $n = 3) {
    $allow = crrg_contain_allow($pc);
    if ($allow === '' || $allow === '丙') return ['（带小队要更高级别——你现在只能一个人办。）'];
    $n = max(3, min(5, (int) $n));
    $names = ['小周', '小李', '小赵', '小孙', '小陈', '小吴', '小郑'];
    shuffle($names);
    $mem = [];
    for ($i = 0; $i < $n; $i++) {
        $mem[] = ['n' => $names[$i], 'guts' => mt_rand(20, 70), 'stam' => mt_rand(40, 80), 'obey' => mt_rand(50, 90), 'state' => 'ok'];
    }
    $pc['squad'] = $mem;
    return [sprintf('（值班室给你派了 %d 个人：%s。）', $n, implode('、', array_column($mem, 'n'))),
            '（他们没名字——出事时，你得替他们写名字。）'];
}
function crrg_squad_state(&$pc) {
    $sq = (array) ($pc['squad'] ?? []);
    if (!$sq) return ['（没有小队。高等级可以带 3–5 个人。）'];
    $out = ['〔小队〕'];
    foreach ($sq as $m) {
        $out[] = sprintf('　%s 胆量 %d ｜ 体力 %d ｜ 听话 %d ｜ %s',
            $m['n'], $m['guts'], $m['stam'], $m['obey'],
            $m['state'] === 'ok' ? '在位' : ($m['state'] === 'hurt' ? '**受伤**' : '**殉职**'));
    }
    $out[] = '（可下令：把风／搬运／警戒／协助取样）';
    return $out;
}

/** 下令：胆量低会拒绝 ✓；伤亡会写日志＋抬暴露 ✓ */
function crrg_squad_order(&$s, &$pc, $order, $target = '') {
    $sq = (array) ($pc['squad'] ?? []);
    if (!$sq) return ['（没有小队可下令。）'];
    $needGuts = ['把风' => 20, '搬运' => 30, '警戒' => 45, '协助取样' => 55][$order] ?? 30;
    $uid = (int) ($pc['uid'] ?? 0);
    $out = ['〔下令〕' . $order];
    $did = 0; $refused = []; $hurt = [];
    foreach ($sq as $i => $m) {
        if (($m['state'] ?? 'ok') !== 'ok') continue;
        if ((int) $m['guts'] < $needGuts) { $refused[] = $m['n']; continue; }
        $did++;
        $pc['squad'][$i]['stam'] = max(0, (int) $m['stam'] - mt_rand(3, 12));
        /* 危险活（警戒/协助取样）有伤亡概率；胆量越低越容易出事 ✓ */
        if (in_array($order, ['警戒', '协助取样'], true)) {
            $p = max(3, 30 - (int) $m['guts'] / 2);
            if (mt_rand(1, 100) <= $p) {
                $pc['squad'][$i]['state'] = mt_rand(1, 100) <= 35 ? 'dead' : 'hurt';
                $hurt[] = $m['n'] . ($pc['squad'][$i]['state'] === 'dead' ? '（殉职）' : '（受伤）');
            }
        }
    }
    if ($did) $out[] = sprintf('（%d 个人照办了。）', $did);
    if ($refused) $out[] = '（**没动**：' . implode('、', $refused) . '——他们没看你，只是没动。）';
    if ($hurt) {
        $out[] = '（出了事：' . implode('、', $hurt) . '）';
        foreach ($hurt as $h) {
            crrg_contain_log_add(['target' => $target ?: '(小队)', 'op' => '小队·' . $order, 'note' => '伤亡：' . $h], $uid);
        }
        $s['expose_contain'] = (int) ($s['expose_contain'] ?? 0) + count($hurt);
        if (function_exists('crrg_fact_propose')) {
            crrg_fact_propose('base.小队伤亡', ['n' => count($hurt)], ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], $uid);
        }
        if (function_exists('crrg_evt_emit')) {
            crrg_evt_emit('mission.beat', ['kind' => 'squad_casualty', 'n' => count($hurt), 'order' => $order], ['src' => 'contain']);
        }
    }
    /* 下令本身也留一条日志（护理记录里得有这次任务 ✓） */
    crrg_contain_log_add(['target' => $target ?: '(小队)', 'op' => '小队·' . $order,
                          'note' => sprintf('照办 %d 人｜拒 %d 人｜伤亡 %d', $did, count($refused), count($hurt))], $uid);
    return $out;
}

/* ══════════ 六、面板 ══════════ */
function crrg_contain_panel(&$pc) {
    $all = crrg_contain_all();
    $allow = crrg_contain_allow($pc);
    $out = [sprintf('收容台账：执行权限 %s ｜ 记录 %d 条 ｜ 已评定 %d 件',
        $allow === '' ? '（无）' : $allow . '级',
        count((array) ($all['log'] ?? [])), count((array) ($all['rating'] ?? [])))];
    foreach (array_slice(crrg_contain_log('', 3), 0, 3) as $l) $out[] = '　' . $l;
    foreach (crrg_squad_state($pc) as $l) $out[] = $l;
    return $out;
}
