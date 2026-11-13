<?php
/**
 * CRRG 演习系统 · drill.php 拆分块[view]（2026-09-29 从 drill.php 原样搬出，仅位置变化）
 * 内容：见下方各函数。搬出后由 drill.php 在顶部 require，保证声明顺序与拆分前一致。
 */
if (!defined('ABSPATH')) exit;

function crrg_drill_room_lines($s, $ctx, $pc = null) {
    $rooms = crrg_drill_rooms();
    $rm = $rooms[$s['room']];
    $out = ['〔' . $rm['t'] . '〕' . ($rm['f'] !== '—' ? '　' . $rm['f'] : ''), $rm['d']];
    if (function_exists('crrg_world_line') && crrg_world_outdoor($s['room'])) {
        $w = crrg_world_weather(crrg_drill_cur_scene());
        $c = crrg_world_clock();
        $out[] = sprintf('〔%s · %s〕%s', $c['phase'], $w['n'], $w['d']);
    }
    if (function_exists('crrg_drill_room_extra')) {          // 模组按账号旗标改写现场
        foreach ((array) crrg_drill_room_extra($s, $pc, $s['room']) as $l) $out[] = $l;
    }
    if (!empty($rm['x'])) {
        $keys = array_keys($rm['x']);
        $keys = array_values(array_filter($keys, fn($k) => $k !== '刷卡'));
        if ($keys) $out[] = '可往：' . implode('、', $keys);
    }
    if (($rm['rule'] ?? '') === 'two_key') $out[] = '（双人双锁：演习中需两名 B 级以上在场，单人无法开启。）';
    /* 有水的地方提示一句（2026-09-29）：接水点扩到 28 处，但玩家得看得见 */
    if (function_exists('crrg_vitals_water_spot')) {
        $ws = crrg_vitals_water_spot($s['room'] ?? '');
        if ($ws !== '') $out[] = '（' . $ws . '——可以「灌水」。）';
    }

    /* 〔现场〕把"可察现象"写出来（2026-09-29）：面板摆调查点，场景里就得看得见来由 */
    if (function_exists('crrg_seal_spot_text') && function_exists('crrg_seal_paths_full')) {
        $scopeS = [];
        foreach ([$pc['mission'] ?? '', $pc['mission2']['id'] ?? ''] as $mid5) {
            $mm5 = crrg_drill_mission_any($mid5);
            foreach ((array) ($mm5['steps'] ?? []) as $st5) if (!empty($st5['flag'])) $scopeS[$st5['flag']] = 1;
        }
        $spots5 = [];
        foreach (crrg_seal_paths_full() as $nd5 => $ps5) {
            if (crrg_seal_node_room($nd5) !== ($s['room'] ?? '')) continue;
            if (crrg_seal_path_done($s, $nd5)) continue;
            $fl5 = ''; foreach ($ps5 as $p5) { $fl5 = (string) ($p5['flag'] ?? ''); break; }
            if ($scopeS && $fl5 !== '' && !isset($scopeS[$fl5])) continue;
            $sp5 = crrg_seal_spot_text($nd5);
            if ($sp5 !== '') $spots5[] = sprintf('　· %s（可「调查 %s」）', $sp5, $nd5);
        }
        if ($spots5) { $out[] = '〔现场〕'; $out = array_merge($out, array_slice($spots5, 0, 6)); }
    }

        /* 在场的人（2026-09-29 统一：基地作息 + 镇上固定） */
    if (function_exists('crrg_npc_here_line')) {
        $hl = crrg_npc_here_line($s['room'] ?? '', $s);
        if ($hl !== '') $out[] = $hl;
    }
    /* 负四层缓冲区：三道气密闸的走法要说清楚（否则玩家以为卡死） */
    if (($s['room'] ?? '') === 'b4_buffer') {
        $g = (int) ($s['gates'] ?? 0);
        $out[] = sprintf('〔闸门〕入口／中部／出口三道气密闸：**连续「刷卡」三次**即全开，出口通观察室（继续走可到收容区主走廊）。进度 %d/3。', $g);
        $out[] = ($g < 3) ? '（命令：「刷卡」。须着 T3 防护服。）'
                          : '（闸都开着——再「刷卡」一次就穿过去，出口是观察室。）';
    }
    /* 高风险区提示（2026-09-29：新手不该盲进） */
    $R = function_exists('crrg_room_risk') ? crrg_room_risk($s['room']) : null;
    if (!empty($rm['risk'])) $R = ['note' => $rm['risk'], 'gear' => $rm['risk_gear'] ?? ''];
    if ($R) {
        $need = $R['gear'] ?? '';
        $worn = false;
        if ($need !== '' && is_array($pc)) {
            foreach ((array) ($pc['equip'] ?? []) as $slot => $it) {                  // 部位装备（主结构）
                $iid = is_array($it) ? ($it['id'] ?? ($it['item'] ?? '')) : $it;
                if ($iid === $need) { $worn = true; break; }
            }
            if (!$worn) foreach ((array) ($pc['worn'] ?? []) as $iid) if ($iid === $need) $worn = true;
        }
        $out[] = '⚠ ' . $R['note'];
        if ($need !== '' && !$worn) {
            $out[] = '⚠ **你身上没有' . crrg_drill_item_name($need) . '**——进去会掉理智、掉血。先到装备整备室取并「穿戴」。';
        }
    }
    return $out;
}
/** 高风险区表（房间 → 提示与所需装备） */

