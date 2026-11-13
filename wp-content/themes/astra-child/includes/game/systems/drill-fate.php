<?php
/**
 * 命运模块（2026-09-28）：殉职 / 疯狂 / 轮回
 * 口径（委员长定）：
 *   1. 演习需 2 积分，死亡不返还（演习里不会真死，只"停机"）。
 *   2. 实弹死亡 → 殉职：封卡（只读）、随身物品入遗物箱、写《殉职报告》；新卡**继承 50%**。
 *   3. 理智归零 → 失能：**3 游戏日内**回到基地可行「激进治疗」（2000 元 + 住院 3 游戏日 → 理智回 1D10，留遗症）；
 *      也可**放弃治疗**（永久失能）。
 *   4. 封卡**不永久**：**S 级权限**玩家可调动「轮回」完美复活被封的卡；
 *      调用者降为 **A 级**、各地点**声望 −50%**。经轮回复活者，记为「转生」，获 S 级权限。
 */
if (!defined('ABSPATH')) exit;

/* ================= 状态 ================= */
function crrg_fate(&$pc) {
    if (!isset($pc['fate']) || !is_array($pc['fate'])) {
        $pc['fate'] = ['sealed' => 0, 'reason' => '', 'at' => 0, 'cause' => '', 'revived' => 0, 'legacy' => 0];
    }
    if (!isset($pc['insanity']) || !is_array($pc['insanity'])) {
        $pc['insanity'] = ['insane' => 0, 'at' => 0, 'deadline' => 0];
    }
    return $pc['fate'];
}
function crrg_fate_sealed($pc) { return !empty($pc['fate']['sealed']); }
/** 治疗费按卡级分级（2026-09-29：新手 300 元起步，2000 元等于劝退） */
function crrg_fate_treat_fee($pc) {
    $lv = function_exists('crrg_drill_ctx') && !empty($pc['uid']) ? crrg_drill_ctx($pc['uid'])['lv'] : 0;
    if ($lv <= 3) return 500;      // D／C 级
    if ($lv <= 5) return 1200;     // B 级
    return 2000;                   // A 级及以上
}
function crrg_fate_insane($pc) { return !empty($pc['insanity']['insane']); }
function crrg_game_now() { return function_exists('crrg_world_clock') ? crrg_world_clock()['ts'] : time(); }
/** 3 游戏日的窗口还剩多少（游戏小时） */
function crrg_fate_insanity_window_left(&$pc) {
    if (empty($pc['insanity']['deadline'])) return 0;
    return max(0, ((int) $pc['insanity']['deadline'] - crrg_game_now()) / 3600.0);
}
/** S 级权限（含"转生"过的玩家） */
function crrg_fate_is_s($uid) { return (bool) get_user_meta($uid, 'crrg_drill_s', true); }
function crrg_fate_grant_s($uid, $why = '') {
    update_user_meta($uid, 'crrg_drill_s', 1);
    update_user_meta($uid, 'crrg_drill_s_at', current_time('mysql'));
    update_user_meta($uid, 'crrg_drill_s_why', $why);
}
function crrg_fate_drop_s($uid) { delete_user_meta($uid, 'crrg_drill_s'); }

