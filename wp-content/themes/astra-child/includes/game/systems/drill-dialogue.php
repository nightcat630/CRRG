<?php
/**
 * 虚拟演习 · 对话引擎（2026-09-27）
 *
 * 口径（老式 CRPG：辐射 1/2、新维加斯）：
 *   1. **不问不说**——NPC 不会主动吐线索，玩家得自己开口问；话题要玩家想到（关键词匹配）。
 *   2. **知识门**——只有先知道某件事，某些话题才谈得下去；否则 NPC 会反问或闪躲。
 *   3. **检定门**——有些回答得先过检定（心理学／侦查／说服…），失败给的是搪塞而不是真话。
 *   4. **态度可累积**——威胁失败、给过好处、被戳穿，都会改变后续难度与口风。
 *   5. **同一事实多条来源**——谁都可能知道一部分，走哪条路取决于玩家怎么走。
 */
if (!defined('ABSPATH')) exit;

/** 时效门：until 里的任一旗标出现，话题即失效 */
function crrg_dlg_closed($t, $has) {
    foreach ((array) ($t['until'] ?? []) as $f) if ($has($f)) return true;
    return false;
}
/** 知识门判定：单标记，或 any:A|B 形式（任一满足即可） */
function crrg_dlg_need_ok($need, $has) {
    foreach ((array) $need as $n) {
        if (strpos((string) $n, 'any:') === 0) {
            $ok = false;
            foreach (explode('|', substr($n, 4)) as $f) if ($has(trim($f))) { $ok = true; break; }
            if (!$ok) return false;
        } elseif (!$has($n)) {
            return false;
        }
    }
    return true;
}
function crrg_dlg_reg() {
    $r = &crrg_drill_reg();
    if (!isset($r['dlg'])) $r['dlg'] = [];
    return $r['dlg'];
}
function crrg_dlg_register($id, $def) {
    $r = &crrg_drill_reg();
    if (!isset($r['dlg'])) $r['dlg'] = [];
    $r['dlg'][$id] = $def + ['id' => $id];
}
function crrg_dlg_all() { $r = &crrg_drill_reg(); return $r['dlg'] ?? []; }
/** 改写已注册话题的字段（给后续内容加厚用，不必重注册整个 NPC） */
function crrg_dlg_patch($npc_id, $topic_id, $patch = []) {
    $r = &crrg_drill_reg();
    if (!isset($r['dlg'][$npc_id]['topics'])) return false;
    foreach ($r['dlg'][$npc_id]['topics'] as $i => $t) {
        if (($t['id'] ?? '') !== $topic_id) continue;
        $r['dlg'][$npc_id]['topics'][$i] = $patch + $t;   // patch 里的键覆盖旧的
        return true;
    }
    return false;
}
/** 给 NPC 追加话题（插在最前，便于结案后话头优先） */
function crrg_dlg_add_topic($npc_id, $topic, $front = false) {
    $r = &crrg_drill_reg();
    if (!isset($r['dlg'][$npc_id])) return false;
    if ($front) array_unshift($r['dlg'][$npc_id]['topics'], $topic);
    else $r['dlg'][$npc_id]['topics'][] = $topic;
    return true;
}
function crrg_dlg_get($id) { $a = crrg_dlg_all(); return $a[$id] ?? null; }
/** 当前房间里能对话的人 */
function crrg_dlg_npcs_at($room) {
    $out = [];
    foreach (crrg_dlg_all() as $id => $d) {
        /* 2026-10-22（委员长："拜访说他在，问却说没人"）：
           原来只认**对话注册表里写死的 room** ✗ 与作息脱节 ✗
           现在：① 表里的 room 对上 → 在 ✓ ② 按**作息**（crrg_actor_where）在这间 → 也算在 ✓
           两边口径统一到"人此刻在哪" ✓ */
        $inTable = (($d['room'] ?? '') === $room);
        $bySched = false;
        if (!$inTable && function_exists('crrg_actor_where')) {
            /* 对话 id 常带 npc_ 前缀（npc_kang），作息表里是裸 id（kang）→ 两种都试 ✓ */
            $try = [(string) $id];
            if (strpos((string) $id, 'npc_') === 0) $try[] = substr((string) $id, 4);
            foreach ($try as $tid) {
                if ((string) crrg_actor_where($tid) === (string) $room) { $bySched = true; break; }
            }
        }
        if ($inTable || $bySched) $out[$id] = $d;
    }
    /* 常驻人员按时段走动：此刻在这个房间的，算在场 */
    if (function_exists('crrg_npc_at_room')) {
        foreach (crrg_npc_at_room($room) as $nid => $nd) {
            $key = 'npc_' . $nid;
            $def = function_exists('crrg_npc_dlg_def') ? crrg_npc_dlg_def($nid) : null;
            if ($def) $out[$key] = $def;
        }
    }
    return $out;
}
/** 按玩家输入（自由文本）找 NPC：姓名包含即可 */
function crrg_dlg_match_npc($room, $q) {
    foreach (crrg_dlg_npcs_at($room) as $id => $d) {
        $n = $d['name'] ?? '';
        if ($q !== '' && (mb_strpos($n, $q) !== false || mb_strpos($q, $n) !== false)) return $id;
        foreach ((array) ($d['alias'] ?? []) as $a) if ($q !== '' && mb_strpos($a, $q) !== false) return $id;
    }
    $list = crrg_dlg_npcs_at($room);
    return $list ? array_key_first($list) : null;
}
/** 找出与输入匹配的话题（id 或关键词） */
function crrg_dlg_match_topic($d, $q) {
    $q = trim((string) $q);
    if ($q === '') return null;
    $topics = (array) ($d['topics'] ?? []);
    foreach ($topics as $t) if (($t['id'] ?? '') === $q) return $t;                 // 1 id 全等
    foreach ($topics as $t) foreach ((array) ($t['q'] ?? []) as $k) if ($k === $q) return $t;   // 2 关键词全等
    $best = null; $bestLen = -1;
    foreach ($topics as $t) {                                                       // 3 子串：取匹配最长者
        foreach ((array) ($t['q'] ?? []) as $k) {
            if (mb_strpos($k, $q) !== false || mb_strpos($q, $k) !== false) {
                $len = mb_strlen($k);
                if ($len > $bestLen) { $best = $t; $bestLen = $len; }
            }
        }
        if (($t['id'] ?? '') !== '' && mb_strpos($t['id'], $q) !== false && mb_strlen($t['id']) > $bestLen) {
            $best = $t; $bestLen = mb_strlen($t['id']);
        }
    }
    return $best;
}
/** 态度：-2 翻脸 · -1 生冷 · 0 平常 · +1 好说话 */
function crrg_dlg_att(&$s, $npc_id) { return (int) (($s['att'][$npc_id] ?? 0)); }
function crrg_dlg_att_add(&$s, $npc_id, $n) {
    if (!isset($s['att']) || !is_array($s['att'])) $s['att'] = [];
    $s['att'][$npc_id] = max(-2, min(1, (int) ($s['att'][$npc_id] ?? 0) + $n));
}
/** 检定难度随态度浮动：好感则常规，生冷要困难，翻脸要极难 */
function crrg_dlg_check(&$s, $pc, $npc_id, $skill, $base_need = 2) {
    $att  = crrg_dlg_att($s, $npc_id);
    $need = max(2, $base_need - ($att >= 1 ? 1 : 0) + ($att <= -1 ? 1 : 0) + ($att <= -2 ? 1 : 0));
    [$ob, $op, $ow] = function_exists('crrg_outfit_mod') ? crrg_outfit_mod($pc, $skill, $npc_id) : [0, 0, []];
    $r = crrg_coc_check($skill, max(1, crrg_coc_skill_val($pc, $skill)), $ob, $op);
    if ($ow) $r['text'] .= '　［' . implode('，', $ow) . '］';
    $r['pass'] = crrg_coc_level_rank($r['level']) >= $need;
    $r['need'] = $need;
    return $r;
}

