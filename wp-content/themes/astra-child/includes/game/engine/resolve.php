<?php
/**
 * CRRG 演习 · 多解法与检定（engine/resolve.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 治两件：**多解法**（同一件事至少两条路 ✓）与**失败代价**（不再"没成可以再来" ✗）。
 *
 * 一条解法（path）长这样：
 *   ['name'=>'硬来（撬门）', 'type'=>'brute', 'hp'=>1,
 *    'need'=>['item'=>'tool_pry'] | ['skill'=>'侦查'] | ['who'=>'kang','fav'=>2],
 *    'check'=>['skill'=>'侦查','bonus'=>0],
 *    'cost'=>['minutes'=>30,'expose'=>1,'flag'=>'痕迹_撬过锁'],
 *    'gain'=>['clues'=>['锁','划痕'], 'stage'=>'s2', 'fact'=>['base.水务间被动过','once']]]
 *
 * 三档结果（成/成但有代价/代价大）：检定成功＝干净；勉强成功＝**加代价**；失败＝**付代价且推进**（fail forward ✓）
 *   —— 失败不再原地重试：它推进时间/暴露/痕迹，世界因此变化 ✓
 */
if (!defined('ABSPATH')) exit;

/** 取某任务的一条解法 */
function crrg_task_path($task, $pid) {
    $t = crrg_task($task);
    if (!$t) return null;
    $p = (array) ($t['paths'][$pid] ?? null);
    return $p ? ($p + ['id' => $pid]) : null;
}

/** 可行性：前置（道具/技能/关系/旗标/事实）——**不满足就说清为什么**，不惩罚 ✓ */
function crrg_path_gate(&$s, &$pc, $task, $path) {
    $need = (array) ($path['need'] ?? []);
    if (function_exists('crrg_task_need_ok')) return crrg_task_need_ok($s, $pc, $need, $path);   // P1.9：统一走对接层 ✓
    $flags = (array) ($s['flags'] ?? []);
    foreach ((array) ($need['flags'] ?? []) as $f) {
        if (!in_array($f, $flags, true)) return [false, '（还差一步：' . ($path['need_hint'] ?? '有些事没办完') . '）'];
    }
    foreach ((array) ($need['not_flags'] ?? []) as $f) {
        if (in_array($f, $flags, true)) return [false, '（这条路已经走不通了。）'];
    }
    if (!empty($need['item'])) {
        $own = function_exists('crrg_drill_own_count') ? crrg_drill_own_count($pc, (string) $need['item']) : 0;
        if ($own < (int) ($need['n'] ?? 1)) {
            $nm = function_exists('crrg_item_name') ? crrg_item_name((string) $need['item']) : $need['item'];
            return [false, '（手上没有' . $nm . '。）'];
        }
    }
    if (!empty($need['who'])) {
        $fav = (int) ($pc['npc_fav'][$need['who']] ?? 0);
        if ($fav < (int) ($need['fav'] ?? 0)) {
            $nm = function_exists('crrg_npc_name') ? crrg_npc_name($need['who']) : $need['who'];
            return [false, sprintf('（%s 现在不肯帮——人情 %d，至少要 %d。跑腿能涨。）', $nm, $fav, (int) ($need['fav'] ?? 0))];
        }
    }
    if (!empty($need['stage']) && function_exists('crrg_task_stage')) {
        if (crrg_task_stage($s, $task) === '' && empty($path['allow_before_begin'])) return [false, '（这件事现在轮不到你管。）'];
    }
    return [true, ''];
}

/**
 * 走一条解法。返回给玩家的行数组。
 * @return array
 */
