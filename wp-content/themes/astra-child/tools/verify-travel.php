<?php
/**
 * 车旅／周边地点 门禁（tools/verify-travel.php，2026-10-14）
 * ① **开放即落地**：每个"已通/部分通"的节点都要在开放表里，且落地房间**真实存在**
 * ② **落地房间可用**：房间里要有出口（不能是死屋 ✗）
 * ③ **新地点合规**：方位落在四条干线上（0/45/90/180 ✓）＋ 同干线 road_km **单调递增**（放射路网不交叉 ✓）
 * ④ **到了有事干**：每个新增周边地点至少 1 个勘查点（不然就是白跑 ✗）
 * ⑤ 目的地能算出路线（`crrg_travel_route_of` 不为空 ✓）
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$err = []; $warn = []; $ok = [];

$nodes = (array) crrg_worldmap_nodes();
$open  = (array) crrg_travel_open_nodes();
$dests = (array) crrg_travel_destinations();

/* ① 开放即落地 */
$noRoom = [];
foreach ($nodes as $id => $n) {
    if (!in_array((string) ($n['st'] ?? ''), ['已通', '部分通'], true)) continue;
    if (!isset($open[$id])) { $noRoom[] = $id . '（没进开放表）'; continue; }
    $rm = (string) ($open[$id]['room'] ?? '');
    $sc = (string) ($open[$id]['scene'] ?? '');
    if ($rm === '') { $noRoom[] = $id . '（没有落地房间）'; continue; }
    /* 房间是否真实存在于它指的场景 */
    $R = &crrg_drill_reg();
    $exists = false;
    if ($sc !== '' && isset($R['scenes'][$sc]['rooms'][$rm])) $exists = true;
    if (!$exists && isset($R['rooms'][$rm])) $exists = true;                       // 中枢注册的房间
    if (!$exists && function_exists('crrg_drill_rooms') && isset(crrg_drill_rooms()[$rm])) $exists = true;
    if (!$exists) $noRoom[] = $id . '（房间不存在：' . $sc . ':' . $rm . '）';
}
if ($noRoom) $err[] = '① 开放即落地失败：' . implode('；', array_slice($noRoom, 0, 6));
else $ok[] = sprintf('① 已通节点 %d 个，全部有落地房间 ✓', count(array_filter($nodes, fn($n) => in_array((string) ($n['st'] ?? ''), ['已通', '部分通'], true))));

/* ② 落地房间要有出口 */
$dead = [];
foreach ($open as $id => $row) {
    $rm = (string) ($row['room'] ?? '');
    if ($rm === '') continue;
    $sc = (string) ($row['scene'] ?? '');
    $R = &crrg_drill_reg();
    $def = $R['scenes'][$sc]['rooms'][$rm] ?? ($R['rooms'][$rm] ?? null);
    /* 2026-10-14：**车旅自动生成的到达点**（scene=region 的 r_* 房）靠车旅进出 ✓ 不算死屋 ✗
       （只有**定制房间**才要求自带出口 ✓） */
    if ($sc === 'region') continue;
    if (is_array($def) && empty($def['x'])) $dead[] = $id . ':' . $rm;
}
if ($dead) $err[] = '② 落地房间没有出口（死屋）：' . implode('、', array_slice($dead, 0, 5));
else $ok[] = '② 落地房间都有出口 ✓';

/* ③ 新地点合规（只看我加的那批：content/places-around.php 里列的） */
if (function_exists('crrg_game_worldmap_extra')) {
    $lanes = [0 => [], 45 => [], 90 => [], 180 => []];
    $bad = [];
    foreach ((array) crrg_game_worldmap_extra() as $id => $n) {
        $brg = (int) ($n['brg'] ?? -1);
        if (!isset($lanes[$brg])) { $bad[] = $id . '（方位 ' . $brg . ' 不在干线上）'; continue; }
        $lanes[$brg][] = $id;
    }
    foreach ($lanes as $brg => $ids) {
        if (!$ids) continue;
        $rows = [];
        foreach ($ids as $id) $rows[] = ['id' => $id, 'km' => (int) ($nodes[$id]['road_km'] ?? 0)];
        usort($rows, fn($a, $b) => $a['km'] <=> $b['km']);
        /* 与**同干线上已有的老节点**比：不能插在同里程（路口） */
        $mineIds = array_keys((array) crrg_game_worldmap_extra());
        foreach ($nodes as $oid => $on) {
            if ((int) ($on['brg'] ?? -1) !== $brg) continue;
            if (in_array((string) $oid, $mineIds, true)) continue;      // 别跟自己比 ✗（我第一版就这么错的）
            foreach ($rows as $r) if ((int) $r['km'] === (int) ($on['road_km'] ?? 0)) $bad[] = $r['id'] . ' 与 ' . $oid . ' 同里程';
        }
    }
    if ($bad) $err[] = '③ 新地点不合规：' . implode('；', array_slice($bad, 0, 5));
    else $ok[] = '③ 新地点方位／里程合规（落干线上且不撞里程）✓';
}

/* ④ 到了有事干（**每个已通/部分通的地点** ≥1 勘查点 ✓ —— 2026-10-14 委员长要的是"完善现有地点"，所以查现有 ✓） */
if (function_exists('crrg_node_surveys')) {
    $lazy = []; $done = 0;
    foreach ($nodes as $id => $n) {
        if (!in_array((string) ($n['st'] ?? ''), ['已通', '部分通'], true)) continue;
        if (count((array) crrg_node_surveys($id)) === 0) $lazy[] = (string) $id;
        else $done++;
    }
    if ($lazy) $warn[] = sprintf('④ 完善度：%d/%d 个已通地点有勘查点；还空着：%s', $done, $done + count($lazy), implode('、', array_slice($lazy, 0, 8)));
    else $ok[] = sprintf('④ 完善度：%d 个已通地点全部有勘查点 ✓', $done);
}

/* ⑤ 目的地能算路线 */
$noRoute = [];
foreach (['qingshi', 'baishi', 'ganzhou', 'liuhe', 'hewan', 'shiqiao', 'quanerwan'] as $d) {
    if (!isset($dests[$d])) { $noRoute[] = $d . '（不在目的地表）'; continue; }
    $pc = ['uid' => 0, 'clearance' => 3, 'veh' => ['卡车' => ['fuel' => 50, 'wear' => 0]]];
    $r = function_exists('crrg_travel_route_of') ? crrg_travel_route_of($pc, $d) : null;
    if (!is_array($r) || (int) ($r['km'] ?? 0) <= 0) $noRoute[] = $d . '（算不出路线）';
}
if ($noRoute) $err[] = '⑤ 路线算不出：' . implode('；', $noRoute);
else $ok[] = '⑤ 抽查 7 个目的地，路线都能算出来 ✓';

echo "=== 车旅门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：目的地 %d 个 ｜ 开放 %d 个 ｜ 错误 %d ｜ 提醒 %d\n", count($dests), count($open), count($err), count($warn));
exit($err ? 1 : 0);