/**
 * 执行一次问话。返回 [输出行, 是否命中话题]
 * $topic_q 可为空：此时列出该 NPC 当前**能问出口**的话题（不泄露未解锁的）
 */
function crrg_dlg_ask(&$s, &$pc, $uid, $npc_id, $topic_q = '') {
    $has = fn($f) => crrg_seal_has($s, $f);      // 被当值传（crrg_dlg_closed/need_ok），故保留可调用包装
    $d = crrg_dlg_get($npc_id);
    if (!$d) return [['这里没有这个人。'], false];
    $flags = &$s['flags'];
    if (!is_array($flags)) $flags = [];
    $att = crrg_dlg_att($s, $npc_id);
    $out = [];
    if ($att <= -2) $out[] = $d['name'] . '不看你，也不接话。';

    /* 无话题 → 列出可问的（只列解锁的） */
    if ($topic_q === '') {
        $out[] = ($att <= -2 ? '（他现在不肯说话。）' : '你可以问：');
        foreach ((array) ($d['topics'] ?? []) as $t) {
            if (!crrg_dlg_need_ok($t['need'] ?? [], $has)) continue;
            if (crrg_dlg_closed($t, $has)) continue;
            if (!empty($t['once']) && crrg_seal_has($s, 'asked_' . $npc_id . '_' . $t['id'])) continue;
            $out[] = '　· ' . ($t['label'] ?? $t['id']);
        }
        $out[] = '（用法：问 ' . $d['name'] . ' <话题>）';
        return [$out, true];
    }

    $t = crrg_dlg_match_topic($d, $topic_q);
    if (!$t && ($topic_q === ($d['name'] ?? '') || in_array($topic_q, (array) ($d['alias'] ?? []), true))) {
        return crrg_dlg_ask($s, $pc, $uid, $npc_id, '');    // 报了对方名字 = 想跟他说话
    }
    if (!$t) {
        $out[] = $d['name'] . '：「这个我不知道，也管不着。」';
        return [$out, true];
    }
    if (crrg_dlg_closed($t, $has)) {          // 结案后旧话题关门，给一句在人物口吻里的回绝
        $out[] = $t['closed'] ?? ($d['name'] . '：「这事不是了了吗？」');
        return [$out, true];
    }
    if (($g = crrg_dlg_rep_gate($pc ?? [], $t)) !== '') { $out[] = $g; return [$out, false]; }
    if (!crrg_dlg_need_ok($t['need'] ?? [], $has)) {
        $out[] = $d['name'] . '：「你问这个干什么？」（他显然在等你先说出点什么。）';
        return [$out, true];
    }
    $asked = 'asked_' . $npc_id . '_' . ($t['id'] ?? '');
    if (!empty($t['once']) && crrg_seal_has($s, $asked)) {
        $out[] = $d['name'] . '：「刚才说过了。」';
        return [$out, true];
    }
    /* 检定门 */
    if (!empty($t['skill'])) {
        $r = crrg_dlg_check($s, $pc, $npc_id, $t['skill'][0], (int) ($t['skill'][1] ?? 2));
        $out[] = $r['text'];
        if (!$r['pass']) {
            foreach ((array) ($t['fail'] ?? ['他含糊过去，把话岔开了。']) as $l) $out[] = $l;
            if (!empty($t['fail_att'])) crrg_dlg_att_add($s, $npc_id, (int) $t['fail_att']);
            crrg_seal_set($s, $asked);
            return [$out, true];
        }
    }
    $text = $t['text'] ?? [];
    if (!empty($t['dyn']) && is_callable($t['dyn'])) {          // 按旗标分叉的台词
        $text = (array) call_user_func($t['dyn'], $s, $pc);
    }
    foreach ((array) $text as $l) $out[] = $l;
    foreach ((array) ($t['sets'] ?? []) as $f) crrg_seal_set($s, $f);
    if (!empty($t['att'])) crrg_dlg_att_add($s, $npc_id, (int) $t['att']);
    crrg_seal_set($s, 'asked_' . $npc_id . '_' . ($t['id'] ?? ''));
    if (!empty($t['once'])) crrg_seal_set($s, $asked);
    if (!empty($t['san'])) {
        $pc['san'] = max(0, (int) $pc['san'] - (int) $t['san']);
        $out[] = '理智 −' . (int) $t['san'] . '（现在 ' . $pc['san'] . '）。';
    }
    if (!empty($t['mythos'])) {
        $sk = $pc['skills'] ?: []; $sk['克苏鲁神话'] = (int) ($sk['克苏鲁神话'] ?? 0) + (int) $t['mythos'];
        $pc['skills'] = $sk;
        $out[] = '克苏鲁神话 +' . (int) $t['mythos'] . '%。';
    }
    return [$out, true];
}
/** 声望门：该地点声望不够则话题不可问（返回原因，够则返回空串） */
function crrg_dlg_rep_gate($pc, $topic) {
    foreach ((array) ($topic['rep'] ?? []) as $loc => $need) {
        if (!function_exists('crrg_rep_of')) return '';
        if (crrg_rep_of($pc, $loc) < (int) $need) {
            $pn = crrg_rep_places()[$loc]['short'] ?? $loc;
            return sprintf('%s那边还认不得你——要声望 %d（你现在 %d）。', $pn, (int) $need, crrg_rep_of($pc, $loc));
        }
    }
    return '';
}

