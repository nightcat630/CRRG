<?php
/**
 * bus · 第一批订阅者（Phase 0，2026-10-14）
 * ------------------------------------------------------------
 * 立规矩不能只立个空壳 ✗ —— 这里挂两个**真的有用**的订阅者，
 * 让"事件 → 事实 → 面板/任务/对话"这条路第一次真正跑通。
 *
 * ① 探索足迹：谁**第一个**进到关键房间 → 写一条独有型公共事实 `base.first_visit.<room>`
 *    （独有型＝一次写入不可逆 ✓ → 以后任务/对话可以引用"第 N 个发现某处的人"）
 * ② 事故标记：玩家在**关键房间**做检定失败 → 写一条 `base.mishap.<room>`（阈值型，2 人以上才算"大家都出过事"）
 *    → 这条是给"局面会变"用的：以后 beats 可以订阅它。
 */
if (!defined('ABSPATH')) exit;

/** 关键房间（值得记一笔"谁先到的"）：舱段/镇上要害/野外要点 */
function crrg_bus_key_rooms() {
    return [
        'b4_c2' => 'B4·C2 内间', 'b4_main' => 'B4 主通道', 'b3_water' => 'B3 水务间',
        'b2b_tech' => 'B2 技术研发室', 'b1_archive' => 'B1 档案室',
        'tw_bank' => '镇上信用社', 'tw_hillpath' => '后山小路', 'tw_paper' => '纸扎铺',
        'r_baishi' => '白石县城', 'r_ganzhou' => '甘州',
    ];
}

add_action('init', function () {
    if (!function_exists('crrg_evt_on')) return;

    /* ① 探索足迹（独有型：一次写入不可逆） */
    crrg_evt_on('player.enter', function ($p) {
        $room = (string) ($p['room'] ?? '');
        $uid  = (int) ($p['uid'] ?? 0);
        $key  = crrg_bus_key_rooms();
        if (!isset($key[$room])) return;
        $k = 'base.first_visit.' . $room;
        if (crrg_fact_count($k, 'base') > 0) return;          // 幂等：已经有人先到过了 ✓
        crrg_fact_propose($k, ['uid' => $uid, 'name' => ($p['name'] ?? '')], ['mode' => 'once', 'scope' => 'base'], $uid);
    }, ['module' => 'bus.p0']);

    /* ② 事故标记（阈值型：够 2 个不同的人在这间屋里出过事，才成立） */
    crrg_evt_on('player.check', function ($p) {
        $room = (string) ($p['room'] ?? '');
        $ok   = !empty($p['ok']);
        $uid  = (int) ($p['uid'] ?? 0);
        if ($ok || $room === '') return;                      // 幂等：只记失败
        $key = crrg_bus_key_rooms();
        if (!isset($key[$room])) return;
        crrg_fact_propose('base.mishap.' . $room, ['uid' => $uid, 'skill' => ($p['skill'] ?? '')],
                          ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], $uid);
    }, ['module' => 'bus.p0']);
}, 30);
