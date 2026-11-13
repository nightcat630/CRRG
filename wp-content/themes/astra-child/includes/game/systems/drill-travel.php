<?php
/**
 * 长途车旅与露营系统（2026-09-28）
 *   载具档案：速度（平路/土路）、油耗、油箱、精力消耗、载人
 *   燃油：按升计，基地车库/青石镇/白石县可加油；油桶可带 20 L 备用；跑干要叫救援
 *   损耗：按里程累积（平路 +2/100km、土路 +5/100km），高损耗降速增耗、可能抛锚；可保养/大修
 *   行程：以游戏时间推进，**离线也算**（与睡眠同理，属被动等待）；到点自动抵达并结算精力/饥渴
 *   露营：野外节点可扎营，效果取决于帐篷/睡袋/炉子；生火可烧水热饭，但夜里可能有人来
 */
if (!defined('ABSPATH')) exit;

/* ================= 载具档案 ================= */
function crrg_travel_profiles() {
    return [
        '徒步'     => ['sp_flat' => 5,  'sp_dirt' => 4,  'sp_hw' => 0,   'fuel' => 0,    'tank' => 0,  'energy_km' => 3.0, 'carry' => 0,
                       'd' => '走到哪算哪。最慢，也最不挑路。'],
        '自行车'   => ['sp_flat' => 12, 'sp_dirt' => 8,  'sp_hw' => 0,   'fuel' => 0,    'tank' => 0,  'energy_km' => 1.2, 'carry' => 0,
                       'd' => '平路还行，上坡推着走。'],
        '越野摩托' => ['sp_flat' => 45, 'sp_dirt' => 28, 'sp_hw' => 95,  'fuel' => 0.05, 'tank' => 12, 'energy_km' => 0.3, 'carry' => 0,
                       'd' => '油耗低，山路也能上。'],
        '越野车'   => ['sp_flat' => 60, 'sp_dirt' => 38, 'sp_hw' => 115, 'fuel' => 0.11, 'tank' => 65, 'energy_km' => 0.2, 'carry' => 2,
                       'd' => '最快最稳，油耗高，能捎人。'],
    ];
}
function crrg_travel_profile($mode) { return crrg_travel_profiles()[$mode] ?? crrg_travel_profiles()['徒步']; }
/** 车主当前载具（含徒步） */
function crrg_travel_mode(&$pc) {
    $v = (string) ($pc['vehicle'] ?? '');
    return isset(crrg_travel_profiles()[$v]) ? $v : '徒步';
}
/** 车辆状态：油量、损耗、里程 */
function crrg_travel_veh(&$pc, $mode = null) {
    $mode = $mode ?: crrg_travel_mode($pc);
    if (!isset($pc['vehicles']) || !is_array($pc['vehicles'])) $pc['vehicles'] = [];
    if (!isset($pc['vehicles'][$mode]) || !is_array($pc['vehicles'][$mode])) {
        $p = crrg_travel_profile($mode);
        $pc['vehicles'][$mode] = ['fuel' => (float) $p['tank'] * 0.6, 'wear' => 0, 'odo' => 0];   // 初始六成油
    }
    return $pc['vehicles'][$mode];
}
function crrg_travel_save_veh(&$pc, $mode, $st) { crrg_travel_veh($pc, $mode); $pc['vehicles'][$mode] = $st; }