function crrg_drill_map_lines($s) {
    $rooms = crrg_drill_rooms();
    $floor = $rooms[$s['room']]['f'];
    $out = ['〔' . ($floor === '—' ? '竖向交通' : $floor . ' 区域 · 已确认') . '〕'];
    foreach ($rooms as $k => $v) {
        if ($v['f'] !== $floor) continue;
        $mark = ($k === $s['room']) ? '◆' : '　';
        $dst = $v['x'][$k] ?? '';
        $need = $dst !== '' ? crrg_drill_gate_of($dst) : 1;
        $out[] = $mark . $v['t'] . '（' . ($need > 1 ? crrg_drill_card_name($need) . ' 级' : '自由通行') . '）';
    }
    $out[] = '竖向：主楼梯（B1↔B2↔B3）、专用楼梯（B1↔B4）、独立楼梯（B1 东端↔驻防区）、内部楼梯（档案室↔研究区）、快速反应通道（驻防区↔车库）、货运电梯（B4 转运走廊↔B3 货运通道）';
    return $out;
}

function crrg_drill_look_at($s, $ctx, $arg) {
    $rooms = crrg_drill_rooms();
    $rm = $rooms[$s['room']];
    // 先认房间里可交互的人／物（左侧面板点出来的就是这些名字）
    $desc = crrg_drill_ent_desc();
    foreach ((crrg_drill_ents()[$s['room']] ?? []) as $e) {
        $n = $e['n'];
        $a = str_replace(' ', '', $arg);
        $b = str_replace(' ', '', $n);
        if (mb_strpos($b, $a) !== false || mb_strpos($a, $b) !== false) {
            return ['〔' . $n . '〕' . ($desc[$n] ?? '摆在这里，没什么可说的。')];
        }
    }
    foreach (($rm['x'] ?? []) as $k => $to) {
        if ($k === '刷卡') continue;
        if (mb_strpos($k, $arg) !== false || mb_strpos($arg, $k) !== false) {
            $dst = $rooms[$to];
            return ['〔' . $dst['t'] . '〕' . $dst['d'],
                    '门禁：' . (($dn = crrg_drill_gate_of($v['x'][$k] ?? '')) > 1 ? crrg_drill_card_name($dn) . ' 级（你持 ' . $ctx['card'] . ' 级）' : '自由通行'),
                    '可往：' . implode('、', array_keys(array_filter($dst['x'], fn($k) => $k !== '刷卡', ARRAY_FILTER_USE_KEY) ?: ['—']))];
        }
    }
    /* 2026-10-01：《封条》线的**现场人/物**也要能看（玩家报「查看 冒充者」没反应 ✗）
       数据源与"现场"那段一致（节点表 ✓）→ 节点名/线索名都能对上 ✓ */
    if (function_exists('crrg_seal_paths_full')) {
        foreach ((array) crrg_seal_paths_full() as $nd => $ps) {
            $nd = (string) $nd;
            if ($nd === '' || $arg === '') continue;
            if (mb_strpos($nd, $arg) === false && mb_strpos($arg, $nd) === false) continue;
            $o = ['〔' . $nd . '〕'];
            /* 2026-10-01（委员长要求 ✓）：**别让玩家敲指令** —— 直接弹「现场处置」窗，列出全部法子 ✓
               （look_at 不是引用传参 ✗ → 用全局通道 ✓） */
            if (function_exists('crrg_seal_paths_full') && !empty(crrg_seal_paths_full()[$nd])) {
                $GLOBALS['crrg_ui_open'] = 'paths:' . $nd;
            }
            $sp = function_exists('crrg_seal_spot_text') ? (string) crrg_seal_spot_text($nd) : '';
            if ($sp !== '') $o[] = $sp;
            if (function_exists('crrg_seal_clue_text')) foreach ((array) crrg_seal_clue_text($nd) as $l) $o[] = $l;
            if (count($o) === 1) $o[] = '（在这儿看得见——想细查就用「调查 ' . $nd . '」。）';
            return $o;
        }
    }
    /* 2026-10-01：**NPC 统一定义**（属性/性格/作息/特殊出现/对话/功能 ✓）——模板看得见 ✓ */
    if (function_exists('crrg_npc_def') && $arg !== '') {
        foreach (crrg_npc_all_ids() as $nid) {
            $dfn = crrg_npc_def($nid);
            $nm2 = (string) ($dfn['name'] ?? '');
            if ($nm2 === '' || $arg === '') continue;
            if (mb_strpos($nm2, $arg) === false && mb_strpos($arg, $nm2) === false) continue;
            $o = ['〔' . $nm2 . '〕' . ($dfn['role'] !== '' ? '　' . $dfn['role'] : '')];
            if (!empty($dfn['personality'])) $o[] = '性格：' . $dfn['personality'];
            if (!empty($dfn['schedule'])) {
                $sc = [];
                foreach ((array) $dfn['schedule'] as $ph => $rm) { $rn = function_exists('crrg_npc_room_name') ? crrg_npc_room_name($rm) : $rm; $sc[] = $ph . '在' . $rn; }
                $o[] = '作息：' . implode('　', $sc);
            }
            if (!empty($dfn['visits'])) foreach ((array) $dfn['visits'] as $vi) $o[] = '（剧情出现：' . (string) ($vi['room'] ?? '') . '，条件 ' . (string) ($vi['flag'] ?? '') . '）';
            if (!empty($dfn['func']))   $o[] = '功能：' . implode('、', array_keys((array) $dfn['func']));
            if (!empty($dfn['daily']))  $o[] = '能聊：' . count((array) $dfn['daily']) . ' 个话题（用「问 ' . $nm2 . '」）';
            return $o;
        }
    }
    /* 2026-10-01（第二路）：**能"细问"的人/物**也要能"查看" —— 玩家报「查看 冒充者」没反应 ✗
       （《封条》的"细问"目标不在节点表里 ✓，所以上面那路抓不到 ✓）→ 这里列出他能回答的话题 ✓ */
    foreach (['crrg_seal3_npc_ask', 'crrg_seal2_npc_ask', 'crrg_seal_npc_ask'] as $fnA) {
        if (!function_exists($fnA) || $arg === '') continue;
        $rA = $fnA($s, $pc, $arg, '');
        if ($rA === null) continue;
        $o = ['〔' . $arg . '〕'];
        foreach ((array) $rA as $l) $o[] = (string) $l;
        $o[] = '（可以「细问 ' . $arg . ' <话题>」；用「问 ' . $arg . '」也行。）';
        return $o;
    }
    /* 全局关键词回退：凡是说明表里有条目的，按名字模糊匹配都能看（如「班次」对应售票窗口） */
    foreach ($desc as $k => $v) {
        if ($k === '' || mb_strlen($arg) < 2) continue;
        if (mb_strpos($k, $arg) !== false || mb_strpos($arg, $k) !== false) {
            return ['〔' . $k . '〕' . $v];
        }
    }
    $cn = [
        '防护服' => ['T3 级防护服：面罩校验有效，密封条完好。', '负二层装备整备室的货。'],
        '记录板' => ['薄板夹着一张表格，栏位：时间、地点、读数、签名。', '领任务时发的。'],
        '闸门' => ['三道气密闸门，分别在入口、中部、出口；每次刷卡开启十五秒。', '负四层缓冲区。'],
        '热成像仪' => ['红外热成像仪，架在 C1 廊道入口，屏幕是暗的。', 'C1 入口。'],
        '储存柜' => ['C2 廊道入口的储存柜，柜门分上下两格，上格空着。', 'C2 入口。'],
        '绞碎装置' => ['单独一间，设备用螺栓固定在地面上，操作面板没有通电。', 'C3 廊道中部。'],
        '自毁装置' => ['终端屏幕黑着，只有一颗绿灯慢闪。授权人限定。', 'C3 廊道末端。'],
        '时间车' => ['蒙着防尘布，布下露出一个圆形舱口。封存状态，未启动。', '车库东侧封存隔间。'],
        '武器柜' => ['应急武器柜，柜门贴封条，编号与轮值小队对应。', '快速反应通道拐角。'],
        '地图' => ['全国及周边区域大幅地图，标注为手绘，边角写着更新日期。', '负二层地图室。'],
        '共振装置' => ['低强度「思」之共振装置，外壳上有铭牌，处于未开机状态。', '负二层秘术研究室。'],
        '六爻手札' => ['《六爻手札》电子扫描件，存在一台不联网的终端里。', '负二层秘术研究室。'],
    ];
    foreach ($cn as $k => $v) if (mb_strpos($arg, $k) !== false || mb_strpos($k, $arg) !== false) return $v;
    return ['这里看不到「' . $arg . '」这个目标。'];
}

