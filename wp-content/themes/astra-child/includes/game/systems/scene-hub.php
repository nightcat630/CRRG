<?php
/**
 * 自 includes/drill-hub.php 搬出的函数（2026-09-30）
 */
if (!defined('ABSPATH')) exit;

function crrg_drill_dorm_of($uid) {
    $pc = crrg_drill_pc($uid);
    return $pc['dorm'] ?? ['desc' => '', 'furn' => [], 'last' => ''];
}
function crrg_drill_dorm_lines($pc, $who) {
    $d = $pc['dorm'] ?? [];
    $l = ['〔' . $who . ' 的宿舍〕'];
    /* 2026-10-01 修：家具原来直接显示**内部 id** ✗（`bed_soft、lamp_cold`）→ 换成**中文名** ✓ */
    $fn = [];
    foreach ((array) ($d['furn'] ?? []) as $f) {
        $nm = (string) $f;
        if (function_exists('crrg_drill_goods')) {
            foreach ((array) (crrg_drill_goods()['家具'] ?? []) as $g) {
                if (($g['dorm'] ?? '') === $f) { $nm = (string) ($g['n'] ?? $f); break; }
            }
        }
        $fn[] = $nm;
    }
    $l[] = '家具与设施：' . ($fn ? implode('、', $fn) : '只有配发的床、桌、柜');
    /* 私人布置：两种存法都认（`deco` 槽位表 ✓ ／ `deco_items` 清单 ✓） */
    $deco = [];
    foreach ((array) ($d['deco'] ?? []) as $slot => $name) if (is_string($name) && $name !== '') $deco[] = $name;
    foreach ((array) ($d['deco_items'] ?? []) as $name) if (is_string($name) && $name !== '' && !in_array($name, $deco, true)) $deco[] = $name;
    $l[] = '私人布置：' . ($deco ? implode('、', $deco) : '（空着）');
    return $l;
}

/* ================= 左栏「可用服务」按钮 ================= */
function crrg_drill_services($room, $pc) {
    $svc = [];
    $m   = !empty($pc['mission']) ? crrg_drill_mission($pc['mission']) : null;
    $allShops = function_exists('crrg_drill_shops_all') ? crrg_drill_shops_all() : crrg_drill_shops();
    foreach (array_keys(crrg_drill_shops_at($room)) as $sid) {
        $shop = $allShops[$sid] ?? null;
        if ($shop) $svc[] = ['label' => '商店 · ' . $shop['n'], 'cmd' => '商店'];
    }
    if ($room === 'b2b_hall' || $room === 'b2b_duty') $svc[] = ['label' => '值班板 · 任务', 'cmd' => '任务'];
    if ($m && ($m['depart_room'] ?? '') === $room) $svc[] = ['label' => '出发 · ' . $m['name'], 'cmd' => '出发'];
    if (in_array($room, ['b1_room', 'b1_dorm'], true)) $svc[] = ['label' => '宿舍面板', 'cmd' => '宿舍'];
    if ($room === 'b4_obs') $svc[] = ['label' => '认知稳定性测试', 'cmd' => '测试'];
    $svc[] = ['label' => '账户', 'cmd' => '钱包'];
    return $svc;
}

/* ==================================================================
 * 模组扩展接口（2026-09-27）：场景追加 / 观察钩子 / 多路径任务步骤
 * ================================================================== */
/** 往已注册的场景里追加房间、坐标、实体（不覆盖原有内容） */
function crrg_drill_scene_extend($id, $rooms = [], $pos = [], $ents = []) {
    $r = &crrg_drill_reg();
    if (!isset($r['scenes'][$id])) $r['scenes'][$id] = ['name' => $id, 'rooms' => [], 'pos' => [], 'ents' => []];
    /* 2026-09-29 修：按房间**深合并**——此前是浅合并，补一个 'x' 会把该房间的 t／d 一起覆盖掉
       （青石镇主街中段/东段、老陵就这么变成了 null） */
    foreach ((array) $rooms as $rk => $rv) {
        $old = (array) ($r['scenes'][$id]['rooms'][$rk] ?? []);
        $merged = array_merge($old, (array) $rv);
        if (isset($rv['x']) && isset($old['x'])) $merged['x'] = array_merge((array) $old['x'], (array) $rv['x']);
        $r['scenes'][$id]['rooms'][$rk] = $merged;
    }
    $r['scenes'][$id]['pos']   = array_merge($r['scenes'][$id]['pos'] ?? [], $pos);
    foreach ($ents as $room => $list) {
        $r['scenes'][$id]['ents'][$room] = array_merge($r['scenes'][$id]['ents'][$room] ?? [], $list);
    }
}

function crrg_drill_own_count($pc, $id) {
    $n = (int) crrg_drill_bag_count($pc, $id);
    $name = crrg_trade_name($id);
    foreach ((array) ($pc['dorm']['deco_items'] ?? []) as $x) if ($x === $name) $n++;
    foreach ((array) ($pc['dorm']['furn'] ?? []) as $x) if ($x === $name) $n++;
    return $n;
}
function crrg_drill_scene_add_exit($id, $room, $label, $target) {
    $r = &crrg_drill_reg();
    if (isset($r['scenes'][$id]['rooms'][$room])) {
        $r['scenes'][$id]['rooms'][$room]['x'][$label] = $target;
        return true;
    }
    return false;
}

function crrg_drill_route_hint($from, $to) {
    $rooms = crrg_drill_rooms();
    if (!isset($rooms[$to])) return '（目标不在当前区域——先回基地／先到镇上再找。）';
    if (!isset($rooms[$from])) return '';
    if ($from === $to) return '（你就在这儿。）';
    /* 要靠动作才能到的房间：先给动作提示（否则 BFS 会绕一条十段的远路） */
    if ($from === 'b4_buffer' && in_array($to, ['b4_obs', 'b4_main', 'b4_guard', 'b4_transfer'], true)) {
        return '（先过闸：在缓冲区连续「刷卡」三次，出闸即观察室，再往主走廊。共 2 段。）';
    }
    /* 只走"能走的出口"：排除刷卡／上锁这类需要条件的门（2026-09-29 修） */
    $walkable = function ($label, $dest) use ($rooms) {
        if ($label === '刷卡') return false;
        if (mb_strpos($label, '锁') !== false || mb_strpos($label, '闸') !== false) return false;
        return isset($rooms[$dest]);
    };
    $q = [[$from, []]]; $seen = [$from => 1];
    while ($q) {
        [$cur, $acc] = array_shift($q);
        foreach ((array) ($rooms[$cur]['x'] ?? []) as $label => $dest) {
            if (!$walkable($label, $dest) || isset($seen[$dest])) continue;
            $path = array_merge($acc, [$label]);
            if ($dest === $to) return implode(' → ', $path) . '（共 ' . count($path) . ' 段）';
            $seen[$dest] = 1;
            $q[] = [$dest, $path];
        }
    }
    return '（从这里没有可走的路到那儿——先回主走廊／主楼梯再找。）';
}

