<?php
/**
 * 河洛 · 虚拟演习终端（MUD 式基地模拟）
 * 依据《祁连山总基地内部结构描述》：主体东西 200m × 南北 80m，四层；车库为山体外独立掩体。
 * 每个账号 = 一个独立演习角色：身份取自职务，门禁按卡级；状态存 user_meta，互不影响。
 * 卡级：D=1（观察员/行动员） C=3（组长/处长） B=5（首席顾问） A=6（副总指挥/委员长）
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/drill-part-exec.php';   // 拆分块（2026-09-29）
require_once __DIR__ . '/drill-part-panels.php';   // 拆分块（2026-09-29）
require_once __DIR__ . '/drill-part-view.php';   // 拆分块（2026-09-29）
require_once __DIR__ . '/drill-part-core.php';   // 拆分块（2026-09-29）

const CRRG_DRILL_META = 'crrg_drill_state';

/* ---------------- 通行权限（白名单制） ----------------
 * 口径（委员长 2026-09-28 定）：**正常通行不设权限**，只有最重要的部分才查卡。
 * 下表之外的房间一律自由进出；表内为需授权的高危/涉密区域。
 */
function crrg_drill_mission_pass($pc, $target) {
    /* 2026-09-29：任务授权要带上"必经的过道"——负四层任何目标都必须先过缓冲区，
       否则玩家会被卡在楼梯口（《封条》第一段就这么卡住的）。 */
    /* 2026-10-01 修（玩家卡住 ✗）：负四层的**必经过道**要包含「主走廊」——
       否则任务要求进 C 区（如《封条》判门/补件 ✓）时，卡在观察室过不去 ✓（提示"需要 B 级授权"✓） */
    $transit = ['b4_buffer', 'b4_obs', 'b4_main'];
    foreach ([$pc['mission'] ?? '', $pc['mission2']['id'] ?? ''] as $mid) {
        if ($mid === '') continue;
        $m = crrg_drill_mission_any($mid);            // 跨场景查（续段可能属于别的场景）
        if (!$m) continue;
        $rooms = array_merge((array) ($m['pass_rooms'] ?? []), array_map(
            fn($st) => $st['room'] ?? ($st['hint_room'] ?? ''), (array) ($m['steps'] ?? [])));
        /* 2026-10-01 修（玩家报「任务在手也进不去该去的房间」✗，全量扫出 24 个任务有此缺口 ✓）：
           **任务自己指名的房间**（步骤 hint_room／room ＋ 接单／出发／进入房间）一律视为授权 ✓
           —— 一份真相：任务说"去哪儿"，通行就得到哪儿 ✓（不必每个任务手写 pass_rooms ✗） */
        foreach ([$m['brief_room'] ?? '', $m['depart_room'] ?? '', $m['entry_room'] ?? ''] as $x) if ($x !== '') $rooms[] = (string) $x;
        $rooms = array_values(array_filter($rooms, 'strlen'));
        $b4need = (bool) array_filter($rooms, fn($r) => strpos((string) $r, 'b4_') === 0);
        if ($b4need && in_array($target, ['b4_buffer', 'b4_obs', 'b4_main'], true)) return true;   // 必经过道 ✓
        foreach ($rooms as $r) if ($r === $target) return true;
        foreach ($transit as $t) if ($target === $t && in_array($t, $rooms, true)) return true;
        /* 2026-10-01（第二次修 ✗→✓，玩家给了三条证据 ✓）：
           上面只授权了"任务房"和写死的几条过道 ✗ —— 但**通往它们的竖向交通**（货运电梯／专用楼梯间／
           A/B/C 楼梯 ✓）也要 B 级 ✗ → 玩家仍卡在门外 ✓（「货运电梯需要 B 级授权，你的卡是 C 级」✓）。
           正解：**认路** ✓ —— 从要去的房间出发做 BFS，若能走到某个"任务房"，说明它是**必经中转** ✓ → 放行 ✓。
           （比手写白名单可靠 ✓：换了路线也不会漏 ✓） */
        if (!in_array($target, $rooms, true) && function_exists('crrg_drill_rooms')) {
            $all = crrg_drill_rooms();
            $q = [$target]; $seen = [$target => 1]; $hit = false;
            while ($q && count($seen) <= 60) {
                $cur = array_shift($q);
                if (in_array($cur, $rooms, true)) { $hit = true; break; }
                foreach ((array) ($all[$cur]['x'] ?? []) as $lb => $to) {
                    $lb = (string) $lb; $to = (string) $to;
                    if ($lb === '刷卡' || $to === '' || isset($seen[$to])) continue;
                    $seen[$to] = 1; $q[] = $to;
                }
            }
            if ($hit) return true;
        }
    }
    return false;
}