/* ================= 油耗与损耗 ================= */
function crrg_travel_fuel_price() { return 8; }                    // 元／升
/** 路况：干线=平路，乡道/山道/林道=土路 */
function crrg_travel_road_class($via = '', $road_km = 0, $km = 0) {
    if (mb_strpos($via, '高速') !== false) return 'hw';
    if (mb_strpos($via, '三级公路') !== false || mb_strpos($via, '省道') !== false || mb_strpos($via, '国道') !== false) return 'flat';
    if (mb_strpos($via, '乡道') !== false || mb_strpos($via, '山道') !== false || mb_strpos($via, '林道') !== false) return 'dirt';
    return ($road_km > $km * 1.4) ? 'dirt' : 'flat';
}
/** 一次行程的时间／油耗／损耗／精力 */
function crrg_travel_estimate($mode, $km, $cls, &$pc = null) {
    $p = crrg_travel_profile($mode);
    if ($cls === 'hw') {
        $sp = (int) ($p['sp_hw'] ?? 0);
    } else {
        $sp = ($cls === 'dirt') ? $p['sp_dirt'] : $p['sp_flat'];
    }
    $hours = $sp > 0 ? $km / $sp : 0;
    /* 损耗影响：≥60 降速 20%、油耗 +10%；≥85 再降一档 */
    $wear = 0;
    if ($pc) { $v = crrg_travel_veh($pc, $mode); $wear = (int) $v['wear']; }
    $spMul = 1.0;
    if ($wear >= 60) $spMul -= 0.2;
    if ($wear >= 85) $spMul -= 0.15;
    $hours = $hours / max(0.3, $spMul);
    $fuelMul = ($cls === 'hw') ? 0.85 : (($wear >= 60) ? 1.1 : 1);
    $fuel = (float) $p['fuel'] * $km * $fuelMul;
    $wear_add = (($cls === 'dirt') ? 5 : (($cls === 'hw') ? 1 : 2)) * $km / 100;
    $energy = (float) $p['energy_km'] * $km;
    return ['hours' => $hours, 'fuel' => $fuel, 'wear' => $wear_add, 'energy' => $energy, 'cls' => $cls, 'km' => $km];
}
/** 抛锚概率（每次行程）：以车况为主；**路段状态「部分通」再抬一档** ✓
 *  2026-10-09：开放判定本来就是读 `st` 派生的（`crrg_travel_open_nodes()`）✓ ——
 *  这里把 `st` 的**第二层含义**（路面时好时坏）也接上，避免 `st` 只当"开／不开"的开关 ✓ */
function crrg_travel_break_chance($pc, $mode, $st = '') {
    $w = (int) crrg_travel_veh($pc, $mode)['wear'];
    $c = 0.0;
    if ($w >= 85) $c = 0.15;
    elseif ($w >= 60) $c = 0.04;
    if ($st === '部分通') $c += 0.06;
    return min(0.25, $c);
}

