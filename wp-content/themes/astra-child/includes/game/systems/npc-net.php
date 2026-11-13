<?php
/**
 * CRRG 演习 · NPC 关系网（systems/npc-net.php，R9-②，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："谁跟谁一伙、谁嘴碎（影响告密与对话选项）"
 * 三样：
 *   · **亲疏** bond(a,b)：-3..+3（一伙的、不对付的、点头之交）
 *   · **嘴碎** gossip(npc)：0..3（越高越会把"你问过"传出去 ✓ 告密）
 *   · **打听**：向 A 打听 B 的事 → A 与 B 越熟给的越多；A 越嘴碎，你这一问越容易被传出去（→ 暴露）
 */
if (!defined('ABSPATH')) exit;

/** 关系表（一对只写一次，查两侧都认 ✓） */
function crrg_npc_net() {
    return [
        ['康明远', '韩雪', 2, '一起值过夜班的交情'],
        ['康明远', '周德海', 1, '排班与领料，天天打交道'],
        ['周德海', '魏长庚', 2, '领料搭子'],
        ['周德海', '白露', 1, '食堂那点事互相照应'],
        ['白大夫', '白露', 2, '同姓，认了姐妹'],
        ['白大夫', '康明远', 1, '体检单上的老熟人'],
        ['陈连生', '白露', 2, '一个灶台一个窗口'],
        ['陈连生', '周德海', -1, '为一份肉吵过'],
        ['林正则', '何若风', 3, '档案室的老搭档'],
        ['江晓', '韩雪', 2, '技术上的师徒'],
        ['王守一', '魏长庚', 1, '工具互相借'],
        ['王守一', '陈连生', -1, '修灶台的事还没翻篇'],
    ];
}
/** 嘴碎度 0..3（越高越爱说） */
function crrg_npc_gossip_tab() {
    return ['白露' => 3, '周德海' => 2, '王守一' => 2, '陈连生' => 2, '江晓' => 1,
            '康明远' => 0, '韩雪' => 1, '林正则' => 1, '何若风' => 0, '白大夫' => 0, '魏长庚' => 1];
}
function crrg_npc_gossip($npc) { $t = crrg_npc_gossip_tab(); return (int) ($t[(string) $npc] ?? 1); }

/** 亲疏（对称查询 ✓） */
function crrg_npc_bond($a, $b) {
    foreach (crrg_npc_net() as $row) {
        if (($row[0] === $a && $row[1] === $b) || ($row[0] === $b && $row[1] === $a)) return (int) $row[2];
    }
    return 0;
}
/** 这个人和谁最亲（"一伙的"） */
function crrg_npc_close_to($a, $n = 3) {
    $out = [];
    foreach (crrg_npc_net() as $row) {
        if ($row[2] <= 0) continue;
        if ($row[0] === $a) $out[$row[1]] = (int) $row[2];
        elseif ($row[1] === $a) $out[$row[0]] = (int) $row[2];
    }
    arsort($out);
    return array_slice($out, 0, $n, true);
}

/**
 * 向 $ask 打听 $about：返回 [给的信息程度 0..3, 供玩家看的行]
 *   · 亲疏越高 → 给得越多 ✓
 *   · 嘴碎越高 → 你这一问越可能被传出去（暴露 +1 ✓）
 */
function crrg_npc_ask_about(&$s, &$pc, $ask, $about, $task = '') {
    $bond = crrg_npc_bond($ask, $about);
    $goss = crrg_npc_gossip($ask);
    $lv = $bond >= 3 ? 3 : ($bond >= 1 ? 2 : ($bond <= -1 ? 1 : 1));
    $out = [];
    if ($bond <= -1) {
        $out[] = sprintf('%s 听见这个名字就把话岔开了。', $ask);
        $lv = 1;
    } elseif ($bond >= 2) {
        $out[] = sprintf('%s 想了想，愿意多说两句。', $ask);
    } else {
        $out[] = sprintf('%s 只给了个大概。', $ask);
    }
    /* 告密：嘴碎 + 关系差 → 更容易把你问的事传出去 ✓ */
    if ($goss >= 2 && mt_rand(1, 100) <= 30 * $goss) {
        $out[] = sprintf('（%s 转头把这事跟人说了。）', $ask);
        if ($task !== '') $s['expose_' . $task] = (int) ($s['expose_' . $task] ?? 0) + 1;
        $s['flags'][] = 'gossiped_' . preg_replace('/[^\x{4e00}-\x{9fa5}a-z0-9_]/u', '_', $ask);
        if (function_exists('crrg_evt_emit')) {
            crrg_evt_emit('npc.talked', ['npc' => $ask, 'about' => $about, 'kind' => 'gossip', 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'npc.net']);
        }
    }
    return [$lv, $out];
}

/** 一行关系速览（面板/作者工具用） */
function crrg_npc_net_lines($npc = '') {
    if ($npc !== '') {
        $out = [sprintf('〔%s 的关系〕嘴碎 %d／3', $npc, crrg_npc_gossip($npc))];
        foreach (crrg_npc_close_to($npc) as $n => $b) $out[] = sprintf('　和 %s：%+d', $n, $b);
        return $out;
    }
    $out = ['〔关系网〕'];
    foreach (crrg_npc_net() as $r) $out[] = sprintf('　%s — %s：%+d（%s）', $r[0], $r[1], (int) $r[2], $r[3]);
    return $out;
}
