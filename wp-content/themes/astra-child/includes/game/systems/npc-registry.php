<?php
/**
 * CRRG 演习系统 · NPC 统一模块（2026-10-01）
 * ============================================================
 * 目的：**所有 NPC 一套模板**。字段（委员长指定）：
 *   attrs       属性（默认模板即可，特殊设计才写 ✓）
 *   personality 性格
 *   schedule    作息：时段 → 房间（平常不同时间出现在不同地点 ✓）
 *   visits      特殊出现：[ ['room'=>…, 'when'=>条件, 'note'=>…] ]（特殊任务/时间地点 ✓）
 *   daily       日常对话（话题列表 ✓ 走对话 v2 ✓）
 *   special     特殊对话（条件对话 ✓ 同一份 dialogue 树里按 cond 分 ✓）
 *   func        特殊功能（如无则留空 ✓ 形如 ['shop'=>…, 'service'=>…]）
 *
 * 一份真相：
 *   · **不重写**现有内容 ✗ —— 本模块把三处来源**汇总**成统一定义 ✓：
 *       ① 基地名册 crrg_npc_roster()（作息 ✓）
 *       ② 对话注册表 crrg_dlg_all()（日常对话 ✓）
 *       ③ 任务人物表（特殊出现 ✓）
 *   · 于是"在场判定 / 左栏 / 对话 / 查看"全部读同一份 ✓（今天几次漏人都是因为没有唯一来源 ✗）
 *
 * 用法：
 *   $def = crrg_npc_def('kang');            // 取统一定义（缺字段自动补默认 ✓）
 *   crrg_npc_def_register('tw_barber', […]); // 补充/覆盖某人的额外字段（属性/性格/功能 ✓）
 *   crrg_npc_present_all($room, $s);         // 谁在这里（唯一判定 ✓）
 */
if (!defined('ABSPATH')) exit;

/** 默认值（"除特殊设计外，用默认模板" ✓） */
function crrg_npc_defaults() {
    return [
        'id'          => '',
        'name'        => '',
        'alias'       => [],
        'role'        => '',
        'attrs'       => ['体质' => 50, '敏捷' => 50, '力量' => 50, '体型' => 50, '外貌' => 50, '智力' => 50, '意志' => 50, '教育' => 50, '幸运' => 50],
        'personality' => '（未写性格——按默认：普通、务实。）',
        'schedule'    => [],            // ['黎明'=>'房间键', …]（基地作息 ✓）
        'visits'      => [],            // [['room'=>…, 'flag'=>…, 'mission'=>…, 'note'=>…]]
        'daily'       => [],            // 话题（来自对话注册表 ✓）
        'special'     => [],            // 特殊对话（同一棵树里按 cond ✓）
        'func'        => [],            // 特殊功能（无则空 ✓）
        'src'         => [],            // 来源标注（roster/dlg/mission/google ✓）
    ];
}

/** 额外的"补充字段"（属性/性格/功能 等，中央注册 ✓ 加一条即可） */
function crrg_npc_bonus() {
    static $B = null;
    if ($B !== null) return $B;
    $B = [
        /* 例：
        'tw_barber' => ['personality' => '话不多，手上比嘴上快。', 'func' => ['shop' => 'town_barber']],
        */
    ];
    return $B;
}
function crrg_npc_def_register($id, array $fields) {
    $B = &crrg_npc_bonus();
    $B[(string) $id] = array_merge((array) ($B[(string) $id] ?? []), $fields);
    return true;
}

/** 特殊出现表：旗标 → [任务, 房间 => [人名, 说明]]（与在场判定共用 ✓） */
function crrg_npc_visits() {
    return [
        's3_遇徒弟'   => ['m_mid_seal3', 'tw_hillpath' => ['田小满', '半夜骑摩托来的']],
        's3_遇冒充者' => ['m_mid_seal3', 'tw_hillpath' => ['冒充者', '开小货车来的']],
    ];
}

/** 汇总：取某人的统一定义 ✓（缺字段补默认 ✓） */
function crrg_npc_def($id) {
    $id = (string) $id;
    $d  = crrg_npc_defaults();
    $d['id'] = $id;
    $src = [];
    /* ① 名册（作息） */
    if (function_exists('crrg_npc_roster')) {
        foreach ((array) crrg_npc_roster() as $k => $v) {
            if ((string) $k !== $id && (string) ($v['n'] ?? '') !== $id) continue;
            $d['name']        = (string) ($v['n'] ?? $d['name']);
            $d['alias']       = (array) ($v['alias'] ?? []);
            $d['role']        = (string) ($v['role'] ?? '');
            $d['schedule']    = (array) ($v['sched'] ?? []);
            $src[] = 'roster';
            break;
        }
    }
    /* ② 对话注册表（日常对话 ✓） */
    if (function_exists('crrg_dlg_all')) {
        foreach ((array) crrg_dlg_all() as $k => $v) {
            $nm = (string) ($v['name'] ?? '');
            if ((string) $k !== $id && $nm !== $id) continue;
            if ($d['name'] === '') $d['name'] = $nm;
            if (!$d['role']) $d['role'] = (string) ($v['role'] ?? '');
            $d['daily'] = array_merge($d['daily'], (array) ($v['topics'] ?? []));
            $src[] = 'dlg';
            break;
        }
    }
    /* ③ 特殊出现（任务人物 ✓） */
    foreach (crrg_npc_visits() as $flag => $spec) {
        foreach ((array) ($spec[1] ?? []) as $room => $who) {
            if ((string) ($who[0] ?? '') !== $id && (string) ($who[0] ?? '') !== $d['name']) continue;
            $d['visits'][] = ['room' => (string) $room, 'flag' => (string) $flag, 'mission' => (string) ($spec[0] ?? ''), 'note' => (string) ($who[1] ?? '')];
        }
    }
    /* ④ 补充字段（属性/性格/功能 ✓） */
    $B = crrg_npc_bonus();
    if (isset($B[$id])) { $d = array_merge($d, $B[$id]); $src[] = 'bonus'; }
    /* 名字兜底：找不到就当 id 是人名 ✓ */
    if ($d['name'] === '') $d['name'] = $id;
    /* 特殊对话：树里的节点凡带 cond 的，归入 special ✓（工程上仍走同一棵树 ✓） */
    if (function_exists('crrg_dlg2_get')) {
        $t = crrg_dlg2_get($id);
        if (is_array($t)) foreach ((array) ($t['nodes'] ?? []) as $nid => $nd) {
            if (!empty($nd['cond'])) $d['special'][] = ['node' => (string) $nid, 'cond' => $nd['cond']];
        }
    }
    $d['src'] = $src;
    return $d;
}

/** 全部 NPC（名册 + 对话表 + 任务人物 ✓） */
function crrg_npc_all_ids() {
    $ids = [];
    if (function_exists('crrg_npc_roster')) foreach ((array) crrg_npc_roster() as $k => $v) $ids[(string) $k] = 1;
    if (function_exists('crrg_dlg_all')) foreach ((array) crrg_dlg_all() as $k => $v) $ids[(string) $k] = 1;
    foreach (crrg_npc_visits() as $f => $spec) foreach ((array) ($spec[1] ?? []) as $room => $who) if (!empty($who[0])) $ids['visit:' . $who[0]] = 1;
    return array_keys($ids);
}
function crrg_npc_def_all() { $o = []; foreach (crrg_npc_all_ids() as $id) $o[$id] = crrg_npc_def($id); return $o; }