/* ================= 行程 ================= */
/** 大地图节点里可作为长途目的地的（含基地） */
function crrg_travel_destinations() {
    $out = ['base' => ['n' => '青山基地', 'km' => 0, 'road_km' => 0, 'via' => '—', 'st' => '已通']];
    foreach (crrg_worldmap_nodes() as $id => $n) $out[$id] = $n;
    return $out;
}
/** 该目的地对这张卡来说走哪条路：有高速且是机动车 → 高速 */
function crrg_travel_route_of($pc, $dest) {
    $ds = crrg_travel_destinations();
    if (!isset($ds[$dest])) return null;
    $mode = crrg_travel_mode($pc);
    $motor = !in_array($mode, ['徒步', '自行车'], true);
    $node = $ds[$dest];
    /* 节点自身就在高速上（服务区等） */
    if (mb_strpos($node['via'] ?? '', '高速') !== false) {
        return ['cls' => 'hw', 'via' => $node['via'], 'km' => (int) $node['road_km']];
    }
    /* 机动车可走高速：查有没有一条高速经过这个目的地 */
    if ($motor) {
        foreach (crrg_worldmap_roads() as $r) {
            if (($r['lv'] ?? '') !== '高速') continue;
            if (!in_array($dest, (array) $r['path'], true)) continue;
            if (!in_array($r['st'], ['已通', '部分通'], true)) continue;
            /* 只把"路况"换成高速（更快、更省油、更省车）；里程仍用该节点自己的里程 */
            return ['cls' => 'hw', 'via' => $r['name'], 'km' => (int) $node['road_km']];
        }
    }
    $km = (int) $node['road_km'];
    $cls = crrg_travel_road_class($node['via'] ?? '', $km, (int) $node['km']);
    return ['cls' => $cls, 'via' => $node['via'], 'km' => $km];
}
/** 出发前检查（返回 [可否, 说明行]） */
function crrg_travel_precheck(&$pc, $dest) {
    $ds = crrg_travel_destinations();
    if (!isset($ds[$dest])) return [false, ['地图上没有这个目的地。']];
    $mode = crrg_travel_mode($pc);
    $p = crrg_travel_profile($mode);
    $rt = crrg_travel_route_of($pc, $dest);
    $km = (int) ($rt['km'] ?? $ds[$dest]['road_km'] ?? 0);
    $cls = $rt['cls'] ?? 'flat';
    $est = crrg_travel_estimate($mode, $km, $cls, $pc);
    $lines = [];
    if ($mode !== '徒步' && $p['fuel'] > 0) {
        $v = crrg_travel_veh($pc, $mode);
        if ((float) $v['fuel'] < $est['fuel']) {
            return [false, [sprintf('油不够：这趟要 %.1f 升，油箱里只有 %.1f 升。', $est['fuel'], (float) $v['fuel']),
                            '（先加油，或带一桶备用油。）']];
        }
    }
    if (!empty($pc['travel'])) return [false, ['你还在路上。']];
    if ($cls === 'hw' && in_array($mode, ['徒步', '自行车'], true)) {
        return [false, ['高速不让上——徒步和自行车都进不去。',
                        '（要么搭车、要么换摩托／越野车。）']];
    }
    return [true, $lines];
}
/** 发起行程 */
function crrg_travel_start(&$pc, $dest) {
    [$ok, $msg] = crrg_travel_precheck($pc, $dest);
    if (!$ok) return $msg;
    $ds = crrg_travel_destinations(); $n = $ds[$dest];
    $mode = crrg_travel_mode($pc);
    $rt = crrg_travel_route_of($pc, $dest);
    $km = (int) ($rt['km'] ?? $n['road_km'] ?? 0);
    $cls = $rt['cls'] ?? 'flat';
    $est = crrg_travel_estimate($mode, $km, $cls, $pc);
    $now = crrg_vitals_now();
    /* 扣油与损耗（出发即算，抛锚在途中判定） */
    if ($mode !== '徒步' && crrg_travel_profile($mode)['fuel'] > 0) {
        $v = crrg_travel_veh($pc, $mode);
        $v['fuel'] = max(0, (float) $v['fuel'] - $est['fuel']);
        $v['wear'] = min(100, (float) $v['wear'] + $est['wear']);
        $v['odo']  = (float) $v['odo'] + $km;
        crrg_travel_save_veh($pc, $mode, $v);
    }
    $break = (mt_rand(1, 100) / 100) < crrg_travel_break_chance($pc, $mode, (string) ($n['st'] ?? ''));
    $extra = $break ? $est['hours'] * 0.35 + 2 : 0;             // 抛锚：多花时间（含修车）
    $pc['travel'] = ['dest' => $dest, 'name' => $n['n'], 'mode' => $mode, 'km' => $km, 'cls' => $cls,
                   'depart' => $now, 'arrive' => $now + (int) round(($est['hours'] + $extra) * 3600),
                   'energy' => $est['energy'], 'break' => $break ? 1 : 0];
    if (function_exists('crrg_travel_event_roll')) {                       // 10% 触发在途事件
        $ev = crrg_travel_event_roll($pc);
        if ($ev) $pc['travel']['ev'] = $ev;
    }
    $clsCn = ($cls === 'hw') ? '高速' : (($cls === 'dirt') ? '土路' : '平路');
    $out = [sprintf('〔出发〕%s → %s ｜ %s ｜ %d 公里（%s%s）', '当前驻地', $n['n'], $mode, $km, $clsCn,
                    $cls === 'hw' ? '：' . ($rt['via'] ?? '') : ''),
            sprintf('（预计 %s 游戏小时，现实约 %d 分钟；路上离线也算，回头会自动抵达。）',
                round($est['hours'] + $extra, 1), (int) ceil(($est['hours'] + $extra) * 3600 / 48 / 60))];
    if (($n['st'] ?? '') === '部分通') $out[] = '（这段路只是**部分通**：路面时好时坏，抛锚的风险比平常高。）';
    if ($mode !== '徒步' && crrg_travel_profile($mode)['fuel'] > 0) {
        $v = crrg_travel_veh($pc, $mode);
        $out[] = sprintf('（油 %.1f 升 ｜ 车况损耗 %.0f%% ｜ 总里程 %.0f km）', (float) $v['fuel'], (float) $v['wear'], (float) $v['odo']);
    }
    return $out;
}
/** 途中结算（每次行动调用；到点即抵达） */
function crrg_travel_sync(&$pc, &$s) {
    if (empty($pc['travel'])) return [];
    $t = $pc['travel'];
    $now = crrg_vitals_now();
    $out = [];
    if ($now >= (int) $t['arrive']) {
        $pc['travel'] = [];
        /* 旅途消耗：精力（按公里）/饥渴（按小时，离线也算） */
        $hours = max(0, ((int) $t['arrive'] - (int) $t['depart']) / 3600.0);
        $v = &$pc['vitals'];
        $v['energy'] = max(0, $v['energy'] - (float) $t['energy']);
        $v['food']   = max(0, $v['food'] - $hours * crrg_vrate('food_per_h'));
        $v['water']  = max(0, $v['water'] - $hours * crrg_vrate('water_per_h'));
        $v['t'] = $now;
        $v['seen_real'] = time();
        $out[] = sprintf('〔抵达〕%s。', $t['name']);
        /* 2026-10-14（P1.9）：行程带任务上下文 → 到达时挂"待落账"，由引擎下次动作收 ✓ */
        if (!empty($t['ctx']['task'])) {
            $pend = (array) get_option('crrg_travel_pending', []);
            $pend[] = ['task' => (string) $t['ctx']['task'], 'path' => (string) ($t['ctx']['path'] ?? ''),
                       'uid' => (int) ($t['ctx']['uid'] ?? 0), 'at' => time()];
            update_option('crrg_travel_pending', array_slice($pend, -20), false);
        }
        if (!empty($t['break'])) {
            $out[] = '途中抛锚了——你花了两小时修好，手上全是机油。';
            $out[] = '（车况建议尽快保养，或先修一次。）';
        }
        $out[] = sprintf('（路上走了 %s 游戏小时；精力 −%d，饱食 −%d，口渴 −%d。）',
            round($hours, 1), (int) $t['energy'], (int) floor($hours * 3.4), (int) floor($hours * 4.2));
        /* 抵达：切到对应场景，并把玩家放到该节点的入口房间 */
        /* 2026-09-29 修：以"开放节点表"为准（qingshi → tw_gate／town），
           避免另一套地图机制把玩家扔进没有出口的落地房间 */
        $openNodes = crrg_travel_open_nodes();
        $room = $openNodes[$t['dest']]['room'] ?? crrg_travel_node_scene($t['dest']);
        $sceneWanted = $openNodes[$t['dest']]['scene'] ?? '';
        if ($t['dest'] === 'base') {
            if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
            $s['room'] = $room ?: 'b1_cmd_hall';
            if (function_exists('crrg_drill_sync_room')) crrg_drill_sync_room($s, $pc);
        } elseif ($room !== '') {
            $sc = $sceneWanted !== '' ? $sceneWanted : 'region';
            if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene($sc);
            if (!isset(crrg_drill_rooms()[$room])) {            // 落地房间不存在 → 落回该场景入口
                $first = array_key_first((array) crrg_drill_rooms());
                $room = $first ?: $room;
            }
            $s['room'] = $room;
            if (!empty($pc['travel'])) { $pc['travel'] = []; }   // 清掉行程，避免重复结算
        }
        return $out;
    }
    $left = ((int) $t['arrive'] - $now) / 3600.0;
    $out = [sprintf('（在路上：%s，还需 %.1f 游戏小时。）', $t['name'], $left)];
    if (!empty($t['ev']) && function_exists('crrg_travel_event_show')) $out = array_merge($out, crrg_travel_event_show($t['ev']));
    return $out;
}
/** 长途状态一行 */
function crrg_travel_status_lines(&$pc) {
    $out = [];
    if (!empty($pc['travel'])) {
        $t = $pc['travel'];
        $out[] = sprintf('〔在途〕去 %s ｜ %s ｜ 还需 %.1f 游戏小时', $t['name'], $t['mode'],
            max(0, ((int) $t['arrive'] - crrg_vitals_now()) / 3600.0));
    }
    $mode = crrg_travel_mode($pc);
    $p = crrg_travel_profile($mode);
    $out[] = sprintf('〔载具〕%s——%s', $mode, $p['d']);
    if ($mode !== '徒步') {
        $v = crrg_travel_veh($pc, $mode);
        $out[] = sprintf('　油 %.1f／%d 升 ｜ 损耗 %.0f%% ｜ 里程 %.0f km ｜ 百公里油耗 %.1f 升',
            (float) $v['fuel'], (int) $p['tank'], (float) $v['wear'], (float) $v['odo'], $p['fuel'] * 100);
        if ((float) $v['wear'] >= 60) $out[] = '　（车况差了：速度下降、油耗上升，跑烂路有抛锚风险。）';
    }
    return $out;
}

