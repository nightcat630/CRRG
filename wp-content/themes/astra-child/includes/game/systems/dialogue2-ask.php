<?php
/* 对话 v2 · 交互层：把玩家的「问 <NPC> [选项]」落到节点树上
 * 返回 [输出行, 是否有进展]（与旧 crrg_dlg_ask 的返回形状保持一致）
 */
if (!defined('ABSPATH')) exit;

/** 一个选项此刻能不能选：cond 满足，且目标节点不是"已经问过的一次性节点" */
function crrg_dlg2_choice_ok(&$s, &$pc, $npc_id, $tree, array $ch) {
    if (!crrg_dlg2_ok($s, $pc, (array) ($ch['cond'] ?? []))) return false;
    $goto = (string) ($ch['goto'] ?? '');
    if ($goto !== '' && $goto !== 'end' && isset($tree['nodes'][$goto])) {
        $nd = $tree['nodes'][$goto];
        if (!empty($nd['once']) && in_array('asked_' . $npc_id . '_' . $goto, (array) ($s['flags'] ?? []), true)) return false;
    }
    return true;
}

function crrg_dlg2_ask(&$s, &$pc, $uid, $npc_id, $input = '') {
    $tree = crrg_dlg2_tree($npc_id, $s, $pc);
    if (!$tree) return [['这里没有这个人。'], false];
    $input = trim((string) $input);

    /* ── 兼容层（2026-09-29 决策）：旧 NPC **直接委托旧实现**，保证逐字节一致 ✓
       理由：旧系统其实有条件（need/closed/rep_gate/once/skill/dyn/att）但都写成 PHP 闭包，
             且结构是"平行话题" ✗ —— 与它完全对齐不如**原样调用**；
             新树（v2）才是以后的写法，作者按需逐个迁移 ✓ ── */
    if (!empty($tree['legacy_list']) && function_exists('crrg_dlg_ask')) {
        return crrg_dlg_ask($s, $pc, $uid, $npc_id, $input);
    }
    if (!empty($tree['legacy_list'])) {
        $kids = (array) $tree['legacy_list'];
        $avail = [];
        foreach ($kids as $id) {
            $nd = $tree['nodes'][$id];
            if (!empty($nd['once']) && in_array('asked_' . $npc_id . '_' . $id, (array) ($s['flags'] ?? []), true)) continue;
            $avail[] = $id;
        }
        if ($input === '') {                                  // 只报名字＝想看能问什么
            $out = [];
            foreach ($avail as $id) $out[] = '　· ' . ($tree['nodes'][$id]['label'] ?? $id);
            return [$out, true];
        }
        foreach ($avail as $id) {                             // 按关键词/标签匹配话题
            $nd = $tree['nodes'][$id];
            $hit = false;
            foreach ((array) ($nd['kw'] ?? []) as $kw) if ($kw !== '' && (mb_strpos($input, $kw) !== false || mb_strpos($kw, $input) !== false)) { $hit = true; break; }
            if (!$hit && mb_strpos((string) ($nd['label'] ?? ''), $input) !== false) $hit = true;
            if (!$hit) continue;
            $out = (array) $nd['text'];
            foreach ((array) ($nd['set'] ?? []) as $f) if (function_exists('crrg_seal_set')) crrg_seal_set($s, (string) $f);
            return [$out, true];
        }
        return [['（他没听懂你要问什么。可以只报他的名字，看他乐意聊什么。）'], false];
    }

    /* ── 新格式：节点树 ── */
    if (!isset($s['dlg2']) || !is_array($s['dlg2'])) $s['dlg2'] = [];
    $cur = (string) ($s['dlg2'][$npc_id] ?? '');
    if ($cur === '' || !isset($tree['nodes'][$cur])) $cur = (string) (crrg_dlg2_current($s, $pc, $npc_id, $tree) ?? '');

    /* 入场时先施加该节点的效果（一次性） */
    $enter = function ($id) use (&$s, &$pc, $npc_id, $tree) {
        $nd = $tree['nodes'][$id] ?? [];
        $extra = crrg_dlg2_apply($s, $pc, $npc_id, $nd);
        if (!empty($nd['once']) && function_exists('crrg_seal_set')) crrg_seal_set($s, 'asked_' . $npc_id . '_' . $id);
        return $extra;
    };

    /* 没给输入：展示当前节点 + 选项 */
    if ($input === '') {
        if ($cur === '') return [['（他不想说什么。）'], false];
        $nd = $tree['nodes'][$cur] ?? [];
        $out = (array) ($nd['text'] ?? []);
        $out = array_merge($out, $enter($cur));
        $n = 0;
        foreach ((array) ($nd['choices'] ?? []) as $ch) {
            if (!crrg_dlg2_choice_ok($s, $pc, $npc_id, $tree, $ch)) continue;
            $n++;
            $out[] = sprintf('　%d. %s', $n, (string) ($ch['label'] ?? '继续'));
        }
        if ($n > 0) $out[] = '（用「问 ' . $npc_id . ' 1」选第 1 项）';
        else $out[] = '（话说到这里。）';
        return [$out, true];
    }

    /* 给了输入：选选项（编号或关键词） */
    if ($cur === '') return [['（他没听懂。）'], false];
    $nd = $tree['nodes'][$cur] ?? [];
    $list = [];
    foreach ((array) ($nd['choices'] ?? []) as $ch) {
        if (!crrg_dlg2_choice_ok($s, $pc, $npc_id, $tree, $ch)) continue;
        $list[] = $ch;
    }
    $pick = null;
    if (preg_match('/^\d+$/u', $input)) { $i = (int) $input - 1; $pick = $list[$i] ?? null; }
    if ($pick === null) {
        foreach ($list as $ch) {
            $lb = (string) ($ch['label'] ?? '');
            $kw = (array) ($ch['kw'] ?? []);
            $hit = ($lb !== '' && mb_strpos($lb, $input) !== false);
            foreach ($kw as $k) if ($k !== '' && (mb_strpos($input, $k) !== false || mb_strpos($k, $input) !== false)) $hit = true;
            if ($hit) { $pick = $ch; break; }
        }
    }
    if ($pick === null) return [['（他没有接这句话。可以只报他的名字，看他说什么。）'], false];

    $out = [];
    $out = array_merge($out, crrg_dlg2_apply($s, $pc, $npc_id, $pick));      // 选项自带效果
    $goto = (string) ($pick['goto'] ?? '');
    if ($goto === 'end' || $goto === '') {                                   // 收场：清状态
        unset($s['dlg2'][$npc_id]);
        if ($goto === 'end') $out[] = '（这段说完了。）';
        return [$out, true];
    }
    if (!isset($tree['nodes'][$goto])) { unset($s['dlg2'][$npc_id]); return [array_merge($out, ['（对方没接下去。）']), true]; }
    $s['dlg2'][$npc_id] = $goto;
    $nd2 = $tree['nodes'][$goto];
    $out = array_merge($out, (array) ($nd2['text'] ?? []));
    $out = array_merge($out, $enter($goto));
    $n = 0;
    foreach ((array) ($nd2['choices'] ?? []) as $ch) {
        if (!crrg_dlg2_choice_ok($s, $pc, $npc_id, $tree, $ch)) continue;
        $n++;
        $out[] = sprintf('　%d. %s', $n, (string) ($ch['label'] ?? '继续'));
    }
    if ($n > 0) $out[] = '（用「问 ' . $npc_id . ' 1」选第 1 项）';
    return [$out, true];
}

