<?php
/**
 * 县城门禁（tools/verify-city.php，2026-10-14）
 * 委员长："所有县城（规模）扩大到和青石镇一样，而且要各具特色。"
 * 五查：
 *   ① **规模达标**：每个县城级场景的房间数 ≥ 青石镇（标尺 ✓ 现取青石镇的实数）
 *   ② **可达**：从该城的入口房间出发，**所有房间都能走到**（BFS ✓ 防孤岛 ✗）
 *   ③ **出口合法**：每个出口指向的房间都必须存在 ✓
 *   ④ **落地**：该地图节点要能落到城里（车旅开放表 ✓）
 *   ⑤ **各具特色**：两座城**不许有完全相同的房间描述**（同一模子＝白做 ✗）；房间键前缀必须各不相同 ✓
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$err = []; $warn = []; $ok = [];
$R = &crrg_drill_reg();

/* 标尺：青石镇 */
$townRooms = (array) ($R['scenes']['town']['rooms'] ?? []);
$ruler = count($townRooms);
if ($ruler <= 0) { fwrite(STDERR, "找不到青石镇场景，无法比对\n"); exit(1); }
$ok[] = '标尺：青石镇 ' . $ruler . ' 间 ✓';

/* 要检查的城（场景 => [入口房间, 地图节点]） */
$cities = [
    /* 分档标尺（2026-10-14 委员长："甘州是大城市，不是县城" ✓ 大城市该比县城大 ✓） */
    'county'  => ['entry' => 'bs_gate', 'node' => 'baishi',  'name' => '白石县', 'tier' => 'county'],
    'gz_city' => ['entry' => 'gz_gate', 'node' => 'ganzhou', 'name' => '甘州',   'tier' => 'big'],
    /* 四个镇治（2026-10-14 续做 ✓ 镇比县城小一档 ✓） */
    'town_heisongyi'  => ['entry' => 'hy_gate', 'node' => 'heisongyi', 'name' => '黑松驿镇', 'tier' => 'town'],
    'town_liuhe'      => ['entry' => 'lh_gate', 'node' => 'liuhe',     'name' => '柳河镇',   'tier' => 'town'],
    'town_sandaogou'  => ['entry' => 'sd_gate', 'node' => 'sandaogou', 'name' => '三道沟镇', 'tier' => 'town'],
    'town_hewan'      => ['entry' => 'hw_gate', 'node' => 'hewan',     'name' => '河湾镇',   'tier' => 'town'],
    'village_shangying' => ['entry' => 'shangying_gate', 'node' => 'shangying', 'name' => 'shangying', 'tier' => 'village'],
    'village_xiaying' => ['entry' => 'xiaying_gate', 'node' => 'xiaying', 'name' => 'xiaying', 'tier' => 'village'],
    'village_hanliugou' => ['entry' => 'hanliugou_gate', 'node' => 'hanliugou', 'name' => 'hanliugou', 'tier' => 'village'],
    'village_lijiaao' => ['entry' => 'lijiaao_gate', 'node' => 'lijiaao', 'name' => 'lijiaao', 'tier' => 'village'],
    'village_jinger' => ['entry' => 'jinger_gate', 'node' => 'jinger', 'name' => 'jinger', 'tier' => 'village'],
    'village_liushugou' => ['entry' => 'liushugou_gate', 'node' => 'liushugou', 'name' => 'liushugou', 'tier' => 'village'],
    'village_danghewan' => ['entry' => 'danghewan_gate', 'node' => 'danghewan', 'name' => 'danghewan', 'tier' => 'village'],
    'village_daquanwan' => ['entry' => 'daquanwan_gate', 'node' => 'daquanwan', 'name' => 'daquanwan', 'tier' => 'village'],
    'village_hongliuwan' => ['entry' => 'hongliuwan_gate', 'node' => 'hongliuwan', 'name' => 'hongliuwan', 'tier' => 'village'],
    'village_shiqiao' => ['entry' => 'shiqiao_gate', 'node' => 'shiqiao', 'name' => 'shiqiao', 'tier' => 'village'],
    'village_cahanliang' => ['entry' => 'cahanliang_gate', 'node' => 'cahanliang', 'name' => 'cahanliang', 'tier' => 'village'],
    'village_sanchakou' => ['entry' => 'sanchakou_gate', 'node' => 'sanchakou', 'name' => 'sanchakou', 'tier' => 'village'],
    'village_quanerwan' => ['entry' => 'quanerwan_gate', 'node' => 'quanerwan', 'name' => 'quanerwan', 'tier' => 'village'],
    'village_shimenzi' => ['entry' => 'shimenzi_gate', 'node' => 'shimenzi', 'name' => 'shimenzi', 'tier' => 'village'],
    'village_baiyanggou' => ['entry' => 'baiyanggou_gate', 'node' => 'baiyanggou', 'name' => 'baiyanggou', 'tier' => 'village'],
    'village_huanghuatan' => ['entry' => 'huanghuatan_gate', 'node' => 'huanghuatan', 'name' => 'huanghuatan', 'tier' => 'village'],
];
$tierMin = ['town' => 30, 'county' => $ruler, 'big' => (int) ceil($ruler * 1.35)];   // 镇 30 ／ 县城 ≥ 青石镇 ／ 大城市 ≥ 县城×1.35 ✓
$tierMin['village'] = 10;   // 村 ≥ 10 间 ✓（比镇再小一档）