/* ================= 加油与保养 ================= */
function crrg_travel_fuel_spots() {
    return ['garage_maint' => '基地车库', 'tw_gas' => '青石镇加油站', 'baishi_node' => '白石县城'];
}
function crrg_travel_is_service_area($room) { return strpos((string) $room, 'r_sa_') === 0; }
function crrg_travel_refuel(&$pc, $room, $liters = 0) {
    $mode = crrg_travel_mode($pc);
    $p = crrg_travel_profile($mode);
    if ($p['fuel'] <= 0) return ['这车不烧油。'];
    $svc = crrg_travel_is_service_area($room);
    if (!$svc && !isset(crrg_travel_fuel_spots()[$room]) && !in_array($room, ['garage_maint', 'tw_gas'], true)) return ['这里没有加油的地方。'];
    $v = crrg_travel_veh($pc, $mode);
    $need = max(0, (float) $p['tank'] - (float) $v['fuel']);
    if ($need <= 0.1) return ['油箱是满的。'];
    $want = ($liters > 0) ? min($liters, $need) : $need;
    $price = (int) ceil($want * crrg_travel_fuel_price() * ($svc ? 1.1 : 1));   // 服务区油价贵一成
    if ((int) $pc['money'] < $price) return [sprintf('加 %.1f 升要 %d 元（你在 %d 元）。', $want, $price, (int) $pc['money'])];
    $pc['money'] = (int) $pc['money'] - $price;
    $v['fuel'] = (float) $v['fuel'] + $want;
    crrg_travel_save_veh($pc, $mode, $v);
    return [sprintf('加了 %.1f 升（%d 元）。油箱 %.1f／%d 升。', $want, $price, (float) $v['fuel'], (int) $p['tank'])];
}
/** 保养 / 大修 */
function crrg_travel_service(&$pc, $room, $big = false) {
    $mode = crrg_travel_mode($pc);
    $p = crrg_travel_profile($mode);
    if ($p['fuel'] <= 0) return ['自行车不需要这个。'];
    if (!in_array($room, ['garage_maint', 'tw_repair'], true)) return ['保养要到基地车库或镇上修车铺。'];
    $cost = $big ? 250 : 80;
    if ((int) $pc['money'] < $cost) return [sprintf('%s要 %d 元（你在 %d 元）。', $big ? '大修' : '保养', $cost, (int) $pc['money'])];
    $v = crrg_travel_veh($pc, $mode);
    $pc['money'] = (int) $pc['money'] - $cost;
    $before = (float) $v['wear'];
    $v['wear'] = $big ? 0 : max(0, $before - 40);
    crrg_travel_save_veh($pc, $mode, $v);
    return [sprintf('%s：损耗 %.0f%% → %.0f%%。', $big ? '大修' : '保养', $before, (float) $v['wear'])];
}