/**
 * 只读预览（2026-10-01，为"对话窗口"加）：给窗口显示用——**不施加任何效果** ✗
 * 返回 ['name'=>…, 'text'=>[…], 'choices'=>[['label','cmd','tag']…]]
 *    · 旧话题表（legacy）→ 列出可用话题 ✓（本身不置旗标 ✓）
 *    · v2 节点树 → 当前节点的台词 ＋ 可用选项（带 [标签] ✓）
 */
function crrg_dlg2_peek(&$s, &$pc, $npc_id) {
    $d = function_exists('crrg_dlg_get') ? crrg_dlg_get($npc_id) : null;
    $name = (string) ($d['name'] ?? $npc_id);
    $tree = crrg_dlg2_tree($npc_id, $s, $pc);
    if (!$tree) return null;
    /* 旧话题表 */
    if (!empty($tree['legacy_list'])) {
        $rows = [];
        foreach ((array) $tree['legacy_list'] as $id) {
            $nd = $tree['nodes'][$id] ?? [];
            if (!empty($nd['once']) && in_array('asked_' . $npc_id . '_' . $id, (array) ($s['flags'] ?? []), true)) continue;
            $lb = (string) ($nd['label'] ?? $id);
            $rows[] = ['label' => $lb, 'cmd' => '问 ' . $name . ' ' . $lb, 'tag' => ''];
        }
        return ['name' => $name, 'text' => ['（' . $name . ' 等着你先开口。）'], 'choices' => $rows];
    }
    /* v2 树：当前节点 */
    $cur = (string) ($s['dlg2'][$npc_id] ?? '');
    if ($cur === '' || !isset($tree['nodes'][$cur])) $cur = (string) (crrg_dlg2_current($s, $pc, $npc_id, $tree) ?? '');
    if ($cur === '') return ['name' => $name, 'text' => ['（他不想说什么。）'], 'choices' => [['label' => '（结束对话）', 'cmd' => '问 ' . $name . ' （不问了）', 'tag' => '']]];
    $nd = $tree['nodes'][$cur] ?? [];
    $rows = []; $n = 0;
    foreach ((array) ($nd['choices'] ?? []) as $ch) {
        if (!crrg_dlg2_choice_ok($s, $pc, $npc_id, $tree, $ch)) continue;
        $n++;
        /* 选项标签：从 cond 里取一个"人话"标签（辐射 2 那种 [技能]/[人情] ✓） */
        $tag = '';
        $cond = (array) ($ch['cond'] ?? []);
        if (isset($cond['fav']))  $tag = '人情';
        elseif (isset($cond['rep']))  $tag = '声望';
        elseif (isset($cond['item'])) $tag = '持有';
        elseif (isset($cond['flag'])) $tag = '线索';
        elseif (isset($cond['phase'])) $tag = '时机';
        $rows[] = ['label' => (string) ($ch['label'] ?? '继续'), 'cmd' => '问 ' . $name . ' ' . $n, 'tag' => $tag];
    }
    if (!$rows) $rows[] = ['label' => '（结束对话）', 'cmd' => '问 ' . $name . ' end', 'tag' => ''];
    return ['name' => $name, 'text' => array_values((array) ($nd['text'] ?? [])), 'choices' => $rows];
}