/* ================= 殉职 ================= */
/** 把随身物品挪进遗物箱（个人仓库），返回条数 */
function crrg_fate_stash_all(&$pc) {
    $n = 0;
    foreach ((array) crrg_drill_bag_counts($pc) as $id => $c) {
        if ($c <= 0) continue;
        crrg_drill_bag_del_n($pc, $id, $c);
        crrg_drill_stash_add($pc, $id, $c);
        $n += $c;
    }
    return $n;
}
/** 殉职结算 */
function crrg_fate_die(&$pc, $uid, $cause = '战斗', $room = '') {
    $f = crrg_fate($pc);
    if (!empty($f['sealed'])) return ['（这张卡已经封存了。）'];
    $rooms = function_exists('crrg_drill_rooms') ? crrg_drill_rooms() : [];
    $where = $rooms[$room]['t'] ?? ($room ?: '未知地点');
    $moved = crrg_fate_stash_all($pc);
    $f['sealed'] = 1; $f['reason'] = '殉职'; $f['at'] = crrg_game_now(); $f['cause'] = $cause;
    $f['legacy'] = 1;                                   // 供新卡继承
    $pc['fate'] = $f;
    $pc['hp'] = 0;
    /* 遗留给下一张卡：50% 积分、50% 声望 */
    update_user_meta($uid, 'crrg_fate_legacy', [
        'credits' => (int) floor((int) $pc['credits'] * 0.5),
        'rep'     => array_map(fn($v) => (int) round($v * 0.5), (array) ($pc['rep'] ?? [])),
        'stash'   => (array) ($pc['stash'] ?? []),      // 遗物箱：继任者开局就能取
        'from'    => $pc['name'] ?? '', 'cause' => $cause,
    ]);
    crrg_drill_pc_save($uid, $pc);
    /* 写殉职报告 */
    if (function_exists('wp_insert_post')) {
        $body = "本报告由系统自动生成。\n\n"
              . "殉职人员：" . ($pc['name'] ?? '未登记') . "（账号 #" . $uid . "）\n"
              . "时间：" . (function_exists('crrg_wdate') ? crrg_wdate('Y-m-d H:i', crrg_game_now()) : date('Y-m-d H:i')) . "\n"
              . "地点：" . $where . "\n"
              . "死因：" . $cause . "\n"
              . "随身物品：" . $moved . " 件，已转入遗物箱（个人仓库），可由继任者领取。\n"
              . "继承安排：继任者继承 50% 积分与 50% 当地声望；本卡封存，可由 S 级权限者调动「轮回」复活。\n";
        $pid = wp_insert_post(['post_title' => '【殉职报告】' . ($pc['name'] ?? ('#' . $uid)),
            'post_content' => $body, 'post_status' => 'private', 'post_type' => 'post', 'post_author' => $uid], true);
        if (!is_wp_error($pid)) {
            update_post_meta($pid, 'crrg_report_type', 'events');
            update_post_meta($pid, 'crrg_report_type_name', '事件');
            update_post_meta($pid, 'crrg_access_level', 'operative');
            update_post_meta($pid, 'crrg_record_status', 'death_report');
            $f['report'] = $pid; $pc['fate'] = $f; crrg_drill_pc_save($uid, $pc);
        }
    }
    return ['——' , '【殉职】' . ($pc['name'] ?? '你') . '没能撑住。',
            sprintf('（随身 %d 件物品已转入遗物箱；已生成《殉职报告》。）', $moved),
            '（这张卡已封存。可用另一个账号或新建角色继续；继任者将继承 50% 积分与 50% 声望。）'];
}
/** 新卡建立时应用继承 */
function crrg_fate_apply_legacy(&$pc, $uid) {
    $lg = get_user_meta($uid, 'crrg_fate_legacy', true);
    if (!is_array($lg)) return [];
    $out = ['（继任安排：上一张卡的遗产已并入本卡。）'];
    $add = (int) ($lg['credits'] ?? 0);
    if ($add > 0) { $pc['credits'] = (int) ($pc['credits'] ?? 0) + $add; $out[] = sprintf('　积分 +%d（前任积分的一半）', $add); }
    foreach ((array) ($lg['rep'] ?? []) as $loc => $v) {
        if (!function_exists('crrg_rep_add')) break;
        $pc['rep'][$loc] = (int) $v;
        $out[] = sprintf('　%s声望继承 %+d', crrg_rep_places()[$loc]['short'] ?? $loc, (int) $v);
    }
    $st = (array) ($lg['stash'] ?? []);
    if ($st) {
        foreach ($st as $id => $n) if ($n > 0) crrg_drill_stash_add($pc, $id, (int) $n);
        $out[] = sprintf('　遗物箱 %d 种物品已入库（宿舍储物柜）', count($st));
    }
    delete_user_meta($uid, 'crrg_fate_legacy');
    return $out;
}