function crrg_drill_eff_lv($pc, $ctx = null) {
    $lv = (int) ($pc['clearance'] ?? 0);
    if ($ctx === null && function_exists('crrg_drill_ctx')) $ctx = null;   // 由调用方传入更省
    if (is_array($ctx)) $lv = max($lv, (int) ($ctx['lv'] ?? 0));
    return $lv;
}
/** 有效卡级名称（D／C／B…） */
function crrg_room_risk($room) {
    $M = [
        'b4_c1' => ['note' => 'C1 信息污染型收容廊：认知污染风险，无防护进入会持续掉理智。', 'gear' => 'gear_hazmat'],
        'b4_c2' => ['note' => 'C2 生物危害型收容廊：生物气溶胶风险，必须穿 T3 防护服。', 'gear' => 'gear_hazmat'],
        'b4_c3' => ['note' => 'C3 专用收容廊：机密级别最高，进入即强制理智检定。', 'gear' => 'gear_hazmat'],
        'b4_transfer' => ['note' => '转运走廊：转运动线，随时可能有趟运任务穿过。'],
        'tw_tomb'   => ['note' => '老陵：夜间独行有遭遇风险，建议带照明与同伴。'],
        'tw_tunnel' => ['note' => '地下隧道：结构不稳，深处无人值守。'],
    ];
    return $M[$room] ?? null;
}
/** 面板分组：把一堆卡片收成几组，避免右侧越堆越长 */
function crrg_drill_ents_base() {
    return [
        'b1_tunnel' => [['n' => '顶部管道', 'k' => 'item', 'c' => '查看 管道']],
        'b1_door1' => [['n' => '手轮式闭锁', 'k' => 'item', 'c' => '查看 闭锁']],
        'b1_guard1' => [['n' => '执勤值班员', 'k' => 'npc', 'c' => '查看 值班员'],
                        ['n' => '监控屏', 'k' => 'item', 'c' => '查看 监控屏']],
        'b1_med' => [['n' => '观察床位', 'k' => 'item', 'c' => '查看 床位'],
                     ['n' => '药品柜', 'k' => 'item', 'c' => '查看 药品柜']],
        'b1_meet_big' => [['n' => '祁连山地形图', 'k' => 'item', 'c' => '查看 地形图'],
                          ['n' => '投影仪', 'k' => 'item', 'c' => '查看 投影仪']],
        'b1_meet_small' => [['n' => '便签本', 'k' => 'item', 'c' => '查看 便签']],
        'b1_canteen' => [['n' => '书架', 'k' => 'item', 'c' => '查看 书架'],
                         ['n' => '保温台', 'k' => 'item', 'c' => '查看 保温台']],
        'b1_office' => [['n' => '打印机', 'k' => 'item', 'c' => '查看 打印机']],
        'b1_chief_office' => [['n' => '桌上文件', 'k' => 'item', 'c' => '查看 文件']],
        'b1_dorm' => [['n' => '两端门禁', 'k' => 'item', 'c' => '查看 门禁']],
        'b1_room' => [['n' => '书桌', 'k' => 'item', 'c' => '查看 书桌']],
        'b1_archive' => [['n' => '卷宗柜', 'k' => 'item', 'c' => '查看 卷宗'],
                         ['n' => '双人双锁', 'k' => 'item', 'c' => '查看 锁']],
        'b1_evac_e' => [['n' => '出口伪装网', 'k' => 'item', 'c' => '查看 伪装网']],
        'b2d_sample' => [['n' => '样本架', 'k' => 'item', 'c' => '查看 样本']],
        'b2d_chem' => [['n' => '光谱仪', 'k' => 'item', 'c' => '查看 光谱仪'],
                       ['n' => '色谱仪', 'k' => 'item', 'c' => '查看 色谱仪'],
                       ['n' => '记录簿', 'k' => 'item', 'c' => '查看 记录簿']],
        'b2d_iso' => [['n' => '远程机械手', 'k' => 'item', 'c' => '查看 机械手']],
        'b2d_bio' => [['n' => '高温焚烧炉', 'k' => 'item', 'c' => '查看 焚烧炉']],
        'b2d_eso' => [['n' => '《六爻手札》', 'k' => 'item', 'c' => '查看 六爻手札'],
                      ['n' => '「思」之共振装置', 'k' => 'item', 'c' => '查看 共振装置']],
        'b2b_map' => [['n' => '大幅地图', 'k' => 'item', 'c' => '查看 地图']],
        'b2b_comm' => [['n' => '短波电台', 'k' => 'item', 'c' => '查看 电台'],
                       ['n' => '秘术传讯装置', 'k' => 'item', 'c' => '查看 传讯装置']],
        'b2b_tech' => [['n' => '特殊检测仪', 'k' => 'item', 'c' => '查看 检测仪']],
        'b2b_intel' => [['n' => '独立服务器', 'k' => 'item', 'c' => '查看 服务器'],
                        ['n' => '加密线路图', 'k' => 'item', 'c' => '查看 线路图']],
        'b2b_hall' => [['n' => '值班员 · 领任务', 'k' => 'npc', 'c' => '领任务'],
                       ['n' => '值班员 · 交报告', 'k' => 'npc', 'c' => '交报告'],
                       ['n' => '任务追踪屏', 'k' => 'item', 'c' => '查看 追踪屏']],
        'b2b_duty' => [['n' => '值班小队', 'k' => 'npc', 'c' => '查看 值班小队']],
        'b2f_brief' => [['n' => '加密终端', 'k' => 'item', 'c' => '查看 终端']],
        'b2f_barracks' => [['n' => '武器架', 'k' => 'item', 'c' => '查看 武器架']],
        'b2f_armory' => [['n' => '保养台', 'k' => 'item', 'c' => '查看 保养台']],
        'b2f_gear' => [['n' => 'T3 防护服', 'k' => 'item', 'c' => '取 防护服'],
                       ['n' => '领取登记簿', 'k' => 'item', 'c' => '查看 登记簿']],
        'b2f_sim' => [['n' => '声效控制台', 'k' => 'item', 'c' => '查看 控制台']],
        'qrf' => [['n' => '应急武器柜', 'k' => 'item', 'c' => '查看 武器柜']],
        'b3_air' => [['n' => '过滤机组', 'k' => 'item', 'c' => '查看 过滤机组']],
        'b3_water' => [['n' => '净化管路', 'k' => 'item', 'c' => '查看 管路']],
        'b3_power' => [['n' => '变压器', 'k' => 'item', 'c' => '查看 变压器']],
        'b3_gen' => [['n' => '柴油机组', 'k' => 'item', 'c' => '查看 机组']],
        'b3_batt' => [['n' => '电池柜', 'k' => 'item', 'c' => '查看 电池柜']],
        'b3_warehouse' => [['n' => '分区货架', 'k' => 'item', 'c' => '查看 货架']],
        'b3_field_gear' => [['n' => '检测仪箱', 'k' => 'item', 'c' => '查看 检测仪箱']],
        'b3_armory' => [['n' => '登记簿', 'k' => 'item', 'c' => '查看 登记簿']],
        'b3_freight' => [['n' => '地面轨道', 'k' => 'item', 'c' => '查看 轨道']],
        'b4_buffer' => [['n' => '三道气密闸门', 'k' => 'item', 'c' => '刷卡'],
                        ['n' => '门框计数牌', 'k' => 'item', 'c' => '查看 计数牌']],
        'b4_obs' => [['n' => '认知稳定性测试装置', 'k' => 'item', 'c' => '测试'],
                     ['n' => '远程监控屏', 'k' => 'item', 'c' => '查看 监控屏']],
        'b4_guard' => [['n' => '固定岗值守人员', 'k' => 'npc', 'c' => '查看 值守人员']],
        'b4_c1' => [['n' => '红外热成像仪', 'k' => 'item', 'c' => '查看 热成像仪']],
        'b4_c2' => [['n' => '取样口', 'k' => 'item', 'c' => '取样'],
                    ['n' => '入口储存柜', 'k' => 'item', 'c' => '查看 储存柜']],
        'b4_c3' => [['n' => '绞碎装置', 'k' => 'item', 'c' => '查看 绞碎装置'],
                    ['n' => '自毁装置激活终端', 'k' => 'item', 'c' => '激活']],
        'b4_transfer' => [['n' => '武装值守人员', 'k' => 'npc', 'c' => '查看 值守人员']],
        'freight_lift' => [['n' => '轿厢吊环', 'k' => 'item', 'c' => '查看 吊环']],
        'garage_car' => [['n' => '越野车与装甲车', 'k' => 'item', 'c' => '查看 越野车']],
        'garage_maint' => [['n' => '维修设备', 'k' => 'item', 'c' => '查看 维修设备']],
        'garage_seal' => [['n' => '时间车原型机', 'k' => 'item', 'c' => '查看 时间车']],
    ];
}