/** 能落地的大地图节点（已通／部分通即可去；规划中暂不开放） */
function crrg_travel_open_nodes() {
    if (function_exists('crrg_travel_ensure_region')) crrg_travel_ensure_region();
    $out = ['qingshi' => ['room' => 'tw_gate', 'scene' => 'town']];
    foreach (crrg_worldmap_nodes() as $id => $n) {
        if (isset($out[$id])) continue;      // 2026-09-29 修：青石镇已显式指向镇上，别被大地图节点覆盖成"街口"
        if (in_array($n['st'], ['已通', '部分通'], true)) $out[$id] = ['room' => 'r_' . $id, 'scene' => 'region'];
    }
    /* 2026-10-14：**县城落地映射**（白石县/甘州 → 各自的定制场景 ✓） */
    if (function_exists('crrg_game_travel_landing')) {
        foreach ((array) crrg_game_travel_landing() as $__id => $__row) $out[$__id] = $__row;
    }
    /* 2026-10-14：**十六个村的落地表** ✓ */
    if (function_exists('crrg_game_travel_landing_villages')) {
        foreach ((array) crrg_game_travel_landing_villages() as $__id => $__row) $out[$__id] = $__row;
    }
    /* 2026-10-14：**四个镇的落地表** ✓ */
    if (function_exists('crrg_game_travel_landing_towns')) {
        foreach ((array) crrg_game_travel_landing_towns() as $__id => $__row) $out[$__id] = $__row;
    }
    /* 2026-10-14：**周边地点落地映射**（显式指定房间/场景 ✓ 优先于自动生成的 r_<节点>） */
    if (function_exists('crrg_game_travel_open_extra')) {
        foreach ((array) crrg_game_travel_open_extra() as $__id => $__row) {
            if (isset($out[$__id])) $out[$__id] = $__row;    /* 已开：改用定制房间 ✓ */
        }
    }
    return $out;
}
function crrg_travel_node_scene($id) {
    if (function_exists('crrg_travel_ensure_region')) crrg_travel_ensure_region();
    if ($id === 'base') return 'b1_cmd_hall';
    $o = crrg_travel_open_nodes();
    return $o[$id]['room'] ?? '';
}
function crrg_travel_node_open($id) { return $id === 'base' || isset(crrg_travel_open_nodes()[$id]); }
/** 动态登记区域场景：每个可去节点一间房（村口／镇口／山脚） */
function crrg_travel_ensure_region() {
    static $done = false;
    if ($done) return;
    if (!function_exists('crrg_drill_scene_extend')) return;   // 加载顺序未到，下次再试
    $done = true;
    crrg_travel_register_region();
}
function crrg_travel_register_region() {
    if (!function_exists('crrg_drill_scene_extend')) return;
    $rooms = []; $pos = []; $ents = []; $i = 0;
    foreach (crrg_worldmap_nodes() as $id => $n) {
        if (!in_array($n['st'], ['已通', '部分通'], true)) continue;
        $i++;
        $kind = $n['t'];
        $roomKey = 'r_' . $id;
        $desc = $n['d'];
        if ($kind === '服务区')    $desc .= ' 高速旁的加油站、便利店和停车位，夜里也亮着灯。';
        elseif ($kind === '山区')  $desc .= ' 山脚下有块被压平的碎石地，能停车，也能扎营。';
        elseif ($kind === '村')    $desc .= ' 村口一棵老树，树下有石墩；路边是土坯院墙。';
        elseif ($kind === '镇')    $desc .= ' 街口有几间铺子，修车的、卖杂货的、还有一处能加油。';
        elseif ($kind === '县城')  $desc .= ' 城边一条主街，路灯亮着，来往的车比镇上多。';
        else                       $desc .= ' 城郊的检查站与加油站，路牌上写着往各个方向的距离。';
        $rooms[$roomKey] = ['t' => $n['n'] . ($kind === '山区' ? '（山脚）' : ($kind === '村' ? '·村口' : ($kind === '镇' ? '·街口' : ($kind === '服务区' ? '·高速' : '')))),
            'f' => '外区', 'c' => 1, 'd' => $desc, 'x' => []];
        $pos[$roomKey] = [20 + ($i % 8) * 18, 20 + intdiv($i, 8) * 18];
        $ents[$roomKey] = [['n' => $kind === '山区' ? '进山小路' : '路边的车辙', 'k' => 'item', 'c' => '查看 ' . ($kind === '山区' ? '小路' : '车辙')]];
        if ($kind === '镇' || $kind === '县城' || $kind === '大城市') $ents[$roomKey][] = ['n' => '街口的杂货铺', 'k' => 'item', 'c' => '查看 杂货铺'];
    }
    crrg_drill_scene_extend('region', $rooms, $pos, $ents);
}
crrg_travel_ensure_region();