/* ---------------- AJAX ---------------- */
add_action('wp_ajax_crrg_drill_cmd', 'crrg_drill_ajax_cmd');

function crrg_drill_pos() {
    static $cache = [];
    $sc = crrg_drill_cur_scene();
    if (isset($cache[$sc])) return $cache[$sc];
    $p = [
        'b1_tunnel' => [6, 40], 'b1_door1' => [30, 40], 'b1_guard1' => [33, 48],
        'b1_cor_w' => [62, 40], 'b1_cor_m' => [100, 40], 'b1_cor_e' => [152, 40],
        'b1_med' => [62, 26], 'b1_meet_big' => [88, 26], 'b1_meet_small' => [112, 26],
        'b1_train' => [96, 18],
        'b1_canteen' => [172, 14], 'b1_office' => [72, 58], 'b1_chief_office' => [176, 58],
        'b1_dorm' => [150, 66], 'b1_room' => [150, 74], 'b1_bath' => [122, 62],
        'b1_archive' => [192, 74], 'b1_door2' => [196, 40], 'b1_evac_e' => [208, 40],
        'stair_main' => [100, 50], 'stair_c' => [114, 44], 'stair_f' => [188, 40], 'ext_east' => [232, 40],
        'b2_junction' => [100, 50], 'b2d_cor' => [38, 40], 'b2d_sample' => [24, 60],
        'b2d_chem' => [24, 40], 'b2d_iso' => [24, 20], 'b2d_bio' => [52, 60], 'b2d_eso' => [52, 20],
        'stair_archive' => [38, 8], 'b2b_cor' => [100, 30], 'b2b_map' => [78, 44],
        'b2b_comm' => [100, 44], 'b2b_tech' => [126, 44], 'b2b_intel' => [86, 16],
        'b2b_hall' => [116, 14], 'b2b_duty' => [142, 14], 'b2f_cor' => [166, 40],
        'b2f_brief' => [156, 54], 'b2f_barracks' => [174, 54], 'b2f_armory' => [154, 26],
        'b2f_gear' => [168, 26], 'b2f_gym' => [184, 26], 'b2f_sim' => [196, 26], 'qrf' => [206, 40],
        'b3_entry' => [40, 40], 'b3_cor_e' => [150, 40], 'b3_air' => [52, 22],
        'b3_water' => [82, 22], 'b3_power' => [112, 22], 'b3_gen' => [132, 25], 'b3_batt' => [146, 25],
        'b3_warehouse' => [100, 62], 'b3_field_gear' => [100, 54], 'b3_armory' => [186, 56],
        'ftunnel_gate' => [190, 40], 'b3_freight' => [212, 40], 'freight_tunnel' => [228, 40],
        'garage_car' => [238, 40], 'garage_maint' => [252, 40], 'garage_seal' => [266, 40],
        'garage_exit' => [278, 40],
        'b4_buffer' => [100, 64], 'b4_obs' => [100, 48], 'b4_main' => [100, 34],
        'b4_guard' => [156, 34], 'b4_c1' => [70, 16], 'b4_c2' => [96, 16], 'b4_c3' => [122, 16],
        'b4_transfer' => [96, 4], 'b4_evac' => [56, 4], 'freight_lift' => [44, 4], 'ext_south' => [56, 0],
    ];
    foreach (crrg_drill_scene_pos() as $k => $v) $p[$k] = $v;
    $cache[$sc] = $p;
    return $p;
}

