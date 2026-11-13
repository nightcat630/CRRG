<?php
/**
 * CRRG 演习系统 · 玩家互动模块（social）v1  2026-10-01
 * ============================================================
 * 设计（先看"该有什么"，再动手 ✓）：
 *   已有：同地点在场列表 ✓、同处可见的发言/动作 ✓、组队与分红 ✓、站内消息 ✓
 *   缺的：**在场的人点不动** ✗、没有"人对人"的动作 ✗
 *
 * 本模块提供（全部**异步安全** ✓：双方不必同时在线 ✓）：
 *   ① 打个招呼      —— 同房间的人会在日志里看到（社交可见 ✓）
 *   ② 递个东西      —— 赠予，收件人从窗口领取（不会凭空消失 ✓）
 *   ③ 邀请组队      —— 生成"待接受邀请"，对方接受即入队 ✓（组队系统已存在 ✓）
 *   ④ 留一句话      —— 进对方的"互动信箱"（不刷屏 ✓，集中看 ✓）
 *   ⑤ 看对方一眼    —— 公开信息：职务/卡级/所在（不看背包 ✓ 隐私 ✓）
 * 边界（刻意不做 ✗）：偷窃/强夺/骚扰刷屏 —— 互动一律**自愿**、**留痕**（信箱可查 ✓）
 *
 * 接入方式（模块化 ✓）：
 *   · 动作：核心动作分发 `default:` 里会调 `crrg_soc_do($k,$id,…)` ✓（核心不认识本模块 ✓）
 *   · 界面：左栏"同在此处"的人 → 点开 `player:<uid>` 窗口 ✓
 *   · 存储：写在各角色自己的 `pc['social']`（信箱/礼物/邀请 ✓）——不动全局表 ✓
 */
if (!defined('ABSPATH')) exit;

/** 存储位置（角色数据里的一个键 ✓） */
function crrg_soc_bucket(&$pc) {
    if (!isset($pc['social']) || !is_array($pc['social'])) $pc['social'] = ['inbox' => [], 'gifts' => [], 'invites' => []];
    foreach (['inbox', 'gifts', 'invites'] as $k) if (!isset($pc['social'][$k]) || !is_array($pc['social'][$k])) $pc['social'][$k] = [];
    return $pc['social'];
}
/** 对方显示名 */
function crrg_soc_name($uid) {
    $u = get_userdata((int) $uid);
    return $u ? (string) $u->display_name : ('#' . (int) $uid);
}
/** 写一条到对方信箱（**留痕** ✓） */
function crrg_soc_push($toUid, $kind, $text, $fromUid) {
    $toUid = (int) $toUid; $fromUid = (int) $fromUid;
    if ($toUid <= 0 || $toUid === $fromUid) return false;
    if (!function_exists('crrg_drill_pc') || !function_exists('crrg_drill_pc_save')) return false;
    $pc = crrg_drill_pc($toUid);
    $b  = crrg_soc_bucket($pc);
    $row = ['k' => (string) $kind, 'from' => $fromUid, 'from_name' => crrg_soc_name($fromUid),
            'text' => (string) $text, 't' => time()];
    if ($kind === 'gift')      $pc['social']['gifts'][]   = $row;
    elseif ($kind === 'invite') $pc['social']['invites'][] = $row;
    else                        $pc['social']['inbox'][]   = $row;
    /* 只留最近 30 条（防无限增长 ✓） */
    foreach (['inbox' => 30, 'gifts' => 30, 'invites' => 12] as $kk => $max) {
        if (count($pc['social'][$kk]) > $max) $pc['social'][$kk] = array_slice($pc['social'][$kk], -$max);
    }
    crrg_drill_pc_save($toUid, $pc);
    return true;
}
/** 取信箱（给窗口用 ✓） */
function crrg_soc_box(&$pc) { return crrg_soc_bucket($pc); }
/** 未读计数（左栏/帮助提示用 ✓） */
function crrg_soc_pending(&$pc) { $b = crrg_soc_bucket($pc); return count($b['inbox']) + count($b['gifts']) + count($b['invites']); }