function crrg_task_try(&$s, &$pc, $task, $pid) {
    $path = crrg_task_path($task, $pid);
    if (!$path) return ['（没有这条做法。）'];
    [$ok, $why] = crrg_path_gate($s, $pc, $task, $path);
    if (!$ok) return [$why];

    $out = ['〔' . ($path['name'] ?? $pid) . '〕'];

    /* 2026-10-14（P2.5）：**对抗型解法**——交给战斗系统，胜负由那里判，落账回来再收 ✓ */
    if (($path['type'] ?? '') === 'fight' && function_exists('crrg_task_fight_start')) {
        foreach ((array) ($path['text'] ?? []) as $x) $out[] = $x;
        return array_merge($out, (array) crrg_task_fight_start($s, $pc, $task, $pid, $path));
    }

    /* 2026-10-14（R3）：**远程交报告型解法**——走手机/通讯，结算用同一套 ✓ */
    if (($path['type'] ?? '') === 'report' && function_exists('crrg_phone_report')) {
        foreach ((array) ($path['text'] ?? []) as $x) $out[] = $x;
        return array_merge($out, (array) crrg_phone_report($s, $pc, $GLOBALS['crrg_ctx'] ?? [], '', (string) ($path['body'] ?? '')));
    }

    /* 2026-10-14（links2）：**交易型** 与 **鉴定型** 解法 ✓ */
    if (($path['type'] ?? '') === 'trade' && function_exists('crrg_l2_trade')) {
        return crrg_l2_trade($s, $pc, $task, $pid, $path);
    }
    if (($path['type'] ?? '') === 'appraise' && function_exists('crrg_l2_appraise')) {
        return crrg_l2_appraise($s, $pc, $task, $pid, $path);
    }

    /* 2026-10-14（P1.9）：**行程型解法**——交给车旅系统，到达后回来落账 ✓ */
    if (($path['type'] ?? '') === 'travel' && function_exists('crrg_task_travel_start')) {
        foreach ((array) ($path['text'] ?? []) as $x) $out[] = $x;
        return array_merge($out, (array) crrg_task_travel_start($s, $pc, $task, $pid, $path));
    }

    $grade = 'clean';                                   // clean | cost | fail

    /* 检定（可选）：复用 CoC 规则层 ✓ */
    $check = (array) ($path['check'] ?? []);
    if ($check) {
        $skill = (string) ($check['skill'] ?? '侦查');
        $val = function_exists('crrg_coc_skill_val') ? max(1, crrg_coc_skill_val($pc, $skill)) : 30;
        /* 2026-10-14（R9）：**加成来源**（gear:手电 ／ team:lookout ／ duty:执勤长）
           —— 好装备给奖励骰、报废的给惩罚骰、分工对路给奖励骰 ✓ 一处统一算 */
        $bonus = (int) ($check['bonus'] ?? 0); $penalty = (int) ($check['penalty'] ?? 0);
        foreach ((array) ($check['bonus_from'] ?? []) as $src) {
            $src = (string) $src;
            if (strpos($src, 'gear:') === 0 && function_exists('crrg_gear_bonus')) {
                $b = crrg_gear_bonus($pc, substr($src, 5), $skill);
                if ($b > 0) $bonus++; elseif ($b < 0) $penalty++;
            } elseif (strpos($src, 'team:') === 0 && function_exists('crrg_team_bonus')) {
                if (crrg_team_bonus($pc, substr($src, 5), $skill) > 0) $bonus++;
            } elseif ($src === 'duty:chief' && function_exists('crrg_duty_is')) {
                if (crrg_duty_is('chief', (int) ($pc['uid'] ?? 0))) $bonus++;
            }
        }
        $r = crrg_coc_check($skill, $val, $bonus, $penalty);
        $out[] = $r['text'];
        $rank = function_exists('crrg_coc_level_rank') ? crrg_coc_level_rank($r['level']) : 0;
        $grade = $rank >= 3 ? 'clean' : ($rank >= 2 ? 'cost' : 'fail');    // 常规成功＝干净；勉强＝加代价；失败＝付代价推进
    }
    if (!empty($path['hp'])) { $pc['hp'] = max(1, (int) ($pc['hp'] ?? 1) - (int) $path['hp']); $grade = $grade === 'clean' ? 'cost' : $grade; }

    /* 代价：时间（**只算个人在线钟**，不碰世界钟 ✓）／暴露／痕迹 */
    $cost = (array) ($path['cost'] ?? []);
    $cost = crrg_cost_scale($cost, $grade, $path);
    $out = array_merge($out, function_exists('crrg_task_cost_apply')
        ? crrg_task_cost_apply($s, $pc, $task, $cost)          // P1.9：统一代价层（体力/物品/钱/声望/人情/睡眠…）
        : crrg_cost_apply($s, $pc, $task, $cost, $grade));

    /* 产出：线索 / 结论 / 阶段 / 公共事实（**失败也给一部分**＝失败推进 ✓） */
    $gain = (array) ($path['gain'] ?? []);
    if ($grade === 'fail') $gain = (array) ($path['gain_on_fail'] ?? ['stage' => $path['fail_stage'] ?? '']);
    foreach ((array) ($gain['clues'] ?? []) as $ck) {
        if (function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, $ck)) {
            $d = (array) (crrg_task($task)['clues'][$ck] ?? []);
            $out[] = '〔线索〕' . ($d['title'] ?? $ck) . '　' . (string) ($d['point'] ?? '');
        }
    }
    /* 2026-10-14（自查）：`gain.concl` 白名单里有、**实现没有** ✗ → 补上：
       显式声明"这条解法直接让某条结论成立"（少见但有用：当场读到的东西不靠组合 ✓） */
    foreach ((array) ($gain['concl'] ?? []) as $cid) {
        $flag = 'concl_' . $task . '_' . $cid;
        if (!in_array($flag, (array) $s['flags'], true)) {
            $s['flags'][] = $flag;
            $c = (array) (crrg_task($task)['concl'][$cid] ?? []);
            $out[] = '〔结论〕' . ($c['name'] ?? $cid);
            if (!empty($c['text'])) $out[] = (string) $c['text'];
        }
    }
    if (function_exists('crrg_task_gain_apply')) {
        $out = array_merge($out, crrg_task_gain_apply($s, $pc, $task, $gain, (int) ($pc['uid'] ?? 0)));
    }
    if (!empty($gain['stage']) && function_exists('crrg_task_stage_set')) {
        if (crrg_task_stage_set($s, $pc, $task, $gain['stage'])) $out[] = '（进展：' . ($gain['stage_text'] ?? '任务往前走了一步') . '）';
    }
    if (!empty($gain['fact']) && function_exists('crrg_fact_propose')) {
        $f = (array) $gain['fact'];
        [$w, $msg] = crrg_fact_propose($f[0], $f[1] ?? true,
            ['mode' => $f[2] ?? 'once', 'scope' => $f[3] ?? 'base', 'need' => $f[4] ?? 2], (int) ($pc['uid'] ?? 0));
        if ($w) $out[] = '（这件事记进了基地的账。）';
    }
    foreach ((array) ($path['text'] ?? []) as $x) $out[] = $x;
    if (!empty($path['ending']) && function_exists('crrg_task_finish')) {
        crrg_task_finish($s, $pc, $task, (string) $path['ending']);
    }
    return $out;
}