/* 房间里可交互的物品 / 人物：n=显示名，k=item|npc，c=点击后执行的指令 */

function crrg_drill_arrow($from, $to) {
    $rooms = crrg_drill_rooms();
    $pos   = crrg_drill_pos();
    $order = ['B4' => 1, 'B3' => 2, 'B2' => 3, 'B1' => 4, '车库' => 5, '野外' => 6, '—' => 0];
    $vkeys = ['stair_main', 'stair_c', 'stair_f', 'stair_archive', 'qrf', 'freight_lift', 'freight_tunnel'];
    $ff = $rooms[$from]['f'] ?? '—';
    $tf = $rooms[$to]['f'] ?? '—';
    if ($ff !== $tf) {
        $a = $order[$ff] ?? 9;
        $b = $order[$tf] ?? 9;
        return $b > $a ? '↓ 下' : '↑ 上';
    }
    if (in_array($to, $vkeys, true) || in_array($from, $vkeys, true)) return '⇅ 竖向';
    if (!isset($pos[$from], $pos[$to])) return '· 邻接';
    $dx = $pos[$to][0] - $pos[$from][0];
    $dy = $pos[$to][1] - $pos[$from][1];
    $h  = abs($dx) < 12 ? '' : ($dx > 0 ? '东' : '西');
    $v  = abs($dy) < 12 ? '' : ($dy > 0 ? '北' : '南');
    $map = ['' => '· 邻接', '东' => '→ 东', '西' => '← 西', '北' => '↑ 北', '南' => '↓ 南',
            '东北' => '↗ 东北', '西北' => '↖ 西北', '东南' => '↘ 东南', '西南' => '↙ 西南'];
    return $map[$h . $v] ?? '· 邻接';   // 键序 h+v：东/西在前，北/南在后
}

