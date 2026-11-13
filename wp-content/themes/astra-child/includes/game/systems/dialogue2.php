<?php
/**
 * CRRG 演习系统 · 对话引擎 v2（2026-09-29）
 * ------------------------------------------------------------
 * 为什么重做：旧对话是"一列平行话题"，每个话题＝一张点击卡 ✗ —— 没有节点、没有分支、
 * 条件和效果都写死在代码里。作者加一句台词要改 PHP ✗。
 *
 * v2 的设计（**数据驱动、易编辑、通用**）：
 *   ① **节点树**：nodes[id] = ['text'=>[…], 'choices'=>[…]]，可跳转、可回环、可结束
 *   ② **声明式条件** cond：flag / not_flag / item / rep / fav / phase / once  —— 作者不写 PHP
 *   ③ **声明式效果**：set / fav / give / rep / stock
 *   ④ **动态入口**：entry 指定起始节点；也可给 nodes 配 cond，按当前状态选第一个可用节点
 *   ⑤ **兼容层**：没写新树的老 NPC，自动把旧 topics 转成"单层树"→ 旧内容立刻可用 ✓
 *
 * 玩家侧接口（不变，仍是「问 <NPC>」）：
 *   · 「问 常守业」        → 显示当前节点的台词 ＋ 可用选项（编号）
 *   · 「问 常守业 2」      → 选第 2 项
 *   · 「问 常守业 配方」    → 按关键词选（选项 label 里含该词即可）
 */
if (!defined('ABSPATH')) exit;

/** 注册一棵对话树 */
function crrg_dlg2_register($npc, array $tree) {
    if (!function_exists('crrg_game_register')) return false;
    return crrg_game_register('dlg2', $npc, $tree + ['npc' => $npc], ['module' => 'content', 'file' => __FILE__]);
}
function crrg_dlg2_get($npc) {
    if (!function_exists('crrg_game_one')) return null;
    $t = crrg_game_one('dlg2', $npc);
    if (is_array($t)) return $t;
    /* 作者可以直接写**人名**：这里把名字解析成 NPC id（便于编写 ✓） */
    if (function_exists('crrg_dlg_all')) {
        foreach ((array) crrg_dlg_all() as $id => $d) {
            $nm = (string) ($d['name'] ?? '');
            $al = (array) ($d['alias'] ?? []);
            if ($nm === (string) $npc || in_array((string) $npc, $al, true)) {
                $t2 = crrg_game_one('dlg2', $id);
                if (is_array($t2)) return $t2;
            }
        }
    }
    return null;
}

/* ══════════ 条件求值（声明式） ══════════ */
function crrg_dlg2_ok(&$s, &$pc, array $cond) {
    if (!$cond) return true;
    $has = fn($f) => in_array($f, (array) ($s['flags'] ?? []), true);
    if (!empty($cond['flag'])     && !$has((string) $cond['flag'])) return false;
    if (!empty($cond['not_flag']) && $has((string) $cond['not_flag'])) return false;
    foreach ((array) ($cond['all_flags'] ?? []) as $f) if (!$has((string) $f)) return false;
    if (!empty($cond['item'])) {
        $need = (int) ($cond['n'] ?? 1);
        $own = function_exists('crrg_drill_own_count') ? crrg_drill_own_count($pc, (string) $cond['item']) : 0;
        if ($own < $need) return false;
    }
    if (!empty($cond['rep'])) {                       // ['rep' => ['town_qingshi', 10]]
        $cur = function_exists('crrg_rep_of') ? (int) crrg_rep_of($pc, (string) $cond['rep'][0]) : 0;
        if ($cur < (int) $cond['rep'][1]) return false;
    }
    if (!empty($cond['fav'])) {                       // ['fav' => ['康明远', 2]]
        $cur = (int) ($pc['npc_fav'][(string) $cond['fav'][0]] ?? 0);
        if ($cur < (int) $cond['fav'][1]) return false;
    }
    if (!empty($cond['phase'])) {                     // ['phase' => ['夜','深夜']]
        $ph = function_exists('crrg_world_clock') ? (crrg_world_clock()['phase'] ?? '') : '';
        if (!in_array($ph, (array) $cond['phase'], true)) return false;
    }
    return true;
}

