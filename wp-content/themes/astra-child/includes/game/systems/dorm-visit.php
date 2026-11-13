<?php
/**
 * 宿舍·独立实例（2026-10-01 委员长提出）
 * ============================================================
 * 问题：宿舍原来是**普通房间**（`b1_dorm` 宿舍区走廊 ✓）→ **别人进宿舍会和你同处** ✗
 *
 * 现在拆成三种"在里面"：
 *   ① **自己的宿舍**（私人 ✓ 别人不在场 ✓）——`进宿舍` / 回自己宿舍
 *   ② **拜访玩家的宿舍**（`拜访 <玩家名>` ✓）——看他的家具／装修 ✓
 *   ③ **拜访 NPC 的宿舍**（`拜访 <NPC名>` ✓）——**按作息**：该 NPC 处**休息时段**才在 ✓，
 *      否则明确告诉你"他不在宿舍（在 X）"✓（依据名册 `sched` ✓：深夜 → `b2f_barracks` 等）
 *
 * 落地方式（不新建房间 ✗，用"位置标记" ✓）：
 *   · 状态里记 `$s['at_dorm']` ＝ 'self' ｜ '<uid>' ｜ 'npc:<id>' ✓
 *   · 在宿舍里时：**在场列表只显示该宿舍的人**（自己的 → 谁也不显示 ✓）
 *     → 别人进他们自己的宿舍，不会跟你同处 ✓
 *   · `出去` / `去 <别处>` 都会**自动离开宿舍** ✓（不会卡住 ✓）
 */
if (!defined('ABSPATH')) exit;

/** 营房/宿舍类房间（NPC 这些房间 = 他在宿舍 ✓） */
function crrg_dorm_rooms() { return ['b2f_barracks', 'b1_dorm', 'b1_room', 'b1_cabin']; }

/** 当前是否在"某个宿舍里" */
function crrg_dorm_where(&$s) { return (string) ($s['at_dorm'] ?? ''); }

/** 进（自己的）宿舍 */
function crrg_dorm_enter_self(&$s, &$pc) {
    $s['at_dorm'] = 'self';
    return ['你刷卡进了自己的宿舍。门在身后合上——**这里只有你** ✓',
            '（家具／装修用「宿舍」窗口调；要出去就说「出去」。）'];
}
/** 拜访：玩家或 NPC ✓ */
function crrg_dorm_visit(&$s, &$pc, $who) {
    $who = trim((string) $who);
    if ($who === '') return ['（拜访谁？「拜访 康明远」／「拜访 <玩家名>」。）'];
    /* 先当玩家名找 ✓ */
    foreach (get_users(['fields' => ['ID', 'display_name']]) as $u) {
        if ((int) $u->ID === (int) ($pc['__uid'] ?? 0)) continue;
        $nm = (string) $u->display_name;
        if ($who === $nm || (mb_strlen($who) >= 2 && mb_strpos($nm, $who) !== false)) {
            $s['at_dorm'] = (string) $u->ID;
            $their = crrg_drill_pc((int) $u->ID);
            $lines = function_exists('crrg_drill_dorm_lines') ? (array) crrg_drill_dorm_lines($their, $nm) : [];
            return array_merge(['你敲了敲 ' . $nm . ' 的门，里面没人应——他不在。你自己看了看：'],
                               $lines ?: ['（他的宿舍很素：床、桌、柜，跟配发的一样。）'],
                               ['（要出去就说「出去」。）']);
        }
    }
    /* 再当 NPC 找 ✓ */
    if (function_exists('crrg_npc_roster')) {
        foreach ((array) crrg_npc_roster() as $id => $d) {
            $nm = (string) ($d['n'] ?? $id);
            if ($who !== $nm && (mb_strlen($who) < 2 || mb_strpos($nm, $who) === false)) continue;
            $phase = function_exists('crrg_npc_phase') ? crrg_npc_phase() : '';
            $room  = (string) ($d['sched'][$phase] ?? '');
            $inDorm = in_array($room, crrg_dorm_rooms(), true);
            if ($inDorm) {
                $s['at_dorm'] = 'npc:' . $id;
                return [sprintf('你敲了敲 %s 的门。里面应了一声——**他在**（这个点他确实在宿舍休息 ✓）。', $nm),
                        '（他不方便让你久留；说「出去」就走。）'];
            }
            $rn = function_exists('crrg_npc_room_name') ? crrg_npc_room_name($room) : $room;
            return [sprintf('你敲了敲门，没人应。%s 这个点不在宿舍——**他在「%s」**。', $nm, $rn !== '' ? $rn : '别处'),
                    '（想找他，按作息去那里；或等他休息的时段再来。）'];
        }
    }
    return ['（没找到这个人——玩家名要写对，NPC 只在名册里的那几位。）'];
}
/** 离开宿舍 */
function crrg_dorm_leave(&$s) {
    if (crrg_dorm_where($s) === '') return ['（你本来就不在宿舍里。）'];
    unset($s['at_dorm']);
    return ['你带上宿舍门，回到了宿舍区走廊。'];
}
/** 宿舍里的"在场"：只有该宿舍的主人（自己的 → 谁也没有 ✓） */
function crrg_dorm_presence(&$s, $uid, $room) {
    $w = crrg_dorm_where($s);
    if ($w === '') return null;                       // 不在宿舍 → 照常算 ✓
    if ($w === 'self') return [];
    if (strpos($w, 'npc:') === 0) return [];          // NPC 在宿舍：不列玩家 ✓
    $o = (int) $w;
    if ($o <= 0) return [];
    $u = get_userdata($o);
    if (!$u) return [];
    return [['id' => $o, 'name' => (string) $u->display_name,
             'role' => (function_exists('crrg_drill_ctx') ? (crrg_drill_ctx($o)['rank_name'] ?? '') : ''),
             'label' => (string) $u->display_name . '（宿舍主人）']];
}
/** 宿舍里的标题 */
function crrg_dorm_title(&$s) {
    $w = crrg_dorm_where($s);
    if ($w === '') return '';
    if ($w === 'self') return '你的宿舍';
    if (strpos($w, 'npc:') === 0) {
        $id = substr($w, 4);
        $r = function_exists('crrg_npc_roster') ? (array) crrg_npc_roster() : [];
        return (string) ($r[$id]['n'] ?? $id) . ' 的宿舍';
    }
    $u = get_userdata((int) $w);
    return ($u ? (string) $u->display_name : ('#' . $w)) . ' 的宿舍';
}