/** 当前房间 → 大地图节点（供露营判定） */
function crrg_travel_current_node(&$s) {
    $room = $s['room'] ?? '';
    if ($room === 'base' || strpos($room, 'b') === 0 || strpos($room, 'garage') === 0) return 'base';
    if (strpos($room, 'tw_') === 0) return 'qingshi';
    if (strpos($room, 'w_') === 0) {
        $map = ['w_ridge' => 'heisongling', 'w_hollow' => 'bingouliang', 'w_stand' => 'heisongling', 'w_trail' => 'yingzuiya'];
        return $map[$room] ?? 'heisongling';
    }
    return 'base';
}

/* ================= 座位与在途行为 ================= */
/** 座位：driver（开车／骑车／走路的本人）｜ passenger（搭车的） */
function crrg_travel_seat(&$pc) { return ($pc['travel']['seat'] ?? 'driver'); }
function crrg_travel_in_transit(&$pc) { return !empty($pc['travel']); }
/** 搭车：跟随某位车主（需其车有空位） */
function crrg_travel_ride_with(&$pc, $uid, $host_uid) {
    if (!empty($pc['travel'])) return ['你已经在路上了。'];
    $hp = crrg_drill_pc($host_uid);
    if (empty($hp['travel'])) return ['（那个人不在路上。）'];
    $mode = $hp['travel']['mode'];
    $p = crrg_travel_profile($mode);
    $pax = (int) ($hp['travel']['pax'] ?? 0);
    if ($p['carry'] <= $pax) return [sprintf('（%s 上没位置了。）', $mode)];
    /* 搭上：同一目的地与抵达时间 */
    $pc['travel'] = ['dest' => $hp['travel']['dest'], 'name' => $hp['travel']['name'], 'mode' => $mode,
                   'km' => $hp['travel']['km'], 'cls' => $hp['travel']['cls'], 'depart' => $hp['travel']['depart'],
                   'arrive' => $hp['travel']['arrive'], 'energy' => 0, 'break' => 0,
                   'seat' => 'passenger', 'host' => $host_uid, 'pax' => 0,
                   'ev' => ($hp['travel']['ev'] ?? null)];
    $hp['travel']['pax'] = $pax + 1;
    crrg_drill_pc_save($host_uid, $hp);
    return [sprintf('你搭上%s的副驾，关上车门。', $mode),
            sprintf('（同去 %s，预计同一时间抵达；你在车上可以吃东西、喝水、睡觉。）', $hp['travel']['name'])];
}
/** 在途可做的事（返回 null 表示这张卡在途不能做这件事） */
function crrg_travel_transit_allow($pc, $cmd) {
    $seat = crrg_travel_seat($pc);
    if (preg_match('/^(说|喊|聊|唱|emote|动作|点头|摇头|叹气)/u', $cmd)) return true;         // 全体都能说话
    if (preg_match('/^(吃|喝)/u', $cmd)) return true;                                          // 司机也能吃喝
    if (preg_match('/^(睡|睡觉|打盹|眯一会)/u', $cmd)) {
        return $seat === 'passenger' ? true : 'driver';                                        // 乘客能睡
    }
    if (preg_match('/^(状态|身体|车况|载具|查看|看)/u', $cmd)) return true;
    return false;
}
/** 乘客在途睡觉：到点按睡眠结算 */
function crrg_travel_passenger_sleep(&$pc, $hours = 0) {
    if (!crrg_travel_in_transit($pc)) return ['你现在不在路上。'];
    if (crrg_travel_seat($pc) !== 'passenger') return ['你在开车——停车才能睡。'];
    $now = crrg_vitals_now();
    $pc['travel']['sleeping'] = 1;
    $pc['vitals']['rest_until'] = (int) $pc['travel']['arrive'];
    $pc['travel']['energy'] = -9.0 * max(0, ((int) $pc['travel']['arrive'] - $now) / 3600.0);       // 睡着的乘客：精力按睡眠增长
    return ['你靠在椅背上睡着了。', '（睡到抵达为止——到地方自然醒。）'];
}

