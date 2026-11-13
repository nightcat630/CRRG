<?php
/**
 * 队伍 / 车辆与行程：面板与窗口共用（2026-09-30 **手工**自 drill-economy 整块搬出）
 * 说明：这两块带前置分支与共享变量，按行切片会切断依赖 ✗ —— 整块搬进函数体才是对的 ✓
 */
if (!defined('ABSPATH')) exit;

function crrg_team_block(&$s, &$ctx, &$pc, $uid = 0) {
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
    $room = $s['room'] ?? '';
    /* 队伍 */
    $team = crrg_drill_team_of($uid);
    $titems = [];
    if (!$team) {
        $titems[] = $act('创建队伍', 'team_new');
        foreach (crrg_drill_teams() as $id => $t) $titems[] = $act('加入 · ' . $t['n'] . '（' . count($t['members']) . ' 人）', 'team_join', $id);
    } else {
        $is_leader = (int) $team['leader'] === (int) $uid;
        $titems[] = $act('退队', 'team_leave');
        if ($is_leader) {
            $titems[] = $act('分红：平均', 'team_share', 'even');
            $titems[] = $act('分红：队长 50%', 'team_share', 'lead50');
            $titems[] = $act('解散队伍', 'team_disband');
        }
        $lines = [];
        foreach ((array) $team['members'] as $m) {
            $u = get_userdata($m);
            $lines[] = ($u ? $u->display_name : ('#' . $m)) . ' ' . ((int) $team['leader'] === (int) $m ? '（队长）' : '')
                     . ' ' . ((int) ($team['share'][(string) $m] ?? 0)) . '%';
        }
        return ['title' => '队伍 · ' . $team['n'], 'note' => implode('　', $lines), 'items' => $titems];
    }
    return null;
}

function crrg_vehicle_block(&$s, &$ctx, &$pc, $uid = 0) {
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
    $room = $s['room'] ?? '';
    /* 车辆与行程（车库 / 加油站 / 修车铺） */
    if (function_exists('crrg_travel_status_lines')) {
        $it = [];
        $mode = crrg_travel_mode($pc);
        $open = [];
        foreach (crrg_travel_destinations() as $id => $n) {
            if ($id !== 'base' && !crrg_travel_node_open($id)) continue;
            $open[] = $id;
        }
        if ($room === 'garage_maint' || $room === 'tw_gas') $it[] = ['label' => '加油（加满）', 'k' => 'travel_refuel', 'id' => ''];
        if ($room === 'garage_maint' || $room === 'tw_repair') {
            $it[] = ['label' => '保养（80 元，损耗 −40）', 'k' => 'travel_service', 'id' => ''];
            $it[] = ['label' => '大修（250 元，损耗归零）', 'k' => 'travel_service_major', 'id' => ''];
        }
        foreach ($open as $id) {
            if ($id === 'base') continue;
            $n = crrg_travel_destinations()[$id];
            $rt = crrg_travel_route_of($pc, $id);
            $cls = $rt['cls'] ?? 'flat';
            $est = crrg_travel_estimate(crrg_travel_mode($pc), (int) ($rt['km'] ?? $n['road_km']), $cls, $pc);
            $it[] = ['label' => sprintf('长途 · %s（%d km，约 %.1f 小时，油 %.1f 升）', $n['n'], (int) $n['road_km'], $est['hours'], $est['fuel']),
                     'k' => 'travel_go', 'id' => $id];
        }
        if (in_array($room, ['garage_maint', 'tw_repair', 'tw_gas'], true) || !empty($pc['travel'])) {
            return ['title' => '车辆与行程', 'note' => implode('　', array_slice(crrg_travel_status_lines($pc), 0, 2)),
                    'items' => $it ?: [['label' => '（无）', 'k' => 'noop', 'id' => '', 'off' => true]]];
        }
    }
    return null;
}
