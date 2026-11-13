<?php
/**
 * bus · 通知订阅者（Phase 0，2026-10-14）
 * ------------------------------------------------------------
 * 目的：让"世界在动"这件事**能被玩家看见**，而不是只在后台发生（沙盒的体感来源之一）。
 *   · world.fact  → 记一个全局"有新记录"的标记（面板可以标红"基地动向"）
 *   · mission.beat→ 记一个**按人**的标记（这个人身上有新动静）
 *   · system.tick → 跨过 24 游戏小时就发 system.day，并落一条 `system.day.<天>` 事实
 * 全部幂等、只写标记/事实，不动个人档 ✓
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_evt_on')) return;

    crrg_evt_on('world.fact', function ($p) {
        update_option('crrg_fact_ping', ['id' => (int) ($p['id'] ?? 0), 'k' => (string) ($p['k'] ?? ''), 't' => time()], false);
    }, ['module' => 'bus.notify']);

    crrg_evt_on('mission.beat', function ($p) {
        $uid = (int) ($p['uid'] ?? 0);
        if ($uid <= 0) return;
        update_user_meta($uid, 'crrg_beat_ping', ['kind' => (string) ($p['kind'] ?? ''), 't' => time()]);
    }, ['module' => 'bus.notify']);

    crrg_evt_on('system.tick', function ($p) {
        $at = (int) ($p['at'] ?? 0);
        if ($at <= 0) return;
        $day = (int) floor($at / 86400);
        if (get_option('crrg_last_day', null) === $day) return;        // 幂等：同一天只发一次 ✓
        update_option('crrg_last_day', $day, false);
        if (function_exists('crrg_evt_emit')) crrg_evt_emit('system.day', ['day' => $day, 'at' => $at], ['src' => 'notify']);
        if (function_exists('crrg_fact_add')) crrg_fact_add('system.day', $day, 'global', 0);
    }, ['module' => 'bus.notify']);

    crrg_evt_on('system.day', function ($p) {
        /* 换日：给"世界自己动"留一个稳定的锚（后面任务/NPC 计划都订阅它 ✓） */
        update_option('crrg_world_day_note', ['day' => (int) ($p['day'] ?? 0), 't' => time()], false);
    }, ['module' => 'bus.notify']);

    /* 2026-10-14（engine）：任务生命周期也进通知层 —— 面板能标红"你刚接了/结了活" ✓ */
    crrg_evt_on('mission.accept', function ($p) {
        $uid = (int) ($p['uid'] ?? 0);
        if ($uid <= 0) return;
        update_user_meta($uid, 'crrg_beat_ping', ['kind' => 'accept', 'task' => (string) ($p['task'] ?? ''), 't' => time()]);
    }, ['module' => 'bus.notify']);

    crrg_evt_on('mission.settle', function ($p) {
        $uid = (int) ($p['uid'] ?? 0);
        if ($uid <= 0) return;
        update_user_meta($uid, 'crrg_beat_ping', ['kind' => 'settle', 'task' => (string) ($p['task'] ?? ''), 't' => time()]);
        $recent = (array) get_option('crrg_recent_settle', []);
        array_unshift($recent, ['task' => (string) ($p['task'] ?? ''), 'result' => (string) ($p['result'] ?? ''), 't' => time()]);
        update_option('crrg_recent_settle', array_slice($recent, 0, 20), false);
    }, ['module' => 'bus.notify']);

    /* 2026-10-14（R9）：**私下聊过的事会被传开** —— 嘴碎的人把你打听的事说出去，
       够 2 个人听过 → 写一条公共事实（别的任务/对话可以订阅 ✓） */
    crrg_evt_on('npc.talked', function ($p) {
        if ((string) ($p['kind'] ?? '') !== 'gossip') return;
        $who = (string) ($p['npc'] ?? '');
        if ($who === '') return;
        if (function_exists('crrg_fact_propose')) {
            crrg_fact_propose('base.被议论.' . $who, ['about' => (string) ($p['about'] ?? '')],
                ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], (int) ($p['uid'] ?? 0));
        }
    }, ['module' => 'bus.notify']);
}, 31);