/** 服务区：接热水 / 便利店 / 打盹 */
function crrg_travel_service_area(&$pc, $room, $what) {
    if (!crrg_travel_is_service_area($room)) return ['这里不是服务区。'];
    $v = &$pc['vitals'];
    switch ($what) {
        case 'hotwater':
            $v['water'] = min(100, (float) $v['water'] + 20);
            $v['energy'] = min(100, (float) $v['energy'] + 2);
            return ['你在热水机上接了一壶，又灌了灌自己的水壶。', sprintf('（口渴 +20 ／ 精力 +2；现有 口渴 %d。）', (int) $v['water'])];
        case 'shop_water':
            if ((int) $pc['money'] < 5) return ['钱不够（矿泉水 5 元）。'];
            $pc['money'] = (int) $pc['money'] - 5;
            crrg_drill_bag_add_n($pc, 'drink_mineral', 1);
            return ['买了一瓶矿泉水（5 元）。'];
        case 'shop_coffee':
            if ((int) $pc['money'] < 15) return ['钱不够（罐装咖啡 15 元）。'];
            $pc['money'] = (int) $pc['money'] - 15;
            crrg_drill_bag_add_n($pc, 'drink_coffee', 1);
            return ['买了一罐咖啡（15 元）。'];
        case 'shop_ration':
            if ((int) $pc['money'] < 38) return ['钱不够（干粮 38 元）。'];
            $pc['money'] = (int) $pc['money'] - 38;
            crrg_drill_bag_add_n($pc, 'food_ration', 1);
            return ['买了一包压缩干粮（38 元）。'];
        case 'nap':
            if (crrg_travel_in_transit($pc)) return ['（在路上，先到地方再说。）'];
            $hours = 1;
            $v['energy'] = min(100, (float) $v['energy'] + crrg_vrate('service_area_nap') * $hours);
            $v['food']   = max(0, (float) $v['food'] - $hours * crrg_vrate('food_sleep_per_h'));
            $v['water']  = max(0, (float) $v['water'] - $hours * crrg_vrate('water_sleep_per_h'));
            return ['你把座椅放倒，在车里眯了一个小时。',
                    sprintf('（精力 +%d → %d；车上睡不解力竭。）', crrg_vrate('service_area_nap'), (int) $v['energy'])];
    }
    return ['这里没有这个服务。'];
}