/* ================= 疯狂 ================= */
/** 理智归零：进入失能，给 3 游戏日窗口 */
function crrg_fate_go_insane(&$pc, $uid, $cause = '理智归零') {
    $m = $pc['insanity'] ?? [];
    if (!empty($m['insane'])) return [];
    $now = crrg_game_now();
    $pc['insanity'] = ['insane' => 1, 'at' => $now, 'deadline' => $now + 3 * 86400, 'cause' => $cause];
    return ['——', '【失能】你的理智见了底。剩下的只有一段含糊的记忆。',
            '（3 游戏日内（现实约 90 分钟）回到基地医疗室，可以接受激进治疗；也可以选择放弃治疗。）'];
}
/** 激进治疗（基地医疗室）：花钱＋住院，理智回到 1D10，留后遗症 */
function crrg_fate_treat(&$pc, $uid) {
    if (!crrg_fate_insane($pc)) return ['你没有需要治疗的地方。'];
    $left = crrg_fate_insanity_window_left($pc);
    if ($left <= 0) return ['（窗口已经过了——只能走「放弃治疗」。）'];
    $fee = crrg_fate_treat_fee($pc);
    if ((int) $pc['money'] < $fee) return [sprintf('激进治疗需 %d 元（你在 %d 元）。', $fee, (int) $pc['money'])];
    $pc['money'] = (int) $pc['money'] - crrg_fate_treat_fee($pc);
    $d = crrg_coc_derive($pc['attrs']);
    $gain = crrg_coc_dice('1d10');
    $pc['san'] = max(1, min($d['san_max'], $gain));
    /* 后遗症：随机一项 */
    $seq = [['手抖', '技能', '射击'], ['噩梦', '技能', '意志'], ['畏光', '属性', '体质']];
    $pick = $seq[array_rand($seq)];
    if ($pick[1] === '属性') {
        $pc['attrs'][$pick[2]] = max(1, (int) ($pc['attrs'][$pick[2]] ?? 50) - 5);
        $seqText = $pick[2] . ' −5（永久）';
    } else {
        $pc['skills'][$pick[2]] = max(1, (int) ($pc['skills'][$pick[2]] ?? 25) - 5);
        $seqText = $pick[2] . '技能 −5（永久）';
    }
    $pc['insanity'] = ['insane' => 0, 'at' => 0, 'deadline' => 0];
    crrg_drill_pc_save($uid, $pc);
    return ['——', '【激进治疗】你在医疗室的床上躺了三天。醒来时，名字还记得，但有些东西不对了。',
            sprintf('（理智回到 %d；后遗症：%s。）', (int) $pc['san'], $seqText)];
}
/** 放弃治疗：永久失能（封卡，原卡转为基地里的失能者） */
function crrg_fate_give_up(&$pc, $uid) {
    if (!crrg_fate_insane($pc)) return ['你没有需要放弃的治疗。'];
    $f = crrg_fate($pc);
    $moved = crrg_fate_stash_all($pc);
    $f['sealed'] = 1; $f['reason'] = '永久失能'; $f['at'] = crrg_game_now(); $f['legacy'] = 1;
    $pc['fate'] = $f;
    $pc['insanity'] = ['insane' => 1, 'at' => crrg_game_now(), 'deadline' => 0, 'cause' => '永久失能'];
    update_user_meta($uid, 'crrg_fate_legacy', ['credits' => (int) floor((int) $pc['credits'] * 0.5),
        'rep' => array_map(fn($v) => (int) round($v * 0.5), (array) ($pc['rep'] ?? [])),
        'stash' => (array) ($pc['stash'] ?? []), 'from' => $pc['name'] ?? '', 'cause' => '永久失能']);
    crrg_drill_pc_save($uid, $pc);
    return ['——', '【永久失能】笔迹的最后一页停在半句上。',
            sprintf('（随身 %d 件物品已转入遗物箱；本卡封存。继任者继承 50%% 积分与声望。）', $moved)];
}
/** 窗口过期自动失能（每次行动时检查） */
function crrg_fate_check_expire(&$pc, $uid) {
    if (!crrg_fate_insane($pc) || crrg_fate_sealed($pc)) return [];
    if (!empty($pc['insanity']['deadline']) && crrg_game_now() > (int) $pc['insanity']['deadline']) {
        return array_merge(['——', '【窗口过期】没人来找你。'], crrg_fate_give_up($pc, $uid));
    }
    return [];
}