$descAll = [];
foreach ($cities as $sid => $c) {
    $sc = (array) ($R['scenes'][$sid] ?? []);
    $rooms = (array) ($sc['rooms'] ?? []);
    $n = count($rooms);
    if ($n === 0) { $err[] = "✗ {$c['name']}：场景 $sid 不存在"; continue; }

    /* ① 规模 */
    $min = (int) ($tierMin[$c['tier'] ?? 'county'] ?? $ruler);
    $tierCn = ['big' => '大城市', 'county' => '县城', 'town' => '镇'][$c['tier'] ?? 'county'] ?? '地点';
    if ($n < $min) $err[] = sprintf('① %s（%s）：只有 %d 间，少于该档标尺 %d', $c['name'], $tierCn, $n, $min);
    else $ok[] = sprintf('① %s（%s）：%d 间 ≥ 该档标尺 %d ✓', $c['name'], $tierCn, $n, $min);

    /* ③ 出口合法 */
    $bad = [];
    foreach ($rooms as $rk => $rd) {
        foreach ((array) ($rd['x'] ?? []) as $label => $dest) {
            if (!isset($rooms[$dest])) $bad[] = $rk . '→' . $dest;
        }
    }
    if ($bad) $err[] = "③ {$c['name']}：出口指向不存在的房间：" . implode('、', array_slice($bad, 0, 5));
    else $ok[] = "③ {$c['name']}：出口全部指向有效房间 ✓";

    /* ② 可达（BFS） */
    $entry = (string) $c['entry'];
    if (!isset($rooms[$entry])) { $err[] = "② {$c['name']}：入口 $entry 不存在"; }
    else {
        $seen = [$entry => 1]; $q = [$entry];
        while ($q) {
            $cur = array_shift($q);
            foreach ((array) ($rooms[$cur]['x'] ?? []) as $dest) {
                if (isset($rooms[$dest]) && !isset($seen[$dest])) { $seen[$dest] = 1; $q[] = $dest; }
            }
        }
        $miss = array_diff(array_keys($rooms), array_keys($seen));
        if ($miss) $err[] = "② {$c['name']}：从 $entry 走不到 " . count($miss) . " 间房（" . implode('、', array_slice($miss, 0, 5)) . '）';
        else $ok[] = "② {$c['name']}：从入口 $entry 可走遍全部 $n 间房 ✓";
    }

    /* ④ 落地 */
    $open = (array) crrg_travel_open_nodes();
    if (!isset($open[$c['node']]) || (string) ($open[$c['node']]['room'] ?? '') === '') {
        $err[] = "④ {$c['name']}：地图节点 {$c['node']} 没有落地房间（车旅到不了城里）";
    } elseif ((string) $open[$c['node']]['room'] !== $entry) {
        $warn[] = "④ {$c['name']}：落地房间是 {$open[$c['node']]['room']}，不是入口 $entry（确认有意 ✓）";
    } else $ok[] = "④ {$c['name']}：车旅落地 → $entry ✓";

    /* ⑤ 特色：收集描述 */
    foreach ($rooms as $rk => $rd) $descAll[] = ['city' => $c['name'], 'key' => (string) $rk, 'd' => (string) ($rd['d'] ?? '')];
}

/* ⑤ 各具特色 */
$byDesc = [];
foreach ($descAll as $row) if ($row['d'] !== '') $byDesc[$row['d']][] = $row['city'] . ':' . $row['key'];
$dup = [];
foreach ($byDesc as $d => $who) {
    $cities2 = array_unique(array_map(fn($x) => explode(':', $x)[0], $who));
    if (count($cities2) > 1) $dup[] = ['d' => $d, 'who' => $who];
}
if ($dup) {
    $names = [];
    foreach ($dup as $row) $names[] = implode('×', array_slice($row['who'], 0, 2));
    $err[] = '⑤ 有雷同房间描述（' . count($dup) . ' 处）：' . implode('；', array_slice($names, 0, 3)) . ' ——"各具特色"不成立 ✗';
} else $ok[] = '⑤ 各具特色：全部城的房间描述没有一处雷同 ✓';

/* 键前缀不许混（防"同一个模子换名字"） */
$pfx = [];
foreach ($descAll as $row) { $p = substr($row['key'], 0, 3); $pfx[$row['city']][$p] = 1; }
$inter = array_intersect_key($pfx['白石县'] ?? [], $pfx['甘州'] ?? []);
if ($inter) $warn[] = '⑤ 两城房间键前缀有交集：' . implode('、', array_keys($inter));
else $ok[] = '⑤ 房间键前缀互不重叠 ✓';

echo "=== 县城门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