/** 动作分发（核心只调这一个 ✓ 模块自己认领 k ✓） */
function crrg_soc_do($k, $id, &$s, &$pc, $uid, $ctx) {
    $k = (string) $k; $id = (string) $id;
    /* 打招呼：同房间的人都能看到 ✓（不写信箱） */
    if ($k === 'soc_wave') {
        $who = crrg_soc_name($id);
        if (!crrg_soc_same_room($uid, $id, $s)) return ['（他不在你眼前——先看看「同在此处」。）'];
        if (function_exists('crrg_drill_feed_add')) crrg_drill_feed_add($s['room'] ?? '', crrg_soc_name($uid), sprintf('%s 朝 %s 扬了扬下巴，算是打过招呼。', crrg_soc_name($uid), $who), 'act');
        return ['你朝 ' . $who . ' 扬了扬下巴。（同处的人看得见。）'];
    }
    /* 递东西：先在自己背包里找，再放进对方礼物盒 ✓ */
    if ($k === 'soc_gift') {
        /* id 形如「物品名|目标uid」（窗口按钮带上的 ✓）—— **先解析** ✓
           （原来先判 $s['soc_target'] ✗ → 永远说"先选人" ✗） */
        $arg = $id; $target = (int) ($s['soc_target'] ?? 0);
        if (mb_strpos($arg, '|') !== false) { [$arg, $t2] = explode('|', $arg, 2); $target = (int) $t2; }
        if ($arg === '') return ['（要递什么？例：「递 压缩干粮」。）'];
        if ($target <= 0) return ['（先从"同在此处"里选人。）'];
        if (!crrg_soc_same_room($uid, $target, $s)) return ['（他不在你眼前——递东西得当面。）'];
        $idItem = '';
        foreach ((array) ($pc['items'] ?? []) as $it) {
            $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($it) : $it;
            if ($nm === $arg || $it === $arg || mb_strpos($nm, $arg) !== false) { $idItem = $it; break; }
        }
        if ($idItem === '') return ['（你身上没有「' . $arg . '」。）'];
        $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($idItem) : $idItem;
        crrg_drill_bag_del_n($pc, $idItem, 1);                       // 先出你的包 ✓
        crrg_soc_push($target, 'gift', $nm . '|' . $idItem, $uid); // 再放进对方礼物盒（含物品键 ✓）
        if (function_exists('crrg_drill_feed_add')) crrg_drill_feed_add($s['room'] ?? '', crrg_soc_name($uid), sprintf('%s 把一件东西递给了 %s。', crrg_soc_name($uid), crrg_soc_name($target)), 'act');
        return ['你把「' . $nm . '」递给了 ' . crrg_soc_name($target) . '。（他打开窗口就能收。）'];
    }
    /* 邀请组队 */
    if ($k === 'soc_invite') {
        $target = (int) $id;
        if ($target <= 0) return ['（先选人。）'];
        /* 2026-10-01：邀请人还没队伍 → **先替他建一个** ✓（否则对方接受时会"没有这个队伍" ✗） */
        $msg = '';
        if (function_exists('crrg_drill_team_of') && function_exists('crrg_drill_team_create')) {
            if (!crrg_drill_team_of($uid)) {
                crrg_drill_team_create((int) $uid, crrg_soc_name($uid) . '的小队');   // 签名是 ($uid,$name) ✓
                $msg = '（顺手替你建了「' . crrg_soc_name($uid) . '的小队」，你是队长。）';
            }
        }
        crrg_soc_push($target, 'invite', '组队邀请', $uid);
        return array_values(array_filter(['已向 ' . crrg_soc_name($target) . ' 发出组队邀请。（他在互动窗口里接受。）', $msg]));
    }
    /* 留一句话（可选）：从窗口的输入走「留话 <uid>|<内容>」 */
    if ($k === 'soc_note') {
        if (mb_strpos($id, '|') === false) return ['（格式：留话 <uid>|<内容>。）'];
        [$t, $txt] = explode('|', $id, 2);
        $txt = trim(mb_substr($txt, 0, 120));                     // 限长，防刷屏 ✓
        if ($txt === '') return ['（没写内容。）'];
        crrg_soc_push((int) $t, 'note', $txt, $uid);
        return ['已把话留给 ' . crrg_soc_name((int) $t) . '。（他下次看互动窗口会见到。）'];
    }
    /* 千年杀（2026-10-01 委员长点的）—— 演习里的老梗 ✓
       规则：同房间 ✓ ＋ **冷却 10 分钟/人** ✓（防刷屏 ✗）；**不扣任何属性** ✗（只留痕与可见文字 ✓） */
    if ($k === 'soc_kancho') {
        $target = (int) $id;
        if ($target <= 0) return ['（先选人。）'];
        if (!crrg_soc_same_room($uid, $target, $s)) return ['（得当面——他不在你眼前。）'];
        $cd = (int) ($pc['social_cd']['kancho'][$target] ?? 0);
        if ($cd > time()) return [sprintf('（他刚被戳过——缓一缓，%d 秒后再来。）', $cd - time())];
        $pc['social_cd']['kancho'][$target] = time() + 600;              // 10 分钟
        if (function_exists('crrg_drill_feed_add')) {
            crrg_drill_feed_add($s['room'] ?? '', crrg_soc_name($uid),
                sprintf('%s 从背后并起两指，朝 %s 戳了过去——演习里管这叫「千年杀」。',
                        crrg_soc_name($uid), crrg_soc_name($target)), 'act');
        }
        crrg_soc_push($target, 'note', '有人在你背后用了「千年杀」。（记录在案，是谁自己看。）', $uid);
        return ['你并起两指，从背后戳了过去。',
                '对方原地绷直，喉咙里挤出一声不像人的动静——整条走廊都听见了。',
                '（同处的人都看见了；他回头那一眼，你最好先跑。）'];
    }
    /* 领取礼物 */
    if ($k === 'soc_take') {
        $idx = (int) $id;
        $b = crrg_soc_bucket($pc);
        if (!isset($b['gifts'][$idx])) return ['（这个礼物已经不在了。）'];
        $g = $b['gifts'][$idx];
        [$nm, $key] = array_pad(explode('|', (string) ($g['text'] ?? ''), 2), 2, '');
        unset($pc['social']['gifts'][$idx]);
        $pc['social']['gifts'] = array_values($pc['social']['gifts']);
        if ($key !== '') crrg_drill_bag_add($pc, $key, 1);
        return ['收下了 ' . crrg_soc_name((int) ($g['from'] ?? 0)) . ' 递来的「' . $nm . '」。'];
    }
    /* 接受组队邀请 */
    if ($k === 'soc_accept') {
        $idx = (int) $id;
        $b = crrg_soc_bucket($pc);
        if (!isset($b['invites'][$idx])) return ['（这个邀请已经失效。）'];
        $g = $b['invites'][$idx];
        unset($pc['social']['invites'][$idx]);
        $pc['social']['invites'] = array_values($pc['social']['invites']);
        $f = (int) ($g['from'] ?? 0);
        /* 签名是 ($uid, **队ID**) ✓ —— 原来传的是人名 ✗ → 永远"没有这个队伍" ✗ */
        if (function_exists('crrg_drill_team_join') && function_exists('crrg_drill_team_of')) {
            $t = crrg_drill_team_of($f);
            if (!$t) return ['（' . crrg_soc_name($f) . ' 现在没有队伍了——让他重新邀请一次。）'];
            $r = crrg_drill_team_join((int) $uid, (string) $t['id']);
            return array_merge(['接受 ' . crrg_soc_name($f) . ' 的组队邀请。'], (array) $r);
        }
        return ['接受 ' . crrg_soc_name($f) . ' 的邀请。（组队系统未加载。）'];
    }
    /* 清空信箱（留痕→归档：直接清掉已读 ✓） */
    if ($k === 'soc_clear') {
        $what = (string) $id;
        if (in_array($what, ['inbox', 'gifts', 'invites'], true)) { $pc['social'][$what] = []; return ['（已清空那一栏。）']; }
        $pc['social']['inbox'] = [];
        return ['（已清空留言。）'];
    }
    return null;    // 不是本模块的动作 ✓
}
/** 是否同一房间（互动前置 ✓） */
function crrg_soc_same_room($uid, $other, &$s) {
    $room = (string) ($s['room'] ?? '');
    if ($room === '') return true;
    $st = get_user_meta((int) $other, CRRG_DRILL_META, true);
    return is_array($st) && (string) ($st['room'] ?? '') === $room;
}