/* 距离说明：同层按坐标算直线距离取整到 5 米；跨层写「跨层」 */

function crrg_drill_dist($from, $to) {
    $rooms = crrg_drill_rooms();
    $pos   = crrg_drill_pos();
    if (!isset($pos[$from], $pos[$to])) return '';
    if (($rooms[$from]['f'] ?? '') !== ($rooms[$to]['f'] ?? '')) return '跨层';
    $dx = $pos[$to][0] - $pos[$from][0];
    $dy = $pos[$to][1] - $pos[$from][1];
    $m  = (int) (round(sqrt($dx * $dx + $dy * $dy) / 5) * 5);
    return $m > 0 ? '约 ' . $m . ' 米' : '同处';
}

/* 同层方位图：◎ 你　1-9＝右栏「可往」列表的编号　· 同层其他
   横轴每格 7 米、纵轴每格 12 米，使格子接近正方（等宽字符高宽比约 1:0.6） */

function crrg_drill_map_ascii($key, $marks = []) {
    $rooms = crrg_drill_rooms();
    $pos   = crrg_drill_pos();
    if (!isset($pos[$key])) return ['（本处无方位数据）'];
    $floor = $rooms[$key]['f'];
    $W = 30; $H = 7;
    $sx = fn($x) => max(0, min($W - 1, (int) round($x / 7)));
    $sy = fn($y) => max(0, min($H - 1, 6 - (int) round($y / 12)));
    $g = array_fill(0, $H, array_fill(0, $W, ' '));
    foreach ($rooms as $k => $v) {
        if ($k === $key || $v['f'] !== $floor || !isset($pos[$k])) continue;
        $g[$sy($pos[$k][1])][$sx($pos[$k][0])] = '·';
    }
    // 相邻关系：同行走 ─、同列走 │
    foreach ($rooms[$key]['x'] as $n => $to) {
        if ($n === '刷卡' || !isset($pos[$to]) || $rooms[$to]['f'] !== $floor) continue;
        $x1 = $sx($pos[$key][0]); $y1 = $sy($pos[$key][1]);
        $x2 = $sx($pos[$to][0]);  $y2 = $sy($pos[$to][1]);
        if ($y1 === $y2) { for ($c = min($x1, $x2) + 1; $c < max($x1, $x2); $c++) $g[$y1][$c] = '─'; }
        elseif ($x1 === $x2) { for ($r = min($y1, $y2) + 1; $r < max($y1, $y2); $r++) $g[$r][$x1] = '│'; }
    }
    foreach ($rooms[$key]['x'] as $n => $to) {
        if (!isset($pos[$to]) || $rooms[$to]['f'] !== $floor) continue;
        $g[$sy($pos[$to][1])][$sx($pos[$to][0])] = isset($marks[$to]) ? (string) $marks[$to] : '○';
    }
    $g[$sy($pos[$key][1])][$sx($pos[$key][0])] = '◎';
    $out = ['　　↑北'];
    foreach ($g as $row) $out[] = '　' . rtrim(implode('', $row));
    $out[] = '　　◎你　1-9＝可往列表编号　·同层其他　─│相邻';
    return $out;
}