/* ================= 露营 ================= */
function crrg_travel_camp_spots() {
    $spots = [];
    foreach (crrg_worldmap_nodes() as $id => $n) {
        if (in_array($n['t'], ['山区'], true)) $spots[$id] = $n['n'];
        elseif (in_array($n['t'], ['村'], true) && (int) $n['km'] >= 20) $spots[$id] = $n['n'] . '外的空地';
    }
    return $spots;
}
/** 扎营：需帐篷；睡袋/炉子提升效果 */
function crrg_travel_camp(&$pc, $node, $hours = 6) {
    $spots = crrg_travel_camp_spots();
    $inWild = strpos((string) ($node), 'w_') === 0 || strpos((string) ($node), 'heisong') === 0
           || strpos((string) ($node), 'yingzui') === 0 || strpos((string) ($node), 'bingou') === 0
           || strpos((string) ($node), 'dabanshan') === 0 || strpos((string) ($node), 'yeniu') === 0;
    if (!isset($spots[$node]) && !$inWild) return ['这里不适合扎营——去山里或野外的空地。'];
    if (crrg_drill_bag_count($pc, 'tool_tent') <= 0) return ['没有帐篷。露天地躺一晚，不如不睡。'];
    $hasBag   = crrg_drill_bag_count($pc, 'tool_sleepbag') > 0;
    $hasStove = crrg_drill_bag_count($pc, 'tool_stove') > 0;
    $out = [sprintf('你在%s扎了营。', $spots[$node])];
    /* 生火（有打火机/火柴） */
    $fire = (crrg_drill_bag_count($pc, 'tool_lighter') > 0 || crrg_drill_bag_count($pc, 'smoke_matches') > 0);
    if ($fire) {
        $out[] = '你捡了点干枝生起火。';
        if ($hasStove) {
            $out[] = '（炉子上烧了热水，顺手热了口吃的。）';
            if (function_exists('crrg_vitals_consume')) {
                $pc['vitals']['water'] = min(100, (float) $pc['vitals']['water'] + 12);
                $pc['vitals']['food']  = min(100, (float) $pc['vitals']['food'] + 10);
            }
        }
    } else {
        $out[] = '（没有火：这一晚只能喝凉水、啃冷的。）';
    }
    /* 睡眠质量 */
    $perH = crrg_vrate('energy_camp_per_h');
    if ($hasBag) $perH += crrg_vrate('camp_bag_bonus');
    if ($hasStove && $fire) $perH += crrg_vrate('camp_stove_bonus');
    if (!$fire) $perH -= crrg_vrate('camp_nofire_pen');
    $gain = (int) round($perH * $hours);
    $pc['vitals']['energy'] = min(100, (float) $pc['vitals']['energy'] + $gain);
    $pc['vitals']['food']   = max(0, (float) $pc['vitals']['food'] - $hours * crrg_vrate('food_sleep_per_h'));
    $pc['vitals']['water']  = max(0, (float) $pc['vitals']['water'] - $hours * crrg_vrate('water_sleep_per_h'));
    $out[] = sprintf('（睡 %d 游戏小时：精力 +%d ／ 饱食 −%d ／ 口渴 −%d；露营不如床上，但比赶夜路强。）',
        $hours, $gain, (int) round($hours * 1.7), (int) round($hours * 2.1));
    /* 夜里的事件 */
    $roll = mt_rand(1, 100);
    if ($roll <= 12) {
        $out[] = '半夜有东西从营地外绕过去，踩断了两根枯枝。你没敢开灯。';
        if (function_exists('crrg_coc_san_check')) $out[] = crrg_coc_san_check($pc, '0/1', '野外的夜');
    } elseif ($roll <= 22) {
        $out[] = '后半夜起风，帐篷杆响了一整宿。';
        $pc['vitals']['energy'] = max(0, (float) $pc['vitals']['energy'] - 5);
    } elseif ($roll <= 28) {
        $out[] = '有辆车在远处停了十几分钟，又开走了。';
    } else {
        $out[] = '一夜无事。天亮时露水很重。';
    }
    if (empty($pc['vitals']['fatigue']) && $hours >= 6) { /* 露营不算睡够，力竭不清除 */ }
    return $out;
}