/* ══════════ 效果施加（声明式） ══════════ */
function crrg_dlg2_apply(&$s, &$pc, $npc, array $eff) {
    $out = [];
    foreach ((array) ($eff['set'] ?? []) as $f) if (function_exists('crrg_seal_set')) crrg_seal_set($s, (string) $f);
    if (!empty($eff['fav']))   crrg_npc_fav_add($pc, (string) $eff['fav'][0], (int) $eff['fav'][1]);
    foreach ((array) ($eff['give'] ?? []) as $it => $n) {              // ['give' => ['doc_x' => 1]]
        if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, (string) $it, (int) $n);
        $out[] = '（得到：' . (function_exists('crrg_drill_item_name') ? crrg_drill_item_name($it) : $it) . '×' . (int) $n . '）';
    }
    if (!empty($eff['rep']))   crrg_rep_add($pc, (string) $eff['rep'][0], (int) $eff['rep'][1], (string) ($eff['rep'][2] ?? '对话'));
    if (!empty($eff['stock'])) crrg_seal_use($pc, (int) $eff['stock'][0], (string) ($eff['stock'][1] ?? '对话'));
    /* 2026-10-14（engine）：对话里"记下线索" —— 登记/冲结论交给引擎 ✓
       写法：['clue' => '线索键', 'clue_task' => '任务id']  或  ['clue' => ['任务id','线索键']] */
    if (!empty($eff['clue'])) {
        $tk = $eff['clue']; $task = (string) ($eff['clue_task'] ?? '');
        if (is_array($tk)) { $task = (string) ($tk[0] ?? ''); $tk = (string) ($tk[1] ?? ''); }
        if ($task !== '' && $tk !== '' && function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, (string) $tk)) {
            $d = (array) (crrg_task($task)['clues'][$tk] ?? []);
            $out[] = '〔线索〕' . ($d['title'] ?? $tk) . '　' . (string) ($d['point'] ?? '');
        }
    }
    if (!empty($eff['open']) && function_exists('crrg_ui_request_open')) {     // 对话里打开窗口（如与 NPC 交易 ✓）
        crrg_ui_request_open($s, (string) $eff['open']);
        $out[] = '（窗口已打开。）';
    }
    return $out;
}

/* ══════════ 兼容层：旧 topics → 单层树 ══════════ */
function crrg_dlg2_from_legacy($npc_id) {
    $d = function_exists('crrg_dlg_get') ? crrg_dlg_get($npc_id) : null;
    if (!$d || empty($d['topics'])) return null;
    $nodes = []; $kids = [];
    $__n = 0;
    foreach ((array) $d['topics'] as $i => $t) {
        /* 2026-10-29：**话题键可能是中文名**（旧系统风格）→ 不能拿它做算术 ✗ */
        $__n++;
        $id = 't' . $__n . '_' . preg_replace('/[^a-z0-9_]/u', '', (string) ($t['id'] ?? 'x'));
        $kids[] = $id;
        $nodes[$id] = [
            'text'  => (array) ($t['text'] ?? []),
            'label' => (string) ($t['label'] ?? $t['id'] ?? $id),      // 选项显示名（兼容层用话题名）
            'kw'    => (array) ($t['q'] ?? []),                        // 关键词（旧系统靠它匹配）
            'once'  => !empty($t['once']),
            'set'   => ['asked_' . $npc_id . '_' . (string) ($t['id'] ?? $__n)],
        ];
    }
    return ['npc' => $npc_id, 'entry' => null, 'legacy_list' => $kids, 'nodes' => $nodes];
}

/* ══════════ 取得某 NPC 的树（新格式优先，否则用兼容层） ══════════ */
function crrg_dlg2_tree($npc_id, &$s = null, &$pc = null) {
    $t = crrg_dlg2_get($npc_id);
    if (!$t) $t = crrg_dlg2_from_legacy($npc_id);
    /* 2026-10-14（engine）：**任务对话注入**在出口合并 ✓ —— 新人物/插支线/结束回收都走这里；
       不传 $s/$pc 时＝旧行为（不合并）✓ 老调用方零改动 ✓ */
    if ($s !== null && function_exists('crrg_dlg_overlay_apply')) {
        $t = crrg_dlg_overlay_apply($npc_id, $t, $s, $pc);
    }
    return $t;
}

/** 当前该展示哪个节点？（legacy 单层树＝展示"话题列表"） */
function crrg_dlg2_current(&$s, &$pc, $npc_id, $tree) {
    if (!empty($tree['legacy_list'])) return null;                     // legacy：直接列话题
    $entry = (string) ($tree['entry'] ?? '');
    if ($entry !== '' && isset($tree['nodes'][$entry])) return $entry;
    /* 没写 entry：按 nodes 顺序取第一个 cond 满足的 */
    foreach ((array) $tree['nodes'] as $id => $nd) {
        if (!empty($nd['once']) && in_array('asked_' . $npc_id . '_' . $id, (array) ($s['flags'] ?? []), true)) continue;
        if (crrg_dlg2_ok($s, $pc, (array) ($nd['cond'] ?? []))) return $id;
    }
    return null;
}