/** 代价按档位缩放：勉强成功＝×1；失败＝×1.5 或按作者声明 */
function crrg_cost_scale(array $cost, $grade, array $path) {
    if ($grade === 'fail') {
        if (!empty($path['cost_on_fail'])) return (array) $path['cost_on_fail'];
        foreach ($cost as $k => $v) if (is_numeric($v)) $cost[$k] = (int) ceil($v * 1.5);
        $cost['expose'] = (int) ($cost['expose'] ?? 0) + 1;              // 失败至少留个动静 ✓
    }
    return $cost;
}

/** 落账：时间（个人在线钟）／暴露／痕迹旗标／公共事实 */
function crrg_cost_apply(&$s, &$pc, $task, array $cost, $grade) {
    $out = [];
    if (!empty($cost['minutes'])) {
        /* 只推**个人的**时间账（vitals 那套按在线时长 ✓）——绝不碰世界钟 ✗ */
        $mins = (int) $cost['minutes'];
        if (function_exists('crrg_vitals_now')) {
            $v = &$pc['vitals'];
            $v['t'] = crrg_vitals_now() - $mins * 60;                    // 把"个人时间"往前压＝多耗了这么久
        }
        $out[] = sprintf('（耗了约 %d 分钟。）', $mins);
    }
    if (!empty($cost['expose'])) {
        $n = (int) $cost['expose'];
        $s['expose_' . $task] = (int) ($s['expose_' . $task] ?? 0) + $n;
        $out[] = '（有人注意到你了。）';
        if (function_exists('crrg_fact_propose') && (int) $s['expose_' . $task] >= 3) {
            crrg_fact_propose('base.被注意到.' . $task, true, ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], (int) ($pc['uid'] ?? 0));
        }
    }
    if (!empty($cost['flag'])) { $s['flags'][] = (string) $cost['flag']; }
    if (!empty($cost['san']))   { $pc['san'] = max(0, (int) ($pc['san'] ?? 0) - (int) $cost['san']); }
    return $out;
}