/** 面板用：列出可问话题按钮 */
function crrg_dlg_panel_items($s, $npc_id, $pc = null) {
    $has = fn($f) => crrg_seal_has($s, $f);      // 被当值传（crrg_dlg_closed/need_ok），故保留可调用包装
    $d = crrg_dlg_get($npc_id);
    if (!$d) return [];
    $flags = (array) ($s['flags'] ?? []);
    /* 2026-09-29 修：下面两个判定需要**可调用**的 $has，但这里从来没定义过它 ✗ ——
       带 need/until 的话题会走到「null is not callable」致命错误（空条件时看不出来） */
    $has = fn($f) => in_array($f, $flags, true);
    $it = [];
    foreach ((array) ($d['topics'] ?? []) as $t) {
        if (!crrg_dlg_need_ok($t['need'] ?? [], $has)) continue;
        if (crrg_dlg_rep_gate(is_array($pc) ? $pc : [], $t) !== '') continue;   // 声望不够：不出现
        if (crrg_dlg_closed($t, $has)) continue;
        if (!empty($t['once']) && crrg_seal_has($s, 'asked_' . $npc_id . '_' . $t['id'])) continue;
        /* 2026-09-29：标签带上人名——聊天/投影通道发的是标签，同屋多人时"问：话题"会问错人 */
        $npcNameL = function_exists('crrg_dlg_get') ? ((crrg_dlg_get($npc_id)['name'] ?? '') ?: $npc_id) : $npc_id;
        $labT = trim((string) ($t['label'] ?? ''));
        if ($labT === '') $labT = trim((string) ($t['id'] ?? ''));
        if ($labT === '' || $npcNameL === '') continue;      /* 2026-10-22：空标签不出按钮（门禁会报"问「」"） */
        $it[] = ['label' => '问' . $npcNameL . '：' . $labT, 'k' => 'dlg', 'id' => $npc_id . '|' . ($t['id'] ?? '')];
    }
    return $it;
}