/* ================= 轮回（S 级） ================= */
/** 列出被封存的卡（本账号 + 全站的，供 S 级调度） */
function crrg_fate_sealed_list() {
    $out = [];
    foreach (get_users(['fields' => ['ID']]) as $u) {
        $p = get_user_meta($u->ID, 'crrg_drill_pc', true);
        if (is_array($p) && !empty($p['fate']['sealed'])) {
            $out[(int) $u->ID] = ['name' => $p['name'] ?? ('#' . $u->ID), 'reason' => $p['fate']['reason'] ?? '',
                'at' => $p['fate']['at'] ?? 0, 'cause' => $p['fate']['cause'] ?? ''];
        }
    }
    return $out;
}
/** 轮回：完美复活一张封存的卡；调用者降为 A 级 + 声望 −50% */
function crrg_fate_reincarnate(&$invoker_pc, $invoker_uid, $target_uid) {
    if (!crrg_fate_is_s($invoker_uid)) return ['只有 S 级权限能调动「轮回」。'];
    $tp = get_user_meta($target_uid, 'crrg_drill_pc', true);
    if (!is_array($tp) || empty($tp['fate']['sealed'])) return ['那张卡没有被封存。'];
    /* 完美复活：封存解除、生命与理智回满、失能清除、物品保留、记「转生」 */
    $tp['fate']['sealed'] = 0; $tp['fate']['reason'] = ''; $tp['fate']['revived'] = (int) ($tp['fate']['revived'] ?? 0) + 1;
    $tp['insanity'] = ['insane' => 0, 'at' => 0, 'deadline' => 0];
    $d = crrg_coc_derive($tp['attrs'] ?? []);
    $tp['hp'] = min((int) ($tp['hp'] ?? 0) ? $d['hp_max'] : $d['hp_max'], $d['hp_max']);
    $tp['hp'] = $d['hp_max']; $tp['san'] = $d['san_max'];     // 完美复活：两项回满
    update_user_meta($target_uid, 'crrg_drill_pc', $tp);
    /* 被复活者获得 S 级（"转生之后的玩家"） */
    crrg_fate_grant_s($target_uid, '轮回转生 #' . $invoker_uid);
    /* 调用者代价：降为 A 级 + 各地点声望 −50% */
    crrg_fate_drop_s($invoker_uid);
    $rep = [];
    if ((int) $invoker_uid === (int) $target_uid) {
        /* 自己复活自己：价钱从"复活后的这张卡"上扣，避免覆盖复活结果 */
        foreach ((array) ($tp['rep'] ?? []) as $loc => $v) {
            $tp['rep'][$loc] = (int) floor(((int) $v) / 2);
            $rep[] = sprintf('%s %d', crrg_rep_places()[$loc]['short'] ?? $loc, (int) $tp['rep'][$loc]);
        }
        update_user_meta($target_uid, 'crrg_drill_pc', $tp);
    } else {
        foreach ((array) ($invoker_pc['rep'] ?? []) as $loc => $v) {
            $invoker_pc['rep'][$loc] = (int) floor(((int) $v) / 2);
            $rep[] = sprintf('%s %d', crrg_rep_places()[$loc]['short'] ?? $loc, (int) $invoker_pc['rep'][$loc]);
        }
        crrg_drill_pc_save($invoker_uid, $invoker_pc);
    }
    /* 记录 */
    if (function_exists('wp_insert_post')) {
        $body = "【轮回记录】\n\n调用者：#" . $invoker_uid . "（已降为 A 级，声望减半）\n"
              . "被复活者：#" . $target_uid . "（" . ($tp['name'] ?? '') . "，已获 S 级权限）\n"
              . "时间：" . (function_exists('crrg_wdate') ? crrg_wdate('Y-m-d H:i', crrg_game_now()) : date('Y-m-d H:i')) . "\n"
              . "声望变更：" . (implode('｜', $rep) ?: '（无）') . "\n";
        $pid = wp_insert_post(['post_title' => '【轮回记录】复活 ' . ($tp['name'] ?? ('#' . $target_uid)),
            'post_content' => $body, 'post_status' => 'private', 'post_type' => 'post', 'post_author' => $invoker_uid], true);
        if (!is_wp_error($pid)) {
            update_post_meta($pid, 'crrg_report_type_name', '事件');
            update_post_meta($pid, 'crrg_access_level', 'operative');
            update_post_meta($pid, 'crrg_record_status', 'reincarnation');
        }
    }
    return ['——', '【轮回】牌面翻过来，名字重新写上去。',
            sprintf('（%s 已复活，生命与理智回满，并获 S 级权限；你的权限降为 A 级，各地点声望减半。）', $tp['name'] ?? ('#' . $target_uid)),
            '（声望现为：' . (implode('｜', $rep) ?: '无') . '）'];
}