/* 朝向符号：同层按坐标，跨层/竖向通道给 上/下/竖向 */
function crrg_drill_view($s, $ctx, $uid, $pc = null) {
    if (!is_array($pc)) $pc = crrg_coc_default_pc();
    $pc = crrg_drill_pc_defaults($pc);
    crrg_drill_set_scene($pc['scene'] ?? 'base');
    crrg_drill_sync_room($s, $pc);
    $rooms = crrg_drill_rooms();
    $key   = $s['room'];
    $rm    = $rooms[$key];
    $exits = [];
    $marks = [];
    $no    = 0;
    foreach ((array) ($rm['x'] ?? []) as $n => $to) {
        if ($n === '刷卡') continue;
        $dst = $rooms[$to];
        $no++;
        $exits[] = [
            'no'     => $no,
            'label'  => $n,
            'name'   => $dst['t'],
            'sym'    => crrg_drill_arrow($key, $to),
            'card'   => crrg_drill_card_name($dst['c']),
            'dist'   => crrg_drill_dist($key, $to),
            'locked' => (crrg_drill_eff_lv($pc, $ctx) < $dst['c']),      // 卡级单一来源（2026-09-29）
        ];
        if (($dst['f'] ?? '') === $rm['f']) $marks[$to] = $no;
    }
    /* 左栏实体：可捡物带剩余份数，捡完消失；地上的掉落物也列进来 */
    $ents_shown = [];
    foreach ((array) (crrg_drill_ents()[$key] ?? []) as $e) {
        if (!empty($e['take'])) {
            $left = crrg_drill_stock_get($key, $e['n'], crrg_drill_ent_stock($e));
            if ($left <= 0) continue;
            $e['n'] = $e['n'] . ' ×' . $left;
        }
        $ents_shown[] = $e;
    }
    foreach (crrg_drill_drops($key) as $id => $n) {
        $ents_shown[] = ['n' => '（地上的）' . crrg_drill_item_name($id) . ($n > 1 ? ' ×' . $n : ''),
                         'k' => 'item', 'c' => '取 ' . crrg_drill_item_name($id)];
    }
    /* 2026-10-01：《封条》线的"现场人/物"（含「冒充者」「车辙」这类）也列进左栏可点 ✓
       —— 玩家报"找了半天才找到"✗：原来它们只写在房间描述里 ✗ */
    if (function_exists('crrg_seal_paths_full') && function_exists('crrg_seal_node_room')) {
        /* 注意：**不按"当前任务范围"过滤** ✓ ——
           曾经过滤过 ✗：`tw_hillpath` 的节点挂的是上一段的旗标（`paper_车辙` 等 ✗），
           玩家带着本段任务（`s3_…`）时全被挡掉 → 只能"找半天" ✗（2026-10-01 玩家反馈 ✓）
           同房间的就该都看得见 ✓（≤6 条，不至于吵 ✓） */
        foreach ((array) crrg_seal_paths_full() as $nd => $ps) {
            if (crrg_seal_node_room($nd) !== $key) continue;
            if (function_exists('crrg_seal_path_done') && crrg_seal_path_done($s, $nd)) continue;
            $ents_shown[] = ['n' => (string) $nd . '（现场）', 'k' => 'item', 'c' => '调查 ' . (string) $nd];
        }
    }

    /* 2026-10-01 修：把**在场的人**也列进左栏（前端早就支持 k=npc ✓，只是服务端一直没给 ✗）
       —— 来源＝统一在场函数（基地作息 ＋ 对话注册表 ＋ 旗标驱动的任务人物 ✓）
       去重：老写法常在"房间实体表"里也登记一次同一个人（如「售货员」）✗ →
             与在场名单**同名或互相包含**的实体条目**丢掉** ✓，只留能「问」的那条 ✓ */
    if (function_exists('crrg_npc_present')) {
        $npcs = (array) crrg_npc_present($key, $s);
        if ($npcs) {
            foreach ($ents_shown as $i => $e) {
                $en = (string) ($e['n'] ?? '');
                if ($en === '') continue;
                foreach ($npcs as $nm => $d) {
                    $nm = (string) $nm;
                    if ($nm === '') continue;
                    if ($en === $nm || mb_strpos($en, $nm) !== false || mb_strpos($nm, $en) !== false) { unset($ents_shown[$i]); break; }
                }
            }
            $ents_shown = array_values($ents_shown);
            foreach ($npcs as $nm => $d) {
                $ents_shown[] = ['n' => (string) $nm, 'k' => 'npc', 'c' => '问 ' . (string) $nm];
            }
        }
    }

    /* 2026-10-22：**任务交互点**（委员长："没接这个任务，为什么会有选项"）
       只列"该任务对这张卡正在进行中"的交互点 ✓ 没接＝不出现 ✗
       —— 房间自己别再写死交互点，改在任务的 ents 字段里声明 ✓ */
    if (function_exists('crrg_task_ents')) {
        $pcRef = is_array($pc) ? $pc : [];
        foreach ((array) crrg_task_ents($s, $pcRef, $key) as $te) {
            if (empty($te['n']) || empty($te['c'])) continue;
            $ents_shown[] = ['n' => (string) $te['n'], 'k' => 'item', 'c' => (string) $te['c'],
                             'task' => (string) ($te['task'] ?? ''), 'why' => (string) ($te['why'] ?? '')];
        }
    }

    return [
        'title'   => (function_exists('crrg_dorm_title') && crrg_dorm_title($s) !== '') ? crrg_dorm_title($s) : $rm['t'],
        'floor'   => $rm['f'],
        'ents'    => array_values($ents_shown),
        'exits'   => $exits,
        'map'     => crrg_drill_map_ascii($key, $marks),
        'players' => (function_exists('crrg_dorm_presence') && crrg_dorm_presence($s, $uid, $key) !== null)
                        ? crrg_dorm_presence($s, $uid, $key)          // 2026-10-01：在宿舍里 → 只有该宿舍的人 ✓（自己的→没人 ✓）
                        : crrg_drill_presence($uid, $key),
        'score'   => crrg_drill_score($s),
        'gates'   => (int) ($s['gates'] ?? 0),
        'pc'      => [
            'ready'   => !empty($pc['ready']),
            'name'    => $pc['name'] ?: '未登记',
            'occ'     => $pc['occ'] ?: '—',
            'posture' => $pc['posture'] ?? '站立',
            'hp'      => (int) $pc['hp'],
            'san'     => (int) $pc['san'],
            'sheet'   => crrg_drill_pc_lines($pc),
        ],
        'bag'     => array_map('crrg_drill_item_name', (array) ($pc['items'] ?? [])),
        'worn'    => array_map('crrg_drill_item_name', (array) ($pc['worn'] ?? [])),
        'feed'    => (($s['feed_room'] ?? '') === $key)
                     ? crrg_drill_feed_since($key, $s['feed_seen'] ?? 0) : [],
        'econ'    => [
            'credits'   => (int) ($pc['credits'] ?? 0),
            'money'     => (int) ($pc['money'] ?? 0),
            'clearance' => crrg_drill_clearance_name(crrg_drill_eff_lv($pc, $ctx)),
        ],
        'mission' => (!empty($pc['mission']) && ($__m = crrg_drill_mission($pc['mission'])))
                     ? sprintf('%s（%d/%d）', $__m['name'], (int) $pc['mission_step'], count($__m['steps'])) : '',
        'scene'   => crrg_drill_cur_scene(),
        'worldmap' => (!empty($s['ui']['worldmap']) && function_exists('crrg_worldmap_svg')) ? crrg_worldmap_svg() : '',
        'worldmap_on' => !empty($s['ui']['worldmap']),
        'world'   => function_exists('crrg_world_clock') ? [
            'line'  => crrg_world_line(),
            'ymd'   => crrg_world_clock()['ymd'],
            'hm'    => crrg_world_clock()['hm'],
            'day'   => crrg_world_clock()['day'],
            'phase' => crrg_world_clock()['phase'],
            'night' => crrg_world_clock()['night'],
            'weather' => crrg_world_weather(crrg_drill_cur_scene()),
            'ts'      => crrg_world_clock()['ts'],
            'slot_end'=> crrg_world_slot_end(),
            'scale'   => 48,
            'tz_offset' => (int) round((float) get_option('gmt_offset', 0) * 3600),
            'next_hour_in' => crrg_world_next_hour_in(),
        ] : null,
        'art'     => function_exists('crrg_drill_roomart') ? crrg_drill_roomart($key) : null,
        'dorm'    => crrg_drill_dorm_lines($pc, $pc['name'] ?: '你'),
        'services' => crrg_drill_services($key, $pc),
        'panels'  => array_map('crrg_drill_panel_group', crrg_drill_panels($s, $ctx, $pc, $uid)),
        'pgroups' => crrg_drill_panel_groups(),
        'battle'  => function_exists('crrg_bt_view') ? crrg_bt_view($key, $uid) : null,
        'vehicle' => $pc['vehicle'] ?? '',
        'team'    => ($__t = crrg_drill_team_of($uid)) ? $__t['n'] : '',
    ];
}

/* 可交互物／人的说明文字（键＝实体显示名，与 crrg_drill_ents() 的 n 一致） */