/* 同房间的其他在线玩家 */

function crrg_drill_presence($uid, $room) {
    /* 2026-10-01：返回**结构化**（id/name/role ✓）—— 左栏据此把"同在此处的人"做成**可点** ✓
       （从前只回字符串 ✗ → 玩家只能看着名字，做不了任何事 ✗） */
    $out = [];
    foreach (get_users(['fields' => ['ID', 'display_name']]) as $u) {
        if ((int) $u->ID === (int) $uid) continue;
        $st = get_user_meta($u->ID, CRRG_DRILL_META, true);
        if (is_array($st) && ($st['room'] ?? '') === $room) {
            $out[] = ['id' => (int) $u->ID,
                      'name' => (string) $u->display_name,
                      'role' => (string) (crrg_drill_ctx($u->ID)['rank_name'] ?? ''),
                      'label' => (string) $u->display_name . '（' . (crrg_drill_ctx($u->ID)['rank_name'] ?? '') . '）'];
        }
    }
    return $out;
}

/* 汇总视图：左栏实体 / 右栏方位 / 角色卡 / 背包 / 同房间动态 */

function crrg_drill_ent_desc_base() {
    return [
        '顶部管道' => '一根接一根，沿顶棚排到看不见的地方。接头处凝着水汽，偶尔滴一滴。',
        '手轮式闭锁' => '手轮比手掌大一圈，轮缘的漆磨没了。顺时针是三圈半，逆时针两圈——值班员特意说过。',
        '执勤值班员' => '特殊勤务司轮值人员，抬了下头，问了一句「演习还是真的」。',
        '监控屏' => '十六格画面，大半是走廊和防爆门，画面里没有人。',
        '观察床位' => '两张，床单是新的。床头卡挂着一支没摘的笔。',
        '药品柜' => '烫伤膏、绷带、两瓶生理盐水，最下层有一盒没拆封的口服补液盐。',
        '祁连山地形图' => '挂满整面墙，等高线画得极密，几处山谷用红笔画了圈。',
        '投影仪' => '镜头盖着，线被人拔了没插回去。',
        '便签本' => '撕了一半，最上面一页写着「十五分钟」，被圈了两次。',
        '书架' => '三层，多是手册和旧杂志，有一本被人翻得起了毛边。',
        '保温台' => '还热着，两个菜。窗口贴着本周菜单。',
        '打印机' => '卡纸提示闪了一上午，纸盒上贴着「卡纸请自己掏」。',
        '桌上文件' => '码得整整齐齐，最上面一份盖着红章。没有人在。',
        '两端门禁' => '两个门禁盒，进出都要刷卡；记录屏滚着最近十条。',
        '书桌' => '桌面空，只有一只杯子，水凉了。',
        '卷宗柜' => '按编号立着，抽出任何一卷都会带出一股防潮剂的味道。',
        '双人双锁' => '两把锁，钥匙分持。值班员说：需要两人同时在场，演习里单人开不了。',
        '出口伪装网' => '从里侧看是网，从外侧看是灌丛。掀开就是山坡。',
        '样本架' => '每格一张手写标签，编号与记录簿对得上。',
        '光谱仪' => '开机待用，屏幕上停在最后一次校准的界面。',
        '色谱仪' => '管路里还留着上一批的载气，压力表在绿区。',
        '记录簿' => '翻开着，最近一页的日期是三天前，签名只写了姓。',
        '远程机械手' => '悬在操作台正上方，四个角都有监控；操作杆上贴着「只用远程」。',
        '高温焚烧炉' => '炉门关着，侧面小窗透出一点余温，温度记录纸在走。',
        '《六爻手札》' => '电子扫描件存在一台不联网的终端里，翻页很慢，字是手写的。',
        '「思」之共振装置' => '低强度型号，未开机。铭牌上只有一串编号，没有说明。',
        '大幅地图' => '全国及周边区域，标注全是手绘的，边角写着更新日期。',
        '短波电台' => '频率表摊在旁边，用铅笔标了两个常用频点。',
        '秘术传讯装置' => '单独隔出的一小间，机壳比同屋的其他设备旧得多。',
        '特殊检测仪' => '外壳拆到一半，旁边放着一把没归位的螺丝刀。',
        '独立服务器' => '风扇声很稳，机柜上贴着「不接外网」。',
        '加密线路图' => '一张手抄的线路图，末端画着兰州的坐标。',
        '任务追踪屏' => '屏上三条线，两条已完成，一条标着你的名字，进度停在 0。',
        '值班员 · 领任务' => '指挥大厅值班员，手上压着一叠演习脚本。有事找他。',
        '值班员 · 交报告' => '同一个值班员，另一件事：报告交给他，他记分。',
        '值班小队' => '三个人在交接班，没人说话，动作很快。',
        '加密终端' => '屏幕锁着，键盘上盖着防尘布。',
        '武器架' => '空的，架子上的编号牌一排排对得齐。',
        '保养台' => '油布铺开，台面上一层薄油，工具按型号排好。',
        'T3 防护服' => '挂在最里面一排，面罩校验日期是上个月，密封条完好。',
        '领取登记簿' => '摊开着，等你签字。上一行是三天前。',
        '声效控制台' => '现在关着灯，台面上的旋钮停在「山地·夜」。',
        '应急武器柜' => '柜门贴着封条，编号与轮值小队对应，柜锁未开。',
        '过滤机组' => '粗滤、高效、活性炭三道并联，压差表都停在绿区。',
        '净化管路' => '饮用与非饮用两路，路标用不同颜色缠了胶带。',
        '变压器' => '嗡鸣稳定，测温贴纸没有变色。',
        '柴油机组' => '未运转，缸体是凉的，油位在标线以上。',
        '电池柜' => '四组电池，指示灯全绿。',
        '分区货架' => '食品、药品、弹药、备用零件分列，货位卡手写，字迹各异。',
        '检测仪箱' => '箱面写着编号和归还日期，最上面一箱是空的。',
        '登记簿' => '挂在门内，翻开着，页码上压着一支笔。',
        '地面轨道' => '嵌在地面里，通向卷帘门；有一处轨缝里积着锈。',
        '三道气密闸门' => '入口、中部、出口各一道，刷一次开一道，十五秒后自动闭合。计数牌停在「三」。',
        '门框计数牌' => '三格，目前都是暗的。',
        '认知稳定性测试装置' => '手掌贴上去，指示灯由黄转绿即为通过。值班员说这项每年都要做。',
        '远程监控屏' => '六宫格切换，能看到三条收容廊和所有收容间——画面里都是空的。',
        '固定岗值守人员' => '不参与轮换之外的任务，只点了下头，没说话。',
        '红外热成像仪' => '架在入口，屏幕是暗的，镜头对着廊道深处。',
        '取样口' => '亮着绿灯，接口是标准件，旁边挂着一次性手套。',
        '入口储存柜' => '上下两格，上格空着，下格锁着。',
        '绞碎装置' => '单独一间，螺栓固定在地面，操作面板没有通电。',
        '自毁装置激活终端' => '屏幕黑着，只有一颗绿灯慢闪。授权人限定——这台不归别人碰。',
        '武装值守人员' => '两名，站在两端防爆气密门之间，枪在身前，目光跟着你走。',
        '轿厢吊环' => '焊了一排，其中两个挂着旧绳头。',
        '越野车与装甲车' => '越野四辆、物资运输车两辆、装甲车一辆，车头一律朝外，油量表都在半箱以上。',
        '维修设备' => '举升机、工具车、扭力扳手，地面有一圈旧油痕。',
        '时间车原型机' => '蒙着防尘布，布下露出一个圆形舱口。封存状态，铭牌被擦得很干净。',
    ];
}

/* ---------------- 面板动作入口（界面点击走这里，指令只是备用） ---------------- */
add_action('wp_ajax_crrg_drill_act', 'crrg_drill_ajax_act');

